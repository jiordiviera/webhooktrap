<?php

declare(strict_types=1);

use App\Modules\Agent\Application\Pairing;
use App\Modules\Agent\Domain\AuthMessage;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Identity\Domain\User;
use App\Modules\Workspace\Contracts\OwnedProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__).'/Capture/CaptureHelpers.php';

/*
 * Agent connection helpers: a PHP-side Ed25519 test key pair plays the
 * agent (contracts §5). Loaded once by the agent test files.
 */

const TEST_ORIGIN = 'https://webhooktrap.test';

/** @return array{secret: string, public: string} */
function agentKeys(): array
{
    $pair = sodium_crypto_sign_keypair();

    return ['secret' => sodium_crypto_sign_secretkey($pair), 'public' => base64_encode(sodium_crypto_sign_publickey($pair))];
}

function issueCode(Principal $principal): string
{
    return app(Pairing::class)->issue($principal)['code'];
}

function userPrincipal(User $user): Principal
{
    return new Principal('user', $user->id);
}

function guestPrincipalOf(string $projectKey): Principal
{
    $id = DB::table('projects')->join('workspaces', 'workspaces.id', '=', 'projects.workspace_id')
        ->where('project_key', $projectKey)->value('workspaces.guest_session_id');

    return new Principal('guest', (string) $id);
}

/** @param  array<string, mixed>  $overrides */
function pairRequest(string $code, string $publicKey, array $overrides = []): TestResponse
{
    return test()->postJson('/agent/v1/pair', $overrides + [
        'proto' => 1, 'code' => $code, 'public_key' => $publicKey, 'name' => 'maya-mbp',
        'version' => '0.9.2', 'os' => 'darwin', 'arch' => 'arm64', 'proto_min' => 1, 'proto_max' => 1,
    ]);
}

/** Signs the challenge for `$origin` and asks for a token. */
function tokenRequest(string $agentId, string $secret, string $origin = TEST_ORIGIN, ?array $challenge = null): TestResponse
{
    $challenge ??= test()->postJson('/agent/v1/auth/challenge', ['proto' => 1, 'agent_id' => $agentId])->assertOk()->json();
    $message = AuthMessage::bytes($origin, $agentId, (string) base64_decode($challenge['nonce']), $challenge['expires_at']);

    return test()->postJson('/agent/v1/auth/token', [
        'proto' => 1, 'agent_id' => $agentId, 'nonce' => $challenge['nonce'],
        'signature' => base64_encode(sodium_crypto_sign_detached($message, $secret)),
    ]);
}

function asAgent(string $token): mixed
{
    return test()->withHeader('Authorization', 'Bearer '.$token);
}

/** @return list<array<string, mixed>> */
function localTargets(int $port = 3000): array
{
    return [['scheme' => 'http', 'host' => 'localhost', 'port' => $port, 'kind' => 'local', 'declared' => true, 'approved' => true]];
}

/**
 * A paired agent: pair, token, and unless told otherwise confirm and hello.
 *
 * @return array{id: string, token: string, secret: string, public: string}
 */
function pairedAgent(Principal $principal, bool $confirm = true, string $name = 'maya-mbp'): array
{
    $keys = agentKeys();
    $id = pairRequest(issueCode($principal), $keys['public'], ['name' => $name])->assertCreated()->json('agent_id');
    $token = tokenRequest($id, $keys['secret'])->assertOk()->json('access_token');
    if ($confirm) {
        asAgent($token)->postJson('/agent/v1/confirm', ['proto' => 1])->assertOk();
        asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertOk();
    }

    return ['id' => $id, 'token' => $token, 'secret' => $keys['secret'], 'public' => $keys['public']];
}

/** @return array<string, mixed> */
function helloBody(array $overrides = []): array
{
    return $overrides + [
        'proto' => 1, 'version' => '0.9.2', 'proto_min' => 1, 'proto_max' => 1,
        'secrets' => [['provider' => 'stripe', 'env_name' => 'STRIPE_WEBHOOK_SECRET']],
        'targets' => localTargets(), 'probe_config_hash' => null, 'busy_run_id' => null,
    ];
}

