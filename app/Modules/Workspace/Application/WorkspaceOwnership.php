<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Workspace\Contracts\ProjectCaptureUrls;
use App\Modules\Workspace\Contracts\WorkspaceMoveParticipant;
use App\Modules\Workspace\Domain\Project;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Events\ProjectCreated;
use Illuminate\Support\Facades\DB;

/**
 * Workspace ownership for every principal (domain §3, `Workspace::isOwnedBy`).
 * Returns ids and keys only; models never leave the module.
 */
final readonly class WorkspaceOwnership
{
    /** @param  iterable<WorkspaceMoveParticipant>  $moveParticipants */
    public function __construct(
        private ProjectCaptureUrls $captureUrls,
        private Clock $clock,
        private iterable $moveParticipants = [],
    ) {}

    /**
     * Creates the guest workspace with its one project, "Guest session"
     * (domain §3), and its capture URL; the caller holds the transaction
     * (contracts §3.2 `POST /new`). Returns the project key.
     */
    public function createForGuest(string $guestSessionId): string
    {
        $workspace = Workspace::query()->create(['guest_session_id' => $guestSessionId]);
        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'project_key' => Project::unusedKey(),
            'name' => (string) config('workspace.guest_project_name'),
        ]);
        $this->captureUrls->provision($project->id);
        ProjectCreated::dispatch($project->id, $workspace->id);

        return $project->project_key;
    }

    /** The user's workspace id; created on first need (one per principal). */
    public function ensureForUser(string $userId): string
    {
        return Workspace::query()->firstOrCreate(['user_id' => $userId])->id;
    }

    public function projectKeyOfGuest(string $guestSessionId): ?string
    {
        $workspace = Workspace::query()->where('guest_session_id', $guestSessionId)->first();

        return $workspace?->projects()->value('project_key');
    }

    /** The owner check of every owner route (TM-13): false for anyone else and for deleted projects. */
    public function owns(?Principal $principal, string $projectKey): bool
    {
        $project = Project::query()->live()->where('project_key', $projectKey)->with('workspace')->first();

        return $project !== null && $project->workspace->isOwnedBy($principal);
    }

    /** @return array{id: string, key: string, name: string}|null */
    public function project(string $projectKey): ?array
    {
        $project = Project::query()->live()->where('project_key', $projectKey)->first();

        return $project === null ? null : ['id' => $project->id, 'key' => $project->project_key, 'name' => $project->name];
    }

    /**
     * Claim (ADR-003 amendment 1 §4 steps 3-4): an empty guest workspace is
     * discarded; otherwise its project moves into the user's workspace and the
     * guest workspace is deleted. Keys do not change; the move clears
     * `is_guest` (trigger), so the project becomes an account project.
     */
    public function absorbGuestWorkspace(string $guestSessionId, string $userId, bool $empty): void
    {
        DB::transaction(function () use ($guestSessionId, $userId, $empty): void {
            $guest = Workspace::query()->where('guest_session_id', $guestSessionId)->lockForUpdate()->first();
            if ($guest === null) {
                return;
            }
            if (! $empty) {
                $target = $this->ensureForUser($userId);
                $projectIds = array_values(array_map(strval(...), Project::query()->where('workspace_id', $guest->id)->pluck('id')->all()));
                Project::query()->where('workspace_id', $guest->id)->update(['workspace_id' => $target]);
                $this->carryCaptureCounters($projectIds);
                // Agents move with the workspace (ADR-003 amendment 1 §4, TM-42).
                foreach ($this->moveParticipants as $participant) {
                    $participant->moved($guest->id, $target);
                }
            }
            $guest->delete();
        });
    }

    /**
     * A09 S3-9: the account quota of a claimed project starts from what it
     * already stores (its captures' counted bytes) and from today's count,
     * so retention later gives back only bytes that were counted. The guest
     * session's bytes leave the global guest budget through its trigger.
     *
     * @param  list<string>  $projectIds
     */
    private function carryCaptureCounters(array $projectIds): void
    {
        $today = $this->clock->now()->utc()->toDateString();
        $usage = $this->captureUrls->storedUsage($projectIds, $today);
        foreach ($projectIds as $projectId) {
            Project::query()->whereKey($projectId)->update([
                'stored_body_bytes' => $usage[$projectId]['bytes'] ?? 0,
                'captures_today' => $usage[$projectId]['onDay'] ?? 0,
                'captures_day' => $today,
            ]);
        }
    }

    /**
     * The workspace of a browser principal (one per principal): a user's is
     * created on first need; a guest's exists from `POST /new`.
     */
    public function workspaceIdOf(?Principal $principal): ?string
    {
        return match ($principal?->kind) {
            'user' => $this->ensureForUser($principal->id),
            'guest' => $this->guestWorkspaceId($principal->id),
            default => null,
        };
    }

    public function guestWorkspaceId(string $guestSessionId): ?string
    {
        $id = Workspace::query()->where('guest_session_id', $guestSessionId)->value('id');

        return is_string($id) ? $id : null;
    }

    public function userWorkspaceId(string $userId): ?string
    {
        $id = Workspace::query()->where('user_id', $userId)->value('id');

        return is_string($id) ? $id : null;
    }

    /** Who owns a workspace, or null when it does not exist. */
    public function ownerOf(string $workspaceId): ?Principal
    {
        $workspace = Workspace::query()->find($workspaceId);
        if ($workspace === null) {
            return null;
        }

        return $workspace->user_id !== null
            ? new Principal('user', $workspace->user_id)
            : new Principal('guest', (string) $workspace->guest_session_id);
    }

    public function deleteGuestWorkspace(string $guestSessionId): void
    {
        Workspace::query()->where('guest_session_id', $guestSessionId)->delete();
    }
}
