<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\WebAuthn\AssertionCheck;
use App\Modules\Identity\Domain\WebAuthn\AssertionPolicy;

it('applies the WebAuthn counter rule (WA-1, TM-T120)', function (int $stored, int $received, bool $anomalous): void {
    expect(AssertionPolicy::counterIsAnomalous($stored, $received))->toBe($anomalous);
})->with([
    'stored 57, received 0' => [57, 0, true],
    'both 0 (synced passkey)' => [0, 0, false],
    'equal non-zero' => [5, 5, true],
    'lower' => [5, 4, true],
    'higher' => [5, 6, false],
    'first count' => [0, 1, false],
]);

it('refuses a discoverable assertion with another user\'s handle (WA-2, TM-T121), flagged keys, BE changes and missing UV', function (): void {
    $user = makeUser();
    $key = addKey($user, ['sign_count' => 3]);
    $handle = $user->webauthn_user_handle;

    expect(AssertionPolicy::check($key, $handle, 4, true, true, true, $handle))->toBe(AssertionCheck::Accepted)
        ->and(AssertionPolicy::check($key, $handle, 4, true, true, true, makeUser()->webauthn_user_handle))->toBe(AssertionCheck::UserHandleMismatch)
        ->and(AssertionPolicy::check($key, $handle, 4, true, true, true, null))->toBe(AssertionCheck::UserHandleMismatch)
        ->and(AssertionPolicy::check($key, $handle, 3, true, true, true, $handle))->toBe(AssertionCheck::CounterAnomaly)
        ->and(AssertionPolicy::check($key, $handle, 4, false, true, true, $handle))->toBe(AssertionCheck::BackupEligibilityChanged)
        ->and(AssertionPolicy::check($key, $handle, 4, true, false, true, $handle))->toBe(AssertionCheck::UserVerificationMissing)
        ->and(AssertionPolicy::check($key, $handle, 4, true, false, false, null))->toBe(AssertionCheck::Accepted);

    $key->forceFill(['flagged_at' => now(), 'flag_cause' => 'counter', 'flag_count' => 1])->save();
    expect(AssertionPolicy::check($key, $handle, 9, true, true, true, $handle))->toBe(AssertionCheck::Flagged);
});

it('sends frame-ancestors none and X-Frame-Options DENY on every app page (WA-4, TM-T123)', function (string $path): void {
    $this->get($path)->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
})->with(['/', '/sign-in', '/register', '/expired']);

it('allows WebAuthn only on ceremony pages', function (): void {
    $this->get('/sign-in')->assertHeader('Permissions-Policy', 'publickey-credentials-get=(self), publickey-credentials-create=(self)');
    $this->get('/register')->assertHeader('Permissions-Policy', 'publickey-credentials-get=(), publickey-credentials-create=()');
});

it('sends no referrer and no caching on emailed-link pages', function (): void {
    $this->get('/email-change/some-token')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('Cache-Control', 'no-store, private');
    $this->get('/account-deletion/some-token')->assertHeader('Referrer-Policy', 'no-referrer');
});

it('names every app cookie with the __Host- prefix and Secure (TM-10)', function (): void {
    expect(config('session.cookie'))->toStartWith('__Host-')
        ->and(config('session.secure'))->toBeTrue()
        ->and(config('session.domain'))->toBeNull()
        ->and(config('identity.guest.cookie'))->toBe('__Host-wt_guest');
});
