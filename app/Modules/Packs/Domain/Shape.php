<?php

declare(strict_types=1);

namespace App\Modules\Packs\Domain;

use stdClass;

/**
 * SHP-1: the set of (path, JSON type) pairs of a JSON body. Object fields
 * are joined with `.`; an array at `p` is written `p[]` with type `array`,
 * and its elements add their own shape under `p[]` (the union over every
 * element), so an empty array adds `p[]` only. The root object has no pair;
 * a root array is `[]`, a root scalar is the empty path.
 *
 * Bounded: past `maxNodes` visited values or `maxPaths` pairs the shape is
 * not recorded (null).
 */
final class Shape
{
    /** @var array<string, true> "path\0type" */
    private array $pairs = [];

    private int $nodes = 0;

    private function __construct(private readonly int $maxNodes, private readonly int $maxPaths) {}

    /** @return list<array{0: string, 1: string}>|null sorted by path, then type */
    public static function of(mixed $root, int $maxNodes, int $maxPaths): ?array
    {
        $shape = new self($maxNodes, $maxPaths);
        if (! $shape->walk($root, '', true)) {
            return null;
        }
        $pairs = array_map(fn (string $p): array => explode("\0", $p, 2), array_keys($shape->pairs));
        usort($pairs, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        /** @var list<array{0: string, 1: string}> $pairs */
        return $pairs;
    }

    public static function type(mixed $value): string
    {
        return match (true) {
            $value instanceof stdClass => 'object',
            is_array($value) => 'array',
            is_string($value) => 'string',
            is_int($value), is_float($value) => 'number',
            is_bool($value) => 'boolean',
            default => 'null',
        };
    }

    private function walk(mixed $value, string $path, bool $root = false): bool
    {
        if (++$this->nodes > $this->maxNodes) {
            return false;
        }

        if ($value instanceof stdClass) {
            if (! $root && ! $this->add($path, 'object')) {
                return false;
            }
            foreach (get_object_vars($value) as $field => $child) {
                if (! $this->walk($child, $path === '' ? (string) $field : $path.'.'.$field)) {
                    return false;
                }
            }

            return true;
        }

        if (is_array($value)) {
            $at = $path.'[]';
            if (! $this->add($at, 'array')) {
                return false;
            }
            foreach ($value as $element) {
                if (! $this->element($element, $at)) {
                    return false;
                }
            }

            return true;
        }

        return $this->add($path, self::type($value));
    }

    /** An array element: its type at `p[]`, then its fields below `p[]`. */
    private function element(mixed $element, string $at): bool
    {
        if ($element instanceof stdClass) {
            if (++$this->nodes > $this->maxNodes || ! $this->add($at, 'object')) {
                return false;
            }
            foreach (get_object_vars($element) as $field => $child) {
                if (! $this->walk($child, $at.'.'.$field)) {
                    return false;
                }
            }

            return true;
        }

        if (is_array($element)) {
            // Nested array: `p[]` holds arrays, written `p[][]`.
            return $this->add($at, 'array') && $this->walk($element, $at);
        }

        return $this->walk($element, $at);
    }

    private function add(string $path, string $type): bool
    {
        $this->pairs[$path."\0".$type] = true;

        return count($this->pairs) <= $this->maxPaths;
    }
}
