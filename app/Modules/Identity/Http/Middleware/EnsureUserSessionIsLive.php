<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\SignInSessions;
use App\Modules\Identity\Domain\SessionKeys;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every authenticated request checks that its `user_sessions` row is live
 * (domain §2.7); a revoked or expired row ends the Laravel session here.
 */
final readonly class EnsureUserSessionIsLive
{
    public function __construct(private SignInSessions $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            $id = $request->session()->get(SessionKeys::USER_SESSION);
            if (! is_string($id) || ! $this->sessions->isLive($id)) {
                $this->sessions->endBrowserSession($request);
                if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                    return response()->json(['error' => 'unauthenticated'], 401);
                }

                return redirect('/sign-in');
            }
            $this->sessions->touch($id);
        }

        return $next($request);
    }
}
