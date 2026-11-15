<?php

declare(strict_types=1);

namespace App\Modules\Capture\Jobs;

use App\Modules\Capture\Application\CaptureDerivation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Derives one capture after insert (domain §4), on `default`. Unique per
 * capture while it waits; a failed run leaves the capture stale, and the
 * hourly `captures:rederive` picks it up.
 */
final class DeriveCapture implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 300;

    public function __construct(public readonly string $captureId)
    {
        $this->onQueue('default');
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [2, 10];
    }

    public function uniqueId(): string
    {
        return $this->captureId;
    }

    public function handle(CaptureDerivation $derivation): void
    {
        $derivation->derive($this->captureId);
    }
}
