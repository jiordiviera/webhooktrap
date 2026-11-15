<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\EmailMask;
use App\Modules\Identity\Application\SecondFactorChallenge;
use App\Modules\Identity\Application\SignInSessions;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\SignInOutcome;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class TwoFactorChallengeController
{
    public function __construct(private SecondFactorChallenge $challenge, private Clock $clock) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $this->challenge->pendingUser($request);
        if ($user === null) {
            return redirect('/sign-in');
        }

        return Inertia::render('TwoFactor/Challenge', [
            'methods' => $this->challenge->methods($user),
            // S-27 line "Signed in as {email}. One more step." (A07). Masked
            // (A09 delta review D3): an OAuth first factor through a linked
            // identity proves only the provider account, not this address.
            'email' => EmailMask::mask($user->email),
            'recoveryAvailable' => config('identity.instance') === 'hosted',
            'retryAt' => $request->session()->get(SessionKeys::RETRY_AT),
            'attemptsLeft' => $this->challenge->attemptsLeft($request),
        ]);
    }

    public function totp(Request $request, SignInSessions $sessions): RedirectResponse|JsonResponse
    {
        return $this->verify($request, $sessions, 'totp');
    }

    public function recovery(Request $request, SignInSessions $sessions): RedirectResponse|JsonResponse
    {
        return $this->verify($request, $sessions, 'recovery_code');
    }

    private function verify(Request $request, SignInSessions $sessions, string $kind): RedirectResponse|JsonResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:32']])['code'];
        $firstFactor = $this->challenge->firstFactor($request);
        $outcome = $this->challenge->verify($request, $kind, $code);

        switch ($outcome->status) {
            case SignInOutcome::RESTART:
                return redirect('/sign-in')->withErrors(['form' => 'challenge_expired']);
            case SignInOutcome::THROTTLED:
                return Answers::throttled($request, $outcome->retryAt ?? $this->clock->now(), $this->clock->now());
            case SignInOutcome::FAILED:
                return back()->withErrors(['code' => 'code_invalid']);
        }
        $user = $outcome->user;
        assert($user !== null);
        // A reset link never claims a guest session (TM-12).
        $sessions->complete($request, $user, $firstFactor, $kind, mayClaim: $firstFactor !== 'reset_link');

        return SignInController::afterSignIn($user);
    }
}
