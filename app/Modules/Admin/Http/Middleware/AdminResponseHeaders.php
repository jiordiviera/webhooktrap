<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin-host answer (decision 74): never indexed, never cached, never
 * framed, no referrer to anywhere else. The app's CSP (`frame-ancestors
 * 'none'` only) needs no relaxation for Filament's inline scripts and styles.
 */
final class AdminResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Cache-Control', 'no-store, private');
        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'same-origin');
        }
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }
        $response->headers->set('Permissions-Policy', 'publickey-credentials-get=(), publickey-credentials-create=()');

        return $response;
    }
}
