<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Application;

use App\Modules\Identity\Application\GuestSessions;
use Closure;
use Illuminate\Http\Request;

/**
 * Shared props owned by Workspace (contracts §3.1 `currentProject`; slice 2
 * notes `projectSwitcher`), for the app shell (S-00): the project switcher
 * and the URL chip. Lazy: computed only when a page renders, and only for
 * the live owner, so a not-found answer carries nothing.
 */
final readonly class WorkspaceSharedProps
{
    public function __construct(private GuestSessions $guests, private Projects $projects) {}

    /** @return array<string, Closure> */
    public function for(Request $request): array
    {
        $props = [];
        $key = $request->route('projectKey');
        if (is_string($key)) {
            $props['currentProject'] = function () use ($request, $key): ?array {
                $project = $this->projects->owned($this->guests->principal($request), $key);

                return $project === null ? null : [
                    'key' => $project->key,
                    'name' => $project->name,
                    'captureUrl' => $this->projects->captureUrl($project),
                ];
            };
        }
        if ($request->user() !== null) {
            $props['projectSwitcher'] = fn (): array => array_map(
                fn ($p): array => ['key' => $p->key, 'name' => $p->name],
                $this->projects->of($this->guests->principal($request)),
            );
        }

        return $props;
    }
}
