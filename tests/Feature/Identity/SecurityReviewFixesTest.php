<?php

declare(strict_types=1);

/*
 * A09 slice 1 review (project/security/slice1-review.md): one test or more
 * per finding, F1 to F12. Each asserts the secure behaviour.
 */

use App\Http\Middleware\PreventRequestForgery;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Application\IdentityBootChecks;
use App\Modules\Identity\Application\SecondFactorFailures;
use App\Modules\Identity\Application\SignInThrottle;
use App\Modules\Identity\Domain\AccountRecoveryRequest;
use App\Modules\Identity\Domain\IdentityKeys;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\RecoveryCode;
use App\Modules\Identity\Domain\RecoveryCodeGenerator;
use App\Modules\Identity\Domain\TotpFactor;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserIdentity;
use App\Modules\Identity\Domain\UserSession;
use App\Modules\Identity\Domain\WebAuthnCredential;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    config([
        'services.github' => ['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'redirect' => '/auth/github/callback'],
        'services.google' => ['client_id' => 'fixture-id', 'client_secret' => 'fixture-secret', 'redirect' => '/auth/google/callback'],
    ]);
});

/** Registers through the real route in a browser of its own; returns the unverified user. */
function registerAs(string $email, string $password = 'registrant passphrase 1'): User
{
    newBrowser();
    test()->post('/register', ['name' => 'R', 'email' => $email, 'password' => $password]);

    return User::query()->where('email', $email)->sole();
}

/**
 * Credentials a registrant might hold on an unverified account (defence in
 * depth: the routes now refuse to create them, F1).
 */
function seedRegistrantCredentials(User $user): UserSession
{
    enableTotp($user);
    giveRecoveryCodes($user);
    addKey($user);
    $user->identities()->create(['provider' => 'github', 'provider_subject' => 'gh-registrant', 'provider_email_verified' => true, 'provider_label' => 'registrant', 'linked_at' => now(), 'linked_via' => 'settings']);

    return UserSession::query()->create([
        'user_id' => $user->id, 'created_at' => now(), 'last_seen_at' => now(),
        'idle_expires_at' => now()->addDays(14), 'absolute_expires_at' => now()->addDays(30),
        'first_factor' => 'password', 'ua_label' => 'Registrant', 'ip_prefix' => '198.51.100.0/24',
    ]);
}

