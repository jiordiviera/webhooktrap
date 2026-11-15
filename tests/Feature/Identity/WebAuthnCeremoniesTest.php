<?php

declare(strict_types=1);

/*
 * WebAuthn ceremonies (contracts §3.4, domain §2.2) on web-auth/webauthn-lib
 * 5.x, driven by a software authenticator (tests/Support/SoftAuthenticator).
 */

use App\Infrastructure\Persistence\Bytea;
use App\Modules\Identity\Domain\SecondFactorFailure;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserSession;
use App\Modules\Identity\Domain\WebAuthnCredential;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\SoftAuthenticator;

const SAME_ORIGIN = ['Sec-Fetch-Site' => 'same-origin'];

beforeEach(function (): void {
    Mail::fake();
    config(['app.url' => 'https://webhooktrap.test', 'identity.webauthn.rp_id' => null, 'identity.webauthn.rp_name' => 'Webhook Trap']);
});

/** @return array<string, mixed> */
function waOptions(TestResponse $response): array
{
    $response->assertOk();

    return (array) json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
}

function registrationOptions(string $kind = 'passkey'): array
{
    return waOptions(test()->postJson('/webauthn/register/options', ['kind' => $kind], SAME_ORIGIN));
}

/** Registers the authenticator for the user through the real routes; returns the stored row. */
function enrol(User $user, SoftAuthenticator $authenticator, string $kind = 'passkey'): WebAuthnCredential
{
    actAs($user);
    test()->postJson('/webauthn/register', ['credential' => $authenticator->register(registrationOptions($kind)), 'name' => 'My key'], SAME_ORIGIN)->assertCreated();

    return WebAuthnCredential::query()->where('credential_id', Bytea::bind($authenticator->credentialId))->sole();
}

function passkeyOptions(): array
{
    return waOptions(test()->postJson('/webauthn/assert/options', [], SAME_ORIGIN));
}

function assertWith(array $credential): TestResponse
{
    return test()->postJson('/webauthn/assert', ['credential' => $credential], SAME_ORIGIN);
}

// ---------------------------------------------------------------- registration

it('builds creation options from configuration only: attestation none, RP ID, algorithms, user handle, exclusions', function (string $kind, string $residentKey, string $uv): void {
    $user = makeUser();
    $existing = addKey($user);
    actAs($user);

    $options = waOptions(test()->postJson('/webauthn/register/options', ['kind' => $kind], SAME_ORIGIN + ['Host' => 'evil.example', 'X-Forwarded-Host' => 'evil.example']));

    expect($options['attestation'])->toBe('none')
        ->and($options['rp'])->toEqual(['name' => 'Webhook Trap', 'id' => 'webhooktrap.test'])
        ->and(array_column($options['pubKeyCredParams'], 'alg'))->toBe([-7, -8, -257])
        ->and(SoftAuthenticator::unb64($options['user']['id']))->toBe($user->webauthn_user_handle)
        ->and(SoftAuthenticator::unb64($options['excludeCredentials'][0]['id']))->toBe($existing->credential_id)
        ->and($options['authenticatorSelection']['residentKey'])->toBe($residentKey)
        ->and($options['authenticatorSelection']['userVerification'])->toBe($uv)
        ->and($options['timeout'])->toBe(300000)
        ->and(strlen(SoftAuthenticator::unb64($options['challenge'])))->toBe(32)
        ->and(session('identity.webauthn.type'))->toBe('register');
})->with([
    'passkey' => ['passkey', 'required', 'required'],
    'security key' => ['security_key', 'discouraged', 'discouraged'],
]);

