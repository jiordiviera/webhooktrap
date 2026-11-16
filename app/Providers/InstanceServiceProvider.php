<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Instance\Console\HeartbeatCheck;
use App\Modules\Instance\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Instance wiring: `/health` (outside the `web` group) and the scheduler's
 * Compose healthcheck `app:heartbeat-check`. The heartbeat itself is
 * scheduled in routes/console.php.
 */
final class InstanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([HeartbeatCheck::class]);
        }

        if (! $this->app->routesAreCached()) {
            Route::get('/health', HealthController::class)->name('health');
        }
    }
}
