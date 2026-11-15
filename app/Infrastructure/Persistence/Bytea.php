<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * PostgreSQL `bytea` column (schema.md §1.2: hashes are bytea). PDO pgsql
 * returns bytea as a stream and only accepts raw bytes bound as a LOB, which
 * Laravel does for resources; this cast converts both ways.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class Bytea implements CastsAttributes, ComparesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::read($value);
    }

    /**
     * @return resource|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : self::bind(self::read($value) ?? '');
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return self::read($firstValue) === self::read($secondValue);
    }

    /**
     * Raw bytes as a query binding for a bytea column.
     *
     * @return resource
     */
    public static function bind(string $bytes): mixed
    {
        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        fwrite($stream, $bytes);
        rewind($stream);

        return $stream;
    }

    public static function read(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_resource($value)) {
            rewind($value);
            $bytes = stream_get_contents($value);
            // Leave the stream rewound: a pending write may still bind it.
            rewind($value);

            return $bytes === false ? '' : $bytes;
        }

        return is_string($value) ? $value : null;
    }
}
