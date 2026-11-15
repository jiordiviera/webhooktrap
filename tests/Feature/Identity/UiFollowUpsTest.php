<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Principal;
use App\Modules\Identity\Domain\AccountRecoveryRequest;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserIdentity;
use App\Modules\Identity\Http\Answers;
use App\Modules\Workspace\Application\Projects;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;

/*
 * Backend follow-ups from A07's slice 1 UI: Inertia-safe OAuth re-auth,
 * CSRF on link pages, distinct OAuth error codes, RC-2 on the recovery
 * banner, page props the screens name, and a safe `next` on password sign-in.
 */

beforeEach(function (): void {
    Mail::fake();
    config([
        'services.github' => ['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'redirect' => '/auth/github/callback'],
        'services.google' => ['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'redirect' => '/auth/google/callback'],
    ]);
});

function oauthOnlyUser(string $provider = 'github', string $label = 'maya-l'): User
{
    $user = makeUser(['password_hash' => null]);
    $user->identities()->create(['provider' => $provider, 'provider_subject' => "{$provider}-1", 'provider_email_verified' => true, 'provider_label' => $label, 'linked_at' => now(), 'linked_via' => 'signup']);

    return $user;
}

// 1. POST /confirm-auth/{provider}

it('answers an Inertia OAuth re-auth with 409 X-Inertia-Location to the provider', function (): void {
    actAs(oauthOnlyUser(), null, 'github');

    $response = $this->post('/confirm-auth/github', ['action' => 'confirm', 'next' => '/account/security'], ['X-Inertia' => 'true']);

    $response->assertStatus(409);
    expect((string) $response->headers->get('X-Inertia-Location'))->toStartWith('https://github.com/login/oauth/authorize')
        ->toContain('code_challenge=')
        ->and(session('identity.oauth.purpose'))->toBe('confirm');
});

it('still redirects a plain form post for OAuth re-auth, and refuses a non-OAuth-only account', function (): void {
    actAs(oauthOnlyUser(), null, 'github');
    expect((string) $this->post('/confirm-auth/github')->assertRedirect()->headers->get('Location'))
        ->toStartWith('https://github.com/login/oauth/authorize');

    actAs(makeUser());
    $this->post('/confirm-auth/github', [], ['X-Inertia' => 'true'])->assertSessionHasErrors(['form' => 'reauth_mismatch']);
});

// 2. csrfToken on emailed-link pages

it('gives emailed-link pages the CSRF token so their forms post without JavaScript', function (): void {
    $user = makeUser();
    $this->post('/forgot-password', ['email' => $user->email]);
    $this->get(mailedLink('password-reset'))
        ->assertInertia(fn (Assert $p) => $p->component('Auth/ResetPassword')->where('csrfToken', session()->token()));

    $verifyUser = makeUser(['email_verified_at' => null]);
    actAs($verifyUser);
    $this->post('/email/verification-notification');
    $this->get(mailedLink('email-verify'))
        ->assertInertia(fn (Assert $p) => $p->component('Auth/VerifyEmailLink')->where('csrfToken', session()->token()));

    $this->get('/account-recovery/'.str_repeat('a', 43))
        ->assertInertia(fn (Assert $p) => $p->component('Auth/AccountRecoveryLink')->where('csrfToken', session()->token()));
    $this->get('/account-deletion/'.str_repeat('a', 43))
        ->assertInertia(fn (Assert $p) => $p->component('Auth/AccountDeletionLink')->has('csrfToken'));
    $this->get('/email-change/'.str_repeat('a', 43))
        ->assertInertia(fn (Assert $p) => $p->component('Auth/EmailChangeLink')->has('csrfToken'));
});

it('does not hand the CSRF token to ordinary pages', function (): void {
    $this->get('/sign-in')->assertInertia(fn (Assert $p) => $p->component('Auth/SignIn')->missing('csrfToken'));
});

// 3. OAuth cancelled vs failed

it('tells a cancelled OAuth sign-in from a failed one', function (): void {
    oauthFlow('sign_in');
    $this->get('/auth/github/callback?error=access_denied&state=x')
        ->assertRedirect('/sign-in')
        ->assertSessionHasErrors(['form' => 'oauth_cancelled'])
        ->assertSessionHas('identity.oauth_provider', 'github');

    oauthFlow('sign_in', 'google');
    $this->get('/auth/google/callback?error=server_error&state=x')
        ->assertRedirect('/sign-in')->assertSessionHasErrors(['form' => 'oauth_failed']);

    $driver = Mockery::mock();
    $driver->shouldReceive('user')->andThrow(new RuntimeException('token exchange failed'));
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('driver')->andReturn($driver);
    Socialite::swap($factory);
    oauthFlow('sign_in');
    $this->get('/auth/github/callback?code=x&state=x')->assertSessionHasErrors(['form' => 'oauth_failed']);

    $this->get('/sign-in')->assertInertia(fn (Assert $p) => $p->has('oauthProvider'));
    $this->assertGuest('web');
});

it('keeps the uniform invalid_credentials answer for a provider that answered but cannot sign in', function (): void {
    makeUser(['email' => 'maya@example.com']);
    providerReturns('google', 'g-9', 'maya@example.com', false);
    oauthFlow('sign_in', 'google');
    $this->get('/auth/google/callback')->assertSessionHasErrors(['form' => 'invalid_credentials']);
});

it('returns a cancelled or mismatched OAuth confirmation to S-28 with its action, target and reason', function (): void {
    $user = oauthOnlyUser();
    actAs($user, null, 'github');
    $flow = ['next' => '/account/security', 'action' => 'confirm'];

    session()->put('identity.oauth', ['purpose' => 'confirm', 'provider' => 'github', 'user_id' => $user->id, 'reason' => 'sign_out_others'] + $flow);
    $location = (string) $this->get('/auth/github/callback?error=access_denied')
        ->assertSessionHasErrors(['form' => 'oauth_cancelled'])->headers->get('Location');
    expect($location)->toContain('/confirm-auth?')->toContain('next=%2Faccount%2Fsecurity')->toContain('reason=sign_out_others')
        ->and(session('identity.recent_auth'))->toBeNull();

    providerReturns('github', 'someone-else', 'x@example.com');
    oauthFlow('confirm', 'github', $user, $flow);
    expect((string) $this->get('/auth/github/callback')->assertSessionHasErrors(['form' => 'reauth_mismatch'])->headers->get('Location'))
        ->toContain('/confirm-auth?');
    expect(session('identity.recent_auth'))->toBeNull();
});

it('answers a cancelled connect with "Nothing changed" and links nothing', function (): void {
    $user = makeUser();
    actAs($user);
    oauthFlow('link', 'github', $user);
    $this->get('/auth/github/callback?error=access_denied')
        ->assertRedirect('/account/security')->assertSessionHas('flash.code', 'nothing_changed');
    expect(UserIdentity::query()->count())->toBe(0);
});

// 4. recoveryPending: requestedAt and RC-2

it('shares requestedAt, completesAt and the RC-2 flag on recoveryPending', function (): void {
    $this->freezeSecond();
    $user = makeUser();
    enableTotp($user);
    AccountRecoveryRequest::query()->create(['user_id' => $user->id, 'token_hash' => random_bytes(32), 'requested_at' => now()->subHour(), 'confirmed_at' => now()->subHour(), 'completes_at' => now()->addDays(3)]);
    actAs($user);

    $this->get('/account/security')->assertInertia(fn (Assert $p) => $p
        ->where('principal.recoveryPending.requestedAt', now()->subHour()->utc()->toIso8601ZuluString('millisecond'))
        ->where('principal.recoveryPending.completesAt', now()->addDays(3)->utc()->toIso8601ZuluString('millisecond'))
        ->where('principal.recoveryPending.extended', false)
        ->where('security.recovery.extended', false));

    AccountRecoveryRequest::query()->sole()->forceFill(['wait_extended_at' => now(), 'completes_at' => now()->addDays(7)])->save();
    $this->get('/account/security')->assertInertia(fn (Assert $p) => $p
        ->where('principal.recoveryPending.extended', true)
        ->where('principal.recoveryPending.completesAt', now()->addDays(7)->utc()->toIso8601ZuluString('millisecond')));
});

it('shares no recoveryPending for an unconfirmed request', function (): void {
    $user = makeUser();
    AccountRecoveryRequest::query()->create(['user_id' => $user->id, 'token_hash' => random_bytes(32), 'requested_at' => now()]);
    actAs($user);
    $this->get('/account/security')->assertInertia(fn (Assert $p) => $p->missing('principal.recoveryPending'));
});

// 5. Page props

it('names the account on the reset page and flags an imported 1.x account', function (): void {
    $user = makeUser(['email' => 'maya@example.com']);
    $this->post('/forgot-password', ['email' => $user->email]);
    $this->get(mailedLink('password-reset'))->assertInertia(fn (Assert $p) => $p
        ->where('link.email', 'maya@example.com')->where('link.legacy', false));

    $legacy = makeUser(['email' => 'old@example.com', 'password_hash' => null, 'email_verified_at' => null, 'legacy_source' => '1x', 'legacy_imported_at' => now()]);
    $this->post('/forgot-password', ['email' => $legacy->email]);
    $this->get(mailedLink('password-reset'))->assertInertia(fn (Assert $p) => $p
        ->where('link.email', 'old@example.com')->where('link.legacy', true));
});

it('counts down attempts left on the two-step challenge', function (): void {
    $user = makeUser();
    enableTotp($user);
    signIn($user);

    $this->get('/two-factor')->assertInertia(fn (Assert $p) => $p->where('attemptsLeft', 5));
    $this->post('/two-factor/totp', ['code' => '000000']);
    $this->post('/two-factor/totp', ['code' => '000000']);
    $this->post('/two-factor/totp', ['code' => '000000']);
    $this->get('/two-factor')->assertInertia(fn (Assert $p) => $p->where('attemptsLeft', 2));
});

it('passes an allow-listed reason and its verb to S-28 for a generic recent-auth bounce', function (): void {
    $user = makeUser();
    actAs($user, null);

    $location = (string) $this->delete('/account/sessions')->headers->get('Location');
    expect($location)->toContain('reason=sign_out_others');
    $this->get($location)->assertInertia(fn (Assert $p) => $p
        ->where('reason', 'sign_out_others')->where('verb', 'sign out other browsers'));

    expect((string) $this->post('/account/security/identities/google/connect')->headers->get('Location'))->toContain('reason=connect_google');

    // A crafted reason never reaches the page as text.
    $this->get('/confirm-auth?reason=wire+money+to+eve')->assertInertia(fn (Assert $p) => $p
        ->where('reason', null)->where('verb', null));
});

it('gives S-28 the linked account labels of an OAuth-only account, and none otherwise', function (): void {
    actAs(oauthOnlyUser('github', 'maya-l'), null, 'github');
    $this->get('/confirm-auth')->assertInertia(fn (Assert $p) => $p->where('providerLabels', ['github' => 'maya-l']));

    actAs(makeUser());
    $this->get('/confirm-auth')->assertInertia(fn (Assert $p) => $p->where('providerLabels', []));
});

// 6. Safe `next` on POST /sign-in/password

it('lands on a safe next after password sign-in', function (): void {
    $user = makeUser();
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD, 'next' => '/p/abcde12345?tab=runs'])
        ->assertRedirect('/p/abcde12345?tab=runs');
});

