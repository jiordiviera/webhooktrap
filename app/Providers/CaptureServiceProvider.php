<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Capture\Application\CaptureAccountDeletion;
use App\Modules\Capture\Application\CaptureUrls;
use App\Modules\Capture\Console\RederiveCaptures;
use App\Modules\Capture\Events\CaptureDerived;
use App\Modules\Capture\Events\CaptureReceived;
use App\Modules\Capture\Events\CaptureUrlRotated;
use App\Modules\Capture\Http\Middleware\ServeCaptureHost;
use App\Modules\Capture\Listeners\BroadcastCaptureChanges;
use App\Modules\Capture\Listeners\EvictCaptureKeys;
use App\Modules\Capture\Listeners\QueueDerivation;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Identity\Events\GuestSessionClaimed;
use App\Modules\Identity\Events\GuestSessionEnded;
use App\Modules\Workspace\Contracts\ProjectCaptureUrls;
use App\Modules\Workspace\Events\ProjectDeleted;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Capture wiring (slices 3 and 4): the real `ProjectCaptureUrls` for
 * Workspace, Capture's part of account deletion, cache eviction listeners
 * (DL-2), the Live Prop triggers for `CaptureReceived` and
 * `CaptureUrlRotated`, the ingest middleware (first in the global stack,
 * contracts §2) and the owner routes in routes/capture.php. Packs slice:
 * `CaptureReceived` queues the derivation job, `CaptureDerived` sends the
 * stream's live trigger again, and `captures:rederive` re-derives after a
 * pack update.
 */
final class CaptureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProjectCaptureUrls::class, CaptureUrls::class);
        $this->app->tag([CaptureAccountDeletion::class], 'identity.deletion-participants');
    }

    public function boot(): void
    {
        Event::listen(AccountDeleted::class, [EvictCaptureKeys::class, 'accountDeleted']);
        Event::listen(ProjectDeleted::class, [EvictCaptureKeys::class, 'projectDeleted']);
        Event::listen(GuestSessionClaimed::class, [EvictCaptureKeys::class, 'guestClaimed']);
        Event::listen(GuestSessionEnded::class, [EvictCaptureKeys::class, 'guestEnded']);
        Event::listen(CaptureReceived::class, [BroadcastCaptureChanges::class, 'received']);
        Event::listen(CaptureReceived::class, [QueueDerivation::class, 'handle']);
        Event::listen(CaptureDerived::class, [BroadcastCaptureChanges::class, 'derived']);
        Event::listen(CaptureUrlRotated::class, [BroadcastCaptureChanges::class, 'rotated']);

        $kernel = $this->app->make(Kernel::class);
        if (! $kernel->hasMiddleware(ServeCaptureHost::class)) {
            $kernel->prependMiddleware(ServeCaptureHost::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([RederiveCaptures::class]);
        }

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('routes/capture.php'));
        }
    }
}
