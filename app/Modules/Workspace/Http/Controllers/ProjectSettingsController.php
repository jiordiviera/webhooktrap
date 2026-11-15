<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Contracts\OwnedProject;
use App\Modules\Workspace\Http\ProjectRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S-16 · Project settings (contracts §3.2 `GET /p/{projectKey}/settings/{section}`;
 * slice 2 notes for the mutations). Owner only; a guest owner sees `danger`
 * only (End session now). Anyone else, a deleted project, and a guest asking
 * for an account-only page get the same not-found (TM-13, IA5).
 */
final readonly class ProjectSettingsController
{
    private const array COMPONENTS = ['capture' => 'Settings/Capture', 'ci' => 'Settings/Ci', 'budgets' => 'Settings/Budgets', 'danger' => 'Settings/Danger'];

    public function __construct(private GuestSessions $guests, private Projects $projects) {}

    public function index(Request $request, string $projectKey): RedirectResponse
    {
        $project = $this->owned($request, $projectKey);

        return redirect("/p/{$project->key}/settings/".($project->isGuest ? 'danger' : 'capture'));
    }

    public function show(Request $request, string $projectKey, string $section): Response
    {
        $project = $this->owned($request, $projectKey, accountOnly: $section !== 'danger');
        $props = [
            'section' => $section,
            'sections' => $project->isGuest ? ['danger'] : array_keys(self::COMPONENTS),
            'project' => [
                'key' => $project->key,
                'name' => $project->name,
                'isGuest' => $project->isGuest,
                'captureUrl' => $this->projects->captureUrl($project),
                'captureRetentionDays' => $project->captureRetentionDays,
                'runRetentionDays' => $project->runRetentionDays,
                'defaultVisibility' => $project->defaultVisibility,
            ],
        ];
        if ($section === 'capture') {
            $props['retentionOptions'] = [
                'captureDays' => ProjectRules::ints('workspace.retention.capture_days'),
                'runDays' => ProjectRules::ints('workspace.retention.run_days'),
            ];
        }

        return Inertia::render(self::COMPONENTS[$section], $props);
    }

    public function rename(Request $request, string $projectKey): RedirectResponse
    {
        $project = $this->owned($request, $projectKey, accountOnly: true);
        $data = $request->validate(['name' => ProjectRules::name()], ProjectRules::messages());
        $this->projects->rename($project, (string) $data['name']);

        return back()->with('flash', ['kind' => 'success', 'code' => 'project_renamed', 'message' => (string) $data['name']]);
    }

    public function retention(Request $request, string $projectKey): RedirectResponse
    {
        $project = $this->owned($request, $projectKey, accountOnly: true);
        $data = $request->validate([
            'captureRetentionDays' => ProjectRules::captureDays(),
            'runRetentionDays' => ProjectRules::runDays(),
        ], ProjectRules::messages());
        $this->projects->setRetention($project, (int) $data['captureRetentionDays'], (int) $data['runRetentionDays']);

        return back()->with('flash', ['kind' => 'success', 'code' => 'retention_saved', 'message' => '']);
    }

    /** S-16 Danger · Delete project → `/projects`, which lands on the remaining project or New project. */
    public function destroy(Request $request, string $projectKey): RedirectResponse
    {
        $project = $this->owned($request, $projectKey, accountOnly: true);
        $this->projects->delete($project);

        return redirect('/projects')->with('flash', ['kind' => 'success', 'code' => 'project_deleted', 'message' => $project->name]);
    }

    private function owned(Request $request, string $projectKey, bool $accountOnly = false): OwnedProject
    {
        $project = $this->projects->owned($this->guests->principal($request), $projectKey);
        abort_if($project === null || ($accountOnly && $project->isGuest), 404);

        return $project;
    }
}
