<?php

declare(strict_types=1);

namespace App\Modules\Agent\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** An agent was revoked (event-catalogue §2): Proving aborts its open runs. */
final readonly class AgentRevoked
{
    use Dispatchable;

    public function __construct(public string $agentId, public string $workspaceId, public string $reason) {}
}
