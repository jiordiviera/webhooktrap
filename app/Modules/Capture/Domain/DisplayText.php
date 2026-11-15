<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

/**
 * Capture text for the inspector (ADR-005 amendment 1 §7, TM-06; A09 S3-16).
 * Text-only rendering is not enough: control (C0, DEL, C1), bidi, other
 * format characters (zero-width, BOM, Unicode tags, soft hyphen) and line or
 * paragraph separators would hide or reorder what is shown. Each one is
 * replaced by its visible escape `\u{XXXX}`, so the reader sees that it is
 * there and which one it is. Bodies keep tab, line feed and carriage return.
 *
 * The exact bytes stay available through the raw download.
 */
final class DisplayText
{
    private const string HIDDEN = '/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u';

    private const string HIDDEN_KEEP_WHITESPACE = '/(?![\t\n\r])[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u';

    /**
     * Escapes a single-line value (header name or value, path, query, method).
     *
     * @return array{0: string, 1: bool} [escaped text, whether anything was escaped]
     */
    public static function line(string $value): array
    {
        return self::escape(self::HIDDEN, $value);
    }

    /**
     * Escapes body text, keeping tab, line feed and carriage return.
     *
     * @return array{0: string, 1: bool}
     */
    public static function body(string $value): array
    {
        return self::escape(self::HIDDEN_KEEP_WHITESPACE, $value);
    }

    /** @return array{0: string, 1: bool} */
    private static function escape(string $pattern, string $value): array
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_scrub($value, 'UTF-8');
        }
        $count = 0;
        $escaped = preg_replace_callback(
            $pattern,
            fn (array $m): string => sprintf('\\u{%04X}', mb_ord($m[0], 'UTF-8')),
            $value,
            -1,
            $count,
        );

        return [$escaped ?? $value, $count > 0];
    }
}
