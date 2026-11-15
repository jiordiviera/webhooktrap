<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

use RuntimeException;
use stdClass;

/**
 * The JSON Schema subset the agent protocol schemas use
 * (`contracts/agent/v1/*.schema.json`, draft 2020-12): `$ref` (same file or
 * a sibling file), `type`, `const`, `enum`, `pattern`, `minLength`,
 * `maxLength`, `minimum`, `maximum`, `properties`, `required`,
 * `additionalProperties`, `propertyNames`, `maxProperties`, `items`,
 * `prefixItems`, `minItems`, `maxItems`, `anyOf`, `oneOf`. Anything else in
 * a schema is ignored (`description`, `title`, `$id`).
 *
 * Data is validated in its decoded-object form (`json_decode(..., false)`),
 * so a JSON object and a JSON array stay distinct. Errors name a path and a
 * keyword, never a value (bodies are never logged, TM-82).
 */
final class JsonSchema
{
    private const int MAX_DEPTH = 32;

    /** @var array<string, array<string, mixed>> file => decoded schema */
    private array $files = [];

    public function __construct(private readonly string $directory) {}

    /** The first error as `path: keyword`, or null when the data is valid. */
    public function validate(string $file, mixed $data): ?string
    {
        return $this->check($this->load($file), $data, '$', $file, 0);
    }

    /** @param  array<string, mixed>  $schema */
    private function check(array $schema, mixed $data, string $path, string $file, int $depth): ?string
    {
        if ($depth > self::MAX_DEPTH) {
            return "{$path}: depth";
        }
        if (isset($schema['$ref'])) {
            [$schema, $file] = $this->resolve((string) $schema['$ref'], $file);

            return $this->check($schema, $data, $path, $file, $depth + 1);
        }
        if (array_key_exists('const', $schema) && ! self::same($schema['const'], $data)) {
            return "{$path}: const";
        }
        if (isset($schema['enum']) && is_array($schema['enum'])) {
            $found = false;
            foreach ($schema['enum'] as $option) {
                $found = $found || self::same($option, $data);
            }
            if (! $found) {
                return "{$path}: enum";
            }
        }
        if (isset($schema['type']) && ! self::typeMatches($schema['type'], $data)) {
            return "{$path}: type";
        }
        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            $ok = false;
            foreach ($schema['anyOf'] as $option) {
                if ($this->check((array) $option, $data, $path, $file, $depth + 1) === null) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                return "{$path}: anyOf";
            }
        }
        if (isset($schema['oneOf']) && is_array($schema['oneOf'])) {
            $matches = 0;
            foreach ($schema['oneOf'] as $option) {
                $matches += $this->check((array) $option, $data, $path, $file, $depth + 1) === null ? 1 : 0;
            }
            if ($matches !== 1) {
                return "{$path}: oneOf";
            }
        }

        if (is_string($data)) {
            return $this->checkString($schema, $data, $path);
        }
        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                return "{$path}: minimum";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                return "{$path}: maximum";
            }
        }
        if ($data instanceof stdClass) {
            return $this->checkObject($schema, $data, $path, $file, $depth);
        }
        if (is_array($data)) {
            return $this->checkArray($schema, $data, $path, $file, $depth);
        }

        return null;
    }

    /** @param  array<string, mixed>  $schema */
    private function checkString(array $schema, string $data, string $path): ?string
    {
        if (! mb_check_encoding($data, 'UTF-8')) {
            return "{$path}: encoding";
        }
        $length = mb_strlen($data, 'UTF-8');
        if (isset($schema['minLength']) && $length < $schema['minLength']) {
            return "{$path}: minLength";
        }
        if (isset($schema['maxLength']) && $length > $schema['maxLength']) {
            return "{$path}: maxLength";
        }
        if (isset($schema['pattern']) && preg_match('~'.str_replace('~', '\~', (string) $schema['pattern']).'~u', $data) !== 1) {
            return "{$path}: pattern";
        }

        return null;
    }

    /** @param  array<string, mixed>  $schema */
    private function checkObject(array $schema, stdClass $data, string $path, string $file, int $depth): ?string
    {
        $values = get_object_vars($data);
        if (isset($schema['maxProperties']) && count($values) > $schema['maxProperties']) {
            return "{$path}: maxProperties";
        }
        foreach ((array) ($schema['required'] ?? []) as $name) {
            if (! array_key_exists((string) $name, $values)) {
                return "{$path}.{$name}: required";
            }
        }
        $properties = (array) ($schema['properties'] ?? []);
        foreach ($values as $name => $value) {
            $name = (string) $name;
            if (isset($schema['propertyNames'])) {
                $error = $this->checkString((array) $schema['propertyNames'], $name, "{$path}[name]");
                if ($error !== null) {
                    return $error;
                }
            }
            if (array_key_exists($name, $properties)) {
                $error = $this->check((array) $properties[$name], $value, "{$path}.{$name}", $file, $depth + 1);
            } elseif (($schema['additionalProperties'] ?? true) === false) {
                return "{$path}.{$name}: additionalProperties";
            } elseif (is_array($schema['additionalProperties'] ?? null)) {
                $error = $this->check($schema['additionalProperties'], $value, "{$path}.{$name}", $file, $depth + 1);
            } else {
                $error = null;
            }
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<mixed>  $data
     */
    private function checkArray(array $schema, array $data, string $path, string $file, int $depth): ?string
    {
        if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
            return "{$path}: minItems";
        }
        if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
            return "{$path}: maxItems";
        }
        $prefix = (array) ($schema['prefixItems'] ?? []);
        foreach (array_values($data) as $i => $item) {
            $itemSchema = $prefix[$i] ?? ($schema['items'] ?? null);
            if (is_array($itemSchema)) {
                $error = $this->check($itemSchema, $item, "{$path}[{$i}]", $file, $depth + 1);
                if ($error !== null) {
                    return $error;
                }
            }
        }

        return null;
    }

    private static function typeMatches(mixed $type, mixed $data): bool
    {
        foreach ((array) $type as $one) {
            $ok = match ($one) {
                'string' => is_string($data),
                'integer' => is_int($data),
                'number' => is_int($data) || is_float($data),
                'boolean' => is_bool($data),
                'null' => $data === null,
                'object' => $data instanceof stdClass,
                'array' => is_array($data),
                default => false,
            };
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    private static function same(mixed $expected, mixed $data): bool
    {
        if (is_array($expected) || $data instanceof stdClass || is_array($data)) {
            return json_encode($expected) === json_encode($data);
        }

        return $expected === $data;
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function resolve(string $ref, string $file): array
    {
        [$target, $pointer] = array_pad(explode('#', $ref, 2), 2, '');
        $target = $target === '' ? $file : $target;
        $node = $this->load($target);
        foreach (array_filter(explode('/', $pointer), fn (string $p): bool => $p !== '') as $part) {
            if (! is_array($node[$part] ?? null)) {
                throw new RuntimeException("Unresolvable \$ref {$ref} in {$file}.");
            }
            $node = $node[$part];
        }

        return [$node, $target];
    }

    /** @return array<string, mixed> */
    private function load(string $file): array
    {
        if (! isset($this->files[$file])) {
            if (preg_match('/^[a-z0-9.-]+\.schema\.json$/', $file) !== 1) {
                throw new RuntimeException('Bad schema file name.');
            }
            $raw = file_get_contents($this->directory.'/'.$file);
            if ($raw === false) {
                throw new RuntimeException("Schema file missing: {$file}.");
            }
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, 128, JSON_THROW_ON_ERROR);
            $this->files[$file] = $decoded;
        }

        return $this->files[$file];
    }
}
