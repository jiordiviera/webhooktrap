<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Persistence\Bytea;
use App\Modules\Capture\Domain\Capture;
use App\Modules\Capture\Domain\PgArray;
use App\Modules\Capture\Domain\ShapeVerdict;
use App\Modules\Capture\Events\CaptureDerived;
use App\Modules\Packs\Application\PackCatalog;
use App\Modules\Packs\Contracts\DerivedFacts;
use App\Modules\Packs\Contracts\GroupRef;
use Illuminate\Support\Facades\DB;

/**
 * Capture derivations (domain §4): provider, event type, provider event id,
 * API version, object ids (GRP-1), group (GRP-2..4) and shape (SHP-1..3),
 * from pack data, after insert and again when a pack changes.
 *
 * Only derived columns are written; the request-facts trigger refuses the
 * rest. A run is idempotent: the per-capture facts are a pure function of
 * (headers, body, packs), and the cross-capture ones (group merging, shape
 * baseline) depend only on the project's already-derived captures, under a
 * per-project advisory lock so two derivations never merge groups at once.
 * The signature verdict is never touched here (TM-36: agents only).
 */
final readonly class CaptureDerivation
{
    /** SHP-2: most recent earlier captures read as the shape baseline. */
    private const int BASELINE_MAX = 500;

    public function __construct(private PackCatalog $packs) {}

    /** Derives one capture; false when it no longer exists. */
    public function derive(string $captureId): bool
    {
        $capture = Capture::query()->whereKey($captureId)
            ->first(['id', 'project_id', 'seq', 'headers', 'body_kept', 'body_size']);
        if ($capture === null) {
            return false;
        }

        $body = null;
        if ($capture->body_kept && $capture->body_size <= (int) config('packs.derive.max_json_bytes')) {
            $body = Bytea::read(DB::table('capture_bodies')->where('capture_id', $capture->id)->value('body'));
        }
        $facts = $this->packs->derive($capture->headers, $body);

        $result = DB::transaction(function () use ($capture, $facts): ?array {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['capture-derivation:'.$capture->project_id]);
            if (! Capture::query()->whereKey($capture->id)->exists()) {
                return null;
            }

            $group = $this->group($capture, $facts);
            $verdict = $this->verdict($capture, $facts);
            $objectIds = $facts->objectIds();

            DB::update(
                'UPDATE captures SET provider = ?, provider_name = ?, provider_event_id = ?, event_type = ?, api_version = ?,
                    object_ids = ?::text[], group_key = ?, group_label = ?, group_anchor = ?, shape = ?::jsonb,
                    shape_verdict = ?, derived_with_pack = ?
                 WHERE id = ?',
                [
                    $facts->provider, $facts->providerName, $facts->eventId, $facts->eventType, $facts->apiVersion,
                    $objectIds === [] ? null : PgArray::text($objectIds),
                    $group['key'] ?? null, $group['label'] ?? null, $group['anchor'] ?? null,
                    $facts->shape === null ? null : json_encode($facts->shape, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $verdict, $facts->packRef, $capture->id,
                ],
            );

            return ['key' => $group['key'] ?? null, 'verdict' => $verdict];
        });
        if ($result === null) {
            return false;
        }

        CaptureDerived::dispatch($capture->id, $capture->project_id, $facts->provider, $facts->eventType, $result['key'], $result['verdict']);

        return true;
    }

    /**
     * GRP-2..4. With pack objects: the capture joins every group of the same
     * provider that shares a linking id with it (transitively, since each
     * existing group is already closed), and the merged group takes the best
     * anchor (GRP-3 priority, then the key, so the result does not depend on
     * arrival order). Rows of the merged groups are re-keyed. Without pack
     * objects: the GRP-4 key, which needs no merging.
     *
     * @return array{key: string, label: string, anchor: string}|null
     */
    private function group(Capture $capture, DerivedFacts $facts): ?array
    {
        if ($facts->groupRefs === []) {
            $fallback = $facts->fallbackGroup;

            return $fallback === null ? null : ['key' => $fallback->key, 'label' => $fallback->label, 'anchor' => $fallback->type];
        }

        $ids = array_values(array_unique(array_map(fn (GroupRef $r): string => $r->id, $facts->groupRefs)));
        $related = DB::table('captures')
            ->where('project_id', $capture->project_id)
            ->where('provider', $facts->provider)
            ->where('id', '!=', $capture->id)
            ->whereNotNull('group_key')
            ->whereRaw('object_ids && ?::text[]', [PgArray::text($ids)])
            ->distinct()->get(['group_key', 'group_label', 'group_anchor']);

        /** @var list<array{rank: int, key: string, label: string, anchor: string, display: bool, own: bool}> $candidates */
        $candidates = [];
        foreach ($facts->groupRefs as $ref) {
            $candidates[] = ['rank' => $ref->rank, 'key' => $ref->key, 'label' => $ref->label, 'anchor' => $ref->type, 'display' => $ref->display, 'own' => true];
        }
        foreach ($related as $g) {
            $anchor = (string) $g->group_anchor;
            $candidates[] = [
                'rank' => $this->packs->anchorRank($facts->provider, $anchor),
                'key' => (string) $g->group_key, 'label' => (string) ($g->group_label ?? $g->group_key),
                'anchor' => $anchor, 'display' => false, 'own' => false,
            ];
        }
        usort($candidates, fn (array $a, array $b): int => [$a['rank'], $a['key']] <=> [$b['rank'], $b['key']]);
        $winner = $candidates[0];

        // Label: a display name this capture carries for the winning key
        // (`Order #1042`) beats a stored label; a stored one beats a bare id.
        $label = null;
        foreach ($candidates as $c) {
            if ($c['key'] === $winner['key'] && $c['own'] && $c['display']) {
                $label = $c['label'];
            }
        }
        if ($label === null) {
            foreach ($candidates as $c) {
                if ($c['key'] === $winner['key'] && ! $c['own']) {
                    $label = $c['label'];
                    break;
                }
            }
        }
        $label ??= $winner['label'];

        $keys = array_values(array_unique(array_merge([$winner['key']], array_map(fn (object $g): string => (string) $g->group_key, $related->all()))));
        DB::update(
            'UPDATE captures SET group_key = ?, group_label = ?, group_anchor = ?
             WHERE project_id = ? AND provider = ? AND id <> ? AND group_key = ANY(?::text[])
               AND (group_key, group_label, group_anchor) IS DISTINCT FROM (?, ?, ?)',
            [$winner['key'], $label, $winner['anchor'], $capture->project_id, $facts->provider, $capture->id, PgArray::text($keys),
                $winner['key'], $label, $winner['anchor']],
        );

        return ['key' => $winner['key'], 'label' => $label, 'anchor' => $winner['anchor']];
    }

    /**
     * SHP-2/3 against the earlier captures of the project with the same
     * (provider, event type, API version). Unknown providers, captures
     * without an event type and non-JSON bodies get no verdict. Saved
     * fixtures (the preferred baseline) arrive with the Fixtures slice.
     */
    private function verdict(Capture $capture, DerivedFacts $facts): ?string
    {
        if ($facts->provider === null || $facts->eventType === null || $facts->shape === null) {
            return null;
        }
        $baseline = DB::table('captures')
            ->where('project_id', $capture->project_id)
            ->where('provider', $facts->provider)
            ->where('event_type', $facts->eventType)
            ->when($facts->apiVersion === null, fn ($q) => $q->whereNull('api_version'), fn ($q) => $q->where('api_version', $facts->apiVersion))
            ->where('seq', '<', $capture->seq)
            ->whereNotNull('shape')
            ->orderByDesc('seq')->limit(self::BASELINE_MAX)
            ->pluck('shape');

        $shapes = [];
        foreach ($baseline as $raw) {
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $shapes[] = $decoded;
            }
        }

        return ShapeVerdict::of($facts->shape, $shapes);
    }
}
