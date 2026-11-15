<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A project was created (event-catalogue §2). Stored uuids. Dispatched after commit. */
final readonly class ProjectCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $projectId,
        public string $workspaceId,
    ) {}
}
