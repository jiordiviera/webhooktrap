<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The account address changed (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class EmailChanged
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $requestId,
    ) {}
}
