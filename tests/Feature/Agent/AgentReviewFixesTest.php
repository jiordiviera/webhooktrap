<?php

declare(strict_types=1);

use App\Infrastructure\Ids\EntityId;
use App\Infrastructure\Persistence\LongPollSlots;
use App\Modules\Agent\Domain\Agent;
use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Contracts\AccountDeletionParticipant;
use App\Modules\Identity\Domain\Mail\IdentityMail;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Events\AccountDeleted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/AgentHelpers.php';

/*
 * A09 agent connection review, server side (project/security/
 * agent-connection-review.md): AC-3 to AC-8, AC-10, AC-12, AC-14 and the
 * decisions on dev builds and `--insecure-dev-origin` (`server.kind`).
 */

beforeEach(fn () => Mail::fake());

function reviewUuid(string $publicId): string
{
    return EntityId::decode($publicId)[1];
}

/** @param  array<string, mixed>  $overrides */
function reviewObserve(array $setup, int $seq, array $deliveries, array $overrides = []): mixed
{
    return asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", $overrides + [
        'proto' => 1, 'seq' => $seq, 'deliveries' => $deliveries, 'scenarios' => [], 'findings_provisional' => [],
    ]);
}

function reviewFinal(array $setup, array $doc): mixed
{
    return asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $doc);
}

// ---- AC-3: no silent same-name replacement --------------------------------

it('gives a colliding name a suffix and replaces nobody (AC-3)', function (): void {
    $user = makeUser();
    userProject($user);
    $old = pairedAgent(userPrincipal($user));

    $keys = agentKeys();
    $id = pairRequest(issueCode(userPrincipal($user)), $keys['public'])->assertCreated()->assertJsonPath('name', 'maya-mbp-2')->assertJsonPath('replaces', null)->json('agent_id');
    $token = tokenRequest($id, $keys['secret'])->assertOk()->json('access_token');
    asAgent($token)->postJson('/agent/v1/confirm', ['proto' => 1])->assertOk();

    asAgent($old['token'])->postJson('/agent/v1/hello', helloBody())->assertOk();
    expect(DB::table('agents')->whereNull('revoked_at')->orderBy('paired_at')->pluck('name')->all())->toBe(['maya-mbp', 'maya-mbp-2']);
});

it('suffixes names within the 48-character grammar, ignoring case (AC-3)', function (): void {
    $long = str_repeat('a', 48);
    expect(Agent::freeName('laptop', []))->toBe('laptop')
        ->and(Agent::freeName('laptop', ['Laptop']))->toBe('laptop-2')
        ->and(Agent::freeName('laptop', ['laptop', 'laptop-2']))->toBe('laptop-3')
        ->and(Agent::freeName($long, [$long]))->toBe(str_repeat('a', 46).'-2');
});

it('replaces an agent only when the owner chose it while issuing the code (AC-3)', function (): void {
    $user = makeUser();
    userProject($user);
    $old = pairedAgent(userPrincipal($user));
    $fingerprint = DB::table('agents')->value('key_fingerprint');

    actAs($user);
    $view = $this->postJson('/pairing-codes', ['replace' => $old['id']])->assertCreated()
        ->assertJsonPath('replaces', ['agentId' => $old['id'], 'name' => 'maya-mbp', 'keyFingerprint' => $fingerprint])->json();
    newBrowser();

    $keys = agentKeys();
    $id = pairRequest($view['code'], $keys['public'], ['name' => 'other-name'])->assertCreated()->assertJsonPath('name', 'maya-mbp')
        ->assertJsonPath('replaces', ['agent_id' => $old['id'], 'name' => 'maya-mbp', 'key_fingerprint' => $fingerprint])->json('agent_id');
    // The old agent works until the new one is confirmed locally.
    asAgent($old['token'])->postJson('/agent/v1/hello', helloBody())->assertOk();
    $token = tokenRequest($id, $keys['secret'])->assertOk()->json('access_token');
    asAgent($token)->postJson('/agent/v1/confirm', ['proto' => 1])->assertOk();

    asAgent($old['token'])->postJson('/agent/v1/hello', helloBody())->assertStatus(401)->assertJsonPath('reason', 'replaced');
    expect(DB::table('agents')->whereNull('revoked_at')->pluck('name')->all())->toBe(['maya-mbp'])
        ->and(DB::table('agents')->whereNull('revoked_at')->value('replaces_agent_id'))->toBeNull();
});

