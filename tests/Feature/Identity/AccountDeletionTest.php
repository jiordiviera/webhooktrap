<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\AuditLog;
use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Domain\AccountDeletionRequest;
use App\Modules\Identity\Domain\AccountRecoveryRequest;
use App\Modules\Identity\Domain\User;
use App\Modules\Retention\Application\DeletionLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

function confirmFor(string $purpose, string $method): void
{
    session()->put('identity.confirmation', ['purpose' => $purpose, 'method' => $method, 'second_factor' => in_array($method, ['totp', 'webauthn'], true), 'at' => now()->getTimestamp(), 'consumed' => false]);
}

it('refuses DELETE /account without a fresh, unused delete_account confirmation (DL-1, TM-T162)', function (): void {
    $user = makeUser();
    $secret = enableTotp($user);
    actAs($user, 'totp');
    $this->delete('/account', ['confirm' => true])->assertSessionHasErrors(['form' => 'confirmation_stale']);

    confirmFor('change_email', 'totp');
    $this->delete('/account', ['confirm' => true])->assertSessionHasErrors(['form' => 'confirmation_stale']);

    confirmFor('delete_account', 'totp');
    $this->travel(6)->minutes();
    $this->delete('/account', ['confirm' => true])->assertSessionHasErrors(['form' => 'confirmation_stale']);

    $this->post('/confirm-auth', ['action' => 'delete_account', 'code' => totpCode($secret, 0)]);
    $this->delete('/account', ['confirm' => false])->assertSessionHasErrors(['form' => 'confirmation_required']);
    expect($user->refresh()->deleted_at)->toBeNull();
});

it('asks a 2FA account for a second-factor confirmation', function (): void {
    $user = makeUser();
    enableTotp($user);
    actAs($user);
    confirmFor('delete_account', 'password');
    $this->delete('/account', ['confirm' => true])->assertSessionHasErrors(['form' => 'second_factor_required']);
});

it('tombstones a 2FA account at once: all sessions revoked, email queued, audit with email_hmac', function (): void {
    $user = makeUser(['email' => 'maya@example.com']);
    enableTotp($user);
    $row = actAs($user, 'totp');
    confirmFor('delete_account', 'totp');

    $this->delete('/account', ['confirm' => true])->assertRedirect('/')->assertSessionHas('flash.code', 'account_deleted');

    $user->refresh();
    expect($user->deleted_at)->not->toBeNull()
        ->and($row->refresh()->revoked_reason)->toBe('account_deleted')
        ->and(mailed('account-deleted', 'maya@example.com'))->toBeTrue()
        ->and(DB::table('audit_log')->where('action', 'account.deleted')->whereNotNull('email_hmac')->count())->toBe(1);
    $this->assertGuest('web');
    $this->delete('/account', ['confirm' => true])->assertRedirect('/sign-in');
});

it('sends a confirmation link on an account without a second factor; the link deletes (DL-4)', function (): void {
    $user = makeUser();
    actAs($user);
    confirmFor('delete_account', 'password');

    $this->delete('/account', ['confirm' => true])->assertRedirect('/account/delete')->assertSessionHas('flash.code', 'deletion_link_sent');
    expect($user->refresh()->deleted_at)->toBeNull();
    $link = mailedLink('account-deletion-confirm');
    $this->get('/account/delete')->assertInertia(fn (Assert $p) => $p->where('deletion.mode', 'email_link')->has('deletion.pending'));

    // The confirmation was spent on the request.
    $this->delete('/account', ['confirm' => true])->assertSessionHasErrors(['form' => 'confirmation_stale']);

    $this->get($link)->assertInertia(fn (Assert $p) => $p->component('Auth/AccountDeletionLink')->where('link.state', 'confirm'));
    expect($user->refresh()->deleted_at)->toBeNull();
    $this->post($link)->assertRedirect('/')->assertSessionHas('flash.code', 'account_deleted');
    expect($user->refresh()->deleted_at)->not->toBeNull()->and(AccountDeletionRequest::query()->sole()->used_at)->not->toBeNull();
});

it('voids the link when a second factor was added since, and on cancel', function (): void {
    $user = makeUser();
    actAs($user);
    confirmFor('delete_account', 'password');
    $this->delete('/account', ['confirm' => true]);
    $link = mailedLink('account-deletion-confirm');

    $this->delete('/account/deletion')->assertRedirect('/account/delete');
    $this->get($link)->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));

    confirmFor('delete_account', 'password');
    $this->delete('/account', ['confirm' => true]);
    $link = mailedLink('account-deletion-confirm');
    enableTotp($user);
    $this->get($link)->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));
    $this->post($link)->assertSessionHasErrors(['form' => 'link_invalid']);
    expect($user->refresh()->deleted_at)->toBeNull();
});

