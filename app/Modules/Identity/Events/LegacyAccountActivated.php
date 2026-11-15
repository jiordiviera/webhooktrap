<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** An imported account proved its email; method reset|oauth (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class LegacyAccountActivated
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $method,
    ) {}
}
