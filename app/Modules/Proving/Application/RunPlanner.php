<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use App\Infrastructure\Cache\ShortLivedCache;
use App\Infrastructure\EngineProcess\Engine;
use App\Infrastructure\EngineProcess\PlanPreview;
use App\Infrastructure\EngineProcess\PlanRequest;
use App\Infrastructure\EngineProcess\PlanResult;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Capture\Application\CaptureRefs;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Packs\Application\PackCatalog;
use App\Modules\Proving\Domain\Scenarios;
use App\Modules\Workspace\Contracts\OwnedProject;

/**
 * Server-side plan of a selection (contracts §3.2 `Runs/Draft` preview,
 * run `delivery_count`) through the engine sandbox. TM-18: engine calls are
 * rate-limited per principal and their answers cached by what they were
 * planned from (capture refs, orders, body and header hashes, scenarios,
 * pack id and version, has_secret, budget), so reloading a draft costs
 * nothing. Any failure is a code; callers keep working without a plan.
 */
final readonly class RunPlanner
{
    public const string RATE_LIMITED = 'rate_limited';

    public function __construct(
        private CaptureRefs $captures,
        private PackCatalog $packs,
        private Engine $engine,
        private ShortLivedCache $cache,
        private Throttle $throttle,
    ) {}

    /**
     * The draft preview over every scenario (the page sums the chosen ones).
     * Callers call it only for a selection without refusals.
     *
     * @param  list<int>  $seqs
     * @return array{preview: array<string, mixed>|null, previewError: string|null}
     */
    public function draftPreview(OwnedProject $project, Principal $principal, array $seqs): array
    {
        $result = $this->plan($project, $principal, $seqs, Scenarios::ALL);

        return ['preview' => $result->preview?->toArray(), 'previewError' => $result->error];
    }

    /**
     * The planned delivery count of a run being created, from the cached
     * draft preview when there is one (the sum of the chosen scenarios and
     * `once`, which the engine always plans, PLAN-2),
     * else from a fresh plan of the chosen scenarios; null when the engine
     * cannot say (the agent reports it, as before).
     *
     * @param  list<int>  $seqs
     * @param  list<string>  $scenarios
     */
    public function deliveryCount(OwnedProject $project, Principal $principal, array $seqs, array $scenarios): ?int
    {
        $seqs = array_values(array_unique($seqs));
        $meta = $this->captures->forPlanning($project->id, $seqs, false);
        $pack = $this->pack($meta);
        if ($pack !== null && count($meta) === count($seqs)) {
            $draft = $this->cached($this->key($meta, Scenarios::ALL, $pack));
            if ($draft?->preview !== null) {
                $sum = 0;
                foreach ($draft->preview->scenarios as $s) {
                    // PLAN-2: `once` is always planned, chosen or not.
                    $sum += $s['name'] === 'once' || in_array($s['name'], $scenarios, true) ? $s['deliveries'] : 0;
                }

                return $sum;
            }
        }
        $preview = $this->plan($project, $principal, $seqs, $scenarios)->preview;

        return $preview?->deliveryCount;
    }

    /**
     * @param  list<int>  $seqs
     * @param  list<string>  $scenarios
     */
    public function plan(OwnedProject $project, Principal $principal, array $seqs, array $scenarios): PlanResult
    {
        $seqs = array_values(array_unique($seqs));
        $meta = $this->captures->forPlanning($project->id, $seqs, false);
        $pack = $this->pack($meta);
        if ($meta === [] || count($meta) !== count($seqs) || $pack === null || $scenarios === []) {
            return PlanResult::failed('not_plannable');
        }
        $key = $this->key($meta, $scenarios, $pack);
        $hit = $this->cached($key);
        if ($hit !== null) {
            return $hit;
        }
        if (! $this->throttle->reserve('engine-plan:'.$principal->kind.':'.$principal->id, (int) config('engine.plan_per_minute'), 60)) {
            return PlanResult::failed(self::RATE_LIMITED);
        }
        $captures = [];
        foreach ($this->captures->forPlanning($project->id, $seqs, true) as $c) {
            $captures[] = ['ref' => $c['ref'], 'order' => $c['order'], 'headers' => $c['headers'], 'body' => (string) $c['body']];
        }
        if (count($captures) !== count($seqs)) {
            return PlanResult::failed('not_plannable');
        }
        $result = $this->engine->plan(new PlanRequest($captures, $scenarios, $pack['id'], $pack['version']));
        if (! $result->transient()) {
            $this->cache->put($key, $result->preview !== null ? ['preview' => $result->preview->toArray()] : ['error' => $result->error],
                (int) config('engine.plan_cache_seconds'));
        }

        return $result;
    }

    /**
     * @param  list<array{id: string, ref: string, provider: string|null, order: int, headers: list<array{0: string, 1: string}>, bodySha256: string, body: string|null}>  $meta
     * @return array{id: string, version: string}|null
     */
    private function pack(array $meta): ?array
    {
        if ($meta === []) {
            return null;
        }
        $providers = array_values(array_unique(array_map(fn (array $c): ?string => $c['provider'], $meta)));
        $id = $providers[0];
        if (count($providers) !== 1 || $id === null) {
            return null;
        }
        $version = $this->packs->pinnedVersion($id);

        return $version === null ? null : ['id' => $id, 'version' => $version];
    }

    /**
     * @param  list<array{id: string, ref: string, provider: string|null, order: int, headers: list<array{0: string, 1: string}>, bodySha256: string, body: string|null}>  $meta
     * @param  list<string>  $scenarios
     * @param  array{id: string, version: string}  $pack
     */
    private function key(array $meta, array $scenarios, array $pack): string
    {
        $material = [
            'captures' => array_map(fn (array $c): array => [$c['ref'], $c['order'], $c['bodySha256'],
                hash('sha256', json_encode($c['headers'], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE))], $meta),
            'scenarios' => $scenarios,
            'pack' => [$pack['id'], $pack['version']],
            'has_secret' => false,
            'budget_ms' => 0,
        ];

        return 'engine-plan:'.hash('sha256', json_encode($material, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function cached(string $key): ?PlanResult
    {
        $hit = $this->cache->get($key);
        if (! is_array($hit)) {
            return null;
        }
        if (is_string($hit['error'] ?? null)) {
            return PlanResult::failed($hit['error']);
        }
        $preview = PlanPreview::fromArray($hit['preview'] ?? null);

        return $preview === null ? null : PlanResult::ok($preview);
    }
}
