<?php

declare(strict_types=1);

namespace App\Modules\Agent\Listeners;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\EntityId;
use App\Modules\Agent\Application\AgentBroadcasts;
use App\Modules\Agent\Application\AgentLifecycle;
use App\Modules\Agent\Events\AgentPaired;
use App\Modules\Identity\Application\AccountNotices;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Identity\Events\AccountRecoveryCompleted;
use App\Modules\Identity\Events\GuestSessionEnded;
use App\Modules\Proving\Events\RunAccepted;
use App\Modules\Proving\Events\RunQueued;
use App\Modules\Workspace\Application\WorkspaceOwnership;

/**
 * Agent reacts to other modules' events (event-catalogue §2): the
 * `job.available` nudge when a run is queued for it (ADR-010 §5: nudge +
 * pull), the "New agent paired" email (AC-7), revocation when the guest
 * session ends or the account is deleted (a no-op after the tombstone
 * participant, AC-4), reconfirmation after account recovery (RC-4).
 */
final readonly class AgentReactions
{
    public function __construct(
        private AgentLifecycle $lifecycle,
        private AgentBroadcasts $broadcasts,
        private WorkspaceOwnership $workspaces,
        private AccountNotices $notices,
        private Clock $clock,
    ) {}

    public function runQueued(RunQueued $event): void
    {
        if ($event->agentId !== null) {
            $this->broadcasts->toAgent($event->agentId, 'job.available', ['job_id' => EntityId::encode('job', $event->jobId)]);
        }
    }

    /**
     * AC-7, S-1 detective control: "New agent paired" to the account owner
     * with machine name, OS, key fingerprint, time and network, and a link
     * to the agent page that has the Revoke action. Guests have no address.
     */
    public function agentPaired(AgentPaired $event): void
    {
        $owner = $this->workspaces->ownerOf($event->workspaceId);
        if ($owner === null || ! $owner->isUser()) {
            return;
        }
        $this->notices->send($owner->id, 'agent-paired', [
            'machine' => $event->name,
            'os' => $event->os,
            'key_fingerprint' => $event->keyFingerprint,
            'time' => $this->clock->now()->toIso8601ZuluString(),
            'network' => $event->network,
        ], url('/agents/'.EntityId::encode('agt', $event->agentId)));
    }

    /** A browser run accepted: its target was approved on the agent (mirror, display only). */
    public function runAccepted(RunAccepted $event): void
    {
        if ($event->agentId !== null && $event->targetScheme !== null && $event->targetHost !== null && $event->targetPort !== null) {
            $this->lifecycle->targetApproved($event->agentId, $event->targetScheme, $event->targetHost, $event->targetPort);
        }
    }

    public function guestEnded(GuestSessionEnded $event): void
    {
        $workspaceId = $this->workspaces->guestWorkspaceId($event->guestSessionId);
        if ($workspaceId !== null) {
            $this->lifecycle->revokeWorkspace($workspaceId, 'session_ended');
        }
    }

    public function accountDeleted(AccountDeleted $event): void
    {
        $workspaceId = $this->workspaces->userWorkspaceId($event->userId);
        if ($workspaceId !== null) {
            $this->lifecycle->revokeWorkspace($workspaceId, 'account_deleted');
        }
    }

    public function recoveryCompleted(AccountRecoveryCompleted $event): void
    {
        $workspaceId = $this->workspaces->userWorkspaceId($event->userId);
        if ($workspaceId !== null) {
            $this->lifecycle->requireReconfirm($workspaceId);
        }
    }
}
