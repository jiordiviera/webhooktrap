<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\GuestSession;
use App\Modules\Identity\Domain\User;

/**
 * Is a principal live (for other modules; domain §4 ingest "the project's
 * owner is live")? A user is live until tombstoned or disabled (domain
 * §2.10); a guest session until it expires, ends or is claimed.
 */
final readonly class PrincipalLiveness
{
    public function __construct(private Clock $clock) {}

    public function isLive(string $kind, string $id): bool
    {
        return match ($kind) {
            'user' => $this->userIsLive($id),
            'guest' => $this->guestIsLive($id),
            default => false,
        };
    }

    public function userIsLive(string $userId): bool
    {
        return User::query()->whereKey($userId)->whereNull('deleted_at')->whereNull('disabled_at')->exists();
    }

    public function guestIsLive(string $guestSessionId): bool
    {
        $guest = GuestSession::query()->find($guestSessionId);

        return $guest !== null && $guest->isLiveAt($this->clock->now());
    }
}
