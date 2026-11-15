<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Application\GuestSessionChannel;
use App\Modules\Identity\Application\IdentityBootChecks;
use App\Modules\Identity\Application\SecurityViews;
use App\Modules\Identity\Console\ImportLegacyAccounts;
use App\Modules\Identity\Domain\GuestGuard;
use App\Modules\Identity\Domain\LiveUserProvider;
use App\Modules\Identity\Events\GuestSessionClaimed;
use App\Modules\Identity\Events\GuestSessionEnded;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Http\Controllers\BroadcastAuthController;
use App\Modules\Workspace\Listeners\WorkspaceIdentityListener;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Identity wiring: the `web` provider that skips tombstoned accounts, the
 * `guest` guard, cross-module listeners and the host console commands.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Other modules' parts of account deletion (domain §2.10, F13).
        $this->app->when([AccountDeletion::class, SecurityViews::class])
            ->needs('$participants')->giveTagged('identity.deletion-participants');
        // Private browser channel families (event-catalogue §1); each owning module tags its own.
        $this->app->tag([GuestSessionChannel::class], 'identity.browser-channels');
        $this->app->when(BroadcastAuthController::class)
            ->needs('$channels')->giveTagged('identity.browser-channels');
    }

    public function boot(): void
    {
        // argon2id only; the app refuses to boot without it (domain §2.1).
        if (! defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException('PHP was built without argon2id support (PASSWORD_ARGON2ID).');
        }
        // Argon2id cost never below the OWASP floor (A09 F10). Tests run with
        // a cheaper cost on purpose; the floor itself is unit tested.
        if (! $this->app->environment('testing') && ($problems = IdentityBootChecks::hashingProblems()) !== []) {
            throw new RuntimeException('Refusing to boot: '.implode('; ', $problems).'.');
        }
        // Dedicated keys and the common-password list (A09 F6, F8, F9): the
        // app does not serve requests without them. Console commands (image
        // build, migrations) still run; each use of a missing key throws.
        // The ingest runtime (WT_ROLE=ingest) never reads them and is skipped.
        if (! $this->app->runningInConsole() && IdentityBootChecks::servesIdentity()
            && ($problems = IdentityBootChecks::serviceProblems()) !== []) {
            throw new RuntimeException('Refusing to serve: '.implode('; ', $problems).'.');
        }

        Auth::provider('live-eloquent', fn (Application $app, array $config) => new LiveUserProvider($app->make(Hasher::class), (string) $config['model']));
        Auth::extend('guest-cookie', function (Application $app): GuestGuard {
            $guard = new GuestGuard($app->make(Request::class), $app->make(Clock::class));
            $app->refresh('request', $guard, 'setRequest');

            return $guard;
        });
        $this->app->bind('identity.guest-guard', fn (Application $app): mixed => $app->make('auth')->guard('guest'));

        Event::listen(UserRegistered::class, [WorkspaceIdentityListener::class, 'registered']);
        Event::listen(GuestSessionClaimed::class, [WorkspaceIdentityListener::class, 'claimed']);
        Event::listen(GuestSessionEnded::class, [WorkspaceIdentityListener::class, 'ended']);

        if ($this->app->runningInConsole()) {
            $this->commands([ImportLegacyAccounts::class]);
        }
    }
}
