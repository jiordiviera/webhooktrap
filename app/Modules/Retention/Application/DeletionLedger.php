<?php

declare(strict_types=1);

namespace App\Modules\Retention\Application;

use App\Infrastructure\Clock\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * App side of the deletion ledger (domain §2.10, DL-3a): an idempotent append
 * of `(usr_ id, deleted_at)`, never the address, written before the purge
 * deletes the `users` row.
 *
 * TODO(A10, DL-3b/c): ship each entry to the off-database ledger next to the
 * backup repository (`LEDGER_PATH`, age-encrypted off-server copy) and mark
 * `shipped_at`; the restore runbook re-applies entries before any process starts.
 */
final readonly class DeletionLedger
{
    public function __construct(private Clock $clock) {}

    /** Durable once this returns; throws when the write fails (the purge then keeps the row). */
    public function append(string $subject, CarbonImmutable $deletedAt): void
    {
        // Own (sub)transaction: a failed write never poisons the caller's.
        DB::transaction(fn () => DB::table('deletion_ledger')->insertOrIgnore([
            'subject' => $subject,
            'deleted_at' => $deletedAt,
            'appended_at' => $this->clock->now(),
        ]));
    }

    public function has(string $subject): bool
    {
        return DB::table('deletion_ledger')->where('subject', $subject)->exists();
    }
}
