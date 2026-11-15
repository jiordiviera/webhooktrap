<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Contracts\OwnedProject;
use App\Modules\Workspace\Contracts\ProjectCaptureUrls;
use App\Modules\Workspace\Http\ProjectRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** S-15 · Projects and New project (contracts §3.2 `GET /projects`, `GET/POST /projects/new`). Signed-in users only. */
final readonly class ProjectsController
{
    public function __construct(
        private GuestSessions $guests,
        private Projects $projects,
        private ProjectCaptureUrls $captureUrls,
    ) {}

    /**
     * Shown only with 2+ projects; otherwise straight to the one, or to New
     * project. Also the landing after sign-in and email confirmation (QA
     * F-03), so a flash set for it is kept across this hop.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $projects = $this->projects->of($this->guests->principal($request));
        if ($projects === [] || count($projects) === 1) {
            $request->session()->reflash();

            return redirect($projects === [] ? '/projects/new' : '/p/'.$projects[0]->key);
        }

        $activity = $this->captureUrls->activity(array_map(fn (OwnedProject $p): string => $p->id, $projects));

        return Inertia::render('Projects/Index', [
            'projects' => array_map(fn (OwnedProject $p): array => $this->row($p, $activity[$p->id] ?? null), $projects),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/New', [
            'defaultName' => (string) config('workspace.default_project_name'),
        ]);
    }

    /** → S-03 `waiting` of the new project. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ProjectRules::name()], ProjectRules::messages());
        $principal = $this->guests->principal($request);
        abort_if($principal === null || ! $principal->isUser(), 404);
        $project = $this->projects->create($principal, (string) $data['name']);

        return redirect('/p/'.$project->key);
    }

    /**
     * `ProjectRow` (slice 2 notes). `deliveries` counts stored captures and
     * `lastActivityAt` is the newest capture (slice 3); the last run status
     * belongs to the Proving slice and stays null until then.
     *
     * @param  array{deliveries: int, lastActivityAt: CarbonImmutable|null}|null  $activity
     * @return array<string, mixed>
     */
    private function row(OwnedProject $project, ?array $activity): array
    {
        return [
            'key' => $project->key,
            'name' => $project->name,
            'captureUrl' => $this->projects->captureUrl($project),
            'deliveries' => $activity['deliveries'] ?? 0,
            'lastActivityAt' => ($activity['lastActivityAt'] ?? null)?->utc()->toIso8601ZuluString('millisecond'),
            'lastRunStatus' => null,
            'createdAt' => $project->createdAt->utc()->toIso8601ZuluString('millisecond'),
        ];
    }
}