it('keeps a safe next through the two-step challenge', function (): void {
    $user = makeUser();
    $secret = enableTotp($user);
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD, 'next' => '/account/security'])
        ->assertRedirect('/two-factor');
    $this->post('/two-factor/totp', ['code' => totpCode($secret)])->assertRedirect('/account/security');
});

it('refuses open-redirect targets in next', function (string $next): void {
    $user = makeUser();
    $response = $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD, 'next' => $next]);

    $response->assertRedirect('/projects');
    expect((string) $response->headers->get('Location'))->toBe(url('/projects'));
})->with([
    'protocol-relative' => '//evil.example',
    'protocol-relative with path' => '//evil.example/account',
    'absolute https' => 'https://evil.example/',
    'absolute http' => 'http://evil.example',
    'javascript scheme' => 'javascript:alert(1)',
    'backslash' => '/\\evil.example',
    'double backslash' => '\\\\evil.example',
    'tab smuggling' => "/\t/evil.example",
    'newline smuggling' => "/\n/evil.example",
    'data scheme' => 'data:text/html,hi',
    'relative without slash' => 'account/security',
]);

it('does not record next when the sign-in fails', function (): void {
    $user = makeUser();
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => 'wrong password here', 'next' => '/account/security']);
    expect(session('url.intended'))->toBeNull();
});

