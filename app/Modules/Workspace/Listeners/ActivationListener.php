<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Listeners;

use App\Modules\Capture\Events\CaptureReceived;
use App\Modules\Identity\Events\GuestSessionClaimed;
use App\Modules\Proving\Events\RunFinished;
use App\Modules\Workspace\Application\Activation;
use App\Modules\Workspace\Events\ProjectCreated;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Feeds the activation funnel (decision 79) from public events. Counting
 * never gets in the way of the product: it runs after commit, and a failure
 * is reported, not thrown.
 */
final readonly class ActivationListener
{
    public function __construct(private Activation $activation) {}

    public function projectCreated(ProjectCreated $event): void
    {
        $this->safely(fn () => $this->activation->projectCreated($event->projectId));
    }

    public function captureReceived(CaptureReceived $event): void
    {
        $this->safely(fn () => $this->activation->captureReceived($event->projectId));
    }

    public function runFinished(RunFinished $event): void
    {
        $this->safely(fn () => $this->activation->runFinished($event->projectId, $event->status));
    }

    public function claimed(GuestSessionClaimed $event): void
    {
        $this->safely(fn () => $this->activation->claimed($event->empty));
    }

    /**
     * After the surrounding transaction commits (now, outside one): a failed
     * count inside the claim transaction would otherwise abort the claim.
     */
    private function safely(callable $count): void
    {
        DB::afterCommit(function () use ($count): void {
            try {
                $count();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
