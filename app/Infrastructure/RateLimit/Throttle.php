<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

use App\Infrastructure\Clock\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;

/**
 * Counters and holds behind the throttles of domain §2.1 ("Throttle values").
 * Backed by the cache store (Valkey/Redis in production) through Laravel's
 * contracts; keys are namespaced and never contain an email in clear.
 */
final readonly class Throttle
{
    public function __construct(
        private RateLimiter $limiter,
        private Repository $cache,
        private Clock $clock,
    ) {}

    public function attempts(string $key): int
    {
        return (int) $this->limiter->attempts('wt:'.$key);
    }

    public function tooMany(string $key, int $max): bool
    {
        return $this->limiter->tooManyAttempts('wt:'.$key, $max);
    }

    public function hit(string $key, int $decaySeconds): int
    {
        return $this->limiter->hit('wt:'.$key, $decaySeconds);
    }

    /**
     * Reserve-then-check (A09 F2): counts this attempt atomically before the
     * expensive check and answers whether it is within the limit. Concurrent
     * requests each get a distinct count, so at most `$max` pass.
     */
    public function reserve(string $key, int $max, int $decaySeconds): bool
    {
        return $this->limiter->hit('wt:'.$key, $decaySeconds) <= $max;
    }

    /** Gives back a reservation taken by `reserve()` (the attempt succeeded). */
    public function refund(string $key, int $decaySeconds): void
    {
        if ($this->attempts($key) > 0) {
            $this->limiter->decrement('wt:'.$key, $decaySeconds);
        }
    }

    public function clear(string $key): void
    {
        $this->limiter->clear('wt:'.$key);
    }

    /** When the window of a counter that hit its limit ends. */
    public function availableAt(string $key): CarbonImmutable
    {
        return $this->clock->now()->addSeconds(max(1, $this->limiter->availableIn('wt:'.$key)));
    }

    /** Places a hold that ends at now + $seconds; returns its end. */
    public function hold(string $key, int $seconds): CarbonImmutable
    {
        $until = $this->clock->now()->addSeconds($seconds);
        $this->cache->put('wt:hold:'.$key, $until->getTimestamp(), $seconds);

        return $until;
    }

    public function heldUntil(string $key): ?CarbonImmutable
    {
        $ts = $this->cache->get('wt:hold:'.$key);
        if (! is_int($ts)) {
            return null;
        }
        $until = CarbonImmutable::createFromTimestampUTC($ts);

        return $until->greaterThan($this->clock->now()) ? $until : null;
    }

    public function release(string $key): void
    {
        $this->cache->forget('wt:hold:'.$key);
    }
}
