<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A user account was created; method password|github|google|passkey (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class UserRegistered
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $method,
    ) {}
}
