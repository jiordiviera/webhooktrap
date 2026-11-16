<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\EntityId;
use App\Modules\Agent\Domain\Agent;
use App\Modules\Agent\Domain\Runner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Read model of paired agents and server runner tokens for the operator
 * panel (decision 74). Arrays only, from an explicit column list: never a
 * public key, fingerprint, token hash or secret name.
 */
final readonly class OperatorFleet
{
    public function __construct(private Clock $clock) {}

    /**
     * Paired agents, newest first.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function agents(int $page, int $perPage, bool $includeRevoked = false): array
    {
        $query = DB::table('agents')->when(! $includeRevoked, fn ($q) => $q->whereNull('revoked_at'));
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('paired_at')->orderBy('id')
            ->forPage(max(1, $page), max(1, min(100, $perPage)))
            ->get(['id', 'workspace_id', 'name', 'version', 'os', 'arch', 'proto', 'state', 'last_seen_at', 'paired_at', 'revoked_at', 'guest_slot']);

        return ['rows' => array_values($rows->map(fn (object $a): array => [
            'id' => EntityId::encode(Agent::ID_PREFIX, (string) $a->id),
            'workspace' => EntityId::encode('wsp', (string) $a->workspace_id),
            'name' => (string) $a->name,
            'version' => (string) $a->version,
            'platform' => trim(($a->os ?? '').'/'.($a->arch ?? ''), '/') ?: null,
            'proto' => (int) $a->proto,
            'state' => $a->revoked_at !== null ? 'revoked' : (string) $a->state,
            'guest' => (bool) $a->guest_slot,
            'last_seen_at' => self::time($a->last_seen_at),
            'paired_at' => self::time($a->paired_at),
        ])->all()), 'total' => $total];
    }

    /** @return list<array<string, mixed>> server runner tokens, newest first */
    public function runners(): array
    {
        return array_values(DB::table('runners')->orderByDesc('issued_at')
            ->select(['id', 'name', 'last4', 'binding_required', 'issued_at', 'version', 'last_seen_at', 'revoked_at'])
            ->selectRaw('array_to_json(allowed_cidrs)::text AS cidrs')
            ->get()
            ->map(fn (object $r): array => [
                'id' => EntityId::encode(Runner::ID_PREFIX, (string) $r->id),
                'name' => (string) $r->name,
                'last4' => (string) $r->last4,
                'cidrs' => self::cidrs($r->cidrs),
                'binding_required' => (bool) $r->binding_required,
                'version' => is_string($r->version) ? $r->version : null,
                'issued_at' => self::time($r->issued_at),
                'last_seen_at' => self::time($r->last_seen_at),
                'revoked' => $r->revoked_at !== null,
            ])->all());
    }

    /**
     * Versions in use by live agents and live runners.
     *
     * @return list<array{kind: string, version: string, count: int}>
     */
    public function versions(): array
    {
        $agents = DB::table('agents')->whereNull('revoked_at')
            ->selectRaw("'agent' AS kind, version, count(*) AS n")->groupBy('version');
        $runners = DB::table('runners')->whereNull('revoked_at')
            ->selectRaw("'runner' AS kind, coalesce(version, 'unknown') AS version, count(*) AS n")->groupByRaw("coalesce(version, 'unknown')");

        return array_values($agents->unionAll($runners)->get()
            ->sortBy([['kind', 'asc'], ['n', 'desc']])
            ->map(fn (object $v): array => ['kind' => (string) $v->kind, 'version' => (string) $v->version, 'count' => (int) $v->n])
            ->values()->all());
    }

    /**
     * Connected agents and runners seen recently, for the dashboard.
     *
     * @return array{agents_connected: int, agents_paired: int, runners_live: int, runners_polling: int}
     */
    public function counts(): array
    {
        $recent = $this->clock->now()->subMinutes(5);

        return [
            'agents_connected' => DB::table('agents')->whereNull('revoked_at')->where('state', 'connected')->count(),
            'agents_paired' => DB::table('agents')->whereNull('revoked_at')->count(),
            'runners_live' => DB::table('runners')->whereNull('revoked_at')->count(),
            'runners_polling' => DB::table('runners')->whereNull('revoked_at')->where('last_seen_at', '>=', $recent)->count(),
        ];
    }

    /** @return list<string> */
    private static function cidrs(mixed $raw): array
    {
        $list = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($list) ? array_values(array_map('strval', $list)) : [];
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->utc() : null;
    }
}
