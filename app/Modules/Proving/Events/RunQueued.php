<?php

declare(strict_types=1);

namespace App\Modules\Proving\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A run was queued for an executor (event-catalogue §2). Ids are stored uuids. */
final readonly class RunQueued
{
    use Dispatchable;

    public function __construct(public string $runId, public string $projectId, public string $executor, public ?string $agentId, public string $jobId) {}
}
