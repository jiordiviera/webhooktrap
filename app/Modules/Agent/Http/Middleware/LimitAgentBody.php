<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every `/agent/v1` endpoint has a payload size limit (contracts §5,
 * TM-40): `agent.body:<config key under agent.limits>`. Above it: `413`.
 */
final class LimitAgentBody
{
    public function handle(Request $request, Closure $next, string $limit = 'small'): Response
    {
        $max = (int) config('agent.limits.'.$limit, 4096);
        $declared = (int) $request->headers->get('Content-Length', '0');
        if ($declared > $max || strlen($request->getContent()) > $max) {
            return new JsonResponse(['proto' => 1, 'error' => 'too_large'], 413, ['Cache-Control' => 'no-store']);
        }

        return $next($request);
    }
}
