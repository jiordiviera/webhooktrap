<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A pending deletion request closed; reason user|recovery|replaced|email_changed|expired (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class AccountDeletionCancelled
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $requestId,
        public string $reason,
    ) {}
}
