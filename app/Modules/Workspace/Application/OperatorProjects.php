<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\EntityId;
use App\Modules\Workspace\Domain\Project;
use App\Modules\Workspace\Domain\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Read model of an account's projects for the operator panel (decision 74):
 * names, quotas and usage. Arrays only; never capture keys, URLs or bodies.
 */
final readonly class OperatorProjects
{
    public function __construct(private Clock $clock) {}

    /** @return list<array<string, mixed>> */
    public function forUser(string $userId): array
    {
        $today = $this->clock->now()->utc()->toDateString();
        $perDay = (int) config('workspace.quota.captures_per_day');
        $bytes = (int) config('workspace.quota.stored_body_bytes');

        return array_values(DB::table('projects')
            ->join('workspaces', 'workspaces.id', '=', 'projects.workspace_id')
            ->where('workspaces.user_id', $userId)
            ->orderByDesc('projects.created_at')
            ->get([
                'projects.id', 'projects.workspace_id', 'projects.name', 'projects.created_at', 'projects.deleted_at',
                'projects.captures_today', 'projects.captures_day', 'projects.stored_body_bytes',
                'projects.capture_retention_days', 'projects.run_retention_days', 'projects.default_visibility',
                'projects.next_capture_seq', 'projects.next_run_number',
            ])
            ->map(fn (object $p): array => [
                'id' => EntityId::encode(Project::ID_PREFIX, (string) $p->id),
                'workspace' => EntityId::encode(Workspace::ID_PREFIX, (string) $p->workspace_id),
                'name' => (string) $p->name,
                'created_at' => CarbonImmutable::parse((string) $p->created_at)->utc(),
                'deleted' => $p->deleted_at !== null,
                'captures_today' => $p->captures_day === $today ? (int) $p->captures_today : 0,
                'captures_per_day' => $perDay,
                'stored_body_bytes' => (int) $p->stored_body_bytes,
                'stored_body_bytes_quota' => $bytes,
                'captures_total' => max(0, (int) $p->next_capture_seq - 1),
                'runs_total' => max(0, (int) $p->next_run_number - 1),
                'capture_retention_days' => (int) $p->capture_retention_days,
                'run_retention_days' => (int) $p->run_retention_days,
                'visibility' => (string) $p->default_visibility,
            ])->all());
    }
}
