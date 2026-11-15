<?php

declare(strict_types=1);

namespace App\Modules\Capture\Listeners;

use App\Modules\Capture\Events\CaptureReceived;
use App\Modules\Capture\Jobs\DeriveCapture;
use Throwable;

/**
 * `CaptureReceived` → the derivation job (domain §4 ingest pipeline). The
 * capture is already committed; failing to queue is reported and swallowed,
 * and the hourly re-derive catches the capture up.
 */
final readonly class QueueDerivation
{
    public function handle(CaptureReceived $event): void
    {
        try {
            DeriveCapture::dispatch($event->captureId);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
