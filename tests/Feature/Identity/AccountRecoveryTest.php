<?php

declare(strict_types=1);

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\AuditLog;
use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Application\AccountRecovery;
use App\Modules\Identity\Application\EmailChanges;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Identity\Domain\AccountRecoveryRequest;
use App\Modules\Identity\Domain\EmailChangeRequest;
use App\Modules\Identity\Domain\RecoveryCode;
use App\Modules\Identity\Domain\TotpFactor;
use App\Modules\Identity\Domain\UserSession;
use App\Modules\Identity\Events\AccountRecoveryCompleted;
use App\Modules\Identity\Jobs\RunIdentityMaintenance;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

/** A 2FA user with an open recovery whose wait has started; returns [user, secret]. */
function recoveringUser(): array
{
    $user = makeUser();
    $secret = enableTotp($user);
    giveRecoveryCodes($user);
    test()->post('/account-recovery', ['email' => $user->email]);
    test()->post(mailedLink('recovery-request'));

    return [$user, $secret];
}

it('answers uniformly and sends a link only for a live 2FA account', function (): void {
    $plain = makeUser();
    $this->post('/account-recovery', ['email' => 'nobody@example.com'])->assertSessionHas('flash.code', 'recovery_link_sent');
    $this->post('/account-recovery', ['email' => $plain->email])->assertSessionHas('flash.code', 'recovery_link_sent');
    expect(AccountRecoveryRequest::query()->count())->toBe(0);

    $user = makeUser();
    enableTotp($user);
    $this->post('/account-recovery', ['email' => $user->email]);
    expect(AccountRecoveryRequest::query()->count())->toBe(1)->and(auditCount('recovery_requested', $user))->toBe(1);
});

it('starts the 72-hour wait only on the button, never on GET (TM-T153)', function (): void {
    $user = makeUser();
    enableTotp($user);
    $this->post('/account-recovery', ['email' => $user->email]);
    $link = mailedLink('recovery-request');

    $this->get($link)->assertInertia(fn (Assert $p) => $p->component('Auth/AccountRecoveryLink')->where('link.state', 'confirm'));
    expect(AccountRecoveryRequest::query()->sole()->confirmed_at)->toBeNull();

    $this->post($link)->assertRedirect('/sign-in');
    $request = AccountRecoveryRequest::query()->sole();
    expect($request->confirmed_at)->not->toBeNull()
        ->and($request->completes_at->diffInHours($request->confirmed_at, true))->toEqual(72)
        ->and(auditCount('recovery_confirmed', $user))->toBe(1);
    $this->get($link)->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));
});

it('closes declined and expired requests without a cool-down; a confirmed cancel starts 30 days (TM-T154)', function (): void {
    $user = makeUser();
    enableTotp($user);
    $this->post('/account-recovery', ['email' => $user->email]);
    $this->delete(mailedLink('recovery-request'));
    expect(AccountRecoveryRequest::query()->sole()->cancelled_by)->toBe('declined');

    $this->post('/account-recovery', ['email' => $user->email]);
    $this->travel(61)->minutes();
    app(AccountRecovery::class)->expireUnconfirmed();
    expect(AccountRecoveryRequest::query()->where('cancelled_by', 'link_expired')->count())->toBe(1);

    $this->post('/account-recovery', ['email' => $user->email]);
    $this->post(mailedLink('recovery-request'));
    actAs($user);
    $this->delete('/account-recovery')->assertSessionHas('flash.code', 'recovery_cancelled');
    expect(AccountRecoveryRequest::query()->where('cancelled_by', 'user')->count())->toBe(1);

    newBrowser();
    $this->post('/account-recovery', ['email' => $user->email]);
    expect(AccountRecoveryRequest::query()->count())->toBe(3);
    $this->travel(31)->days();
    $this->post('/account-recovery', ['email' => $user->email]);
    expect(AccountRecoveryRequest::query()->count())->toBe(4);
});

it('is cancelled by a sign-in that completes a second factor (RC-1, TM-T125)', function (): void {
    [$user, $secret] = recoveringUser();
    newBrowser();
    signIn($user);
    $this->post('/two-factor/totp', ['code' => totpCode($secret)]);

    expect(AccountRecoveryRequest::query()->sole()->cancelled_by)->toBe('second_factor_signin')
        ->and(auditCount('recovery_cancelled', $user))->toBe(1);
});

