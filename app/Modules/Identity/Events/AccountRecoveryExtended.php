<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The wait was extended after a reset by email (RC-2) (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class AccountRecoveryExtended
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $requestId,
        public string $completesAt,
    ) {}
}
