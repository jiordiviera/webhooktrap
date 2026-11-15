<?php

declare(strict_types=1);

namespace App\Modules\Capture\Listeners;

use App\Modules\Capture\Application\CaptureKeys;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Identity\Events\GuestSessionClaimed;
use App\Modules\Identity\Events\GuestSessionEnded;
use App\Modules\Workspace\Application\ProjectOwners;
use App\Modules\Workspace\Events\ProjectDeleted;

/**
 * Evicts cached key resolutions when a project's owner stops being live or
 * changes (DL-2: after the tombstone commits, a capture to the deleted
 * owner's keys stores nothing at once). Synchronous: no queue in the way.
 */
final readonly class EvictCaptureKeys
{
    public function __construct(private CaptureKeys $keys, private ProjectOwners $owners) {}

    public function accountDeleted(AccountDeleted $event): void
    {
        $this->keys->evictProjects($this->owners->projectIdsOfUser($event->userId));
    }

    public function projectDeleted(ProjectDeleted $event): void
    {
        $this->keys->evictProjects([$event->projectId]);
    }

    /** The guest project moves to the user's workspace: its owner changes. */
    public function guestClaimed(GuestSessionClaimed $event): void
    {
        $this->keys->evictProjects(array_values(array_unique([
            ...$this->owners->projectIdsOfGuest($event->guestSessionId),
            ...$this->owners->projectIdsOfUser($event->userId),
        ])));
    }

    public function guestEnded(GuestSessionEnded $event): void
    {
        $this->keys->evictProjects($this->owners->projectIdsOfGuest($event->guestSessionId));
    }
}
