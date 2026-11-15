<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Modules\Identity\Contracts\AccountDeletionParticipant;
use App\Modules\Workspace\Domain\Project;
use App\Modules\Workspace\Domain\Workspace;

/**
 * Workspace's rows in account deletion (domain §2.10 "Cascade per module":
 * workspace, projects). Phase 1 writes nothing: from the tombstone the owner
 * is not live, so `Workspace::isOwnedBy` never passes and every project page
 * is not found; ingest's owner-liveness check is the Capture slice's. Phase 2
 * deletes the workspace tree, soft-deleted projects included, before the
 * identity tree and the `users` row, in the purge transaction.
 */
final readonly class WorkspaceAccountDeletion implements AccountDeletionParticipant
{
    public function tombstone(string $userId): void
    {
        // Lock the account's project rows with the user row, so a counter
        // allocation or settings change in flight finishes before the tombstone
        // commits and none starts against a tombstoned owner's project.
        Project::query()->whereIn('workspace_id', Workspace::query()->select('id')->where('user_id', $userId))
            ->lockForUpdate()->pluck('id');
    }

    public function purge(string $userId): void
    {
        $workspaceIds = Workspace::query()->where('user_id', $userId)->pluck('id');
        Project::query()->whereIn('workspace_id', $workspaceIds)->delete();
        Workspace::query()->whereKey($workspaceIds)->delete();
    }

    /** @return array{projects: list<array{name: string}>} */
    public function summary(string $userId): array
    {
        $names = Project::query()->live()
            ->whereIn('workspace_id', Workspace::query()->select('id')->where('user_id', $userId))
            ->orderByRaw('lower(name)')->pluck('name');

        return ['projects' => array_values($names->map(fn (string $name): array => ['name' => $name])->all())];
    }
}
