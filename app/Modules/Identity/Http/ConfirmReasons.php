<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * Why a generic recent-auth bounce went to S-28, so the page can say
 * "You're about to {verb}." (screen spec S-28). The `reason` travels in the
 * `/confirm-auth` URL as a code from this allow-list only; the wording never
 * comes from the URL, so a crafted link cannot put its own text on the page.
 */
final class ConfirmReasons
{
    /** Code => S-28 wording. */
    public const array VERBS = [
        'change_password' => 'change your password',
        'setup_totp' => 'set up an authenticator app',
        'disable_totp' => 'turn off the authenticator app',
        'recovery_codes' => 'generate new recovery codes',
        'add_key' => 'add a passkey or security key',
        'remove_key' => 'remove a key',
        'unblock_key' => 'unblock a key',
        'connect_github' => 'connect GitHub',
        'connect_google' => 'connect Google',
        'disconnect_github' => 'disconnect GitHub',
        'disconnect_google' => 'disconnect Google',
        'sign_out_browser' => 'sign out a browser',
        'sign_out_others' => 'sign out other browsers',
    ];

    /** Route name => code; identity routes add the provider. */
    private const array ROUTES = [
        'account.password' => 'change_password',
        'account.totp.start' => 'setup_totp',
        'account.totp.confirm' => 'setup_totp',
        'account.totp.disable' => 'disable_totp',
        'account.recovery-codes' => 'recovery_codes',
        'webauthn.register.options' => 'add_key',
        'webauthn.register' => 'add_key',
        'account.keys.remove' => 'remove_key',
        'account.keys.unblock' => 'unblock_key',
        'account.identities.connect' => 'connect',
        'account.identities.disconnect' => 'disconnect',
        'account.sessions.revoke' => 'sign_out_browser',
        'account.sessions.revoke-others' => 'sign_out_others',
    ];

    /** The reason code for the route being bounced, if it has one. */
    public static function forRequest(Request $request): ?string
    {
        $route = $request->route();
        if (! $route instanceof Route) {
            return null;
        }
        $name = $route->getName();
        $code = is_string($name) ? (self::ROUTES[$name] ?? null) : null;
        if ($code === 'connect' || $code === 'disconnect') {
            $provider = $route->parameter('provider');
            $code = is_string($provider) ? "{$code}_{$provider}" : null;
        }

        return self::valid($code);
    }

    /** An allow-listed code, or null. */
    public static function valid(mixed $code): ?string
    {
        return is_string($code) && array_key_exists($code, self::VERBS) ? $code : null;
    }

    public static function verb(?string $code): ?string
    {
        return $code === null ? null : (self::VERBS[$code] ?? null);
    }
}
