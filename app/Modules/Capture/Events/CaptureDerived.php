<?php

declare(strict_types=1);

namespace App\Modules\Capture\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A capture's derived facts were (re)computed (event-catalogue §2), raised
 * after the transaction commits. Listeners: the stream's live trigger;
 * Fixtures (drift, SHP-4) later. `projectId` is added to the catalogue's
 * fields so the trigger needs no lookup.
 */
final readonly class CaptureDerived
{
    use Dispatchable;

    public function __construct(
        public string $captureId,
        public string $projectId,
        public ?string $provider,
        public ?string $eventType,
        public ?string $groupKey,
        public ?string $shapeVerdict,
    ) {}
}
