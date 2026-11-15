<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A flagged credential was unblocked by its owner (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class WebAuthnCredentialUnblocked
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $keyId,
    ) {}
}
