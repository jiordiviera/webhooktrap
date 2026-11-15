<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A deletion link was sent (DL-4) (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class AccountDeletionRequested
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $requestId,
    ) {}
}
