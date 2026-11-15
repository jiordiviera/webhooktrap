<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

function resetToken(): string
{
    return str(mailedLink('password-reset'))->afterLast('/')->toString();
}

it('answers uniformly and emails a link with no email parameter', function (): void {
    $user = makeUser();
    $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('flash.code', 'reset_link_sent');
    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('flash.code', 'reset_link_sent');

    expect(queuedMails('password-reset')->count())->toBe(1)
        ->and(mailedLink('password-reset'))->toMatch('#^/reset-password/[A-Za-z0-9_-]{43}$#');
});

it('shows the form without consuming the token, then resets and revokes every session (TM-T153)', function (): void {
    $user = makeUser();
    signIn($user);
    newBrowser();
    $this->post('/forgot-password', ['email' => $user->email]);
    $token = resetToken();

    $this->get('/reset-password/'.$token)->assertInertia(fn (Assert $p) => $p->where('link.state', 'valid'))
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/reset-password/'.$token)->assertInertia(fn (Assert $p) => $p->where('link.state', 'valid'));

    $this->post('/reset-password', ['token' => $token, 'password' => 'a brand new passphrase', 'password_confirmation' => 'a brand new passphrase'])
        ->assertRedirect('/');

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull()
        ->and(UserSession::query()->where('revoked_reason', 'password_reset')->count())->toBe(1)
        ->and(UserSession::query()->whereNull('revoked_at')->sole()->first_factor)->toBe('reset_link')
        ->and(session('identity.recent_auth'))->toBeNull()
        ->and(auditCount('password_reset', $user))->toBe(1)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
    $this->get('/reset-password/'.$token)->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));
});

it('leads to the 2FA challenge on a 2FA account: no session, no recent auth (AR-2, TM-T151)', function (): void {
    $user = makeUser();
    $secret = enableTotp($user);
    $this->post('/forgot-password', ['email' => $user->email]);

    $this->post('/reset-password', ['token' => resetToken(), 'password' => 'a brand new passphrase', 'password_confirmation' => 'a brand new passphrase'])
        ->assertRedirect('/two-factor');
    $this->assertGuest('web');
    expect(session('identity.recent_auth'))->toBeNull()->and(UserSession::query()->count())->toBe(0);

    $this->post('/two-factor/totp', ['code' => totpCode($secret)])->assertRedirect('/projects');
    expect(UserSession::query()->sole()->first_factor)->toBe('reset_link')
        ->and(session('identity.recent_auth'))->toBeNull();
    $this->post('/account/security/recovery-codes')->assertRedirect('/confirm-auth?next=%2Faccount%2Fsecurity&action=confirm&reason=recovery_codes');
});

it('expires the link after 60 minutes and invalidates it with a newer request', function (): void {
    $user = makeUser();
    $this->post('/forgot-password', ['email' => $user->email]);
    $first = resetToken();
    $this->post('/forgot-password', ['email' => $user->email]);
    $second = resetToken();
    $this->get('/reset-password/'.$first)->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));

    $this->travel(61)->minutes();
    $this->get('/reset-password/'.$second)->assertInertia(fn (Assert $p) => $p->where('link.state', 'invalid'));
});

it('silently drops requests past 3 per hour per email; shows the per-address limit', function (): void {
    $user = makeUser();
    for ($i = 0; $i < 5; $i++) {
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('flash.code', 'reset_link_sent');
    }
    expect(queuedMails('password-reset')->count())->toBe(3);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/forgot-password', ['email' => "x{$i}@example.com"]);
    }
    $this->post('/forgot-password', ['email' => 'y@example.com'])->assertSessionHasErrors(['form' => 'throttled']);
});

it('applies the password policy to the reset', function (): void {
    $user = makeUser();
    $this->post('/forgot-password', ['email' => $user->email]);
    $this->post('/reset-password', ['token' => resetToken(), 'password' => $user->email, 'password_confirmation' => $user->email])
        ->assertSessionHasErrors(['password' => 'password_is_email']);
});

it('activates an imported 1.x account through the reset link', function (): void {
    $user = makeUser(['password_hash' => null, 'email_verified_at' => null, 'legacy_source' => '1x', 'legacy_imported_at' => now()]);
    $this->post('/forgot-password', ['email' => $user->email]);
    $this->post('/reset-password', ['token' => resetToken(), 'password' => 'a brand new passphrase', 'password_confirmation' => 'a brand new passphrase']);

    $user = User::query()->find($user->id);
    expect($user->legacy_activated_at)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password_hash)->toStartWith('$argon2id$');
});
