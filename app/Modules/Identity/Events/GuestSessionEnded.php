<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A guest session ended; reason user|expired (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class GuestSessionEnded
{
    use Dispatchable;

    public function __construct(
        public string $guestSessionId,
        public string $reason,
    ) {}
}
