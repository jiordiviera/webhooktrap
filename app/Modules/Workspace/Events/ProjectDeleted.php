<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A project was deleted by its owner (event-catalogue §2, listener:
 * Retention). It is soft-deleted at once (every page and ingest treat it as
 * unknown) and hard-deleted after 24 h. Stored uuid. Dispatched after commit.
 */
final readonly class ProjectDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $projectId,
    ) {}
}
