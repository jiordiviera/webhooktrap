<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Agent\Contracts\AgentView;
use App\Modules\Agent\Domain\Agent;
use App\Modules\Agent\Domain\AgentRefusal;
use App\Modules\Agent\Domain\AgentVersions;
use App\Modules\Agent\Domain\PairingCode;
use App\Modules\Agent\Domain\TerminalText;
use App\Modules\Agent\Events\AgentConfirmed;
use App\Modules\Agent\Events\AgentRevoked;
use App\Modules\Agent\Events\AgentStateReported;
use App\Modules\Identity\Application\PrincipalLabels;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Proving\Application\RunJobs;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use Illuminate\Support\Facades\DB;

/**
 * Agent lifecycle (domain §5): `paired (needs_reconfirm) → confirmed →
 * connected ⇄ disconnected → revoked`. Confirmation is the local `[y/N]`;
 * for a guest workspace it revokes the previous agent in the same
 * transaction (A-13), and the agent the owner chose to replace when issuing
 * the code (AC-3). A same-name agent is never replaced: names collide into
 * a suffix at `pair`. Mirrors are stored only after the first confirmation
 * (RE-1). Claim moves agents (`AgentWorkspaceMove`).
 */
final readonly class AgentLifecycle
{
    public function __construct(
        private Clock $clock,
        private WorkspaceOwnership $workspaces,
        private PrincipalLabels $labels,
        private AgentBroadcasts $broadcasts,
        private RunJobs $jobs,
    ) {}

    public function confirm(AgentView $view): void
    {
        DB::transaction(function () use ($view): void {
            $agent = Agent::query()->live()->whereKey($view->id)->lockForUpdate()->first() ?? throw AgentRefusal::revoked('user');
            if (! $agent->needs_reconfirm) {
                return;
            }
            $owner = $this->workspaces->ownerOf($agent->workspace_id);
            $guest = $owner !== null && $owner->isGuest();
            // AC-3: only an owner-chosen replacement (bound to the code) and,
            // for a guest workspace, the one guest agent (A-13) are revoked.
            // A same-name agent is never replaced silently.
            $others = Agent::query()->live()->where('workspace_id', $agent->workspace_id)->whereKeyNot($agent->id)
                ->where('needs_reconfirm', false)
                ->where(function ($q) use ($guest, $agent): void {
                    $q->whereRaw('false');
                    if ($guest) {
                        $q->orWhere('guest_slot', true);
                    }
                    if ($agent->replaces_agent_id !== null) {
                        $q->orWhere('id', $agent->replaces_agent_id);
                    }
                })
                ->lockForUpdate()->get();
            $replaced = [];
            foreach ($others as $other) {
                $this->markRevoked($other, 'replaced');
                $replaced[] = $other->id;
            }
            // A name taken meanwhile by another confirmed agent keeps a suffix.
            $taken = Agent::query()->live()->where('workspace_id', $agent->workspace_id)->whereKeyNot($agent->id)
                ->where('needs_reconfirm', false)->pluck('name')->all();
            $agent->forceFill([
                'name' => Agent::freeName($agent->name, array_values(array_map(strval(...), $taken))),
                'needs_reconfirm' => false,
                'confirmed_at' => $agent->confirmed_at ?? $this->clock->now(),
                'guest_slot' => $guest,
                'replaces_agent_id' => null,
            ])->save();
            AgentConfirmed::dispatch($agent->id, $agent->workspace_id, $replaced);
            $this->broadcasts->toWorkspace($agent->workspace_id, 'agent.status', [
                'agent_id' => $agent->publicId(), 'state' => 'connected', 'version' => $agent->version,
                'too_old' => false, 'needs_reconfirm' => false,
                'replaced_agent_id' => $replaced === [] ? null : Agent::query()->find($replaced[0])?->publicId(),
            ]);
        });
    }

    /**
     * `hello` with a schema-valid body (closed: no `hint_last4`, S-8).
     *
     * @param  array{version: string, secrets: list<array{provider: string, env_name: string}>, targets: list<array{scheme: string, host: string, port: int, kind: string, declared: bool, approved: bool}>, probe_config_hash: string|null}  $body
     * @return array<string, mixed>
     */
    public function hello(AgentView $view, array $body): array
    {
        if ($view->needsReconfirm) {
            throw AgentRefusal::needsReconfirm();
        }
        if (AgentVersions::refused($body['version'], 'hello')) {
            throw AgentRefusal::tooOld();
        }
        $now = $this->clock->now();
        $changed = DB::transaction(function () use ($view, $body, $now): bool {
            $agent = Agent::query()->live()->whereKey($view->id)->lockForUpdate()->first() ?? throw AgentRefusal::revoked('user');
            $before = DB::table('agent_targets')->where('agent_id', $agent->id)->get();
            $approvedAt = $before->mapWithKeys(fn (object $t): array => [$t->scheme.'|'.$t->host.'|'.$t->port => $t->approved_at])->all();
            $mirror = fn (array $rows): array => collect($rows)
                ->map(fn (array $t): string => $t['scheme'].'|'.strtolower($t['host']).'|'.$t['port'].'|'.$t['kind'].'|'.(int) $t['declared'].'|'.(int) $t['approved'])
                ->sort()->values()->all();
            $stored = $before->map(fn (object $t): array => [
                'scheme' => (string) $t->scheme, 'host' => (string) $t->host, 'port' => (int) $t->port,
                'kind' => (string) $t->kind, 'declared' => (bool) $t->declared, 'approved' => $t->approved_at !== null,
            ])->all();
            $within = (int) config('agent.connected_within_seconds');
            $wasConnected = $agent->version === $body['version'] && $agent->last_seen_at !== null
                && $agent->last_seen_at->greaterThan($now->subSeconds($within));
            DB::table('agent_secret_names')->where('agent_id', $agent->id)->delete();
            DB::table('agent_targets')->where('agent_id', $agent->id)->delete();
            $secrets = [];
            foreach ($body['secrets'] as $s) {
                $secrets[$s['provider'].'|'.$s['env_name']] = ['agent_id' => $agent->id, 'provider' => $s['provider'], 'env_name' => $s['env_name']];
            }
            DB::table('agent_secret_names')->insert(array_values($secrets));
            $targets = [];
            foreach ($body['targets'] as $t) {
                $host = strtolower($t['host']);
                $key = $t['scheme'].'|'.$host.'|'.$t['port'];
                $targets[$key] = [
                    'agent_id' => $agent->id, 'scheme' => $t['scheme'], 'host' => $host, 'port' => $t['port'],
                    'kind' => $t['kind'], 'declared' => $t['declared'],
                    'approved_at' => $t['approved'] ? ($approvedAt[$key] ?? $now) : null,
                ];
            }
            DB::table('agent_targets')->insert(array_values($targets));
            $agent->forceFill([
                'version' => $body['version'],
                'state' => 'connected',
                'last_seen_at' => $now,
                'probe_config_hash' => $body['probe_config_hash'] === null ? null : (string) hex2bin($body['probe_config_hash']),
            ])->save();

            return ! $wasConnected || $mirror($stored) !== $mirror($body['targets']);
        });
        // The agent pages are live on `workspace.agents`: refresh them when
        // the agent came back or its targets or approvals changed.
        if ($changed) {
            $this->broadcasts->refreshWorkspace($view->workspaceId);
        }
        AgentStateReported::dispatch($view->id, $body['version']);
        $owner = $this->workspaces->ownerOf($view->workspaceId);

        return [
            'proto' => 1,
            // A09 decision 6: same value as pair's `server.kind`.
            'server' => ['kind' => AgentVersions::serverKind()],
            'guest' => $owner !== null && $owner->isGuest(),
            'needs_reconfirm' => false,
            'workspace' => ['label' => $owner === null ? '' : TerminalText::clean($this->labels->workspaceLabel($owner->kind, $owner->id))],
            'probe_display_policy' => (string) config('agent.probe_display_policy'),
            'min_agent_version' => (string) config('agent.min_agent_version'),
            'deprecation' => null,
            'pending_jobs' => $this->jobs->pendingCount($view->id),
            'signature_checks_pending' => 0,
        ];
    }

    /**
     * An agent accepted a browser run to this target: it approved the target
     * in its terminal first (TM-56), so the mirror shows it approved now
     * instead of at the next heartbeat `hello` (up to 20 s later).
     */
    public function targetApproved(string $agentId, string $scheme, string $host, int $port): void
    {
        $agent = Agent::query()->live()->whereKey($agentId)->first(['id', 'workspace_id']);
        if ($agent === null) {
            return;
        }
        $updated = DB::table('agent_targets')->where('agent_id', $agent->id)->where('scheme', $scheme)
            ->where('host', strtolower($host))->where('port', $port)->whereNull('approved_at')
            ->update(['approved_at' => $this->clock->now()]);
        if ($updated > 0) {
            $this->broadcasts->refreshWorkspace($agent->workspace_id);
        }
    }

    public function revoke(string $agentId, string $reason): bool
    {
        return DB::transaction(function () use ($agentId, $reason): bool {
            $agent = Agent::query()->live()->whereKey($agentId)->lockForUpdate()->first();
            if ($agent === null) {
                return false;
            }
            $this->markRevoked($agent, $reason);

            return true;
        });
    }

    /** RE-1, lazily on every authenticated request: true when it was just revoked. */
    public function expireIfUnconfirmed(Agent $agent): bool
    {
        $deadline = $this->clock->now()->subSeconds((int) config('agent.confirm_within_seconds'));
        if ($agent->revoked_at !== null || $agent->confirmed_at !== null || $agent->paired_at->greaterThan($deadline)) {
            return false;
        }
        $this->revoke($agent->id, 'unconfirmed');

        return true;
    }

    /** RE-1, every minute: revokes every agent still unconfirmed 10 minutes after `pair`. */
    public function expireUnconfirmed(): int
    {
        $deadline = $this->clock->now()->subSeconds((int) config('agent.confirm_within_seconds'));
        $ids = Agent::query()->live()->whereNull('confirmed_at')->where('paired_at', '<=', $deadline)->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $count += $this->revoke((string) $id, 'unconfirmed') ? 1 : 0;
        }

        return $count;
    }

    /** Session ended, account deleted: every live agent of the workspace. */
    public function revokeWorkspace(string $workspaceId, string $reason): void
    {
        foreach (Agent::query()->live()->where('workspace_id', $workspaceId)->pluck('id') as $id) {
            $this->revoke((string) $id, $reason);
        }
    }

    /**
     * Account deletion, phase 1 (AC-4, DL-2), inside the tombstone
     * transaction: every live agent revoked (`account_deleted`, which aborts
     * their open runs through `AgentRevoked`), every pairing code and access
     * token of the workspace deleted.
     */
    public function tombstoneWorkspace(string $workspaceId): void
    {
        $ids = Agent::query()->where('workspace_id', $workspaceId)->lockForUpdate()->pluck('id')->all();
        foreach (Agent::query()->live()->whereKey($ids)->get() as $agent) {
            $this->markRevoked($agent, 'account_deleted');
        }
        PairingCode::query()->where('workspace_id', $workspaceId)->delete();
        DB::table('agent_access_tokens')->whereIn('agent_id', $ids)->delete();
    }

    /**
     * `DELETE /agents/{agentId}` (AC-7, contracts §3.2): the workspace owner,
     * user or guest, revokes one of its live agents (`user`). Anything else is
     * the same not-found.
     */
    public function revokeOwned(Principal $principal, string $agentPublicId): bool
    {
        $workspaceId = $this->workspaces->workspaceIdOf($principal);
        $uuid = Agent::uuidFromPublicId($agentPublicId);
        if ($workspaceId === null || $uuid === null) {
            return false;
        }

        return DB::transaction(function () use ($workspaceId, $uuid): bool {
            $agent = Agent::query()->live()->whereKey($uuid)->where('workspace_id', $workspaceId)->lockForUpdate()->first();
            if ($agent === null) {
                return false;
            }
            $this->markRevoked($agent, 'user');
            DB::table('agent_access_tokens')->where('agent_id', $agent->id)->delete();

            return true;
        });
    }

    /** Account recovery completed (RC-4): every agent asks for its local confirmation again, approvals cleared. */
    public function requireReconfirm(string $workspaceId): void
    {
        $ids = Agent::query()->live()->where('workspace_id', $workspaceId)->pluck('id')->all();
        DB::table('agent_targets')->whereIn('agent_id', $ids)->delete();
        Agent::query()->whereIn('id', $ids)->update(['needs_reconfirm' => true, 'updated_at' => $this->clock->now()]);
    }

    private function markRevoked(Agent $agent, string $reason): void
    {
        $agent->forceFill(['revoked_at' => $this->clock->now(), 'revoked_reason' => $reason, 'state' => 'revoked'])->save();
        if ($reason === 'unconfirmed') {
            DB::table('agent_secret_names')->where('agent_id', $agent->id)->delete();
            DB::table('agent_targets')->where('agent_id', $agent->id)->delete();
        }
        AgentRevoked::dispatch($agent->id, $agent->workspace_id, $reason);
        $this->broadcasts->toAgent($agent->id, 'agent.revoked', ['reason' => $reason]);
        $this->broadcasts->toWorkspace($agent->workspace_id, 'agent.status', [
            'agent_id' => $agent->publicId(), 'state' => 'revoked', 'version' => $agent->version,
            'too_old' => false, 'needs_reconfirm' => $agent->needs_reconfirm, 'replaced_agent_id' => null,
        ]);
    }
}
