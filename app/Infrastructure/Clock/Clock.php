<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use Carbon\CarbonImmutable;

/**
 * The server clock. Modules depend on this contract and never call now() or
 * Carbon::now() themselves (domain-model §1.2 rule 4, D-10).
 */
interface Clock
{
    public function now(): CarbonImmutable;
}
