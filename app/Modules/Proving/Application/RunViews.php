<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Modules\Agent\Application\Agents;
use App\Modules\Agent\Contracts\AgentView;
use App\Modules\Capture\Application\CaptureRefs;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Packs\Application\PackCatalog;
use App\Modules\Proving\Domain\FindingProse;
use App\Modules\Proving\Domain\PgArrays;
use App\Modules\Proving\Domain\Run;
use App\Modules\Proving\Domain\Scenarios;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Contracts\OwnedProject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Props of the run pages (contracts §3.2, §3.3): `Run/Show` for the owner in
 * the same shape as the S-14 example (A07's components), the runs list S-05
 * and the run draft S-10. Every prose string of a finding is rendered here
 * from the pack (TM-16); display values are passed as stored, never
 * formatted (PV-6).
 */
final readonly class RunViews
{
    public function __construct(
        private Clock $clock,
        private Projects $projects,
        private PackCatalog $packs,
        private Agents $agents,
        private CaptureRefs $captures,
    ) {}

    /** The run with this public id when the principal owns its project; null otherwise (uniform not-found). */
    public function owned(?Principal $principal, string $runPublicId): ?Run
    {
        $uuid = Run::uuidFromPublicId($runPublicId);
        $run = $uuid === null ? null : Run::query()->find($uuid);
        if ($run === null || $this->projects->ownedById($principal, $run->project_id) === null) {
            return null;
        }

        return $run;
    }

    public function byNumber(OwnedProject $project, int $number): ?Run
    {
        return Run::query()->where('project_id', $project->id)->where('number', $number)->first();
    }

    public function ownsRunId(Principal $principal, string $runId): bool
    {
        $projectId = Run::query()->whereKey($runId)->value('project_id');

        return is_string($projectId) && $this->projects->ownedById($principal, $projectId) !== null;
    }

    /** @return array<string, mixed> */
    public function header(Run $run): array
    {
        $doc = $run->document ?? [];
        $docRun = (array) ($doc['run'] ?? []);
        $prose = $this->packs->prose($run->pack_id, $run->pack_version);
        $budget = $this->budget($run, $prose);
        $agent = $run->agent_id === null ? null : $this->agents->find($run->agent_id);
        $docAgent = (array) ($docRun['agent'] ?? []);
        $framework = is_array($docRun['framework'] ?? null) ? (string) ($docRun['framework']['name'] ?? '') : '';
        $provenance = ['executor' => (string) ($run->provenance['executor'] ?? $run->executor), 'source' => (string) ($run->provenance['source'] ?? $run->source)];
        if (is_string($run->provenance['agent_name'] ?? null)) {
            $provenance['agentName'] = $run->provenance['agent_name'];
        }
        $duration = $run->started_at !== null && $run->finished_at !== null
            ? (int) $run->started_at->diffInMilliseconds($run->finished_at, true) : null;

        return [
            'id' => $run->publicId(),
            'number' => $run->number,
            'status' => $run->status,
            'reason' => $run->status_reason,
            'exitCode' => $run->exit_code,
            'provenance' => $provenance,
            'agent' => $agent === null ? null : [
                'name' => $agent->name,
                'version' => is_string($docAgent['version'] ?? null) && $docAgent['version'] !== '' ? $docAgent['version'] : $agent->version,
            ],
            'provider' => $run->provider,
            'pack' => ['id' => $run->pack_id, 'version' => $run->pack_version],
            'budget' => $budget,
            'target' => ['scheme' => (string) $run->target_scheme, 'host' => (string) $run->target_host, 'port' => (int) $run->target_port, 'path' => (string) $run->target_path],
            'levels' => $run->levels(),
            'probeEntries' => array_map(fn ($e): array => ['name' => (string) ($e['name'] ?? ''), 'displayMode' => (string) ($e['display_mode'] ?? 'count')], (array) $run->probe_entries),
            'counts' => [
                'critical' => $run->counts_critical, 'high' => $run->counts_high, 'medium' => $run->counts_medium,
                'info' => $run->counts_info, 'findings' => $run->counts_critical + $run->counts_high + $run->counts_medium,
            ],
            'from' => ['seqs' => $run->sourceSeqs()],
            'startedAt' => self::iso($run->started_at),
            'finishedAt' => self::iso($run->finished_at),
            'durationMs' => $duration,
            'notChecked' => self::notChecked((array) ($doc['not_checked'] ?? [])),
            'orderDependent' => (bool) ($docRun['order_dependent'] ?? false),
            'visibility' => $run->visibility,
            'project' => $this->projects->byId($run->project_id)?->name,
            'failOn' => $run->fail_on,
            'framework' => $framework === '' ? null : FindingProse::frameworkName($framework),
            'queuedAt' => self::iso($run->queued_at),
            // "D8 of 17": planned deliveries from the job or the agent's plan
            // (observations seq 1); null until known.
            'deliveryCount' => $run->delivery_count > 0 ? $run->delivery_count : null,
        ];
    }

    /**
     * `Run/Show` props for the owner (contracts §3.2; same shape as `/r/example`).
     *
     * @return array<string, mixed>
     */
    public function show(Run $run): array
    {
        $project = $this->projects->byId($run->project_id);

        return [
            // Owner only: "Runs" and "Run again" links (guests included).
            'project' => $project === null ? null : ['key' => $project->key, 'name' => $project->name, 'isGuest' => $project->isGuest],
            'run' => $this->header($run),
            'scenarios' => $this->scenarios($run),
            'timeline' => $this->timeline($run),
            'findings' => $this->findings($run),
            'access' => 'owner',
            'share' => ['visibility' => $run->visibility, 'sharedBy' => '', 'bodiesHidden' => false, 'includeProbeValues' => false],
            // The client subscribes while the run is not final (event-catalogue §3.3).
            'live' => $run->isFinal() ? null : ['channel' => RunBroadcasts::channel($run->id)],
        ];
    }

    /** @return array{lastSeq: int, status: string, deliveries: list<array<string, mixed>>} */
    public function timeline(Run $run): array
    {
        $refs = $this->eventTypes($run);
        $offending = [];
        foreach (DB::table('findings')->where('run_id', $run->id)->orderBy('n')->get(['n', 'evidence']) as $f) {
            foreach ((array) json_decode((string) $f->evidence, true) as $e) {
                if (($e['role'] ?? null) === 'offending') {
                    $offending[(int) $e['d']] ??= (int) $f->n;
                }
            }
        }
        $rows = [];
        foreach (DB::table('run_deliveries')->where('run_id', $run->id)->orderBy('n')->get() as $d) {
            $signing = ['kind' => (string) $d->signing_kind];
            if ($d->timestamp_offset_s !== null) {
                $signing['timestampOffsetS'] = (int) $d->timestamp_offset_s;
            }
            $sha = $d->response_sha256 === null ? null : Bytea::read($d->response_sha256);
            $state = $d->state === null ? null : json_decode((string) $d->state, true);
            $row = [
                'd' => (int) $d->n,
                'scenario' => (string) $d->scenario,
                'scenarioName' => Scenarios::label((string) $d->scenario),
                'sourceSeq' => $d->source_seq === null ? null : (int) $d->source_seq,
                'mode' => (string) $d->mode,
                'offsetMs' => (int) ($d->offset_ms ?? 0),
                'signing' => $signing,
                'tamper' => $d->tamper === null ? null : json_decode((string) $d->tamper, true),
                'status' => $d->status === null ? null : (int) $d->status,
                'transport' => $d->transport,
                'latencyMs' => $d->latency_ms === null ? null : (int) $d->latency_ms,
                'overBudget' => $d->over_budget === null ? null : (bool) $d->over_budget,
                'result' => $d->result,
                'eventType' => $d->capture_id === null ? null : ($refs[(string) $d->capture_id] ?? null),
                'finding' => $offending[(int) $d->n] ?? null,
            ];
            if ($d->response_bytes !== null) {
                $row['response'] = ['contentType' => $d->response_content_type, 'bytes' => (int) $d->response_bytes, 'sha256' => $sha === null ? '' : bin2hex($sha)];
            }
            if (is_array($state)) {
                $row['state'] = [
                    'readings' => array_values(array_map(fn (array $r): array => [
                        'name' => (string) $r['name'], 'changed' => $r['changed'] ?? null, 'matchesExpected' => $r['matches_expected'] ?? null,
                        'flagged' => (bool) ($r['flagged'] ?? false), 'display' => (array) ($r['display'] ?? []),
                    ], (array) ($state['readings'] ?? []))),
                    'failed' => (bool) ($state['failed'] ?? false),
                    'unsettled' => (bool) ($state['unsettled'] ?? false),
                ];
            }
            $rows[] = $row;
        }

        return ['lastSeq' => $run->last_seq, 'status' => $run->status, 'deliveries' => $rows];
    }

    /** @return list<array<string, mixed>> */
    public function scenarios(Run $run): array
    {
        $deliveries = DB::table('run_deliveries')->where('run_id', $run->id)->orderBy('n')->get(['n', 'scenario', 'status', 'transport']);
        $findings = $this->findings($run);
        $cells = [];
        foreach (DB::table('run_scenarios')->where('run_id', $run->id)->orderBy('position')->get() as $s) {
            $name = (string) $s->name;
            $mine = $deliveries->where('scenario', $name);
            $cellFindings = array_values(array_filter($findings, fn (array $f): bool => in_array($name, $f['scenarioKeys'], true)));
            $reading = $s->reading === null ? null : json_decode((string) $s->reading, true);
            $readingText = '';
            if (is_array($reading)) {
                $display = (array) ($reading['display'] ?? []);
                $readingText = ($display['before'] ?? '–').' → '.($display['after'] ?? '–');
            }
            $statuses = $mine->map(fn (object $d): string => $d->status !== null ? (string) $d->status : (string) ($d->transport ?? '—'))->values()->all();
            $line = count(array_unique($statuses)) === 1 && count($statuses) > 1 ? $statuses[0].' × '.count($statuses) : implode(', ', $statuses);
            if (is_array($reading)) {
                $line .= ($line === '' ? '' : ' · ').($reading['probe'] ?? '').' '.$readingText;
            }
            $cells[] = [
                'key' => $name,
                'name' => Scenarios::label($name),
                'result' => $s->result,
                'reason' => $s->reason,
                'deliveries' => array_values($mine->pluck('n')->map(fn (mixed $n): int => (int) $n)->all()),
                'findings' => array_map(fn (array $f): array => ['n' => $f['n'], 'severity' => $f['severity']], $cellFindings),
                'counted' => count(array_filter($cellFindings, fn (array $f): bool => $f['severity'] !== 'info')),
                'reading' => $readingText,
                'line' => $line,
            ];
        }

        return $cells;
    }

    /**
     * Findings with server-rendered prose (TM-16). While Running, the
     * provisional findings are listed by severity, then smallest offending
     * delivery (FND-6 note), with `provisional: true`.
     *
     * @return list<array<string, mixed>>
     */
    public function findings(Run $run): array
    {
        $prose = $this->packs->prose($run->pack_id, $run->pack_version);
        $budget = $this->budget($run, $prose);
        $rows = [];
        if ($run->document !== null) {
            foreach (DB::table('findings')->where('run_id', $run->id)->orderBy('n')->get() as $f) {
                $rows[] = [
                    'n' => (int) $f->n, 'provisional' => false, 'type' => (string) $f->type, 'severity' => (string) $f->severity,
                    'level' => (int) $f->level, 'event_type' => $f->event_type, 'scenarios' => PgArrays::strings($f->scenarios),
                    'evidence' => (array) json_decode((string) $f->evidence, true), 'params' => (array) json_decode((string) $f->params, true),
                    'measured' => (array) json_decode((string) ($f->measured ?? '[]'), true), 'likely_cause_rule' => $f->likely_cause_rule,
                    'framework' => $f->framework, 'fingerprint' => (string) $f->fingerprint,
                ];
            }
        } else {
            $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'info' => 3];
            $provisional = array_values((array) $run->provisional_findings);
            usort($provisional, function (array $a, array $b) use ($rank): int {
                $first = fn (array $f): int => (int) min(array_merge([999], array_map(fn ($e): int => (int) $e['d'], array_filter((array) $f['evidence'], fn ($e): bool => ($e['role'] ?? '') === 'offending'))));

                return [$rank[$a['severity']] ?? 9, $first($a)] <=> [$rank[$b['severity']] ?? 9, $first($b)];
            });
            foreach ($provisional as $i => $f) {
                $off = array_values(array_filter((array) $f['evidence'], fn ($e): bool => ($e['role'] ?? '') === 'offending'));
                $rows[] = [
                    'n' => $i + 1, 'provisional' => true, 'type' => (string) $f['type'], 'severity' => (string) $f['severity'],
                    'level' => in_array($f['type'], ['accepts_tampered', 'accepts_unsigned', 'accepts_stale', 'server_error', 'non_2xx_on_valid', 'error_on_invalid', 'over_budget'], true) ? 1 : 2,
                    'event_type' => $f['event_type'] ?? null, 'scenarios' => [], 'evidence' => (array) $f['evidence'],
                    'params' => $off === [] ? [] : ['d' => (int) $off[0]['d']], 'measured' => [],
                    'likely_cause_rule' => null, 'framework' => null, 'fingerprint' => '',
                ];
            }
        }
        $seen = $this->seenBefore($run, array_values(array_filter(array_column($rows, 'fingerprint'))));

        $out = [];
        foreach ($rows as $f) {
            $text = $prose === null ? null : FindingProse::render($prose, [
                'type' => $f['type'], 'event_type' => $f['event_type'], 'scenarios' => $f['scenarios'],
                'evidence' => array_values(array_map(fn ($e): array => ['d' => (int) $e['d'], 'role' => (string) $e['role']], $f['evidence'])),
                'params' => $f['params'], 'measured' => array_values($f['measured']),
                'likely_cause_rule' => $f['likely_cause_rule'], 'framework' => $f['framework'],
            ], $budget);
            $out[] = [
                'n' => $f['n'],
                'provisional' => $f['provisional'],
                'type' => $f['type'],
                'severity' => $f['severity'],
                'level' => $f['level'],
                'levelLabel' => $f['level'] === 1 ? 'Level 1 · transport' : 'Level 2 · state',
                'title' => $text['title'] ?? $f['type'],
                'eventType' => $f['event_type'],
                'scenarios' => array_map(Scenarios::label(...), $f['scenarios']),
                'scenarioKeys' => $f['scenarios'],
                'evidence' => array_values(array_map(fn ($e): array => ['d' => (int) $e['d'], 'role' => (string) $e['role']], $f['evidence'])),
                'what' => $text['what'] ?? '',
                'why' => $text['why'] ?? '',
                'citation' => $text['citation'] ?? null,
                'fix' => ['text' => $text['fix'] ?? '', 'framework' => $text['snippetFor'] ?? null, 'snippet' => $text['snippet'] ?? null],
                'likelyCause' => ($text['likelyCause'] ?? null) === null ? null : ['rule' => (string) $f['likely_cause_rule'], 'text' => (string) $text['likelyCause']],
                'fingerprint' => $f['fingerprint'],
                'seenBefore' => $f['fingerprint'] !== '' && in_array($f['fingerprint'], $seen, true),
                'caption' => null,
            ];
        }

        return $out;
    }

    /**
     * S-05 runs list rows, newest first.
     *
     * @param  array{source: string|null, status: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function list(OwnedProject $project, array $filters): array
    {
        $runs = Run::query()->where('project_id', $project->id)
            ->when($filters['source'] !== null, fn ($q) => $q->where('source', $filters['source']))
            ->when($filters['status'] !== null, fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('number')->limit(200)->get();

        return array_values($runs->map(fn (Run $run): array => [
            'id' => $run->publicId(),
            'number' => $run->number,
            'status' => $run->status,
            'reason' => $run->status_reason,
            'source' => $run->source,
            'provenance' => array_filter(['executor' => $run->provenance['executor'] ?? $run->executor, 'source' => $run->provenance['source'] ?? $run->source, 'agentName' => $run->provenance['agent_name'] ?? null], fn ($v) => $v !== null),
            'provider' => $run->provider,
            'providerName' => $this->packs->providerName($run->provider),
            'target' => ['scheme' => (string) $run->target_scheme, 'host' => (string) $run->target_host, 'port' => (int) $run->target_port, 'path' => (string) $run->target_path],
            'counts' => ['critical' => $run->counts_critical, 'high' => $run->counts_high, 'medium' => $run->counts_medium, 'info' => $run->counts_info,
                'findings' => $run->counts_critical + $run->counts_high + $run->counts_medium],
            'from' => ['seqs' => $run->sourceSeqs()],
            'queuedAt' => self::iso($run->queued_at),
            'startedAt' => self::iso($run->started_at),
            'finishedAt' => self::iso($run->finished_at),
        ])->all());
    }

    /**
     * S-10 draft props: the selection, declared targets of the workspace's
     * confirmed agents (TM-50), the agent summary and what would refuse the run.
     *
     * @param  list<int>  $seqs
     * @return array<string, mixed>
     */
    public function draft(OwnedProject $project, array $seqs): array
    {
        $refs = $this->captures->forSeqs($project->id, $seqs);
        $selection = array_map(fn (array $r): array => [
            'seq' => $r['seq'], 'id' => $r['publicId'], 'provider' => $r['provider'], 'providerName' => $r['providerName'],
            'eventType' => $r['eventType'], 'receivedAt' => $r['receivedAt'], 'bodySha256' => $r['bodySha256'],
            'bodyKept' => $r['bodyKept'], 'bodySize' => $r['bodySize'], 'signature' => $r['signature'],
        ], $refs);
        $refusals = [];
        if ($seqs === []) {
            $refusals[] = 'empty_selection';
        }
        if (count($seqs) > RunLauncher::MAX_EVENTS) {
            $refusals[] = 'cap';
        }
        if (count($refs) !== count(array_unique($seqs))) {
            $refusals[] = 'unknown_capture';
        }
        $providers = array_unique(array_map(fn (array $r): ?string => $r['provider'], $refs));
        if (count($providers) > 1) {
            $refusals[] = 'provider_mix';
        } elseif ($refs !== [] && $this->packs->pinnedVersion($refs[0]['provider']) === null) {
            $refusals[] = 'unknown_provider';
        }
        if (array_filter($refs, fn (array $r): bool => ! $r['bodyKept']) !== []) {
            $refusals[] = 'body_not_kept';
        }

        $agents = $this->agents->ofWorkspace($project->workspaceId);
        $now = $this->clock->now();
        $targets = [];
        foreach ($agents as $agent) {
            if (! $agent->confirmed || $agent->needsReconfirm) {
                continue;
            }
            foreach ($agent->targets as $t) {
                if ($t['declared']) {
                    $default = ($t['scheme'] === 'http' && $t['port'] === 80) || ($t['scheme'] === 'https' && $t['port'] === 443);
                    $origin = $t['scheme'].'://'.$t['host'].($default ? '' : ':'.$t['port']);
                    $targets[] = ['kind' => 'agent', 'agentId' => $agent->publicId, 'targetRef' => $origin,
                        'display' => $agent->name.' · '.$origin, 'declared' => true, 'approvedOnAgent' => $t['approved']];
                }
            }
        }
        $summary = $agents === [] ? null : self::summary($agents[0], $now);

        return [
            'selection' => $selection,
            'preview' => null,
            'refusals' => $refusals,
            'targets' => $targets,
            'agent' => $summary,
            'agents' => array_map(fn (AgentView $a): array => self::summary($a, $now), $agents),
            'pairing' => null,
            'scenarios' => array_map(fn (string $s): array => ['key' => $s, 'name' => Scenarios::label($s)], Scenarios::ALL),
            'failOn' => 'high',
        ];
    }

    /** @return array{id: string, name: string, os: string, keyFingerprint: string, state: string, version: string} */
    public static function summary(AgentView $agent, CarbonImmutable $now): array
    {
        return ['id' => $agent->publicId, 'name' => $agent->name, 'os' => (string) $agent->os,
            'keyFingerprint' => $agent->keyFingerprint, 'state' => $agent->displayState($now), 'version' => $agent->version];
    }

    /**
     * @param  array{name: string, text: array<string, mixed>, citations: array<string, mixed>, tolerance_s: int, budget: array{ms: int, label: string}|null}|null  $prose
     * @return array{ms: int, source: string, label: string}
     */
    private function budget(Run $run, ?array $prose): array
    {
        $packBudget = $prose['budget'] ?? null;
        $docBudget = (array) (($run->document['run'] ?? [])['budget'] ?? []);
        $ms = is_int($docBudget['ms'] ?? null) ? $docBudget['ms'] : ($packBudget['ms'] ?? 10000);
        if ($packBudget !== null && $packBudget['ms'] === $ms) {
            return ['ms' => $ms, 'source' => 'pack', 'label' => $packBudget['label']];
        }
        $seconds = $ms % 1000 === 0 ? (string) intdiv($ms, 1000) : number_format($ms / 1000, 1);

        return ['ms' => $ms, 'source' => 'config', 'label' => $seconds.' s budget · your configuration'];
    }

    /**
     * @param  list<string>  $fingerprints
     * @return list<string>
     */
    private function seenBefore(Run $run, array $fingerprints): array
    {
        if ($fingerprints === []) {
            return [];
        }

        return array_values(array_map('strval', DB::table('findings')->join('runs', 'runs.id', '=', 'findings.run_id')
            ->where('findings.project_id', $run->project_id)->whereIn('findings.fingerprint', $fingerprints)
            ->where('runs.number', '<', $run->number)->distinct()->pluck('findings.fingerprint')->all()));
    }

    /** @return array<string, string|null> capture id => event type */
    private function eventTypes(Run $run): array
    {
        $out = [];
        foreach ($this->captures->forSeqs($run->project_id, $run->sourceSeqs()) as $r) {
            $out[$r['id']] = $r['eventType'];
        }

        return $out;
    }

    /**
     * RUN-7 lines, as the engine words them (`agent/render/text.go`).
     *
     * @param  array<mixed>  $items
     * @return list<string>
     */
    private static function notChecked(array $items): array
    {
        $out = [];
        foreach ($items as $n) {
            $types = array_values(array_map('strval', (array) ($n['object_types'] ?? [])));
            $line = match ($n['code'] ?? null) {
                'no_probe' => 'state, because there is no probe',
                'uncovered_objects' => FindingProse::andList($types).' state, which no probe reads',
                'no_reset' => 'state between scenarios, because no reset ran',
                'side_effects' => 'side effects outside your database, such as emails',
                default => null,
            };
            if ($line !== null) {
                $out[] = $line;
            }
        }

        return $out;
    }

    private static function iso(?CarbonImmutable $at): ?string
    {
        return $at?->toIso8601ZuluString('millisecond');
    }
}
