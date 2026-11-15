<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Modules\Agent\Contracts\AgentView;
use App\Modules\Identity\Application\GuestQuotas;
use App\Modules\Packs\Application\PackCatalog;
use App\Modules\Proving\Contracts\RunRefusal;
use App\Modules\Proving\Domain\DisplayValues;
use App\Modules\Proving\Domain\FinalDocument;
use App\Modules\Proving\Domain\Run;
use App\Modules\Proving\Domain\RunJob;
use App\Modules\Proving\Domain\Scenarios;
use App\Modules\Proving\Events\RunFinished;
use App\Modules\Proving\Events\RunObservationsRecorded;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Observation intake and the final document (contracts §5.5, §5.7; domain
 * §6; TM-37). Writes only for runs assigned to the authenticated agent;
 * batches are idempotent on `seq`; display values pass PV-11 before
 * storage; the final document is first-write-wins on its SHA-256, checked
 * for Level 1 consistency only (S-5), and its findings are stored as
 * structured fields whose prose the server renders (TM-16).
 */
final readonly class RunRecorder
{
    public function __construct(
        private Clock $clock,
        private PackCatalog $packs,
        private GuestQuotas $guestQuotas,
        private RunBroadcasts $broadcasts,
    ) {}

    /**
     * @param  array<string, mixed>  $batch  schema-valid observations body
     */
    public function observe(AgentView $agent, string $runPublicId, array $batch, int $bytes = 0): int
    {
        $seq = (int) $batch['seq'];

        DB::transaction(function () use ($agent, $runPublicId, $batch, $seq, $bytes): void {
            $run = $this->lockOwn($agent, $runPublicId);
            if ($run->isFinal()) {
                throw new RunRefusal(409, 'run_final');
            }
            if ($run->status !== 'running') {
                throw new RunRefusal(409, 'not_running');
            }
            // TM-37, AC-12: batches relative to the planned deliveries, with a
            // hard ceiling; total bytes per run capped.
            $cap = min(
                (int) config('agent.observations.max_batches'),
                max(1, $run->delivery_count ?: 50) * 4 + (int) config('agent.observations.extra_batches'),
            );
            if ($seq > $cap) {
                throw new RunRefusal(422, 'seq_out_of_range');
            }
            if (DB::table('run_events_inbox')->where('run_id', $run->id)->where('seq', $seq)->exists()) {
                return; // a retry: already recorded
            }
            if ($run->observation_bytes + $bytes > (int) config('agent.observations.max_bytes_per_run')) {
                throw new RunRefusal(413, 'observations_exceeded');
            }
            $this->checkProvisional($run, (array) $batch['findings_provisional']);
            DB::table('run_events_inbox')->insert(['run_id' => $run->id, 'seq' => $seq, 'received_at' => $this->clock->now()]);
            $run->observation_bytes += $bytes;
            if ($seq === 1) {
                $this->applyPlan($run, $batch);
            }
            $from = $run->last_seq + 1;
            $timeline = $run->last_seq;

            foreach ((array) $batch['deliveries'] as $d) {
                $timeline++;
                $this->storeDelivery($run, (array) $d, $timeline);
                $state = is_array($d['state'] ?? null) ? $d['state'] : null;
                $readings = (array) ($state['readings'] ?? []);
                $this->broadcasts->timeline($run->id, 'run.delivery', [
                    'run_id' => $run->publicId(), 'seq' => $timeline, 'd' => (int) $d['n'], 'scenario' => (string) $d['scenario'],
                    'phase' => (string) $d['phase'], 'status' => $d['status'] ?? null, 'transport' => $d['transport'] ?? null,
                    'latency_ms' => $d['latency_ms'] ?? null, 'over_budget' => $d['over_budget'] ?? null,
                    'state_changed' => $state === null ? null : collect($readings)->contains(fn ($r): bool => ($r['changed'] ?? false) === true),
                    'flagged' => $state === null ? null : collect($readings)->contains(fn ($r): bool => ($r['flagged'] ?? false) === true),
                    'result' => $d['result'] ?? null,
                ]);
            }
            foreach ((array) $batch['scenarios'] as $s) {
                $timeline++;
                $this->storeScenario($run, (string) $s['name'], $s['result'] ?? null, $s['reason'] ?? null, null);
                $this->broadcasts->timeline($run->id, 'run.scenario', [
                    'run_id' => $run->publicId(), 'seq' => $timeline, 'scenario' => (string) $s['name'],
                    'result' => $s['result'] ?? null, 'reason' => $s['reason'] ?? null,
                ]);
            }
            $provisional = $run->provisional_findings ?? [];
            foreach ((array) $batch['findings_provisional'] as $f) {
                $timeline++;
                $key = (string) $f['key'];
                $entry = [
                    'type' => (string) $f['type'], 'severity' => (string) $f['severity'],
                    'event_type' => is_string($f['event_type'] ?? null) ? $f['event_type'] : null,
                    'evidence' => array_values(array_map(fn ($e): array => ['d' => (int) $e['d'], 'role' => (string) $e['role']], (array) $f['evidence'])),
                ];
                $provisional[$key] = $entry;
                $this->broadcasts->timeline($run->id, 'run.finding', ['run_id' => $run->publicId(), 'seq' => $timeline, 'key' => $key, ...$entry]);
            }
            $run->forceFill(['last_seq' => $timeline, 'provisional_findings' => $provisional === [] ? null : $provisional])->save();
            RunObservationsRecorded::dispatch($run->id, $from, $timeline);
        });

        return $seq;
    }

    /**
     * @param  array<string, mixed>  $doc  schema-valid hosted run document
     * @return array{proto: int, run_id: string, status: string}
     */
    public function finish(AgentView $agent, string $runPublicId, array $doc, string $sha256): array
    {
        return DB::transaction(function () use ($agent, $runPublicId, $doc, $sha256): array {
            $run = $this->lockOwn($agent, $runPublicId);
            $answer = fn (): array => ['proto' => 1, 'run_id' => $run->publicId(), 'status' => $run->status];
            if ($run->document_sha256 !== null) {
                if (hash_equals($run->document_sha256, $sha256)) {
                    return $answer();
                }
                throw new RunRefusal(409, 'run_final');
            }
            if ($run->isFinal()) {
                throw new RunRefusal(409, 'run_final');
            }
            if ($run->status !== 'running') {
                throw new RunRefusal(409, 'not_running');
            }
            $r = (array) $doc['run'];
            $pack = (array) $r['pack'];
            if ($this->packs->prose((string) $pack['id'], (string) $pack['version']) === null) {
                throw new RunRefusal(422, 'unknown_pack');
            }
            $counts = FinalDocument::check(
                $doc, $run->publicId(), $run->provider, $run->fail_on,
                $this->completedObservations($run), $run->delivery_count, (int) config('agent.final.latency_tolerance_ms'),
            );

            $optedAllowed = $run->initiated_by_user_id !== null;
            $labelBits = is_int($r['label_bits'] ?? null) ? $r['label_bits'] : 24;
            $run->forceFill([
                'probe_entries' => (array) $r['probe_entries'],
                'label_bits' => is_int($r['label_bits'] ?? null) ? $r['label_bits'] : null,
                'probe_display_policy' => (string) $r['probe_display_policy'],
                'pack_version' => (string) $pack['version'],
            ]);
            $timeline = $run->last_seq;
            foreach ((array) $doc['deliveries'] as $d) {
                $d = (array) $d;
                if (($d['result'] ?? null) === 'n/a') {
                    $d['result'] = 'not_run';
                }
                $d['phase'] = 'judged';
                $this->storeDelivery($run, $d, ++$timeline, true);
            }
            foreach ((array) $doc['scenarios'] as $s) {
                $reading = is_array($s['reading'] ?? null) ? [
                    'kind' => (string) $s['reading']['kind'],
                    'probe' => (string) $s['reading']['probe'],
                    'display' => array_slice(DisplayValues::display((array) $s['reading']['display'], $optedAllowed, $labelBits), 0, 2),
                ] : null;
                $this->storeScenario($run, (string) $s['name'], (string) $s['result'], $s['reason'] ?? null, $reading, true);
            }
            DB::table('findings')->where('run_id', $run->id)->delete();
            foreach (array_values((array) $doc['findings']) as $i => $f) {
                $f = (array) $f;
                $params = (array) $f['params'];
                $opted = $optedAllowed && $this->optedEntry($run, (string) ($params['probe'] ?? ''));
                foreach (['before', 'after', 'expected'] as $k) {
                    if (array_key_exists($k, $params)) {
                        $params[$k] = DisplayValues::clean(is_string($params[$k]) ? $params[$k] : null, $opted, $labelBits);
                    }
                }
                $measured = [];
                foreach ((array) ($f['measured'] ?? []) as $m) {
                    $m = (array) $m;
                    foreach (['before', 'after', 'expected'] as $k) {
                        if (array_key_exists($k, $m)) {
                            $m[$k] = DisplayValues::clean(is_string($m[$k]) ? $m[$k] : null, $opted, $labelBits);
                        }
                    }
                    $measured[] = $m;
                }
                DB::table('findings')->insert([
                    'run_id' => $run->id,
                    'n' => $i + 1,
                    'project_id' => $run->project_id,
                    'type' => (string) $f['type'],
                    'fingerprint' => (string) $f['fingerprint'],
                    'severity' => (string) $f['severity'],
                    'level' => (int) $f['level'],
                    'event_type' => ($f['event_type'] ?? '') === '' ? null : (string) $f['event_type'],
                    'scenarios' => '{'.implode(',', array_map('strval', (array) $f['scenarios'])).'}',
                    'evidence' => json_encode(array_values((array) $f['evidence']), JSON_THROW_ON_ERROR),
                    'pack_version' => (string) $pack['version'],
                    'params' => json_encode((object) $params, JSON_THROW_ON_ERROR),
                    'framework' => ($f['framework'] ?? '') === '' ? null : (string) $f['framework'],
                    'likely_cause_rule' => ($f['likely_cause_rule'] ?? '') === '' ? null : (string) $f['likely_cause_rule'],
                    'measured' => json_encode($measured, JSON_THROW_ON_ERROR),
                ]);
            }

            $now = $this->clock->now();
            $status = (string) $r['status'];
            $reason = is_string($r['status_reason'] ?? null) ? $r['status_reason'] : null;
            $exit = (int) $r['exit_code'];
            // AC-6: the agent's clock is not trusted: clamp to [started_at, now].
            $finishedAt = is_string($r['finished_at'] ?? null) ? CarbonImmutable::parse($r['finished_at'])->utc() : $now;
            $startedAt = $run->started_at ?? $now;
            $finishedAt = $finishedAt->lessThan($startedAt) ? $startedAt : ($finishedAt->greaterThan($now) ? $now : $finishedAt);
            $run->forceFill([
                'status' => $status, 'status_reason' => $reason, 'exit_code' => $exit,
                'fail_on_inconclusive' => (bool) $r['fail_on_inconclusive'],
                'counts_critical' => $counts['critical'], 'counts_high' => $counts['high'],
                'counts_medium' => $counts['medium'], 'counts_info' => $counts['info'],
                'delivery_count' => count((array) $doc['deliveries']),
                'document' => $doc,
                'document_sha256' => $sha256,
                'finished_at' => $finishedAt,
                'provisional_findings' => null,
                'last_seq' => ++$timeline,
            ]);
            DB::statement('UPDATE runs SET levels = ?::smallint[] WHERE id = ?', ['{'.implode(',', array_map('intval', (array) $r['levels'])).'}', $run->id]);
            // A-13: a guest run counts once it got past Once.
            if ($run->initiated_by_guest_session_id !== null && ! $run->quota_counted && $status !== 'couldnt_start') {
                $this->guestQuotas->countRun($run->initiated_by_guest_session_id);
                $run->quota_counted = true;
            }
            $run->save();
            RunJob::query()->where('run_id', $run->id)->whereNull('completed_at')->update(['completed_at' => $now]);
            RunFinished::dispatch($run->id, $run->project_id, $status, $reason, $exit, $run->agent_id);
            $this->broadcasts->timeline($run->id, 'run.finished', [
                'run_id' => $run->publicId(), 'seq' => $timeline, 'status' => $status, 'reason' => $reason,
                'exit_code' => $exit, 'counts' => $counts,
            ]);
            $this->broadcasts->runsList($run->project_id);

            return $answer();
        });
    }

    /**
     * AC-12: provisional findings follow the type table (SEV-1) and stay
     * within `max_provisional` distinct keys per run.
     *
     * @param  array<mixed>  $provisional
     */
    private function checkProvisional(Run $run, array $provisional): void
    {
        $keys = array_fill_keys(array_keys($run->provisional_findings ?? []), true);
        foreach ($provisional as $f) {
            $f = (array) $f;
            if ((FinalDocument::SEVERITY[(string) $f['type']] ?? null) !== (string) $f['severity']) {
                throw new RunRefusal(422, 'severity_mismatch');
            }
            $keys[(string) $f['key']] = true;
        }
        if (count($keys) > (int) config('agent.observations.max_provisional')) {
            throw new RunRefusal(422, 'too_many_provisional');
        }
    }

    /**
     * Completed observations stored live, by delivery number (AC-6).
     *
     * @return array<int, array{status: int|null, transport: string|null, latency_ms: int|null, over_budget: bool|null, state: array<string, mixed>|null}>
     */
    private function completedObservations(Run $run): array
    {
        $out = [];
        foreach (DB::table('run_deliveries')->where('run_id', $run->id)->where('completed', true)
            ->get(['n', 'status', 'transport', 'latency_ms', 'over_budget', 'state']) as $row) {
            $state = is_string($row->state) ? json_decode($row->state, true, 16) : null;
            $out[(int) $row->n] = [
                'status' => $row->status === null ? null : (int) $row->status,
                'transport' => $row->transport === null ? null : (string) $row->transport,
                'latency_ms' => $row->latency_ms === null ? null : (int) $row->latency_ms,
                'over_budget' => $row->over_budget === null ? null : (bool) $row->over_budget,
                'state' => is_array($state) ? $state : null,
            ];
        }

        return $out;
    }

    /** @param  array<string, mixed>  $batch */
    private function applyPlan(Run $run, array $batch): void
    {
        $plan = $batch['plan'] ?? null;
        if (is_array($plan) && strlen(json_encode($plan, JSON_THROW_ON_ERROR)) > (int) config('agent.observations.max_plan_bytes')) {
            throw new RunRefusal(413, 'too_large');
        }
        $count = is_int($batch['delivery_count'] ?? null) ? $batch['delivery_count']
            : (is_array($plan) && is_array($plan['deliveries'] ?? null) ? count($plan['deliveries']) : $run->delivery_count);
        $run->forceFill([
            'plan' => is_array($plan) ? $plan : $run->plan,
            'plan_hash' => is_string($batch['plan_hash'] ?? null) ? (string) hex2bin($batch['plan_hash']) : $run->plan_hash,
            'delivery_count' => min(50, max(0, $count)),
            'probe_entries' => is_array($batch['probe_entries'] ?? null) ? $batch['probe_entries'] : $run->probe_entries,
            'label_bits' => is_int($batch['label_bits'] ?? null) ? $batch['label_bits'] : $run->label_bits,
            'probe_display_policy' => is_string($batch['probe_display_policy'] ?? null) ? $batch['probe_display_policy'] : $run->probe_display_policy,
        ]);
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  bool  $judged  from the final document: a completed delivery keeps
     *                        its measured facts (checked equal, AC-6) and takes the verdict
     *                        (`result`, `phase`, judged readings)
     */
    private function storeDelivery(Run $run, array $d, int $timelineSeq, bool $judged = false): void
    {
        $n = (int) $d['n'];
        $existing = DB::table('run_deliveries')->where('run_id', $run->id)->where('n', $n)->first(['completed']);
        $keepMeasured = $existing !== null && (bool) $existing->completed;
        if ($keepMeasured && ! $judged) {
            return; // immutable once completed (domain §6)
        }
        $optedAllowed = $run->initiated_by_user_id !== null;
        $labelBits = $run->label_bits ?? 24;
        $captureId = RunJobs::captureUuid((string) $d['event_ref']);
        $index = $captureId === null ? false : array_search($captureId, $run->captureIds(), true);
        $state = null;
        if (is_array($d['state'] ?? null)) {
            $readings = [];
            foreach ((array) ($d['state']['readings'] ?? []) as $r) {
                $readings[] = [
                    'name' => (string) $r['name'],
                    'changed' => is_bool($r['changed'] ?? null) ? $r['changed'] : null,
                    'matches_expected' => is_bool($r['matches_expected'] ?? null) ? $r['matches_expected'] : null,
                    'flagged' => (bool) ($r['flagged'] ?? false),
                    'display' => DisplayValues::display(is_array($r['display'] ?? null) ? $r['display'] : null, $optedAllowed && $this->optedEntry($run, (string) $r['name']), $labelBits),
                ];
            }
            $state = ['readings' => $readings, 'failed' => (bool) ($d['state']['failed'] ?? false), 'unsettled' => (bool) ($d['state']['unsettled'] ?? false)];
        }
        $response = is_array($d['response'] ?? null) ? $d['response'] : null;
        $signing = (array) $d['signing'];
        $values = [
            'scenario' => (string) $d['scenario'],
            'capture_id' => $index === false ? null : $captureId,
            'source_seq' => $index === false ? null : ($run->sourceSeqs()[$index] ?? null),
            'mode' => (string) $d['mode'],
            'offset_ms' => is_int($d['offset_ms'] ?? null) ? $d['offset_ms'] : null,
            'signing_kind' => (string) $signing['kind'],
            'timestamp_offset_s' => is_int($signing['timestamp_offset_s'] ?? null) ? $signing['timestamp_offset_s'] : null,
            'tamper' => is_array($d['tamper'] ?? null) ? json_encode($d['tamper'], JSON_THROW_ON_ERROR) : null,
            'status' => is_int($d['status'] ?? null) ? $d['status'] : null,
            'transport' => is_string($d['transport'] ?? null) ? $d['transport'] : null,
            'latency_ms' => is_int($d['latency_ms'] ?? null) ? $d['latency_ms'] : null,
            'over_budget' => is_bool($d['over_budget'] ?? null) ? $d['over_budget'] : null,
            'response_content_type' => $response === null ? null : ($response['content_type'] ?? null),
            'response_sha256' => $response === null ? null : Bytea::bind((string) hex2bin((string) $response['body_sha256'])),
            'response_bytes' => $response === null ? null : (int) $response['body_bytes'],
            'state' => $state === null ? null : json_encode($state, JSON_THROW_ON_ERROR),
            'result' => is_string($d['result'] ?? null) ? $d['result'] : null,
            'phase' => is_string($d['phase'] ?? null) ? $d['phase'] : null,
            'completed' => (bool) $d['completed'],
            'seq' => $timelineSeq,
        ];
        if ($keepMeasured) {
            // Measured facts stay as observed; the verdict comes from the document.
            DB::table('run_deliveries')->where('run_id', $run->id)->where('n', $n)->update([
                'result' => $values['result'], 'phase' => $values['phase'], 'state' => $values['state'], 'seq' => $timelineSeq,
            ]);
        } elseif ($existing === null) {
            DB::table('run_deliveries')->insert(['run_id' => $run->id, 'n' => $n, ...$values]);
        } else {
            DB::table('run_deliveries')->where('run_id', $run->id)->where('n', $n)->update($values);
        }
    }

    /** @param  array<string, mixed>|null  $reading */
    private function storeScenario(Run $run, string $name, mixed $result, mixed $reason, ?array $reading, bool $final = false): void
    {
        $position = array_search($name, $run->scenarioList(), true);
        $position = $position === false ? 100 + (int) array_search($name, Scenarios::ALL, true) : $position + 1;
        $values = [
            'name' => $name,
            'result' => is_string($result) ? $result : null,
            'reason' => is_string($reason) ? $reason : null,
        ];
        if ($final) {
            $values['reading'] = $reading === null ? null : json_encode($reading, JSON_THROW_ON_ERROR);
        }
        $exists = DB::table('run_scenarios')->where('run_id', $run->id)->where('name', $name)->exists();
        $exists
            ? DB::table('run_scenarios')->where('run_id', $run->id)->where('name', $name)->update($values)
            : DB::table('run_scenarios')->insert(['run_id' => $run->id, 'position' => $position, ...$values]);
    }

    private function optedEntry(Run $run, string $name): bool
    {
        foreach ((array) $run->probe_entries as $e) {
            if (($e['name'] ?? null) === $name) {
                return str_starts_with((string) ($e['display_mode'] ?? ''), 'opted_');
            }
        }

        return false;
    }

    private function lockOwn(AgentView $agent, string $runPublicId): Run
    {
        $uuid = Run::uuidFromPublicId($runPublicId);
        $run = $uuid === null ? null : Run::query()->whereKey($uuid)->lockForUpdate()->first();
        if ($run === null || $run->agent_id !== $agent->id) {
            throw RunRefusal::notFound();
        }

        return $run;
    }
}
