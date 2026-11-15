<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Modules\Identity\Contracts\BrowserChannel;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Workspace\Domain\Workspace;

/**
 * `private-workspace.{workspaceId}` (event-catalogue §1): the owner
 * principal only; agent status and pairing completion. An agent never
 * subscribes here, not even to its own workspace (rule 2): the browser
 * endpoint only knows users and guests.
 */
final readonly class WorkspaceChannel implements BrowserChannel
{
    public function name(): string
    {
        return 'workspace';
    }

    public function allows(Principal $principal, string $id): bool
    {
        $workspace = Workspace::query()->find($id);

        return $workspace !== null && $workspace->isOwnedBy($principal);
    }
}
