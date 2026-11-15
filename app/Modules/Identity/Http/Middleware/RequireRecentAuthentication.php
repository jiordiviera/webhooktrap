<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Application\RecentAuthentication;
use App\Modules\Identity\Http\ConfirmReasons;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes that need an authentication within the last 10 minutes (domain
 * §2.1). Without it, the browser goes to `/confirm-auth` and comes back.
 * Use as `recent-auth` or `recent-auth:<action>`.
 */
final readonly class RequireRecentAuthentication
{
    public function __construct(private RecentAuthentication $recent) {}

    public function handle(Request $request, Closure $next, string $action = 'confirm'): Response
    {
        if ($this->recent->isRecent($request)) {
            return $next($request);
        }
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['error' => 'recent_auth_required'], 403);
        }
        $back = $request->isMethod('GET') ? $request->getRequestUri() : '/account/security';

        $query = ['next' => $back, 'action' => $action];
        if ($reason = ConfirmReasons::forRequest($request)) {
            $query['reason'] = $reason;
        }

        return redirect('/confirm-auth?'.http_build_query($query));
    }
}
