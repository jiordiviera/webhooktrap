<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

/**
 * What a capture key resolves to, as held in the short-lived cache. Only an
 * active key of a live project whose owner is live is storable; every other
 * case is answered identically and dropped (ADR-005 amendment 1 §4).
 */
final readonly class ResolvedKey
{
    public function __construct(
        public ?string $urlId,
        public ?string $projectId,
        public bool $active,
        public ?string $ownerKind,
        public ?string $ownerId,
        public bool $ownerLive,
    ) {}

    public static function unknown(): self
    {
        return new self(null, null, false, null, null, false);
    }

    /** Null when storable; otherwise the `CaptureDropped` reason. */
    public function dropReason(): ?string
    {
        return match (true) {
            $this->urlId === null => 'unknown_key',
            ! $this->active => 'rotated',
            ! $this->ownerLive => 'expired',
            default => null,
        };
    }

    /** @return array<string, string|bool|null> */
    public function toCache(): array
    {
        return ['u' => $this->urlId, 'p' => $this->projectId, 'a' => $this->active, 'k' => $this->ownerKind, 'o' => $this->ownerId, 'l' => $this->ownerLive];
    }

    public static function fromCache(mixed $value): ?self
    {
        if (! is_array($value) || ! array_key_exists('u', $value)) {
            return null;
        }
        $str = fn (string $k): ?string => is_string($value[$k] ?? null) ? $value[$k] : null;

        return new self($str('u'), $str('p'), ($value['a'] ?? false) === true, $str('k'), $str('o'), ($value['l'] ?? false) === true);
    }
}
