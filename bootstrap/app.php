<?php

declare(strict_types=1);

use App\Http\ErrorPages;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Identity\Http\Middleware\EnsureEmailIsVerified;
use App\Modules\Identity\Http\Middleware\EnsureUserSessionIsLive;
use App\Modules\Identity\Http\Middleware\RequireRecentAuthentication;
use App\Modules\Identity\Http\Middleware\RequireSameOriginFetch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as BasePreventRequestForgery;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The guest cookie carries a random token stored hashed; it is read raw.
        $middleware->encryptCookies(except: ['appearance', 'layout', 'layout_sidebar', 'sidebar_state', '__Host-wt_guest']);

        $middleware->redirectGuestsTo('/sign-in');
        $middleware->redirectUsersTo('/');
        $middleware->alias([
            'recent-auth' => RequireRecentAuthentication::class,
            'verified-email' => EnsureEmailIsVerified::class,
            'same-origin-fetch' => RequireSameOriginFetch::class,
        ]);
        // `__Host-XSRF-TOKEN` instead of `XSRF-TOKEN` (TM-T4, A09 F4).
        $middleware->web(replace: [BasePreventRequestForgery::class => PreventRequestForgery::class]);

        $middleware->web(append: [
            EnsureUserSessionIsLive::class,
            SecurityHeaders::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // S-18 branded system pages for HTML and Inertia requests (F-06).
        $exceptions->respond(
            fn (Response $response, Throwable $e, Request $request): Response => ErrorPages::render($response, $request),
        );
    })->create();
