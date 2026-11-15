<?php

declare(strict_types=1);

namespace App\Modules\Capture\Http\Middleware;

use App\Modules\Capture\Application\Ingest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Public ingest (contracts §2; ADR-005 amendment 1 §4, §8), first in the
 * global middleware stack, so it runs before sessions, cookies, CSRF, CORS
 * and the router:
 *
 * - every method token reaches ingest (the router only matches a fixed verb
 *   list), and the method is the one received, never an override;
 * - every `/c/{captureKey}` and `/c/{captureKey}/{rest…}` request gets the
 *   same status, headers and body, whatever happened to it; only the
 *   per-address `429` and the `413` above the hard limit differ, and neither
 *   depends on the key;
 * - with CAPTURE_HOST set, the capture host serves `/c/…` only (HD-7) and
 *   `/c/…` is not served on the app host.
 */
final readonly class ServeCaptureHost
{
    public function __construct(private Ingest $ingest) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $rawTarget = (string) $request->server->get('REQUEST_URI', '/');
        $path = explode('?', $rawTarget, 2)[0];
        $isIngest = preg_match('#^/c/([^/]*)(?:/|$)#', $path, $match) === 1;

        $captureHost = config('capture.host');
        if (is_string($captureHost) && $captureHost !== '') {
            if (! self::isHost($request, $captureHost)) {
                return $next($request);
            }
            if (! $isIngest) {
                return new Response('', 404, ['Cache-Control' => 'no-store', 'Content-Type' => 'text/plain; charset=utf-8']);
            }
        } elseif (! $isIngest) {
            return $next($request);
        }

        try {
            $outcome = $this->ingest->handle($request, $match[1]);
        } catch (Throwable $e) {
            report($e);
            $outcome = Ingest::DROPPED;
        }

        return match ($outcome) {
            Ingest::RATE_LIMITED => self::answer($request, 429, '{"error":"rate_limited"}', ['Retry-After' => '60']),
            Ingest::TOO_LARGE => self::answer($request, 413, '{"error":"too_large"}'),
            default => self::received($request),
        };
    }

    /** The one answer of ADR-005 amendment 1 §4 (`HEAD`: same status and headers, no body). */
    public static function received(Request $request): Response
    {
        return self::answer($request, 200, '{"received":true}');
    }

    /** @param  array<string, string>  $extra */
    private static function answer(Request $request, int $status, string $body, array $extra = []): Response
    {
        $isHead = strtoupper((string) $request->server->get('REQUEST_METHOD', 'GET')) === 'HEAD';

        return new Response($isHead ? '' : $body, $status, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ] + $extra);
    }

    private static function isHost(Request $request, string $host): bool
    {
        $sent = strtolower((string) preg_replace('/:\d+$/', '', (string) $request->server->get('HTTP_HOST', '')));

        return $sent === strtolower($host);
    }
}
