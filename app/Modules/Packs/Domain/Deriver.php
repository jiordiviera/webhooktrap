<?php

declare(strict_types=1);

namespace App\Modules\Packs\Domain;

use App\Modules\Packs\Contracts\DerivedFacts;
use App\Modules\Packs\Contracts\GroupRef;
use stdClass;

/**
 * Derivations of one capture from (headers, body, pack set): provider
 * detection, event type, provider event id, API version, object ids
 * (GRP-1), group anchors (GRP-3, GRP-4) and the shape (SHP-1). Pure: no
 * I/O, no clock, no other captures. Every value read from the request is
 * bounded and checked; anything odd is dropped rather than stored.
 */
final readonly class Deriver
{
    /**
     * @param  array{max_json_bytes: int, max_depth: int, max_shape_paths: int, max_nodes: int, max_value_chars: int, max_object_ids: int}  $limits
     */
    public function __construct(private PackSet $packs, private array $limits) {}

    /** @param  list<array{0: string, 1: string}>  $headers  as stored, in received order */
    public function derive(array $headers, ?string $body): DerivedFacts
    {
        $index = self::headerIndex($headers);
        $json = JsonBody::parse($body, $this->limits['max_json_bytes'], $this->limits['max_depth']);
        $shape = $json === null ? null : Shape::of($json->root, $this->limits['max_nodes'], $this->limits['max_shape_paths']);
        $pack = $this->detect($index);

        if ($pack === null) {
            $fallback = $this->packs->unknownDataIdFallback && $json !== null ? $this->dataIdGroup('unknown', $json) : null;

            return new DerivedFacts(null, null, $this->packs->noneRef(), null, null, null, self::fallbackObjects($fallback), [], $fallback, $json !== null, $shape);
        }

        $sender = $this->sender($pack, $index);
        $eventType = $this->read($pack->eventType, $index, $json);
        $objects = [];
        $refs = [];
        $slug = self::slug($sender ?? $pack->id);
        if ($eventType !== null && $json !== null) {
            [$objects, $refs] = $this->objects($pack, $eventType, $json, $slug);
        }
        $fallback = $pack->dataIdFallback && $json !== null && $refs === [] ? $this->dataIdGroup($slug, $json) : null;
        if ($objects === []) {
            $objects = self::fallbackObjects($fallback);
        }

        return new DerivedFacts(
            provider: $pack->id,
            providerName: $sender ?? $pack->name,
            packRef: $pack->ref(),
            eventType: $eventType,
            eventId: $this->read($pack->eventId, $index, $json),
            apiVersion: $this->read($pack->apiVersion, $index, $json, 64),
            objects: $objects,
            groupRefs: $refs,
            fallbackGroup: $fallback,
            json: $json !== null,
            shape: $shape,
        );
    }

    /** @param  array<string, string>  $index */
    private function detect(array $index): ?Pack
    {
        foreach ($this->packs->packs as $pack) {
            $all = true;
            foreach ($pack->detectHeaders as $name) {
                if (trim($index[$name] ?? '') === '') {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return $pack;
            }
        }

        return null;
    }

    /**
     * First non-empty source value.
     *
     * @param  list<array{0: 'header'|'body', 1: string}>  $sources
     * @param  array<string, string>  $index
     */
    private function read(array $sources, array $index, ?JsonBody $json, ?int $max = null): ?string
    {
        foreach ($sources as [$kind, $where]) {
            $value = $kind === 'header'
                ? ($index[strtolower($where)] ?? null)
                : ($json?->at($where));
            $value = $this->scalar($value, $max);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The sender's display name for scheme packs: the User-Agent product
     * token (`Parcelbird-Webhooks/2.1` → `Parcelbird`), unless it is a
     * generic HTTP client.
     *
     * @param  array<string, string>  $index
     */
    private function sender(Pack $pack, array $index): ?string
    {
        if ($pack->sender === null) {
            return null;
        }
        $agent = trim($index['user-agent'] ?? '');
        if (preg_match('/^([A-Za-z0-9][A-Za-z0-9._ -]{0,63}?)(?:\/|\s\(|$)/', $agent, $m) !== 1) {
            return null;
        }
        $product = trim($m[1]);
        if (in_array(strtolower($product), $pack->sender['ignore'], true)) {
            return null;
        }
        foreach ($pack->sender['strip'] as $suffix) {
            if (strlen($product) > strlen($suffix) && str_ends_with(strtolower($product), $suffix)) {
                $product = substr($product, 0, -strlen($suffix));
                break;
            }
        }
        $product = trim($product, ' .-_');

        return $product === '' || strlen($product) > 40 ? null : $product;
    }

    /**
     * GRP-1: the ids the first matching `by_event` entry yields, and the
     * ones that may link captures (GRP-2: not `group: false` types, not
     * search-only), best anchor first (GRP-3).
     *
     * @return array{0: list<array{type: string, id: string, searchOnly: bool}>, 1: list<GroupRef>}
     */
    private function objects(Pack $pack, string $eventType, JsonBody $json, string $slug): array
    {
        $entry = null;
        foreach ($pack->byEvent as $candidate) {
            foreach ($candidate['patterns'] as $pattern) {
                if (self::matches($pattern, $eventType)) {
                    $entry = $candidate;
                    break 2;
                }
            }
        }
        if ($entry === null) {
            return [[], []];
        }

        $objects = [];
        $refs = [];
        $seen = [];
        foreach ($entry['yield'] as $item) {
            $value = $json->at($item['path']);
            if ($value instanceof stdClass) {
                // Stripe expands referenced objects in place: take their id.
                $value = property_exists($value, 'id') ? $value->id : null;
            }
            $id = $this->scalar($value);
            $type = $item['type'] ?? $this->scalar($json->at((string) $item['typePath']), 64);
            if ($id === null || $type === null || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $type) !== 1) {
                continue;
            }
            if (isset($seen[$type."\0".$id]) || count($objects) >= $this->limits['max_object_ids']) {
                continue;
            }
            $seen[$type."\0".$id] = true;
            $objects[] = ['type' => $type, 'id' => $id, 'searchOnly' => $item['searchOnly']];

            if ($item['searchOnly'] || ! ($pack->types[$type]['group'] ?? true)) {
                continue;
            }
            $label = null;
            if ($item['labelPath'] !== null) {
                $label = $this->scalar($json->at($item['labelPath']));
                if ($label !== null && $item['label'] !== null) {
                    $label = str_replace('{value}', $label, $item['label']);
                }
            }
            $template = $pack->types[$type]['key'] ?? '{id}';
            $key = str_replace(['{id}', '{label}'], [$id, $label ?? $id], $template);
            $refs[] = new GroupRef($type, $id, 'grp_'.$slug.'_'.$key, $label ?? $id, $label !== null, $pack->rank($type));
        }
        usort($refs, fn (GroupRef $a, GroupRef $b): int => [$a->rank, $a->key] <=> [$b->rank, $b->key]);

        return [$objects, $refs];
    }

    /**
     * The GRP-4 id is the capture's object id too (search by `shp_…`).
     *
     * @return list<array{type: string, id: string, searchOnly: bool}>
     */
    private static function fallbackObjects(?GroupRef $fallback): array
    {
        return $fallback === null ? [] : [['type' => $fallback->type, 'id' => $fallback->id, 'searchOnly' => false]];
    }

    /** GRP-4: group on the first `data` field named `id` or `*_id`. */
    private function dataIdGroup(string $slug, JsonBody $json): ?GroupRef
    {
        $found = $json->firstDataId();
        if ($found === null) {
            return null;
        }
        [$field, $value] = $found;
        $id = $this->scalar($value);
        if ($id === null) {
            return null;
        }
        $type = $field === 'id' ? 'object' : substr($field, 0, -3);
        $type = preg_match('/^[a-z][a-z0-9_]{0,63}$/', $type) === 1 ? $type : 'object';

        return new GroupRef($type, $id, 'grp_'.$slug.'_'.$id, $id, false, 0);
    }

    /**
     * A derived string: a JSON string or integer (or a header value),
     * trimmed, at most `max_value_chars` characters, valid UTF-8 with no
     * control characters. Anything else is null.
     */
    private function scalar(mixed $value, ?int $max = null): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);
        $max ??= $this->limits['max_value_chars'];
        if ($value === '' || strlen($value) > $max * 4 || ! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max) {
            return null;
        }

        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1 ? null : $value;
    }

    /** Event type glob: `*` matches any run of characters. */
    private static function matches(string $pattern, string $eventType): bool
    {
        $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/s';

        return preg_match($regex, $eventType) === 1;
    }

    /**
     * First value per lowercase header name. Stored rows are checked again:
     * anything but a [name, value] pair of strings is skipped.
     *
     * @param  array<mixed>  $headers
     * @return array<string, string>
     */
    private static function headerIndex(array $headers): array
    {
        $index = [];
        foreach ($headers as $pair) {
            if (! is_array($pair) || ! isset($pair[0], $pair[1]) || ! is_string($pair[0]) || ! is_string($pair[1])) {
                continue;
            }
            $index[strtolower($pair[0])] ??= $pair[1];
        }

        return $index;
    }

    public static function slug(string $name): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');

        return $slug === '' ? 'unknown' : $slug;
    }
}
