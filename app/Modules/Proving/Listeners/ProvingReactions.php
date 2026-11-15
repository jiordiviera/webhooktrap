<?php

declare(strict_types=1);

namespace App\Modules\Proving\Listeners;

use App\Modules\Agent\Events\AgentRevoked;
use App\Modules\Proving\Application\RunJobs;

/**
 * `AgentRevoked` (event-catalogue §2): the agent's open runs end Aborted
 * `agent_lost`, or `account_deleted` when the account is being deleted.
 */
final readonly class ProvingReactions
{
    public function __construct(private RunJobs $jobs) {}

    public function agentRevoked(AgentRevoked $event): void
    {
        $this->jobs->abortForAgent($event->agentId, $event->reason === 'account_deleted' ? 'account_deleted' : 'agent_lost');
    }
}
