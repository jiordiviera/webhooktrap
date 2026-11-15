<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Modules\Agent\Domain\Agent;
use App\Modules\Agent\Domain\PairingCode;
use App\Modules\Identity\Contracts\AccountDeletionParticipant;
use App\Modules\Workspace\Application\WorkspaceOwnership;

/**
 * Agent's rows in account deletion (domain §2.10, DL-2; A09 AC-4). Phase 1,
 * inside the tombstone transaction: every live agent of the account's
 * workspace is revoked (`account_deleted`; their open runs end Aborted
 * through `AgentRevoked`), and every pairing code and access token is
 * deleted, so nothing authenticates or pairs during the tombstone even if a
 * later listener fails. Agent auth also checks owner liveness on every
 * request. Phase 2 deletes the agents (mirrors, tokens and verify jobs
 * cascade) and any code left.
 */
final readonly class AgentAccountDeletion implements AccountDeletionParticipant
{
    public function __construct(private WorkspaceOwnership $workspaces, private AgentLifecycle $lifecycle) {}

    public function tombstone(string $userId): void
    {
        $workspaceId = $this->workspaces->userWorkspaceId($userId);
        if ($workspaceId !== null) {
            $this->lifecycle->tombstoneWorkspace($workspaceId);
        }
        PairingCode::query()->where('user_id', $userId)->delete();
    }

    public function purge(string $userId): void
    {
        $workspaceId = $this->workspaces->userWorkspaceId($userId);
        if ($workspaceId !== null) {
            PairingCode::query()->where('workspace_id', $workspaceId)->delete();
            Agent::query()->where('workspace_id', $workspaceId)->delete();
        }
        PairingCode::query()->where('user_id', $userId)->delete();
    }

    /** @return array<string, mixed> */
    public function summary(string $userId): array
    {
        return [];
    }
}
