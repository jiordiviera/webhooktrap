<?php

declare(strict_types=1);

namespace App\Modules\Agent\Contracts;

use Carbon\CarbonImmutable;

/**
 * Read model of an agent for other modules (Proving, pages). Ids are stored
 * uuids; `publicId` is the `agt_` form. Mirrors are display-only.
 */
final readonly class AgentView
{
    /**
     * @param  list<array{scheme: string, host: string, port: int, kind: string, declared: bool, approved: bool}>  $targets
     */
    public function __construct(
        public string $id,
        public string $publicId,
        public string $workspaceId,
        public string $name,
        public string $version,
        public ?string $os,
        public string $keyFingerprint,
        public bool $needsReconfirm,
        public bool $confirmed,
        public bool $guestSlot,
        public bool $revoked,
        public ?string $revokedReason,
        public ?CarbonImmutable $lastSeenAt,
        public CarbonImmutable $pairedAt,
        public array $targets = [],
    ) {}

    /** AgentSummary.state (contracts §3.3). */
    public function displayState(CarbonImmutable $now): string
    {
        if (! $this->confirmed) {
            return 'awaiting_confirmation';
        }
        if ($this->needsReconfirm) {
            return 'needs_reconfirm';
        }
        $within = (int) config('agent.connected_within_seconds');

        return $this->lastSeenAt !== null && $this->lastSeenAt->greaterThan($now->subSeconds($within)) ? 'connected' : 'disconnected';
    }
}
