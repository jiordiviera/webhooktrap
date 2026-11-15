<?php

declare(strict_types=1);

namespace App\Modules\Proving\Domain;

use App\Modules\Proving\Contracts\RunRefusal;

/**
 * The server's checks of a final run document beyond its schema
 * (contracts §5.7, domain §6 invariants): it belongs to this run and
 * provider, `fail_on` is the one the job carried, counts are recomputed
 * from `findings[]`, severities follow the type table (SEV-1), evidence
 * names real deliveries, the exit code matches status and `fail_on`
 * (EXIT-1), and Level 1 findings agree with the Level 1 observations
 * (S-5). Level 2 is never rejudged.
 *
 * AC-6 (S-5, TM-17): the document is also held against what the server
 * stored live. It lists exactly the planned deliveries; every completed
 * observation appears with the same status, transport, latency (within
 * tolerance) and probe outcome; each finding's `level` is the one its type
 * allows (`LEVELS`); and every Level 1 finding the deliveries imply is
 * present (the rules of the engine's judge: GEN-1 to GEN-3, BUD-6, INV-1).
 */
final class FinalDocument
{
    public const array SEVERITY = [
        'accepts_tampered' => 'critical', 'accepts_unsigned' => 'critical',
        'accepts_stale' => 'high', 'duplicate_changed_state' => 'high', 'order_changed_state' => 'high',
        'concurrent_changed_state' => 'high', 'partial_side_effect' => 'high', 'retry_diverged' => 'high', 'server_error' => 'high',
        'non_2xx_on_valid' => 'medium', 'error_on_invalid' => 'medium', 'over_budget' => 'medium',
        'needs_probe' => 'info', 'order_dependent' => 'info', 'probe_gap' => 'info', 'cold_start' => 'info', 'async_write' => 'info',
    ];

    /**
     * AC-6: the levels a finding type can have (the judge's rules). The
     * `accepts_*` types are Level 1 on a 2xx and Level 2 when the delivery was
     * refused but the write happened first (INV-1).
     */
    public const array LEVELS = [
        'accepts_tampered' => [1, 2], 'accepts_unsigned' => [1, 2], 'accepts_stale' => [1, 2],
        'server_error' => [1], 'non_2xx_on_valid' => [1], 'error_on_invalid' => [1], 'over_budget' => [1],
        'cold_start' => [1], 'needs_probe' => [1],
        'duplicate_changed_state' => [2], 'order_changed_state' => [2], 'concurrent_changed_state' => [2],
        'partial_side_effect' => [2], 'retry_diverged' => [2], 'order_dependent' => [2], 'probe_gap' => [2], 'async_write' => [2],
    ];

    private const array VALID_MODES = ['normal', 'duplicate', 'retry'];

    private const array INVALID_TYPE = ['late' => 'accepts_stale', 'tampered' => 'accepts_tampered', 'unsigned' => 'accepts_unsigned'];

    private const array RANK = ['critical' => 0, 'high' => 1, 'medium' => 2, 'info' => 3];

