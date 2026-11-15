<?php

declare(strict_types=1);

namespace App\Modules\Packs\Contracts;

/**
 * One candidate group anchor of a capture (GRP-1..4): an object the capture
 * yields, the group key it would give the group, its GRP-3 label and its
 * rank in the pack's anchor priority (0 is the highest).
 */
final readonly class GroupRef
{
    public function __construct(
        public string $type,
        public string $id,
        public string $key,
        public string $label,
        /** The label is a display name from the body (`Order #1042`), not the bare id. */
        public bool $display,
        public int $rank,
    ) {}
}