it('refuses a replacement of an unknown, foreign, unconfirmed or revoked agent (AC-3)', function (): void {
    $user = makeUser();
    userProject($user);
    $unconfirmed = pairedAgent(userPrincipal($user), false, 'waiting');
    $revoked = pairedAgent(userPrincipal($user), true, 'gone');
    asAgent($revoked['token'])->deleteJson('/agent/v1/agent', ['proto' => 1])->assertOk();
    $stranger = makeUser();
    userProject($stranger);
    $foreign = pairedAgent(userPrincipal($stranger));

    actAs($user);
    foreach ([$unconfirmed['id'], $revoked['id'], $foreign['id'], 'agt_01M7G7YXEQE0ZS87ZEWR3GG5TF', 'laptop', 42] as $replace) {
        $this->postJson('/pairing-codes', ['replace' => $replace])->assertStatus(422)->assertJsonPath('error', 'unknown_agent');
    }
    expect(DB::table('pairing_codes')->whereNull('used_at')->count())->toBe(0);
});

// ---- AC-4: account deletion ----------------------------------------------

it('revokes agents, deletes codes and tokens and aborts open runs in the tombstone, without the AccountDeleted listener (AC-4)', function (): void {
    $setup = runningRun();
    $user = $setup['user'];
    issueCode(userPrincipal($user));
    Event::fake([AccountDeleted::class]); // the post-commit listener never runs

    app(AccountDeletion::class)->tombstone($user, 'admin', RequestContext::console());

    expect(DB::table('agents')->whereNull('revoked_at')->count())->toBe(0)
        ->and(DB::table('agents')->value('revoked_reason'))->toBe('account_deleted')
        ->and(DB::table('pairing_codes')->count())->toBe(0)
        ->and(DB::table('agent_access_tokens')->count())->toBe(0)
        ->and(DB::table('runs')->first(['status', 'status_reason', 'exit_code']))->toMatchArray(['status' => 'aborted', 'status_reason' => 'account_deleted', 'exit_code' => 2])
        ->and(DB::table('run_jobs')->whereNull('completed_at')->count())->toBe(0);
    asAgent($setup['agent']['token'])->getJson("/agent/v1/jobs/{$setup['job']['job_id']}/captures/{$setup['job']['selection'][0]['capture_id']}/request")->assertStatus(401);
});

it('rolls the agent and run cascade back with the rest of a failed tombstone (AC-4)', function (): void {
    $setup = runningRun();
    issueCode(userPrincipal($setup['user']));
    app()->instance('test.failing-participant', new class implements AccountDeletionParticipant
    {
        public function tombstone(string $userId): void
        {
            throw new RuntimeException('tombstone failed');
        }

        public function purge(string $userId): void {}

        public function summary(string $userId): array
        {
            return [];
        }
    });
    app()->tag(['test.failing-participant'], 'identity.deletion-participants');

    expect(fn () => app(AccountDeletion::class)->tombstone($setup['user'], 'admin', RequestContext::console()))
        ->toThrow(RuntimeException::class, 'tombstone failed');
    expect(DB::table('agents')->whereNull('revoked_at')->count())->toBe(1)
        ->and(DB::table('pairing_codes')->count())->toBe(2)
        ->and(DB::table('runs')->value('status'))->toBe('running');
});

