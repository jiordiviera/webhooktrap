<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Infrastructure\Clock\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Stored captures per UTC day, instance-wide, for the operator dashboard
 * (decision 74). Counts only. Captures past retention are gone, so older
 * days undercount once purged.
 */
final readonly class CaptureVolume
{
    public function __construct(private Clock $clock) {}

    /** @return array<string, int> `Y-m-d` => count, oldest first, every day present */
    public function perDay(int $days): array
    {
        $days = max(1, min(90, $days));
        $start = $this->clock->now()->utc()->startOfDay()->subDays($days - 1);
        $counts = DB::table('captures')
            ->where('received_at', '>=', $start)
            ->selectRaw("to_char(received_at AT TIME ZONE 'UTC', 'YYYY-MM-DD') AS day, count(*) AS n")
            ->groupBy('day')
            ->pluck('n', 'day');

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i)->toDateString();
            $out[$day] = (int) ($counts[$day] ?? 0);
        }

        return $out;
    }
}
