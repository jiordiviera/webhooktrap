<?php

declare(strict_types=1);

namespace App\Modules\Packs\Application;

use App\Modules\Packs\Contracts\DerivedFacts;
use App\Modules\Packs\Domain\Deriver;
use App\Modules\Packs\Domain\Pack;
use App\Modules\Packs\Domain\PackSet;

/**
 * Read-only provider pack data (domain §8): the files in `packs/`, loaded
 * once per container (bound as a singleton) and never written. Other
 * modules call this; it never touches the database.
 */
final class PackCatalog
{
    private ?PackSet $set = null;

    /** @var array<string, array{name: string, text: array<string, mixed>, citations: array<string, mixed>, tolerance_s: int, budget: array{ms: int, label: string}|null}|null> */
    private array $prose = [];

    public function __construct(private readonly string $path) {}

    /**
     * Derived facts of one capture (pure: same headers, body and packs, same
     * answer).
     *
     * @param  list<array{0: string, 1: string}>  $headers
     */
    public function derive(array $headers, ?string $body): DerivedFacts
    {
        /** @var array{max_json_bytes: int, max_depth: int, max_shape_paths: int, max_nodes: int, max_value_chars: int, max_object_ids: int} $limits */
        $limits = array_map('intval', (array) config('packs.derive'));

        return new Deriver($this->set(), $limits)->derive($headers, $body);
    }

    /** The pack set version (`packs/manifest.json`). */
    public function version(): string
    {
        return $this->set()->version;
    }

    /**
     * Every `derived_with_pack` value the current packs produce: a capture
     * with any other value (or none) is stale and re-derived.
     *
     * @return list<string>
     */
    public function currentRefs(): array
    {
        $refs = array_map(fn (Pack $p): string => $p->ref(), $this->set()->packs);
        $refs[] = $this->set()->noneRef();

        return $refs;
    }

    /** Display name of a provider (pack id), or null for an unknown id. */
    public function providerName(?string $provider): ?string
    {
        return $this->set()->find($provider)?->name;
    }

    /** Display noun of an object type (`subscription` → `Subscription`). */
    public function objectName(?string $provider, string $type): string
    {
        return $this->set()->find($provider)?->typeName($type) ?? Pack::humanize($type);
    }

    /** GRP-3 rank of an anchor type for a provider (0 is the highest). */
    public function anchorRank(?string $provider, string $type): int
    {
        return $this->set()->find($provider)?->rank($type) ?? 0;
    }

    /**
     * Providers with a pack, in detection order (S-03 Waiting tabs and filters).
     *
     * @return list<array{id: string, name: string}>
     */
    public function providers(): array
    {
        return array_map(fn (Pack $p): array => ['id' => $p->id, 'name' => $p->name], $this->set()->packs);
    }

    /** The pinned version of a provider's pack, or null when no pack has that id. */
    public function pinnedVersion(?string $provider): ?string
    {
        return $this->set()->find($provider)?->version;
    }

    /**
     * What the server needs to render finding prose for a run (TM-16): the
     * pack's `text` templates, citations, display name, signing tolerance
     * and budget, read from `packs/<id>/<version>.json` for exactly the
     * version the run used. Null for an unknown id or version.
     *
     * @return array{name: string, text: array<string, mixed>, citations: array<string, mixed>, tolerance_s: int, budget: array{ms: int, label: string}|null}|null
     */
    public function prose(string $id, string $version): ?array
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/', $id) !== 1 || preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            return null;
        }
        $key = $id.'@'.$version;
        if (! array_key_exists($key, $this->prose)) {
            $file = $this->path.'/'.$id.'/'.$version.'.json';
            $raw = is_file($file) ? file_get_contents($file) : false;
            $data = $raw === false ? null : json_decode($raw, true, 64);
            $this->prose[$key] = is_array($data) && ($data['id'] ?? null) === $id ? [
                'name' => is_string($data['name'] ?? null) ? $data['name'] : $id,
                'text' => is_array($data['text'] ?? null) ? $data['text'] : [],
                'citations' => is_array($data['citations'] ?? null) ? $data['citations'] : [],
                'tolerance_s' => (int) ($data['signing']['tolerance_s'] ?? 0),
                'budget' => is_array($data['budget'] ?? null)
                    ? ['ms' => (int) ($data['budget']['ms'] ?? 0), 'label' => (string) ($data['budget']['label'] ?? '')]
                    : null,
            ] : null;
        }

        return $this->prose[$key];
    }

    private function set(): PackSet
    {
        return $this->set ??= PackSet::load($this->path);
    }
}
