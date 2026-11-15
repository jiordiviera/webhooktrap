<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A provider identity was linked; via signup|oauth_email_match|settings (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class IdentityLinked
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $provider,
        public string $via,
    ) {}
}
