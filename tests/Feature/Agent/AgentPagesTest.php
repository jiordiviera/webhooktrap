<?php

declare(strict_types=1);

use App\Infrastructure\Broadcasting\LiveTrigger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/AgentHelpers.php';

/*
 * A07's end-to-end gaps (contracts §3.1, §3.2): the `agentPill` shared prop,
 * the agent pages, and the run page's per-delivery result, planned delivery
 * count and project.
 */

beforeEach(fn () => Mail::fake());

it('shares agentPill for the principal\'s own workspace: none, awaiting, connected, disconnected, too old', function (): void {
    $user = makeUser();
    $project = userProject($user);
    $pill = function () use ($user, $project): mixed {
        actAs($user);
        $pill = test()->get("/p/{$project->key}/runs")->assertOk()->inertiaProps('agentPill');
        newBrowser();

        return $pill;
    };

    expect($pill())->toBe(['state' => 'none']);
    $agent = pairedAgent(userPrincipal($user), false);
    expect($pill())->toBe(['state' => 'awaiting_confirmation', 'name' => 'maya-mbp', 'version' => '0.9.2']);
    asAgent($agent['token'])->postJson('/agent/v1/confirm', ['proto' => 1])->assertOk();
    asAgent($agent['token'])->postJson('/agent/v1/hello', helloBody())->assertOk();
    expect($pill())->toBe(['state' => 'connected', 'name' => 'maya-mbp', 'version' => '0.9.2']);
    $this->travel(2)->minutes();
    expect($pill()['state'])->toBe('disconnected');
    config(['agent.min_agent_version' => '1.0.0']);
    expect($pill()['state'])->toBe('too_old');

    newBrowser();
    expect($this->get('/r/example')->inertiaProps('agentPill'))->toBeNull();
});

it('serves the user agent pages with live rows, detail and the pair page; strangers and guests get 404', function (): void {
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user));
    $stranger = makeUser();
    userProject($stranger);

    actAs($user);
    $this->get('/agents')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Agents/Index', false)
        ->where('pairing', null)->has('agents', 1)
        ->where('agents.0.id', $agent['id'])->where('agents.0.name', 'maya-mbp')->where('agents.0.state', 'connected')
        ->where('agents.0.tooOld', false)->where('agents.0.revokeUrl', '/agents/'.$agent['id'])->etc());
    foreach (['maya-mbp', $agent['id']] as $ref) {
        $this->get("/agents/{$ref}")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Agents/Show', false)
            ->where('agent.id', $agent['id'])->where('agent.arch', 'arm64')
            ->where('agent.secretNames', [['provider' => 'stripe', 'envName' => 'STRIPE_WEBHOOK_SECRET']])
            ->where('agent.targets.0.host', 'localhost')->etc());
    }
    $this->get('/agents/pair?replace='.$agent['id'])->assertOk()->assertInertia(fn (Assert $page) => $page->component('Agents/Pair', false)
        ->where('pairing', null)->where('replace', $agent['id'])->has('agents', 1));
    $this->get('/agents/nobody')->assertNotFound();
    expect(DB::table('pairing_codes')->whereNull('used_at')->count())->toBe(0); // GET never issues a code

    actAs($stranger);
    $this->get('/agents/'.$agent['id'])->assertNotFound();
    $this->get('/agents')->assertOk()->assertInertia(fn (Assert $page) => $page->has('agents', 0));

    newBrowser();
    $this->get('/agents')->assertNotFound();
    [$cookie] = guestProject();
    asGuest($cookie)->get('/agents')->assertNotFound();
    asGuest($cookie)->get('/agents/pair')->assertNotFound();
});

it('serves the guest agent page to its owner only, and sends a user owner to /agents', function (): void {
    [$cookie, $key] = guestProject();
    asGuest($cookie)->get("/p/{$key}/agent")->assertOk()->assertInertia(fn (Assert $page) => $page->component('Agent/Guest', false)
        ->where('agent', null)->where('pairing', null)->where('project.key', $key));
    $agent = pairedAgent(guestPrincipalOf($key));
    asGuest($cookie)->get("/p/{$key}/agent")->assertOk()->assertInertia(fn (Assert $page) => $page->where('agent.id', $agent['id'])->etc());

    $user = makeUser();
    $project = userProject($user);
    actAs($user);
    $this->get("/p/{$key}/agent")->assertNotFound();
    $this->get("/p/{$project->key}/agent")->assertRedirect('/agents');
});

