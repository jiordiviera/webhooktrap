<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

use App\Infrastructure\Clock\Clock;
use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * Instance-wide per-minute counters in the cache store (Valkey in production),
 * e.g. `signin:fail` for the failed sign-in alert (hosted plan §6.6). Keys are
 * `wt:count:<name>:<yyyymmddhhmm>` with a 15-minute TTL; names carry no
 * address, email, user id or cookie. Fail-soft: a store outage drops the
 * count and `sum()` answers null.
 */
final readonly class MinuteCounters
{
    private const int TTL_SECONDS = 900;

    public function __construct(private Repository $cache, private Clock $clock) {}

    public function add(string $name): void
    {
        $key = $this->key($name, 0);
        try {
            $this->cache->add($key, 0, self::TTL_SECONDS);
            $this->cache->increment($key);
        } catch (Throwable) {
            // Counts only; never in the way of the request.
        }
    }

    /** Sum of the last `$minutes` buckets (the current one included), or null when the store is down. */
    public function sum(string $name, int $minutes): ?int
    {
        $keys = [];
        for ($i = 0; $i < max(1, $minutes); $i++) {
            $keys[] = $this->key($name, $i);
        }
        try {
            $values = $this->cache->many($keys);
        } catch (Throwable) {
            return null;
        }

        return array_sum(array_map(fn (mixed $v): int => is_numeric($v) ? (int) $v : 0, $values));
    }

    private function key(string $name, int $minutesAgo): string
    {
        return 'wt:count:'.$name.':'.$this->clock->now()->utc()->subMinutes($minutesAgo)->format('YmdHi');
    }
}
