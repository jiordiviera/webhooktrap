<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http;

use App\Modules\Identity\Domain\SessionKeys;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Shared answers of the authentication routes (contracts §3.4). */
final class Answers
{
    /**
     * Throttled: forms return with `errors.form = 'throttled'` and `retryAt`;
     * JSON endpoints answer 429 with Retry-After.
     */
    public static function throttled(Request $request, CarbonImmutable $retryAt, CarbonImmutable $now): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(['error' => 'throttled'], 429, [
                'Retry-After' => (string) max(1, $retryAt->getTimestamp() - $now->getTimestamp()),
            ]);
        }

        return back()
            ->withErrors(['form' => 'throttled'])
            ->with(SessionKeys::RETRY_AT, $retryAt->utc()->toIso8601ZuluString('millisecond'));
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public static function flash(string $kind, string $code, string $message = '', array $extra = []): array
    {
        return ['kind' => $kind, 'code' => $code, 'message' => $message] + $extra;
    }

    /**
     * A same-origin relative path only; anything else becomes the fallback
     * (no open redirect). Refused: absolute and scheme URLs, protocol-relative
     * `//host`, any backslash (browsers read `/\\host` as `//host`), and
     * whitespace or control characters (browsers drop tab and newline, so
     * `/\t/host` would become `//host`).
     */
    public static function safeNext(?string $next, string $fallback = '/'): string
    {
        if ($next === null || $next === '' || strlen($next) > 2000
            || ! str_starts_with($next, '/') || str_starts_with($next, '//')
            || str_contains($next, '\\') || preg_match('/[\x00-\x20\x7F]/', $next) === 1) {
            return $fallback;
        }

        return $next;
    }

    /**
     * Emailed-link pages (S-32) carry the CSRF token as a prop, so their
     * confirm form is a real `POST` form that works without JavaScript.
     *
     * @param  array<string, mixed>  $props
     */
    public static function linkPage(Request $request, string $component, array $props): Response
    {
        return Inertia::render($component, $props + ['csrfToken' => $request->session()->token()]);
    }
}
