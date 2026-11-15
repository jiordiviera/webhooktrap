<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

use RuntimeException;

/**
 * S-1 pairing codes: 10 Crockford base32 symbols (no I, L, O, U; ≈ 50 bits),
 * shown `XXXXX-XXXXX`, accepted case-insensitively with or without the
 * hyphen, stored as HMAC-SHA-256 under a dedicated server key.
 */
final class PairingCodeFormat
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int MIN_KEY_BYTES = 32;

    /** A fresh code in its normalised form (10 symbols, no hyphen). */
    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < 10; $i++) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }

        return $code;
    }

    public static function display(string $code): string
    {
        return substr($code, 0, 5).'-'.substr($code, 5);
    }

    /** The normalised code, or null when it cannot be one. */
    public static function normalise(string $input): ?string
    {
        $code = strtoupper(str_replace('-', '', trim($input)));

        return preg_match('/^[0-9A-HJKMNP-TV-Z]{10}$/', $code) === 1 ? $code : null;
    }

    /** Raw 32-byte HMAC of a normalised code. Fails closed without a key. */
    public static function hash(string $normalised): string
    {
        return hash_hmac('sha256', $normalised, self::key(), true);
    }

    public static function keyIsConfigured(): bool
    {
        return self::decode(config('agent.pairing_hmac_key')) !== null;
    }

    private static function key(): string
    {
        return self::decode(config('agent.pairing_hmac_key'))
            ?? throw new RuntimeException('AGENT_PAIRING_HMAC_KEY is missing or shorter than 32 bytes.');
    }

    private static function decode(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        if (str_starts_with($value, 'base64:')) {
            $value = base64_decode(substr($value, 7), true);
            if ($value === false) {
                return null;
            }
        }

        return strlen($value) >= self::MIN_KEY_BYTES ? $value : null;
    }
}
