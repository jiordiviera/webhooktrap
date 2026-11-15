<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The WebAuthn JSON endpoints require `Sec-Fetch-Site: same-origin`
 * (contracts §3.4, domain §2.2), on top of the session and CSRF.
 */
final class RequireSameOriginFetch
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('Sec-Fetch-Site') !== 'same-origin') {
            return response()->json(['error' => 'cross_site'], 403);
        }

        return $next($request);
    }
}
