<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Coarse browser label derived at sign-in ("Firefox on macOS"); the raw
 * User-Agent is never stored (schema §2 `user_sessions.ua_label`).
 */
final class UserAgentLabel
{
    public static function from(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser === null) {
            return $os === null ? 'Unknown browser' : 'Browser on '.$os;
        }

        return $os === null ? $browser : $browser.' on '.$os;
    }
}
