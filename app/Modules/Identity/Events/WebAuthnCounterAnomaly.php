<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A credential was flagged; cause counter|user_handle_mismatch (event-catalogue §2). Ids are stored uuids; never an address or a code. */
final readonly class WebAuthnCounterAnomaly
{
    use Dispatchable;

    public function __construct(
        public string $userId,
        public string $keyId,
        public string $cause,
    ) {}
}
