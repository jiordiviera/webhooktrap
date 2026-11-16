<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\SessionRevoker;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserSession;
use App\Modules\Identity\Events\UserSignedIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

/**
 * Completing a sign-in and managing `user_sessions` (domain §2.7). The
 * browser session is regenerated at sign-in, after the 2FA challenge and at
 * claim; the claim and RC-1 run only after any second factor.
 */
final readonly class SignInSessions
{
    public function __construct(
        private Clock $clock,
        private Audit $audit,
        private SessionRevoker $revoker,
        private RecentAuthentication $recent,
        private GuestSessions $guests,
        private AccountRecovery $recovery,
    ) {}

    /**
     * @param  string  $firstFactor  password|github|google|passkey|reset_link|recovery_finish
     * @param  string|null  $secondFactor  security_key|totp|recovery_code|passkey
     */
    public function complete(Request $request, User $user, string $firstFactor, ?string $secondFactor, bool $mayClaim = true, bool $setsRecentAuth = true): UserSession
    {
        $context = RequestContext::from($request);
        $now = $this->clock->now();
        $session = $request->session();
        $session->forget([SessionKeys::CHALLENGE, SessionKeys::RECENT_AUTH, SessionKeys::CONFIRMATION, SessionKeys::PENDING_REGISTRATION]);
        $session->migrate(true);

        Auth::guard('web')->login($user);

        $row = UserSession::query()->create([
            'user_id' => $user->id,
            'created_at' => $now,
            'last_seen_at' => $now,
            'idle_expires_at' => $now->addDays((int) config('identity.sessions.idle_days')),
            'absolute_expires_at' => $now->addDays((int) config('identity.sessions.absolute_days')),
            'first_factor' => $firstFactor,
            'second_factor' => $secondFactor,
            'ua_label' => $context->uaLabel,
            'ip_prefix' => $context->ipPrefix,
        ]);
        $session->put(SessionKeys::USER_SESSION, $row->id);

        // A sign-in with a knowledge or possession factor counts as recent
        // authentication; a reset link, a recovery finish or OAuth alone never
        // does (AR-2, domain §2.1).
        $completedSecondFactor = $secondFactor !== null || $firstFactor === 'passkey';
        $setsRecentAuth = $setsRecentAuth && ! in_array($firstFactor, ['reset_link', 'recovery_finish'], true);
        if (! $setsRecentAuth) {
            // AR-2: emailed links prove the mailbox only.
        } elseif ($completedSecondFactor) {
            $this->recent->record($request, $secondFactor === 'recovery_code' ? 'recovery_code' : ($secondFactor ?? 'passkey'));
        } elseif ($firstFactor === 'password') {
            $this->recent->record($request, 'password');
        }

        if ($mayClaim) {
            $this->guests->claim($request, $user);
        } else {
            $session->forget(SessionKeys::CLAIM);
        }

        if ($completedSecondFactor) {
            $this->recovery->cancelOnSecondFactor($user, $context);
        }

        $this->audit->record('signed_in', $user, $context, ['how' => ['first' => $firstFactor, 'second' => $secondFactor]]);
        UserSignedIn::dispatch($user->id, $row->id, $firstFactor, $secondFactor);

        return $row;
    }

    public function current(Request $request): ?UserSession
    {
        $id = $request->session()->get(SessionKeys::USER_SESSION);

        return is_string($id) ? UserSession::query()->find($id) : null;
    }

    /**
     * Liveness check on every authenticated request, cached for at most the
     * configured window; revocation evicts the entry (TM-T156).
     */
    public function isLive(string $sessionId): bool
    {
        $seconds = (int) config('identity.sessions.liveness_cache_seconds');

        return (bool) Cache::remember(SessionRevoker::cacheKey($sessionId), $seconds, function () use ($sessionId): bool {
            $row = UserSession::query()->find($sessionId);

            return $row !== null && $this->revoker->isLive($row);
        });
    }

    /**
     * The `user_sessions` row of this browser's signed-in session is live
     * (and is touched); false without one. For hosts that do not run the
     * `web` group, such as the admin panel (decision 74).
     */
    public function browserSessionIsLive(Request $request): bool
    {
        $id = $request->session()->get(SessionKeys::USER_SESSION);
        if (! is_string($id) || ! $this->isLive($id)) {
            return false;
        }
        $this->touch($id);

        return true;
    }

    public function touch(string $sessionId): void
    {
        $now = $this->clock->now();
        $every = (int) config('identity.sessions.last_seen_every_seconds');
        UserSession::query()
            ->whereKey($sessionId)
            ->whereNull('revoked_at')
            ->where('last_seen_at', '<', $now->subSeconds($every))
            ->update([
                'last_seen_at' => $now,
                'idle_expires_at' => $now->addDays((int) config('identity.sessions.idle_days')),
            ]);
    }

    public function signOut(Request $request, bool $keepIntended = false): void
    {
        if ($row = $this->current($request)) {
            $this->revoker->revoke($row, 'signed_out');
        }
        $intended = $request->session()->get(SessionKeys::INTENDED);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory(); // D1: drop the encrypted history of the signed-in pages
        if ($keepIntended && is_string($intended)) {
            $request->session()->put(SessionKeys::INTENDED, $intended);
        }
    }

    /** Ends this browser's session after the account went away (tombstone). */
    public function endBrowserSession(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();
    }
}
