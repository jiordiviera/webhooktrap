<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A guest session began (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class GuestSessionStarted
{
    use Dispatchable;

    public function __construct(
        public string $guestSessionId,
    ) {}
}
