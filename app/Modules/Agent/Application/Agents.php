<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Agent\Contracts\AgentView;
use App\Modules\Agent\Domain\Agent;
use App\Modules\Agent\Domain\TerminalText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Agents for other modules (Proving: run targets, run pages; pages: the
 * agent summary). Read models only; the model never leaves the module.
 */
final readonly class Agents
{
    public function __construct(private Clock $clock) {}

    /**
     * `agentPill` (contracts §3.1): the workspace's most useful live agent —
     * connected first, then waiting for its local confirmation, then
     * disconnected — or `none`. `too_old` when that agent's build is below
     * `min_agent_version`.
     *
     * @return array{state: string, name?: string, version?: string}
     */
    public function pill(string $workspaceId): array
    {
        $now = $this->clock->now();
        $rank = ['connected' => 0, 'awaiting_confirmation' => 1, 'needs_reconfirm' => 1, 'disconnected' => 2];
        $best = null;
        foreach ($this->ofWorkspace($workspaceId) as $agent) {
            $state = $agent->displayState($now);
            if ($best === null || $rank[$state] < $rank[$best[1]]) {
                $best = [$agent, $state];
            }
        }
        if ($best === null) {
            return ['state' => 'none'];
        }
        [$agent, $state] = $best;
        $state = $state === 'needs_reconfirm' ? 'awaiting_confirmation' : $state;
        if (self::tooOld($agent->version)) {
            $state = 'too_old';
        }

        return ['state' => $state, 'name' => $agent->name, 'version' => $agent->version];
    }

    /**
     * `AgentRow[]` (contracts §3.2 `/agents`): live agents, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $workspaceId): array
    {
        return array_map(fn (AgentView $a): array => $this->row($a), $this->ofWorkspace($workspaceId));
    }

    /**
     * `AgentDetail` of a live agent of the workspace, by `agt_` id or by name.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $workspaceId, string $idOrName): ?array
    {
        $uuid = Agent::uuidFromPublicId($idOrName);
        $query = Agent::query()->live()->where('workspace_id', $workspaceId);
        $agent = ($uuid === null ? null : (clone $query)->whereKey($uuid)->first()) ?? (clone $query)->where('name', $idOrName)->first();
        if ($agent === null) {
            return null;
        }
        $view = self::view($agent, $this->targets([$agent->id])[$agent->id] ?? []);
        $secrets = DB::table('agent_secret_names')->where('agent_id', $agent->id)->orderBy('provider')->orderBy('env_name')->get();

        return [
            ...$this->row($view),
            'arch' => $agent->arch,
            'targets' => $view->targets,
            'secretNames' => array_values(array_map(fn (object $r): array => ['provider' => (string) $r->provider, 'envName' => (string) $r->env_name], $secrets->all())),
        ];
    }

    /**
     * The guest workspace's agent for `/p/{key}/agent`: the confirmed one,
     * else the newest waiting for its confirmation.
     *
     * @return array<string, mixed>|null
     */
    public function guestDetail(string $workspaceId): ?array
    {
        $agents = $this->ofWorkspace($workspaceId);
        usort($agents, fn (AgentView $a, AgentView $b): int => (int) $b->confirmed <=> (int) $a->confirmed);

        return $agents === [] ? null : $this->detail($workspaceId, $agents[0]->publicId);
    }

    /** @return array<string, mixed> */
    private function row(AgentView $a): array
    {
        $now = $this->clock->now();

        return [
            'id' => $a->publicId, 'name' => $a->name, 'os' => (string) $a->os, 'keyFingerprint' => $a->keyFingerprint,
            'state' => $a->displayState($now), 'version' => $a->version, 'tooOld' => self::tooOld($a->version),
            'pairedAt' => $a->pairedAt->toIso8601ZuluString('millisecond'),
            'lastSeenAt' => $a->lastSeenAt?->toIso8601ZuluString('millisecond'),
            'revokeUrl' => '/agents/'.$a->publicId,
        ];
    }

    private static function tooOld(string $version): bool
    {
        return $version !== '0.0.0-dev' && TerminalText::versionBelow($version, (string) config('agent.min_agent_version'));
    }

    public function find(string $agentId): ?AgentView
    {
        $agent = Agent::query()->find($agentId);

        return $agent === null ? null : self::view($agent, $this->targets([$agent->id])[$agent->id] ?? []);
    }

    public function findPublic(string $publicId): ?AgentView
    {
        $uuid = Agent::uuidFromPublicId($publicId);

        return $uuid === null ? null : $this->find($uuid);
    }

    /**
     * Live (not revoked) agents of a workspace, newest first, with their target mirrors.
     *
     * @return list<AgentView>
     */
    public function ofWorkspace(string $workspaceId): array
    {
        $agents = Agent::query()->live()->where('workspace_id', $workspaceId)->orderByDesc('paired_at')->get();
        $targets = $this->targets(array_values(array_map(strval(...), $agents->pluck('id')->all())));

        return array_values($agents->map(fn (Agent $a): AgentView => self::view($a, $targets[$a->id] ?? []))->all());
    }

    /**
     * @param  list<string>  $agentIds
     * @return array<string, list<array{scheme: string, host: string, port: int, kind: string, declared: bool, approved: bool}>>
     */
    private function targets(array $agentIds): array
    {
        if ($agentIds === []) {
            return [];
        }
        $out = [];
        foreach (DB::table('agent_targets')->whereIn('agent_id', $agentIds)->orderBy('scheme')->orderBy('host')->orderBy('port')->get() as $t) {
            $out[(string) $t->agent_id][] = [
                'scheme' => (string) $t->scheme,
                'host' => (string) $t->host,
                'port' => (int) $t->port,
                'kind' => (string) $t->kind,
                'declared' => (bool) $t->declared,
                'approved' => $t->approved_at !== null,
            ];
        }

        return $out;
    }

    /** @param  list<array{scheme: string, host: string, port: int, kind: string, declared: bool, approved: bool}>  $targets */
    public static function view(Agent $agent, array $targets = []): AgentView
    {
        return new AgentView(
            id: $agent->id,
            publicId: $agent->publicId(),
            workspaceId: $agent->workspace_id,
            name: $agent->name,
            version: $agent->version,
            os: $agent->os,
            keyFingerprint: $agent->key_fingerprint,
            needsReconfirm: $agent->needs_reconfirm,
            confirmed: $agent->confirmed_at !== null,
            guestSlot: $agent->guest_slot,
            revoked: $agent->revoked_at !== null,
            revokedReason: $agent->revoked_reason,
            lastSeenAt: $agent->last_seen_at instanceof CarbonImmutable ? $agent->last_seen_at : null,
            pairedAt: $agent->paired_at,
            targets: $targets,
        );
    }
}
