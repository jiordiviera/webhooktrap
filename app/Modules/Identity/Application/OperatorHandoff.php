<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * The app → admin handoff (decision 74). The session cookie is `__Host-`
 * and host-only, and WebAuthn's allowed origin is the app's, so the admin
 * host cannot sign anyone in itself. A recently confirmed app session mints
 * a token; the admin host trades it for its own session:
 *
 * - 256 random bits, only the SHA-256 is kept, in the shared store;
 * - valid `admin.handoff_seconds` (60 s), single use (an atomic `add` marks
 *   it spent before it is read, so two racing requests cannot both win);
 * - bound to the user and to the app `user_sessions` row that minted it:
 *   that row must still be live, and the account still eligible, when the
 *   token is spent.
 */
final readonly class OperatorHandoff
{
    public function __construct(private SignInSessions $sessions, private Audit $audit) {}

    /** A token for this eligible, recently confirmed app session, or null. */
    public function issue(Request $request, User $user): ?string
    {
        $origin = $request->session()->get(SessionKeys::USER_SESSION);
        if (! $user->isEligibleOperator() || ! is_string($origin)) {
            return null;
        }
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Cache::put(self::key($token), ['user_id' => $user->id, 'origin_session' => $origin], (int) config('admin.handoff_seconds'));
        $this->audit->record('operator_handoff_issued', $user, RequestContext::from($request));

        return $token;
    }

    /**
     * Spends the token and signs the user in on this (admin-host) browser
     * session. Null when the token is unknown, spent, expired, or its
     * account or app session no longer qualifies.
     */
    public function redeem(Request $request, string $token): ?User
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1) {
            return null;
        }
        $key = self::key($token);
        if (! Cache::add($key.':spent', true, (int) config('admin.handoff_seconds') * 2)) {
            return null;
        }
        $payload = Cache::get($key);
        Cache::forget($key);
        if (! is_array($payload) || ! is_string($payload['user_id'] ?? null) || ! is_string($payload['origin_session'] ?? null)) {
            return null;
        }
        $user = User::query()->find($payload['user_id']);
        if (! $user instanceof User || ! $user->isEligibleOperator() || ! $this->sessions->isLive($payload['origin_session'])) {
            return null;
        }

        // A session already open on this browser ends first: one live
        // `user_sessions` row per admin browser.
        if (Auth::guard('web')->check()) {
            $this->sessions->signOut($request);
        }
        $this->sessions->complete($request, $user, 'operator_handoff', null, mayClaim: false, setsRecentAuth: false);

        return $user;
    }

    private static function key(string $token): string
    {
        return 'wt:operator-handoff:'.hash('sha256', $token);
    }
}
