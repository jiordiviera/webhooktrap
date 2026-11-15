<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A sign-in completed (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class UserSignedIn
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $sessionId,
        public string $firstFactor,
        public ?string $secondFactor,
    ) {}
}
