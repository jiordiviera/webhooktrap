<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Modules\Identity\Contracts\BrowserChannel;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Workspace\Domain\Project;

/**
 * `private-project.{projectId}` (event-catalogue §1): the user or guest that
 * owns the live project's workspace, the same check as every owner route
 * (TM-13). A deleted project, another principal and a tombstoned owner are
 * refused alike.
 */
final readonly class ProjectChannel implements BrowserChannel
{
    public function name(): string
    {
        return 'project';
    }

    public function allows(Principal $principal, string $id): bool
    {
        $project = Project::query()->live()->whereKey($id)->with('workspace')->first();

        return $project !== null && $project->workspace->isOwnedBy($principal);
    }
}
