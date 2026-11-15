<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccountRecoveryRequest;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserIdentity;
use App\Modules\Identity\Domain\UserSession;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    config([
        'services.github' => ['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'redirect' => '/auth/github/callback'],
        'services.google' => ['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'redirect' => '/auth/google/callback'],
    ]);
});

it('redirects with PKCE, a session-bound state, and forces a fresh Google login for confirm', function (): void {
    $response = $this->get('/sign-in/github');
    $location = (string) $response->headers->get('Location');
    expect($location)->toStartWith('https://github.com/login/oauth/authorize')
        ->toContain('code_challenge=')->toContain('code_challenge_method=S256')
        ->and(session('state'))->not->toBeNull()
        ->and(session('identity.oauth.purpose'))->toBe('sign_in');

    $user = makeUser(['password_hash' => null]);
    $user->identities()->create(['provider' => 'google', 'provider_subject' => 'g-1', 'provider_email_verified' => true, 'provider_label' => 'm@example.com', 'linked_at' => now(), 'linked_via' => 'signup']);
    actAs($user, null, 'google');
    $location = (string) $this->post('/confirm-auth/google', ['action' => 'delete_account'])->headers->get('Location');
    expect($location)->toContain('prompt=login')->toContain('max_age=0');
});

it('creates an account on first GitHub sign-in with a verified email', function (): void {
    providerReturns('github', '1001', 'new@example.com');
    oauthFlow('sign_in');

    $this->get('/auth/github/callback')->assertRedirect('/projects');

    $user = User::query()->sole();
    expect($user->email_verified_at)->not->toBeNull()->and($user->password_hash)->toBeNull()
        ->and(UserIdentity::query()->sole()->linked_via)->toBe('signup')
        ->and(UserSession::query()->sole()->first_factor)->toBe('github')
        ->and(session('identity.recent_auth'))->toBeNull();
});

it('signs in by provider subject and refreshes the label', function (): void {
    $user = makeUser();
    $user->identities()->create(['provider' => 'github', 'provider_subject' => '1001', 'provider_email_verified' => true, 'provider_label' => 'old', 'linked_at' => now(), 'linked_via' => 'settings']);
    providerReturns('github', '1001', 'other@example.com', nickname: 'new-login');
    oauthFlow('sign_in');

    $this->get('/auth/github/callback')->assertRedirect('/projects');
    $this->assertAuthenticatedAs($user, 'web');
    expect(UserIdentity::query()->sole()->provider_label)->toBe('new-login');
});

it('links by verified email, notifies and audits; pre-hijack rule discards a password set before verification (WA-5)', function (): void {
    $user = makeUser(['email' => 'maya@example.com', 'email_verified_at' => null]);
    providerReturns('google', 'g-9', 'maya@example.com', true);
    oauthFlow('sign_in', 'google');

    $this->get('/auth/google/callback')->assertRedirect('/projects');

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->password_hash)->toBeNull()
        ->and(UserIdentity::query()->sole()->linked_via)->toBe('oauth_email_match')
        ->and(auditCount('identity_linked', $user))->toBe(1)
        ->and(mailed('method-linked', 'maya@example.com'))->toBeTrue();
});

it('never links or creates on an unverified provider email', function (): void {
    makeUser(['email' => 'maya@example.com']);
    providerReturns('google', 'g-9', 'maya@example.com', false);
    oauthFlow('sign_in', 'google');

    $this->get('/auth/google/callback')->assertRedirect('/sign-in')->assertSessionHasErrors(['form' => 'invalid_credentials']);
    expect(UserIdentity::query()->count())->toBe(0)->and(User::query()->count())->toBe(1);
});

it('answers a tombstoned account like an unknown one, by subject and by email (DL-2, TM-T157)', function (): void {
    $gone = makeUser(['email' => 'gone@example.com', 'deleted_at' => now()]);
    $gone->identities()->create(['provider' => 'github', 'provider_subject' => '77', 'provider_email_verified' => true, 'provider_label' => 'g', 'linked_at' => now(), 'linked_via' => 'signup']);

    providerReturns('github', '77', 'gone@example.com');
    oauthFlow('sign_in');
    $this->get('/auth/github/callback')->assertSessionHasErrors(['form' => 'invalid_credentials']);

    newBrowser();
    providerReturns('google', 'g-new', 'gone@example.com');
    oauthFlow('sign_in', 'google');
    $this->get('/auth/google/callback')->assertSessionHasErrors(['form' => 'invalid_credentials']);

    expect(User::query()->count())->toBe(1)->and(UserIdentity::query()->count())->toBe(1)->and(auditCount('identity_linked'))->toBe(0);
    $this->assertGuest('web');
});

