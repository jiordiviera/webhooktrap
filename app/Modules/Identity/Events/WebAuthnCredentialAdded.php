<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A WebAuthn credential was registered; kind passkey|security_key. Ids are stored uuids; never an address or a key. */
final readonly class WebAuthnCredentialAdded
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $keyId,
        public string $kind,
    ) {}
}
