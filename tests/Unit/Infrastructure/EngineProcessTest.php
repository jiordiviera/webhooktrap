<?php

declare(strict_types=1);

use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\EngineProcess\PlanRequest;
use App\Infrastructure\EngineProcess\PlanResult;
use App\Infrastructure\EngineProcess\ReplyDecoder;
use App\Infrastructure\EngineProcess\SocketEngine;

/*
 * EngineProcess client against a fake `serve-sandbox` (tests/Support/
 * fake-engine.php, one call per process) and, when `agent/bin/webhooktrap`
 * was built, against the real one. The reply is untrusted: anything off is
 * a code, never engine text.
 */

function engineSocketPath(): string
{
    return sys_get_temp_dir().'/wte'.bin2hex(random_bytes(4)).'.sock';
}

/** @return array{0: string, 1: resource} */
function fakeEngine(string $mode, string $reply = ''): array
{
    $path = engineSocketPath();
    file_put_contents($path.'.reply', $reply);
    $proc = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/Support/fake-engine.php', $path, $mode, $path.'.reply'],
        [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    for ($i = 0; $i < 200 && ! file_exists($path); $i++) {
        usleep(10_000);
    }

    return [$path, $proc];
}

function planRequest(): PlanRequest
{
    return new PlanRequest(
        [
            ['ref' => 'cap_A', 'order' => 1, 'headers' => [['Content-Type', 'application/json'], ['X-B', '2'], ['x-a', '1']], 'body' => '{"id":"evt_1"}'],
            ['ref' => 'cap_B', 'order' => 3, 'headers' => [], 'body' => "\x00\xff raw"],
        ],
        ['once', 'twice'], 'stripe', '1.0.0',
    );
}

/** @return array<string, mixed> */
function goodPreview(array $overrides = []): array
{
    return array_replace([
        'delivery_count' => 4, 'plan_hash' => str_repeat('ab', 32), 'needs_secret' => true,
        'pack' => ['id' => 'stripe', 'version' => '1.0.0'], 'budget' => ['ms' => 10000, 'source' => 'documented'],
        'scenarios' => [['name' => 'once', 'na' => null, 'deliveries' => 2], ['name' => 'twice', 'na' => null, 'deliveries' => 2]],
        'events' => [['ref' => 'cap_A', 'event_type' => 'invoice.paid', 'primary' => true], ['ref' => 'cap_B', 'event_type' => '', 'primary' => false]],
    ], $overrides);
}

function replyLine(array $reply): string
{
    return json_encode($reply, JSON_THROW_ON_ERROR)."\n";
}

function callFake(string $mode, string $reply = '', int $timeoutMs = 3000): array
{
    [$path, $proc] = fakeEngine($mode, $reply);
    try {
        $result = (new SocketEngine(new SystemClock, $path, $timeoutMs, 500))->plan(planRequest());
        $frame = @file_get_contents($path.'.frame');
    } finally {
        proc_terminate($proc);
        proc_close($proc);
        @unlink($path.'.frame');
        @unlink($path.'.reply');
        @unlink($path);
    }

    return [$result, $frame === false ? null : $frame];
}

it('sends one plan frame and returns the validated preview', function (): void {
    [$result, $frame] = callFake('reply', replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview()]));

    expect($result->error)->toBeNull()
        ->and($result->preview?->toArray())->toBe([
            'deliveryCount' => 4, 'planHash' => str_repeat('ab', 32), 'needsSecret' => true,
            'pack' => ['id' => 'stripe', 'version' => '1.0.0'], 'budget' => ['ms' => 10000, 'source' => 'documented'],
            'scenarios' => [['name' => 'once', 'na' => null, 'deliveries' => 2], ['name' => 'twice', 'na' => null, 'deliveries' => 2]],
            'events' => [['ref' => 'cap_A', 'eventType' => 'invoice.paid', 'primary' => true], ['ref' => 'cap_B', 'eventType' => '', 'primary' => false]],
        ]);
    $sent = json_decode((string) $frame, true);
    expect($sent['op'])->toBe('plan')
        ->and($sent['input']['captures'][0]['headers'])->toBe([['Content-Type', 'application/json'], ['X-B', '2'], ['x-a', '1']])
        ->and(base64_decode($sent['input']['captures'][1]['body_base64']))->toBe("\x00\xff raw")
        ->and($sent['input']['captures'][1]['order'])->toBe(3)
        ->and($sent['input']['pack'])->toBe(['id' => 'stripe', 'version' => '1.0.0'])
        ->and($sent['input'])->not->toHaveKey('has_secret');
});