it('rejects a callback whose state does not match the session', function (): void {
    $this->get('/auth/github/callback?state=forged&code=x')->assertRedirect('/sign-in');
    $this->assertGuest('web');
});

it('connects from settings once per provider, refusing a subject linked elsewhere (domain §2.5)', function (): void {
    $user = makeUser();
    $other = makeUser();
    $other->identities()->create(['provider' => 'github', 'provider_subject' => 'taken', 'provider_email_verified' => true, 'provider_label' => 'x', 'linked_at' => now(), 'linked_via' => 'signup']);
    actAs($user);

    providerReturns('github', 'taken', 'x@example.com');
    oauthFlow('link', 'github', $user);
    $this->get('/auth/github/callback')->assertRedirect('/account/security')->assertSessionHasErrors(['form' => 'identity_in_use']);

    providerReturns('github', 'mine', 'whatever@example.com', false);
    oauthFlow('link', 'github', $user);
    $this->get('/auth/github/callback')->assertSessionHas('flash.code', 'identity_linked');
    expect($user->identities()->sole()->linked_via)->toBe('settings');

    providerReturns('github', 'second', 'w@example.com');
    oauthFlow('link', 'github', $user);
    $this->get('/auth/github/callback')->assertSessionHasErrors(['form' => 'identity_already_linked']);
});

it('refuses a link or confirm flow that belongs to another account', function (): void {
    $victim = makeUser();
    actAs(makeUser());
    providerReturns('github', 'mine', 'w@example.com');
    oauthFlow('link', 'github', $victim);
    $this->get('/auth/github/callback')->assertSessionHasErrors(['form' => 'reauth_mismatch']);
    expect(UserIdentity::query()->count())->toBe(0);
});

it('confirms an OAuth-only account only with the same provider subject', function (): void {
    $user = makeUser(['password_hash' => null]);
    $user->identities()->create(['provider' => 'github', 'provider_subject' => 'gh-1', 'provider_email_verified' => true, 'provider_label' => 'm', 'linked_at' => now(), 'linked_via' => 'signup']);
    actAs($user, null, 'github');

    providerReturns('github', 'gh-2', 'm@example.com');
    oauthFlow('confirm', 'github', $user, ['next' => '/account/delete', 'action' => 'delete_account']);
    $this->get('/auth/github/callback')->assertSessionHasErrors(['form' => 'reauth_mismatch']);

    providerReturns('github', 'gh-1', 'm@example.com');
    oauthFlow('confirm', 'github', $user, ['next' => '/account/delete', 'action' => 'delete_account']);
    $this->get('/auth/github/callback')->assertRedirect('/account/delete');
    expect(session('identity.confirmation.purpose'))->toBe('delete_account')->and(session('identity.confirmation.method'))->toBe('github');
});

it('refuses to disconnect the last first factor, and shields only identities linked before a recovery (AR-1, TM-T152)', function (): void {
    $user = makeUser(['password_hash' => null]);
    $old = $user->identities()->create(['provider' => 'github', 'provider_subject' => 'gh', 'provider_email_verified' => true, 'provider_label' => 'm', 'linked_at' => now()->subDays(3), 'linked_via' => 'signup']);
    actAs($user, 'github', 'github');
    $this->delete('/account/security/identities/github')->assertSessionHasErrors(['form' => 'last_sign_in_method']);

    $user->forceFill(['password_hash' => bcrypt('x')])->save();
    AccountRecoveryRequest::query()->create(['user_id' => $user->id, 'token_hash' => random_bytes(32), 'requested_at' => now()->subDay(), 'confirmed_at' => now()->subDay(), 'completes_at' => now()->addDays(2)]);
    $new = $user->identities()->create(['provider' => 'google', 'provider_subject' => 'go', 'provider_email_verified' => true, 'provider_label' => 'm', 'linked_at' => now(), 'linked_via' => 'settings']);

    $this->delete('/account/security/identities/github')->assertSessionHasErrors(['form' => 'recovery_lock']);
    $this->delete('/account/security/identities/google')->assertSessionHas('flash.code', 'identity_unlinked');
    expect(UserIdentity::query()->find($old->id))->not->toBeNull()->and(UserIdentity::query()->find($new->id))->toBeNull()
        ->and(auditCount('identity_unlinked', $user))->toBe(1)->and(mailed('method-unlinked'))->toBeTrue();
});
