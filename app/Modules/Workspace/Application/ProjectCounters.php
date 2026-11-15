<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Infrastructure\Clock\Clock;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Per-project counters (domain §3, schema §3): capture `seq` and run `number`
 * are allocated under a row lock (`SELECT … FOR UPDATE`), so they are never
 * handed out twice and never reused after deletion or retention (IA §2.3).
 * The account capture quota is one guarded `UPDATE` (schema "Atomic quota
 * updates"). Callers (Capture, Proving) call these inside their own insert
 * transaction; each call is its own savepoint otherwise.
 */
final readonly class ProjectCounters
{
    public function __construct(private Clock $clock) {}

    public function nextCaptureSeq(string $projectId): int
    {
        return $this->allocate($projectId, 'next_capture_seq');
    }

    public function nextRunNumber(string $projectId): int
    {
        return $this->allocate($projectId, 'next_run_number');
    }

    /**
     * Account projects only (guest quotas are the guest session's counters,
     * Identity). True when one more capture of `$bytes` fits the daily count
     * and the stored-bytes limit, and counts it; false (nothing changed) when
     * over quota, deleted or a guest project: ingest then answers identically
     * and stores nothing (ADR-005 amendment 1 §5, TM-02).
     */
    public function reserveCapture(string $projectId, int $bytes): bool
    {
        $today = $this->clock->now()->utc()->toDateString();

        return DB::update(<<<'SQL'
            UPDATE projects
               SET captures_today = CASE WHEN captures_day = ?::date THEN captures_today + 1 ELSE 1 END,
                   captures_day = ?::date,
                   stored_body_bytes = stored_body_bytes + ?
             WHERE id = ? AND deleted_at IS NULL AND NOT is_guest
               AND (captures_day IS DISTINCT FROM ?::date OR captures_today < ?)
               AND stored_body_bytes + ? <= ?
            SQL, [
            $today, $today, $bytes, $projectId, $today,
            (int) config('workspace.quota.captures_per_day'), $bytes, (int) config('workspace.quota.stored_body_bytes'),
        ]) === 1;
    }

    /** Retention and capture deletion give stored bytes back. */
    public function releaseStoredBytes(string $projectId, int $bytes): void
    {
        DB::update('UPDATE projects SET stored_body_bytes = GREATEST(stored_body_bytes - ?, 0) WHERE id = ?', [$bytes, $projectId]);
    }

    private function allocate(string $projectId, string $column): int
    {
        return DB::transaction(function () use ($projectId, $column): int {
            $row = DB::table('projects')->where('id', $projectId)->whereNull('deleted_at')->lockForUpdate()->first([$column]);
            if ($row === null) {
                throw new RuntimeException('Project not found or deleted.');
            }
            $value = (int) $row->{$column};
            DB::table('projects')->where('id', $projectId)->update([$column => $value + 1]);

            return $value;
        });
    }
}
