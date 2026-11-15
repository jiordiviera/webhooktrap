<?php

declare(strict_types=1);

namespace App\Modules\Packs\Domain;

use RuntimeException;

/**
 * One provider pack (`packs/<id>/<version>.json`, format 1, schema in
 * `packs/_schema/pack.schema.json`), parsed and checked once at load. The
 * checks here are the ones derivation relies on; the JSON Schema is the
 * full description shared with the engine.
 */
final readonly class Pack
{
    /**
     * @param  list<string>  $detectHeaders  lowercase names
     * @param  array{strip: list<string>, ignore: list<string>}|null  $sender
     * @param  list<array{0: 'header'|'body', 1: string}>  $eventType
     * @param  list<array{0: 'header'|'body', 1: string}>  $eventId
     * @param  list<array{0: 'header'|'body', 1: string}>  $apiVersion
     * @param  array<string, array{name: string, group: bool, key: string}>  $types
     * @param  list<string>  $anchors
     * @param  list<array{patterns: list<string>, yield: list<array{path: string, type: ?string, typePath: ?string, labelPath: ?string, label: ?string, searchOnly: bool}>}>  $byEvent
     * @param  array{ms: int, source: string, label: string}|null  $budget
     */
    public function __construct(
        public string $id,
        public string $version,
        public string $name,
        public array $detectHeaders,
        public ?array $sender,
        public array $eventType,
        public array $eventId,
        public array $apiVersion,
        public array $types,
        public array $anchors,
        public array $byEvent,
        public bool $dataIdFallback,
        public ?array $budget,
    ) {}

    public function ref(): string
    {
        return $this->id.'@'.$this->version;
    }

    /** GRP-3 rank: position in the anchor priority; unlisted types rank after every listed one. */
    public function rank(string $type): int
    {
        $i = array_search($type, $this->anchors, true);

        return $i === false ? count($this->anchors) : $i;
    }

    public function typeName(string $type): string
    {
        return $this->types[$type]['name'] ?? self::humanize($type);
    }

    public static function humanize(string $type): string
    {
        return ucfirst(str_replace('_', ' ', $type));
    }

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data, string $file): self
    {
        $fail = fn (string $why): RuntimeException => new RuntimeException("Pack {$file}: {$why}.");
        $string = function (mixed $value, string $field) use ($fail): string {
            if (! is_string($value) || $value === '') {
                throw $fail("`{$field}` must be a non-empty string");
            }

            return $value;
        };

        if (($data['format'] ?? null) !== 1) {
            throw $fail('unsupported format');
        }
        $id = $string($data['id'] ?? null, 'id');
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/', $id) !== 1) {
            throw $fail('bad id');
        }
        $version = $string($data['version'] ?? null, 'version');
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw $fail('bad version');
        }

        $detect = $data['detect']['headers_all'] ?? null;
        if (! is_array($detect) || $detect === []) {
            throw $fail('`detect.headers_all` must list at least one header');
        }

        // `event`: a header wins over a body path when a pack sets both.
        $event = $data['event'] ?? null;
        if (! is_array($event) || (! isset($event['type_header']) && ! isset($event['type_path']))) {
            throw $fail('`event` needs type_header or type_path');
        }
        $sources = function (string $what) use ($event, $string): array {
            $out = [];
            if (isset($event[$what.'_header'])) {
                $out[] = ['header', $string($event[$what.'_header'], "event.{$what}_header")];
            }
            if (isset($event[$what.'_path'])) {
                $out[] = ['body', $string($event[$what.'_path'], "event.{$what}_path")];
            }

            return $out;
        };

        $sender = null;
        if (isset($data['sender'])) {
            if (($data['sender']['from'] ?? null) !== 'user_agent_product') {
                throw $fail('`sender.from` must be user_agent_product');
            }
            $sender = [
                'strip' => array_values(array_map(fn ($s): string => strtolower((string) $s), (array) ($data['sender']['strip_suffixes'] ?? []))),
                'ignore' => array_values(array_map(fn ($s): string => strtolower((string) $s), (array) ($data['sender']['ignore_products'] ?? []))),
            ];
        }

        $types = [];
        $byEvent = [];
        $objects = $data['grouping'] ?? null;
        if (! is_array($objects) || ! is_array($objects['types'] ?? null) || ! is_array($objects['anchors'] ?? null) || ! is_array($objects['by_event'] ?? null)) {
            throw $fail('`grouping` needs types, anchors and by_event');
        }
        foreach ($objects['types'] as $type => $def) {
            $key = (string) ($def['key'] ?? '{id}');
            if (! str_contains($key, '{id}') && ! str_contains($key, '{label}')) {
                throw $fail("type `{$type}` key template needs {id} or {label}");
            }
            $types[(string) $type] = [
                'name' => $string($def['name'] ?? null, "grouping.types.{$type}.name"),
                'group' => ($def['group'] ?? true) !== false,
                'key' => $key,
            ];
        }
        $anchors = array_values(array_map('strval', $objects['anchors']));
        foreach ($objects['by_event'] as $i => $entry) {
            $patterns = $entry['events'] ?? null;
            $yield = $entry['yield'] ?? null;
            if (! is_array($patterns) || $patterns === [] || ! is_array($yield)) {
                throw $fail("`grouping.by_event.{$i}` needs events and yield");
            }
            $items = [];
            foreach ($yield as $item) {
                $type = isset($item['type']) ? (string) $item['type'] : null;
                $typePath = isset($item['type_path']) ? (string) $item['type_path'] : null;
                if (($type === null) === ($typePath === null)) {
                    throw $fail("`grouping.by_event.{$i}` yields need exactly one of type, type_path");
                }
                $items[] = [
                    'path' => $string($item['path'] ?? null, "grouping.by_event.{$i}.yield.path"),
                    'type' => $type,
                    'typePath' => $typePath,
                    'labelPath' => isset($item['label_path']) ? (string) $item['label_path'] : null,
                    'label' => isset($item['label']) ? (string) $item['label'] : null,
                    'searchOnly' => ($item['search_only'] ?? false) === true,
                ];
            }
            $byEvent[] = ['patterns' => array_values(array_map('strval', $patterns)), 'yield' => $items];
        }

        $fallback = $objects['fallback'] ?? null;
        if ($fallback !== null && $fallback !== 'data_id') {
            throw $fail('`grouping.fallback` must be data_id or null');
        }

        $budget = null;
        if (isset($data['budget'])) {
            $budget = [
                'ms' => (int) ($data['budget']['ms'] ?? 0),
                'source' => (string) ($data['budget']['source'] ?? ''),
                'label' => (string) ($data['budget']['label'] ?? ''),
            ];
        }

        return new self(
            id: $id,
            version: $version,
            name: $string($data['name'] ?? null, 'name'),
            detectHeaders: array_values(array_map(fn ($h): string => strtolower((string) $h), $detect)),
            sender: $sender,
            eventType: $sources('type'),
            eventId: $sources('id'),
            apiVersion: $sources('api_version'),
            types: $types,
            anchors: $anchors,
            byEvent: $byEvent,
            dataIdFallback: $fallback === 'data_id',
            budget: $budget,
        );
    }
}
