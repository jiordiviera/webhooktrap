<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use Closure;
use Illuminate\Http\Request;

/**
 * Shared props owned by Agent (contracts §3.1 `agentPill`), for the app
 * shell pill (S-00). Lazy, and about the principal's own workspace only:
 * anonymous visitors get null.
 */
final readonly class AgentSharedProps
{
    public function __construct(private GuestSessions $guests, private WorkspaceOwnership $workspaces, private Agents $agents) {}

    /** @return array{agentPill: Closure(): (array{state: string, name?: string, version?: string}|null)} */
    public function for(Request $request): array
    {
        return [
            'agentPill' => function () use ($request): ?array {
                // Read only: never creates a user's workspace on a page render.
                $principal = $this->guests->principal($request);
                $workspaceId = match ($principal?->kind) {
                    'user' => $this->workspaces->userWorkspaceId($principal->id),
                    'guest' => $this->workspaces->guestWorkspaceId($principal->id),
                    default => null,
                };

                return $workspaceId === null ? null : $this->agents->pill($workspaceId);
            },
        ];
    }
}
