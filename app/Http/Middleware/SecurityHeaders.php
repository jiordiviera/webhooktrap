<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * App-page headers (contracts §3.4 "Headers", WA-4, TM-123): no framing
 * anywhere; WebAuthn allowed only on ceremony pages; emailed-link pages send
 * no referrer and are never cached; pages that show one-time secrets or
 * account details are never cached either (A09 delta review D1).
 */
final class SecurityHeaders
{
    private const array CEREMONY_PREFIXES = ['sign-in', 'two-factor', 'confirm-auth', 'account/security', 'webauthn'];

    private const array NO_STORE_PREFIXES = ['account/security', 'two-factor', 'confirm-auth'];

    private const array LINK_PREFIXES = ['verify-email/', 'email-change/', 'email-change-old/', 'account-deletion/', 'reset-password/', 'account-recovery/', 'set-password/'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $path = ltrim($request->path(), '/');

        $response->headers->set('X-Frame-Options', 'DENY');
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }

        $ceremony = false;
        foreach (self::CEREMONY_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $ceremony = true;
            }
        }
        $response->headers->set('Permissions-Policy', $ceremony
            ? 'publickey-credentials-get=(self), publickey-credentials-create=(self)'
            : 'publickey-credentials-get=(), publickey-credentials-create=()');

        foreach (self::NO_STORE_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $response->headers->set('Cache-Control', 'no-store, private');
            }
        }

        foreach (self::LINK_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $response->headers->set('Referrer-Policy', 'no-referrer');
                $response->headers->set('Cache-Control', 'no-store, private');
            }
        }

        return $response;
    }
}
