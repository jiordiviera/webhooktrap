<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Repository;
use Throwable;

/**
 * Reachability of the default cache store (Valkey in production) for
 * `/health`: one read of a fixed key. Never throws.
 */
final readonly class StoreProbe
{
    public function __construct(private Repository $cache) {}

    public function reachable(): bool
    {
        try {
            $this->cache->get('wt:health:probe');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
