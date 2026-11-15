<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** An account was tombstoned; actor user|admin (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class AccountDeleted
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $subject,
        public string $actor,
    ) {}
}
