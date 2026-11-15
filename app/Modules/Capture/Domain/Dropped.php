<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

use RuntimeException;

/** Rolls the ingest transaction back: the request is answered identically and not stored. */
final class Dropped extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
