<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\UserSession;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/** Wraps the hasher to count verifications (TM-T159, TM-T160). */
function countHashChecks(): object
{
    $real = app('hash');
    $counter = new class($real)
    {
        public int $checks = 0;

        public function __construct(private readonly object $real) {}

        public function check(string $value, ?string $hash, array $options = []): bool
        {
            $this->checks++;

            return $this->real->check($value, $hash, $options);
        }

        public function __call(string $method, array $args): mixed
        {
            return $this->real->{$method}(...$args);
        }
    };
    app()->instance('hash', $counter);
    Hash::clearResolvedInstance('hash');

    return $counter;
}

it('signs in with a password, regenerates the session and records a user_sessions row', function (): void {
    $user = makeUser();
    $this->get('/sign-in');
    $before = session()->getId();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Gecko/20100101 Firefox/130.0')
        ->post('/sign-in/password', ['email' => strtoupper($user->email), 'password' => FIXTURE_PASSWORD])
        ->assertRedirect('/projects');

    $this->assertAuthenticatedAs($user, 'web');
    $row = UserSession::query()->sole();
    expect(session()->getId())->not->toBe($before)
        ->and(session('identity.user_session_id'))->toBe($row->id)
        ->and($row->first_factor)->toBe('password')
        ->and($row->second_factor)->toBeNull()
        ->and($row->ua_label)->toBe('Firefox on macOS')
        ->and($row->ip_prefix)->toBe('127.0.0.0/24')
        ->and($row->absolute_expires_at->diffInDays($row->created_at, true))->toEqual(30)
        ->and(auditCount('signed_in', $user))->toBe(1);
});

it('gives one uniform failure for a wrong password, an unknown email and a password-less account', function (): void {
    $user = makeUser();
    $oauthOnly = makeUser(['password_hash' => null]);

    foreach ([[$user->email, 'wrong password!!'], ['nobody@example.com', 'whatever pass'], [$oauthOnly->email, 'whatever pass']] as [$email, $password]) {
        $this->from('/sign-in')->post('/sign-in/password', ['email' => $email, 'password' => $password])
            ->assertRedirect('/sign-in')->assertSessionHasErrors(['form' => 'invalid_credentials']);
    }
    $this->assertGuest('web');
});

it('runs the same argon2id verification for unknown, password-less and wrong-password cases (TM-T160)', function (): void {
    $user = makeUser();
    $oauthOnly = makeUser(['password_hash' => null]);
    $hashes = countHashChecks();

    $this->post('/sign-in/password', ['email' => 'nobody@example.com', 'password' => 'whatever pass']);
    $this->post('/sign-in/password', ['email' => $oauthOnly->email, 'password' => 'whatever pass']);
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => 'wrong password!!']);

    expect($hashes->checks)->toBe(3);
});

it('blocks the (email, address) pair after 5 failures; during the hold the correct password is not verified (TM-T159)', function (): void {
    $user = makeUser();
    for ($i = 0; $i < 5; $i++) {
        $this->post('/sign-in/password', ['email' => $user->email, 'password' => 'wrong password!!']);
    }
    $hashes = countHashChecks();

    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])
        ->assertSessionHasErrors(['form' => 'throttled'])->assertSessionHas('identity.retry_at');

    expect($hashes->checks)->toBe(0);
    $this->assertGuest('web');
});

it('counts unknown emails in the per-pair key too, so the answer does not reveal an account', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/sign-in/password', ['email' => 'ghost@example.com', 'password' => 'wrong password!!']);
    }
    $this->post('/sign-in/password', ['email' => 'ghost@example.com', 'password' => 'wrong password!!'])
        ->assertSessionHasErrors(['form' => 'throttled']);
});

it('holds password sign-in for the account after 20 failures from any address, audited once', function (): void {
    $user = makeUser();
    for ($i = 0; $i < 20; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$i}"])
            ->post('/sign-in/password', ['email' => $user->email, 'password' => 'wrong password!!']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])
        ->assertSessionHasErrors(['form' => 'throttled']);
    expect(auditCount('sign_in_held', $user))->toBe(1);

    $this->travel(16)->minutes();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])->assertRedirect('/projects');
});

it('keys address limits on the IPv6 /64', function (): void {
    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "2001:db8:1:2::{$i}"])
            ->post('/sign-in/password', ['email' => 'x@example.com', 'password' => 'wrong password!!']);
    }
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:2:ffff::1'])
        ->post('/sign-in/password', ['email' => 'x@example.com', 'password' => 'wrong password!!'])
        ->assertSessionHasErrors(['form' => 'throttled']);
    $this->withServerVariables(['REMOTE_ADDR' => '2001:db8:1:3::1'])
        ->post('/sign-in/password', ['email' => 'x@example.com', 'password' => 'wrong password!!'])
        ->assertSessionHasErrors(['form' => 'invalid_credentials']);
});

it('blocks an address after 30 failures across emails', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $this->post('/sign-in/password', ['email' => "u{$i}@example.com", 'password' => 'wrong password!!']);
    }
    $this->post('/sign-in/password', ['email' => 'fresh@example.com', 'password' => 'wrong password!!'])
        ->assertSessionHasErrors(['form' => 'throttled']);
});

it('treats a tombstoned or disabled account as unknown', function (): void {
    $gone = makeUser(['deleted_at' => now()]);
    $disabled = makeUser(['disabled_at' => now()]);
    $this->post('/sign-in/password', ['email' => $gone->email, 'password' => FIXTURE_PASSWORD])->assertSessionHasErrors(['form' => 'invalid_credentials']);
    $this->post('/sign-in/password', ['email' => $disabled->email, 'password' => FIXTURE_PASSWORD])->assertSessionHasErrors(['form' => 'invalid_credentials']);
});

it('upgrades a stored hash at the next successful sign-in when the cost changed', function (): void {
    $user = makeUser();
    config(['hashing.argon.time' => 2]);
    app('hash')->forgetDrivers();
    signIn($user);
    expect($user->refresh()->password_hash)->toContain('t=2');
});

it('signs out and revokes the session row', function (): void {
    signIn(makeUser());
    $this->post('/sign-out')->assertRedirect('/');
    expect(UserSession::query()->sole()->revoked_reason)->toBe('signed_out');
    $this->assertGuest('web');
});

it('ends the browser session at the next request once its row is revoked (TM-T156)', function (): void {
    $user = makeUser();
    signIn($user);
    $this->get('/account/security')->assertOk();
    UserSession::query()->sole()->forceFill(['revoked_at' => now(), 'revoked_reason' => 'admin'])->save();
    $this->get('/account/security')->assertOk(); // cached liveness, at most 30 s
    $this->travel(31)->seconds();
    $this->get('/account/security')->assertRedirect('/sign-in');
});

it('renders the sign-in page with the contract props', function (): void {
    $this->get('/sign-in')->assertInertia(fn (Assert $p) => $p->component('Auth/SignIn')
        ->where('passkeysAvailable', true)->has('next')->where('principal.kind', 'anonymous')->has('instance'));
});
