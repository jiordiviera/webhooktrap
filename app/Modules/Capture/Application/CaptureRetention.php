<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Workspace\Application\ProjectCounters;
use App\Modules\Workspace\Application\ProjectOwners;
use Illuminate\Support\Facades\DB;

/**
 * Capture retention for account projects (domain §12: 30 days by default,
 * per project): deletes captures (bodies with them) older than the
 * project's `capture_retention_days`, by `received_at`, in batches, and
 * gives each capture's `counted_bytes` back to the account quota. Sequence numbers are not
 * reused (the counter only grows). Guest data goes with its session.
 *
 * TODO(retention slice): guest expiry purge, run retention, metrics and the
 * purge-late alert live with the Retention module's maintenance jobs.
 */
final readonly class CaptureRetention
{
    public function __construct(
        private Clock $clock,
        private ProjectOwners $owners,
        private ProjectCounters $counters,
    ) {}

    /** Returns the number of captures deleted. */
    public function purgeExpired(): int
    {
        $batch = max(1, (int) config('capture.retention_batch'));
        $deleted = 0;
        foreach ($this->owners->captureRetention() as $projectId => $days) {
            $cutoff = $this->clock->now()->subDays($days);
            do {
                $rows = DB::transaction(function () use ($projectId, $cutoff, $batch): array {
                    $rows = DB::select(<<<'SQL'
                        DELETE FROM captures WHERE id IN (
                            SELECT id FROM captures WHERE project_id = ? AND received_at < ? ORDER BY received_at LIMIT ?
                        ) RETURNING counted_bytes
                        SQL, [$projectId, $cutoff, $batch]);
                    $bytes = 0;
                    foreach ($rows as $row) {
                        $bytes += (int) $row->counted_bytes;
                    }
                    if ($bytes > 0) {
                        $this->counters->releaseStoredBytes($projectId, $bytes);
                    }

                    return $rows;
                });
                $deleted += count($rows);
            } while (count($rows) === $batch);
        }

        return $deleted;
    }
}
