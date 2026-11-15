<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contracts;

/**
 * Who owns a live project, for ingest (domain §4: a capture is stored only
 * when the project's owner is live). Ids are stored uuids.
 */
final readonly class ProjectOwner
{
    public function __construct(
        public string $projectId,
        public string $ownerKind,   // 'user' | 'guest'
        public string $ownerId,
        public bool $isGuest,
    ) {}
}
