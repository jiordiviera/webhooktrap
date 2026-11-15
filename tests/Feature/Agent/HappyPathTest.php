<?php

declare(strict_types=1);

use App\Modules\Packs\Application\PackCatalog;
use App\Modules\Proving\Domain\FindingProse;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/AgentHelpers.php';

/*
 * Decision 64: pairing → confirm → hello → job → accept → observations →
 * final, with a PHP-side Ed25519 key pair as the agent, and the result on
 * the run board (`/r/{runId}`, same props as `/r/example`).
 */

it('runs the whole agent connection and shows the run on the board', function (): void {
    $user = makeUser();
    $project = userProject($user);
    $seqs = [stripeCapture($project->id), stripeCapture($project->id, 'invoice.paid', 'evt_2')];

    // Pairing code from the browser.
    actAs($user);
    $code = $this->postJson('/pairing-codes')->assertCreated()->assertJsonStructure(['code', 'expiresAt', 'command'])->json('code');
    expect($code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{5}-[0-9A-HJKMNP-TV-Z]{5}$/');
    newBrowser();

    // pair → challenge → token.
    $keys = agentKeys();
    $pair = pairRequest(strtolower(str_replace('-', '', $code)), $keys['public'])->assertCreated()
        ->assertJsonPath('proto', 1)->assertJsonPath('needs_reconfirm', true)->assertJsonPath('guest', false)
        ->assertJsonPath('server.origin', TEST_ORIGIN)->json();
    $token = tokenRequest($pair['agent_id'], $keys['secret'])->assertOk()->assertJsonPath('proto', 1)
        ->assertJsonPath('broadcast.auth_endpoint', '/agent/v1/broadcasting/auth')->json('access_token');

    // confirm → hello (mirrors stored only now).
    asAgent($token)->postJson('/agent/v1/confirm', ['proto' => 1])->assertOk()->assertExactJson(['proto' => 1]);
    asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertOk()
        ->assertJsonPath('pending_jobs', 0)->assertJsonPath('needs_reconfirm', false);
    expect(DB::table('agent_targets')->count())->toBe(1)->and(DB::table('agent_secret_names')->value('env_name'))->toBe('STRIPE_WEBHOOK_SECRET');

    // The draft lists the declared target; the run is created from the web.
    actAs($user);
    $this->get("/p/{$project->key}/runs/new?from=".implode(',', $seqs))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Runs/Draft', false)
            ->where('targets.0.agentId', $pair['agent_id'])->where('targets.0.targetRef', 'http://localhost:3000')
            ->where('refusals', [])->has('selection', 2)->where('agent.state', 'connected'));
    $response = startRunAsUser($user, $project->key, $pair['agent_id'], $seqs)->assertRedirect();
    $runId = runIdFrom($response);
    expect($runId)->toMatch('/^run_/');
    newBrowser();

    // hello now counts the job; jobs/next returns it.
    asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertJsonPath('pending_jobs', 1);
    $job = nextJob($token);
    expect($job)->toMatchArray(['proto' => 1, 'kind' => 'run', 'run_id' => $runId, 'run_number' => 1, 'remote' => true,
        'target_ref' => 'http://localhost:3000/webhooks/stripe', 'pack' => ['id' => 'stripe', 'version' => '1.0.0'], 'fail_on' => 'high', 'delivery_count' => null])
        ->and($job['selection'])->toHaveCount(2)->and($job['selection'][0]['body_sha256'])->toMatch('/^[0-9a-f]{64}$/');

    // Capture fetch before accept (the agent plans locally), logged per agent.
    $capture = asAgent($token)->getJson("/agent/v1/jobs/{$job['job_id']}/captures/{$job['selection'][0]['capture_id']}/request")->assertOk()->json();
    expect(hash('sha256', base64_decode($capture['body_base64'])))->toBe($job['selection'][0]['body_sha256'])
        ->and(DB::table('capture_access_log')->count())->toBe(1);

    asAgent($token)->postJson("/agent/v1/jobs/{$job['job_id']}/accept", ['proto' => 1])->assertOk();
    expect(DB::table('runs')->value('status'))->toBe('running');

    // Observations, seq 1 with the plan facts, seq 2 later.
    $cap = $job['selection'][0]['capture_id'];
    asAgent($token)->postJson("/agent/v1/runs/{$runId}/observations", [
        'proto' => 1, 'seq' => 1, 'plan' => ['deliveries' => [1, 2, 3, 4]], 'plan_hash' => str_repeat('b', 64), 'delivery_count' => 4,
        'probe_entries' => [['name' => 'orders.count', 'display_mode' => 'count']], 'label_bits' => 24, 'probe_display_policy' => 'strict',
        'deliveries' => [delivery(1, 'once', $cap, ['phase' => 'probed', 'result' => null, 'completed' => false])],
        'scenarios' => [], 'findings_provisional' => [],
    ])->assertStatus(202)->assertJsonPath('seq', 1);
    asAgent($token)->postJson("/agent/v1/runs/{$runId}/observations", [
        'proto' => 1, 'seq' => 2,
        'deliveries' => [delivery(1, 'once', $cap, ['phase' => 'probed'])],
        'scenarios' => [['name' => 'once', 'result' => 'pass', 'reason' => null]],
        'findings_provisional' => [['key' => 'accepts_tampered:invoice.paid', 'type' => 'accepts_tampered', 'severity' => 'critical', 'event_type' => 'invoice.paid', 'evidence' => [['d' => 3, 'role' => 'offending']]]],
    ])->assertStatus(202);

    // While Running, the board shows the delivery and the provisional finding.
    actAs($user);
    $this->get("/r/{$runId}")->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn (Assert $page) => $page->component('Run/Show')->where('access', 'owner')
            ->where('run.status', 'running')->where('timeline.status', 'running')->has('timeline.deliveries', 1)
            ->where('findings.0.provisional', true)->where('findings.0.title', 'Accepts tampered payload')
            ->where('live.channel', 'run.'.DB::table('runs')->value('id')));
    newBrowser();

    // Final document.
    $doc = finalDocument($job);
    asAgent($token)->postJson("/agent/v1/runs/{$runId}/final", $doc)->assertOk()->assertJsonPath('status', 'findings');

    // The board shows F1–F4, prose rendered by the server from the pack.
    $pack = app(PackCatalog::class)->prose('stripe', '1.0.0');
    actAs($user);
    $this->get("/r/{$runId}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Run/Show')
        ->where('run.id', $runId)->where('run.number', 1)->where('run.status', 'findings')->where('run.exitCode', 1)
        ->where('run.counts', ['critical' => 1, 'high' => 1, 'medium' => 1, 'info' => 1, 'findings' => 3])
        ->where('run.provenance', ['executor' => 'agent', 'source' => 'web', 'agentName' => 'maya-mbp'])
        ->where('run.budget.label', '10 s budget · Webhook Trap default')
        ->where('run.notChecked', ['side effects outside your database, such as emails'])
        ->where('live', null)->where('timeline.status', 'findings')->has('timeline.deliveries', 4)
        ->has('findings', 4)
        ->where('findings.0.n', 1)->where('findings.0.type', 'accepts_tampered')->where('findings.0.title', 'Accepts tampered payload')
        ->where('findings.0.what', 'D3 changed amount_paid from 2400 to 0 and was signed for the original body. Your handler returned 200 in 120 ms: orders.count 0 → 1, expected a 4xx and no change.')
        ->where('findings.0.citation', $pack['citations']['accepts_tampered'][0])
        ->where('findings.0.fix.framework', 'Express')->where('findings.0.fix.snippet', $pack['text']['snippets']['accepts_tampered']['express'])
        ->where('findings.0.likelyCause.rule', 'LC-1')
        ->where('findings.1.title', 'Duplicate delivery changed orders.count again')
        ->where('findings.1.what', FindingProse::fill($pack['text']['findings']['duplicate_changed_state']['what'], ['event_type' => 'invoice.paid', 'state_list' => 'orders.count went 1 → 2 on D2, expected 1']))
        ->where('findings.2.type', 'over_budget')->where('findings.3.type', 'probe_gap')->where('findings.3.severity', 'info')
        ->where('scenarios.0.key', 'once')->where('scenarios.0.result', 'pass')->where('scenarios.0.reading', '0 → 1'));

    // Runs list and the number redirect.
    $this->get("/p/{$project->key}/runs")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Runs/Index', false)
        ->has('runs', 1)->where('runs.0.id', $runId)->where('runs.0.status', 'findings'));
    $this->get("/p/{$project->key}/runs/1")->assertRedirect("/r/{$runId}");

    // The job is done: no capture fetch any more (TM-35).
    newBrowser();
    asAgent($token)->getJson("/agent/v1/jobs/{$job['job_id']}/captures/{$cap}/request")->assertNotFound();
});
