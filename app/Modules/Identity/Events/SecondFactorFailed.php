<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A second-factor attempt failed; count in the 24 h window (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class SecondFactorFailed
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $kind,
        public int $countInWindow,
    ) {}
}
