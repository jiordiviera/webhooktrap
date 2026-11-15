<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

/**
 * Masked form of an address for notices and the audit log:
 *
 * `maya@bakery.example` → `m***@b***.example` (domain §2.6).
 */
final class EmailMask
{
    public static function mask(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return '***';
        }
        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        $dot = strrpos($domain, '.');
        $host = $dot === false ? $domain : substr($domain, 0, $dot);
        $tld = $dot === false ? '' : substr($domain, $dot);

        return mb_substr($local, 0, 1).'***@'.mb_substr($host, 0, 1).'***'.$tld;
    }
}
