<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Application\PasswordResets;
use App\Modules\Identity\Application\SecondFactorChallenge;
use App\Modules\Identity\Application\SignInSessions;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot password" and `/reset-password/{token}` (contracts §3.4). The reset
 * never claims a guest session; on a 2FA account it leads to the challenge
 * and the reset link is a first factor only (AR-2, TM-T151).
 */
final readonly class PasswordResetController
{
    public function request(Request $request): Response
    {
        return Inertia::render('Auth/ForgotPassword', ['retryAt' => $request->session()->get(SessionKeys::RETRY_AT)]);
    }

    public function send(Request $request, PasswordResets $resets, Clock $clock): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'max:254']]);
        if ($retryAt = $resets->request((string) $data['email'], RequestContext::from($request))) {
            return Answers::throttled($request, $retryAt, $clock->now());
        }

        return back()->with('flash', Answers::flash('info', 'reset_link_sent'));
    }

    /**
     * The reset link proved the mailbox, so the page names the account; an
     * imported 1.x account without a password gets the "Set a password" form.
     */
    public function show(Request $request, string $token, PasswordResets $resets): Response
    {
        $user = $resets->resolve($token);

        return Answers::linkPage($request, 'Auth/ResetPassword', [
            'link' => $user === null ? ['state' => 'invalid'] : [
                'state' => 'valid',
                'token' => $token,
                'email' => $user->email,
                'legacy' => $user->legacy_source === '1x' && ! $user->hasPassword(),
            ],
            'passwordPolicy' => PasswordPolicy::fromConfig()->toProp(),
        ]);
    }

    public function reset(Request $request, PasswordResets $resets, SecondFactorChallenge $challenge, SignInSessions $sessions): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:128'],
            'password' => ['required', 'string', 'max:4096', 'confirmed'],
        ]);
        $user = $resets->reset((string) $data['token'], (string) $data['password'], RequestContext::from($request));

        // Never claims: the link may have been opened in another browser.
        $request->session()->forget(SessionKeys::CLAIM);
        if ($user->hasSecondFactor()) {
            $challenge->begin($request, $user, 'reset_link');

            return redirect('/two-factor');
        }
        $sessions->complete($request, $user, 'reset_link', null, mayClaim: false);

        return redirect('/')->with('flash', Answers::flash('success', 'password_reset'));
    }
}
