<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

/**
 * Server strings an agent prints (TM-41): control, format (bidi, zero-width)
 * and Unicode tag characters removed, length capped. The agent sanitises
 * again; this keeps the server from ever sending them.
 */
final class TerminalText
{
    public static function clean(string $text, int $max = 120): string
    {
        $clean = (string) preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}\x{E0000}-\x{E007F}]/u', '', $text);

        return mb_substr($clean, 0, $max);
    }

    /** semver compare on the numeric core; a pre-release counts as older. */
    public static function versionBelow(string $version, string $minimum): bool
    {
        $core = fn (string $v): string => (string) preg_replace('/-.*$/', '', $v);
        $cmp = version_compare($core($version), $core($minimum));

        return $cmp < 0 || ($cmp === 0 && str_contains($version, '-') && ! str_contains($minimum, '-'));
    }
}
