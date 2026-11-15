<?php

declare(strict_types=1);

namespace App\Modules\Proving\Jobs;

use App\Modules\Proving\Application\RunJobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Every minute (domain §6): runs whose job expired end Aborted `agent_lost`. */
final class ExpireRunJobs implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(RunJobs $jobs): void
    {
        $jobs->expire();
    }
}
