<?php

declare(strict_types=1);

namespace App\Modules\Proving\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** An observation batch was committed (event-catalogue §2). Never values. */
final readonly class RunObservationsRecorded
{
    use Dispatchable;

    public function __construct(public string $runId, public int $fromSeq, public int $toSeq) {}
}
