<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A recovery code was used (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class RecoveryCodeUsed
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public int $codesLeft,
    ) {}
}
