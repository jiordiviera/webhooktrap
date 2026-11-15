<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\RateLimit\ClientAddress;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Agent\Application\AgentAuth;
use App\Modules\Agent\Application\AgentLifecycle;
use App\Modules\Agent\Application\Pairing;
use App\Modules\Agent\Domain\AgentRefusal;
use App\Modules\Agent\Http\AgentApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `/agent/v1` pairing, authentication, confirmation, hello and self-unpair
 * (contracts §5.1–§5.3, §5.8).
 */
final readonly class AgentSessionController
{
    public function __construct(
        private AgentApi $api,
        private Pairing $pairing,
        private AgentAuth $auth,
        private AgentLifecycle $lifecycle,
        private Throttle $throttle,
        private Clock $clock,
    ) {}

    public function pair(Request $request): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request): JsonResponse {
            /** @var array{code: string, public_key: string, name: string, version: string, os: string, arch: string, proto_min: int, proto_max: int} $body */
            $body = $this->api->body($request, 'pair.request.schema.json');

            return new JsonResponse($this->pairing->pair($body, $request->ip()), 201, ['Cache-Control' => 'no-store']);
        });
    }

    public function challenge(Request $request): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request): JsonResponse {
            $key = 'agent:challenge:'.ClientAddress::rateKey($request->ip());
            if (! $this->throttle->reserve($key, (int) config('agent.auth.challenges_per_minute'), 60)) {
                throw new AgentRefusal(429, 'rate_limited', [], ['Retry-After' => (string) max(1, $this->throttle->availableAt($key)->getTimestamp() - $this->clock->now()->getTimestamp())]);
            }
            $body = $this->api->body($request, 'auth-challenge.request.schema.json');

            return AgentApi::ok($this->auth->challenge((string) $body['agent_id']));
        });
    }

    public function token(Request $request): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request): JsonResponse {
            $body = $this->api->body($request, 'auth-token.request.schema.json');

            return AgentApi::ok($this->auth->token((string) $body['agent_id'], (string) $body['nonce'], (string) $body['signature']));
        });
    }

    public function confirm(Request $request): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request): JsonResponse {
            $this->api->body($request, 'confirm.request.schema.json');
            $this->lifecycle->confirm(AgentApi::agent($request));

            return AgentApi::ok();
        });
    }

    public function hello(Request $request): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request): JsonResponse {
            $agent = AgentApi::agent($request);
            if ($agent->needsReconfirm) {
                throw AgentRefusal::needsReconfirm();
            }
            /** @var array{version: string, secrets: list<array{provider: string, env_name: string}>, targets: list<array{scheme: string, host: string, port: int, kind: string, declared: bool, approved: bool}>, probe_config_hash: string|null} $body */
            $body = $this->api->body($request, 'hello.request.schema.json');

            return AgentApi::ok($this->lifecycle->hello($agent, $body));
        });
    }

    /** `DELETE /agent/v1/agent`: self-unpair; allowed before confirmation (the local N). */
    public function destroy(Request $request): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request): JsonResponse {
            $this->lifecycle->revoke(AgentApi::agent($request)->id, 'user');

            return AgentApi::ok();
        });
    }
}
