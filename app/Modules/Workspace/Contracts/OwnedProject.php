<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contracts;

use Carbon\CarbonImmutable;

/**
 * Read model of a live project the caller has just been checked to own.
 * Other modules receive this, never the model (domain §1.2 rule 1).
 */
final readonly class OwnedProject
{
    public function __construct(
        public string $id,
        public string $publicId,
        public string $workspaceId,
        public string $key,
        public string $name,
        public bool $isGuest,
        public int $captureRetentionDays,
        public int $runRetentionDays,
        public string $defaultVisibility,
        public CarbonImmutable $createdAt,
    ) {}
}
