<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Admin\Http\Controllers\AdminEntryController;
use App\Modules\Admin\Http\Controllers\AdminSignOut;
use App\Modules\Admin\Http\Middleware\AdminResponseHeaders;
use App\Modules\Admin\Http\Middleware\AuditOperatorPageView;
use App\Modules\Admin\Http\Middleware\GuardOperatorSession;
use App\Modules\Admin\Pages\AccountDetail;
use App\Modules\Admin\Pages\Accounts;
use App\Modules\Admin\Pages\Agents;
use App\Modules\Admin\Pages\Dashboard;
use App\Modules\Admin\Pages\Runners;
use App\Modules\Admin\Support\InitialsAvatar;
use App\Modules\Identity\Http\Controllers\OperatorHandoffController;
use Filament\Auth\Http\Controllers\LogoutController;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

/**
 * The operator admin panel (decision 74): Filament v5, read-only, on
 * ADMIN_HOST only. Without ADMIN_HOST nothing is registered: no panel route
 * exists on any host. This provider is the composition root of the panel,
 * so it may wire Identity's handoff controller like bootstrap/app.php wires
 * Identity's middleware; the pages themselves (app/Modules/Admin) only use
 * other modules' public Application layers.
 */
final class AdminPanelProvider extends PanelProvider
{
    public function register(): void
    {
        if (! self::enabled()) {
            return;
        }
        parent::register();

        // Filament's sign-out only forgets the guard (see AdminSignOut).
        $this->app->bind(LogoutController::class, AdminSignOut::class);
    }

    public function boot(): void
    {
        if (! self::enabled()) {
            return;
        }
        // Livewire keeps per-request state in singletons, among them the route
        // whose persistent middleware (Filament's Authenticate) it last applied,
        // and skips re-applying for the same route. Livewire never resets that
        // itself, so a reused application (worker mode, the test client) would
        // let a later update skip the access check. Reset it after every request.
        $this->app->terminating(fn () => Livewire::flushState());
        // Before the app's own `/robots.txt` (loaded once every provider has booted).
        Route::domain((string) config('admin.host'))
            ->get('/robots.txt', [AdminEntryController::class, 'robots'])
            ->name('admin.robots');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->domain((string) config('admin.host'))
            ->authGuard('web')
            ->login([AdminEntryController::class, 'login'])
            ->brandName('Webhook Trap operators')
            ->colors(['primary' => Color::Amber])
            ->defaultAvatarProvider(InitialsAvatar::class)
            ->globalSearch(false)
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): HtmlString => new HtmlString('<meta name="robots" content="noindex, nofollow, noarchive">'))
            ->pages([Dashboard::class, Accounts::class, AccountDetail::class, Agents::class, Runners::class])
            ->resources([])
            ->widgets([])
            ->routes(fn () => Route::get('/handoff/{token}', [OperatorHandoffController::class, 'redeem'])->name('handoff'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                SecurityHeaders::class,
                AdminResponseHeaders::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([GuardOperatorSession::class], isPersistent: true)
            ->authMiddleware([Authenticate::class])
            ->authMiddleware([AuditOperatorPageView::class]);
    }

    public static function enabled(): bool
    {
        $host = config('admin.host');

        return is_string($host) && $host !== '';
    }
}
