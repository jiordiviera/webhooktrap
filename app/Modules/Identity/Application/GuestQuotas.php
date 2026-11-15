<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Guest session quotas held on the session row (A-13; schema §3 "Atomic
 * quota updates"). Each check is one guarded `UPDATE`, so concurrent requests
 * never pass the limit, and it fails for a session that is no longer live.
 */
final readonly class GuestQuotas
{
    public function __construct(private Clock $clock) {}

    /**
     * Inside the ingest transaction: counts one stored capture of `$bytes`
     * counted bytes when the session is live, under its capture quota and
     * under its byte cap (A09 S3-8). False: nothing changed; ingest answers
     * identically and stores nothing.
     */
    public function reserveCapture(string $guestSessionId, int $bytes): bool
    {
        $quotas = (array) config('identity.guest.quotas');

        return DB::update(<<<'SQL'
            UPDATE guest_sessions
               SET stored_captures = stored_captures + 1, stored_bytes = stored_bytes + ?
             WHERE id = ? AND stored_captures < ? AND stored_bytes + ? <= ?
               AND expires_at > ? AND ended_at IS NULL AND claimed_at IS NULL
            SQL, [$bytes, $guestSessionId, (int) $quotas['captures'], $bytes, (int) $quotas['stored_bytes'], $this->clock->now()]) === 1;
    }

    /**
     * Starting a run checks the run quotas without counting (A-13: a run
     * that fails to start must not burn quota; schema §3).
     */
    public function canStartRun(string $guestSessionId): bool
    {
        $quotas = (array) config('identity.guest.quotas');

        return DB::table('guest_sessions')->where('id', $guestSessionId)
            ->where('counted_runs', '<', (int) $quotas['runs'])
            ->where('accepted_runs', '<', (int) $quotas['accepted_runs'])
            ->where('expires_at', '>', $this->clock->now())
            ->whereNull('ended_at')->whereNull('claimed_at')
            ->exists();
    }

    /** `RunAccepted` (A09 §7.3): counted by the server, whatever the agent reports. */
    public function countAcceptedRun(string $guestSessionId): void
    {
        DB::update('UPDATE guest_sessions SET accepted_runs = accepted_runs + 1 WHERE id = ?', [$guestSessionId]);
    }

    /** A finished run that got past Once (A-13); the caller guards it with `runs.quota_counted`. */
    public function countRun(string $guestSessionId): void
    {
        DB::update('UPDATE guest_sessions SET counted_runs = counted_runs + 1 WHERE id = ? AND counted_runs < ?',
            [$guestSessionId, (int) config('identity.guest.quotas.runs')]);
    }
}
