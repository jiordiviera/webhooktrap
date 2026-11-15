<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

/**
 * SHP-3 against a baseline of earlier shapes (SHP-2: the union of them):
 * `new` when there is none; `differs` when the capture adds a (path, type)
 * pair the union lacks, or lacks a path present in every one of at least 5
 * baseline captures; `matches` otherwise. Optional fields that come and go
 * never make a capture differ.
 */
final class ShapeVerdict
{
    public const int REMOVAL_MIN_BASELINE = 5;

    /**
     * @param  list<array{0: string, 1: string}>  $shape
     * @param  list<array<mixed>>  $baseline  earlier shapes, as stored
     */
    public static function of(array $shape, array $baseline): string
    {
        if ($baseline === []) {
            return 'new';
        }

        $union = [];
        $everywhere = null;
        foreach ($baseline as $earlier) {
            $paths = [];
            foreach ($earlier as $pair) {
                if (is_array($pair) && isset($pair[0], $pair[1]) && is_string($pair[0]) && is_string($pair[1])) {
                    $union[$pair[0]."\0".$pair[1]] = true;
                    $paths[$pair[0]] = true;
                }
            }
            $everywhere = $everywhere === null ? $paths : array_intersect_key($everywhere, $paths);
        }

        $mine = [];
        foreach ($shape as [$path, $type]) {
            if (! isset($union[$path."\0".$type])) {
                return 'differs';
            }
            $mine[$path] = true;
        }

        if (count($baseline) >= self::REMOVAL_MIN_BASELINE && array_diff_key($everywhere, $mine) !== []) {
            return 'differs';
        }

        return 'matches';
    }
}
