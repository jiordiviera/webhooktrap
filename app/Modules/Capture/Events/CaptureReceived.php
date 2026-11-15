<?php

declare(strict_types=1);

namespace App\Modules\Capture\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A capture was stored (event-catalogue §2), raised after the ingest
 * transaction commits. Listeners (derivation job, broadcast, signature check)
 * arrive with later slices; none may make persistence depend on them.
 */
final readonly class CaptureReceived
{
    use Dispatchable;

    public function __construct(
        public string $captureId,
        public string $projectId,
        public int $seq,
    ) {}
}
