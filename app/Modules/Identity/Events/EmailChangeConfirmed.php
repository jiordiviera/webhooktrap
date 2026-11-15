<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** One half of a two-sided email change was pressed; side new|old (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class EmailChangeConfirmed
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $requestId,
        public string $side,
    ) {}
}
