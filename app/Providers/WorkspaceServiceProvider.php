<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Capture\Events\CaptureReceived;
use App\Modules\Identity\Events\GuestSessionClaimed;
use App\Modules\Proving\Events\RunFinished;
use App\Modules\Workspace\Application\ProjectChannel;
use App\Modules\Workspace\Application\WorkspaceAccountDeletion;
use App\Modules\Workspace\Application\WorkspaceChannel;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use App\Modules\Workspace\Events\ProjectCreated;
use App\Modules\Workspace\Listeners\ActivationListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Workspace wiring: Workspace's part of account deletion. The capture URL
 * seam (`ProjectCaptureUrls`) is bound by CaptureServiceProvider.
 */
final class WorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([WorkspaceAccountDeletion::class], 'identity.deletion-participants');
        $this->app->tag([ProjectChannel::class, WorkspaceChannel::class], 'identity.browser-channels');
        // Modules whose rows follow a claimed guest workspace (Agent).
        $this->app->when(WorkspaceOwnership::class)
            ->needs('$moveParticipants')->giveTagged('workspace.move-participants');
    }

    public function boot(): void
    {
        // Activation funnel (decision 79): first-party, aggregate counts only.
        Event::listen(ProjectCreated::class, [ActivationListener::class, 'projectCreated']);
        Event::listen(CaptureReceived::class, [ActivationListener::class, 'captureReceived']);
        Event::listen(RunFinished::class, [ActivationListener::class, 'runFinished']);
        Event::listen(GuestSessionClaimed::class, [ActivationListener::class, 'claimed']);
    }
}
