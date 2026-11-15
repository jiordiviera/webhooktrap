<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use Symfony\Component\HttpFoundation\Cookie;

/** A guest session just created by `POST /new`, with its cookie. */
final readonly class StartedGuestSession
{
    public function __construct(
        public string $id,
        public Cookie $cookie,
    ) {}
}