it('registers a passkey once, returns recovery codes only for the first second factor, emails and audits', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator(backupEligible: true);
    actAs($user);
    $response = $this->postJson('/webauthn/register', ['credential' => $authenticator->register(registrationOptions()), 'name' => 'Laptop'], SAME_ORIGIN)
        ->assertCreated()->assertJson(['name' => 'Laptop', 'kind' => 'passkey', 'synced' => true, 'flagged' => false]);
    expect($response->json('recoveryCodes'))->toHaveCount(10);

    $key = WebAuthnCredential::query()->sole();
    expect($key->discoverable)->toBeTrue()->and($key->uv_at_registration)->toBeTrue()
        ->and($key->alg)->toBe(-7)->and($key->sign_count)->toBe(0)->and($key->backup_eligible)->toBeTrue()
        ->and($user->refresh()->two_factor_enabled_at)->not->toBeNull()
        ->and(mailed('key-added', $user->email))->toBeTrue()
        ->and(auditCount('key_added', $user))->toBe(1);

    $second = new SoftAuthenticator(userVerifying: false);
    $this->postJson('/webauthn/register', ['credential' => $second->register(registrationOptions('security_key'))], SAME_ORIGIN)
        ->assertCreated()->assertJsonMissingPath('recoveryCodes')->assertJson(['kind' => 'security_key']);
});

it('uses a registration challenge once', function (): void {
    actAs(makeUser());
    $credential = (new SoftAuthenticator)->register(registrationOptions());
    $this->postJson('/webauthn/register', ['credential' => $credential], SAME_ORIGIN)->assertCreated();
    $this->postJson('/webauthn/register', ['credential' => $credential], SAME_ORIGIN)->assertStatus(422)->assertJson(['error' => 'challenge_expired']);
});

it('refuses a registration with another origin, RP ID, challenge, attestation format, or a passkey without UV', function (array $bend, bool $uv = true): void {
    actAs(makeUser());
    $authenticator = new SoftAuthenticator(userVerifying: $uv);
    $this->postJson('/webauthn/register', ['credential' => $authenticator->register(registrationOptions(), $bend)], SAME_ORIGIN)
        ->assertStatus(422)->assertJson(['error' => 'registration_failed']);
    expect(WebAuthnCredential::query()->count())->toBe(0);
})->with([
    'origin' => [['origin' => 'https://evil.example']],
    'capture host origin' => [['origin' => 'https://in.webhooktrap.test']],
    'RP ID' => [['rpId' => 'evil.example']],
    'challenge' => [['challenge' => SoftAuthenticator::b64(random_bytes(32))]],
    'ceremony type' => [['type' => 'webauthn.get']],
    'attestation format' => [['fmt' => 'packed']],
    'passkey without UV' => [[], false],
]);

it('expires a registration challenge after 5 minutes', function (): void {
    actAs(makeUser());
    $options = registrationOptions();
    $this->travel(301)->seconds();
    $this->postJson('/webauthn/register', ['credential' => (new SoftAuthenticator)->register($options)], SAME_ORIGIN)->assertStatus(422)->assertJson(['error' => 'challenge_expired']);
});

it('guards registration: same-origin fetch, recent authentication, 10 keys at most', function (): void {
    $user = makeUser();
    actAs($user);
    $this->postJson('/webauthn/register/options', ['kind' => 'passkey'], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(403);
    $this->postJson('/webauthn/register/options', ['kind' => 'passkey'])->assertStatus(403);

    actAs($user, null);
    $this->postJson('/webauthn/register/options', ['kind' => 'passkey'], SAME_ORIGIN)->assertStatus(403)->assertJson(['error' => 'recent_auth_required']);

    for ($i = 0; $i < 10; $i++) {
        addKey($user);
    }
    actAs($user);
    $this->postJson('/webauthn/register/options', ['kind' => 'passkey'], SAME_ORIGIN)->assertStatus(422)->assertJson(['error' => 'too_many_keys']);
});

it('refuses ceremonies where WebAuthn cannot run (plain HTTP or an IP address)', function (string $url): void {
    config(['app.url' => $url]);
    actAs(makeUser());
    $this->postJson('/webauthn/register/options', ['kind' => 'passkey'], SAME_ORIGIN)->assertStatus(422)->assertJson(['error' => 'webauthn_unavailable']);
})->with(['http://webhooktrap.example', 'https://203.0.113.7']);

// ---------------------------------------------------------------- passkey sign-in

it('signs in with a passkey: empty allowCredentials, UV required, completes sign-in and 2FA', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    enrol($user, $authenticator);
    newBrowser();

    $options = passkeyOptions();
    expect($options['allowCredentials'] ?? [])->toBe([])->and($options['userVerification'])->toBe('required')->and($options['rpId'])->toBe('webhooktrap.test');

    assertWith($authenticator->assert($options, $user->webauthn_user_handle))->assertOk()->assertJson(['redirect' => 'https://webhooktrap.test/projects']);
    $this->assertAuthenticatedAs($user, 'web');
    expect(UserSession::query()->where('user_id', $user->id)->where('first_factor', 'passkey')->whereNull('second_factor')->count())->toBe(1)
        ->and(session('identity.recent_auth.second_factor'))->toBeTrue()
        ->and(WebAuthnCredential::query()->sole()->sign_count)->toBe(1);
});

