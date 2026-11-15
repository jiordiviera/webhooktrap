<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Identity\Contracts\StartedGuestSession;
use App\Modules\Identity\Domain\GuestGuard;
use App\Modules\Identity\Domain\GuestSession;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\Tokens;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\GuestSessionClaimed;
use App\Modules\Identity\Events\GuestSessionEnded;
use App\Modules\Identity\Events\GuestSessionStarted;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie as CookieJar;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Guest sessions (domain §2, ADR-003 amendment 1): 24 h fixed lifetime,
 * `__Host-wt_guest` cookie with a 256-bit token stored hashed, and the claim
 * on sign-in bound to a nonce created at sign-in start (TM-12).
 */
final readonly class GuestSessions
{
    public function __construct(private Clock $clock, private Throttle $throttle) {}

    /** The live guest session of this browser, if any. */
    public function current(): ?GuestSession
    {
        return $this->guard()->guestSession();
    }

    /** The session named by the cookie, live or not (for `/expired`). */
    public function fromCookie(): ?GuestSession
    {
        return $this->guard()->sessionFromCookie();
    }

    /** Id of this browser's live guest session (for other modules). */
    public function currentId(): ?string
    {
        return $this->current()?->id;
    }

    /** Id of the session the cookie names even when it is no longer live (for `/expired`). */
    public function cookieSessionId(): ?string
    {
        return $this->fromCookie()?->id;
    }

    public function principal(Request $request): ?Principal
    {
        $user = Auth::guard('web')->user();
        if ($user instanceof User) {
            return new Principal('user', $user->id);
        }
        $guest = $this->current();

        return $guest === null ? null : new Principal('guest', $guest->id);
    }

    /**
     * Creates a guest session (per-address and global creation limits). The
     * caller holds the transaction and creates the workspace.
     */
    public function start(Request $request): StartedGuestSession
    {
        if (! (bool) config('identity.guest_mode')) {
            throw new IdentityRefusal('guest_mode_off');
        }
        $context = RequestContext::from($request);
        $c = config('identity.guest');
        foreach ([["guest:new:{$context->addressKey}", $c['create_per_address_per_hour']], ['guest:new:global', $c['create_global_per_hour']]] as [$key, $max]) {
            if (! $this->throttle->reserve($key, (int) $max, 3600)) {
                throw new IdentityRefusal('throttled');
            }
        }

        $token = Tokens::random();
        $now = $this->clock->now();
        $session = GuestSession::query()->create([
            'token_hash' => Tokens::hash($token),
            'created_at' => $now,
            'expires_at' => $now->addHours((int) $c['lifetime_hours']),
        ]);
        GuestSessionStarted::dispatch($session->id);

        return new StartedGuestSession($session->id, $this->cookie($token, $session->expires_at));
    }

    public function end(GuestSession $session, string $reason): void
    {
        if ($session->ended_at !== null) {
            return;
        }
        $session->forceFill(['ended_at' => $this->clock->now()])->save();
        GuestSessionEnded::dispatch($session->id, $reason);
    }

    /** `__Host-` cookie: Secure, HttpOnly, SameSite=Lax, Path=/, no Domain (TM-10). */
    public function cookie(string $token, CarbonImmutable $expiresAt): Cookie
    {
        return CookieJar::make(
            (string) config('identity.guest.cookie'),
            $token,
            max(1, (int) ceil(($expiresAt->getTimestamp() - $this->clock->now()->getTimestamp()) / 60)),
            '/', null, true, true, false, 'lax',
        );
    }

    public function forgetCookie(): Cookie
    {
        return CookieJar::forget((string) config('identity.guest.cookie'), '/', null);
    }

    /** At sign-in start from a live guest session: a single-use claim nonce. */
    public function beginClaim(Request $request): void
    {
        $guest = $this->current();
        if ($guest === null) {
            return;
        }
        $nonce = Tokens::random();
        $guest->forceFill([
            'claim_nonce_hash' => Tokens::hash($nonce),
            'claim_nonce_expires_at' => $this->clock->now()->addMinutes((int) config('identity.guest.claim_nonce_minutes')),
        ])->save();
        $request->session()->put(SessionKeys::CLAIM, ['guest_session_id' => $guest->id, 'nonce' => $nonce]);
    }

    /**
     * Claims the guest session when, and only when, the sign-in completing now
     * was started from it in this browser session; the nonce is consumed.
     */
    public function claim(Request $request, User $user): bool
    {
        $claim = $request->session()->pull(SessionKeys::CLAIM);
        if (! is_array($claim) || ! isset($claim['guest_session_id'], $claim['nonce'])) {
            return false;
        }
        $guest = $this->current();
        $now = $this->clock->now();
        if ($guest === null || $guest->id !== $claim['guest_session_id']) {
            return false;
        }

        // One conditional UPDATE: the nonce is single use even under races.
        $updated = GuestSession::query()
            ->whereKey($guest->id)
            ->where('claim_nonce_hash', Bytea::bind(Tokens::hash((string) $claim['nonce'])))
            ->where('claim_nonce_expires_at', '>', $now)
            ->whereNull('claimed_at')
            ->whereNull('ended_at')
            ->where('expires_at', '>', $now)
            ->update([
                'claimed_by_user_id' => $user->id,
                'claimed_at' => $now,
                'claim_nonce_hash' => null,
                'claim_nonce_expires_at' => null,
            ]);
        if ($updated !== 1) {
            return false;
        }
        $claimed = $guest->refresh();
        GuestSessionClaimed::dispatch($claimed->id, $user->id, $claimed->isEmpty());

        CookieJar::queue($this->forgetCookie());
        $this->guard()->forget();
        $request->session()->migrate(true);
        $request->session()->flash('flash', ['kind' => 'success', 'code' => 'claimed', 'message' => $user->email]);

        return true;
    }

    /**
     * The guest meter (contracts §3.1, S-00): stored deliveries and counted
     * runs against the session limits, and the time left (`secondsLeft`,
     * from the server clock, so the client needs no clock of its own to
     * start the countdown).
     *
     * @return array{expiresAt: string, secondsLeft: int, captures: array{used: int, limit: int}, runs: array{used: int, limit: int}}
     */
    public function meter(GuestSession $guest): array
    {
        $q = config('identity.guest.quotas');

        return [
            'expiresAt' => $guest->expires_at->toIso8601ZuluString('millisecond'),
            'secondsLeft' => max(0, $guest->expires_at->getTimestamp() - $this->clock->now()->getTimestamp()),
            'captures' => ['used' => $guest->stored_captures, 'limit' => (int) $q['captures']],
            'runs' => ['used' => $guest->counted_runs, 'limit' => (int) $q['runs']],
        ];
    }

    private function guard(): GuestGuard
    {
        return app('identity.guest-guard');
    }
}
