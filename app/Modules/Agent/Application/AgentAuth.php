<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Modules\Agent\Contracts\AgentView;
use App\Modules\Agent\Domain\Agent;
use App\Modules\Agent\Domain\AgentRefusal;
use App\Modules\Agent\Domain\AuthMessage;
use App\Modules\Identity\Application\PrincipalLiveness;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Agent authentication (contracts §5.2, S-3, TM-33): a 32-byte nonce, single
 * use, 60 s, bound to the agent id; an Ed25519 signature over the
 * domain-separated message that includes this server's pinned origin; a
 * 15-minute access token stored hashed, with revocation checked on every
 * request. An agent still unconfirmed 10 minutes after `pair` is revoked
 * the next time it shows up (RE-1; the maintenance job does it too), and an
 * agent whose workspace owner is not live (tombstoned, disabled, guest
 * session over) is refused on every request (DL-2, AC-4).
 */
final readonly class AgentAuth
{
    public function __construct(
        private Clock $clock,
        private AgentLifecycle $lifecycle,
        private WorkspaceOwnership $workspaces,
        private PrincipalLiveness $liveness,
    ) {}

    /** @return array{proto: int, nonce: string, expires_at: string} */
    public function challenge(string $agentPublicId): array
    {
        // Unknown ids get a nonce too: the answer never says whether an agent exists.
        $uuid = Agent::uuidFromPublicId($agentPublicId) ?? throw new AgentRefusal(400, 'invalid_request');
        $nonce = random_bytes(32);
        $expiresAt = $this->clock->now()->addSeconds((int) config('agent.auth.nonce_ttl_seconds'))->toIso8601ZuluString('millisecond');
        DB::table('agent_auth_nonces')->insert([
            'nonce' => Bytea::bind($nonce),
            'agent_id' => $uuid,
            'expires_at' => $expiresAt,
        ]);

        return ['proto' => 1, 'nonce' => base64_encode($nonce), 'expires_at' => $expiresAt];
    }

    /** @return array<string, mixed> the token answer */
    public function token(string $agentPublicId, string $nonceB64, string $signatureB64): array
    {
        $uuid = Agent::uuidFromPublicId($agentPublicId);
        $nonce = base64_decode($nonceB64, true);
        $signature = base64_decode($signatureB64, true);
        if ($uuid === null || $nonce === false || strlen($nonce) !== 32 || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new AgentRefusal(401, 'invalid_challenge');
        }
        // Single use: the nonce is gone whatever happens next.
        $row = DB::selectOne(
            'DELETE FROM agent_auth_nonces WHERE nonce = ? AND agent_id = ? RETURNING expires_at',
            [Bytea::bind($nonce), $uuid],
        );
        $now = $this->clock->now();
        if ($row === null) {
            throw new AgentRefusal(401, 'invalid_challenge');
        }
        $expires = CarbonImmutable::parse((string) $row->expires_at)->utc();
        if ($expires->lessThanOrEqualTo($now)) {
            throw new AgentRefusal(401, 'invalid_challenge');
        }
        $agent = Agent::query()->find($uuid) ?? throw new AgentRefusal(401, 'invalid_challenge');
        $this->assertUsable($agent);

        $message = AuthMessage::bytes(
            AuthMessage::origin((string) config('agent.origin')),
            $agent->publicId(),
            $nonce,
            $expires->toIso8601ZuluString('millisecond'),
        );
        $key = $agent->public_key;
        if ($key === '' || ! sodium_crypto_sign_verify_detached($signature, $message, $key)) {
            throw new AgentRefusal(401, 'invalid_signature');
        }

        $token = 'wt_agt_'.Str::random(40);
        $tokenExpires = $now->addSeconds((int) config('agent.auth.token_ttl_seconds'));
        DB::table('agent_access_tokens')->insert([
            'token_hash' => Bytea::bind(hash('sha256', $token, true)),
            'agent_id' => $agent->id,
            'expires_at' => $tokenExpires,
        ]);
        $reverb = (array) config('broadcasting.connections.reverb');

        return [
            'proto' => 1,
            'access_token' => $token,
            'expires_at' => $tokenExpires->toIso8601ZuluString('millisecond'),
            'channel' => 'private-'.AgentBroadcasts::agentChannel($agent->id),
            'broadcast' => [
                'host' => is_string($reverb['options']['host'] ?? null) ? $reverb['options']['host'] : null,
                'key' => is_string($reverb['key'] ?? null) ? $reverb['key'] : null,
                'auth_endpoint' => '/agent/v1/broadcasting/auth',
            ],
        ];
    }

    /** The agent behind a bearer token, checked for revocation and RE-1 on every request. */
    public function authenticate(?string $bearer): AgentView
    {
        if ($bearer === null || preg_match('/^wt_agt_[A-Za-z0-9]{40}$/', $bearer) !== 1) {
            throw new AgentRefusal(401, 'unauthenticated');
        }
        $agentId = DB::table('agent_access_tokens')
            ->where('token_hash', Bytea::bind(hash('sha256', $bearer, true)))
            ->where('expires_at', '>', $this->clock->now())
            ->value('agent_id');
        $agent = is_string($agentId) ? Agent::query()->find($agentId) : null;
        if ($agent === null) {
            throw new AgentRefusal(401, 'unauthenticated');
        }
        $this->assertUsable($agent);

        return Agents::view($agent);
    }

    private function assertUsable(Agent $agent): void
    {
        if ($agent->revoked_at !== null) {
            throw AgentRefusal::revoked((string) $agent->revoked_reason);
        }
        // DL-2 (AC-4): the workspace owner must be live on every request, so a
        // tombstoned account's agents are refused even if a revocation failed.
        $owner = $this->workspaces->ownerOf($agent->workspace_id);
        if ($owner === null || ! $this->liveness->isLive($owner->kind, $owner->id)) {
            throw new AgentRefusal(401, 'unauthenticated');
        }
        if ($this->lifecycle->expireIfUnconfirmed($agent)) {
            throw AgentRefusal::revoked('unconfirmed');
        }
    }
}
