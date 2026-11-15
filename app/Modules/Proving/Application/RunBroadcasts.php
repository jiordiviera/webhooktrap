<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use App\Infrastructure\Broadcasting\LiveUpdates;

/**
 * Run timeline direct events on `private-run.{runId}` (event-catalogue
 * §3.2, ADR-004 amendment 1) and the runs-list trigger on the project
 * channel (§3.1). Ids, numbers, codes and booleans only; never a display
 * value (PV-6): values load with the timeline reload.
 */
final readonly class RunBroadcasts
{
    public const string EVENT_RUNS = 'project.runs';

    public function __construct(private LiveUpdates $live) {}

    public static function channel(string $runId): string
    {
        return 'run.'.$runId;
    }

    /** @param  array<string, mixed>  $payload */
    public function timeline(string $runId, string $event, array $payload): void
    {
        $this->live->send(self::channel($runId), $event, ['v' => 1, ...$payload]);
    }

    public function runsList(string $projectId): void
    {
        $this->live->trigger('project.'.$projectId, self::EVENT_RUNS);
    }
}
