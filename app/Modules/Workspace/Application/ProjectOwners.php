<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Modules\Workspace\Contracts\ProjectOwner;
use App\Modules\Workspace\Domain\Project;
use App\Modules\Workspace\Domain\Workspace;
use Illuminate\Database\Eloquent\Builder;

/**
 * Project ownership facts for other modules that act without a browser
 * principal (Capture ingest, its cache eviction and its purges). Returns read
 * models and ids only; models never leave the module.
 */
final readonly class ProjectOwners
{
    /** The owner of a live (not soft-deleted) project; null otherwise. */
    public function ownerOf(string $projectId): ?ProjectOwner
    {
        $project = Project::query()->live()->whereKey($projectId)->with('workspace')->first();
        if ($project === null) {
            return null;
        }
        $workspace = $project->workspace;

        return $workspace->user_id !== null
            ? new ProjectOwner($project->id, 'user', $workspace->user_id, $project->is_guest)
            : new ProjectOwner($project->id, 'guest', (string) $workspace->guest_session_id, $project->is_guest);
    }

    /**
     * Every project of the user's workspace, soft-deleted ones included.
     *
     * @return list<string>
     */
    public function projectIdsOfUser(string $userId): array
    {
        return $this->idsOf(Workspace::query()->select('id')->where('user_id', $userId));
    }

    /** @return list<string> */
    public function projectIdsOfGuest(string $guestSessionId): array
    {
        return $this->idsOf(Workspace::query()->select('id')->where('guest_session_id', $guestSessionId));
    }

    /**
     * Capture retention per live account project (guest data lives and dies
     * with its session, domain §12).
     *
     * @return array<string, int> project id => capture retention days
     */
    public function captureRetention(): array
    {
        /** @var array<string, int> */
        return Project::query()->live()->where('is_guest', false)
            ->pluck('capture_retention_days', 'id')->map(fn (mixed $days): int => (int) $days)->all();
    }

    /**
     * @param  Builder<Workspace>  $workspaces
     * @return list<string>
     */
    private function idsOf(Builder $workspaces): array
    {
        /** @var list<string> */
        return array_values(Project::query()->whereIn('workspace_id', $workspaces)->pluck('id')->all());
    }
}
