<?php

declare(strict_types=1);

namespace App\Modules\Agent\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\LongPollSlots;
use App\Modules\Agent\Domain\AgentRefusal;
use App\Modules\Agent\Domain\TerminalText;
use App\Modules\Agent\Http\AgentApi;
use App\Modules\Proving\Application\RunJobs;
use App\Modules\Proving\Application\RunRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Jobs, capture fetch, observations and the final document (contracts
 * §5.4, §5.5, §5.7). Every endpoint answers `403 needs_reconfirm` until the
 * local confirmation (RE-1, TM-42); Proving checks that the job or run is
 * this agent's (TM-35, TM-37).
 */
final readonly class AgentJobsController
{
    public function __construct(
        private AgentApi $api,
        private RunJobs $jobs,
        private RunRecorder $recorder,
        private LongPollSlots $slots,
        private Clock $clock,
    ) {}

    /**
     * `GET /agent/v1/jobs/next?wait=25`: long-poll inside cross-process slots
     * (TM-40, AC-5): one per agent (a second concurrent poll of the same
     * agent answers 204 at once), at most `per_workspace` per workspace, and
     * the global pool sized against the HTTP workers. A full workspace or
     * global pool answers 503 + `Retry-After`. The workspace label is
     * cleaned for the terminal here, as in `pair` and `hello` (AC-10).
     */
    public function next(Request $request): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request): JsonResponse|Response {
            $agent = AgentApi::confirmedAgent($request);
            $wait = max(0, min((int) config('agent.longpoll.max_wait_seconds'), (int) $request->query('wait', '0')));
            $own = $this->slots->acquireKeyed('agent:'.$agent->id, 1);
            if ($own === null) {
                return new Response('', 204, ['Cache-Control' => 'no-store']);
            }
            $workspace = null;
            $slot = null;
            try {
                $workspace = $this->slots->acquireKeyed('agent-workspace:'.$agent->workspaceId, (int) config('agent.longpoll.per_workspace'));
                $slot = $workspace === null ? null : $this->slots->acquire('agent', LongPollSlots::cap(
                    (int) config('agent.longpoll.slots'), (int) config('agent.longpoll.http_workers'),
                ));
                if ($slot === null) {
                    throw new AgentRefusal(503, 'busy', [], ['Retry-After' => (string) config('agent.longpoll.retry_after_seconds')]);
                }
                $deadline = $this->clock->now()->addSeconds($wait);
                while (true) {
                    $job = $this->jobs->next($agent);
                    if ($job !== null) {
                        $requester = (array) ($job['requester'] ?? []);
                        $label = $requester['workspace_label'] ?? '';
                        $job['requester'] = ['workspace_label' => TerminalText::clean(is_string($label) ? $label : '')];

                        return new JsonResponse($job, 200, ['Cache-Control' => 'no-store']);
                    }
                    if (! $this->clock->now()->lessThan($deadline)) {
                        return new Response('', 204, ['Cache-Control' => 'no-store']);
                    }
                    usleep(max(50, (int) config('agent.longpoll.poll_interval_ms')) * 1000);
                }
            } finally {
                if ($slot !== null) {
                    $this->slots->release($slot);
                }
                if ($workspace !== null) {
                    $this->slots->releaseKeyed($workspace);
                }
                $this->slots->releaseKeyed($own);
            }
        });
    }

    public function accept(Request $request, string $jobId): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request, $jobId): JsonResponse {
            $agent = AgentApi::confirmedAgent($request);
            if ($request->getContent() !== '') {
                $this->api->body($request, 'confirm.request.schema.json');
            }
            $this->jobs->accept($agent, $jobId);

            return AgentApi::ok();
        });
    }

    public function decline(Request $request, string $jobId): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request, $jobId): JsonResponse {
            $agent = AgentApi::agent($request);
            $body = $this->api->body($request, 'decline.request.schema.json');
            $this->jobs->decline($agent, $jobId, (string) $body['reason']);

            return AgentApi::ok();
        });
    }

    public function abort(Request $request, string $jobId): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request, $jobId): JsonResponse {
            $agent = AgentApi::confirmedAgent($request);
            $body = $this->api->body($request, 'abort.request.schema.json');
            $this->jobs->abort($agent, $jobId, (string) $body['reason']);

            return AgentApi::ok();
        });
    }

    public function capture(Request $request, string $jobId, string $captureId): JsonResponse|Response
    {
        return AgentApi::answer(fn (): JsonResponse => AgentApi::ok(
            $this->jobs->captureRequest(AgentApi::confirmedAgent($request), $jobId, $captureId),
        ));
    }

    public function observations(Request $request, string $runId): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request, $runId): JsonResponse {
            $agent = AgentApi::confirmedAgent($request);
            $body = $this->api->body($request, 'observations.request.schema.json');

            return AgentApi::ok(['seq' => $this->recorder->observe($agent, $runId, $body, strlen($request->getContent()))], 202);
        });
    }

    public function final(Request $request, string $runId): JsonResponse|Response
    {
        return AgentApi::answer(function () use ($request, $runId): JsonResponse {
            $agent = AgentApi::confirmedAgent($request);
            $body = $this->api->body($request, 'final.request.schema.json');

            return AgentApi::ok($this->recorder->finish($agent, $runId, $body, hash('sha256', $request->getContent(), true)));
        });
    }
}