it('applies the same rule in safeNext', function (): void {
    expect(Answers::safeNext('/account/security?x=1'))->toBe('/account/security?x=1')
        ->and(Answers::safeNext('/%2F%2Fevil.example'))->toBe('/%2F%2Fevil.example')
        ->and(Answers::safeNext("/\x7F/evil.example"))->toBe('/')
        ->and(Answers::safeNext('/'.str_repeat('a', 2001)))->toBe('/');
});

// QA F-03: the landing after sign-in and email confirmation is `/projects`.

it('lands a plain sign-in on New project, the one project, or the list', function (): void {
    $user = makeUser();
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])->assertRedirect('/projects');
    $this->get('/projects')->assertRedirect('/projects/new');

    $project = app(Projects::class)->create(new Principal('user', $user->id), 'Only');
    $this->get('/projects')->assertRedirect('/p/'.$project->key);
});

it('lands an email confirmation on New project with the flash kept, and a safe intended URL wins', function (): void {
    $this->post('/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'a long enough passphrase']);
    $link = mailedLink('email-verify');

    $this->post($link)->assertRedirect('/projects')->assertSessionHas('flash.code', 'email_verified');
    $this->get('/projects')->assertRedirect('/projects/new');
    $this->get('/projects/new')->assertOk()->assertInertia(fn (Assert $p) => $p->where('flash.code', 'email_verified'));

    $this->post('/sign-out');
    $user = makeUser();
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD, 'next' => '/account/security'])
        ->assertRedirect('/account/security');
});
