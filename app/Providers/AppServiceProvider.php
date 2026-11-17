<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Ssr\PublicPagesSsrGateway;
use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\EngineProcess\Engine;
use App\Infrastructure\EngineProcess\SocketEngine;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Ssr\Gateway;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(Engine::class, fn (Application $app): SocketEngine => new SocketEngine(
            $app->make(Clock::class),
            (string) config('engine.socket'), (int) config('engine.timeout_ms'), (int) config('engine.connect_timeout_ms'),
        ));
        // SSR for the public pages only (replaces inertia-laravel's binding).
        $this->app->bind(Gateway::class, PublicPagesSsrGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
