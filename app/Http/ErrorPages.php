<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * S-18 system pages (F-06): 403, 404, 419, 429, 500 and 503 answer the
 * branded `Error` page through Inertia, for a first visit and for an
 * Inertia visit alike.
 *
 * Left untouched:
 * - JSON answers (`api/*` and requests that expect JSON);
 * - `/c/…` ingest (contracts §2): ServeCaptureHost answers it before the
 *   router and never throws, this guard only keeps it that way;
 * - 500 and 503 while debugging, so the stack trace stays visible.
 *
 * The page carries the status only, so the uniform not-found (IA5) stays
 * the same for someone else's, a deleted and an unknown resource.
 */
final class ErrorPages
{
    /** @var list<int> */
    private const array STATUSES = [403, 404, 419, 429, 500, 503];

    /** Headers of the original answer that the page keeps. */
    private const array KEPT_HEADERS = ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'];

    public static function render(Response $response, Request $request): Response
    {
        $status = $response->getStatusCode();

        // An Inertia visit always wants a page, whatever its Accept header.
        $inertia = $request->hasHeader('X-Inertia');

        if (! in_array($status, self::STATUSES, true)
            || $request->is('api/*', 'c', 'c/*')
            || (! $inertia && ($response instanceof JsonResponse || $request->expectsJson()))
            || ($status >= 500 && (bool) config('app.debug'))) {
            return $response;
        }

        // A URL that matched no route skipped HandleAppearance: read the
        // same plain cookie so the first paint follows the person's theme.
        $appearance = $request->cookie('appearance');
        View::share('appearance', in_array($appearance, ['light', 'dark', 'system'], true) ? $appearance : 'dark');

        $page = Inertia::render('Error', ['status' => $status])->toResponse($request);
        $page->setStatusCode($status);

        foreach (self::KEPT_HEADERS as $header) {
            if ($response->headers->has($header)) {
                $page->headers->set($header, $response->headers->get($header));
            }
        }

        return $page;
    }
}