it('refuses the agents and pairing codes of a tombstoned owner on every request (DL-2, AC-4)', function (): void {
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user));
    $code = issueCode(userPrincipal($user));
    DB::table('users')->where('id', $user->id)->update(['deleted_at' => DB::raw('now()')]);

    // The agent row is still live: only the owner check refuses it.
    expect(DB::table('agents')->whereNull('revoked_at')->count())->toBe(1);
    asAgent($agent['token'])->postJson('/agent/v1/hello', helloBody())->assertStatus(401)->assertJsonPath('error', 'unauthenticated');
    tokenRequest($agent['id'], $agent['secret'])->assertStatus(401)->assertJsonPath('error', 'unauthenticated');
    pairRequest($code, agentKeys()['public'])->assertStatus(400)->assertJsonPath('error', 'invalid_code');
    expect(DB::table('agents')->count())->toBe(1);
});

it('purges agents, codes and runs with the account (AC-4)', function (): void {
    $setup = runningRun();
    $other = queuedRun();
    app(AccountDeletion::class)->tombstone($setup['user'], 'admin', RequestContext::console());

    expect(app(AccountDeletion::class)->purgeDue())->toBe(1)
        ->and(DB::table('agents')->where('id', reviewUuid($setup['agent']['id']))->count())->toBe(0)
        ->and(DB::table('runs')->where('id', reviewUuid($setup['runId']))->count())->toBe(0)
        ->and(DB::table('pairing_codes')->where('user_id', $setup['user']->id)->count())->toBe(0)
        ->and(DB::table('agents')->where('id', reviewUuid($other['agent']['id']))->whereNull('revoked_at')->count())->toBe(1)
        ->and(DB::table('runs')->where('id', reviewUuid($other['runId']))->value('status'))->toBe('queued');
});

// ---- AC-5: long-poll fairness --------------------------------------------

