<?php

declare(strict_types=1);

use App\Infrastructure\Ids\EntityId;
use App\Modules\Agent\Application\AgentLifecycle;
use App\Modules\Agent\Domain\AuthMessage;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/AgentHelpers.php';

/*
 * Pairing and authentication refusals (contracts §5.1–§5.3; S-1, S-3,
 * TM-33, TM-42, TM-43, RE-1).
 */

it('answers the same invalid_code for unknown, used, expired and malformed codes', function (): void {
    $user = makeUser();
    userProject($user);
    $keys = agentKeys();

    pairRequest('00000-00000', $keys['public'])->assertStatus(400)->assertJsonPath('error', 'invalid_code');

    $used = issueCode(userPrincipal($user));
    pairRequest($used, $keys['public'])->assertCreated();
    pairRequest($used, agentKeys()['public'])->assertStatus(400)->assertJsonPath('error', 'invalid_code');

    $expired = issueCode(userPrincipal($user));
    $this->travel(11)->minutes();
    pairRequest($expired, agentKeys()['public'])->assertStatus(400)->assertJsonPath('error', 'invalid_code');

    pairRequest('ILOU0-00000', agentKeys()['public'])->assertStatus(400)->assertJsonPath('error', 'invalid_code');
});

it('keeps one live code per principal and stores it as an HMAC only', function (): void {
    $user = makeUser();
    userProject($user);
    $first = issueCode(userPrincipal($user));
    $second = issueCode(userPrincipal($user));

    expect(DB::table('pairing_codes')->whereNull('used_at')->count())->toBe(1);
    pairRequest($first, agentKeys()['public'])->assertStatus(400);
    pairRequest($second, agentKeys()['public'])->assertCreated();
    $stored = DB::table('pairing_codes')->first();
    expect(str_contains((string) json_encode($stored), str_replace('-', '', $second)))->toBeFalse();
});

it('blocks an address after 10 failed pairings for an hour', function (): void {
    $keys = agentKeys();
    for ($i = 0; $i < 10; $i++) {
        pairRequest('00000-0000'.$i, $keys['public'])->assertStatus(400);
    }
    pairRequest('00000-00000', $keys['public'])->assertStatus(429)->assertHeader('Retry-After');

    // Even a good code waits for the block to end.
    $user = makeUser();
    userProject($user);
    pairRequest(issueCode(userPrincipal($user)), $keys['public'])->assertStatus(429);
});

it('refuses an agent below the minimum version with 426', function (): void {
    $user = makeUser();
    userProject($user);
    pairRequest(issueCode(userPrincipal($user)), agentKeys()['public'], ['version' => '0.8.9'])
        ->assertStatus(426)->assertJsonPath('error', 'agent_too_old')->assertJsonPath('min_agent_version', '0.9.0');
});

it('caps an account workspace at 20 live agents (TM-43)', function (): void {
    config(['agent.max_agents_per_workspace' => 2]);
    $user = makeUser();
    userProject($user);
    pairedAgent(userPrincipal($user), false, 'a1');
    pairedAgent(userPrincipal($user), false, 'a2');
    pairRequest(issueCode(userPrincipal($user)), agentKeys()['public'], ['name' => 'a3'])->assertStatus(409)->assertJsonPath('error', 'agent_limit');
});

it('refuses unknown fields such as hint_last4 (S-8, closed schemas)', function (): void {
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user));
    $body = helloBody();
    $body['secrets'][0]['hint_last4'] = 'abcd';
    asAgent($agent['token'])->postJson('/agent/v1/hello', $body)->assertStatus(422)->assertJsonPath('error', 'schema');
    pairRequest('00000-00000', agentKeys()['public'], ['extra' => 1])->assertStatus(422);
});

it('refuses a signature made for another origin (TM-33)', function (): void {
    $user = makeUser();
    userProject($user);
    $keys = agentKeys();
    $id = pairRequest(issueCode(userPrincipal($user)), $keys['public'])->json('agent_id');

    tokenRequest($id, $keys['secret'], 'https://evil.example')->assertStatus(401)->assertJsonPath('error', 'invalid_signature');
    expect(AuthMessage::origin('HTTPS://WebhookTrap.test:443/x'))->toBe(TEST_ORIGIN);
    tokenRequest($id, $keys['secret'])->assertOk();
});

it('uses each nonce once and only before it expires', function (): void {
    $user = makeUser();
    userProject($user);
    $keys = agentKeys();
    $id = pairRequest(issueCode(userPrincipal($user)), $keys['public'])->json('agent_id');

    $challenge = $this->postJson('/agent/v1/auth/challenge', ['proto' => 1, 'agent_id' => $id])->assertOk()->assertJsonPath('proto', 1)->json();
    tokenRequest($id, $keys['secret'], TEST_ORIGIN, $challenge)->assertOk();
    tokenRequest($id, $keys['secret'], TEST_ORIGIN, $challenge)->assertStatus(401)->assertJsonPath('error', 'invalid_challenge');

    $late = $this->postJson('/agent/v1/auth/challenge', ['proto' => 1, 'agent_id' => $id])->json();
    $this->travel(61)->seconds();
    tokenRequest($id, $keys['secret'], TEST_ORIGIN, $late)->assertStatus(401)->assertJsonPath('error', 'invalid_challenge');
});

