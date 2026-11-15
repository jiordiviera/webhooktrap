<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The password was reset by email (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class PasswordReset
{
    use Dispatchable;

    public function __construct(
        public string $userId,
    ) {}
}
