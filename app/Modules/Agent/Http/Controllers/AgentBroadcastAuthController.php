<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http\Controllers;

use App\Infrastructure\Broadcasting\ChannelSigner;
use App\Modules\Agent\Application\AgentAuth;
use App\Modules\Agent\Application\AgentBroadcasts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * `POST /agent/v1/broadcasting/auth` (contracts §5.8, TM-20, C-3): the
 * `agent` guard only, and only `private-agent.{its own id}`. A session
 * cookie is ignored. Any denial, a revoked agent or a malformed socket id
 * is a bare `403`. Signs `channel_data.user_id = agent:<id>`.
 */
final readonly class AgentBroadcastAuthController
{
    public function __construct(private AgentAuth $auth, private ChannelSigner $signer) {}

    public function __invoke(Request $request): JsonResponse|Response
    {
        try {
            $agent = $this->auth->authenticate($request->bearerToken());
        } catch (Throwable) {
            return self::denied();
        }
        $socketId = $request->input('socket_id');
        $channel = $request->input('channel_name');
        if (! is_string($socketId) || ! is_string($channel) || $agent->needsReconfirm
            || ! hash_equals('private-'.AgentBroadcasts::agentChannel($agent->id), $channel)) {
            return self::denied();
        }
        try {
            return new JsonResponse($this->signer->sign($socketId, $channel, 'agent:'.$agent->id), 200, ['Cache-Control' => 'no-store, private']);
        } catch (Throwable) {
            return self::denied();
        }
    }

    private static function denied(): Response
    {
        return new Response('', 403, ['Cache-Control' => 'no-store, private']);
    }
}
