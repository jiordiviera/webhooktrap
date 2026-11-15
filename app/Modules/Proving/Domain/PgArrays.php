<?php

declare(strict_types=1);

namespace App\Modules\Proving\Domain;

/**
 * PostgreSQL one-dimensional arrays of simple values (uuids, codes,
 * integers): read from the `{a,b}` text form and written as a literal for a
 * `?::type[]` binding. Values here never contain commas, quotes or braces.
 */
final class PgArrays
{
    /** @return list<string> */
    public static function strings(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }
        $inner = trim($raw, '{}');

        return $inner === '' ? [] : (array_map(fn (string $v): string => trim($v, '"'), explode(',', $inner)));
    }

    /** @return list<int> */
    public static function ints(mixed $raw): array
    {
        return array_map(intval(...), self::strings($raw));
    }

    /** @param  list<string|int>  $values */
    public static function literal(array $values): string
    {
        return '{'.implode(',', array_map(fn (string|int $v): string => (string) $v, $values)).'}';
    }
}
