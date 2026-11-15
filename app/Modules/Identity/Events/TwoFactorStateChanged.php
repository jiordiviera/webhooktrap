<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The 2FA state changed (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class TwoFactorStateChanged
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public bool $enabled,
    ) {}
}
