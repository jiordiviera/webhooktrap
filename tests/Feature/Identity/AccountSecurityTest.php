<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\AuditLog;
use App\Modules\Identity\Domain\RecoveryCode;
use App\Modules\Identity\Domain\Totp;
use App\Modules\Identity\Domain\TotpFactor;
use App\Modules\Identity\Domain\UserSession;
use App\Modules\Identity\Domain\WebAuthnCredential;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

/** Signs in, then makes the recent authentication stale. */
function signInStale($user): void
{
    signIn($user);
    test()->travel(11)->minutes();
}

it('requires a recent authentication for sensitive actions and returns to them (domain §2.1)', function (): void {
    $user = makeUser();
    signInStale($user);

    $this->put('/account/password', ['password' => 'another long passphrase', 'password_confirmation' => 'another long passphrase'])
        ->assertRedirect('/confirm-auth?next=%2Faccount%2Fsecurity&action=confirm&reason=change_password');
    $this->get('/confirm-auth?next=/account/security')->assertInertia(fn (Assert $p) => $p->component('Auth/ConfirmAuth')
        ->where('methods', ['password'])->where('secondFactorRequired', false));

    $this->post('/confirm-auth', ['password' => FIXTURE_PASSWORD, 'next' => '/account/security'])->assertRedirect('/account/security');
    $this->put('/account/password', ['password' => 'another long passphrase', 'password_confirmation' => 'another long passphrase'])
        ->assertRedirect('/account/security');
});

it('never redirects off-site from /confirm-auth', function (): void {
    signIn(makeUser());
    $this->post('/confirm-auth', ['password' => FIXTURE_PASSWORD, 'next' => '//evil.example/x'])->assertRedirect('/account/security');
});

it('holds /confirm-auth after 10 failures and counts them toward the account sign-in key (TH-1b, TM-T159)', function (): void {
    $user = makeUser();
    signInStale($user);
    for ($i = 0; $i < 10; $i++) {
        $this->post('/confirm-auth', ['password' => 'wrong password!!'])->assertSessionHasErrors(['form' => 'confirmation_failed']);
    }
    $this->post('/confirm-auth', ['password' => FIXTURE_PASSWORD])->assertSessionHasErrors(['form' => 'throttled']);

    for ($i = 0; $i < 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$i}"])->post('/sign-in/password', ['email' => $user->email, 'password' => 'wrong password!!']);
    }
    expect(auditCount('sign_in_held', $user))->toBe(1);
});

it('counts TOTP failures at /confirm-auth toward the per-account second-factor counter (WA-3)', function (): void {
    $user = makeUser();
    enableTotp($user);
    actAs($user, null);
    $this->post('/confirm-auth', ['code' => '000000']);
    expect(auditCount('second_factor_failed', $user))->toBe(1);
});

it('lists OAuth providers only for OAuth-only accounts and asks for a second factor for deletion on 2FA accounts', function (): void {
    $oauth = makeUser(['password_hash' => null]);
    $oauth->identities()->create(['provider' => 'github', 'provider_subject' => '42', 'provider_email_verified' => true, 'provider_label' => 'maya', 'linked_at' => now(), 'linked_via' => 'signup']);
    actAs($oauth, null, 'github');
    $this->get('/confirm-auth')->assertInertia(fn (Assert $p) => $p->where('methods', ['github']));

    $twoFa = makeUser();
    enableTotp($twoFa);
    actAs($twoFa, null);
    $this->get('/confirm-auth?action=delete_account')->assertInertia(fn (Assert $p) => $p
        ->where('methods', ['totp'])->where('secondFactorRequired', true)->where('action', 'delete_account'));
});

