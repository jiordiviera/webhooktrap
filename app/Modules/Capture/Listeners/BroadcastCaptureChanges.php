<?php

declare(strict_types=1);

namespace App\Modules\Capture\Listeners;

use App\Infrastructure\Broadcasting\LiveUpdates;
use App\Modules\Capture\Events\CaptureDerived;
use App\Modules\Capture\Events\CaptureReceived;
use App\Modules\Capture\Events\CaptureUrlRotated;
use App\Modules\Capture\Http\Controllers\CaptureStreamController;
use Throwable;

/**
 * Live Prop triggers for the capture stream (event-catalogue §2, §3.1):
 * `CaptureReceived` and `CaptureDerived` → `project.captures` (the row
 * appears, then shows its provider and group), `CaptureUrlRotated` →
 * `project.capture_url`, on `private-project.{projectId}`. Both events are
 * raised after their transaction commits, and the trigger is queued after
 * commit on `broadcasts`; nothing here can fail a capture (ADR-004).
 *
 * The payload is `{"v":1}`: no seq, no ids, nothing of the request
 * (rule 3). The client reloads the props that listen for the event.
 */
final readonly class BroadcastCaptureChanges
{
    public function __construct(private LiveUpdates $live) {}

    public function received(CaptureReceived $event): void
    {
        $this->trigger($event->projectId, CaptureStreamController::EVENT_CAPTURES);
    }

    public function derived(CaptureDerived $event): void
    {
        $this->trigger($event->projectId, CaptureStreamController::EVENT_CAPTURES);
    }

    public function rotated(CaptureUrlRotated $event): void
    {
        $this->trigger($event->projectId, CaptureStreamController::EVENT_CAPTURE_URL);
    }

    private function trigger(string $projectId, string $event): void
    {
        try {
            $this->live->trigger('project.'.$projectId, $event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