function expectCleanState(User $user, UserSession $registrantSession): void
{
    $user->refresh();
    expect($user->isVerified())->toBeTrue()
        ->and($user->hasConfirmedTotp())->toBeFalse()
        ->and($user->two_factor_enabled_at)->toBeNull()
        ->and(RecoveryCode::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(WebAuthnCredential::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(UserIdentity::query()->where('provider_subject', 'gh-registrant')->exists())->toBeFalse()
        ->and($registrantSession->refresh()->revoked_reason)->toBe('email_verified');
}

/** Calls $onCheck on every password hash verification, then verifies for real. */
function hashSpy(callable $onCheck): void
{
    $spy = new class(app(), $onCheck) extends HashManager
    {
        /** @var callable */
        private $onCheck;

        public function __construct($app, callable $onCheck)
        {
            parent::__construct($app);
            $this->onCheck = $onCheck;
        }

        public function check(#[SensitiveParameter] $value, $hashedValue, array $options = []): bool
        {
            ($this->onCheck)();

            return parent::check($value, $hashedValue, $options);
        }
    };
    app()->instance('hash', $spy);
    Hash::clearResolvedInstance('hash');
}

// ---------------------------------------------------------------- F1

it('F1: gives an unverified account no session by password and no credential changes', function (): void {
    $user = registerAs('victim@example.com');
    newBrowser();
    $this->post('/sign-in/password', ['email' => 'victim@example.com', 'password' => 'registrant passphrase 1'])->assertRedirect('/verify-email');
    $this->assertGuest('web');
    expect(UserSession::query()->where('user_id', $user->id)->count())->toBe(0);

    // Even with a session (none can be obtained any more), every mutation refuses.
    actAs($user);
    $this->post('/account/security/totp')->assertRedirect('/verify-email');
    $this->put('/account/password', ['password' => 'another passphrase 22', 'password_confirmation' => 'another passphrase 22'])->assertRedirect('/verify-email');
    $this->post('/account/security/identities/github/connect')->assertRedirect('/verify-email');
    $this->postJson('/webauthn/register/options', ['kind' => 'passkey'], ['Sec-Fetch-Site' => 'same-origin'])->assertStatus(403)->assertJson(['error' => 'email_unverified']);
    expect(TotpFactor::query()->find($user->id))->toBeNull();
});

it('F1: refuses an OAuth link to an unverified account in the service too', function (): void {
    $user = makeUser(['email_verified_at' => null]);
    actAs($user);
    providerReturns('github', 'gh-1', 'someone@example.com');
    oauthFlow('link', 'github', $user);
    $this->get('/auth/github/callback')->assertRedirect('/account/security')->assertSessionHasErrors(['form' => 'email_unverified']);
    expect(UserIdentity::query()->count())->toBe(0);
});

it('F1a: the verification link confirmed in another browser resets the account to a clean state', function (): void {
    $user = registerAs('victim@example.com');
    $registrantSession = seedRegistrantCredentials($user);
    $link = (string) mailedLink('email-verify');

    newBrowser();
    $this->post($link)->assertRedirect('/sign-in')->assertSessionHas('flash.code', 'email_verified_set_password');

    expectCleanState($user, $registrantSession);
    expect($user->refresh()->password_hash)->toBeNull();
    $this->assertGuest('web');
});

it('F1a: the verification link confirmed in the registering browser keeps the password and signs in', function (): void {
    $user = registerAs('owner@example.com');
    $this->post((string) mailedLink('email-verify'))->assertRedirect('/projects');

    expect($user->refresh()->isVerified())->toBeTrue()->and($user->password_hash)->not->toBeNull();
    $this->assertAuthenticated('web');
});

it('F1b: a password reset on a never-verified account discards every registrant credential', function (): void {
    $user = registerAs('victim2@example.com');
    $registrantSession = seedRegistrantCredentials($user);

    newBrowser();
    $this->post('/forgot-password', ['email' => 'victim2@example.com']);
    $token = basename((string) mailedLink('password-reset'));
    $this->post('/reset-password', ['token' => $token, 'password' => 'owner passphrase 22', 'password_confirmation' => 'owner passphrase 22'])->assertRedirect('/');

    expectCleanState($user, $registrantSession);
    expect(Hash::check('owner passphrase 22', (string) $user->refresh()->password_hash))->toBeTrue();
    // The owner is signed in, not sent to a challenge they cannot pass.
    $this->assertAuthenticated('web');
});

it('F1c: an OAuth email match on a never-verified account discards credentials, identities and sessions', function (): void {
    $user = registerAs('victim3@example.com');
    $registrantSession = seedRegistrantCredentials($user);

    newBrowser();
    providerReturns('google', 'g-9', 'victim3@example.com');
    oauthFlow('sign_in', 'google');
    $this->get('/auth/google/callback')->assertRedirect('/projects');

    expectCleanState($user, $registrantSession);
    expect($user->refresh()->password_hash)->toBeNull()
        ->and($user->identities()->pluck('provider')->all())->toBe(['google']);
});

// ---------------------------------------------------------------- F2

it('F2: counts a password attempt in the account key before the hash runs', function (): void {
    $user = makeUser();
    $emailKey = app(SignInThrottle::class)->emailKey($user->email);
    $seen = [];
    hashSpy(function () use (&$seen, $emailKey): void {
        $seen[] = app(Throttle::class)->attempts("signin:acct:{$emailKey}");
    });
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => 'wrong passphrase 123']);

    expect($seen)->toBe([1]);
});

it('F2: refuses without hashing once a full window has been reserved, and gives success back', function (): void {
    $user = makeUser();
    $emailKey = app(SignInThrottle::class)->emailKey($user->email);
    signIn($user);
    expect(app(Throttle::class)->attempts("signin:acct:{$emailKey}"))->toBe(0);

    newBrowser();
    for ($i = 0; $i < 20; $i++) {
        app(Throttle::class)->hit("signin:acct:{$emailKey}", 3600);
    }
    $checks = 0;
    hashSpy(function () use (&$checks): void {
        $checks++;
    });
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])->assertSessionHasErrors(['form' => 'throttled']);
    $this->assertGuest('web');
    expect($checks)->toBe(0);
});

it('F2: keeps the per-challenge attempt count on the server, so replaying an old session does not reset it', function (): void {
    $user = makeUser();
    enableTotp($user);
    signIn($user);
    $snapshot = session('identity.challenge');
    expect($snapshot)->not->toHaveKey('attempts');

    for ($i = 0; $i < 4; $i++) {
        $this->post('/two-factor/totp', ['code' => '000000'])->assertSessionHasErrors(['code' => 'code_invalid']);
        session()->put('identity.challenge', $snapshot); // last write wins: the old state comes back
    }
    $this->post('/two-factor/totp', ['code' => '000000'])->assertRedirect('/sign-in');
});

it('F2: never verifies a second factor while the per-account hold is set (row-locked check)', function (): void {
    $user = makeUser(['second_factor_hold_until' => now()->addHour()]);
    $result = app(SecondFactorFailures::class)->guarded($user, 'totp', null, function (): bool {
        throw new RuntimeException('verified during the hold');
    });

    expect($result)->toBeInstanceOf(DateTimeInterface::class);
});

