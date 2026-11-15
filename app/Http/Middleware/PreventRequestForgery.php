<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Base;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Laravel's CSRF middleware with the XSRF cookie named `__Host-XSRF-TOKEN`
 * (TM-T4, A09 F4): Secure, Path=/, no Domain, so a sibling host cannot set
 * or overwrite it. The client reads it under that name (resources/js/app.tsx).
 */
class PreventRequestForgery extends Base
{
    public const string COOKIE = '__Host-XSRF-TOKEN';

    /**
     * @param  Request  $request
     * @param  array<string, mixed>  $config
     */
    protected function newCookie($request, $config): Cookie
    {
        return new Cookie(
            self::COOKIE,
            $request->session()->token(),
            $this->availableAt(60 * (int) $config['lifetime']),
            '/',
            null,
            true,
            false,
            false,
            $config['same_site'] ?? null,
            (bool) ($config['partitioned'] ?? false),
        );
    }

    public static function serialized()
    {
        return EncryptCookies::serialized(self::COOKIE);
    }
}