it('changes the password, revokes every other session and keeps this one', function (): void {
    $user = makeUser();
    signIn($user);
    $current = session('identity.user_session_id');
    UserSession::query()->create(['user_id' => $user->id, 'created_at' => now(), 'last_seen_at' => now(), 'idle_expires_at' => now()->addDay(), 'absolute_expires_at' => now()->addDays(30), 'first_factor' => 'password', 'ua_label' => 'Other', 'ip_prefix' => '10.0.0.0/24']);

    $this->put('/account/password', ['password' => 'another long passphrase', 'password_confirmation' => 'another long passphrase'])
        ->assertRedirect('/account/security');

    expect(UserSession::query()->where('revoked_reason', 'password_changed')->count())->toBe(1)
        ->and(UserSession::query()->find($current)?->revoked_at)->toBeNull()
        ->and(auditCount('password_changed', $user))->toBe(1)
        ->and(mailed('password-changed', $user->email))->toBeTrue();
    $this->get('/account/security')->assertOk();
});

it('enrols TOTP: secret shown once, confirm with a code, recovery codes on the first second factor', function (): void {
    $user = makeUser();
    signIn($user);

    $this->post('/account/security/totp')->assertRedirect('/account/security');
    $setup = session('identity.totp_setup');
    expect($setup['uri'])->toStartWith('otpauth://totp/');
    $secret = decrypt(TotpFactor::query()->sole()->secret);

    $this->post('/account/security/totp/confirm', ['code' => Totp::codeAt($secret, Totp::step(now()->getTimestamp()))])
        ->assertRedirect('/account/security');

    expect(session('identity.recovery_codes'))->toHaveCount(10)
        ->and($user->refresh()->two_factor_enabled_at)->not->toBeNull()
        ->and(RecoveryCode::query()->count())->toBe(10)
        ->and(auditCount('totp_enabled', $user))->toBe(1)
        ->and(mailed('totp-enabled'))->toBeTrue();
    $this->get('/account/security')->assertInertia(fn (Assert $p) => $p->where('security.twoFactor', true)
        ->where('security.totp.enabled', true)->where('security.recoveryCodesLeft', 10));
});

it('removing the last second factor disables 2FA and deletes the recovery codes', function (): void {
    $user = makeUser();
    enableTotp($user);
    giveRecoveryCodes($user);
    actAs($user);

    $this->delete('/account/security/totp')->assertRedirect('/account/security');

    expect($user->refresh()->two_factor_enabled_at)->toBeNull()
        ->and(RecoveryCode::query()->count())->toBe(0)
        ->and(auditCount('totp_disabled', $user))->toBe(1)
        ->and(auditCount('two_factor_disabled', $user))->toBe(1);
});

it('regenerates recovery codes (replaces the set) only with 2FA on', function (): void {
    $user = makeUser();
    signIn($user);
    $this->post('/account/security/recovery-codes')->assertSessionHasErrors(['form' => 'two_factor_required']);

    enableTotp($user);
    giveRecoveryCodes($user);
    actAs($user);
    $this->post('/account/security/recovery-codes')->assertRedirect('/account/security');
    expect(RecoveryCode::query()->count())->toBe(10)->and(auditCount('recovery_codes_regenerated', $user))->toBe(1);
});

it('renames, removes and unblocks WebAuthn keys with the last-method rule (domain §2.2, TM-T155)', function (): void {
    $user = makeUser(['password_hash' => null]);
    $passkey = addKey($user);
    actAs($user, 'passkey', 'passkey');

    $this->patch("/account/security/keys/{$passkey->publicId()}", ['name' => "  Laptop\u{202E}  "])->assertRedirect('/account/security');
    expect($passkey->refresh()->name)->toBe('Laptop');

    $this->delete("/account/security/keys/{$passkey->publicId()}")->assertSessionHasErrors(['form' => 'last_sign_in_method']);

    $passkey->forceFill(['flagged_at' => now(), 'flag_cause' => 'counter', 'flag_count' => 1, 'sign_count' => 57])->save();
    $this->postJson("/account/security/keys/{$passkey->publicId()}/unblock")->assertOk()->assertJson(['flagged' => false]);
    expect($passkey->refresh()->sign_count)->toBe(57)->and(auditCount('key_unblocked', $user))->toBe(1);

    $passkey->forceFill(['flagged_at' => now(), 'flag_cause' => 'counter', 'flag_count' => 2])->save();
    $this->postJson("/account/security/keys/{$passkey->publicId()}/unblock")->assertStatus(422)->assertJson(['error' => 'unblock_not_allowed']);

    $other = makeUser();
    $foreign = addKey($other);
    $this->delete("/account/security/keys/{$foreign->publicId()}")->assertNotFound();
});

