<?php

declare(strict_types=1);

namespace App\Infrastructure\Ids;

use App\Infrastructure\Clock\Clock;
use Illuminate\Support\Str;

/**
 * Creates new UUIDv7 values, timed by the Clock adapter.
 */
final readonly class IdGenerator
{
    public function __construct(private Clock $clock) {}

    /** The value stored in a `uuid` column. */
    public function uuid(): string
    {
        return Str::uuid7($this->clock->now())->toString();
    }

    /** A fresh id in external form, e.g. `run_01M75F1M56RSH0CVKCTFX4FQWC`. */
    public function external(string $prefix): string
    {
        return EntityId::encode($prefix, $this->uuid());
    }
}
