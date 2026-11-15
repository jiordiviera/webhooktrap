<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\RecoveryCode;
use App\Modules\Identity\Domain\RecoveryCodeGenerator;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\SignInOutcome;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\RecoveryCodeUsed;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The 2FA challenge after a first factor (domain §2.1): state lives in the
 * session, its attempt counter in the cache (5 per challenge, then a fresh
 * sign-in); failures feed the per-account counter across challenges (WA-3).
 */
final readonly class SecondFactorChallenge
{
    /** Seconds a challenge stays live. */
    private const int LIFETIME = 900;

    public function __construct(
        private Clock $clock,
        private Throttle $throttle,
        private IdentityLinks $links,
        private SecondFactorFailures $failures,
        private Notifier $notifier,
        private TwoFactorState $twoFactor,
        private TotpVerifier $totp,
    ) {}

    /**
     * @param  array{provider: string, subject: string, label: string, verified: bool}|null  $pendingIdentity
     *                                                                                                         an email-match identity linked only once the challenge succeeds (A09 F3)
     */
    public function begin(Request $request, User $user, string $firstFactor, ?array $pendingIdentity = null): void
    {
        $request->session()->migrate(true);
        $request->session()->put(SessionKeys::CHALLENGE, [
            'id' => bin2hex(random_bytes(16)),
            'user_id' => $user->id,
            'first_factor' => $firstFactor,
            'started_at' => $this->clock->now()->getTimestamp(),
            'pending_identity' => $pendingIdentity,
        ]);
    }

    /** The half-signed-in user, or null when there is no live challenge (15 min). */
    public function pendingUser(Request $request): ?User
    {
        $c = $request->session()->get(SessionKeys::CHALLENGE);
        if (! is_array($c) || $this->clock->now()->getTimestamp() - (int) $c['started_at'] > self::LIFETIME) {
            return null;
        }
        $user = User::query()->find((string) $c['user_id']);

        return $user !== null && $user->isLive() ? $user : null;
    }

    /** @return list<string> */
    public function methods(User $user): array
    {
        $methods = [];
        if ($user->webauthnCredentials()->whereNull('flagged_at')->exists()) {
            $methods[] = 'security_key';
        }
        if ($user->hasConfirmedTotp()) {
            $methods[] = 'totp';
        }
        if ($user->recoveryCodes()->whereNull('used_at')->exists()) {
            $methods[] = 'recovery_code';
        }

        return $methods;
    }

    /**
     * Attempts left on this challenge (S-27 "{n} attempts left", shown at
     * 2 or fewer). Read-only: the counter is the server-side one `attempt()`
     * reserves before every check.
     */
    public function attemptsLeft(Request $request): int
    {
        $state = (array) $request->session()->get(SessionKeys::CHALLENGE);
        $max = (int) config('identity.throttle.challenge_attempts');
        if (! is_string($state['id'] ?? null)) {
            return $max;
        }

        return max(0, $max - $this->throttle->attempts("2fa:challenge:{$state['id']}"));
    }

    public function firstFactor(Request $request): string
    {
        $c = (array) $request->session()->get(SessionKeys::CHALLENGE);

        return (string) ($c['first_factor'] ?? 'password');
    }

    /** Verifies a TOTP or recovery code; the caller completes the sign-in on success. */
    public function verify(Request $request, string $kind, string $code): SignInOutcome
    {
        return $this->attempt($request, $kind, fn (User $user): bool => $kind === 'totp'
            ? $this->totp->verify($user, $code)
            : $this->useRecoveryCode($user, $code));
    }

    /**
     * Runs one second-factor check under every throttle (A09 F2), each
     * counted before the check: the per-address limit, the per-challenge
     * attempt counter (server side, outside the session) and the per-account
     * WA-3 counter, read and written under a row lock on the user so
     * concurrent attempts are evaluated one at a time.
     *
     * @param  string  $kind  totp|recovery_code|webauthn
     * @param  callable(User): bool  $check
     */
    public function attempt(Request $request, string $kind, callable $check): SignInOutcome
    {
        $user = $this->pendingUser($request);
        $state = (array) $request->session()->get(SessionKeys::CHALLENGE);
        if ($user === null || ! is_string($state['id'] ?? null)) {
            return new SignInOutcome(SignInOutcome::RESTART);
        }
        $context = RequestContext::from($request);
        $c = config('identity.throttle');
        $addressKey = "2fa:addr:{$context->addressKey}";
        if (! $this->throttle->reserve($addressKey, $c['two_factor_address']['max'], $c['two_factor_address']['decay'])) {
            return new SignInOutcome(SignInOutcome::THROTTLED, $this->throttle->availableAt($addressKey));
        }
        $attemptsKey = "2fa:challenge:{$state['id']}";
        $max = (int) $c['challenge_attempts'];
        if (! $this->throttle->reserve($attemptsKey, $max, self::LIFETIME)) {
            $request->session()->forget(SessionKeys::CHALLENGE);

            return new SignInOutcome(SignInOutcome::RESTART);
        }

        $verified = null;
        $result = $this->failures->guarded($user, $kind, $context, function (User $locked) use ($check, &$verified): bool {
            $ok = $check($locked);
            $verified = $ok ? $locked : null;

            return $ok;
        });
        if ($result instanceof CarbonImmutable) {
            return new SignInOutcome(SignInOutcome::THROTTLED, $result);
        }
        if ($result && $verified instanceof User) {
            $pending = $state['pending_identity'] ?? null;
            if (is_array($pending)) {
                /** @var array{provider: string, subject: string, label: string, verified: bool} $pending */
                $this->links->create($verified, $pending, 'oauth_email_match', $context);
            }

            return new SignInOutcome(SignInOutcome::SIGNED_IN, null, $verified);
        }
        if ($this->throttle->attempts($attemptsKey) >= $max) {
            $request->session()->forget(SessionKeys::CHALLENGE);

            return new SignInOutcome(SignInOutcome::RESTART);
        }
        if ($until = $this->failures->heldUntil($user->refresh())) {
            return new SignInOutcome(SignInOutcome::THROTTLED, $until);
        }

        return new SignInOutcome(SignInOutcome::FAILED);
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $updated = RecoveryCode::query()
            ->where('user_id', $user->id)
            ->where('code_hash', Bytea::bind(RecoveryCodeGenerator::hash($code)))
            ->whereNull('used_at')
            ->update(['used_at' => $this->clock->now()]);
        if ($updated !== 1) {
            return false;
        }
        $left = (int) $this->twoFactor->codesLeft($user);
        $this->notifier->send($user->email, 'recovery-code-used', ['codesLeft' => $left]);
        RecoveryCodeUsed::dispatch($user->id, $left);

        return true;
    }
}
