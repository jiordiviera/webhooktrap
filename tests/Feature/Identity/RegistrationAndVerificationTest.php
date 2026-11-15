<?php

declare(strict_types=1);

use App\Infrastructure\Ids\EntityId;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

it('applies the password policy on every path (domain §2.1)', function (string $password, ?string $code): void {
    expect(PasswordPolicy::fromConfig()->violation($password, 'maya@example.com'))->toBe($code);
})->with([
    'too short' => ['elevenchars', 'password_too_short'],
    '12 code points with multibyte' => ['ééééééééééé1', null],
    'NFD counts after NFC' => [str_repeat("e\u{0301}", 11), 'password_too_short'],
    'too long' => [str_repeat('a', 129), 'password_too_long'],
    '128 is fine' => [str_repeat('ab', 64), null],
    'spaces allowed, not truncated' => ['correct horse battery', null],
    'equal to the email' => ['MAYA@example.com', 'password_is_email'],
    'common' => ['Password123456', 'password_common'],
]);

it('exposes the password policy prop on pages with a password field', function (): void {
    $this->get('/register')->assertInertia(fn (Assert $p) => $p->component('Auth/Register')
        ->where('passwordPolicy', ['minLength' => 12, 'maxLength' => 128, 'unit' => 'code_points']));
});

it('registers with a usr_ UUIDv7 id, citext email, argon2id hash, nobody signed in', function (): void {
    $this->post('/register', ['name' => 'Maya', 'email' => 'Maya@Example.com', 'password' => 'a long enough passphrase'])
        ->assertRedirect('/verify-email');

    $user = User::query()->sole();
    expect($user->publicId())->toStartWith('usr_')
        ->and(EntityId::decode($user->publicId())[1])->toBe($user->id)
        ->and(substr($user->id, 14, 1))->toBe('7')
        ->and($user->password_hash)->toStartWith('$argon2id$')
        ->and(strlen($user->webauthn_user_handle))->toBe(32)
        ->and(User::query()->where('email', 'maya@example.com')->exists())->toBeTrue()
        ->and(DB::table('workspaces')->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(mailed('email-verify', 'Maya@Example.com'))->toBeTrue();
    $this->assertGuest('web');
});

it('answers a taken address exactly like a new one and sends account-exists (no enumeration)', function (): void {
    makeUser(['email' => 'taken@example.com']);

    $fresh = $this->post('/register', ['name' => 'A', 'email' => 'new@example.com', 'password' => 'a long enough passphrase']);
    newBrowser();
    $taken = $this->post('/register', ['name' => 'B', 'email' => 'TAKEN@example.com', 'password' => 'a long enough passphrase']);

    expect($taken->status())->toBe($fresh->status())
        ->and($taken->headers->get('Location'))->toBe($fresh->headers->get('Location'))
        ->and(User::query()->count())->toBe(2)
        ->and(mailed('account-exists', 'taken@example.com'))->toBeTrue();
});

it('sends nothing to a tombstoned address on sign-up', function (): void {
    makeUser(['email' => 'gone@example.com', 'deleted_at' => now()]);
    $this->post('/register', ['name' => 'B', 'email' => 'gone@example.com', 'password' => 'a long enough passphrase'])->assertRedirect('/verify-email');
    expect(mailed('account-exists'))->toBeFalse();
});

it('refuses weak passwords at sign-up with the contract codes', function (): void {
    $this->post('/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'short'])
        ->assertSessionHasErrors(['password' => 'password_too_short']);
});

it('limits sign-ups per address with a visible retryAt', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->post('/register', ['name' => 'A', 'email' => "a{$i}@example.com", 'password' => 'a long enough passphrase']);
    }
    $this->post('/register', ['name' => 'A', 'email' => 'z@example.com', 'password' => 'a long enough passphrase'])
        ->assertSessionHasErrors(['form' => 'throttled'])
        ->assertSessionHas('identity.retry_at');
});

it('never verifies on GET; the POST verifies (TM-T153)', function (): void {
    $this->post('/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'a long enough passphrase']);
    $link = mailedLink('email-verify');

    $this->get($link)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/VerifyEmailLink')->where('link.state', 'confirm'))
        ->assertHeader('Referrer-Policy', 'no-referrer');
    expect(User::query()->sole()->email_verified_at)->toBeNull();

    $this->post($link)->assertRedirect('/projects');
    expect(User::query()->sole()->email_verified_at)->not->toBeNull();
    $this->assertAuthenticated('web');
});

it('refuses a verification link after 60 minutes or with a bad signature', function (): void {
    $this->post('/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'a long enough passphrase']);
    $link = mailedLink('email-verify');

    $this->get($link.'x')->assertForbidden();
    $this->travel(61)->minutes();
    $this->post($link)->assertForbidden();
    expect(User::query()->sole()->email_verified_at)->toBeNull();
});

it('gives an unverified account no session on password sign-in, only the verify page; resend is limited to one per 60 s (F1)', function (): void {
    $user = makeUser(['email_verified_at' => null]);
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])->assertRedirect('/verify-email');
    $this->assertGuest('web');
    expect(UserSession::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(session('identity.pending_registration'))->toBe($user->id);

    $this->post('/email/verification-notification')->assertSessionHas('flash.code', 'verification_sent');
    $this->post('/email/verification-notification')->assertSessionHasErrors(['form' => 'throttled']);
    $this->travel(61)->seconds();
    $this->post('/email/verification-notification')->assertSessionHasNoErrors();
});
