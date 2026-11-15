<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A sign-in failed; hashed address only, never the email (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class SignInFailed
{
    use Dispatchable;

    public function __construct(
        public string $reason,
        public string $addressHash,
    ) {}
}
