<?php

declare(strict_types=1);

namespace App\Infrastructure\Ids;

use InvalidArgumentException;

/**
 * External form of an entity id (schema.md §1.1): "<prefix>_" followed by the
 * 128 bits of the stored UUIDv7 in 26-character Crockford base32.
 */
final class EntityId
{
    public const array PREFIXES = [
        'usr', 'gst', 'wsp', 'prj', 'cap', 'run', 'agt', 'tok', 'job', 'fix', 'rnr', 'key', 'ses',
    ];

    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public static function encode(string $prefix, string $uuid): string
    {
        self::assertPrefix($prefix);

        $hex = str_replace('-', '', strtolower($uuid));
        if (preg_match('/^[0-9a-f]{32}$/', $hex) !== 1) {
            throw new InvalidArgumentException('Not a UUID.');
        }

        $bits = '00';
        foreach (str_split($hex) as $nibble) {
            $bits .= str_pad(decbin((int) hexdec($nibble)), 4, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[(int) bindec($chunk)];
        }

        return $prefix.'_'.$out;
    }

    /**
     * @return array{0: string, 1: string} [prefix, uuid]
     */
    public static function decode(string $id): array
    {
        if (preg_match('/^([a-z]{3})_([0-7][0-9A-HJKMNP-TV-Z]{25})$/', $id, $m) !== 1) {
            throw new InvalidArgumentException('Malformed entity id.');
        }
        self::assertPrefix($m[1]);

        $bits = '';
        foreach (str_split($m[2]) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $bits = substr($bits, 2);

        $hex = '';
        foreach (str_split($bits, 4) as $nibble) {
            $hex .= dechex((int) bindec($nibble));
        }

        $uuid = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'
            .substr($hex, 16, 4).'-'.substr($hex, 20, 12);

        return [$m[1], $uuid];
    }

    private static function assertPrefix(string $prefix): void
    {
        if (! in_array($prefix, self::PREFIXES, true)) {
            throw new InvalidArgumentException('Unknown entity id prefix.');
        }
    }
}