it('refuses a passkey sign-in without user verification', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    enrol($user, $authenticator);
    newBrowser();

    assertWith($authenticator->assert(passkeyOptions(), $user->webauthn_user_handle, ['flags' => SoftAuthenticator::FLAG_UP]))->assertStatus(422)->assertJson(['error' => 'assertion_failed']);
    $this->assertGuest('web');
});

it('never accepts a security key (not discoverable) for passkey sign-in', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator(userVerifying: false);
    enrol($user, $authenticator, 'security_key');
    newBrowser();

    assertWith($authenticator->assert(passkeyOptions(), $user->webauthn_user_handle, ['flags' => SoftAuthenticator::FLAG_UP | SoftAuthenticator::FLAG_UV]))->assertStatus(422);
    $this->assertGuest('web');
});

it('uses an assertion challenge once and only within 5 minutes', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    enrol($user, $authenticator);
    newBrowser();

    $credential = $authenticator->assert(passkeyOptions(), $user->webauthn_user_handle);
    assertWith($credential)->assertOk();
    newBrowser();
    assertWith($credential)->assertStatus(422)->assertJson(['error' => 'challenge_expired']);

    $options = passkeyOptions();
    $this->travel(301)->seconds();
    assertWith($authenticator->assert($options, $user->webauthn_user_handle))->assertStatus(422)->assertJson(['error' => 'challenge_expired']);
});

it('refuses an assertion for another origin or RP ID, checked against configuration only', function (array $bend): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    enrol($user, $authenticator);
    newBrowser();

    $options = waOptions($this->postJson('/webauthn/assert/options', [], SAME_ORIGIN + ['Host' => 'evil.example']));
    assertWith($authenticator->assert($options, $user->webauthn_user_handle, $bend))->assertStatus(422);
    $this->assertGuest('web');
    expect(WebAuthnCredential::query()->sole()->flagged_at)->toBeNull();
})->with([
    'origin' => [['origin' => 'https://evil.example']],
    'RP ID' => [['rpId' => 'evil.example']],
]);

// ---------------------------------------------------------------- WA-1, WA-2

it('applies the counter rule after the signature: refuses, flags, emails and audits (WA-1, TM-T120)', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    $key = enrol($user, $authenticator);
    $key->forceFill(['sign_count' => 5])->save();
    newBrowser();

    assertWith($authenticator->assert(passkeyOptions(), $user->webauthn_user_handle, ['counter' => 5]))->assertStatus(422);
    $key->refresh();
    expect($key->flagged_at)->not->toBeNull()->and($key->flag_cause)->toBe('counter')->and($key->flag_count)->toBe(1)
        ->and($key->sign_count)->toBe(5)
        ->and(mailed('key-blocked', $user->email))->toBeTrue()
        ->and(auditCount('key_blocked', $user))->toBe(1);

    // A flagged credential stays refused, even with a valid counter.
    newBrowser();
    assertWith($authenticator->assert(passkeyOptions(), $user->webauthn_user_handle, ['counter' => 9]))->assertStatus(422);
    $this->assertGuest('web');
    expect(auditCount('key_refused', $user))->toBe(1);
});

