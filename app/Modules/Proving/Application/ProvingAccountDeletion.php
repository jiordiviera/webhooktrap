<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use App\Modules\Identity\Contracts\AccountDeletionParticipant;
use App\Modules\Proving\Domain\Run;
use App\Modules\Workspace\Application\ProjectOwners;

/**
 * Proving's rows in account deletion (domain §2.10 "runs aborted rather
 * than refused"; A09 AC-4). Phase 1, inside the tombstone transaction:
 * every open run of the account's projects ends Aborted `account_deleted`
 * and its jobs are completed, so no agent fetches a capture or posts a
 * result after the tombstone. Phase 2 deletes the runs (jobs, deliveries,
 * scenarios and findings cascade) before Workspace deletes the projects.
 */
final readonly class ProvingAccountDeletion implements AccountDeletionParticipant
{
    public function __construct(private ProjectOwners $owners, private RunJobs $jobs) {}

    public function tombstone(string $userId): void
    {
        $this->jobs->abortForProjects($this->owners->projectIdsOfUser($userId));
    }

    public function purge(string $userId): void
    {
        $projectIds = $this->owners->projectIdsOfUser($userId);
        if ($projectIds !== []) {
            Run::query()->whereIn('project_id', $projectIds)->delete();
        }
    }

    /** @return array<string, mixed> */
    public function summary(string $userId): array
    {
        return [];
    }
}
