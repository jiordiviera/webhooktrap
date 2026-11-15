<?php

declare(strict_types=1);

namespace Tests\Support;

/** The few CBOR items a WebAuthn attestation needs (RFC 8949), for tests. */
final class Cbor
{
    public static function int(int $value): string
    {
        return $value >= 0 ? self::head(0, $value) : self::head(1, -1 - $value);
    }

    public static function bytes(string $value): string
    {
        return self::head(2, strlen($value)).$value;
    }

    public static function text(string $value): string
    {
        return self::head(3, strlen($value)).$value;
    }

    /** @param  list<array{0: string, 1: string}>  $pairs  encoded key and value */
    public static function map(array $pairs): string
    {
        return self::head(5, count($pairs)).implode('', array_map(fn (array $p): string => $p[0].$p[1], $pairs));
    }

    private static function head(int $major, int $length): string
    {
        $m = $major << 5;

        return match (true) {
            $length < 24 => chr($m | $length),
            $length < 0x100 => chr($m | 24).chr($length),
            $length < 0x10000 => chr($m | 25).pack('n', $length),
            default => chr($m | 26).pack('N', $length),
        };
    }
}
