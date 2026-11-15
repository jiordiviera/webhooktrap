<?php

declare(strict_types=1);

use App\Infrastructure\Ids\EntityId;
use App\Infrastructure\Persistence\LongPollSlots;
use App\Modules\Proving\Application\RunJobs;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/AgentHelpers.php';

/*
 * Job, capture, observation and final refusals (contracts §5.4–§5.7;
 * TM-16, TM-35, TM-37, TM-40, PV-11) and run creation refusals (§3.2).
 */

it('gives one agent nothing of another agent\'s job or captures (TM-35, TM-37)', function (): void {
    $setup = queuedRun();
    $job = nextJob($setup['agent']['token']);
    $intruder = pairedAgent(userPrincipal($setup['user']), true, 'intruder');

    asAgent($intruder['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertNoContent();
    asAgent($intruder['token'])->postJson("/agent/v1/jobs/{$job['job_id']}/accept", ['proto' => 1])->assertNotFound();
    asAgent($intruder['token'])->postJson("/agent/v1/jobs/{$job['job_id']}/decline", ['proto' => 1, 'reason' => 'busy'])->assertNotFound();
    asAgent($intruder['token'])->getJson("/agent/v1/jobs/{$job['job_id']}/captures/{$job['selection'][0]['capture_id']}/request")->assertNotFound();
    asAgent($intruder['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", ['proto' => 1, 'seq' => 1, 'deliveries' => [], 'scenarios' => [], 'findings_provisional' => []])->assertNotFound();
    asAgent($intruder['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", finalDocument($job))->assertNotFound();
    expect(DB::table('capture_access_log')->count())->toBe(0);
});

it('serves only captures the job references (TM-35)', function (): void {
    $setup = queuedRun();
    $job = nextJob($setup['agent']['token']);
    $other = stripeCapture($setup['project']->id, 'invoice.paid', 'evt_9');
    $otherId = DB::table('captures')->where('seq', $other)->value('id');
    $public = EntityId::encode('cap', (string) $otherId);

    asAgent($setup['agent']['token'])->getJson("/agent/v1/jobs/{$job['job_id']}/captures/{$public}/request")->assertNotFound();
    asAgent($setup['agent']['token'])->getJson("/agent/v1/jobs/{$job['job_id']}/captures/{$job['selection'][0]['capture_id']}/request")->assertOk();

    $this->travel(11)->minutes();
    asAgent($setup['agent']['token'])->postJson('/agent/v1/hello', helloBody());
    expect(app(RunJobs::class)->expire())->toBe(1);
    expect(DB::table('runs')->value('status'))->toBe('aborted')->and(DB::table('runs')->value('status_reason'))->toBe('agent_lost');
});

it('accepts a job once (409 after)', function (): void {
    $setup = runningRun();
    asAgent($setup['agent']['token'])->postJson("/agent/v1/jobs/{$setup['job']['job_id']}/accept", ['proto' => 1])->assertStatus(409);
    expect(DB::table('guest_sessions')->count())->toBe(0);
});

it('keeps a declined run Queued for a terminal confirmation, or ends it Couldn\'t start', function (): void {
    $setup = queuedRun();
    $job = nextJob($setup['agent']['token']);
    asAgent($setup['agent']['token'])->postJson("/agent/v1/jobs/{$job['job_id']}/decline", ['proto' => 1, 'reason' => 'needs_confirmation'])->assertOk();
    expect(DB::table('runs')->first(['status', 'status_reason']))->toMatchArray(['status' => 'queued', 'status_reason' => 'needs_confirmation']);

    asAgent($setup['agent']['token'])->postJson("/agent/v1/jobs/{$job['job_id']}/decline", ['proto' => 1, 'reason' => 'target_not_allowed'])->assertOk();
    expect(DB::table('runs')->first(['status', 'status_reason', 'exit_code']))->toMatchArray(['status' => 'couldnt_start', 'status_reason' => 'target_not_allowed', 'exit_code' => 2]);
});

it('ends an accepted run Aborted when the agent aborts it', function (): void {
    $setup = runningRun();
    asAgent($setup['agent']['token'])->postJson("/agent/v1/jobs/{$setup['job']['job_id']}/abort", ['proto' => 1, 'reason' => 'capture_verification_failed'])->assertOk();
    expect(DB::table('runs')->first(['status', 'status_reason']))->toMatchArray(['status' => 'aborted', 'status_reason' => 'capture_verification_failed']);
});

it('ignores a replayed observation seq and refuses one past the cap (TM-37)', function (): void {
    $setup = runningRun();
    $cap = $setup['job']['selection'][0]['capture_id'];
    $batch = ['proto' => 1, 'seq' => 3, 'deliveries' => [delivery(1, 'once', $cap, ['phase' => 'responded', 'completed' => false, 'state' => null, 'result' => null])], 'scenarios' => [], 'findings_provisional' => []];
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", $batch)->assertStatus(202);
    $seq = DB::table('runs')->value('last_seq');
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", $batch)->assertStatus(202)->assertJsonPath('seq', 3);
    expect(DB::table('runs')->value('last_seq'))->toBe($seq)->and(DB::table('run_events_inbox')->count())->toBe(1);

    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", ['seq' => 999] + $batch)->assertStatus(422)->assertJsonPath('error', 'seq_out_of_range');
});

it('stores a display value outside PV-2 as #invalid (PV-11)', function (): void {
    $setup = runningRun();
    $cap = $setup['job']['selection'][0]['capture_id'];
    $d = delivery(1, 'once', $cap, ['phase' => 'probed', 'state' => ['readings' => [['name' => 'orders.count', 'changed' => true, 'matches_expected' => null, 'flagged' => false,
        'display' => ['before' => 'maya@ex.com', 'after' => '12345', 'expected' => null]]], 'failed' => false, 'unsettled' => false]]);
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", ['proto' => 1, 'seq' => 1, 'deliveries' => [$d], 'scenarios' => [], 'findings_provisional' => []])->assertStatus(202);
    $state = json_decode((string) DB::table('run_deliveries')->value('state'), true);
    expect($state['readings'][0]['display'])->toEqual(['before' => '#invalid', 'after' => '#invalid', 'expected' => null]);
});

it('refuses an oversize observation batch with 413', function (): void {
    config(['agent.limits.observations' => 2048]);
    $setup = runningRun();
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", ['proto' => 1, 'seq' => 1, 'pad' => str_repeat('x', 4096)])->assertStatus(413);
});

it('keeps the first final document: same hash 200, different 409 (TM-37)', function (): void {
    $setup = runningRun();
    $doc = finalDocument($setup['job']);
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $doc)->assertOk();
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $doc)->assertOk();
    $doc['run']['duration_ms'] = 1;
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $doc)->assertStatus(409)->assertJsonPath('error', 'run_final');
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", ['proto' => 1, 'seq' => 9, 'deliveries' => [], 'scenarios' => [], 'findings_provisional' => []])
        ->assertStatus(409)->assertJsonPath('error', 'run_final');
});

it('checks counts, severities, exit code and Level 1 consistency of the final document (S-5)', function (array $change, string $error): void {
    $setup = runningRun();
    $doc = finalDocument($setup['job']);
    foreach ($change as $path => $value) {
        data_set($doc, $path, $value);
    }
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $doc)->assertStatus(422)->assertJsonPath('error', $error);
    expect(DB::table('runs')->value('status'))->toBe('running');
})->with([
    'counts' => [['counts.high' => 2, 'counts.findings' => 4], 'counts_mismatch'],
    'severity' => [['findings.0.severity' => 'high', 'counts.critical' => 0, 'counts.high' => 2], 'severity_mismatch'],
    'exit code' => [['run.exit_code' => 0], 'exit_code_mismatch'],
    'level 1' => [['deliveries.2.status' => 400], 'level1_inconsistent'],
    'other run' => [['run.id' => 'run_01M7G7YXEQE0ZS87ZEWR3GG5TF'], 'run_mismatch'],
    'evidence' => [['findings.0.evidence.0.d' => 9], 'unknown_delivery'],
]);

it('never displays agent-authored finding text (TM-16)', function (): void {
    $setup = runningRun();
    $doc = finalDocument($setup['job']);

    // Prose fields have no place in the hosted document.
    $withProse = $doc;
    $withProse['findings'][0]['title'] = '<img src=x onerror=alert(1)>';
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $withProse)->assertStatus(422)->assertJsonPath('error', 'schema');
    // Free strings in params follow their grammar.
    $badParam = $doc;
    $badParam['findings'][0]['params']['event_type'] = '<script>alert(1)</script>';
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $badParam)->assertStatus(422);
    // A citation URL in the document is not a field either.
    $badCitation = $doc;
    $badCitation['findings'][0]['citation'] = ['label' => 'x', 'url' => 'https://evil.example'];
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $badCitation)->assertStatus(422);

    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", $doc)->assertOk();
    actAs($setup['user']);
    $page = $this->get("/r/{$setup['runId']}")->assertOk();
    expect($page->getContent())->not->toContain('evil.example')->not->toContain('onerror')
        ->and(DB::getSchemaBuilder()->getColumnListing('findings'))->not->toContain('title', 'what', 'why', 'citation');
});

