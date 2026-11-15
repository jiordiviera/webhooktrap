<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

/** PostgreSQL `text[]` literals for query bindings (`?::text[]`) and reading them back. */
final class PgArray
{
    /** @param  list<string>  $values */
    public static function text(array $values): string
    {
        return '{'.implode(',', array_map(fn (string $v): string => '"'.addcslashes($v, '"\\').'"', $values)).'}';
    }

    /**
     * Reads PostgreSQL's text form of a `text[]` without NULL elements
     * (`{a,"b c","d\\"e"}`).
     *
     * @return list<string>
     */
    public static function parse(mixed $raw): array
    {
        if (! is_string($raw) || strlen($raw) < 2 || $raw[0] !== '{') {
            return [];
        }
        $out = [];
        $inner = substr($raw, 1, -1);
        $i = 0;
        $n = strlen($inner);
        while ($i < $n) {
            if ($inner[$i] === '"') {
                $value = '';
                for ($i++; $i < $n && $inner[$i] !== '"'; $i++) {
                    if ($inner[$i] === '\\' && $i + 1 < $n) {
                        $i++;
                    }
                    $value .= $inner[$i];
                }
                $i += 2; // closing quote and comma
            } else {
                $end = strpos($inner, ',', $i);
                $end = $end === false ? $n : $end;
                $value = substr($inner, $i, $end - $i);
                $i = $end + 1;
            }
            $out[] = $value;
        }

        return $out;
    }
}
