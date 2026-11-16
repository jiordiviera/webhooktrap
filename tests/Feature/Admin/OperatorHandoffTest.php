<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\SessionRevoker;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserSession;
use Illuminate\Support\Facades\DB;

/*
 * The app → admin handoff (decision 74): a recently confirmed app session
 * with a second factor mints a 60 s single-use token bound to the user and
 * to that app session; the admin host trades it for its own session.
 */

function handoffOperator(): User
{
    $user = makeUser();
    $user->forceFill(['is_operator' => true])->save();
    addKey($user);

    return $user->refresh();
}

/** Mints a token from the app host and returns it (the browser is then forgotten). */
function mintHandoff(User $user): string
{
    actAs($user, 'passkey');
    $location = (string) test()->get('https://webhooktrap.test/operator/handoff')
        ->assertRedirect()->assertHeader('Referrer-Policy', 'no-referrer')->headers->get('Location');
    expect($location)->toStartWith('https://admin.webhooktrap.test/handoff/');

    return substr($location, strlen('https://admin.webhooktrap.test/handoff/'));
}

it('asks for a second-factor confirmation before minting', function (): void {
    actAs(handoffOperator(), 'password');

    test()->get('https://webhooktrap.test/operator/handoff')
        ->assertRedirect('/confirm-auth?next=%2Foperator%2Fhandoff&action=operator');
});

it('refuses a password confirmation for the operator action', function (): void {
    $user = handoffOperator();
    actAs($user, null);

    test()->get('https://webhooktrap.test/confirm-auth?action=operator&next=/operator/handoff')
        ->assertInertia(fn ($page) => $page->where('secondFactorRequired', true)->where('methods', ['webauthn']));
    test()->post('https://webhooktrap.test/confirm-auth', ['action' => 'operator', 'password' => FIXTURE_PASSWORD, 'next' => '/operator/handoff'])
        ->assertSessionHasErrors('form');
});

it('signs the operator in on the admin host with a fresh token', function (): void {
    $user = handoffOperator();
    $token = mintHandoff($user);
    newBrowser();

    test()->get(ADMIN.'/handoff/'.$token)->assertRedirect('/')->assertHeader('Referrer-Policy', 'no-referrer');
    test()->get(ADMIN.'/accounts')->assertOk();

    $session = UserSession::query()->where('user_id', $user->id)->where('first_factor', 'operator_handoff')->sole();
    expect($session->revoked_at)->toBeNull()
        ->and(auditCount('operator_handoff_issued', $user))->toBe(1)
        ->and(auditCount('signed_in', $user))->toBe(1);
});

it('spends a token once', function (): void {
    $token = mintHandoff(handoffOperator());
    newBrowser();
    test()->get(ADMIN.'/handoff/'.$token)->assertRedirect('/');
    newBrowser();

    test()->get(ADMIN.'/handoff/'.$token)->assertForbidden();
});

it('expires a token after 60 seconds', function (): void {
    $token = mintHandoff(handoffOperator());
    newBrowser();
    $this->travel(61)->seconds();

    test()->get(ADMIN.'/handoff/'.$token)->assertForbidden();
});

it('refuses a token whose app session was revoked meanwhile', function (): void {
    $user = handoffOperator();
    $token = mintHandoff($user);
    UserSession::query()->where('user_id', $user->id)->update(['revoked_at' => now(), 'revoked_reason' => 'user']);
    cache()->forget('wt:x'); // liveness is cached; revocation evicts it in the app
    DB::table('user_sessions')->where('user_id', $user->id)->pluck('id')
        ->each(fn (string $id) => cache()->forget(SessionRevoker::cacheKey($id)));
    newBrowser();

    test()->get(ADMIN.'/handoff/'.$token)->assertForbidden();
});

it('refuses a token when operator access was revoked meanwhile', function (): void {
    $user = handoffOperator();
    $token = mintHandoff($user);
    test()->artisan('operator:revoke', ['email' => $user->email])->assertSuccessful();
    newBrowser();

    test()->get(ADMIN.'/handoff/'.$token)->assertForbidden();
});

it('refuses a made-up token', function (): void {
    test()->get(ADMIN.'/handoff/'.str_repeat('A', 43))->assertForbidden();
    test()->get(ADMIN.'/handoff/short')->assertForbidden();
});

it('signs out of the admin host and revokes the admin session', function (): void {
    $user = handoffOperator();
    $token = mintHandoff($user);
    newBrowser();
    test()->get(ADMIN.'/handoff/'.$token);

    test()->post(ADMIN.'/logout')->assertRedirect('https://webhooktrap.test/projects');
    expect(UserSession::query()->where('user_id', $user->id)->where('first_factor', 'operator_handoff')->sole()->revoked_at)->not->toBeNull();
});
