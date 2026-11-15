<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Infrastructure\Broadcasting\LiveUpdates;

/**
 * Agent direct events and triggers (event-catalogue §3): `pairing.completed`
 * and `agent.status` to the owner's workspace channel, `agent.revoked` and
 * `job.available` to the agent's own channel, `workspace.agents` for lists.
 * Payloads hold ids, names, versions and states only.
 */
final readonly class AgentBroadcasts
{
    public function __construct(private LiveUpdates $live) {}

    public static function agentChannel(string $agentId): string
    {
        return 'agent.'.$agentId;
    }

    /** @param  array<string, mixed>  $payload */
    public function toWorkspace(string $workspaceId, string $event, array $payload): void
    {
        $this->live->send('workspace.'.$workspaceId, $event, ['v' => 1, ...$payload]);
        $this->live->trigger('workspace.'.$workspaceId, 'workspace.agents');
    }

    /** Re-sends the live agent lists of the workspace (`workspace.agents`), e.g. after mirrors changed. */
    public function refreshWorkspace(string $workspaceId): void
    {
        $this->live->trigger('workspace.'.$workspaceId, 'workspace.agents');
    }

    /** @param  array<string, mixed>  $payload */
    public function toAgent(string $agentId, string $event, array $payload): void
    {
        $this->live->send(self::agentChannel($agentId), $event, ['v' => 1, ...$payload]);
    }
}
