<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Application\EmailVerification;
use App\Modules\Identity\Application\SignInSessions;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email verification (contracts §3.4): the `GET` shows **Confirm email**,
 * the signed `POST` verifies. Confirming in the browser that registered
 * signs the new user in and, if the sign-up started from a guest session,
 * claims it; in any other browser nothing is claimed (TM-12).
 */
final readonly class VerifyEmailController
{
    public function notice(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Auth/VerifyEmail', [
            'email' => $user instanceof User ? $user->email : null,
            'retryAt' => $request->session()->get(SessionKeys::RETRY_AT),
        ]);
    }

    public function show(Request $request, string $id, string $hash, EmailVerification $verification): Response
    {
        $user = $verification->resolve($id, $hash);

        return Answers::linkPage($request, 'Auth/VerifyEmailLink', [
            'link' => $user === null ? ['state' => 'invalid'] : ['state' => 'confirm', 'alreadyVerified' => $user->isVerified(), 'email' => $user->email],
        ]);
    }

    public function confirm(Request $request, string $id, string $hash, EmailVerification $verification, SignInSessions $sessions): RedirectResponse
    {
        $user = $verification->resolve($id, $hash);
        if ($user === null) {
            return back()->withErrors(['form' => 'link_invalid']);
        }
        $pending = $request->session()->get(SessionKeys::PENDING_REGISTRATION);
        // F1: only the registering browser keeps what the registrant set;
        // anywhere else the account is reset to a clean state.
        $discarded = $verification->verify($user, byRegistrant: $pending === $user->id);

        if ($pending === $user->id && $request->user() === null && ! $user->refresh()->hasSecondFactor() && $user->hasPassword()) {
            $sessions->complete($request, $user->refresh(), 'password', null, setsRecentAuth: false);

            // QA F-03: the intended URL, else the `/projects` landing.
            return redirect()->intended('/projects')->with('flash', Answers::flash('success', 'email_verified'));
        }
        $request->session()->forget(SessionKeys::CLAIM);
        if ($discarded && $request->user() === null) {
            return redirect('/sign-in')->with('flash', Answers::flash('success', 'email_verified_set_password'));
        }

        return ($request->user() !== null ? redirect()->intended('/projects') : redirect('/sign-in'))
            ->with('flash', Answers::flash('success', 'email_verified'));
    }

    public function resend(Request $request, EmailVerification $verification, Clock $clock): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            $pending = $request->session()->get(SessionKeys::PENDING_REGISTRATION);
            $user = is_string($pending) ? User::query()->find($pending) : null;
        }
        if ($user instanceof User && ($retryAt = $verification->resend($user)) !== null) {
            return Answers::throttled($request, $retryAt, $clock->now());
        }

        return back()->with('flash', Answers::flash('info', 'verification_sent'));
    }
}
