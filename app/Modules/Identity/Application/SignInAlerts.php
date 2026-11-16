<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\RateLimit\MinuteCounters;

/**
 * Instance-wide counts behind the failed sign-in alert (hosted plan §6.6,
 * A09 R-AA residual): failed password, TOTP, recovery-code and security-key
 * checks, and `throttled` answers. Counts only: no email, address, user id or
 * cookie is stored. `/health` reads `snapshot()`.
 *
 * The hash concurrency cap (`hashcap`) and the scheduled email are not built
 * yet; the level here uses failures and throttled answers only.
 */
final readonly class SignInAlerts
{
    public function __construct(private MinuteCounters $counters) {}

    public function failed(): void
    {
        $this->counters->add('signin:fail');
    }

    public function throttled(): void
    {
        $this->counters->add('signin:throttled');
    }

    /**
     * @return array{failures: int|null, throttled: int|null, level: 'ok'|'warn'|'crit'|null}
     */
    public function snapshot(): array
    {
        $c = config('identity.signin_alert');
        $minutes = max(1, intdiv((int) $c['window_seconds'] + 59, 60));
        $failures = $this->counters->sum('signin:fail', $minutes);
        $throttled = $this->counters->sum('signin:throttled', $minutes);
        if ($failures === null || $throttled === null) {
            return ['failures' => $failures, 'throttled' => $throttled, 'level' => null];
        }

        $level = match (true) {
            $failures >= (int) $c['crit_failures'] => 'crit',
            $failures >= (int) $c['warn_failures'], $throttled >= (int) $c['warn_throttled'] => 'warn',
            default => 'ok',
        };

        return ['failures' => $failures, 'throttled' => $throttled, 'level' => $level];
    }
}
