<?php

declare(strict_types=1);

namespace App\Modules\Proving\Domain;

/**
 * PV-11: the server's check of every probe display value before storage.
 * PV-2 forms always (small counts, 24-bit labels, 48-bit labels when the run
 * widened to 48 bits); PV-5 forms only for owner (non-guest) runs and probe
 * entries declared `opted_*`. Anything else becomes `#invalid`: counted,
 * never logged, never formatted (PV-6).
 */
final class DisplayValues
{
    public const string INVALID = '#invalid';

    public static function clean(?string $value, bool $optedAllowed, int $labelBits): ?string
    {
        if ($value === null) {
            return null;
        }
        if (preg_match('/^-?(0|[1-9][0-9]{0,3})$/', $value) === 1 || preg_match('/^#[0-9a-f]{6}$/', $value) === 1) {
            return $value;
        }
        if ($labelBits === 48 && preg_match('/^#[0-9a-f]{12}$/', $value) === 1) {
            return $value;
        }
        if ($optedAllowed && (preg_match('/^-?[0-9]{1,12}(\.[0-9]{1,4})?$/', $value) === 1
            || preg_match('/^[A-Za-z0-9_.:-]{1,32}$/', $value) === 1)) {
            return $value;
        }

        return self::INVALID;
    }

    /**
     * Cleans a `{before, after, expected}` display object.
     *
     * @param  array<string, mixed>|null  $display
     * @return array{before: string|null, after: string|null, expected: string|null}
     */
    public static function display(?array $display, bool $optedAllowed, int $labelBits): array
    {
        $out = [];
        foreach (['before', 'after', 'expected'] as $k) {
            $v = $display[$k] ?? null;
            $out[$k] = self::clean(is_string($v) ? $v : null, $optedAllowed, $labelBits);
        }

        return $out;
    }
}
