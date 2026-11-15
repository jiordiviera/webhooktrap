<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Contracts;

/**
 * A module whose rows hang off a workspace and must follow a claim (ADR-003
 * amendment 1 §4): called inside the claim transaction, after the projects
 * moved and before the guest workspace is deleted. Tagged
 * `workspace.move-participants`.
 */
interface WorkspaceMoveParticipant
{
    public function moved(string $fromWorkspaceId, string $toWorkspaceId): void;
}
