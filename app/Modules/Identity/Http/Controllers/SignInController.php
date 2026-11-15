<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Identity\Application\PasswordSignIn;
use App\Modules\Identity\Application\SecurityViews;
use App\Modules\Identity\Application\SignInSessions;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\SignInOutcome;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SignInController
{
    public function __construct(private Clock $clock) {}

    /** `GET /sign-in`: a guest starting here gets a claim nonce (TM-12). */
    public function show(Request $request, GuestSessions $guests, SecurityViews $views): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/projects');
        }
        $guests->beginClaim($request);

        return Inertia::render('Auth/SignIn', [
            'next' => Answers::safeNext($request->query('next') === null ? null : (string) $request->query('next'), ''),
            'passkeysAvailable' => $views->passkeysAvailable(),
            'retryAt' => $request->session()->get(SessionKeys::RETRY_AT),
            // `oauth_cancelled` / `oauth_failed`: which provider, for the wording.
            'oauthProvider' => $request->session()->get(SessionKeys::OAUTH_PROVIDER),
        ]);
    }

    /**
     * `POST /sign-in/password`. A safe `next` (same-origin relative path,
     * `Answers::safeNext`) becomes the intended URL, so the person lands
     * there after the sign-in, or after the two-step challenge.
     */
    public function password(Request $request, PasswordSignIn $signIn): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:4096'],
            'next' => ['nullable', 'string', 'max:2000'],
        ]);
        $outcome = $signIn->attempt($request, (string) $data['email'], (string) $data['password']);

        $next = Answers::safeNext(isset($data['next']) ? (string) $data['next'] : null, '');
        if ($next !== '' && in_array($outcome->status, [SignInOutcome::SIGNED_IN, SignInOutcome::CHALLENGE], true)) {
            $request->session()->put(SessionKeys::INTENDED, $next);
        }

        return match ($outcome->status) {
            SignInOutcome::THROTTLED => Answers::throttled($request, $outcome->retryAt ?? $this->clock->now(), $this->clock->now()),
            SignInOutcome::FAILED => back()->withErrors(['form' => 'invalid_credentials'])->onlyInput('email'),
            SignInOutcome::CHALLENGE => redirect('/two-factor'),
            SignInOutcome::VERIFY_EMAIL => redirect('/verify-email')->with('flash', Answers::flash('info', 'verification_required')),
            default => self::afterSignIn($outcome->user),
        };
    }

    /** `POST /sign-out`; `continue: true` keeps the intended URL ("Sign out and continue"). */
    public function signOut(Request $request, SignInSessions $sessions): RedirectResponse
    {
        $sessions->signOut($request, $request->boolean('continue'));

        return $request->boolean('continue') ? redirect('/sign-in') : redirect('/');
    }

    public static function afterSignIn(?User $user): RedirectResponse
    {
        if ($user !== null && ! $user->isVerified()) {
            return redirect('/verify-email');
        }

        // QA F-03: a safe `next` (the intended URL) wins; otherwise the
        // `/projects` landing (the one project, the list, or New project).
        return redirect()->intended('/projects');
    }
}
