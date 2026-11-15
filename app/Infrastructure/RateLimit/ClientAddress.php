<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

/**
 * Address handling for limits and display (domain §2.1, §2.7):
 * rate-limit keys use IPv4 /32 and IPv6 /64; stored and displayed networks
 * are truncated to IPv4 /24 and IPv6 /48.
 */
final class ClientAddress
{
    public static function rateKey(?string $ip): string
    {
        $bin = $ip === null ? false : @inet_pton($ip);
        if ($bin === false) {
            return 'unknown';
        }
        if (strlen($bin) === 16) {
            return 'v6:'.bin2hex(substr($bin, 0, 8));
        }

        return 'v4:'.$ip;
    }

    public static function networkPrefix(?string $ip): string
    {
        $bin = $ip === null ? false : @inet_pton($ip);
        if ($bin === false) {
            return '0.0.0.0/0';
        }
        if (strlen($bin) === 16) {
            $masked = substr($bin, 0, 6).str_repeat("\0", 10);

            return inet_ntop($masked).'/48';
        }
        $masked = substr($bin, 0, 3)."\0";

        return inet_ntop($masked).'/24';
    }

    /**
     * Per-email throttle key: the normalised email (lower case, NFC) under
     * HMAC with a server key, counted whether or not an account exists.
     */
    public static function emailKey(string $email, string $serverKey): string
    {
        $normalised = mb_strtolower(trim(\Normalizer::normalize($email, \Normalizer::FORM_C) ?: $email));

        return hash_hmac('sha256', $normalised, $serverKey);
    }
}
