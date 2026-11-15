<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Jobs;

use App\Modules\Workspace\Application\Projects;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Hourly (schema §8): hard-delete projects soft-deleted more than 24 h ago. */
final class PurgeDeletedProjects implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(Projects $projects): void
    {
        $projects->purgeDeleted();
    }
}
