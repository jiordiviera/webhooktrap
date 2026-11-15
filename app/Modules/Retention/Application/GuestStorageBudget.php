<?php

declare(strict_types=1);

namespace App\Modules\Retention\Application;

use Illuminate\Support\Facades\DB;

/**
 * Global guest storage budget (TM-02, ADR-005 amendment 1 §5): total kept
 * body bytes across live guest sessions, one counter row in
 * `instance_counters` (schema §10). Ingest reserves inside its transaction
 * with a guarded update; a database trigger gives a session's bytes back
 * when it ends, is claimed or is deleted.
 */
final readonly class GuestStorageBudget
{
    /** False when `$bytes` more would pass the budget (nothing changed). */
    public function reserve(int $bytes): bool
    {
        if ($bytes <= 0) {
            return true;
        }

        return DB::update(
            'UPDATE instance_counters SET guest_stored_bytes = guest_stored_bytes + ? WHERE id = 1 AND guest_stored_bytes + ? <= ?',
            [$bytes, $bytes, (int) config('capture.guest_storage_budget_bytes')],
        ) === 1;
    }

    public function used(): int
    {
        return (int) DB::table('instance_counters')->where('id', 1)->value('guest_stored_bytes');
    }
}
