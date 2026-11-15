<?php

declare(strict_types=1);

namespace App\Modules\Proving\Contracts;

use RuntimeException;

/**
 * A refused run or job operation, with the protocol answer it maps to
 * (contracts §3.2 `POST /p/{projectKey}/runs` 422 codes; §5.4–§5.7 agent
 * answers). The message is the machine code, never content.
 */
final class RunRefusal extends RuntimeException
{
    /** @param  array<string, scalar|null>  $extra */
    public function __construct(
        public readonly int $status,
        public readonly string $error,
        public readonly array $extra = [],
    ) {
        parent::__construct($error);
    }

    public static function notFound(): self
    {
        return new self(404, 'not_found');
    }
}
