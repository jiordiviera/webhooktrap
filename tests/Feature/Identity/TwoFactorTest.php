<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\RecoveryCode;
use App\Modules\Identity\Domain\UserSession;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Mail::fake());

it('follows a password sign-in with the 2FA challenge; nobody is signed in until it passes', function (): void {
    $user = makeUser();
    $secret = enableTotp($user);
    giveRecoveryCodes($user);

    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])->assertRedirect('/two-factor');
    $this->assertGuest('web');
    $this->get('/two-factor')->assertInertia(fn (Assert $p) => $p->component('TwoFactor/Challenge')->where('methods', ['totp', 'recovery_code']));

    $this->post('/two-factor/totp', ['code' => totpCode($secret)])->assertRedirect('/projects');
    $this->assertAuthenticatedAs($user, 'web');
    expect(UserSession::query()->sole()->second_factor)->toBe('totp');
});

it('accepts a TOTP code at most once', function (): void {
    $user = makeUser();
    $secret = enableTotp($user);
    $code = totpCode($secret);
    signIn($user);
    $this->post('/two-factor/totp', ['code' => $code]);
    newBrowser();

    signIn($user);
    $this->post('/two-factor/totp', ['code' => $code])->assertSessionHasErrors(['code' => 'code_invalid']);
    $this->assertGuest('web');
});

it('sends the person back to sign-in after 5 failed attempts in one challenge', function (): void {
    $user = makeUser();
    enableTotp($user);
    signIn($user);
    for ($i = 0; $i < 4; $i++) {
        $this->post('/two-factor/totp', ['code' => '000000'])->assertSessionHasErrors(['code' => 'code_invalid']);
    }
    $this->post('/two-factor/totp', ['code' => '000000'])->assertRedirect('/sign-in');
    $this->get('/two-factor')->assertRedirect('/sign-in');
});

it('holds second-factor sign-in after 20 failures across fresh sign-ins, emails once and audits (TM-T122)', function (): void {
    $user = makeUser();
    $secret = enableTotp($user);
    for ($round = 0; $round < 5; $round++) {
        newBrowser();
        signIn($user);
        for ($i = 0; $i < 4; $i++) {
            $this->post('/two-factor/totp', ['code' => '000000']);
        }
    }

    expect($user->refresh()->second_factor_hold_until)->not->toBeNull()
        ->and(queuedMails('second-factor-hold')->count())->toBe(1)
        ->and(auditCount('second_factor_failed', $user))->toBe(20)
        ->and(auditCount('second_factor_hold', $user))->toBe(1);

    newBrowser();
    signIn($user);
    $this->post('/two-factor/totp', ['code' => totpCode($secret)])->assertSessionHasErrors(['form' => 'throttled']);
    $this->assertGuest('web');

    $this->travel(61)->minutes();
    newBrowser();
    signIn($user);
    $this->post('/two-factor/totp', ['code' => totpCode($secret)])->assertRedirect('/projects');
});

it('accepts a recovery code once, emails the user and shows the count left', function (): void {
    $user = makeUser();
    enableTotp($user);
    $codes = giveRecoveryCodes($user);
    signIn($user);
    $this->post('/two-factor/recovery', ['code' => strtolower($codes[0])])->assertRedirect('/projects');
    expect(UserSession::query()->sole()->second_factor)->toBe('recovery_code')
        ->and(RecoveryCode::query()->whereNull('used_at')->count())->toBe(9)
        ->and(queuedMails('recovery-code-used')->first()?->params['codesLeft'])->toBe(9);

    newBrowser();
    signIn($user);
    $this->post('/two-factor/recovery', ['code' => $codes[0]])->assertSessionHasErrors(['code' => 'code_invalid']);
});

it('needs no challenge for an account without a second factor', function (): void {
    signIn(makeUser());
    $this->assertAuthenticated('web');
});
