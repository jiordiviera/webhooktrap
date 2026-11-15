<?php

declare(strict_types=1);

namespace App\Modules\Packs\Domain;

use JsonException;
use RuntimeException;

/**
 * The pack set named by `packs/manifest.json`: the packs in detection order,
 * pinned to one version each, plus the set's own version (recorded as
 * `none@<version>` on captures no pack matched).
 */
final readonly class PackSet
{
    /**
     * @param  list<Pack>  $packs  detection order
     * @param  list<string>  $coming
     */
    public function __construct(
        public string $version,
        public array $packs,
        public array $coming,
        public bool $unknownDataIdFallback,
    ) {}

    public static function load(string $dir): self
    {
        $manifest = self::read($dir.'/manifest.json');
        if (($manifest['format'] ?? null) !== 1 || ! is_string($manifest['version'] ?? null) || ! is_array($manifest['packs'] ?? null)) {
            throw new RuntimeException('Pack manifest: format 1 with version and packs expected.');
        }

        $packs = [];
        foreach ($manifest['packs'] as $entry) {
            $id = (string) ($entry['id'] ?? '');
            $version = (string) ($entry['version'] ?? '');
            if (preg_match('/^[a-z][a-z0-9_]{1,31}$/', $id) !== 1 || preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
                throw new RuntimeException('Pack manifest: bad pack entry.');
            }
            $file = "{$dir}/{$id}/{$version}.json";
            $pack = Pack::fromArray(self::read($file), "{$id}/{$version}.json");
            if ($pack->id !== $id || $pack->version !== $version) {
                throw new RuntimeException("Pack {$id}/{$version}.json: id or version differs from its path.");
            }
            $packs[] = $pack;
        }

        return new self(
            version: $manifest['version'],
            packs: $packs,
            coming: array_values(array_map('strval', (array) ($manifest['coming'] ?? []))),
            unknownDataIdFallback: ($manifest['unknown']['group_fallback'] ?? null) === 'data_id',
        );
    }

    public function find(?string $id): ?Pack
    {
        foreach ($this->packs as $pack) {
            if ($pack->id === $id) {
                return $pack;
            }
        }

        return null;
    }

    public function noneRef(): string
    {
        return 'none@'.$this->version;
    }

    /** @return array<mixed> */
    private static function read(string $file): array
    {
        $raw = is_file($file) ? file_get_contents($file) : false;
        if ($raw === false) {
            throw new RuntimeException("Pack file missing: {$file}.");
        }
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Pack file is not JSON: {$file}.", 0, $e);
        }
        if (! is_array($data)) {
            throw new RuntimeException("Pack file is not an object: {$file}.");
        }

        return $data;
    }
}
