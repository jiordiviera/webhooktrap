<?php

declare(strict_types=1);

namespace App\Modules\Agent\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An agent paired into a workspace (event-catalogue §2). Ids are stored
 * uuids; never the code or the key. `network` is the source address cut to
 * /24 or /48 (domain §2.7), for the "New agent paired" email (AC-7).
 */
final readonly class AgentPaired
{
    use Dispatchable;

    public function __construct(
        public string $agentId,
        public string $workspaceId,
        public ?string $os,
        public string $keyFingerprint,
        public string $name = '',
        public string $network = '',
    ) {}
}
