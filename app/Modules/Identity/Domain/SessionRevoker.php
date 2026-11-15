<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Events\SessionRevoked;
use Illuminate\Support\Facades\Cache;

/**
 * Every revocation path acts on `user_sessions` rows (domain §2.7). The
 * liveness cache entry is evicted at once, so revocation does not depend on
 * the session driver and holds on every worker within the cache window.
 */
final readonly class SessionRevoker
{
    public function __construct(private Clock $clock) {}

    public static function cacheKey(string $sessionId): string
    {
        return 'identity:session-live:'.$sessionId;
    }

    public function revoke(UserSession $session, string $reason): void
    {
        if ($session->revoked_at !== null) {
            return;
        }
        $session->forceFill(['revoked_at' => $this->clock->now(), 'revoked_reason' => $reason])->save();
        Cache::forget(self::cacheKey($session->id));
        SessionRevoked::dispatch($session->user_id, $session->id, $reason);
    }

    /** Revokes every live session of the user, except one. Returns the count. */
    public function revokeAll(string $userId, string $reason, ?string $exceptId = null): int
    {
        $sessions = UserSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->when($exceptId !== null, fn ($q) => $q->where('id', '<>', $exceptId))
            ->get();

        foreach ($sessions as $session) {
            $this->revoke($session, $reason);
        }

        return $sessions->count();
    }

    public function isLive(UserSession $session): bool
    {
        $now = $this->clock->now();

        return $session->revoked_at === null
            && $now->lessThan($session->idle_expires_at)
            && $now->lessThan($session->absolute_expires_at);
    }
}