it('passes the engine\'s own error codes through', function (): void {
    [$result] = callFake('reply', replyLine(['proto' => 1, 'op' => '', 'ok' => false, 'error' => ['code' => 'busy']]));
    expect($result->preview)->toBeNull()->and($result->error)->toBe('busy');

    [$result] = callFake('reply', replyLine(['proto' => 1, 'op' => 'plan', 'ok' => false, 'error' => ['code' => 'no_pack']]));
    expect($result->error)->toBe('no_pack')->and($result->transient())->toBeFalse();
});

it('maps a bad reply to bad_reply and never surfaces engine text', function (string $reply): void {
    [$result] = callFake('reply', $reply);

    expect($result->preview)->toBeNull()->and($result->error)->toBe(PlanResult::BAD_REPLY);
})->with([
    'wrong proto' => fn () => replyLine(['proto' => 2, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview()]),
    'wrong op' => fn () => replyLine(['proto' => 1, 'op' => 'verify', 'ok' => true, 'preview' => goodPreview()]),
    'malformed JSON' => fn () => "{\"proto\":1,\"op\":\"plan\",\n",
    'no newline before close' => fn () => json_encode(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview()]),
    'oversized reply' => fn () => str_repeat('a', SocketEngine::MAX_REPLY + 10)."\n",
    'error text as code' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => false, 'error' => ['code' => 'Engine exploded: <script>']]),
    'extra error field' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => false, 'error' => ['code' => 'busy', 'message' => 'x']]),
    'extra top-level key' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(), 'text' => 'hi']),
    'count above 50' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['delivery_count' => 51])]),
    'count as float' => fn () => str_replace('"delivery_count":4', '"delivery_count":4.0', replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview()])),
    'count as string' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['delivery_count' => '4'])]),
    'sum mismatch' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['delivery_count' => 3])]),
    'bad plan hash' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['plan_hash' => 'sha256:'.str_repeat('a', 64)])]),
    'other pack' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['pack' => ['id' => 'github', 'version' => '1.0.0']])]),
    'unknown ref' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['events' => [['ref' => 'cap_Z', 'event_type' => '', 'primary' => true]]])]),
    'event type outside charset' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['events' => [['ref' => 'cap_A', 'event_type' => '<b>', 'primary' => true]]])]),
    'na with prose' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['delivery_count' => 2, 'scenarios' => [['name' => 'once', 'na' => null, 'deliveries' => 2], ['name' => 'twice', 'na' => 'not verified for Stripe', 'deliveries' => 0]]])]),
    'na with deliveries' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['scenarios' => [['name' => 'once', 'na' => null, 'deliveries' => 2], ['name' => 'twice', 'na' => 'not_verified', 'deliveries' => 2]]])]),
    'unasked scenario' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['scenarios' => [['name' => 'once', 'na' => null, 'deliveries' => 2], ['name' => 'burst', 'na' => null, 'deliveries' => 2]]])]),
    'budget source text' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['budget' => ['ms' => 10000, 'source' => 'Stripe docs']])]),
    'negative budget' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['budget' => ['ms' => -1, 'source' => 'documented']])]),
    'unknown preview key' => fn () => replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview(['na_text' => 'x'])]),
]);

it('accepts the baseline `once` the engine adds to the asked scenarios (PLAN-2)', function (): void {
    $request = new PlanRequest(planRequest()->captures, ['twice'], 'stripe', '1.0.0');
    $reply = replyLine(['proto' => 1, 'op' => 'plan', 'ok' => true, 'preview' => goodPreview()]);

    expect((new ReplyDecoder)->plan(rtrim($reply), $request)->preview?->deliveryCount)->toBe(4)
        ->and((new ReplyDecoder)->plan(rtrim($reply), new PlanRequest(planRequest()->captures, ['burst'], 'stripe', '1.0.0'))->error)
        ->toBe(PlanResult::BAD_REPLY);
});