it('F2: limits /two-factor/* per address', function (): void {
    config(['identity.throttle.two_factor_address' => ['max' => 3, 'decay' => 900]]);
    $user = makeUser();
    $secret = enableTotp($user);
    signIn($user);
    for ($i = 0; $i < 3; $i++) {
        $this->post('/two-factor/totp', ['code' => '000000']);
    }
    newBrowser();
    signIn($user);
    $this->post('/two-factor/totp', ['code' => totpCode($secret)])->assertSessionHasErrors(['form' => 'throttled']);
    $this->assertGuest('web');
});

// ---------------------------------------------------------------- F3

it('F3: links an email-match identity on a 2FA account only after the challenge succeeds', function (): void {
    $user = makeUser(['email' => 'maya@example.com']);
    $secret = enableTotp($user);

    providerReturns('github', 'gh-77', 'maya@example.com');
    oauthFlow('sign_in');
    $this->get('/auth/github/callback')->assertRedirect('/two-factor');
    expect(UserIdentity::query()->count())->toBe(0)->and(mailed('method-linked'))->toBeFalse();

    // An abandoned challenge links nothing.
    newBrowser();
    providerReturns('github', 'gh-77', 'maya@example.com');
    oauthFlow('sign_in');
    $this->get('/auth/github/callback');
    $this->post('/two-factor/totp', ['code' => totpCode($secret)])->assertRedirect('/projects');

    $identity = UserIdentity::query()->sole();
    expect($identity->user_id)->toBe($user->id)
        ->and($identity->linked_via)->toBe('oauth_email_match')
        ->and(mailed('method-linked', 'maya@example.com'))->toBeTrue();
});

// ---------------------------------------------------------------- F4, F5

it('F4: the XSRF cookie carries the __Host- prefix, Secure, Path=/ and no Domain (TM-T4)', function (): void {
    $cookies = collect($this->get('/sign-in')->headers->getCookies())->keyBy->getName();
    expect($cookies->keys()->all())->toContain('__Host-XSRF-TOKEN')->not->toContain('XSRF-TOKEN');
    $cookie = $cookies['__Host-XSRF-TOKEN'];
    expect($cookie->isSecure())->toBeTrue()->and($cookie->getPath())->toBe('/')->and($cookie->getDomain())->toBeNull();
});

it('F5: refuses a cross-site POST /new without a CSRF token, with the middleware enforced (TM-T11)', function (): void {
    app()->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
    {
        protected function runningUnitTests()
        {
            return false;
        }
    });

    $this->post('/new')->assertStatus(419);
    $this->post('/new', [], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);
    $this->post('/new', ['_token' => 'forged'], ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(419);

    $this->get('/sign-in');
    $this->post('/new', ['_token' => session()->token()])->assertStatus(302);
    $this->post('/new', [], ['Sec-Fetch-Site' => 'same-origin'])->assertStatus(302);
});

// ---------------------------------------------------------------- F6

it('F6: a missing common-password list fails closed', function (): void {
    $policy = new PasswordPolicy(12, 128, '/nonexistent/list.txt');
    expect($policy->violation('Password123456', null))->not->toBeNull()
        ->and($policy->listIsAvailable())->toBeFalse();

    config(['identity.password.common_list' => '/nonexistent/list.txt']);
    expect(IdentityBootChecks::serviceProblems())->toContain('the common-password list is missing or empty');
});

it('F6: finds entries in the full bundled list and accepts an uncommon password', function (): void {
    $policy = PasswordPolicy::fromConfig();
    $entries = array_values(array_filter(
        explode("\n", (string) file_get_contents((string) config('identity.password.common_list'))),
        fn (string $l): bool => $l !== '' && $l[0] !== '#',
    ));
    expect(count($entries))->toBeGreaterThan(100000)
        ->and($policy->violation(strtoupper($entries[50000]), null))->toBe('password_common')
        ->and($policy->violation(end($entries), null))->toBe('password_common')
        ->and($policy->violation('quartz lantern gravel 7319 orbit', null))->toBeNull()
        ->and(IdentityBootChecks::serviceProblems())->toBe([]);
});

it('F6/F8: keeps the serve-time key check strict for app and worker, and skips the ingest runtime', function (?string $role, bool $checked): void {
    config(['identity.runtime_role' => $role]);
    expect(IdentityBootChecks::servesIdentity())->toBe($checked);
})->with([
    'app' => ['app', true],
    'worker' => ['worker', true],
    'unset' => [null, true],
    'unknown' => ['web', true],
    'ingest' => ['ingest', false],
]);

// ---------------------------------------------------------------- F7

