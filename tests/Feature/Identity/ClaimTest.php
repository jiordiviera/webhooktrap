<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\GuestSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** A guest with a non-empty workspace; returns [token, projectKey]. */
function busyGuest(): array
{
    $response = test()->post('/new');
    $token = (string) $response->getCookie('__Host-wt_guest', false)->getValue();
    GuestSession::query()->update(['stored_captures' => 3]);

    return [$token, (string) str($response->headers->get('Location'))->afterLast('/p/')];
}

it('claims when the sign-in started from the guest session, and moves the project (TM-T12)', function (): void {
    $user = makeUser();
    [$token, $key] = busyGuest();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/sign-in')->assertOk();
    $before = session()->getId();

    $response = $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD]);

    $response->assertRedirect('/projects')->assertSessionHas('flash.code', 'claimed')->assertSessionHas('flash.message', $user->email);
    $guest = GuestSession::query()->sole();
    expect($guest->claimed_by_user_id)->toBe($user->id)
        ->and($guest->claim_nonce_hash)->toBeNull()
        ->and(session()->getId())->not->toBe($before)
        ->and($response->getCookie('__Host-wt_guest', false)?->getExpiresTime())->toBeLessThan(time());
    $workspace = DB::table('workspaces')->where('user_id', $user->id)->sole();
    expect(DB::table('projects')->where('project_key', $key)->value('workspace_id'))->toBe($workspace->id)
        ->and(DB::table('workspaces')->whereNotNull('guest_session_id')->count())->toBe(0);
    $this->get('/p/'.$key)->assertOk();
});

it('does not claim when the sign-in was not started from the guest session (TM-T12)', function (): void {
    $user = makeUser();
    [$token] = busyGuest();
    // The nonce was never created: the sign-in page was not opened as this guest.
    $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])
        ->assertRedirect('/projects');

    expect(GuestSession::query()->sole()->claimed_at)->toBeNull();
});

it('does not claim when the sign-in completes in another browser (TM-T12)', function (): void {
    $user = makeUser();
    [$token] = busyGuest();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/sign-in');
    newBrowser();

    signIn($user);

    expect(GuestSession::query()->sole()->claimed_at)->toBeNull();
});

it('uses the claim nonce once (TM-T12)', function (): void {
    $user = makeUser();
    [$token] = busyGuest();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/sign-in');
    $claim = session('identity.claim');
    GuestSession::query()->update(['claim_nonce_hash' => null]);

    $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD]);

    expect($claim)->toBeArray()->and(GuestSession::query()->sole()->claimed_at)->toBeNull();
});

it('claims only after the 2FA challenge', function (): void {
    $user = makeUser();
    $secret = enableTotp($user);
    [$token] = busyGuest();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/sign-in');

    $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])
        ->assertRedirect('/two-factor');
    expect(GuestSession::query()->sole()->claimed_at)->toBeNull();

    $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/two-factor/totp', ['code' => totpCode($secret)])->assertRedirect('/projects');
    expect(GuestSession::query()->sole()->claimed_by_user_id)->toBe($user->id);
});

it('discards an empty guest workspace at claim', function (): void {
    $user = makeUser();
    $response = $this->post('/new');
    $token = (string) $response->getCookie('__Host-wt_guest', false)->getValue();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/sign-in');
    $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD]);

    expect(GuestSession::query()->sole()->claimed_at)->not->toBeNull()
        ->and(DB::table('projects')->count())->toBe(0);
});

it('claims a new account only when its email is verified in the same browser (TM-T12)', function (): void {
    Mail::fake();
    [$token] = busyGuest();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/register');
    $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/register', ['name' => 'Maya', 'email' => 'maya@example.com', 'password' => 'a long enough passphrase'])
        ->assertRedirect('/verify-email');
    $this->assertGuest('web');
    $link = mailedLink('email-verify');

    $this->withUnencryptedCookie('__Host-wt_guest', $token)->post($link)->assertRedirect('/projects');

    $this->assertAuthenticated('web');
    expect(GuestSession::query()->sole()->claimed_at)->not->toBeNull();
});

it('does not claim when the verification link is confirmed in another browser', function (): void {
    Mail::fake();
    [$token] = busyGuest();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/register');
    $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/register', ['name' => 'Maya', 'email' => 'maya@example.com', 'password' => 'a long enough passphrase']);
    $link = mailedLink('email-verify');
    newBrowser();

    $this->post($link)->assertRedirect('/sign-in');

    $this->assertGuest('web');
    expect(GuestSession::query()->sole()->claimed_at)->toBeNull();
});

it('never claims from a password reset link', function (): void {
    Mail::fake();
    $user = makeUser();
    [$token] = busyGuest();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/sign-in');
    $this->post('/forgot-password', ['email' => $user->email]);
    $link = mailedLink('password-reset');

    $this->withUnencryptedCookie('__Host-wt_guest', $token)->post('/reset-password', [
        'token' => str($link)->afterLast('/')->toString(),
        'password' => 'a brand new passphrase',
        'password_confirmation' => 'a brand new passphrase',
    ])->assertRedirect('/');

    expect(GuestSession::query()->sole()->claimed_at)->toBeNull();
});
