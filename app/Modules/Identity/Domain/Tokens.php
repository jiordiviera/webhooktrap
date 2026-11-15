<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Random link and cookie tokens (256 bits) and their stored SHA-256 form.
 */
final class Tokens
{
    public static function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** Raw 32-byte SHA-256, stored in `bytea` columns. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token, true);
    }
}