it('does not count a flagged credential as a second factor or a first factor', function (): void {
    $user = makeUser(['password_hash' => null]);
    $key = addKey($user, ['flagged_at' => now(), 'flag_cause' => 'counter', 'flag_count' => 1]);
    $second = addKey($user);

    expect($user->hasSecondFactor())->toBeTrue();
    $second->delete();
    expect($user->hasSecondFactor())->toBeFalse()
        ->and(WebAuthnCredential::query()->find($key->id))->not->toBeNull();
});

it('lists sessions with this browser first and revokes idempotently (domain §2.7)', function (): void {
    $user = makeUser();
    signIn($user);
    $current = UserSession::query()->sole();
    $other = UserSession::query()->create(['user_id' => $user->id, 'created_at' => now(), 'last_seen_at' => now()->addMinute(), 'idle_expires_at' => now()->addDay(), 'absolute_expires_at' => now()->addDays(30), 'first_factor' => 'github', 'ua_label' => 'Chrome on Windows', 'ip_prefix' => '198.51.100.0/24']);
    $foreign = UserSession::query()->create(['user_id' => makeUser()->id, 'created_at' => now(), 'last_seen_at' => now(), 'idle_expires_at' => now()->addDay(), 'absolute_expires_at' => now()->addDays(30), 'first_factor' => 'password', 'ua_label' => 'Y', 'ip_prefix' => '10.0.0.0/24']);

    $this->get('/account/security')->assertInertia(fn (Assert $p) => $p
        ->where('security.sessions.0.id', $current->publicId())->where('security.sessions.0.current', true)
        ->where('security.sessions.1.browser', 'Chrome on Windows'));

    $this->delete("/account/sessions/{$current->publicId()}")->assertSessionHasErrors(['form' => 'current_session']);
    $this->delete("/account/sessions/{$foreign->publicId()}")->assertSessionHas('flash.code', 'session_already_ended');
    $this->delete('/account/sessions/ses_00000000000000000000000000')->assertSessionHas('flash.code', 'session_already_ended');
    expect($foreign->refresh()->revoked_at)->toBeNull()->and(auditCount('session_revoked'))->toBe(0);

    $this->delete("/account/sessions/{$other->publicId()}")->assertSessionHas('flash.code', 'session_revoked');
    $this->delete("/account/sessions/{$other->publicId()}")->assertSessionHas('flash.code', 'session_already_ended');
    expect($other->refresh()->revoked_reason)->toBe('user')->and(auditCount('session_revoked', $user))->toBe(1);
    $this->delete('/account/sessions')->assertSessionHas('flash.code', 'no_other_sessions');
});

it('refuses to revoke other sessions without a recent authentication (TM-T156)', function (): void {
    $user = makeUser();
    signInStale($user);
    $this->delete('/account/sessions')->assertRedirect('/confirm-auth?next=%2Faccount%2Fsecurity&action=confirm&reason=sign_out_others');
});

it('shows the security log from the allow-list only, with display params', function (): void {
    $user = makeUser();
    signIn($user);
    app(AuditLog::class)->record('instance.secret_used', $user->id);

    $this->get('/account/security')->assertInertia(fn (Assert $p) => $p
        ->has('security.securityLog', 1)
        ->where('security.securityLog.0.action', 'signed_in')
        ->where('security.securityLog.0.params.how.first', 'password')
        ->where('security.passwordPolicy.minLength', 12));
});
