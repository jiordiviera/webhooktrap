<?php

declare(strict_types=1);

namespace App\Modules\Proving\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A run reached a final status (event-catalogue §2, RUN-1). */
final readonly class RunFinished
{
    use Dispatchable;

    public function __construct(public string $runId, public string $projectId, public string $status, public ?string $reason, public ?int $exitCode, public ?string $agentId) {}
}
