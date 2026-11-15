<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Recovery codes: 10 per set, 12 Crockford base32 symbols each (60 bits,
 * above A09's 50-bit floor, R-K), shown as XXXX-XXXX-XXXX, stored as a keyed hash.
 */
final class RecoveryCodeGenerator
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** @return list<string> */
    public static function generate(int $count = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = '';
            for ($j = 0; $j < 12; $j++) {
                $raw .= self::ALPHABET[random_int(0, 31)];
            }
            $codes[] = implode('-', str_split($raw, 4));
        }

        return $codes;
    }

    public static function normalise(string $code): string
    {
        $code = strtoupper((string) preg_replace('/[\s\-]+/', '', $code));

        return strtr($code, ['I' => '1', 'L' => '1', 'O' => '0']);
    }

    /**
     * Keyed hash (HMAC-SHA256 with the dedicated recovery-code key, A09 F8):
     * a database dump alone allows no offline search of the codes.
     */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', self::normalise($code), IdentityKeys::recoveryCodes(), true);
    }
}