it('gives an unconfirmed agent nothing but confirm, auth and unpair (RE-1)', function (): void {
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user), false);

    asAgent($agent['token'])->postJson('/agent/v1/hello', helloBody())->assertStatus(403)->assertJsonPath('error', 'needs_reconfirm');
    asAgent($agent['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertStatus(403)->assertJsonPath('error', 'needs_reconfirm');
    expect(DB::table('agent_targets')->count())->toBe(0)->and(DB::table('agent_secret_names')->count())->toBe(0);
});

it('revokes an agent still unconfirmed 10 minutes after pair (RE-1)', function (): void {
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user), false);
    pairedAgent(userPrincipal($user), false, 'other');

    $this->travel(11)->minutes();
    tokenRequest($agent['id'], $agent['secret'])->assertStatus(401)->assertJsonPath('error', 'revoked')->assertJsonPath('reason', 'unconfirmed');
    expect(app(AgentLifecycle::class)->expireUnconfirmed())->toBe(1);
    expect(DB::table('agents')->whereNull('revoked_at')->count())->toBe(0)
        ->and(DB::table('agents')->where('name', 'other')->value('revoked_reason'))->toBe('unconfirmed');
});

it('answers 401 revoked with the reason after a revocation', function (): void {
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user));
    asAgent($agent['token'])->deleteJson('/agent/v1/agent')->assertOk();
    asAgent($agent['token'])->postJson('/agent/v1/hello', helloBody())->assertStatus(401)
        ->assertJsonPath('error', 'revoked')->assertJsonPath('reason', 'user');
});

it('lets a guest workspace keep one confirmed agent (A-13)', function (): void {
    [, $key] = guestProject();
    $guest = guestPrincipalOf($key);
    $first = pairedAgent($guest, true, 'first');
    $second = pairedAgent($guest, true, 'second');

    asAgent($first['token'])->postJson('/agent/v1/hello', helloBody())->assertStatus(401)->assertJsonPath('reason', 'replaced');
    asAgent($second['token'])->postJson('/agent/v1/hello', helloBody())->assertOk()->assertJsonPath('guest', true);
    expect(DB::table('agents')->whereNull('revoked_at')->count())->toBe(1);
});

it('never replaces an older agent of the same name silently when a machine re-pairs (AC-3)', function (): void {
    $user = makeUser();
    userProject($user);
    $old = pairedAgent(userPrincipal($user));
    pairedAgent(userPrincipal($user));
    asAgent($old['token'])->postJson('/agent/v1/hello', helloBody())->assertOk();
});

it('refuses a body over the endpoint limit with 413', function (): void {
    $this->postJson('/agent/v1/pair', ['proto' => 1, 'pad' => str_repeat('x', 5000)])->assertStatus(413)->assertJsonPath('error', 'too_large');
});

it('signs only the agent its own channel, by bearer token (TM-20)', function (): void {
    config(['broadcasting.connections.reverb.key' => 'k', 'broadcasting.connections.reverb.secret' => 's']);
    $user = makeUser();
    userProject($user);
    $agent = pairedAgent(userPrincipal($user));
    $other = pairedAgent(userPrincipal($user), true, 'other');
    $uuid = fn (string $id): string => EntityId::decode($id)[1];

    $own = asAgent($agent['token'])->postJson('/agent/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-agent.'.$uuid($agent['id'])])
        ->assertOk()->json();
    expect($own['channel_data'])->toBe('{"user_id":"agent:'.$uuid($agent['id']).'"}');
    asAgent($agent['token'])->postJson('/agent/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-agent.'.$uuid($other['id'])])->assertForbidden();
    asAgent($agent['token'])->postJson('/agent/v1/broadcasting/auth', ['socket_id' => 'bad', 'channel_name' => 'private-agent.'.$uuid($agent['id'])])->assertForbidden();
    $this->flushHeaders();
    $this->postJson('/agent/v1/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-agent.'.$uuid($agent['id'])])->assertForbidden();
});

it('moves agents with a claimed guest workspace and asks them to reconfirm (TM-42)', function (): void {
    [, $key] = guestProject();
    $agent = pairedAgent(guestPrincipalOf($key));
    $userId = makeUser()->id;
    app(WorkspaceOwnership::class)->absorbGuestWorkspace(guestPrincipalOf($key)->id, $userId, false);

    asAgent($agent['token'])->getJson('/agent/v1/jobs/next?wait=0')->assertStatus(403)->assertJsonPath('error', 'needs_reconfirm');
    expect(DB::table('agent_targets')->count())->toBe(0);
    asAgent($agent['token'])->postJson('/agent/v1/confirm', ['proto' => 1])->assertOk();
    asAgent($agent['token'])->postJson('/agent/v1/hello', helloBody())->assertOk()->assertJsonPath('guest', false);
    expect(DB::table('agents')->value('guest_slot'))->toBeFalse();
});
