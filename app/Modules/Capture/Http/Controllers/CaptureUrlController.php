<?php

declare(strict_types=1);

namespace App\Modules\Capture\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Capture\Application\CaptureUrls;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Workspace\Application\Projects;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * S-16 Capture · Rotate (contracts §3.2 `POST /p/{projectKey}/settings/capture/rotate`).
 * User owner only: anyone else, a guest owner and a deleted project get the
 * same not-found (TM-13, IA5). The settings page then shows the new
 * `project.captureUrl`; the old key keeps answering and stores nothing.
 * Throttled per user (A09 S3-12, `capture.rotate_rate`) with the uniform
 * throttle answer: `errors.form = 'throttled'` (JSON: `429` + `Retry-After`).
 */
final readonly class CaptureUrlController
{
    public function __construct(
        private GuestSessions $guests,
        private Projects $projects,
        private CaptureUrls $urls,
        private Throttle $throttle,
        private Clock $clock,
    ) {}

    public function rotate(Request $request, string $projectKey): RedirectResponse|JsonResponse
    {
        $principal = $this->guests->principal($request);
        $project = $this->projects->owned($principal, $projectKey);
        abort_if($principal === null || $project === null || $project->isGuest, 404);

        $limit = (array) config('capture.rotate_rate');
        $key = 'capture:rotate:'.$principal->id;
        if (! $this->throttle->reserve($key, (int) ($limit['max'] ?? 10), (int) ($limit['decay'] ?? 3600))) {
            return $this->throttled($request, $key);
        }
        $url = $this->urls->rotate($project);

        return back()->with('flash', ['kind' => 'success', 'code' => 'capture_url_rotated', 'message' => $url]);
    }

    private function throttled(Request $request, string $key): RedirectResponse|JsonResponse
    {
        $retryAt = $this->throttle->availableAt($key);
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(['error' => 'throttled'], 429, [
                'Retry-After' => (string) max(1, $retryAt->getTimestamp() - $this->clock->now()->getTimestamp()),
            ]);
        }

        return back()->withErrors(['form' => 'throttled']);
    }
}