it('times out when the engine never answers', function (): void {
    [$result] = callFake('silent', '', 700);

    expect($result->error)->toBe(PlanResult::TIMEOUT);
});

it('is unavailable when the socket is missing', function (): void {
    $result = (new SocketEngine(new SystemClock, engineSocketPath(), 1000, 200))->plan(planRequest());

    expect($result->error)->toBe(PlanResult::UNAVAILABLE)->and($result->transient())->toBeTrue();
});

it('refuses a frame above 6 MiB without calling', function (): void {
    $big = new PlanRequest([['ref' => 'cap_A', 'order' => 1, 'headers' => [], 'body' => str_repeat('x', 5 << 20)]], ['once'], 'stripe', '1.0.0');

    expect((new SocketEngine(new SystemClock, engineSocketPath()))->plan($big)->error)->toBe(PlanResult::FRAME_TOO_LARGE);
});

it('plans with the real serve-sandbox', function (): void {
    $bin = getenv('WEBHOOKTRAP_ENGINE_BIN') ?: dirname(__DIR__, 3).'/agent/bin/webhooktrap';
    if (! is_executable($bin)) {
        $this->markTestSkipped('engine binary not built (cd agent && CGO_ENABLED=0 go build -o bin/webhooktrap ./cmd/webhooktrap)');
    }
    $path = engineSocketPath();
    $proc = proc_open([$bin, 'serve-sandbox', '--socket', $path], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    for ($i = 0; $i < 300 && ! file_exists($path); $i++) {
        usleep(10_000);
    }
    try {
        $body = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'invoice.paid', 'api_version' => '2024-06-20',
            'data' => ['object' => ['id' => 'in_1', 'object' => 'invoice', 'amount_paid' => 2400]]], JSON_THROW_ON_ERROR);
        $request = new PlanRequest(
            [['ref' => 'cap_01', 'order' => 1, 'headers' => [['Content-Type', 'application/json'], ['Stripe-Signature', 't=1794406143,v1=abc']], 'body' => $body]],
            ['once', 'twice', 'shuffled', 'late', 'retry', 'tampered', 'unsigned', 'burst', 'slow_budget'], 'stripe', '1.0.0',
        );
        $result = (new SocketEngine(new SystemClock, $path))->plan($request);
        $five = [];
        for ($i = 1; $i <= 5; $i++) {
            $five[] = ['ref' => "cap_0{$i}", 'order' => $i, 'headers' => $request->captures[0]['headers'], 'body' => str_replace('evt_1', "evt_{$i}", $body)];
        }
        $all5 = (new SocketEngine(new SystemClock, $path))->plan(new PlanRequest($five, $request->scenarios, 'stripe', '1.0.0'));
        $subset = (new SocketEngine(new SystemClock, $path))->plan(new PlanRequest($five, ['twice', 'burst'], 'stripe', '1.0.0'));
        $unknown = (new SocketEngine(new SystemClock, $path))->plan(new PlanRequest($request->captures, ['once'], 'no-such-pack', ''));
    } finally {
        proc_terminate($proc);
        proc_close($proc);
    }

    expect($result->error)->toBeNull()
        ->and($result->preview?->deliveryCount)->toBeGreaterThan(0)
        ->and($result->preview?->scenarios)->toHaveCount(9)
        ->and(array_sum(array_column($result->preview?->scenarios ?? [], 'deliveries')))->toBe($result->preview?->deliveryCount)
        ->and($result->preview?->events[0]['ref'])->toBe('cap_01')
        ->and($result->preview?->pack)->toBe(['id' => 'stripe', 'version' => '1.0.0'])
        ->and($unknown->error)->toBe('no_pack')
        // 5 captures × 9 scenarios stays under the 50 cap (3n + 8 at most).
        ->and($all5->error)->toBeNull()->and($all5->preview?->deliveryCount)->toBe(23)
        ->and(array_column($subset->preview?->scenarios ?? [], 'name'))->toBe(['once', 'twice', 'burst'])
        ->and($subset->preview?->deliveryCount)->toBe(5 + 2 + 5);
});
