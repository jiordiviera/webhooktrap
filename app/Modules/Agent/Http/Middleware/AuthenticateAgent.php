<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http\Middleware;

use App\Modules\Agent\Application\AgentAuth;
use App\Modules\Agent\Http\AgentApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `agent` guard (domain §2 "Guards"): `Authorization: Bearer
 * wt_agt_…`, revocation and RE-1 checked on every request (S-3). A session
 * cookie means nothing here.
 */
final readonly class AuthenticateAgent
{
    public function __construct(private AgentAuth $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        return AgentApi::answer(function () use ($request, $next): Response {
            $request->attributes->set(AgentApi::AGENT, $this->auth->authenticate($request->bearerToken()));

            return $next($request);
        });
    }
}