it('refuses deletion during a recovery lock (RC-3 extended to deletion)', function (): void {
    $user = makeUser();
    AccountRecoveryRequest::query()->create(['user_id' => $user->id, 'token_hash' => random_bytes(32), 'requested_at' => now()->subDays(4), 'confirmed_at' => now()->subDays(4), 'completes_at' => now()->subDay(), 'completed_at' => now()->subDay(), 'lock_until' => now()->addDays(6)]);
    actAs($user);
    confirmFor('delete_account', 'password');
    $this->delete('/account', ['confirm' => true])->assertSessionHasErrors(['form' => 'recovery_lock']);
    $this->get('/account/delete')->assertInertia(fn (Assert $p) => $p->where('deletion.blocked.reason', 'recovery_completed'));
});

it('refuses the last instance admin on self-host', function (): void {
    config(['identity.instance' => 'selfhost']);
    $user = makeUser(['is_instance_admin' => true]);
    enableTotp($user);
    actAs($user, 'totp');
    confirmFor('delete_account', 'totp');
    $this->delete('/account', ['confirm' => true])->assertSessionHasErrors(['form' => 'last_instance_admin']);
});

it('answers 404 to guests and anonymous visitors', function (): void {
    $this->delete('/account', ['confirm' => true])->assertRedirect('/sign-in');
    $this->delete('/account/deletion')->assertRedirect('/sign-in');
});

it('makes every address- or identity-keyed path a uniform no-op during the tombstone (DL-2, TM-T157)', function (): void {
    $user = makeUser(['email' => 'gone@example.com']);
    enableTotp($user);
    $user->forceFill(['deleted_at' => now()])->save();
    $audits = DB::table('audit_log')->count();

    $this->post('/forgot-password', ['email' => 'gone@example.com'])->assertSessionHas('flash.code', 'reset_link_sent');
    $this->post('/account-recovery', ['email' => 'gone@example.com'])->assertSessionHas('flash.code', 'recovery_link_sent');
    $this->post('/sign-in/password', ['email' => 'gone@example.com', 'password' => FIXTURE_PASSWORD])->assertSessionHasErrors(['form' => 'invalid_credentials']);

    Mail::assertNothingQueued();
    expect(DB::table('audit_log')->count())->toBe($audits)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and(AccountRecoveryRequest::query()->count())->toBe(0);
});

it('purges within the job only after the ledger entry is written, masking audit emails (DL-3a, TM-T158)', function (): void {
    $user = makeUser(['email' => 'maya@bakery.example']);
    $subject = $user->publicId();
    app(AuditLog::class)->record('email_changed', $user->id, $subject, after: ['params' => ['note' => 'was maya@bakery.example']]);
    $user->forceFill(['deleted_at' => now()])->save();

    app(AccountDeletion::class)->purgeDue();

    expect(User::query()->find($user->id))->toBeNull()
        ->and(app(DeletionLedger::class)->has($subject))->toBeTrue()
        ->and(DB::table('deletion_ledger')->where('subject', $subject)->value('subject'))->toBe($subject)
        ->and((string) DB::table('audit_log')->where('subject', $subject)->value('after'))->toContain('m***@b***.example')
        ->not->toContain('maya@bakery.example')
        ->and(DB::table('audit_log')->where('subject', $subject)->value('subject_user_id'))->toBeNull();
});

it('keeps the user row when the ledger write fails', function (): void {
    $user = makeUser();
    $user->forceFill(['deleted_at' => now()])->save();
    // Simulate a failed ledger write: its storage is gone.
    DB::statement('ALTER TABLE deletion_ledger RENAME TO deletion_ledger_unavailable');

    expect(app(AccountDeletion::class)->purgeDue())->toBe(0)
        ->and(User::query()->find($user->id))->not->toBeNull();
});

it('frees the address after the purge: a new sign-up is a new user', function (): void {
    $user = makeUser(['email' => 'reuse@example.com']);
    $user->forceFill(['deleted_at' => now()])->save();
    app(AccountDeletion::class)->purgeDue();

    $this->post('/register', ['name' => 'N', 'email' => 'reuse@example.com', 'password' => 'a long enough passphrase']);
    expect(User::query()->sole()->id)->not->toBe($user->id);
});
