<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Factory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Throttled reports of fail-open store outages (A09 S3-11). While the
 * Redis-compatible store behind the rate limiter or a short-lived cache is
 * down, every ingest request would otherwise call `report()`. Here a failure
 * of one `kind` is reported at most once per `cache.failure_reports.every_seconds`:
 * the exception goes to `report()` and one warning line `wt.store_failure`
 * carries `kind` and `suppressed` (the failures since the last report), for
 * an alert to watch.
 *
 * The throttle lives in a local store (`cache.failure_reports.store`, the
 * file store by default), never in the store that is failing. If that one
 * fails too, the failure is reported unthrottled.
 */
final readonly class StoreFailures
{
    public function __construct(private Factory $caches, private LoggerInterface $log) {}

    public function report(string $kind, Throwable $e): void
    {
        $every = max(1, (int) config('cache.failure_reports.every_seconds', 60));
        try {
            $store = $this->caches->store((string) config('cache.failure_reports.store', 'file'));
            if (! $store->add('wt:store-failure:'.$kind, 1, $every)) {
                $store->increment('wt:store-failure-suppressed:'.$kind);

                return;
            }
            $suppressed = (int) $store->pull('wt:store-failure-suppressed:'.$kind, 0);
        } catch (Throwable) {
            $suppressed = 0;
        }

        $this->log->warning('wt.store_failure', ['kind' => $kind, 'suppressed' => $suppressed, 'error' => $e::class]);
        report($e);
    }
}
