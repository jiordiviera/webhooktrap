<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Agent\Application\Agents;
use App\Modules\Agent\Contracts\AgentView;
use App\Modules\Capture\Application\CaptureRefs;
use App\Modules\Identity\Application\GuestQuotas;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Packs\Application\PackCatalog;
use App\Modules\Proving\Contracts\RunRefusal;
use App\Modules\Proving\Domain\PgArrays;
use App\Modules\Proving\Domain\Run;
use App\Modules\Proving\Domain\RunJob;
use App\Modules\Proving\Domain\Scenarios;
use App\Modules\Proving\Events\RunQueued;
use App\Modules\Workspace\Application\ProjectCounters;
use App\Modules\Workspace\Contracts\OwnedProject;
use Illuminate\Support\Facades\DB;

/**
 * Run creation from the web (contracts §3.2 `POST /p/{projectKey}/runs`,
 * S-10; domain §6): checks the selection (≤ 5 captures of one provider with
 * a pack, bodies kept, DEF-1), the target (an agent of this workspace,
 * confirmed, and a target it declared, TM-50) and the guest quotas without
 * counting them (A-13); then allocates the run number, stores a Queued run
 * and its job for that agent, and nudges the agent after commit.
 */
final readonly class RunLauncher
{
    public const int MAX_EVENTS = 5;

    public function __construct(
        private Clock $clock,
        private CaptureRefs $captures,
        private Agents $agents,
        private PackCatalog $packs,
        private GuestQuotas $guestQuotas,
        private ProjectCounters $counters,
        private RunBroadcasts $broadcasts,
    ) {}

    /**
     * @param  array{from: list<int>, scenarios: list<string>|null, target: array<string, string>, failOn: string|null}  $input
     * @return string the run's public id
     */
    public function start(OwnedProject $project, Principal $principal, array $input): string
    {
        $seqs = array_values(array_unique($input['from']));
        if ($seqs === []) {
            throw new RunRefusal(422, 'empty_selection');
        }
        if (count($seqs) > self::MAX_EVENTS) {
            throw new RunRefusal(422, 'cap');
        }
        $refs = $this->captures->forSeqs($project->id, $seqs);
        if (count($refs) !== count($seqs)) {
            throw new RunRefusal(422, 'unknown_capture');
        }
        $providers = array_values(array_unique(array_map(fn (array $r): ?string => $r['provider'], $refs)));
        if (count($providers) !== 1) {
            throw new RunRefusal(422, 'provider_mix');
        }
        $provider = $providers[0];
        $packVersion = $this->packs->pinnedVersion($provider);
        if ($provider === null || $packVersion === null) {
            throw new RunRefusal(422, 'unknown_provider');
        }
        foreach ($refs as $r) {
            if (! $r['bodyKept']) {
                throw new RunRefusal(422, 'body_not_kept');
            }
        }
        $scenarios = $input['scenarios'] === null ? Scenarios::ALL : Scenarios::ordered($input['scenarios']);
        if ($scenarios === [] || ($input['scenarios'] !== null && count($scenarios) !== count(array_unique($input['scenarios'])))) {
            throw new RunRefusal(422, 'unknown_scenario');
        }
        $failOn = $input['failOn'] ?? 'high';
        if (! in_array($failOn, ['critical', 'high', 'medium', 'never'], true)) {
            throw new RunRefusal(422, 'fail_on');
        }
        $target = $input['target'];
        if (($target['kind'] ?? null) !== 'agent') {
            throw new RunRefusal(422, 'server_runs_disabled');
        }
        $agent = $this->agents->findPublic((string) ($target['agentId'] ?? ''));
        if ($agent === null || $agent->revoked || $agent->workspaceId !== $project->workspaceId) {
            throw new RunRefusal(422, 'no_agent');
        }
        if (! $agent->confirmed || $agent->needsReconfirm) {
            throw new RunRefusal(422, 'agent_needs_confirmation');
        }
        $url = self::declaredTarget($agent, (string) ($target['targetRef'] ?? ''));
        if ($principal->isGuest() && ! $this->guestQuotas->canStartRun($principal->id)) {
            throw new RunRefusal(422, 'quota');
        }

        return DB::transaction(function () use ($project, $principal, $refs, $provider, $packVersion, $scenarios, $failOn, $agent, $url): string {
            $now = $this->clock->now();
            $number = $this->counters->nextRunNumber($project->id);
            $expires = $now->addSeconds((int) config('agent.jobs.accept_within_seconds'));
            $run = new Run;
            $run->forceFill([
                'project_id' => $project->id,
                'number' => $number,
                'source' => 'web',
                'executor' => 'agent',
                'agent_id' => $agent->id,
                'remote' => true,
                'provenance' => ['executor' => 'agent', 'source' => 'web', 'agent_name' => $agent->name],
                'initiated_by_user_id' => $principal->isUser() ? $principal->id : null,
                'initiated_by_guest_session_id' => $principal->isGuest() ? $principal->id : null,
                'status' => 'queued',
                'fail_on' => $failOn,
                'provider' => $provider,
                'pack_id' => $provider,
                'pack_version' => $packVersion,
                'target_scheme' => $url['scheme'],
                'target_host' => $url['host'],
                'target_port' => $url['port'],
                'target_path' => $url['path'],
                'seed' => random_int(1, PHP_INT_MAX),
                'visibility' => 'private',
                'queued_at' => $now,
                'job_expires_at' => $expires,
                'capture_ids' => PgArrays::literal(array_map(fn (array $r): string => $r['id'], $refs)),
                'source_seqs' => PgArrays::literal(array_map(fn (array $r): int => $r['seq'], $refs)),
                'capture_sha256' => PgArrays::literal(array_map(fn (array $r): string => $r['bodySha256'], $refs)),
                'scenarios' => PgArrays::literal($scenarios),
                'levels' => '{1,2}',
            ])->save();
            $job = new RunJob;
            $job->forceFill([
                'run_id' => $run->id,
                'kind' => 'run',
                'agent_id' => $agent->id,
                'expires_at' => $expires,
                'created_at' => $now,
                'capture_ids' => PgArrays::literal(array_map(fn (array $r): string => $r['id'], $refs)),
            ])->save();
            $run->forceFill(['job_id' => $job->id])->save();
            foreach ($scenarios as $i => $name) {
                DB::table('run_scenarios')->insert(['run_id' => $run->id, 'position' => $i + 1, 'name' => $name]);
            }
            RunQueued::dispatch($run->id, $project->id, 'agent', $agent->id, $job->id);
            $this->broadcasts->runsList($project->id);

            return $run->publicId();
        });
    }

    /**
     * The target URL when its scheme, host and port are a target the agent
     * declared locally (TM-50, ADR-010 §6.3); the path is free (≤ 512
     * printable ASCII bytes, §5.11).
     *
     * @return array{scheme: string, host: string, port: int, path: string}
     */
    public static function declaredTarget(AgentView $agent, string $targetRef): array
    {
        $parts = parse_url($targetRef);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $path = (string) ($parts['path'] ?? '/');
        if (isset($parts['query'])) {
            $path .= '?'.$parts['query'];
        }
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || strlen($path) > 512 || preg_match('/^[\x21-\x7e]*$/', $path) !== 1) {
            throw new RunRefusal(422, 'undeclared_target');
        }
        foreach ($agent->targets as $t) {
            if ($t['declared'] && $t['scheme'] === $scheme && strtolower($t['host']) === $host && $t['port'] === $port) {
                return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'path' => $path];
            }
        }
        throw new RunRefusal(422, 'undeclared_target');
    }
}