/** A Stripe-shaped capture (derived as `stripe` by the pack). */
function stripeCapture(string $projectId, string $type = 'invoice.paid', string $id = 'evt_1'): int
{
    sendRaw('POST', '/c/'.activeKey($projectId), json_encode([
        'id' => $id, 'object' => 'event', 'type' => $type, 'api_version' => '2024-06-20',
        'data' => ['object' => ['id' => 'in_1QKImRcZJ8HR9BZYostEV4ui', 'object' => 'invoice', 'subscription' => 'sub_1QH3UF1HXb5oPQT3wfdt4drI', 'amount_paid' => 2400]],
    ], JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json', 'Stripe-Signature' => 't=1794406143,v1=abc'])->assertOk();

    return lastCapture($projectId)->seq;
}

/** Owner of a user project, through the real route. */
function startRunAsUser(User $user, string $projectKey, string $agentId, array $seqs, string $targetRef = 'http://localhost:3000/webhooks/stripe'): TestResponse
{
    actAs($user);

    return test()->post("/p/{$projectKey}/runs", [
        'from' => $seqs, 'target' => ['kind' => 'agent', 'agentId' => $agentId, 'targetRef' => $targetRef], 'failOn' => 'high',
    ]);
}

function runIdFrom(TestResponse $response): string
{
    return (string) str((string) $response->headers->get('Location'))->afterLast('/r/');
}

/** The current job of an agent, through jobs/next. */
function nextJob(string $token): array
{
    return asAgent($token)->getJson('/agent/v1/jobs/next?wait=0')->assertOk()->json();
}

/** @return array<string, mixed> one delivery of an observation batch or a final document */
function delivery(int $n, string $scenario, string $captureId, array $overrides = []): array
{
    return $overrides + [
        'n' => $n, 'scenario' => $scenario, 'event_ref' => $captureId, 'mode' => 'normal', 'offset_ms' => 100 * $n,
        'signing' => ['kind' => 'resigned'], 'tamper' => null, 'status' => 200, 'transport' => null, 'latency_ms' => 120,
        'over_budget' => false, 'response' => ['content_type' => 'application/json', 'body_sha256' => str_repeat('a', 64), 'body_bytes' => 17],
        'state' => ['readings' => [['name' => 'orders.count', 'changed' => true, 'matches_expected' => null, 'flagged' => false,
            'display' => ['before' => '0', 'after' => '1', 'expected' => null]]], 'failed' => false, 'unsettled' => false],
        'result' => 'pass', 'completed' => true,
    ];
}

/**
 * A hosted final document with four findings (F1–F4 of the Brindle run):
 * accepts_tampered (critical), duplicate_changed_state (high),
 * over_budget (medium), probe_gap (info).
 *
 * @return array<string, mixed>
 */
function finalDocument(array $job, array $overrides = []): array
{
    $cap = $job['selection'][0]['capture_id'];
    $deliveries = [
        delivery(1, 'once', $cap),
        delivery(2, 'twice', $cap, ['mode' => 'duplicate', 'state' => ['readings' => [['name' => 'orders.count', 'changed' => true, 'matches_expected' => false, 'flagged' => true, 'display' => ['before' => '1', 'after' => '2', 'expected' => '1']]], 'failed' => false, 'unsettled' => false], 'result' => 'finding']),
        delivery(3, 'tampered', $cap, ['mode' => 'tampered', 'tamper' => ['path' => 'data.object.amount_paid', 'from' => '2400', 'to' => '0'], 'result' => 'finding']),
        delivery(4, 'slow_budget', $cap, ['latency_ms' => 12400, 'over_budget' => true, 'result' => 'finding']),
    ];
    $finding = fn (string $id, string $type, string $severity, int $level, array $evidence, array $params, array $measured = [], string $lc = '') => [
        'id' => $id, 'type' => $type, 'fingerprint' => substr(hash('sha256', $type), 0, 12), 'severity' => $severity, 'level' => $level,
        'event_type' => 'invoice.paid', 'scenarios' => [], 'evidence' => $evidence, 'likely_cause_rule' => $lc,
        'framework' => 'express', 'params' => $params, 'measured' => $measured,
    ];

    return array_replace_recursive([
        'proto' => 1, 'schema_version' => '1.0.0-draft.1', 'profile' => 'hosted',
        'run' => [
            'id' => $job['run_id'], 'status' => 'findings', 'status_reason' => null, 'exit_code' => 1, 'fail_on' => 'high',
            'fail_on_inconclusive' => false, 'started_at' => '2026-11-15T10:00:00.000Z', 'finished_at' => '2026-11-15T10:00:43.194Z',
            'duration_ms' => 43194, 'seed' => $job['seed'], 'plan_hash' => str_repeat('b', 64), 'provider' => 'stripe',
            'pack' => ['id' => 'stripe', 'version' => '1.0.0'], 'budget' => ['ms' => 10000, 'source' => 'default', 'label' => '10 s budget · Webhook Trap default'],
            'target' => ['scheme' => 'http', 'host' => 'localhost', 'port' => 3000, 'path' => '/webhooks/stripe'],
            'provenance' => ['executor' => 'agent', 'source' => 'web', 'agent_name' => 'maya-mbp'],
            'agent' => ['name' => 'maya-mbp', 'version' => '0.9.2'], 'framework' => ['name' => 'express', 'detected_from' => 'x_powered_by'],
            'levels' => [1, 2], 'probe_entries' => [['name' => 'orders.count', 'display_mode' => 'count']], 'probe_display_policy' => 'strict',
            'label_bits' => 24, 'reset_configured' => true, 'order_dependent' => false, 'side_effects_acknowledged' => false,
        ],
        'events' => [['ref' => $cap, 'provider_event_id' => 'evt_1', 'event_type' => 'invoice.paid', 'capture_order' => 0, 'body_sha256' => $job['selection'][0]['body_sha256'], 'primary' => true]],
        'scenarios' => [
            ['name' => 'once', 'result' => 'pass', 'reason' => null, 'deliveries' => [1], 'findings' => [], 'reading' => ['kind' => 'change', 'probe' => 'orders.count', 'display' => ['before' => '0', 'after' => '1']]],
            ['name' => 'twice', 'result' => 'finding', 'reason' => null, 'deliveries' => [2], 'findings' => ['f2'], 'reading' => null],
        ],
        'deliveries' => $deliveries,
        'findings' => [
            $finding('f1', 'accepts_tampered', 'critical', 1, [['d' => 3, 'role' => 'offending']], ['event_type' => 'invoice.paid', 'd' => 3, 'status' => 200, 'latency_ms' => 120, 'tamper' => ['path' => 'data.object.amount_paid', 'from' => '2400', 'to' => '0'], 'probe' => 'orders.count', 'before' => '0', 'after' => '1'], [], 'LC-1'),
            $finding('f2', 'duplicate_changed_state', 'high', 2, [['d' => 1, 'role' => 'reference'], ['d' => 2, 'role' => 'offending']], ['event_type' => 'invoice.paid', 'd' => 2, 'probe' => 'orders.count', 'expected' => '1'], [['d' => 2, 'probe' => 'orders.count', 'before' => '1', 'after' => '2', 'expected' => '1']], 'LC-8'),
            $finding('f3', 'over_budget', 'medium', 1, [['d' => 4, 'role' => 'offending']], ['event_type' => 'invoice.paid', 'd' => 4, 'slow_count' => 1, 'valid_count' => 4], [['d' => 4, 'latency_ms' => 12400]]),
            $finding('f4', 'probe_gap', 'info', 2, [], ['object_type' => 'subscription']),
        ],
        'counts' => ['critical' => 1, 'high' => 1, 'medium' => 1, 'info' => 1, 'findings' => 3],
        'not_checked' => [['code' => 'side_effects']],
        'warnings' => [],
        'plan' => null,
    ], $overrides);
}

/**
 * User, project, two Stripe captures, a confirmed agent and a queued run.
 *
 * @return array{user: User, project: OwnedProject, agent: array{id: string, token: string, secret: string, public: string}, runId: string}
 */
function queuedRun(): array
{
    $user = makeUser();
    $project = userProject($user);
    $seqs = [stripeCapture($project->id), stripeCapture($project->id, 'invoice.paid', 'evt_2')];
    $agent = pairedAgent(userPrincipal($user));
    $response = startRunAsUser($user, $project->key, $agent['id'], $seqs)->assertRedirect();
    newBrowser();

    return ['user' => $user, 'project' => $project, 'agent' => $agent, 'runId' => runIdFrom($response)];
}

/** A queued run, accepted by its agent; returns the job too. */
function runningRun(): array
{
    $setup = queuedRun();
    $job = nextJob($setup['agent']['token']);
    asAgent($setup['agent']['token'])->postJson("/agent/v1/jobs/{$job['job_id']}/accept", ['proto' => 1])->assertOk();

    return $setup + ['job' => $job];
}
