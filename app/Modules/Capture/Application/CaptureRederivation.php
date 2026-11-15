<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Modules\Packs\Application\PackCatalog;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Re-derivation after a pack update (domain §4: derivations are re-runnable
 * when a pack updates). A capture is stale when `derived_with_pack` is not
 * one the current packs produce (or is null: never derived, or its job
 * failed). Projects are processed one at a time, oldest capture first, so
 * group merging and shape baselines see the same history a live run would.
 */
final readonly class CaptureRederivation
{
    /** Captures read per query. */
    private const int CHUNK = 200;

    public function __construct(private CaptureDerivation $derivation, private PackCatalog $packs) {}

    /**
     * Projects with captures to re-derive and how many.
     *
     * @param  list<string>|null  $providers
     * @return array<string, int> project id => captures
     */
    public function projects(bool $all, ?array $providers, ?string $projectId = null): array
    {
        $counts = [];
        $rows = $this->scope($all, $providers)
            ->when($projectId !== null, fn (Builder $q) => $q->where('project_id', $projectId))
            ->selectRaw('project_id, count(*) AS n')
            ->groupBy('project_id')->orderBy('project_id')->get();
        foreach ($rows as $row) {
            $counts[(string) $row->project_id] = (int) $row->n;
        }

        return $counts;
    }

    /**
     * One bounded run for a project: re-derives up to `perRun` matching
     * captures after `afterSeq`, oldest first. At the start of a project
     * (`afterSeq` 0) the matching captures lose their group first, so groups
     * a pack change splits are rebuilt rather than kept.
     *
     * @param  list<string>|null  $providers
     * @return int|null the seq to continue after, or null when done
     */
    public function run(string $projectId, bool $all, ?array $providers, int $afterSeq = 0, int $perRun = 2000): ?int
    {
        if ($afterSeq === 0) {
            $this->scope($all, $providers)->where('project_id', $projectId)
                ->whereNotNull('group_key')
                ->update(['group_key' => null, 'group_label' => null, 'group_anchor' => null]);
        }

        $done = 0;
        $cursor = $afterSeq;
        while ($done < $perRun) {
            $ids = $this->scope($all, $providers)
                ->where('project_id', $projectId)->where('seq', '>', $cursor)
                ->orderBy('seq')->limit(min(self::CHUNK, $perRun - $done))
                ->get(['id', 'seq']);
            if ($ids->isEmpty()) {
                return null;
            }
            foreach ($ids as $row) {
                $this->derivation->derive((string) $row->id);
                $cursor = (int) $row->seq;
                $done++;
            }
        }

        return $this->scope($all, $providers)->where('project_id', $projectId)->where('seq', '>', $cursor)->exists()
            ? $cursor : null;
    }

    /**
     * Captures selected for re-derivation: stale ones unless `all`. The seq
     * cursor moves past each capture as it is re-derived, so one that
     * becomes fresh is never selected twice.
     *
     * @param  list<string>|null  $providers
     */
    private function scope(bool $all, ?array $providers): Builder
    {
        $query = DB::table('captures');
        if (! $all) {
            $current = $this->packs->currentRefs();
            $query->where(fn (Builder $q) => $q->whereNull('derived_with_pack')->orWhereNotIn('derived_with_pack', $current));
        }
        if ($providers !== null) {
            $named = array_values(array_diff($providers, ['unknown']));
            $query->where(function (Builder $q) use ($providers, $named): void {
                if ($named !== []) {
                    $q->whereIn('provider', $named);
                }
                if (in_array('unknown', $providers, true)) {
                    $q->orWhereNull('provider');
                }
            });
        }

        return $query;
    }
}
