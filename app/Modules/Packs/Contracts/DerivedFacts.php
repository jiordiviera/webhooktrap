<?php

declare(strict_types=1);

namespace App\Modules\Packs\Contracts;

/**
 * What one capture's headers and body say, read through the packs (domain
 * §4 derivations). A pure function of (headers, body, pack version): nothing
 * here depends on other captures. Cross-capture facts (GRP-2 merging, SHP-3
 * verdicts) are Capture's job.
 */
final readonly class DerivedFacts
{
    /**
     * @param  list<array{type: string, id: string, searchOnly: bool}>  $objects  GRP-1, yield order
     * @param  list<GroupRef>  $groupRefs  objects that may link captures (GRP-2), best anchor first
     * @param  list<array{0: string, 1: string}>|null  $shape  SHP-1, sorted; null for non-JSON or over the limits
     */
    public function __construct(
        public ?string $provider,
        public ?string $providerName,
        /** `stripe@1.0.0`, or `none@<pack set version>` when no pack matched. */
        public string $packRef,
        public ?string $eventType,
        public ?string $eventId,
        public ?string $apiVersion,
        public array $objects,
        public array $groupRefs,
        /** GRP-4 group when the pack yields no objects. */
        public ?GroupRef $fallbackGroup,
        public bool $json,
        public ?array $shape,
    ) {}

    /** @return list<string> */
    public function objectIds(): array
    {
        return array_values(array_unique(array_map(fn (array $o): string => $o['id'], $this->objects)));
    }
}
