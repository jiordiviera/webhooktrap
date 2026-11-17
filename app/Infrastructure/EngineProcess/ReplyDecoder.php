<?php

declare(strict_types=1);

namespace App\Infrastructure\EngineProcess;

use JsonException;

/**
 * Strict decoding of one sandbox reply line (S-11, TM-74): the engine
 * parses hostile captures, so its answer is checked like any other untrusted
 * input. Exact key sets, exact types, bounded numbers, codes in
 * `^[a-z0-9_]{1,64}$`, refs among the request's; anything else is
 * `bad_reply`. No engine text ever leaves this class.
 */
final class ReplyDecoder
{
    public const int PROTO = 1;

    public const int MAX_DELIVERIES = 50;

    public const int MAX_BUDGET_MS = 600_000;

    private const string CODE = '/^[a-z0-9_]{1,64}$/';

    private const string TOKEN = '/^[A-Za-z0-9_.:\/-]{0,128}$/';

    public function plan(string $line, PlanRequest $request): PlanResult
    {
        try {
            $reply = json_decode($line, true, 16, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return PlanResult::failed(PlanResult::BAD_REPLY);
        }
        // The server's own refusals (busy, timeout, internal_error) carry op "".
        if (! is_array($reply) || ($reply['proto'] ?? null) !== self::PROTO || ! is_bool($reply['ok'] ?? null)
            || ! in_array($reply['op'] ?? null, $reply['ok'] ? ['plan'] : ['plan', ''], true)) {
            return PlanResult::failed(PlanResult::BAD_REPLY);
        }
        if ($reply['ok'] === false) {
            $error = $reply['error'] ?? null;
            if (! self::exactKeys($reply, ['proto', 'op', 'ok', 'error']) || ! is_array($error) || ! self::exactKeys($error, ['code'])
                || ! self::isCode($error['code'])) {
                return PlanResult::failed(PlanResult::BAD_REPLY);
            }

            return PlanResult::failed($error['code']);
        }
        if (! self::exactKeys($reply, ['proto', 'op', 'ok', 'preview']) || ! is_array($reply['preview'])) {
            return PlanResult::failed(PlanResult::BAD_REPLY);
        }
        $preview = $this->preview($reply['preview'], $request);

        return $preview === null ? PlanResult::failed(PlanResult::BAD_REPLY) : PlanResult::ok($preview);
    }

    /** @param  array<mixed>  $p */
    private function preview(array $p, PlanRequest $request): ?PlanPreview
    {
        if (! self::exactKeys($p, ['delivery_count', 'plan_hash', 'needs_secret', 'pack', 'budget', 'scenarios', 'events'])) {
            return null;
        }
        $count = $p['delivery_count'];
        if (! self::intIn($count, 0, self::MAX_DELIVERIES) || ! is_string($p['plan_hash']) || preg_match('/^[0-9a-f]{64}$/', $p['plan_hash']) !== 1
            || ! is_bool($p['needs_secret'])) {
            return null;
        }

        $pack = $p['pack'];
        if (! is_array($pack) || ! self::exactKeys($pack, ['id', 'version']) || $pack['id'] !== $request->packId
            || ! is_string($pack['version']) || preg_match('/^[0-9A-Za-z][0-9A-Za-z.+-]{0,63}$/', $pack['version']) !== 1
            || ($request->packVersion !== '' && $pack['version'] !== $request->packVersion)) {
            return null;
        }

        $budget = $p['budget'];
        if (! is_array($budget) || ! self::exactKeys($budget, ['ms', 'source']) || ! self::intIn($budget['ms'], 1, self::MAX_BUDGET_MS)
            || ! self::isCode($budget['source'])) {
            return null;
        }

        // PLAN-2: the engine always plans `once` (the baseline), asked or not.
        $expected = array_values(array_unique(['once', ...$request->scenarios]));
        $scenarios = $p['scenarios'];
        if (! is_array($scenarios) || ! array_is_list($scenarios) || count($scenarios) !== count($expected)) {
            return null;
        }
        $seen = [];
        $sum = 0;
        $outScenarios = [];
        foreach ($scenarios as $s) {
            if (! is_array($s) || ! self::exactKeys($s, ['name', 'na', 'deliveries']) || ! is_string($s['name'])
                || ! in_array($s['name'], $expected, true) || isset($seen[$s['name']])
                || ($s['na'] !== null && ! self::isCode($s['na'])) || ! self::intIn($s['deliveries'], 0, self::MAX_DELIVERIES)
                || ($s['na'] !== null && $s['deliveries'] !== 0)) {
                return null;
            }
            $seen[$s['name']] = true;
            $sum += $s['deliveries'];
            $outScenarios[] = ['name' => $s['name'], 'na' => $s['na'], 'deliveries' => $s['deliveries']];
        }
        if ($sum !== $count) {
            return null;
        }

        $events = $p['events'];
        $refs = $request->refs();
        if (! is_array($events) || ! array_is_list($events) || $events === [] || count($events) > count($refs)) {
            return null;
        }
        $outEvents = [];
        foreach ($events as $e) {
            if (! is_array($e) || ! self::exactKeys($e, ['ref', 'event_type', 'primary']) || ! in_array($e['ref'], $refs, true)
                || ! is_string($e['event_type']) || preg_match(self::TOKEN, $e['event_type']) !== 1 || ! is_bool($e['primary'])) {
                return null;
            }
            $outEvents[] = ['ref' => (string) $e['ref'], 'eventType' => $e['event_type'], 'primary' => $e['primary']];
        }

        return new PlanPreview($count, $p['plan_hash'], $p['needs_secret'], ['id' => $request->packId, 'version' => $pack['version']],
            ['ms' => $budget['ms'], 'source' => $budget['source']], $outScenarios, $outEvents);
    }

    /**
     * @param  array<mixed>  $a
     * @param  list<string>  $keys
     */
    private static function exactKeys(array $a, array $keys): bool
    {
        if (count($a) !== count($keys)) {
            return false;
        }
        foreach ($keys as $k) {
            if (! array_key_exists($k, $a)) {
                return false;
            }
        }

        return true;
    }

    /** @phpstan-assert-if-true string $v */
    private static function isCode(mixed $v): bool
    {
        return is_string($v) && preg_match(self::CODE, $v) === 1;
    }

    /** @phpstan-assert-if-true int $v */
    private static function intIn(mixed $v, int $min, int $max): bool
    {
        return is_int($v) && $v >= $min && $v <= $max;
    }
}
