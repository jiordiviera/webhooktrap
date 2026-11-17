<?php

declare(strict_types=1);

namespace App\Modules\Capture\Http\Controllers;

use App\Modules\Capture\Application\CaptureFixtures;
use App\Modules\Capture\Application\CaptureStream;
use App\Modules\Capture\Domain\StreamQuery;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Packs\Application\PackCatalog;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use App\Modules\Workspace\Contracts\OwnedProject;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as BytesResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * S-03 capture stream, S-04 delivery detail and the raw body download
 * (contracts §3.2; slice 4 notes). Owner only: anyone else, a deleted
 * project and an unknown capture number get the same not-found (TM-13, IA5).
 *
 * Live updates follow ADR-004 amendment 1 with Inertia Live Props: the
 * stream's `captures.latest`, `captures.stats`, `groups` and the guest
 * `meter` listen for `project.captures` on `private-project.{projectId}`;
 * `captureUrl` listens for `project.capture_url`. The client reloads them by
 * partial reload; nothing in a broadcast carries capture data.
 */
final readonly class CaptureStreamController
{
    public const string EVENT_CAPTURES = 'project.captures';

    public const string EVENT_CAPTURE_URL = 'project.capture_url';

    /** The last onboarding tab (S-03 Waiting): a plain request, no pack. */
    private const array CURL_TAB = ['id' => 'curl', 'name' => 'curl'];

    public function __construct(
        private GuestSessions $guests,
        private Projects $projects,
        private WorkspaceOwnership $ownership,
        private CaptureStream $stream,
        private PackCatalog $packs,
        private CaptureFixtures $fixtures,
    ) {}

    /** `GET /p/{projectKey}` → `Capture/Stream`. */
    public function index(Request $request, string $projectKey): Response|RedirectResponse
    {
        $project = $this->projects->owned($this->guests->principal($request), $projectKey);
        if ($project === null) {
            // A guest whose session just expired gets the expired page, not "not found" (R59).
            $stale = $this->guests->cookieSessionId();
            if ($stale !== null && $this->guests->currentId() === null && $this->ownership->projectKeyOfGuest($stale) === $projectKey) {
                return redirect('/expired');
            }
            abort(404);
        }

        $query = StreamQuery::from($request->query());
        $channel = self::channel($project);
        $throttle = (int) config('capture.live_throttle_ms');
        $id = $project->id;

        $props = [
            'project' => ['key' => $project->key, 'name' => $project->name, 'isGuest' => $project->isGuest],
            'captureUrl' => Inertia::live(fn (): ?string => $this->projects->captureUrl($project), self::EVENT_CAPTURE_URL, $channel),
            'query' => [
                'sel' => $query->sel,
                'tab' => $query->tab,
                'view' => $query->view ?? ($this->stream->groupingAvailable($id) ? 'grouped' : 'flat'),
                'q' => $query->q,
                'group' => $query->group,
            ],
            'captures' => [
                'latest' => Inertia::live(fn (): array => $this->stream->latest($id, $query), self::EVENT_CAPTURES, $channel, $throttle),
                'stats' => Inertia::live(fn (): array => $this->stream->stats($id, $query), self::EVENT_CAPTURES, $channel, $throttle),
                'older' => Inertia::optional(fn (): array => $query->before === null
                    ? ['rows' => [], 'nextBefore' => null]
                    : $this->stream->older($id, $query, $query->before)),
            ],
            'groups' => Inertia::defer(fn (): array => $this->stream->groups($id, $query))->live(self::EVENT_CAPTURES, $channel, $throttle),
            // `?sel=` or, by default, the newest capture (S-03 "Default selection").
            'selected' => function () use ($id, $query): ?array {
                $seq = $query->sel ?? $this->stream->newestSeq($id);

                return $seq === null ? null : $this->stream->detail($id, $seq);
            },
            // Onboarding tabs and filters (S-03): every provider with a pack, in
            // detection order, then curl; copy lives with the client.
            'providers' => [...$this->packs->providers(), self::CURL_TAB],
        ];

        // The guest meter reloads with the stream (event-catalogue §3.1).
        if ($project->isGuest) {
            $props['meter'] = Inertia::live(function (): ?array {
                $guest = $this->guests->current();

                return $guest === null ? null : $this->guests->meter($guest);
            }, self::EVENT_CAPTURES, $channel, $throttle);
        }

        return Inertia::render('Capture/Stream', $props);
    }

    /** `GET /p/{projectKey}/d/{n}` → `Capture/Detail`. */
    public function show(Request $request, string $projectKey, string $n): Response
    {
        [$project, $seq] = $this->ownedCapture($request, $projectKey, $n);
        $detail = $this->stream->detail($project->id, $seq);
        abort_if($detail === null, 404);
        $around = $this->stream->neighbours($project->id, $seq);

        return Inertia::render('Capture/Detail', [
            'project' => ['key' => $project->key, 'name' => $project->name, 'isGuest' => $project->isGuest],
            'capture' => $detail,
            'prev' => $around['prev'],
            'next' => $around['next'],
            'tab' => StreamQuery::from($request->query())->tab,
        ]);
    }

    /**
     * `GET /p/{projectKey}/d/{n}/raw`: the exact kept bytes, never rendered
     * inline (ADR-005 amendment 1 §7, TM-06): a fixed binary type, an
     * attachment, `nosniff`, a sandboxing CSP, no caching. Not found when the
     * body was not kept.
     */
    public function raw(Request $request, string $projectKey, string $n): HttpResponse
    {
        [$project, $seq] = $this->ownedCapture($request, $projectKey, $n);
        $bytes = $this->stream->rawBody($project->id, $seq);
        abort_if($bytes === null, 404);

        return new BytesResponse($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => sprintf('attachment; filename="delivery-%02d.bin"', $seq),
            'Content-Length' => (string) strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; frame-ancestors 'none'",
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * `GET /p/{projectKey}/d/{n}/fixture`: the capture as a fixture file for
     * `webhooktrap test` (issue #15). Same download headers as the raw body.
     * Not found when the capture has no fixture (body not kept, no provider,
     * over 5 MiB or not UTF-8 text).
     */
    public function fixture(Request $request, string $projectKey, string $n): HttpResponse
    {
        [$project, $seq] = $this->ownedCapture($request, $projectKey, $n);
        $file = $this->fixtures->file($project->id, $seq);
        abort_if($file === null, 404);

        return new BytesResponse($file['json'], 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $file['filename']),
            'Content-Length' => (string) strlen($file['json']),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; frame-ancestors 'none'",
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** @return array{0: OwnedProject, 1: int} */
    private function ownedCapture(Request $request, string $projectKey, string $n): array
    {
        $project = $this->projects->owned($this->guests->principal($request), $projectKey);
        $seq = StreamQuery::seq($n);
        abort_if($project === null || $seq === null, 404);

        return [$project, $seq];
    }

    public static function channel(OwnedProject $project): PrivateChannel
    {
        return new PrivateChannel('project.'.$project->id);
    }
}