it('gives finished runs a per-delivery result, the planned delivery count and the project', function (): void {
    $setup = runningRun();
    $cap = $setup['job']['selection'][0]['capture_id'];
    // D1 completed live (result null until judged), then the plan count.
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", [
        'proto' => 1, 'seq' => 1, 'delivery_count' => 4,
        'deliveries' => [delivery(1, 'once', $cap, ['phase' => 'probed', 'result' => null])], 'scenarios' => [], 'findings_provisional' => [],
    ])->assertStatus(202);

    actAs($setup['user']);
    $this->get("/r/{$setup['runId']}")->assertInertia(fn (Assert $page) => $page->component('Run/Show')
        ->where('run.deliveryCount', 4)->where('timeline.deliveries.0.result', null)
        ->where('project', ['key' => $setup['project']->key, 'name' => $setup['project']->name, 'isGuest' => false]));
    newBrowser();

    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/final", finalDocument($setup['job']))->assertOk();
    actAs($setup['user']);
    $this->get("/r/{$setup['runId']}")->assertInertia(fn (Assert $page) => $page->component('Run/Show')
        ->where('run.deliveryCount', 4)
        ->where('timeline.deliveries.0.result', 'pass')->where('timeline.deliveries.1.result', 'finding')
        ->where('timeline.deliveries.2.result', 'finding')->where('timeline.deliveries.2.finding', 1)
        ->where('timeline.deliveries.0.status', 200)->where('timeline.deliveries.0.latencyMs', 120));
    expect(DB::table('run_deliveries')->where('n', 1)->value('phase'))->toBe('judged');
});

it('keeps a completed delivery\'s measured facts immutable, and the whole row once judged (domain §6)', function (): void {
    $setup = runningRun();
    $cap = $setup['job']['selection'][0]['capture_id'];
    asAgent($setup['agent']['token'])->postJson("/agent/v1/runs/{$setup['runId']}/observations", [
        'proto' => 1, 'seq' => 1, 'deliveries' => [delivery(1, 'once', $cap, ['phase' => 'probed', 'result' => null])], 'scenarios' => [], 'findings_provisional' => [],
    ])->assertStatus(202);
    $row = fn () => DB::table('run_deliveries')->where('n', 1);

    expect(fn () => DB::transaction(fn () => $row()->update(['status' => 500])))->toThrow(QueryException::class, 'immutable');
    DB::transaction(fn () => $row()->update(['result' => 'pass', 'phase' => 'judged']));
    expect(fn () => DB::transaction(fn () => $row()->update(['result' => 'finding'])))->toThrow(QueryException::class, 'immutable')
        ->and(fn () => DB::transaction(fn () => $row()->update(['phase' => 'probed'])))->toThrow(QueryException::class, 'immutable');
    expect($row()->value('result'))->toBe('pass');
});

it('gives a guest run page its project for the Runs and Run again links', function (): void {
    [$cookie, $key] = guestProject();
    $projectId = (string) DB::table('projects')->where('project_key', $key)->value('id');
    $seq = stripeCapture($projectId);
    $agent = pairedAgent(guestPrincipalOf($key));
    $response = asGuest($cookie)->post("/p/{$key}/runs", [
        'from' => [$seq], 'target' => ['kind' => 'agent', 'agentId' => $agent['id'], 'targetRef' => 'http://localhost:3000/webhooks/stripe'], 'failOn' => 'high',
    ])->assertRedirect();

    asGuest($cookie)->get('/r/'.runIdFrom($response))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('project.key', $key)->where('project.isGuest', true)->where('run.deliveryCount', null)->etc());
});

it('shows a target approved after the terminal approval: from the accept at once, and from the next hello (A07 e2e)', function (): void {
    $setup = queuedRun();
    $token = $setup['agent']['token'];
    // The agent reports its declared target, not yet approved in the terminal.
    asAgent($token)->postJson('/agent/v1/hello', helloBody(['targets' => [['scheme' => 'http', 'host' => 'localhost', 'port' => 3000, 'kind' => 'local', 'declared' => true, 'approved' => false]]]))->assertOk();
    $approved = function () use ($setup): mixed {
        actAs($setup['user']);
        $approved = test()->get('/agents/'.$setup['agent']['id'])->assertOk()->inertiaProps('agent.targets.0.approved');
        newBrowser();

        return $approved;
    };
    expect($approved())->toBeFalse();

    // y in the terminal, then accept: the mirror says approved right away and the pages refresh.
    Queue::fake();
    $job = nextJob($token);
    asAgent($token)->postJson("/agent/v1/jobs/{$job['job_id']}/accept", ['proto' => 1])->assertOk();
    expect($approved())->toBeTrue();
    $workspace = 'workspace.'.DB::table('agents')->value('workspace_id');
    Queue::assertPushed(LiveTrigger::class, fn (LiveTrigger $t): bool => $t->channel === $workspace && $t->event === 'workspace.agents');

    // The heartbeat hello carries the approval too; it keeps the first approval time.
    $first = DB::table('agent_targets')->value('approved_at');
    $this->travel(20)->seconds();
    asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertOk();
    expect($approved())->toBeTrue()->and(DB::table('agent_targets')->value('approved_at'))->toBe($first);

    // A hello alone (no run) after an approval refreshes the live list as well.
    DB::table('agent_targets')->update(['approved_at' => null]);
    Queue::fake();
    asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertOk();
    expect($approved())->toBeTrue();
    Queue::assertPushed(LiveTrigger::class, fn (LiveTrigger $t): bool => $t->channel === $workspace && $t->event === 'workspace.agents');
    Queue::fake();
    asAgent($token)->postJson('/agent/v1/hello', helloBody())->assertOk();
    Queue::assertNotPushed(LiveTrigger::class, fn (LiveTrigger $t): bool => $t->event === 'workspace.agents');
});
