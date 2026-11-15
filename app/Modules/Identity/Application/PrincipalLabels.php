<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\GuestSession;
use App\Modules\Identity\Domain\User;

/**
 * Short labels of a principal for other modules: the workspace label an
 * agent prints before its `[y/N]` (contracts §5.1, S-2) and the display name
 * on run provenance. Never an email address. Callers sanitise for terminals.
 */
final readonly class PrincipalLabels
{
    public function workspaceLabel(string $kind, string $id): string
    {
        if ($kind === 'guest') {
            $guest = GuestSession::query()->find($id);

            return $guest === null
                ? 'Guest session'
                : 'Guest session · expires '.$guest->expires_at->utc()->format('Y-m-d\TH:i:s\Z');
        }
        $name = $this->displayName($kind, $id);

        return $name === null ? 'Account workspace' : 'Account workspace · '.$name;
    }

    /** The user's chosen display name, or null (guests have none). */
    public function displayName(string $kind, string $id): ?string
    {
        if ($kind !== 'user') {
            return null;
        }
        $name = User::query()->whereKey($id)->whereNull('deleted_at')->value('name');

        return is_string($name) && trim($name) !== '' ? $name : null;
    }

    /** Guest session expiry (RFC 3339), for the pairing answer label. */
    public function guestExpiresAt(string $guestSessionId): ?string
    {
        $guest = GuestSession::query()->find($guestSessionId);

        return $guest?->expires_at->toIso8601ZuluString('millisecond');
    }
}
