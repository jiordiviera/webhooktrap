<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The recovery code set was replaced (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class RecoveryCodesRegenerated
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public int $codesLeft,
    ) {}
}
