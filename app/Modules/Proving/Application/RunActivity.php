<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use Illuminate\Support\Facades\DB;

/** Open runs across the instance, for the operator dashboard (decision 74). Counts only. */
final readonly class RunActivity
{
    /** @return array{queued: int, running: int} */
    public function active(): array
    {
        $counts = DB::table('runs')->whereIn('status', ['queued', 'running'])
            ->selectRaw('status, count(*) AS n')->groupBy('status')->pluck('n', 'status');

        return ['queued' => (int) ($counts['queued'] ?? 0), 'running' => (int) ($counts['running'] ?? 0)];
    }
}
