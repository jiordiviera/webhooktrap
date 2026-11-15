<?php

declare(strict_types=1);

namespace App\Modules\Capture\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** An executor fetched a capture for a job (event-catalogue §2, TM-35). Ids are stored uuids; never content. */
final readonly class CaptureFetchedByExecutor
{
    use Dispatchable;

    public function __construct(
        public string $captureId,
        public string $jobId,
        public ?string $agentId,
        public ?string $runnerId,
    ) {}
}
