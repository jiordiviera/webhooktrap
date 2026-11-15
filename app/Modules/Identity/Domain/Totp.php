<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * RFC 6238 TOTP: SHA-1, 6 digits, 30 s steps, ±1 step; a code is accepted
 * at most once (the caller stores the last used step, domain §2.1).
 */
final class Totp
{
    private const string BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return random_bytes(20);
    }

    public static function step(int $unixTime): int
    {
        return intdiv($unixTime, 30);
    }

    public static function codeAt(string $secret, int $step): string
    {
        $counter = pack('N2', 0, $step);
        $mac = hash_hmac('sha1', $counter, $secret, true);
        $offset = ord($mac[19]) & 0x0F;
        $value = ((ord($mac[$offset]) & 0x7F) << 24)
            | (ord($mac[$offset + 1]) << 16)
            | (ord($mac[$offset + 2]) << 8)
            | ord($mac[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** The matched step, or null. Steps at or before $lastUsedStep never match. */
    public static function verify(string $secret, string $code, int $unixTime, ?int $lastUsedStep): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }
        $current = self::step($unixTime);
        foreach ([$current - 1, $current, $current + 1] as $step) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::codeAt($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[(int) bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account)
            .'?secret='.self::base32($secret).'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits=6&period=30';
    }
}
