<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The 72-hour wait started (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class AccountRecoveryConfirmed
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $requestId,
        public string $completesAt,
    ) {}
}
