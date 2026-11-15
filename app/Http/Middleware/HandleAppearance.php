<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-device display preferences, read from plain cookies so the first
 * paint is right (no flash): `appearance` for the blade template, and the
 * app layout (decision 59) for Inertia's shared `layout` prop.
 */
class HandleAppearance
{
    public const array SHELLS = ['topbar', 'sidebar'];

    public const array SIDEBAR_STATES = ['expanded', 'collapsed'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'dark');

        $shell = $request->cookie('layout');
        $sidebar = $request->cookie('layout_sidebar');
        $request->attributes->set('layout', [
            'shell' => in_array($shell, self::SHELLS, true) ? $shell : 'topbar',
            'sidebar' => in_array($sidebar, self::SIDEBAR_STATES, true) ? $sidebar : 'expanded',
        ]);

        return $next($request);
    }
}
