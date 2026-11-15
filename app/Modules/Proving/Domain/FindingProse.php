<?php

declare(strict_types=1);

namespace App\Modules\Proving\Domain;

/**
 * Finding prose rendered by the server (TM-16, domain §1.2 rule 6, FND-7):
 * every sentence comes from the pack's `text` templates for the run's pack
 * version, filled only from the finding's type, evidence, structured
 * `params`/`measured` (display-safe, validated before storage) and pack
 * data. Nothing the agent wrote as text is used. The citation is the pack's
 * own for the type; any URL in a document is ignored.
 *
 * The placeholder rules are the engine's (`agent/render/prose.go`), so the
 * CLI and the web read the same.
 */
final class FindingProse
{
    /**
     * @param  array{name: string, text: array<string, mixed>, citations: array<string, mixed>, tolerance_s: int, budget: array{ms: int, label: string}|null}  $pack
     * @param  array{type: string, event_type: string|null, scenarios: list<string>, evidence: list<array{d: int, role: string}>, params: array<string, mixed>, measured: list<array<string, mixed>>, likely_cause_rule: string|null, framework: string|null}  $finding
     * @param  array{ms: int, label: string}  $budget  the run's budget as the server renders it
     * @return array{title: string, what: string, why: string, fix: string, snippet: string|null, snippetFor: string|null, likelyCause: string|null, citation: array{label: string, url: string}|null}
     */
    public static function render(array $pack, array $finding, array $budget): array
    {
        $type = $finding['type'];
        $findings = (array) ($pack['text']['findings'] ?? []);
        $tpl = is_array($findings[$type] ?? null) ? $findings[$type] : [];
        $vars = self::vars($pack, $finding, $budget);

        $title = self::fill(self::str($tpl['title'] ?? ''), $vars);
        $likely = null;
        $rule = $finding['likely_cause_rule'];
        if ($rule !== null && $rule !== '') {
            $causes = (array) ($pack['text']['likely_cause'] ?? []);
            $likely = isset($causes[$rule]) ? self::fill(self::str($causes[$rule]), $vars) : null;
        }

        $snippet = null;
        $snippetFor = null;
        $snippets = (array) ($pack['text']['snippets'][$type] ?? []);
        $framework = $finding['framework'];
        if ($framework !== null && $framework !== '' && is_string($snippets[$framework] ?? null)) {
            [$snippet, $snippetFor] = [$snippets[$framework], self::frameworkName($framework)];
        } elseif (is_string($snippets['webhooktrap.yml'] ?? null)) {
            [$snippet, $snippetFor] = [$snippets['webhooktrap.yml'], 'webhooktrap.yml'];
        }

        $citation = null;
        $list = $pack['citations'][$type] ?? null;
        if (is_array($list) && is_array($list[0] ?? null) && is_string($list[0]['label'] ?? null) && is_string($list[0]['url'] ?? null)) {
            $citation = ['label' => $list[0]['label'], 'url' => $list[0]['url']];
        }

        return [
            'title' => $title !== '' ? $title : $type,
            'what' => self::fill(self::str($tpl['what'] ?? ''), $vars),
            'why' => self::fill(self::str($tpl['why'] ?? ''), $vars),
            'fix' => self::fill(self::str($tpl['fix'] ?? ''), $vars),
            'snippet' => $snippet,
            'snippetFor' => $snippetFor,
            'likelyCause' => $likely,
            'citation' => $citation,
        ];
    }

    public static function frameworkName(string $framework): string
    {
        return match ($framework) {
            'express' => 'Express',
            'nextjs' => 'Next.js',
            'laravel' => 'Laravel',
            default => $framework,
        };
    }

    /**
     * `{name}` substitution and `[[name|text]]` kept only when `name` has a value.
     *
     * @param  array<string, string>  $vars
     */
    public static function fill(string $tpl, array $vars): string
    {
        $out = '';
        $len = strlen($tpl);
        for ($i = 0; $i < $len;) {
            if (substr($tpl, $i, 2) === '[[') {
                $end = strpos($tpl, ']]', $i);
                if ($end === false) {
                    return $out.substr($tpl, $i);
                }
                $body = substr($tpl, $i + 2, $end - $i - 2);
                $parts = explode('|', $body, 2);
                if (count($parts) === 2 && ($vars[$parts[0]] ?? '') !== '') {
                    $out .= self::fill($parts[1], $vars);
                }
                $i = $end + 2;
            } elseif ($tpl[$i] === '{') {
                $end = strpos($tpl, '}', $i);
                if ($end === false) {
                    return $out.substr($tpl, $i);
                }
                $out .= $vars[substr($tpl, $i + 1, $end - $i - 1)] ?? '';
                $i = $end + 1;
            } else {
                $out .= $tpl[$i];
                $i++;
            }
        }

        return $out;
    }