it('F7: /confirm-auth does not verify a password while the account sign-in hold is in force (TH-1a/b)', function (): void {
    $user = makeUser();
    actAs($user, null);
    $emailKey = app(SignInThrottle::class)->emailKey($user->email);
    Cache::put('wt:hold:signin:acct:'.$emailKey, now()->addMinutes(15)->getTimestamp(), 900);

    $checks = 0;
    hashSpy(function () use (&$checks): void {
        $checks++;
    });
    $this->post('/confirm-auth', ['password' => FIXTURE_PASSWORD, 'action' => 'confirm'])->assertSessionHasErrors(['form' => 'throttled']);
    expect(session('identity.recent_auth'))->toBeNull()->and($checks)->toBe(0);
});

// ---------------------------------------------------------------- F8, F9

it('F8: stores recovery codes as an HMAC with the dedicated key, not a plain hash', function (): void {
    $user = makeUser();
    $codes = giveRecoveryCodes($user);
    $stored = RecoveryCode::query()->where('user_id', $user->id)->get()->map(fn (RecoveryCode $c): string => $c->code_hash)->all();
    $normalised = RecoveryCodeGenerator::normalise($codes[0]);

    expect($stored)->toContain(hash_hmac('sha256', $normalised, IdentityKeys::recoveryCodes(), true))
        ->not->toContain(hash('sha256', $normalised, true));

    // Another key: the stored hashes no longer match.
    config(['identity.keys.recovery_codes' => str_repeat('k', 32)]);
    expect($stored)->not->toContain(RecoveryCodeGenerator::hash($codes[0]));
});

it('F8, F9: refuses to serve without the dedicated keys, and never falls back to APP_KEY', function (): void {
    config(['identity.keys.email_hmac' => null, 'identity.keys.recovery_codes' => 'too-short']);
    expect(IdentityKeys::problems())->toBe(['IDENTITY_EMAIL_HMAC_KEY', 'IDENTITY_RECOVERY_CODE_KEY'])
        ->and(fn () => IdentityKeys::emailHmac())->toThrow(RuntimeException::class)
        ->and(fn () => RecoveryCodeGenerator::hash('AAAA-BBBB-CCCC'))->toThrow(RuntimeException::class);

    config(['identity.keys.email_hmac' => 'base64:'.base64_encode(random_bytes(32))]);
    expect(IdentityKeys::problems())->toBe(['IDENTITY_RECOVERY_CODE_KEY']);
});

it('F9: writes email_hmac with its own key on deletion', function (): void {
    $user = makeUser(['email' => 'maya@example.com']);
    enableTotp($user);
    actAs($user, 'totp');
    session()->put('identity.confirmation', ['purpose' => 'delete_account', 'method' => 'totp', 'second_factor' => true, 'at' => now()->getTimestamp(), 'consumed' => false]);
    $this->delete('/account', ['confirm' => true])->assertRedirect('/');

    $stored = DB::table('audit_log')->where('action', 'account.deleted')->value('email_hmac');
    $stored = is_resource($stored) ? stream_get_contents($stored) : $stored;
    expect($stored)->toBe(IdentityKeys::emailHmacOf('maya@example.com'))
        ->not->toBe(hash_hmac('sha256', 'maya@example.com', (string) config('app.key'), true));
});

// ---------------------------------------------------------------- F10, F11

it('F10: enforces the argon2id floor (19 MiB, t=2)', function (): void {
    config(['hashing.argon.memory' => 65536, 'hashing.argon.time' => 4, 'hashing.argon.threads' => 1]);
    expect(IdentityBootChecks::hashingProblems())->toBe([]);

    config(['hashing.argon.memory' => 19455, 'hashing.argon.time' => 1]);
    expect(IdentityBootChecks::hashingProblems())->toHaveCount(2);
});

it('F11: encrypts the session payload at rest, not env-overridable', function (): void {
    expect(config('session.encrypt'))->toBeTrue()
        ->and((string) file_get_contents(config_path('session.php')))->not->toContain("env('SESSION_ENCRYPT'");
});

// ---------------------------------------------------------------- F12

it('F12: an email change closes a recovery request whose link went to the old address', function (): void {
    $user = makeUser(['email' => 'old@example.com']);
    enableTotp($user);
    $this->post('/account-recovery', ['email' => 'old@example.com']);
    $recoveryLink = (string) mailedLink('recovery-request');

    actAs($user, 'totp');
    $this->post('/account/email', ['email' => 'new@example.com']);
    $this->post((string) mailedLink('email-change-verify'))->assertSessionHas('flash.code', 'email_changed');

    $request = AccountRecoveryRequest::query()->sole();
    expect($request->cancelled_by)->toBe('email_changed');

    newBrowser();
    $this->post($recoveryLink);
    expect($request->refresh()->confirmed_at)->toBeNull();
});