    /**
     * @param  array<string, mixed>  $doc
     * @param  array<int, array{status: int|null, transport: string|null, latency_ms: int|null, over_budget: bool|null, state: array<string, mixed>|null}>  $observed  completed observations stored live, by delivery number
     * @param  int  $planned  the planned delivery count (0 when the agent never reported one)
     * @return array{critical: int, high: int, medium: int, info: int, findings: int}
     */
    public static function check(array $doc, string $runPublicId, string $provider, string $failOn, array $observed = [], int $planned = 0, int $latencyTolerance = 5): array
    {
        $run = (array) $doc['run'];
        if (($run['id'] ?? null) !== $runPublicId) {
            throw new RunRefusal(422, 'run_mismatch');
        }
        if (($run['provider'] ?? null) !== $provider || (($run['pack'] ?? [])['id'] ?? null) !== $provider) {
            throw new RunRefusal(422, 'provider_mismatch');
        }
        if (($run['fail_on'] ?? null) !== $failOn) {
            throw new RunRefusal(422, 'fail_on_mismatch');
        }

        $deliveries = [];
        foreach ((array) $doc['deliveries'] as $d) {
            if (isset($deliveries[(int) $d['n']])) {
                throw new RunRefusal(422, 'delivery_count_mismatch');
            }
            $deliveries[(int) $d['n']] = (array) $d;
            $transport = $d['transport'] ?? null;
            if (($d['result'] ?? null) === 'unreachable' && ! in_array($transport, ['refused', 'dns', 'tls'], true)) {
                throw new RunRefusal(422, 'level1_inconsistent');
            }
        }
        if ($planned > 0 && count($deliveries) !== $planned) {
            throw new RunRefusal(422, 'delivery_count_mismatch');
        }
        foreach ($observed as $n => $o) {
            if (! isset($deliveries[$n]) || ! self::matchesObservation($deliveries[$n], $o, $latencyTolerance)) {
                throw new RunRefusal(422, 'observation_mismatch');
            }
        }

        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'info' => 0, 'findings' => 0];
        foreach ((array) $doc['findings'] as $f) {
            $type = (string) $f['type'];
            $severity = (string) $f['severity'];
            if ((self::SEVERITY[$type] ?? null) !== $severity) {
                throw new RunRefusal(422, 'severity_mismatch');
            }
            if (! in_array((int) $f['level'], self::LEVELS[$type] ?? [], true)) {
                throw new RunRefusal(422, 'level_mismatch');
            }
            $counts[$severity]++;
            if ($severity !== 'info') {
                $counts['findings']++;
            }
            $offending = [];
            foreach ((array) $f['evidence'] as $e) {
                if (! isset($deliveries[(int) $e['d']])) {
                    throw new RunRefusal(422, 'unknown_delivery');
                }
                if ($e['role'] === 'offending') {
                    $offending[] = $deliveries[(int) $e['d']];
                }
            }
            if ((int) $f['level'] === 1) {
                foreach ($offending as $d) {
                    if (! self::level1Holds($type, $d)) {
                        throw new RunRefusal(422, 'level1_inconsistent');
                    }
                }
            } elseif (str_starts_with($type, 'accepts_')) {
                // Level 2 only when the delivery was refused (no 2xx) but wrote anyway.
                foreach ($offending as $d) {
                    if (self::is2xx($d)) {
                        throw new RunRefusal(422, 'level_mismatch');
                    }
                }
            }
        }
        if (in_array($run['status'] ?? null, ['findings', 'inconclusive', 'passed'], true)) {
            self::requireImpliedLevel1($deliveries, (array) $doc['findings'], (array) ($run['budget'] ?? []), $run['status_reason'] ?? null, $observed);
        }
        $claimed = (array) $doc['counts'];
        foreach ($counts as $k => $v) {
            if ((int) ($claimed[$k] ?? -1) !== $v) {
                throw new RunRefusal(422, 'counts_mismatch');
            }
        }
        if ((int) $run['exit_code'] !== self::exitCode($doc, $failOn)) {
            throw new RunRefusal(422, 'exit_code_mismatch');
        }

