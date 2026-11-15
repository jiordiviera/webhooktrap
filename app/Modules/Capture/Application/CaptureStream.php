<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Persistence\Bytea;
use App\Modules\Capture\Domain\Capture;
use App\Modules\Capture\Domain\DisplayText;
use App\Modules\Capture\Domain\InspectorBody;
use App\Modules\Capture\Domain\PgArray;
use App\Modules\Capture\Domain\StreamQuery;
use App\Modules\Packs\Application\PackCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the capture stream (S-03) and the delivery detail (S-04),
 * contracts §3.2–§3.3 and the slice 4 notes. Callers have already checked
 * that the principal owns the project (`Projects::owned`); every method takes
 * the project's stored id and never reads another project's rows.
 *
 * Grouping: rows carry the derived `group_key` and `group_label` (GRP-2..4,
 * filled by the derivation job after insert). Until a capture is derived it
 * is ungrouped; `?group=ungrouped` selects captures without a key.
 */
final readonly class CaptureStream
{
    /** Worst first (S-03 group row "worst signature state"); null (not checked) sits in the middle. */
    private const array SIGNATURE_RANK = [
        'invalid' => 6, 'unknown_scheme' => 5, 'no_secret' => 4, 'not_checked' => 3, 'unsigned' => 2, 'valid' => 1,
    ];

    private const array ROW_COLUMNS = [
        'seq', 'received_at', 'method', 'path', 'provider', 'provider_name', 'event_type', 'group_key', 'group_label',
        'sig_state', 'sig_reason', 'sig_checks', 'body_size', 'body_kept', 'shape_verdict',
    ];

    public function __construct(private PackCatalog $packs) {}

    /**
     * Newest captures matching the query (`captures.latest`, live).
     *
     * @return list<array<string, mixed>>
     */
    public function latest(string $projectId, StreamQuery $query): array
    {
        return $this->page($projectId, $query, null)['rows'];
    }

    /**
     * One page of older captures, newest first (`captures.older`).
     *
     * @return array{rows: list<array<string, mixed>>, nextBefore: int|null}
     */
    public function older(string $projectId, StreamQuery $query, int $before): array
    {
        return $this->page($projectId, $query, $before);
    }

    /**
     * Header counts (S-03 "13 deliveries · 7 groups · last 14:06:39") and
     * whether a page older than `latest` exists.
     *
     * @return array{total: int, matching: int, lastReceivedAt: string|null, hasOlder: bool}
     */
    public function stats(string $projectId, StreamQuery $query): array
    {
        $all = Capture::query()->where('project_id', $projectId)
            ->selectRaw('count(*) AS total, max(received_at) AS last_at')->toBase()->first();
        $total = (int) ($all->total ?? 0);
        $matching = $query->filtered() ? $this->filtered($projectId, $query)->count() : $total;

        return [
            'total' => $total,
            'matching' => $matching,
            'lastReceivedAt' => is_string($all->last_at ?? null) ? self::iso(CarbonImmutable::parse($all->last_at)) : null,
            'hasOlder' => $matching > self::pageSize(),
        ];
    }

    /**
     * Group summaries for the grouped view (`groups`, deferred and live), most
     * recent activity first (S-03 "Group order"). Captures without a group key
     * form one summary with `key: null` (Ungrouped).
     *
     * @return list<array<string, mixed>>
     */
    public function groups(string $projectId, StreamQuery $query): array
    {
        $rank = 'CASE coalesce(sig_state, \'not_checked\')';
        foreach (self::SIGNATURE_RANK as $state => $value) {
            $rank .= " WHEN '{$state}' THEN {$value}";
        }
        $rank .= ' END';

        $rows = $this->filtered($projectId, $query)->toBase()
            ->selectRaw("group_key, max(provider) AS provider, max(provider_name) AS provider_name, max(group_label) AS label,
                max(group_anchor) AS anchor, count(*) AS count, min(seq) AS first_seq, max(seq) AS last_seq,
                min(received_at) AS first_at, max(received_at) AS last_at, max({$rank}) AS worst")
            ->groupBy('group_key')->orderByRaw('max(received_at) DESC')
            ->limit((int) config('capture.stream.groups_max', 200))->get();
        $states = array_flip(self::SIGNATURE_RANK);

        return array_values($rows->map(fn (object $g): array => [
            'key' => is_string($g->group_key) ? $g->group_key : null,
            'label' => is_string($g->group_key) && is_string($g->label) ? DisplayText::line($g->label)[0] : null,
            'kind' => is_string($g->group_key) && is_string($g->anchor) ? $this->packs->objectName(is_string($g->provider) ? $g->provider : null, $g->anchor) : null,
            'provider' => is_string($g->provider) ? $g->provider : null,
            'providerName' => is_string($g->provider_name) ? DisplayText::line($g->provider_name)[0] : null,
            'count' => (int) $g->count,
            'firstSeq' => (int) $g->first_seq,
            'lastSeq' => (int) $g->last_seq,
            'firstAt' => self::iso(CarbonImmutable::parse((string) $g->first_at)),
            'lastAt' => self::iso(CarbonImmutable::parse((string) $g->last_at)),
            'worstSignature' => $states[(int) $g->worst] ?? 'not_checked',
        ])->all());
    }

    /** Whether any capture of the project has a group key yet (the grouped view's default). */
    public function groupingAvailable(string $projectId): bool
    {
        return Capture::query()->where('project_id', $projectId)->whereNotNull('group_key')->exists();
    }

    public function newestSeq(string $projectId): ?int
    {
        $seq = Capture::query()->where('project_id', $projectId)->max('seq');

        return is_numeric($seq) ? (int) $seq : null;
    }

    /**
     * `CaptureDetail` (contracts §3.3) for the owner, or null when the project
     * has no capture with this number.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $projectId, int $seq): ?array
    {
        $capture = Capture::query()->where('project_id', $projectId)->where('seq', $seq)->first();
        if ($capture === null) {
            return null;
        }

        $hidden = false;
        $line = function (?string $value) use (&$hidden): ?string {
            if ($value === null) {
                return null;
            }
            [$text, $escaped] = DisplayText::line($value);
            $hidden = $hidden || $escaped;

            return $text;
        };

        $headers = [];
        foreach ($capture->headers as [$name, $value]) {
            $headers[] = [(string) $line($name), (string) $line($value)];
        }

        $detail = self::row($capture->only(self::ROW_COLUMNS));
        $detail['method'] = (string) $line($capture->method);
        $detail['path'] = (string) $line($capture->path);
        $detail += [
            'id' => $capture->publicId(),
            'rawTarget' => (string) $line($capture->raw_target),
            'rawQuery' => $line($capture->raw_query),
            'headers' => $headers,
            'redactedHeaders' => $capture->redactedHeaders(),
            'headerFidelity' => $capture->header_fidelity,
            'publicOrigin' => (string) $line($capture->public_origin),
            'publicOriginSource' => $capture->public_origin_source,
            'sourceIp' => $capture->source_ip,
            'contentType' => $line($capture->content_type),
            'bodySha256' => bin2hex((string) $capture->body_sha256),
        ];

        if ($capture->body_kept) {
            $bytes = $this->keptBody($capture->id) ?? '';
            $body = InspectorBody::from(
                $bytes,
                (int) config('capture.inspector.text_max_bytes'),
                (int) config('capture.inspector.binary_max_bytes'),
            );
            $detail['body'] = $body->toProps();
            $hidden = $hidden || $body->escaped;
        }
        $detail['hiddenCharacters'] = $hidden;

        $checks = self::checks($capture->getAttributes()['sig_checks'] ?? null);
        if ($checks !== null) {
            $detail['signatureChecks'] = $checks;
        }

        $attributes = $capture->getAttributes();
        if (is_string($attributes['derived_with_pack'] ?? null)) {
            $detail['decoded'] = $this->decoded($attributes, $line);
        }
        $shape = is_string($attributes['shape'] ?? null) ? json_decode($attributes['shape'], true) : null;
        if (is_array($shape)) {
            $detail['shape'] = array_values(array_map(
                fn (array $pair): array => [(string) $line((string) $pair[0]), (string) $pair[1]],
                array_filter($shape, fn (mixed $p): bool => is_array($p) && isset($p[0], $p[1]) && is_string($p[0]) && is_string($p[1])),
            ));
        }

        return $detail;
    }

    /**
     * The Decoded tab's derived facts (contracts, packs slice notes): what
     * the packs read from this capture. Values are display text.
     *
     * @param  array<string, mixed>  $a  raw capture attributes
     * @param  callable(?string): ?string  $line
     * @return array<string, mixed>
     */
    private function decoded(array $a, callable $line): array
    {
        $provider = is_string($a['provider'] ?? null) ? $a['provider'] : null;
        $group = null;
        if (is_string($a['group_key'] ?? null)) {
            $group = [
                'key' => $a['group_key'],
                'label' => $line(is_string($a['group_label'] ?? null) ? $a['group_label'] : $a['group_key']),
                'kind' => is_string($a['group_anchor'] ?? null) ? $this->packs->objectName($provider, $a['group_anchor']) : null,
            ];
        }

        return [
            'provider' => $provider,
            'providerName' => $line(is_string($a['provider_name'] ?? null) ? $a['provider_name'] : null),
            'eventType' => $line(is_string($a['event_type'] ?? null) ? $a['event_type'] : null),
            'providerEventId' => $line(is_string($a['provider_event_id'] ?? null) ? $a['provider_event_id'] : null),
            'apiVersion' => $line(is_string($a['api_version'] ?? null) ? $a['api_version'] : null),
            'objectIds' => array_map(fn (string $id): string => (string) $line($id), PgArray::parse($a['object_ids'] ?? null)),
            'group' => $group,
            'pack' => $a['derived_with_pack'],
        ];
    }

    /**
     * Previous and next capture in the same group (S-04 footer); captures
     * without a group key step through the other ungrouped ones.
     *
     * @return array{prev: int|null, next: int|null}
     */
    public function neighbours(string $projectId, int $seq): array
    {
        $groupKey = Capture::query()->where('project_id', $projectId)->where('seq', $seq)->value('group_key');
        $same = fn (): Builder => Capture::query()->where('project_id', $projectId)
            ->when(is_string($groupKey), fn (Builder $q) => $q->where('group_key', $groupKey), fn (Builder $q) => $q->whereNull('group_key'));
        $prev = $same()->where('seq', '<', $seq)->max('seq');
        $next = $same()->where('seq', '>', $seq)->min('seq');

        return ['prev' => is_numeric($prev) ? (int) $prev : null, 'next' => is_numeric($next) ? (int) $next : null];
    }

    /** The exact kept bytes of capture `#seq`, or null when there is none or its body was not kept. */
    public function rawBody(string $projectId, int $seq): ?string
    {
        $capture = Capture::query()->where('project_id', $projectId)->where('seq', $seq)->first(['id', 'body_kept']);
        if ($capture === null || ! $capture->body_kept) {
            return null;
        }

        return $this->keptBody($capture->id);
    }

    /** @return array{rows: list<array<string, mixed>>, nextBefore: int|null} */
    private function page(string $projectId, StreamQuery $query, ?int $before): array
    {
        $size = self::pageSize();
        $rows = $this->filtered($projectId, $query)
            ->when($before !== null, fn (Builder $q) => $q->where('seq', '<', $before))
            ->orderByDesc('seq')->limit($size + 1)->toBase()->get(self::ROW_COLUMNS);
        $more = $rows->count() > $size;
        $rows = $rows->take($size);

        return [
            'rows' => array_values($rows->map(fn (object $r): array => self::row((array) $r))->all()),
            'nextBefore' => $more ? (int) $rows->last()->seq : null,
        ];
    }

    /** @return Builder<Capture> */
    private function filtered(string $projectId, StreamQuery $query): Builder
    {
        $builder = Capture::query()->where('project_id', $projectId);
        if ($query->group !== null) {
            $query->group === StreamQuery::UNGROUPED
                ? $builder->whereNull('group_key')
                : $builder->where('group_key', $query->group);
        }
        if ($query->q !== null) {
            $q = $query->q;
            $like = '%'.addcslashes($q, '\\%_').'%';
            $seq = StreamQuery::seq($q);
            $builder->where(function (Builder $w) use ($q, $like, $seq): void {
                $w->where('event_type', 'ilike', $like)
                    ->orWhere('provider', 'ilike', $like)
                    ->orWhere('provider_name', 'ilike', $like)
                    ->orWhere('path', 'ilike', $like)
                    ->orWhere('method', 'ilike', $like)
                    ->orWhere('provider_event_id', $q)
                    ->orWhereRaw('object_ids @> ARRAY[?]::text[]', [$q]);
                if ($seq !== null) {
                    $w->orWhere('seq', $seq);
                }
            });
        }

        return $builder;
    }

    /**
     * `CaptureRow` (contracts §3.3).
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private static function row(array $r): array
    {
        $receivedAt = $r['received_at'] instanceof CarbonImmutable ? $r['received_at'] : CarbonImmutable::parse((string) $r['received_at']);
        $signature = ['state' => is_string($r['sig_state']) ? $r['sig_state'] : 'not_checked'];
        if (is_string($r['sig_reason'])) {
            $signature['reason'] = $r['sig_reason'];
        }
        $checks = self::checks($r['sig_checks'] ?? null);
        if ($checks !== null) {
            $signature['checksPassed'] = count(array_filter($checks, fn (array $c): bool => $c['passed']));
            $signature['checksTotal'] = count($checks);
        }

        return [
            'seq' => (int) $r['seq'],
            'receivedAt' => self::iso($receivedAt),
            'method' => DisplayText::line((string) $r['method'])[0],
            'path' => DisplayText::line((string) $r['path'])[0],
            'provider' => is_string($r['provider']) ? $r['provider'] : null,
            'providerName' => is_string($r['provider_name']) ? DisplayText::line($r['provider_name'])[0] : null,
            'eventType' => is_string($r['event_type']) ? DisplayText::line($r['event_type'])[0] : null,
            'groupKey' => is_string($r['group_key']) ? $r['group_key'] : null,
            'groupLabel' => is_string($r['group_key']) && is_string($r['group_label']) ? DisplayText::line($r['group_label'])[0] : null,
            'signature' => $signature,
            'bodySize' => (int) $r['body_size'],
            'bodyKept' => (bool) $r['body_kept'],
            'shapeVerdict' => is_string($r['shape_verdict']) ? $r['shape_verdict'] : null,
        ];
    }

    /** @return list<array{check: string, passed: bool}>|null */
    private static function checks(mixed $raw): ?array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return null;
        }
        $checks = [];
        foreach ($decoded as $c) {
            if (is_array($c) && is_string($c['check'] ?? null) && is_bool($c['passed'] ?? null)) {
                $checks[] = ['check' => $c['check'], 'passed' => $c['passed']];
            }
        }

        return $checks;
    }

    private function keptBody(string $captureId): ?string
    {
        $raw = DB::table('capture_bodies')->where('capture_id', $captureId)->value('body');

        return Bytea::read($raw);
    }

    private static function pageSize(): int
    {
        return max(1, (int) config('capture.stream.page_size', 50));
    }

    private static function iso(CarbonImmutable $at): string
    {
        return $at->utc()->toIso8601ZuluString('millisecond');
    }
}
