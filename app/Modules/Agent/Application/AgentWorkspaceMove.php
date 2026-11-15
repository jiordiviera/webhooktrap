<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Agent\Domain\Agent;
use App\Modules\Workspace\Contracts\WorkspaceMoveParticipant;
use Illuminate\Support\Facades\DB;

/**
 * Claim (ADR-003 amendment 1 §4, TM-42): a guest workspace's agents follow
 * its project into the account workspace, marked `needs_reconfirm` with
 * their target approvals cleared; they get no job until the local
 * confirmation, which also clears the guest flag.
 */
final readonly class AgentWorkspaceMove implements WorkspaceMoveParticipant
{
    public function __construct(private Clock $clock) {}

    public function moved(string $fromWorkspaceId, string $toWorkspaceId): void
    {
        $ids = Agent::query()->live()->where('workspace_id', $fromWorkspaceId)->pluck('id')->all();
        DB::table('agent_targets')->whereIn('agent_id', $ids)->delete();
        Agent::query()->whereIn('id', $ids)->update([
            'workspace_id' => $toWorkspaceId, 'needs_reconfirm' => true, 'updated_at' => $this->clock->now(),
        ]);
    }
}
