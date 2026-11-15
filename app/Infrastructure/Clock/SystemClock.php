<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use Carbon\CarbonImmutable;

/**
 * Wall clock in UTC. Respects Carbon::setTestNow(), so tests can freeze it.
 * Holds no state, which keeps it safe under FrankenPHP worker mode.
 */
final readonly class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }
}
