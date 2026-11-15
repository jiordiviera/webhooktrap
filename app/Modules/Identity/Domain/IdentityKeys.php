<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use RuntimeException;

/**
 * The dedicated Identity keys (config `identity.keys`, A09 F8 and F9): never
 * APP_KEY, kept outside the database and backups. A missing or short key
 * fails closed: boot refuses to serve and every use throws.
 */
final class IdentityKeys
{
    public const array NAMES = [
        'email_hmac' => 'IDENTITY_EMAIL_HMAC_KEY',
        'recovery_codes' => 'IDENTITY_RECOVERY_CODE_KEY',
    ];

    private const int MIN_BYTES = 32;

    public static function emailHmac(): string
    {
        return self::key('email_hmac');
    }

    public static function recoveryCodes(): string
    {
        return self::key('recovery_codes');
    }

    /**
     * Env names of the keys that are missing or too short.
     *
     * @return list<string>
     */
    public static function problems(): array
    {
        $missing = [];
        foreach (self::NAMES as $name => $env) {
            if (self::decode(config("identity.keys.{$name}")) === null) {
                $missing[] = $env;
            }
        }

        return $missing;
    }

    /** HMAC of a normalised address with the `email_hmac` key (raw bytes). */
    public static function emailHmacOf(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), self::emailHmac(), true);
    }

    private static function key(string $name): string
    {
        return self::decode(config("identity.keys.{$name}"))
            ?? throw new RuntimeException(self::NAMES[$name].' is missing or shorter than 32 bytes.');
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

        return strlen($value) >= self::MIN_BYTES ? $value : null;
    }
}