it('answers 503 with Retry-After when every long-poll slot is held (TM-40)', function (): void {
    config(['agent.longpoll.slots' => 1, 'database.connections.slot_holder' => config('database.connections.pgsql')]);
    $setup = queuedRun();
    $holder = DB::connection('slot_holder');
    $slot = app(LongPollSlots::class, ['db' => $holder])->acquire('agent', 1);
    expect($slot)->not->toBeNull();
    try {
        asAgent($setup['agent']['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertStatus(503)->assertHeader('Retry-After', '5');
    } finally {
        $holder->selectOne('SELECT pg_advisory_unlock_all()');
        $holder->disconnect();
    }
    asAgent($setup['agent']['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertOk();
});

it('refuses a run on an undeclared target, a provider mix or more than 5 events', function (): void {
    $user = makeUser();
    $project = userProject($user);
    $seq = stripeCapture($project->id);
    $agent = pairedAgent(userPrincipal($user));

    startRunAsUser($user, $project->key, $agent['id'], [$seq], 'http://localhost:4000/x')->assertSessionHasErrors(['run' => 'undeclared_target']);
    startRunAsUser($user, $project->key, $agent['id'], [$seq], 'http://169.254.169.254:3000/x')->assertSessionHasErrors(['run' => 'undeclared_target']);
    startRunAsUser($user, $project->key, $agent['id'], [1, 2, 3, 4, 5, 6])->assertSessionHasErrors(['run' => 'cap']);
    sendRaw('POST', '/c/'.activeKey($project->id), '{"a":1}', ['Content-Type' => 'application/json'])->assertOk();
    startRunAsUser($user, $project->key, $agent['id'], [$seq, $seq + 1])->assertSessionHasErrors(['run' => 'provider_mix']);
    $stranger = pairedAgent(userPrincipal(makeUser()), true, 'stranger');
    startRunAsUser($user, $project->key, $stranger['id'], [$seq])->assertSessionHasErrors(['run' => 'no_agent']);
    expect(DB::table('runs')->count())->toBe(0);
});

it('checks the guest run quota without counting it, then counts accepted and finished runs (A-13)', function (): void {
    [$cookie, $key] = guestProject();
    $guest = guestPrincipalOf($key);
    $seq = stripeCapture(projectIdOfKey($key));
    $agent = pairedAgent($guest);

    $response = asGuest($cookie)->post("/p/{$key}/runs", ['from' => [$seq], 'target' => ['kind' => 'agent', 'agentId' => $agent['id'], 'targetRef' => 'http://localhost:3000/h']])->assertRedirect();
    $runId = runIdFrom($response);
    expect(DB::table('guest_sessions')->value('accepted_runs'))->toBe(0);
    $job = nextJob($agent['token']);
    asAgent($agent['token'])->postJson("/agent/v1/jobs/{$job['job_id']}/accept", ['proto' => 1])->assertOk();
    expect(DB::table('guest_sessions')->value('accepted_runs'))->toBe(1);
    $doc = finalDocument($job, ['run' => ['target' => ['path' => '/h']]]);
    asAgent($agent['token'])->postJson("/agent/v1/runs/{$runId}/final", $doc)->assertOk();
    expect(DB::table('guest_sessions')->value('counted_runs'))->toBe(1);

    DB::table('guest_sessions')->update(['counted_runs' => 10]);
    asGuest($cookie)->post("/p/{$key}/runs", ['from' => [$seq], 'target' => ['kind' => 'agent', 'agentId' => $agent['id'], 'targetRef' => 'http://localhost:3000/h']])
        ->assertSessionHasErrors(['run' => 'quota']);
});

it('shows a run page to its owner only, with the same not-found for everyone else', function (): void {
    $setup = queuedRun();
    $runId = $setup['runId'];

    actAs($setup['user']);
    $this->get("/r/{$runId}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Run/Show')->where('run.status', 'queued'));
    $this->get("/r/{$runId}/D1")->assertOk();

    $this->get('/r/run_01M7G7YXEQE0ZS87ZEWR3GG5TF')->assertNotFound();
    actAs(makeUser());
    $this->get("/r/{$runId}")->assertNotFound();
    $this->get("/p/{$setup['project']->key}/runs")->assertNotFound();
    $this->get("/p/{$setup['project']->key}/runs/1")->assertNotFound();
    newBrowser();
    $this->get("/r/{$runId}")->assertNotFound();
    [$cookie] = guestProject();
    asGuest($cookie)->get("/r/{$runId}")->assertNotFound();
    $this->get('/r/run_bad')->assertNotFound();
});

it('lets only the run owner subscribe to its timeline channel', function (): void {
    config(['broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's']);
    $setup = queuedRun();
    $uuid = DB::table('runs')->value('id');

    actAs($setup['user']);
    $this->post('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => "private-run.{$uuid}"])->assertOk();
    actAs(makeUser());
    $this->post('/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => "private-run.{$uuid}"])->assertForbidden();
});
