<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\OperatorHandoff;
use App\Modules\Identity\Application\RecentAuthentication;
use App\Modules\Identity\Domain\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The two ends of the app → admin handoff (decision 74).
 *
 * `GET /operator/handoff` on the app host (signed in, verified, recent
 * authentication with a second factor): mints a 60 s single-use token and
 * sends the browser to the admin host.
 *
 * `GET /handoff/{token}` on the admin host: spends the token and starts the
 * admin host's own session (its `__Host-` cookie is host-only).
 */
final readonly class OperatorHandoffController
{
    public function issue(Request $request, OperatorHandoff $handoff, RecentAuthentication $recent): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isEligibleOperator(), 403);

        // The panel needs a fresh confirmation with a second factor: a
        // password-only confirmation is not enough (action `operator`).
        if (! $recent->isRecent($request, requireSecondFactor: true)) {
            return redirect('/confirm-auth?'.http_build_query(['next' => '/operator/handoff', 'action' => 'operator']));
        }

        $token = $handoff->issue($request, $user);
        abort_if($token === null, 403);

        return self::noReferrer(redirect()->away(self::adminOrigin().'/handoff/'.$token));
    }

    public function redeem(Request $request, string $token, OperatorHandoff $handoff): RedirectResponse
    {
        $user = $handoff->redeem($request, $token);
        abort_if($user === null, 403);

        return self::noReferrer(redirect('/'));
    }

    public static function adminOrigin(): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        return (is_string($scheme) ? $scheme : 'https').'://'.config('admin.host');
    }

    private static function noReferrer(RedirectResponse $response): RedirectResponse
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
