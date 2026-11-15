<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Ownership of the account address was proven; via link|reset|oauth|password_set_link (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class EmailVerified
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $via,
    ) {}
}