it('is cancelled by a TOTP passed at /confirm-auth (RC-1)', function (): void {
    [$user, $secret] = recoveringUser();
    actAs($user, null);
    $this->post('/confirm-auth', ['code' => totpCode($secret)]);
    expect(AccountRecoveryRequest::query()->sole()->cancelled_by)->toBe('second_factor_signin');
});

it('extends the wait to 7 days after a password reset by email (RC-2, TM-T125)', function (): void {
    [$user] = recoveringUser();
    newBrowser();
    $this->post('/forgot-password', ['email' => $user->email]);
    $token = str(mailedLink('password-reset'))->afterLast('/')->toString();
    $this->post('/reset-password', ['token' => $token, 'password' => 'a brand new passphrase', 'password_confirmation' => 'a brand new passphrase']);

    $request = AccountRecoveryRequest::query()->sole();
    expect($request->completes_at->diffInDays(now(), true))->toBeGreaterThanOrEqual(6.99)
        ->and($request->wait_extended_at)->not->toBeNull()
        ->and(auditCount('recovery_extended', $user))->toBe(1);
});

it('waits 7 days from the request when a reset happened in the 7 days before it (RC-2)', function (): void {
    $user = makeUser();
    enableTotp($user);
    app(AuditLog::class)->record('password_reset', $user->id);
    $this->travel(2)->days();
    $this->post('/account-recovery', ['email' => $user->email]);
    $this->post(mailedLink('recovery-request'));
    $request = AccountRecoveryRequest::query()->sole();
    expect($request->completes_at->diffInDays($request->requested_at, true))->toEqual(7);
});

it('completes after the wait: factors and password removed, sessions revoked, finish link, review (TM-T125, TM-T154)', function (): void {
    Event::fake([AccountRecoveryCompleted::class]);
    [$user] = recoveringUser();
    $session = actAs($user, 'totp');
    EmailChangeRequest::query()->create(['user_id' => $user->id, 'new_email' => 'x@example.com', 'token_hash' => random_bytes(32), 'requested_at' => now(), 'expires_at' => now()->addHour(), 'old_required' => false]);

    $this->post('/account/email', ['email' => 'new@example.com'])->assertSessionHasErrors(['form' => 'recovery_lock']);

    $this->travel(73)->hours();
    (new RunIdentityMaintenance)->handle(app(Clock::class), app(AccountRecovery::class), app(EmailChanges::class), app(AccountDeletion::class), app(GuestSessions::class));

    $user->refresh();
    expect($user->password_hash)->toBeNull()
        ->and($user->two_factor_enabled_at)->toBeNull()
        ->and(TotpFactor::query()->count())->toBe(0)
        ->and(RecoveryCode::query()->count())->toBe(0)
        ->and($session->refresh()->revoked_reason)->toBe('recovery')
        ->and(AccountRecoveryRequest::query()->sole()->lock_until)->not->toBeNull()
        ->and(auditCount('recovery_completed', $user))->toBe(1);
    Event::assertDispatched(AccountRecoveryCompleted::class);

    newBrowser();
    $finish = mailedLink('recovery-completed');
    $this->get($finish)->assertInertia(fn (Assert $p) => $p->where('link.state', 'valid'));
    $this->post($finish, ['password' => 'a recovered passphrase', 'password_confirmation' => 'a recovered passphrase'])
        ->assertRedirect('/account/security/review');
    expect(UserSession::query()->whereNull('revoked_at')->sole()->first_factor)->toBe('recovery_finish');
    $this->get('/account/security/review')->assertInertia(fn (Assert $p) => $p->component('Account/SecurityReview')->has('review.log'));
    $this->post($finish, ['password' => 'x', 'password_confirmation' => 'x'])->assertSessionHasErrors(['form' => 'link_invalid']);

    actAs($user->refresh());
    $this->post('/account/email', ['email' => 'new@example.com'])->assertSessionHasErrors(['form' => 'recovery_lock']);
    $this->travel(8)->days();
    actAs($user);
    $this->get('/account/security/review')->assertRedirect('/account/security');
});

it('answers 404 on self-host', function (): void {
    config(['identity.instance' => 'selfhost']);
    $this->post('/account-recovery', ['email' => 'a@example.com'])->assertNotFound();
});
