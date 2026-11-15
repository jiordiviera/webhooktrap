<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A WebAuthn credential was removed; actor user|recovery|admin (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class WebAuthnCredentialRemoved
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $keyId,
        public string $actor,
    ) {}
}
