<?php

declare(strict_types=1);

namespace App\Modules\Capture\Jobs;

use App\Modules\Capture\Application\CaptureRetention;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Daily (domain §12): account captures older than the project's capture retention. */
final class PurgeExpiredCaptures implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(CaptureRetention $retention): void
    {
        $retention->purgeExpired();
    }
}
