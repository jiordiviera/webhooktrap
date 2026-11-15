<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\EntityId;
use App\Modules\Agent\Contracts\AgentView;
use App\Modules\Capture\Application\CaptureRefs;
use App\Modules\Identity\Application\GuestQuotas;
use App\Modules\Identity\Application\PrincipalLabels;
use App\Modules\Proving\Contracts\RunRefusal;
use App\Modules\Proving\Domain\Run;
use App\Modules\Proving\Domain\RunJob;
use App\Modules\Proving\Events\RunAccepted;
use App\Modules\Proving\Events\RunFinished;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Run jobs as an agent sees them (contracts §5.4; TM-35, TM-37). Every
 * operation checks that the job was issued to the authenticated agent; any
 * other job is not found. Accepting starts the run; declining leaves it
 * Couldn't start, or Queued while the person confirms in the terminal.
 */
final readonly class RunJobs
{
    private const array WAITING_REASONS = ['needs_confirmation', 'needs_reconfirm'];

    public function __construct(
        private Clock $clock,
        private Projects $projects,
        private WorkspaceOwnership $workspaces,
        private PrincipalLabels $labels,
        private CaptureRefs $captures,
        private GuestQuotas $guestQuotas,
        private RunBroadcasts $broadcasts,
    ) {}

    /** Run jobs waiting for this agent's accept (the `hello` count). */
    public function pendingCount(string $agentId): int
    {
        return $this->openJobs($agentId)->count();
    }

    /**
     * The oldest job waiting for this agent, in the §5.4 shape, or null.
     *
     * @return array<string, mixed>|null
     */
    public function next(AgentView $agent): ?array
    {
        // AC-8: a job held after a waiting decline is not offered again yet.
        $now = $this->clock->now();
        $job = $this->openJobs($agent->id)
            ->where(fn (Builder $q) => $q->whereNull('run_jobs.offer_after')->orWhere('run_jobs.offer_after', '<=', $now))
            ->orderBy('run_jobs.created_at')->first(['run_jobs.*']);
        if ($job === null || $job->run_id === null) {
            return null;
        }
        $run = Run::query()->find($job->run_id);
        $project = $run === null ? null : $this->projects->byId($run->project_id);
        if ($run === null || $project === null) {
            return null;
        }
        $owner = $this->workspaces->ownerOf($project->workspaceId);
        $selection = [];
        foreach ($run->captureIds() as $i => $captureId) {
            $selection[] = [
                'capture_id' => EntityId::encode('cap', $captureId),
                'seq' => $run->sourceSeqs()[$i] ?? 0,
                'body_sha256' => $run->captureHashes()[$i] ?? '',
            ];
        }

        return [
            'proto' => 1,
            'job_id' => $job->publicId(),
            'kind' => 'run',
            'run_id' => $run->publicId(),
            'run_number' => $run->number,
            'remote' => $run->remote,
            'requester' => ['workspace_label' => $owner === null ? '' : $this->labels->workspaceLabel($owner->kind, $owner->id)],
            'project_key' => $project->key,
            'selection' => $selection,
            'scenarios' => $run->scenarioList(),
            'target_ref' => self::targetRef($run),
            'pack' => ['id' => $run->pack_id, 'version' => $run->pack_version],
            'fail_on' => $run->fail_on,
            'seed' => (string) $run->seed,
            'delivery_count' => $run->delivery_count > 0 ? $run->delivery_count : null,
            'expires_at' => $job->expires_at->toIso8601ZuluString('millisecond'),
        ];
    }

    public function accept(AgentView $agent, string $jobPublicId): void
    {
        DB::transaction(function () use ($agent, $jobPublicId): void {
            [$job, $run] = $this->lockOwn($agent, $jobPublicId);
            $now = $this->clock->now();
            if ($job->accepted_at !== null || ! $job->isLiveAt($now) || $run->status !== 'queued') {
                throw new RunRefusal(409, 'job_unavailable');
            }
            $expires = $now->addSeconds((int) config('agent.jobs.run_within_seconds'));
            $job->forceFill(['accepted_at' => $now, 'expires_at' => $expires])->save();
            $seq = $run->last_seq + 1;
            $run->forceFill([
                'status' => 'running', 'status_reason' => null, 'started_at' => $now,
                'job_expires_at' => $expires, 'last_seq' => $seq,
            ])->save();
            if ($run->initiated_by_guest_session_id !== null) {
                $this->guestQuotas->countAcceptedRun($run->initiated_by_guest_session_id);
            }
            RunAccepted::dispatch($run->id, $run->project_id, $agent->id, $run->initiated_by_guest_session_id,
                $run->target_scheme, $run->target_host, $run->target_port);
            $this->broadcasts->timeline($run->id, 'run.accepted', [
                'run_id' => $run->publicId(), 'seq' => $seq, 'agent_id' => $agent->publicId,
                'delivery_count' => $run->delivery_count > 0 ? $run->delivery_count : null,
                'started_at' => $now->toIso8601ZuluString('millisecond'),
            ]);
            $this->broadcasts->runsList($run->project_id);
        });
    }

    public function decline(AgentView $agent, string $jobPublicId, string $reason): void
    {
        DB::transaction(function () use ($agent, $jobPublicId, $reason): void {
            [$job, $run] = $this->lockOwn($agent, $jobPublicId);
            $now = $this->clock->now();
            if ($job->accepted_at !== null || ! $job->isLiveAt($now) || $run->status !== 'queued') {
                throw new RunRefusal(409, 'job_unavailable');
            }
            if (in_array($reason, self::WAITING_REASONS, true)) {
                // Stays Queued: the UI says "Confirm in your terminal". AC-8:
                // held back from `jobs/next` for a while, so a non-TTY agent
                // does not get the same job straight back and loop.
                $job->forceFill(['offer_after' => $now->addSeconds((int) config('agent.jobs.decline_hold_seconds'))])->save();
                $run->forceFill(['status_reason' => $reason])->save();
                $this->broadcasts->runsList($run->project_id);

                return;
            }
            $job->forceFill(['completed_at' => $now])->save();
            $this->finishWithoutDocument($run, 'couldnt_start', $reason, 2);
        });
    }

    /**
     * `POST /agent/v1/jobs/{jobId}/abort`: the agent accepted, then could not
     * go on (e.g. a fetched capture failed its SHA-256 check). The run ends
     * Aborted with the agent's reason code (RUN-1), without a document.
     */
    public function abort(AgentView $agent, string $jobPublicId, string $reason): void
    {
        DB::transaction(function () use ($agent, $jobPublicId, $reason): void {
            [$job, $run] = $this->lockOwn($agent, $jobPublicId);
            if ($job->accepted_at === null || $job->completed_at !== null || $run->status !== 'running') {
                throw new RunRefusal(409, 'job_unavailable');
            }
            $this->finishWithoutDocument($run, 'aborted', $reason, 2);
        });
    }

    /**
     * Jobs past their deadline (domain §6): a Queued run nobody accepted, or
     * a Running run with no final document, ends Aborted `agent_lost`.
     */
    public function expire(): int
    {
        $ids = RunJob::query()->where('kind', 'run')->whereNull('completed_at')
            ->where('expires_at', '<=', $this->clock->now())->pluck('run_id');
        $count = 0;
        foreach ($ids as $runId) {
            $count += DB::transaction(function () use ($runId): int {
                $run = Run::query()->whereKey((string) $runId)->lockForUpdate()->first();
                if ($run === null || $run->isFinal()) {
                    RunJob::query()->where('run_id', (string) $runId)->whereNull('completed_at')->update(['completed_at' => $this->clock->now()]);

                    return 0;
                }
                $this->finishWithoutDocument($run, 'aborted', 'agent_lost', 2);

                return 1;
            });
        }

        return $count;
    }

    /**
     * `AgentRevoked`: the agent's open runs end Aborted `agent_lost`
     * (event-catalogue §2), or `account_deleted` when the account went.
     */
    public function abortForAgent(string $agentId, string $reason = 'agent_lost'): void
    {
        foreach (Run::query()->where('agent_id', $agentId)->whereIn('status', ['queued', 'running'])->pluck('id') as $runId) {
            $this->abortRun((string) $runId, $reason);
        }
    }

    /**
     * Account deletion, phase 1 (AC-4, domain §2.10), inside the tombstone
     * transaction: every open run of the account's projects ends Aborted
     * `account_deleted` and its jobs are completed (no capture fetch after).
     *
     * @param  list<string>  $projectIds
     */
    public function abortForProjects(array $projectIds): void
    {
        if ($projectIds === []) {
            return;
        }
        foreach (Run::query()->whereIn('project_id', $projectIds)->whereIn('status', ['queued', 'running'])->pluck('id') as $runId) {
            $this->abortRun((string) $runId, 'account_deleted');
        }
        RunJob::query()->whereNull('completed_at')
            ->whereIn('run_id', Run::query()->select('id')->whereIn('project_id', $projectIds))
            ->update(['completed_at' => $this->clock->now()]);
    }

    private function abortRun(string $runId, string $reason): void
    {
        DB::transaction(function () use ($runId, $reason): void {
            $run = Run::query()->whereKey($runId)->lockForUpdate()->first();
            if ($run !== null && ! $run->isFinal()) {
                $this->finishWithoutDocument($run, 'aborted', $reason, 2);
            }
        });
    }

    /**
     * The capture request for a job of this agent (TM-35): only a capture the
     * job references, only while the job is live; logged per agent.
     *
     * @return array<string, mixed>
     */
    public function captureRequest(AgentView $agent, string $jobPublicId, string $capturePublicId): array
    {
        $jobId = RunJob::uuidFromPublicId($jobPublicId);
        $captureId = self::captureUuid($capturePublicId);
        $job = $jobId === null ? null : RunJob::query()->find($jobId);
        if ($job === null || $captureId === null || $job->agent_id !== $agent->id
            || ! $job->isLiveAt($this->clock->now()) || ! in_array($captureId, $job->captureIds(), true)) {
            throw RunRefusal::notFound();
        }
        $request = $this->captures->requestForExecutor($captureId, $job->id, $agent->id, null);
        if ($request === null) {
            throw RunRefusal::notFound();
        }

        return ['proto' => 1, ...$request];
    }

    /** Ends a run that has no final document (declined, job expired, agent lost). */
    public function finishWithoutDocument(Run $run, string $status, string $reason, int $exitCode): void
    {
        $now = $this->clock->now();
        $seq = $run->last_seq + 1;
        $run->forceFill([
            'status' => $status, 'status_reason' => $reason, 'exit_code' => $exitCode,
            'finished_at' => $now, 'last_seq' => $seq, 'provisional_findings' => null,
        ])->save();
        RunJob::query()->where('run_id', $run->id)->whereNull('completed_at')->update(['completed_at' => $now]);
        RunFinished::dispatch($run->id, $run->project_id, $status, $reason, $exitCode, $run->agent_id);
        $this->broadcasts->timeline($run->id, 'run.finished', [
            'run_id' => $run->publicId(), 'seq' => $seq, 'status' => $status, 'reason' => $reason, 'exit_code' => $exitCode,
            'counts' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'info' => 0, 'findings' => 0],
        ]);
        $this->broadcasts->runsList($run->project_id);
    }

    public static function targetRef(Run $run): string
    {
        $scheme = (string) $run->target_scheme;
        $port = (int) $run->target_port;
        $default = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $host = str_contains((string) $run->target_host, ':') ? '['.$run->target_host.']' : (string) $run->target_host;

        return $scheme.'://'.$host.($default ? '' : ':'.$port).$run->target_path;
    }

    public static function captureUuid(string $publicId): ?string
    {
        try {
            [$prefix, $uuid] = EntityId::decode($publicId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $prefix === 'cap' ? $uuid : null;
    }

    /** @return Builder<RunJob> */
    private function openJobs(string $agentId): Builder
    {
        return RunJob::query()->join('runs', 'runs.id', '=', 'run_jobs.run_id')
            ->where('run_jobs.agent_id', $agentId)->where('run_jobs.kind', 'run')
            ->whereNull('run_jobs.accepted_at')->whereNull('run_jobs.completed_at')
            ->where('run_jobs.expires_at', '>', $this->clock->now())
            ->where('runs.status', 'queued');
    }

    /** @return array{0: RunJob, 1: Run} */
    private function lockOwn(AgentView $agent, string $jobPublicId): array
    {
        $jobId = RunJob::uuidFromPublicId($jobPublicId);
        $job = $jobId === null ? null : RunJob::query()->whereKey($jobId)->lockForUpdate()->first();
        if ($job === null || $job->agent_id !== $agent->id || $job->run_id === null) {
            throw RunRefusal::notFound();
        }
        $run = Run::query()->whereKey($job->run_id)->lockForUpdate()->first() ?? throw RunRefusal::notFound();

        return [$job, $run];
    }
}
