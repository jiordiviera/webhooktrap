<?php

declare(strict_types=1);

use App\Infrastructure\EngineProcess\Engine;
use App\Infrastructure\EngineProcess\PlanPreview;
use App\Infrastructure\EngineProcess\PlanRequest;
use App\Infrastructure\EngineProcess\PlanResult;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once dirname(__DIR__).'/Agent/AgentHelpers.php';

/*
 * Server-side plan (EngineProcess): the draft preview through a fake engine
 * port, its errors, TM-18 rate limit and cache, and `delivery_count` stored
 * at launch.
 */

final class FakeEngine implements Engine
{
    /** @var list<PlanRequest> */
    public array $calls = [];

    public ?string $error = null;

    public function plan(PlanRequest $request): PlanResult
    {
        $this->calls[] = $request;
        if ($this->error !== null) {
            return PlanResult::failed($this->error);
        }
        // Two deliveries per scenario except `late` (n/a); `once` always planned (PLAN-2).
        $names = array_values(array_unique(['once', ...$request->scenarios]));
        $scenarios = array_map(fn (string $s): array => ['name' => $s, 'na' => $s === 'late' ? 'no_signed_timestamp' : null,
            'deliveries' => $s === 'late' ? 0 : 2], $names);

        return PlanResult::ok(new PlanPreview(array_sum(array_column($scenarios, 'deliveries')), str_repeat('c', 64), false,
            ['id' => $request->packId, 'version' => $request->packVersion], ['ms' => 10000, 'source' => 'documented'], $scenarios,
            array_map(fn (string $ref): array => ['ref' => $ref, 'eventType' => 'invoice.paid', 'primary' => true], $request->refs())));
    }
}

beforeEach(function (): void {
    $this->engine = new FakeEngine;
    app()->instance(Engine::class, $this->engine);
    $this->user = makeUser();
    $this->project = userProject($this->user);
    $this->seqs = [stripeCapture($this->project->id), stripeCapture($this->project->id, 'invoice.paid', 'evt_2')];
});

function draftUrl(string $key, array $seqs): string
{
    return "/p/{$key}/runs/new?from=".implode(',', $seqs);
}

it('shows the validated plan preview on the draft', function (): void {
    actAs($this->user);
    $this->get(draftUrl($this->project->key, $this->seqs))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Runs/Draft', false)
            ->where('refusals', [])->where('previewError', null)
            ->where('preview.deliveryCount', 16)->has('preview.scenarios', 9)
            ->where('preview.scenarios.3', ['name' => 'late', 'na' => 'no_signed_timestamp', 'deliveries' => 0])
            ->where('preview.pack', ['id' => 'stripe', 'version' => '1.0.0'])
            ->where('preview.events.0.ref', fn (string $ref): bool => str_starts_with($ref, 'cap_')));

    $request = $this->engine->calls[0];
    expect($this->engine->calls)->toHaveCount(1)
        ->and(array_column($request->captures, 'order'))->toBe($this->seqs)
        ->and($request->captures[0]['headers'])->toContain(['Content-Type', 'application/json'])
        ->and(json_decode($request->captures[0]['body'], true)['id'])->toBe('evt_1');
});

it('keeps the draft working with a null preview and a code when the engine fails', function (): void {
    $this->engine->error = PlanResult::UNAVAILABLE;
    actAs($this->user);
    $this->get(draftUrl($this->project->key, $this->seqs))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Runs/Draft', false)
            ->where('preview', null)->where('previewError', 'engine_unavailable')->has('selection', 2)->where('refusals', []));
});

it('does not plan a refused selection', function (): void {
    actAs($this->user);
    $this->get(draftUrl($this->project->key, [...$this->seqs, 99]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('refusals', ['unknown_capture'])->where('preview', null)->where('previewError', null));
    expect($this->engine->calls)->toBe([]);
});

it('serves a reload from the cache and plans nothing on partial reloads', function (): void {
    actAs($this->user);
    $this->get(draftUrl($this->project->key, $this->seqs))->assertOk();
    $this->get(draftUrl($this->project->key, $this->seqs))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('preview.deliveryCount', 16));
    $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Inertia\Inertia::getVersion(), 'X-Inertia-Partial-Component' => 'Runs/Draft',
        'X-Inertia-Partial-Data' => 'agent,agents,targets,refusals'])->get(draftUrl($this->project->key, $this->seqs))->assertOk()
        ->assertJsonMissingPath('props.preview');

    expect($this->engine->calls)->toHaveCount(1);
});

it('rate-limits engine calls per principal (TM-18)', function (): void {
    config(['engine.plan_per_minute' => 1]);
    $this->engine->error = PlanResult::BUSY; // transient: never cached
    actAs($this->user);
    $this->get(draftUrl($this->project->key, $this->seqs))->assertInertia(fn (Assert $page) => $page->where('previewError', 'busy'));
    $this->get(draftUrl($this->project->key, $this->seqs))->assertInertia(fn (Assert $page) => $page->where('previewError', 'rate_limited')->where('preview', null));

    expect($this->engine->calls)->toHaveCount(1);
});

it('stores the planned delivery count at launch from the cached draft preview', function (): void {
    $agent = pairedAgent(userPrincipal($this->user));
    actAs($this->user);
    $this->get(draftUrl($this->project->key, $this->seqs))->assertOk();
    $this->post("/p/{$this->project->key}/runs", ['from' => $this->seqs, 'scenarios' => ['late', 'burst'],
        'target' => ['kind' => 'agent', 'agentId' => $agent['id'], 'targetRef' => 'http://localhost:3000/webhooks/stripe'], 'failOn' => 'high'])
        ->assertRedirect();

    expect(DB::table('runs')->value('delivery_count'))->toBe(4)->and($this->engine->calls)->toHaveCount(1)
        ->and(nextJob($agent['token'])['delivery_count'])->toBe(4);
});

it('plans the chosen scenarios at launch when nothing is cached', function (): void {
    $agent = pairedAgent(userPrincipal($this->user));
    startRunAsUser($this->user, $this->project->key, $agent['id'], $this->seqs)->assertRedirect();

    expect(DB::table('runs')->value('delivery_count'))->toBe(16)->and($this->engine->calls[0]->scenarios)->toHaveCount(9);
});

it('launches with an unknown delivery count when the engine is down', function (): void {
    $this->engine->error = PlanResult::UNAVAILABLE;
    $agent = pairedAgent(userPrincipal($this->user));
    startRunAsUser($this->user, $this->project->key, $agent['id'], $this->seqs)->assertRedirect();

    expect(DB::table('runs')->value('delivery_count'))->toBe(0)
        ->and(nextJob($agent['token'])['delivery_count'])->toBeNull();
});
