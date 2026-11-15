<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A recovery was closed; by user|second_factor_signin|declined|link_expired (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class AccountRecoveryCancelled
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $requestId,
        public string $cancelledBy,
    ) {}
}
