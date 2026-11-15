<?php

declare(strict_types=1);

namespace App\Modules\Capture\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The owner rotated the capture URL (event-catalogue §2); ids are stored uuids, never keys. */
final readonly class CaptureUrlRotated
{
    use Dispatchable;

    public function __construct(
        public string $projectId,
        public ?string $oldUrlId,
        public string $newUrlId,
    ) {}
}