        return $counts;
    }

    /**
     * EXIT-1, as the engine's judge computes it.
     *
     * @param  array<string, mixed>  $doc
     */
    public static function exitCode(array $doc, string $failOn): int
    {
        $run = (array) $doc['run'];
        $status = (string) $run['status'];
        if ($status === 'couldnt_start' && (int) $run['exit_code'] === 3) {
            return 3; // a definition error: nothing was sent
        }
        $unreachable = false;
        foreach ((array) $doc['scenarios'] as $s) {
            $unreachable = $unreachable || ($s['result'] ?? null) === 'unreachable';
        }
        foreach ((array) $doc['deliveries'] as $d) {
            $unreachable = $unreachable || ($d['result'] ?? null) === 'unreachable';
        }
        if (in_array($status, ['couldnt_start', 'aborted'], true) || $unreachable) {
            return 2;
        }
        if ($failOn !== 'never') {
            foreach ((array) $doc['findings'] as $f) {
                if (self::RANK[(string) $f['severity']] <= self::RANK[$failOn]) {
                    return 1;
                }
            }
        }

        return $status === 'inconclusive' && (bool) $run['fail_on_inconclusive'] ? 1 : 0;
    }

    /**
     * The Level 1 findings the deliveries imply, as the judge derives them;
     * each must be in the document with that delivery as offending evidence.
     *
     * @param  array<int, array<string, mixed>>  $deliveries
     * @param  array<mixed>  $findings
     * @param  array<mixed>  $budget
     * @param  array<int, array{status: int|null, transport: string|null, latency_ms: int|null, over_budget: bool|null, state: array<string, mixed>|null}>  $observed
     */
    private static function requireImpliedLevel1(array $deliveries, array $findings, array $budget, mixed $statusReason, array $observed): void
    {
        $offends = [];
        foreach ($findings as $f) {
            if ((int) $f['level'] !== 1) {
                continue;
            }
            foreach ((array) $f['evidence'] as $e) {
                if (($e['role'] ?? null) === 'offending') {
                    $offends[(string) $f['type']][(int) $e['d']] = true;
                }
            }
        }
        $has = fn (string $type, int $n): bool => isset($offends[$type][$n]);
        $budgetMs = is_int($budget['ms'] ?? null) ? $budget['ms'] : 0;
        foreach ($deliveries as $n => $d) {
            $status = is_int($d['status'] ?? null) ? $d['status'] : null;
            $transport = is_string($d['transport'] ?? null) ? $d['transport'] : null;
            if (($d['completed'] ?? false) !== true || in_array($transport, ['refused', 'dns', 'tls'], true)) {
                continue;
            }
            $failed = $status === null && in_array($transport, ['timeout', 'reset'], true);
            $mode = (string) ($d['mode'] ?? '');
            $missing = false;
            if (in_array($mode, self::VALID_MODES, true)) {
                if ($failed || ($status !== null && $status >= 500)) {
                    $missing = ! $has('server_error', $n);
                } elseif ($status !== null && $status >= 300 && ! ($n === 1 && $statusReason === 'baseline_rejected')) {
                    $missing = ! $has('non_2xx_on_valid', $n);
                }
                $latency = is_int($d['latency_ms'] ?? null) ? $d['latency_ms'] : null;
                $over = ($observed[$n]['over_budget'] ?? null) === true || ($budgetMs > 0 && $latency !== null && $latency > $budgetMs);
                if ($status !== null && $over && ! $has('over_budget', $n) && ! ($n === 1 && $has('cold_start', 1))) {
                    $missing = true;
                }
            } elseif (isset(self::INVALID_TYPE[$mode])) {
                if ($status !== null && $status >= 200 && $status < 300) {
                    $missing = ! $has(self::INVALID_TYPE[$mode], $n);
                } elseif ($failed || ($status !== null && $status >= 500)) {
                    $missing = ! $has('error_on_invalid', $n);
                }
            }
            if ($missing) {
                throw new RunRefusal(422, 'level1_missing');
            }
        }
    }

    /**
     * A completed observation and the document's copy of that delivery agree
     * (AC-6): completed, same status and transport, latency within the
     * tolerance, same probe failure and per-probe `changed`.
     *
     * @param  array<string, mixed>  $d
     * @param  array{status: int|null, transport: string|null, latency_ms: int|null, over_budget: bool|null, state: array<string, mixed>|null}  $o
     */
    private static function matchesObservation(array $d, array $o, int $tolerance): bool
    {
        $status = is_int($d['status'] ?? null) ? $d['status'] : null;
        $transport = is_string($d['transport'] ?? null) ? $d['transport'] : null;
        $latency = is_int($d['latency_ms'] ?? null) ? $d['latency_ms'] : null;
        if (($d['completed'] ?? false) !== true || $status !== $o['status'] || $transport !== $o['transport']) {
            return false;
        }
        if (($latency === null) !== ($o['latency_ms'] === null)
            || ($latency !== null && $o['latency_ms'] !== null && abs($latency - $o['latency_ms']) > $tolerance)) {
            return false;
        }
        if ($o['state'] === null) {
            return true;
        }
        $state = is_array($d['state'] ?? null) ? $d['state'] : null;
        if ($state === null || (bool) ($state['failed'] ?? false) !== (bool) ($o['state']['failed'] ?? false)) {
            return false;
        }
        $changed = [];
        foreach ((array) ($state['readings'] ?? []) as $r) {
            $changed[(string) $r['name']] = ($r['changed'] ?? null) === true;
        }
        foreach ((array) ($o['state']['readings'] ?? []) as $r) {
            $name = (string) $r['name'];
            if (! array_key_exists($name, $changed) || $changed[$name] !== (($r['changed'] ?? null) === true)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $d */
    private static function is2xx(array $d): bool
    {
        $status = $d['status'] ?? null;

        return is_int($status) && $status >= 200 && $status < 300;
    }

    /** @param  array<string, mixed>  $d */
    private static function level1Holds(string $type, array $d): bool
    {
        $status = is_int($d['status'] ?? null) ? $d['status'] : null;
        $transport = $d['transport'] ?? null;

        return match ($type) {
            'accepts_tampered', 'accepts_unsigned', 'accepts_stale' => $status !== null && $status >= 200 && $status < 300,
            'server_error' => ($status !== null && $status >= 500) || in_array($transport, ['timeout', 'reset'], true),
            'non_2xx_on_valid' => $status !== null && $status >= 300 && $status < 500,
            'error_on_invalid' => $status !== null && $status >= 500,
            'over_budget' => ($d['over_budget'] ?? false) === true,
            default => true,
        };
    }
}
