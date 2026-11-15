<?php

declare(strict_types=1);

namespace App\Modules\Proving\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An executor accepted a run's job (event-catalogue §2): the guest
 * accepted-run counter, `run.accepted`. The target (scheme, host, port) lets
 * Agent mirror the approval an agent gives before it accepts a browser run.
 */
final readonly class RunAccepted
{
    use Dispatchable;

    public function __construct(
        public string $runId,
        public string $projectId,
        public ?string $agentId,
        public ?string $initiatorGuestSessionId,
        public ?string $targetScheme = null,
        public ?string $targetHost = null,
        public ?int $targetPort = null,
    ) {}
}