it('refuses a stored non-zero count followed by 0, and accepts 0 then 0 (synced passkeys)', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    $key = enrol($user, $authenticator);
    newBrowser();
    assertWith($authenticator->assert(passkeyOptions(), $user->webauthn_user_handle, ['counter' => 0]))->assertOk();
    newBrowser();
    assertWith($authenticator->assert(passkeyOptions(), $user->webauthn_user_handle, ['counter' => 0]))->assertOk();

    $key->forceFill(['sign_count' => 57])->save();
    newBrowser();
    assertWith($authenticator->assert(passkeyOptions(), $user->webauthn_user_handle, ['counter' => 0]))->assertStatus(422);
    expect($key->refresh()->flag_cause)->toBe('counter');
});

it('refuses a discoverable assertion whose user handle is missing or another user\'s, and flags it (WA-2, TM-T121)', function (?string $handle): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    $key = enrol($user, $authenticator);
    $other = makeUser();
    newBrowser();

    assertWith($authenticator->assert(passkeyOptions(), $handle === 'other' ? $other->webauthn_user_handle : null))->assertStatus(422)->assertJson(['error' => 'assertion_failed']);
    $this->assertGuest('web');
    expect($key->refresh()->flag_cause)->toBe('user_handle_mismatch')->and(auditCount('key_blocked', $user))->toBe(1);
})->with(['other user' => ['other'], 'missing' => [null]]);

it('never flags a credential on an assertion whose signature does not verify', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    $key = enrol($user, $authenticator);
    $key->forceFill(['sign_count' => 5])->save();
    newBrowser();

    assertWith($authenticator->assert(passkeyOptions(), makeUser()->webauthn_user_handle, ['counter' => 1, 'signature' => random_bytes(70)]))->assertStatus(422);
    expect($key->refresh()->flagged_at)->toBeNull();
});

// ---------------------------------------------------------------- second factor and confirm

it('passes the 2FA challenge with a security key: allowCredentials, UV discouraged, session second factor', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator(userVerifying: false);
    $key = enrol($user, $authenticator, 'security_key');
    newBrowser();
    signIn($user);
    $this->get('/two-factor')->assertOk();

    $options = waOptions($this->postJson('/webauthn/assert/options', [], SAME_ORIGIN));
    expect(SoftAuthenticator::unb64($options['allowCredentials'][0]['id']))->toBe($key->credential_id)
        ->and($options['userVerification'])->toBe('discouraged');

    assertWith($authenticator->assert($options, null))->assertOk()->assertJson(['redirect' => 'https://webhooktrap.test/projects']);
    $this->assertAuthenticatedAs($user, 'web');
    expect(UserSession::query()->where('first_factor', 'password')->where('second_factor', 'security_key')->count())->toBe(1);
});

it('counts a failed security-key assertion toward the per-account second-factor counter (WA-3)', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator(userVerifying: false);
    enrol($user, $authenticator, 'security_key');
    newBrowser();
    signIn($user);

    $options = waOptions($this->postJson('/webauthn/assert/options', [], SAME_ORIGIN));
    assertWith($authenticator->assert($options, null, ['signature' => random_bytes(70)]))->assertStatus(422);
    expect(SecondFactorFailure::query()->where('user_id', $user->id)->where('kind', 'webauthn')->count())->toBe(1);
    $this->assertGuest('web');
});

it('confirms a recent authentication with a credential from purpose=confirm options', function (): void {
    $user = makeUser();
    $authenticator = new SoftAuthenticator;
    enrol($user, $authenticator);
    actAs($user, null);

    $options = waOptions($this->postJson('/webauthn/assert/options?purpose=confirm', [], SAME_ORIGIN));
    $this->post('/confirm-auth', ['action' => 'confirm', 'credential' => json_encode($authenticator->assert($options, $user->webauthn_user_handle))])
        ->assertRedirect('/account/security');
    expect(session('identity.recent_auth.method'))->toBe('webauthn')->and(session('identity.recent_auth.second_factor'))->toBeTrue();
});
