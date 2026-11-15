<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\DummyHash;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\SignInOutcome;
use App\Modules\Identity\Domain\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * `POST /sign-in/password` (contracts §3.4). Throttles are reserved before any hash;
 * during a pair or account hold the password is not verified (TH-1a); every
 * failure path takes the same argon2id cost (TH-1c).
 */
final readonly class PasswordSignIn
{
    public function __construct(
        private SignInThrottle $throttle,
        private SecondFactorChallenge $challenge,
        private SignInSessions $sessions,
    ) {}

    public function attempt(Request $request, string $email, string $password): SignInOutcome
    {
        $context = RequestContext::from($request);
        $emailKey = $this->throttle->emailKey($email);

        if ($retryAt = $this->throttle->reservePassword($emailKey, $context->addressKey)) {
            return new SignInOutcome(SignInOutcome::THROTTLED, $retryAt);
        }

        $user = User::query()->where('email', trim($email))->first();
        $live = $user !== null && $user->isLive() ? $user : null;

        if (! DummyHash::verify($password, $live?->password_hash)) {
            $this->throttle->recordFailure($emailKey, $context->addressKey, $live, $context);

            return new SignInOutcome(SignInOutcome::FAILED);
        }
        assert($live !== null);

        $this->throttle->recordSuccess($emailKey, $context->addressKey);
        if ($live->password_hash !== null && Hash::needsRehash($live->password_hash)) {
            $live->forceFill(['password_hash' => Hash::make($password)])->save();
        }

        // F1 (pre-account takeover): an unverified account gets no session.
        // The answer matches registration: verify page, and this browser
        // becomes the registering one for the verification link.
        if (! $live->isVerified()) {
            $request->session()->migrate(true);
            $request->session()->put(SessionKeys::PENDING_REGISTRATION, $live->id);

            return new SignInOutcome(SignInOutcome::VERIFY_EMAIL, null, $live);
        }

        if ($live->hasSecondFactor()) {
            $this->challenge->begin($request, $live, 'password');

            return new SignInOutcome(SignInOutcome::CHALLENGE, null, $live);
        }

        $this->sessions->complete($request, $live, 'password', null);

        return new SignInOutcome(SignInOutcome::SIGNED_IN, null, $live);
    }
}