it('holds one long-poll slot per agent: a second concurrent poll answers 204 at once (AC-5)', function (): void {
    config(['database.connections.slot_holder' => config('database.connections.pgsql')]);
    $setup = queuedRun();
    $holder = DB::connection('slot_holder');
    expect(app(LongPollSlots::class, ['db' => $holder])->acquireKeyed('agent:'.reviewUuid($setup['agent']['id']), 1))->not->toBeNull();
    try {
        // A job is waiting, but this agent already polls on another worker.
        asAgent($setup['agent']['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertNoContent();
    } finally {
        $holder->selectOne('SELECT pg_advisory_unlock_all()');
        $holder->disconnect();
    }
    asAgent($setup['agent']['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertOk();
});

it('caps long-poll slots per workspace and sizes the global pool from the workers (AC-5)', function (): void {
    config(['database.connections.slot_holder' => config('database.connections.pgsql'), 'database.connections.slot_holder_2' => config('database.connections.pgsql')]);
    $setup = queuedRun();
    $workspaceId = (string) DB::table('agents')->value('workspace_id');
    $holder = DB::connection('slot_holder');
    $slots = app(LongPollSlots::class, ['db' => $holder]);
    // Two other workers of the same workspace (session locks are reentrant, so one holder per slot).
    $second = DB::connection('slot_holder_2');
    expect($slots->acquireKeyed('agent-workspace:'.$workspaceId, 1))->not->toBeNull()
        ->and(app(LongPollSlots::class, ['db' => $second])->acquireKeyed('agent-workspace:'.$workspaceId, 2))->not->toBeNull();
    try {
        asAgent($setup['agent']['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertStatus(503)
            ->assertJsonPath('error', 'busy')->assertHeader('Retry-After', '5');
    } finally {
        foreach ([$holder, $second] as $connection) {
            $connection->selectOne('SELECT pg_advisory_unlock_all()');
            $connection->disconnect();
        }
    }
    asAgent($setup['agent']['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertOk();

    expect(LongPollSlots::cap(4, 8))->toBe(4)
        ->and(LongPollSlots::cap(6, 8))->toBe(4)
        ->and(LongPollSlots::cap(6, 12))->toBe(6)
        ->and(LongPollSlots::cap(4, 5))->toBe(2)
        ->and(LongPollSlots::cap(4, 2))->toBe(1);
});

// ---- AC-6: final document against the stored observations ----------------

it('refuses a final document that differs from the observations stored live (AC-6)', function (array $change, string $error): void {
    $setup = runningRun();
    $cap = $setup['job']['selection'][0]['capture_id'];
    reviewObserve($setup, 1, [delivery(1, 'once', $cap, ['phase' => 'probed'])], ['delivery_count' => 4])->assertStatus(202);
    $doc = finalDocument($setup['job']);
    foreach ($change as $path => $value) {
        data_set($doc, $path, $value);
    }

    reviewFinal($setup, $doc)->assertStatus(422)->assertJsonPath('error', $error);
    expect(DB::table('runs')->value('status'))->toBe('running');
})->with([
    'status' => [['deliveries.0.status' => 201], 'observation_mismatch'],
    'transport' => [['deliveries.0.status' => null, 'deliveries.0.transport' => 'timeout'], 'observation_mismatch'],
    'latency beyond tolerance' => [['deliveries.0.latency_ms' => 200], 'observation_mismatch'],
    'probe outcome' => [['deliveries.0.state.readings.0.changed' => false], 'observation_mismatch'],
    'not completed' => [['deliveries.0.completed' => false], 'observation_mismatch'],
    'delivery count' => [['deliveries.3.n' => 3], 'delivery_count_mismatch'],
]);

it('accepts latency within the tolerance and drops a missing delivery from the count (AC-6)', function (): void {
    $setup = runningRun();
    $cap = $setup['job']['selection'][0]['capture_id'];
    reviewObserve($setup, 1, [delivery(1, 'once', $cap, ['phase' => 'probed'])], ['delivery_count' => 4])->assertStatus(202);
    $doc = finalDocument($setup['job']);
    $doc['deliveries'][0]['latency_ms'] = 124;
    reviewFinal($setup, $doc)->assertOk();

    $short = runningRun();
    reviewObserve($short, 1, [], ['delivery_count' => 5])->assertStatus(202);
    reviewFinal($short, finalDocument($short['job']))->assertStatus(422)->assertJsonPath('error', 'delivery_count_mismatch');
});

it('ties each finding level to its type (AC-6)', function (array $change): void {
    $setup = runningRun();
    $doc = finalDocument($setup['job']);
    foreach ($change as $path => $value) {
        data_set($doc, $path, $value);
    }
    reviewFinal($setup, $doc)->assertStatus(422)->assertJsonPath('error', 'level_mismatch');
})->with([
    'accepts_tampered on a 2xx as level 2' => [['findings.0.level' => 2]],
    'over_budget as level 2' => [['findings.2.level' => 2]],
    'duplicate_changed_state as level 1' => [['findings.1.level' => 1]],
]);

it('refuses a document that leaves out a Level 1 finding the deliveries imply (AC-6)', function (string $case): void {
    $setup = runningRun();
    $doc = finalDocument($setup['job']);
    if ($case === 'forged pass') {
        // A 2xx on the tampered delivery and a slow delivery, reported as passed.
        $doc['findings'] = [];
        $doc['counts'] = ['critical' => 0, 'high' => 0, 'medium' => 0, 'info' => 0, 'findings' => 0];
        $doc['run']['status'] = 'passed';
        $doc['run']['exit_code'] = 0;
    } elseif ($case === 'tampered accepted') {
        array_splice($doc['findings'], 0, 1);
        $doc['counts'] = ['critical' => 0, 'high' => 1, 'medium' => 1, 'info' => 1, 'findings' => 2];
    } elseif ($case === 'server error') {
        $doc['deliveries'][0]['status'] = 503;
    } elseif ($case === 'over budget') {
        array_splice($doc['findings'], 2, 1);
        $doc['counts'] = ['critical' => 1, 'high' => 1, 'medium' => 0, 'info' => 1, 'findings' => 2];
    }
    reviewFinal($setup, $doc)->assertStatus(422)->assertJsonPath('error', 'level1_missing');
    expect(DB::table('runs')->value('status'))->toBe('running');
})->with(['forged pass', 'tampered accepted', 'server error', 'over budget']);

it('clamps finished_at to the run\'s start and the server\'s clock (AC-6)', function (string $finishedAt, string $expected): void {
    $setup = runningRun();
    $doc = finalDocument($setup['job']);
    $doc['run']['finished_at'] = $finishedAt;
    $before = DB::table('runs')->value('started_at');
    reviewFinal($setup, $doc)->assertOk();
    $stored = strtotime((string) DB::table('runs')->value('finished_at'));

    match ($expected) {
        'started' => expect($stored)->toBe(strtotime((string) $before)),
        'now' => expect($stored)->toBeLessThanOrEqual(time() + 1)->toBeGreaterThanOrEqual(strtotime((string) $before)),
    };
})->with([
    'in the past' => ['2001-01-01T00:00:00.000Z', 'started'],
    'in the future' => ['2099-01-01T00:00:00.000Z', 'now'],
]);

// ---- AC-7: detective control ---------------------------------------------

it('emails the account "New agent paired" with machine, time, network and a revoke link; guests get nothing (AC-7)', function (): void {
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user), false);

    $mail = queuedMails('agent-paired', $user->email)->sole();
    expect($mail->params)->toMatchArray(['machine' => 'maya-mbp', 'os' => 'darwin', 'network' => '127.0.0.0/24'])
        ->and($mail->params['key_fingerprint'])->toStartWith('SHA256:')
        ->and($mail->params['time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
        ->and(mailedLink('agent-paired'))->toBe('/agents/'.$agent['id']);

    [, $key] = guestProject();
    pairedAgent(guestPrincipalOf($key), false);
    expect(Mail::queued(IdentityMail::class)->filter(fn (IdentityMail $m): bool => $m->template === 'agent-paired'))->toHaveCount(1);
});

it('lets the workspace owner revoke an agent from the browser, and nobody else (AC-7)', function (): void {
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user));
    $stranger = makeUser();
    userProject($stranger);

    newBrowser();
    $this->deleteJson("/agents/{$agent['id']}")->assertNotFound();
    actAs($stranger);
    $this->deleteJson("/agents/{$agent['id']}")->assertNotFound();
    $this->deleteJson('/agents/agt_01M7G7YXEQE0ZS87ZEWR3GG5TF')->assertNotFound();
    expect(DB::table('agents')->whereNull('revoked_at')->count())->toBe(1);

    actAs($user);
    $this->deleteJson("/agents/{$agent['id']}")->assertNoContent();
    expect(DB::table('agents')->value('revoked_reason'))->toBe('user')
        ->and(DB::table('agent_access_tokens')->count())->toBe(0);
    $this->deleteJson("/agents/{$agent['id']}")->assertNotFound();
    newBrowser();
    asAgent($agent['token'])->postJson('/agent/v1/hello', helloBody())->assertStatus(401);
});

it('revokes from an Inertia visit with a redirect back, and for a guest owner (AC-7)', function (): void {
    [$cookie, $key] = guestProject();
    $agent = pairedAgent(guestPrincipalOf($key));

    asGuest($cookie)->from("/p/{$key}/agent")->withHeader('X-Inertia', 'true')->delete("/agents/{$agent['id']}")
        ->assertStatus(303)->assertRedirect("/p/{$key}/agent")->assertSessionHas('flash.code', 'agent_revoked');
    expect(DB::table('agents')->value('revoked_reason'))->toBe('user');
});

// ---- AC-8: waiting declines are held back ---------------------------------

it('does not hand a job straight back after a needs_confirmation decline (AC-8)', function (): void {
    $setup = queuedRun();
    $token = $setup['agent']['token'];
    $job = nextJob($token);
    asAgent($token)->postJson("/agent/v1/jobs/{$job['job_id']}/decline", ['proto' => 1, 'reason' => 'needs_confirmation'])->assertOk();

    asAgent($token)->getJson('/agent/v1/jobs/next?wait=0')->assertNoContent();
    asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertJsonPath('pending_jobs', 1);
    $this->travel(31)->seconds();
    expect(nextJob($token)['job_id'])->toBe($job['job_id']);
});

// ---- AC-10: terminal text in jobs/next ------------------------------------

it('cleans the workspace label in jobs/next as in pair and hello (AC-10)', function (): void {
    $setup = queuedRun();
    DB::table('users')->where('id', $setup['user']->id)->update(['name' => "Maya\u{202E}\u{200B}\u{0007}\u{2028}\u{E0041} Lopez"]);

    expect(nextJob($setup['agent']['token'])['requester']['workspace_label'])->toBe('Account workspace · Maya Lopez');
});

// ---- AC-12: observation volume ---------------------------------------------

it('caps observation batches relative to the plan with a hard ceiling, and total bytes (AC-12)', function (): void {
    $setup = runningRun();
    reviewObserve($setup, 1, [], ['delivery_count' => 4])->assertStatus(202);
    reviewObserve($setup, 37, [])->assertStatus(422)->assertJsonPath('error', 'seq_out_of_range');
    reviewObserve($setup, 36, [])->assertStatus(202);

    config(['agent.observations.max_batches' => 10]);
    $unplanned = runningRun();
    reviewObserve($unplanned, 11, [])->assertStatus(422)->assertJsonPath('error', 'seq_out_of_range');

    // An empty batch is about 76 bytes: two fit in 200, the third does not.
    config(['agent.observations.max_bytes_per_run' => 200]);
    $bytes = runningRun();
    reviewObserve($bytes, 1, [])->assertStatus(202);
    reviewObserve($bytes, 2, [])->assertStatus(202);
    reviewObserve($bytes, 3, [])->assertStatus(413)->assertJsonPath('error', 'observations_exceeded');
    reviewObserve($bytes, 2, [])->assertStatus(202); // a retry of a stored seq is still idempotent
    expect(DB::table('run_events_inbox')->where('run_id', reviewUuid($bytes['runId']))->count())->toBe(2)
        ->and(DB::table('runs')->where('id', reviewUuid($bytes['runId']))->value('observation_bytes'))->toBeLessThanOrEqual(200);
});

it('caps provisional finding keys and checks their severity (AC-12)', function (): void {
    config(['agent.observations.max_provisional' => 2]);
    $setup = runningRun();
    $f = fn (string $key, string $type = 'server_error', string $severity = 'high'): array => [
        'key' => $key, 'type' => $type, 'severity' => $severity, 'event_type' => 'invoice.paid', 'evidence' => [['d' => 1, 'role' => 'offending']],
    ];
    reviewObserve($setup, 1, [], ['findings_provisional' => [$f('a'), $f('b')]])->assertStatus(202);
    reviewObserve($setup, 2, [], ['findings_provisional' => [$f('a')]])->assertStatus(202);
    reviewObserve($setup, 3, [], ['findings_provisional' => [$f('c')]])->assertStatus(422)->assertJsonPath('error', 'too_many_provisional');
    reviewObserve($setup, 4, [], ['findings_provisional' => [$f('a', 'server_error', 'critical')]])->assertStatus(422)->assertJsonPath('error', 'severity_mismatch');
    expect(array_keys(json_decode((string) DB::table('runs')->value('provisional_findings'), true)))->toBe(['a', 'b']);
});

// ---- AC-14, dev builds, server kind ---------------------------------------

it('shows the pairing command as `webhooktrap pair <CODE>` (AC-14)', function (): void {
    $user = makeUser();
    userProject($user);
    actAs($user);
    $view = $this->postJson('/pairing-codes')->assertCreated()->assertJsonPath('replaces', null)->json();

    expect($view['command'])->toBe('webhooktrap pair '.$view['code'].' --server '.TEST_ORIGIN);
});

it('refuses 0.0.0-dev agents unless the switch is on in local or testing, with a warning (decision 3)', function (): void {
    $user = makeUser();
    userProject($user);
    pairRequest(issueCode(userPrincipal($user)), agentKeys()['public'], ['version' => '0.0.0-dev'])
        ->assertStatus(426)->assertJsonPath('error', 'agent_too_old');
    $agent = pairedAgent(userPrincipal($user));
    asAgent($agent['token'])->postJson('/agent/v1/hello', helloBody(['version' => '0.0.0-dev']))->assertStatus(426);

    config(['agent.allow_dev_builds' => true]);
    app()['env'] = 'production';
    try {
        pairRequest(issueCode(userPrincipal($user)), agentKeys()['public'], ['version' => '0.0.0-dev'])->assertStatus(426);
    } finally {
        app()['env'] = 'testing';
    }

    Log::spy();
    pairRequest(issueCode(userPrincipal($user)), agentKeys()['public'], ['version' => '0.0.0-dev', 'name' => 'dev-box'])->assertCreated();
    asAgent($agent['token'])->postJson('/agent/v1/hello', helloBody(['version' => '0.0.0-dev']))->assertOk();
    Log::shouldHaveReceived('warning')->with('Agent dev build accepted', ['handshake' => 'pair'])->once();
    Log::shouldHaveReceived('warning')->with('Agent dev build accepted', ['handshake' => 'hello'])->once();

    // A release build below the minimum is refused whatever the switch says.
    pairRequest(issueCode(userPrincipal($user)), agentKeys()['public'], ['version' => '0.8.9'])->assertStatus(426);
});

it('reports server.kind in pair and hello: hosted, or selfhost for self-host and local servers (decision 6)', function (): void {
    $user = makeUser();
    userProject($user);
    $keys = agentKeys();
    $id = pairRequest(issueCode(userPrincipal($user)), $keys['public'])->assertCreated()->assertJsonPath('server.kind', 'hosted')
        ->assertJsonMissingPath('deployment')->json('agent_id');
    $token = tokenRequest($id, $keys['secret'])->assertOk()->json('access_token');
    asAgent($token)->postJson('/agent/v1/confirm', ['proto' => 1])->assertOk();
    asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertOk()->assertJsonPath('server', ['kind' => 'hosted']);

    config(['identity.instance' => 'selfhost']);
    pairRequest(issueCode(userPrincipal($user)), agentKeys()['public'], ['name' => 'two'])->assertJsonPath('server.kind', 'selfhost');
    asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertJsonPath('server.kind', 'selfhost');

    config(['identity.instance' => 'hosted']);
    app()['env'] = 'local';
    try {
        pairRequest(issueCode(userPrincipal($user)), agentKeys()['public'], ['name' => 'three'])->assertJsonPath('server.kind', 'selfhost');
    } finally {
        app()['env'] = 'testing';
    }
});

// ---- A07 end-to-end: the "server answered 500" -------------------------------

it('answers a server fault on the agent API in the protocol shape, reported, with Retry-After', function (): void {
    // Reproduces A07's run: the code expects `run_jobs.offer_after`, the
    // database had not been migrated yet, and jobs/next failed with a bare 500.
    $setup = queuedRun();
    Exceptions::fake();
    DB::statement('ALTER TABLE run_jobs RENAME COLUMN offer_after TO offer_after_missing');

    asAgent($setup['agent']['token'])->getJson('/agent/v1/jobs/next?wait=0')
        ->assertStatus(500)->assertExactJson(['proto' => 1, 'error' => 'internal_error'])
        ->assertHeader('Retry-After', '5')->assertHeader('Cache-Control', 'no-store, private');
    Exceptions::assertReported(QueryException::class);
    // Nothing else runs here: PostgreSQL aborted the test transaction, which rolls back the rename.
});
