<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Password sign-in for an existing account is held (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class PasswordSignInHeld
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $until,
    ) {}
}
