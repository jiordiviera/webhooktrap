<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http\Controllers;

use App\Modules\Agent\Application\Agents;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Agent pages (contracts §3.2): `/agents`, `/agents/{idOrName}` and
 * `/agents/pair` for users, `/p/{projectKey}/agent` for a guest owner. The
 * list is live on the workspace channel (`workspace.agents`). `pairing` is
 * null on every page: the page asks `POST /pairing-codes` for a code, so a
 * reload or prefetch never rotates a code someone already copied. Revoke is
 * `DELETE /agents/{agentId}`. Anything not owned is the same not-found.
 */
final readonly class AgentPageController
{
    public function __construct(
        private GuestSessions $guests,
        private WorkspaceOwnership $workspaces,
        private Projects $projects,
        private Agents $agents,
    ) {}

    /** `GET /agents` → `Agents/Index`. */
    public function index(Request $request): Response
    {
        $workspaceId = $this->userWorkspace($request);

        return Inertia::render('Agents/Index', [
            'agents' => Inertia::live(fn (): array => $this->agents->rows($workspaceId), 'workspace.agents', new PrivateChannel('workspace.'.$workspaceId), 1000),
            'pairing' => null,
        ]);
    }

    /** `GET /agents/pair` (`?replace=agt_…` preselects the agent to replace) → `Agents/Pair`. */
    public function pair(Request $request): Response
    {
        $workspaceId = $this->userWorkspace($request);
        $replace = $request->query('replace');
        $agents = $this->agents->rows($workspaceId);
        $ids = array_column($agents, 'id');

        return Inertia::render('Agents/Pair', [
            'agents' => $agents,
            'pairing' => null,
            'replace' => is_string($replace) && in_array($replace, $ids, true) ? $replace : null,
        ]);
    }

    /** `GET /agents/{idOrName}` → `Agents/Show`. */
    public function show(Request $request, string $agent): Response
    {
        $detail = $this->agents->detail($this->userWorkspace($request), $agent);
        abort_if($detail === null, 404);

        return Inertia::render('Agents/Show', ['agent' => $detail]);
    }

    /** `GET /p/{projectKey}/agent` → `Agent/Guest`; a user owner goes to `/agents`. */
    public function guest(Request $request, string $projectKey): Response|RedirectResponse
    {
        $project = $this->projects->owned($this->guests->principal($request), $projectKey);
        abort_if($project === null, 404);
        if (! $project->isGuest) {
            return redirect('/agents');
        }

        return Inertia::render('Agent/Guest', [
            'project' => ['key' => $project->key, 'name' => $project->name, 'isGuest' => true],
            'agent' => Inertia::live(fn (): ?array => $this->agents->guestDetail($project->workspaceId), 'workspace.agents', new PrivateChannel('workspace.'.$project->workspaceId), 1000),
            'pairing' => null,
        ]);
    }

    private function userWorkspace(Request $request): string
    {
        $principal = $this->guests->principal($request);
        abort_if($principal === null || ! $principal->isUser(), 404);

        return $this->workspaces->userWorkspaceId($principal->id) ?? abort(404);
    }
}
