<?php

declare(strict_types=1);

namespace App\Modules\Instance\Application;

use App\Infrastructure\Clock\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Process heartbeats (compose-profile §6): the scheduler writes `scheduler`
 * every minute from inside `schedule:work`; its Compose healthcheck
 * (`app:heartbeat-check --max-age=120`) and `/health` read the age. Kept in
 * the database, so every container sees the same value and a Valkey outage
 * does not make the scheduler look dead.
 */
final readonly class Heartbeats
{
    public const string SCHEDULER = 'scheduler';

    public function __construct(private Clock $clock) {}

    public function beat(string $name): void
    {
        DB::table('instance_heartbeats')->upsert(
            [['name' => $name, 'beat_at' => $this->clock->now()]],
            ['name'],
            ['beat_at'],
        );
    }

    /** Seconds since the last beat (never negative), or null when none was ever written. */
    public function age(string $name): ?int
    {
        $at = DB::table('instance_heartbeats')->where('name', $name)->value('beat_at');
        if (! is_string($at)) {
            return null;
        }

        return max(0, $this->clock->now()->getTimestamp() - CarbonImmutable::parse($at)->getTimestamp());
    }
}
