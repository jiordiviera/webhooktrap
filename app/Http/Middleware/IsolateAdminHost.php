<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * First in the `web` group (decision 74). The admin panel's own routes are
 * bound to ADMIN_HOST and never run this group, except Livewire's update
 * endpoint, which Livewire always puts in it. So:
 *
 * - on the admin host, every app route answers 404: no app sign-in, no
 *   Inertia page, no account route is served there;
 * - Livewire's update endpoint is served on the admin host only (the app
 *   itself has no Livewire component): elsewhere it, and every other
 *   Livewire or Filament support route (uploads, export downloads), answers
 *   404.
 */
final class IsolateAdminHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = config('admin.host');
        $onAdmin = is_string($admin) && $admin !== '' && strcasecmp($request->getHost(), $admin) === 0;
        $route = $request->route();
        $update = $route instanceof Route && $route->named('*livewire.update');
        $panelSupport = $route instanceof Route && $route->named(['*livewire.*', 'filament.*']);

        if ($onAdmin ? ! $update : $panelSupport) {
            abort(404);
        }

        return $next($request);
    }
}
