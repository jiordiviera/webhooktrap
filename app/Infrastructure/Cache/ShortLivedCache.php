<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * A short-lived cache in front of a lookup that must keep working when the
 * Redis-compatible store does not (ADR-004: persistence never depends on it).
 * Every failure of the store is treated as a miss, and reported at most
 * once a minute (`StoreFailures`); callers then compute the value themselves. Keys are namespaced; callers hash anything
 * secret before using it in a key.
 */
final readonly class ShortLivedCache
{
    public function __construct(private Repository $cache, private StoreFailures $failures) {}

    public function get(string $key): mixed
    {
        try {
            return $this->cache->get('wt:'.$key);
        } catch (Throwable $e) {
            $this->failures->report('cache', $e);

            return null;
        }
    }

    public function put(string $key, mixed $value, int $seconds): void
    {
        try {
            $this->cache->put('wt:'.$key, $value, $seconds);
        } catch (Throwable $e) {
            $this->failures->report('cache', $e);
        }
    }

    public function forget(string $key): void
    {
        try {
            $this->cache->forget('wt:'.$key);
        } catch (Throwable $e) {
            $this->failures->report('cache', $e);
        }
    }
}
