<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\RateLimit\ClientAddress;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Agent\Domain\Agent;
use App\Modules\Agent\Domain\AgentRefusal;
use App\Modules\Agent\Domain\AgentVersions;
use App\Modules\Agent\Domain\AuthMessage;
use App\Modules\Agent\Domain\PairingCode;
use App\Modules\Agent\Domain\PairingCodeFormat;
use App\Modules\Agent\Domain\TerminalText;
use App\Modules\Agent\Events\AgentPaired;
use App\Modules\Identity\Application\PrincipalLabels;
use App\Modules\Identity\Application\PrincipalLiveness;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use Illuminate\Support\Facades\DB;

/**
 * Pairing (contracts §3.2 `POST /pairing-codes`, §5.1 `POST /agent/v1/pair`;
 * S-1, S-2, TM-43). A code is issued to a browser principal for its own
 * workspace, one live code at a time; `pair` turns a code into an agent that
 * waits for its local `[y/N]` (`needs_reconfirm`, RE-1). Every failed `pair`
 * counts against the source address and a global breaker; the answer to any
 * bad code is the same `invalid_code`.
 */
final readonly class Pairing
{
    public function __construct(
        private Clock $clock,
        private Throttle $throttle,
        private WorkspaceOwnership $workspaces,
        private PrincipalLabels $labels,
        private AgentBroadcasts $broadcasts,
        private PrincipalLiveness $liveness,
    ) {}

    /**
     * Issues a new code for the principal's workspace; the previous unused
     * code is deleted in the same transaction (one live code per principal).
     * With `$replace` (AC-3) the owner chose to replace one of its confirmed
     * agents: the code is bound to it, and the agent paired with this code
     * revokes it at confirmation and takes its name. Without it, nothing is
     * ever replaced; a colliding name gets a suffix at `pair`.
     *
     * @return array{code: string, expiresAt: string, command: string, replaces: array{agentId: string, name: string, keyFingerprint: string}|null}
     */
    public function issue(Principal $principal, ?string $replace = null): array
    {
        $key = 'agent:pairing-codes:'.$principal->kind.':'.$principal->id;
        if (! $this->throttle->reserve($key, (int) config('agent.pairing.issues_per_window'), (int) config('agent.pairing.issue_window_seconds'))) {
            throw new AgentRefusal(429, 'throttled', [], ['Retry-After' => (string) max(1, $this->throttle->availableAt($key)->getTimestamp() - $this->clock->now()->getTimestamp())]);
        }
        $workspaceId = $this->workspaces->workspaceIdOf($principal) ?? throw new AgentRefusal(404, 'not_found');
        $replaced = null;
        if ($replace !== null) {
            $uuid = Agent::uuidFromPublicId($replace);
            $replaced = $uuid === null ? null : Agent::query()->live()->whereKey($uuid)->where('workspace_id', $workspaceId)
                ->where('needs_reconfirm', false)->first();
            if ($replaced === null) {
                throw new AgentRefusal(422, 'unknown_agent');
            }
        }
        $code = PairingCodeFormat::generate();
        $now = $this->clock->now();
        $expires = $now->addSeconds((int) config('agent.pairing.code_ttl_seconds'));

        DB::transaction(function () use ($workspaceId, $principal, $code, $now, $expires, $replaced): void {
            PairingCode::query()->where('workspace_id', $workspaceId)->whereNull('used_at')->delete();
            PairingCode::query()->create([
                'code_hash' => PairingCodeFormat::hash($code),
                'workspace_id' => $workspaceId,
                'user_id' => $principal->isUser() ? $principal->id : null,
                'guest_session_id' => $principal->isGuest() ? $principal->id : null,
                'replaces_agent_id' => $replaced?->id,
                'expires_at' => $expires,
                'created_at' => $now,
            ]);
        });

        return self::view($code, $expires->toIso8601ZuluString('millisecond'), $replaced === null ? null : [
            'agentId' => $replaced->publicId(), 'name' => $replaced->name, 'keyFingerprint' => $replaced->key_fingerprint,
        ]);
    }

    /**
     * @param  array{agentId: string, name: string, keyFingerprint: string}|null  $replaces
     * @return array{code: string, expiresAt: string, command: string, replaces: array{agentId: string, name: string, keyFingerprint: string}|null}
     */
    public static function view(string $code, string $expiresAt, ?array $replaces = null): array
    {
        $display = PairingCodeFormat::display($code);

        return [
            'code' => $display,
            'expiresAt' => $expiresAt,
            'command' => strtr((string) config('agent.pair_command'), [
                '{code}' => $display,
                '{origin}' => AuthMessage::origin((string) config('agent.origin')),
            ]),
            'replaces' => $replaces,
        ];
    }

    /**
     * `POST /agent/v1/pair` with a schema-valid body.
     *
     * @param  array{code: string, public_key: string, name: string, version: string, os: string, arch: string, proto_min: int, proto_max: int}  $body
     * @return array<string, mixed> the 201 answer
     */
    public function pair(array $body, ?string $ip): array
    {
        $address = 'agent:pair:addr:'.ClientAddress::rateKey($ip);
        foreach (['agent:pair:breaker', $address] as $hold) {
            $until = $this->throttle->heldUntil($hold);
            if ($until !== null) {
                throw new AgentRefusal(429, 'rate_limited', [], ['Retry-After' => (string) max(1, $until->getTimestamp() - $this->clock->now()->getTimestamp())]);
            }
        }
        if ($body['proto_min'] > 1 || $body['proto_max'] < 1) {
            throw new AgentRefusal(426, 'proto_unsupported', ['min_agent_version' => (string) config('agent.min_agent_version')]);
        }
        if (AgentVersions::refused($body['version'], 'pair')) {
            throw AgentRefusal::tooOld();
        }

        $code = PairingCodeFormat::normalise($body['code']);
        $publicKey = base64_decode($body['public_key'], true);
        $result = $code === null ? null : DB::transaction(fn (): array|string|null => $this->consume($code, $publicKey, $body));

        if (! is_array($result)) {
            $this->countFailure($address);
            throw match ($result) {
                'agent_limit' => new AgentRefusal(409, 'agent_limit'),
                'key_in_use' => new AgentRefusal(409, 'key_in_use'),
                default => new AgentRefusal(400, 'invalid_code'),
            };
        }

        [$agent, $owner, $replaced] = $result;
        AgentPaired::dispatch($agent->id, $agent->workspace_id, $agent->os, $agent->key_fingerprint, $agent->name, ClientAddress::networkPrefix($ip));
        // The browser shows both fingerprints when the code replaces an agent (AC-3).
        $this->broadcasts->toWorkspace($agent->workspace_id, 'pairing.completed', [
            'agent_id' => $agent->publicId(), 'name' => $agent->name, 'version' => $agent->version,
            'os' => $agent->os, 'key_fingerprint' => $agent->key_fingerprint, 'state' => 'awaiting_confirmation',
            'replaces' => $replaced === null ? null : [
                'agent_id' => $replaced->publicId(), 'name' => $replaced->name, 'key_fingerprint' => $replaced->key_fingerprint,
            ],
        ]);
        $guest = $owner->isGuest();
        $origin = AuthMessage::origin((string) config('agent.origin'));

        return [
            'proto' => 1,
            'agent_id' => $agent->publicId(),
            // AC-3: the name the server kept, suffixed when it collided.
            'name' => $agent->name,
            // AC-3: the agent this pairing replaces at confirmation (shown in the [y/N] prompt), or null.
            'replaces' => $replaced === null ? null : [
                'agent_id' => $replaced->publicId(), 'name' => $replaced->name, 'key_fingerprint' => $replaced->key_fingerprint,
            ],
            'workspace' => ['label' => TerminalText::clean($this->labels->workspaceLabel($owner->kind, $owner->id)), 'guest' => $guest],
            'server' => [
                'origin' => $origin,
                'name' => (string) parse_url($origin, PHP_URL_HOST),
                'kind' => AgentVersions::serverKind(),
                'min_agent_version' => (string) config('agent.min_agent_version'),
            ],
            'probe_display_policy' => (string) config('agent.probe_display_policy'),
            'guest' => $guest,
            'needs_reconfirm' => true,
        ];
    }

    /**
     * Inside the transaction: the code row locked, checked, used.
     *
     * @param  array{name: string, version: string, os: string, arch: string}  $body
     * @return array{0: Agent, 1: Principal, 2: Agent|null}|string|null
     */
    private function consume(string $code, string|false $publicKey, array $body): array|string|null
    {
        $now = $this->clock->now();
        $row = PairingCode::query()->where('code_hash', Bytea::bind(PairingCodeFormat::hash($code)))
            ->whereNull('used_at')->lockForUpdate()->first();
        if ($row === null || $row->expires_at->lessThanOrEqualTo($now)
            || $row->failed_attempts >= (int) config('agent.pairing.code_max_failures')) {
            return null;
        }
        $owner = $this->workspaces->ownerOf($row->workspace_id);
        // DL-2 (AC-4): a tombstoned owner's code is unknown, like any bad code.
        if ($owner === null || ! $this->liveness->isLive($owner->kind, $owner->id) || $publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }
        $fingerprint = Agent::fingerprint($publicKey);
        $refusal = null;
        if (Agent::query()->live()->where('key_fingerprint', $fingerprint)->exists()) {
            $refusal = 'key_in_use';
        } else {
            DB::table('workspaces')->where('id', $row->workspace_id)->lockForUpdate()->first(['id']);
            if (Agent::query()->live()->where('workspace_id', $row->workspace_id)->count() >= (int) config('agent.max_agents_per_workspace')) {
                $refusal = 'agent_limit';
            }
        }
        if ($refusal !== null) {
            PairingCode::query()->whereKey($row->id)->increment('failed_attempts');

            return $refusal;
        }

        // AC-3: a code bound to a replacement pairs under the replaced agent's
        // name (the old one is revoked at confirmation); any other collision
        // with a live agent gets a suffix. Nothing is replaced silently.
        $replaced = $row->replaces_agent_id === null ? null
            : Agent::query()->live()->whereKey($row->replaces_agent_id)->where('workspace_id', $row->workspace_id)->first();
        $name = $replaced->name ?? Agent::freeName($body['name'], array_values(array_map(strval(...),
            Agent::query()->live()->where('workspace_id', $row->workspace_id)->pluck('name')->all())));
        $agent = Agent::query()->create([
            'workspace_id' => $row->workspace_id,
            'name' => $name,
            'replaces_agent_id' => $replaced?->id,
            'public_key' => $publicKey,
            'key_fingerprint' => $fingerprint,
            'version' => $body['version'],
            'os' => $body['os'],
            'arch' => $body['arch'],
            'proto' => 1,
            'state' => 'disconnected',
            'paired_at' => $now,
            'guest_slot' => $owner->isGuest(),
            'needs_reconfirm' => true,
        ]);
        $row->forceFill(['used_at' => $now, 'used_by_agent_id' => $agent->id])->save();

        return [$agent, $owner, $replaced];
    }

    private function countFailure(string $address): void
    {
        $window = (int) config('agent.pairing.failure_window_seconds');
        if ($this->throttle->hit($address, $window) >= (int) config('agent.pairing.failures_per_address')) {
            $this->throttle->hold($address, (int) config('agent.pairing.block_seconds'));
            $this->throttle->clear($address);
        }
        if ($this->throttle->hit('agent:pair:global', $window) >= (int) config('agent.pairing.global_failures')) {
            report(new \RuntimeException('Agent pairing breaker tripped: global failure rate.'));
            $this->throttle->hold('agent:pair:breaker', (int) config('agent.pairing.global_block_seconds'));
            $this->throttle->clear('agent:pair:global');
        }
    }
}
