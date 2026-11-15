<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\BrowserChannel;
use App\Modules\Identity\Contracts\Principal;

/** `private-session.{guestSessionId}` (event-catalogue §1): that live guest session only. */
final readonly class GuestSessionChannel implements BrowserChannel
{
    public function name(): string
    {
        return 'session';
    }

    public function allows(Principal $principal, string $id): bool
    {
        return $principal->isGuest() && hash_equals($principal->id, $id);
    }
}
