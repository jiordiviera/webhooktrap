<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Application\SignInSessions;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin-host session (decision 74): its `user_sessions` row must stay
 * live (revocation from Account › Security ends it), and it ends after
 * `admin.idle_seconds` without a request. Ending it revokes the row; the
 * next visit goes back through the app's sign-in and confirmation.
 */
final readonly class GuardOperatorSession
{
    public const string LAST_SEEN = 'admin.last_seen';

    public function __construct(private SignInSessions $sessions, private Clock $clock) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            $now = $this->clock->now()->getTimestamp();
            $last = $request->session()->get(self::LAST_SEEN);
            $idle = is_int($last) && $now - $last > (int) config('admin.idle_seconds');
            if ($idle || ! $this->sessions->browserSessionIsLive($request)) {
                $this->sessions->signOut($request);

                return $request->hasHeader('X-Livewire')
                    ? new Response('', 419)
                    : redirect(Filament::getLoginUrl() ?? '/login');
            }
            $request->session()->put(self::LAST_SEEN, $now);
        }

        return $next($request);
    }
}
