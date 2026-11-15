<?php

declare(strict_types=1);

namespace App\Modules\Capture\Application;

use App\Modules\Identity\Contracts\AccountDeletionParticipant;
use App\Modules\Workspace\Application\ProjectOwners;
use Illuminate\Support\Facades\DB;

/**
 * Capture's rows in account deletion (domain §2.10 "Cascade per module":
 * capture URLs, captures, bodies). Phase 1 writes nothing: from the
 * tombstone the owner is not live, so ingest stores nothing (the ingest
 * transaction re-reads liveness under the project row lock the tombstone
 * also takes) and the `AccountDeleted` listener evicts the cached keys.
 * Phase 2 deletes the capture tree of every project of the account,
 * soft-deleted ones included, before Workspace deletes the projects. Keys
 * are not kept: non-reuse holds by randomness.
 */
final readonly class CaptureAccountDeletion implements AccountDeletionParticipant
{
    public function __construct(private ProjectOwners $owners) {}

    public function tombstone(string $userId): void {}

    public function purge(string $userId): void
    {
        $projectIds = $this->owners->projectIdsOfUser($userId);
        if ($projectIds === []) {
            return;
        }
        // capture_bodies cascade from captures.
        DB::table('captures')->whereIn('project_id', $projectIds)->delete();
        DB::table('capture_urls')->whereIn('project_id', $projectIds)->delete();
    }

    /** @return array<string, mixed> */
    public function summary(string $userId): array
    {
        return [];
    }
}
