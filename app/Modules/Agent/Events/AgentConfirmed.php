<?php

declare(strict_types=1);

namespace App\Modules\Agent\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** The local [y/N] confirmed an agent (event-catalogue §2); replaced agents were revoked in the same transaction. */
final readonly class AgentConfirmed
{
    use Dispatchable;

    public function __construct(public string $agentId, public string $workspaceId, /** @var list<string> */ public array $replacedAgentIds) {}
}
