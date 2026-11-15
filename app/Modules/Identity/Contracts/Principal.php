<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * Who the browser principal is, for other modules (Workspace ownership,
 * domain §1.1 "Identity → Workspace"). Ids are stored uuids.
 */
final readonly class Principal
{
    public function __construct(
        public string $kind,   // 'user' | 'guest'
        public string $id,
    ) {}

    public function isUser(): bool
    {
        return $this->kind === 'user';
    }

    public function isGuest(): bool
    {
        return $this->kind === 'guest';
    }
}
