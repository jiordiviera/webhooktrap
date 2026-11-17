<?php

declare(strict_types=1);

namespace App\Modules\Proving\Http\Controllers;

use App\Modules\Capture\Application\CaptureRefs;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Proving\Application\RunBroadcasts;
use App\Modules\Proving\Application\RunLauncher;
use App\Modules\Proving\Application\RunPlanner;
use App\Modules\Proving\Application\RunViews;
use App\Modules\Proving\Contracts\RunRefusal;
use App\Modules\Proving\Domain\ExampleRun;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Contracts\OwnedProject;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner run pages (contracts §3.2): `Run/Show` at `/r/{runId}` (same props
 * as `/r/example`, from the database), the runs list S-05, the run draft
 * S-10 and run creation. Owner only; anyone else, an unknown or malformed id
 * and a deleted project get the same not-found (TM-13, IA5).
 */
final readonly class RunPageController
{
    public function __construct(
        private GuestSessions $guests,
        private Projects $projects,
        private RunViews $views,
        private RunLauncher $launcher,
        private RunPlanner $planner,
        private CaptureRefs $captures,
        private ExampleRun $example,
    ) {}

    public function show(Request $request, string $runId, ?string $focus = null): \Symfony\Component\HttpFoundation\Response
    {
        $run = $this->views->owned($this->guests->principal($request), $runId);
        abort_if($run === null, 404);
        $props = $this->views->show($run);
        $props['focus'] = $this->example->focus($focus, ['timeline' => $props['timeline'], 'findings' => $props['findings']]);
        $props['timeline'] = fn (): array => $this->views->timeline($run->refresh());

        $response = Inertia::render('Run/Show', $props)->toResponse($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    /** `GET /p/{projectKey}/runs` → `Runs/Index` (S-05), `runs` live on `project.runs`. */
    public function index(Request $request, string $projectKey): Response
    {
        $project = $this->owned($request, $projectKey);
        $source = $request->query('source');
        $status = $request->query('status');
        $filters = [
            'source' => is_string($source) && in_array($source, ['web', 'cli', 'ci'], true) ? $source : null,
            'status' => is_string($status) && in_array($status, ['queued', 'running', 'couldnt_start', 'aborted', 'findings', 'inconclusive', 'passed'], true) ? $status : null,
        ];

        return Inertia::render('Runs/Index', [
            'project' => ['key' => $project->key, 'name' => $project->name, 'isGuest' => $project->isGuest],
            'runs' => Inertia::live(fn (): array => $this->views->list($project, $filters), RunBroadcasts::EVENT_RUNS, new PrivateChannel('project.'.$project->id), 1000),
            'filters' => $filters,
        ]);
    }

    /** `GET /p/{projectKey}/runs/new?from=01,03,04` or `?group=` → `Runs/Draft` (S-10). */
    public function draft(Request $request, string $projectKey): Response
    {
        $project = $this->owned($request, $projectKey);
        $group = $request->query('group');
        $seqs = is_string($group) && $group !== ''
            ? $this->captures->seqsOfGroup($project->id, mb_substr($group, 0, 200), RunLauncher::MAX_EVENTS)
            : self::seqs($request->query('from'));

        $draft = $this->views->draft($project, $seqs);
        $principal = $this->guests->principal($request);
        // Lazy (partial reloads of the agent props never plan) and computed
        // once for both props; no plan while the selection is refused.
        $plan = null;
        $planned = function () use (&$plan, $draft, $project, $principal, $seqs): array {
            return $plan ??= $draft['refusals'] !== [] || $principal === null
                ? ['preview' => null, 'previewError' => null]
                : $this->planner->draftPreview($project, $principal, $seqs);
        };

        return Inertia::render('Runs/Draft', [
            'project' => ['key' => $project->key, 'name' => $project->name, 'isGuest' => $project->isGuest],
            ...$draft,
            'preview' => fn (): ?array => $planned()['preview'],
            'previewError' => fn (): ?string => $planned()['previewError'],
        ]);
    }

    /** `POST /p/{projectKey}/runs` → redirect `/r/{runId}`; refusals → back with `errors.run`, or 422 JSON. */
    public function store(Request $request, string $projectKey): RedirectResponse|JsonResponse
    {
        $project = $this->owned($request, $projectKey);
        $principal = $this->guests->principal($request);
        abort_if($principal === null, 404);
        $target = $request->input('target');
        $scenarios = $request->input('scenarios');
        try {
            $runId = $this->launcher->start($project, $principal, [
                'from' => self::seqs($request->input('from')),
                'scenarios' => is_array($scenarios) ? array_values(array_map('strval', array_filter($scenarios, 'is_string'))) : null,
                'target' => is_array($target) ? array_map(fn ($v): string => is_scalar($v) ? (string) $v : '', $target) : ['kind' => ''],
                'failOn' => is_string($request->input('failOn')) ? (string) $request->input('failOn') : null,
            ]);
        } catch (RunRefusal $r) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return new JsonResponse(['error' => $r->error], 422);
            }

            return back()->withErrors(['run' => $r->error]);
        }

        return redirect('/r/'.$runId);
    }

    /** `GET /p/{projectKey}/runs/{n}` → redirect `/r/{runId}`. */
    public function byNumber(Request $request, string $projectKey, string $n): RedirectResponse
    {
        $project = $this->owned($request, $projectKey);
        $run = $this->views->byNumber($project, (int) $n);
        abort_if($run === null, 404);

        return redirect('/r/'.$run->publicId());
    }

    private function owned(Request $request, string $projectKey): OwnedProject
    {
        $project = $this->projects->owned($this->guests->principal($request), $projectKey);
        abort_if($project === null, 404);

        return $project;
    }

    /** @return list<int> `01,03,04`, `[1, 3]` or `["01"]`; anything else is dropped. */
    private static function seqs(mixed $from): array
    {
        $items = is_string($from) ? explode(',', $from) : (is_array($from) ? $from : []);
        $out = [];
        foreach (array_slice($items, 0, 20) as $item) {
            if ((is_int($item) && $item > 0) || (is_string($item) && preg_match('/^#?[0-9]{1,9}$/', trim($item)) === 1)) {
                $out[] = (int) ltrim(trim((string) $item), '#');
            }
        }

        return array_values(array_unique(array_filter($out, fn (int $s): bool => $s > 0)));
    }
}