    /**
     * @param  array{name: string, text: array<string, mixed>, citations: array<string, mixed>, tolerance_s: int, budget: array{ms: int, label: string}|null}  $pack
     * @param  array{type: string, event_type: string|null, scenarios: list<string>, evidence: list<array{d: int, role: string}>, params: array<string, mixed>, measured: list<array<string, mixed>>, likely_cause_rule: string|null, framework: string|null}  $f
     * @param  array{ms: int, label: string}  $budget
     * @return array<string, string>
     */
    private static function vars(array $pack, array $f, array $budget): array
    {
        $p = $f['params'];
        $int = fn (string $k): int => is_int($p[$k] ?? null) ? $p[$k] : 0;
        $str = fn (string $k): string => is_string($p[$k] ?? null) ? $p[$k] : '';
        $v = [
            'event_type' => $str('event_type') !== '' ? $str('event_type') : (string) $f['event_type'],
            'provider' => $pack['name'],
            'probe' => $str('probe'),
            'object_type' => $str('object_type'),
            'budget_label' => $budget['label'],
            'tolerance_s' => (string) $pack['tolerance_s'],
        ];
        if ($int('d') > 0) {
            $v['d'] = 'D'.$int('d');
        }
        if ($int('d_ref') > 0) {
            $v['d_ref'] = 'D'.$int('d_ref');
        }
        if ($int('status') > 0) {
            $v['status'] = (string) $int('status');
        }
        $v['status_text'] = self::statusText($int('status'), $str('transport'));
        if (is_int($p['latency_ms'] ?? null)) {
            $v['latency_ms'] = self::num($p['latency_ms']);
        }
        if (is_int($p['max_other_ms'] ?? null)) {
            $v['max_other_ms'] = self::num($p['max_other_ms']);
        }
        if ($int('timestamp_offset_s') !== 0) {
            $v['timestamp_age_s'] = (string) -$int('timestamp_offset_s');
        }
        if (is_array($p['tamper'] ?? null)) {
            $path = (string) ($p['tamper']['path'] ?? '');
            $dot = strrpos($path, '.');
            $v['tamper.path'] = $dot === false ? $path : substr($path, $dot + 1);
            $v['tamper.from'] = (string) ($p['tamper']['from'] ?? '');
            $v['tamper.to'] = (string) ($p['tamper']['to'] ?? '');
        }
        foreach (['before', 'after', 'expected'] as $k) {
            if (is_string($p[$k] ?? null)) {
                $v[$k] = $p[$k];
            }
        }
        if ($f['type'] === 'over_budget') {
            $ms = $budget['ms'];
            $v['budget_s'] = $ms % 1000 === 0 ? (string) intdiv($ms, 1000) : intdiv($ms, 1000).'.'.intdiv($ms % 1000, 100);
            $v['budget_source'] = (string) preg_replace('/^.*·\s*/u', '', $budget['label']);
            $v['slow_count'] = (string) $int('slow_count');
            $v['valid_count'] = (string) $int('valid_count');
            $parts = [];
            foreach ($f['measured'] as $m) {
                if (is_int($m['latency_ms'] ?? null) && is_int($m['d'] ?? null)) {
                    $parts[] = 'D'.$m['d'].' ('.self::num($m['latency_ms']).' ms)';
                }
            }
            $v['latency_list'] = self::andList($parts);
        }
        $off = [];
        foreach ($f['evidence'] as $e) {
            if ($e['role'] === 'offending') {
                $off[] = 'D'.$e['d'];
            }
        }
        $v['offending'] = self::andList($off);
        $v['scenario_list'] = self::andList(array_map(Scenarios::label(...), $f['scenarios']));
        $first = $f['measured'][0] ?? null;
        if (is_array($first) && is_string($first['probe'] ?? null) && $first['probe'] !== '') {
            $parts = [];
            foreach ($f['measured'] as $m) {
                if (is_string($m['before'] ?? null) && is_string($m['after'] ?? null) && is_int($m['d'] ?? null)) {
                    $parts[] = $m['before'].' → '.$m['after'].' on D'.$m['d'];
                }
            }
            $s = $first['probe'].' went '.self::andList($parts);
            if (is_string($first['expected'] ?? null)) {
                $s .= ', expected '.$first['expected'];
            }
            $v['state_list'] = $s;
        }

        return $v;
    }

    private static function statusText(int $status, string $transport): string
    {
        return match (true) {
            $status > 0 => 'HTTP '.$status,
            $transport === 'timeout' => 'no response within 30 s',
            $transport === 'reset' => 'a closed connection before any response',
            default => '',
        };
    }

    /** Thousands grouped with a space, as the engine prints them. */
    public static function num(int $n): string
    {
        $s = (string) abs($n);
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            if ($i > 0 && ($len - $i) % 3 === 0) {
                $out .= ' ';
            }
            $out .= $s[$i];
        }

        return ($n < 0 ? '-' : '').$out;
    }

    /** @param  list<string>  $xs */
    public static function andList(array $xs): string
    {
        return match (count($xs)) {
            0 => '',
            1 => $xs[0],
            default => implode(', ', array_slice($xs, 0, -1)).' and '.$xs[count($xs) - 1],
        };
    }

    private static function str(mixed $v): string
    {
        return is_string($v) ? $v : '';
    }
}
