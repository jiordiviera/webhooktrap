<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Workspace\Contracts\OwnedProject;
use App\Modules\Workspace\Contracts\ProjectCaptureUrls;
use App\Modules\Workspace\Domain\Project;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Events\ProjectCreated;
use App\Modules\Workspace\Events\ProjectDeleted;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Projects of a workspace (domain §3; S-15, S-16). Every lookup by key goes
 * through `owned()`, which answers null for anyone but the live owner and for
 * deleted projects, so callers answer the same not-found (TM-13, IA5).
 * Returns read models only; models never leave the module.
 */
final readonly class Projects
{
    public function __construct(
        private Clock $clock,
        private ProjectCaptureUrls $captureUrls,
        private WorkspaceOwnership $ownership,
    ) {}

    /** The live project with this key when the principal owns it; otherwise null. */
    public function owned(?Principal $principal, string $projectKey): ?OwnedProject
    {
        if ($principal === null || preg_match('/^[A-Za-z0-9]{10}$/', $projectKey) !== 1) {
            return null;
        }
        $project = Project::query()->live()->where('project_key', $projectKey)->with('workspace')->first();
        if ($project === null || ! $project->workspace->isOwnedBy($principal)) {
            return null;
        }

        return self::view($project);
    }

    /** The live project with this stored id when the principal owns it; otherwise null (run pages). */
    public function ownedById(?Principal $principal, string $projectId): ?OwnedProject
    {
        if ($principal === null) {
            return null;
        }
        $project = Project::query()->live()->whereKey($projectId)->with('workspace')->first();
        if ($project === null || ! $project->workspace->isOwnedBy($principal)) {
            return null;
        }

        return self::view($project);
    }

    /** A live project by stored id, without an owner check: for agent-side work only (never a browser request). */
    public function byId(string $projectId): ?OwnedProject
    {
        $project = Project::query()->live()->whereKey($projectId)->first();

        return $project === null ? null : self::view($project);
    }

    /**
     * Stored id of a live project, for host-side operator commands only
     * (`captures:rederive --project=`); never for a browser request, which
     * goes through `owned()`.
     */
    public function idOfKey(string $projectKey): ?string
    {
        $id = Project::query()->live()->where('project_key', $projectKey)->value('id');

        return is_string($id) ? $id : null;
    }

    /**
     * Live projects of the principal's workspace, by name.
     *
     * @return list<OwnedProject>
     */
    public function of(?Principal $principal): array
    {
        $workspace = $this->workspaceOf($principal);
        if ($workspace === null) {
            return [];
        }

        return array_values(Project::query()->live()->where('workspace_id', $workspace->id)
            ->orderByRaw('lower(name)')->orderBy('created_at')->get()
            ->map(fn (Project $p): OwnedProject => self::view($p))->all());
    }

    /**
     * S-15 `POST /projects/new`: account workspaces only; a guest workspace
     * has exactly one project (domain §3). The capture URL is provisioned in
     * the same transaction; `ProjectCreated` follows the commit.
     */
    public function create(Principal $principal, string $name): OwnedProject
    {
        if (! $principal->isUser()) {
            throw new LogicException('Only an account creates projects.');
        }

        return DB::transaction(function () use ($principal, $name): OwnedProject {
            $workspaceId = $this->ownership->ensureForUser($principal->id);
            $project = Project::query()->create([
                'workspace_id' => $workspaceId,
                'project_key' => Project::unusedKey(),
                'name' => $name,
                'capture_retention_days' => (int) config('workspace.retention.default_capture_days'),
                'run_retention_days' => (int) config('workspace.retention.default_run_days'),
            ]);
            $this->captureUrls->provision($project->id);
            ProjectCreated::dispatch($project->id, $workspaceId);

            return self::view($project->refresh());
        });
    }

    public function rename(OwnedProject $project, string $name): void
    {
        $this->assertAccountProject($project);
        Project::query()->live()->whereKey($project->id)->update(['name' => $name, 'updated_at' => $this->clock->now()]);
    }

    public function setRetention(OwnedProject $project, int $captureDays, int $runDays): void
    {
        $this->assertAccountProject($project);
        Project::query()->live()->whereKey($project->id)->update([
            'capture_retention_days' => $captureDays,
            'run_retention_days' => $runDays,
            'updated_at' => $this->clock->now(),
        ]);
    }

    /**
     * S-16 Danger: soft delete at once (not found everywhere, ingest stores
     * nothing), hard delete after 24 h (schema §3 `deleted_at`, §8). Counters
     * and the key go with the row; neither is reused (domain §3).
     */
    public function delete(OwnedProject $project): void
    {
        $this->assertAccountProject($project);
        DB::transaction(function () use ($project): void {
            $deleted = Project::query()->live()->whereKey($project->id)
                ->update(['deleted_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
            if ($deleted === 1) {
                ProjectDeleted::dispatch($project->id);
            }
        });
    }

    /** Hourly: hard-delete projects soft-deleted more than 24 h ago (cascade). */
    public function purgeDeleted(): int
    {
        $cutoff = $this->clock->now()->subHours((int) config('workspace.deleted_project_grace_hours'));

        return Project::query()->whereNotNull('deleted_at')->where('deleted_at', '<=', $cutoff)->delete() ?? 0;
    }

    public function captureUrl(OwnedProject $project): ?string
    {
        return $this->captureUrls->activeUrl($project->id);
    }

    private function workspaceOf(?Principal $principal): ?Workspace
    {
        return match ($principal?->kind) {
            'user' => Workspace::query()->where('user_id', $principal->id)->first(),
            'guest' => Workspace::query()->where('guest_session_id', $principal->id)->first(),
            default => null,
        };
    }

    /** The guest project's name and life are fixed; "End session now" deletes it. */
    private function assertAccountProject(OwnedProject $project): void
    {
        if ($project->isGuest) {
            throw new LogicException('A guest project is not renamed, re-tuned or deleted.');
        }
    }

    private static function view(Project $project): OwnedProject
    {
        return new OwnedProject(
            id: $project->id,
            publicId: $project->publicId(),
            workspaceId: $project->workspace_id,
            key: $project->project_key,
            name: $project->name,
            isGuest: $project->is_guest,
            captureRetentionDays: $project->capture_retention_days,
            runRetentionDays: $project->run_retention_days,
            defaultVisibility: $project->default_visibility,
            createdAt: $project->created_at,
        );
    }
}
