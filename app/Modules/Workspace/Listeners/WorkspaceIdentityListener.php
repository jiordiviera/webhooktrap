<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Listeners;

use App\Modules\Identity\Events\GuestSessionClaimed;
use App\Modules\Identity\Events\GuestSessionEnded;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Workspace\Application\WorkspaceOwnership;

/**
 * Workspace reacts to Identity events (event-catalogue §2), synchronously so
 * the claim stays one transaction.
 */
final readonly class WorkspaceIdentityListener
{
    public function __construct(private WorkspaceOwnership $ownership) {}

    public function registered(UserRegistered $event): void
    {
        $this->ownership->ensureForUser($event->userId);
    }

    public function claimed(GuestSessionClaimed $event): void
    {
        $this->ownership->absorbGuestWorkspace($event->guestSessionId, $event->userId, $event->empty);
    }

    public function ended(GuestSessionEnded $event): void
    {
        // "End session now" deletes the session's data; expiry leaves it to Retention.
        if ($event->reason === 'user') {
            $this->ownership->deleteGuestWorkspace($event->guestSessionId);
        }
    }
}
