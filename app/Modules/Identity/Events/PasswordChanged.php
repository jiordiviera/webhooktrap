<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The password was set or changed while signed in (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class PasswordChanged
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public ?string $sessionId,
    ) {}
}
