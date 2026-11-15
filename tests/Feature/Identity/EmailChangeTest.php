<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccountRecoveryRequest;
use App\Modules\Identity\Domain\EmailChangeRequest;
use App\Modules\Identity\Domain\UserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

it('needs a recent authentication, and a second factor on 2FA accounts', function (): void {
    $user = makeUser();
    actAs($user, null);
    $this->post('/account/email', ['email' => 'new@example.com'])->assertRedirect('/confirm-auth?next=%2Faccount%2Fsecurity&action=change_email');

    enableTotp($user);
    actAs($user, 'password');
    $this->post('/account/email', ['email' => 'new@example.com'])->assertSessionHasErrors(['form' => 'second_factor_required']);
    actAs($user, 'totp');
    $this->post('/account/email', ['email' => 'new@example.com'])->assertSessionHas('flash.code', 'email_change_sent');
    expect(EmailChangeRequest::query()->sole()->old_required)->toBeFalse()
        ->and(mailed('email-change-requested', $user->email))->toBeTrue()
        ->and(mailed('email-change-verify', 'new@example.com'))->toBeTrue();
});

it('answers uniformly when the new address is taken; the taken address gets no usable link', function (): void {
    $user = makeUser();
    makeUser(['email' => 'taken@example.com']);
    actAs($user);

    $this->post('/account/email', ['email' => 'taken@example.com'])->assertSessionHas('flash.code', 'email_change_sent');
    expect(mailed('email-change-taken', 'taken@example.com'))->toBeTrue()->and(mailed('email-change-verify'))->toBeFalse();
});

it('requires both addresses on an account without a second factor, in either order (EC-1)', function (string $first): void {
    $user = makeUser(['email' => 'old@example.com']);
    $other = actAs($user);
    UserSession::query()->create(['user_id' => $user->id, 'created_at' => now(), 'last_seen_at' => now(), 'idle_expires_at' => now()->addDay(), 'absolute_expires_at' => now()->addDays(30), 'first_factor' => 'password', 'ua_label' => 'Other', 'ip_prefix' => '10.0.0.0/24']);
    DB::table('password_reset_tokens')->insert(['email' => 'old@example.com', 'token' => 'x', 'created_at' => now()]);
    $this->post('/account/email', ['email' => 'new@example.com']);
    $new = mailedLink('email-change-verify');
    $old = mailedLink('email-change-approve');
    expect($old)->toStartWith('/email-change-old/');

    [$a, $b, $waiting] = $first === 'new' ? [$new, $old, 'email_change_waiting_old'] : [$old, $new, 'email_change_waiting_new'];
    $this->get($a)->assertOk();
    $this->post($a)->assertSessionHas('flash.code', $waiting);
    expect($user->refresh()->email)->toBe('old@example.com');
    $this->get('/account/security')->assertInertia(fn (Assert $p) => $p->where('security.pendingEmail.awaiting', $first === 'new' ? 'old' : 'new'));

    $this->post($b)->assertSessionHas('flash.code', 'email_changed');
    expect($user->refresh()->email)->toBe('new@example.com')
        ->and(UserSession::query()->where('revoked_reason', 'email_changed')->count())->toBe(1)
        ->and(UserSession::query()->find($other->id)?->revoked_at)->toBeNull()
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and(auditCount('email_change_confirmed', $user))->toBe(2)
        ->and(auditCount('email_changed', $user))->toBe(1)
        ->and(queuedMails('email-changed', 'old@example.com')->first()?->params['masked'])->toBe('n***@e***.com');
})->with(['new', 'old']);

it('shows the four link states and stores the link as the intended URL, never in next', function (): void {
    $user = makeUser();
    actAs($user);
    $this->post('/account/email', ['email' => 'new@example.com']);
    $link = mailedLink('email-change-verify');

    newBrowser();
    $this->get($link)->assertInertia(fn (Assert $p) => $p->component('Auth/EmailChangeLink')->where('link', ['state' => 'sign_in_required']));
    expect(session('url.intended'))->toEndWith($link);

    actAs(makeUser(['email' => 'someone@example.com']));
    $this->get($link)->assertInertia(fn (Assert $p) => $p->where('link.state', 'other_account')->where('link.signedInAs', 'someone@example.com'));
    $this->post($link)->assertSessionHasErrors(['form' => 'link_invalid']);

    actAs($user);
    $this->get($link)->assertInertia(fn (Assert $p) => $p->where('link.state', 'confirm')->where('link.needsOld', true));
    $this->get('/email-change/unknown-token')->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));
    $this->travel(61)->minutes();
    $this->get($link)->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));
});

it('replaces a pending change and voids both earlier links; cancel voids them too', function (): void {
    $user = makeUser();
    actAs($user);
    $this->post('/account/email', ['email' => 'one@example.com']);
    $first = mailedLink('email-change-verify');
    $this->post('/account/email', ['email' => 'two@example.com']);

    $this->get($first)->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));
    expect(EmailChangeRequest::query()->where('cancelled_reason', 'replaced')->count())->toBe(1);

    $this->delete('/account/email');
    expect(EmailChangeRequest::query()->whereNull('cancelled_at')->count())->toBe(0)->and(auditCount('email_change_cancelled', $user))->toBe(1);
});

it('refuses a change while a recovery is open or locked (RC-3, TM-T125)', function (): void {
    $user = makeUser();
    AccountRecoveryRequest::query()->create(['user_id' => $user->id, 'token_hash' => random_bytes(32), 'requested_at' => now(), 'confirmed_at' => now(), 'completes_at' => now()->addDays(3)]);
    actAs($user);
    $this->post('/account/email', ['email' => 'new@example.com'])->assertSessionHasErrors(['form' => 'recovery_lock']);
});

it('refuses unverified accounts', function (): void {
    actAs(makeUser(['email_verified_at' => null]));
    $this->post('/account/email', ['email' => 'new@example.com'])->assertRedirect('/verify-email');
    $this->postJson('/account/email', ['email' => 'new@example.com'])->assertStatus(403)->assertJson(['error' => 'email_unverified']);
});
