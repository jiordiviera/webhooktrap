<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AccountDeletionController;
use App\Modules\Identity\Http\Controllers\AccountRecoveryController;
use App\Modules\Identity\Http\Controllers\AccountSecurityController;
use App\Modules\Identity\Http\Controllers\BroadcastAuthController;
use App\Modules\Identity\Http\Controllers\ConfirmAuthController;
use App\Modules\Identity\Http\Controllers\EmailChangeController;
use App\Modules\Identity\Http\Controllers\GuestSessionController;
use App\Modules\Identity\Http\Controllers\OAuthController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\RegisterController;
use App\Modules\Identity\Http\Controllers\SignInController;
use App\Modules\Identity\Http\Controllers\TwoFactorChallengeController;
use App\Modules\Identity\Http\Controllers\VerifyEmailController;
use App\Modules\Identity\Http\Controllers\WebAuthnController;
use App\Modules\Workspace\Http\Controllers\GuestWorkspaceController;
use Illuminate\Support\Facades\Route;

/*
 * Identity routes (contracts §3.4) and the guest-session entry points
 * (contracts §3.2). Token path segments are emailed links: their pages
 * consume nothing on GET (domain §2.9).
 */

$provider = '(github|google)';

// Guest sessions and workspace ownership.
Route::post('/new', [GuestWorkspaceController::class, 'store'])->name('guest.new');
Route::post('/session/end', [GuestSessionController::class, 'end'])->name('guest.end');
Route::get('/expired', [GuestSessionController::class, 'expired'])->name('expired');

// Browser channel auth (event-catalogue §1, decision 50): `web` and `guest` principals only;
// Broadcast::routes() is not used. CSRF applies (web group).
Route::post('/broadcasting/auth', BroadcastAuthController::class)->name('broadcasting.auth');

// Sign-in, registration, password reset (anonymous or guest).
Route::get('/sign-in', [SignInController::class, 'show'])->name('login');
Route::post('/sign-in/password', [SignInController::class, 'password'])->name('sign-in.password');
Route::get('/sign-in/{provider}', [OAuthController::class, 'redirect'])->where('provider', $provider)->name('sign-in.oauth');
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->where('provider', $provider)->name('oauth.callback');
Route::post('/sign-out', [SignInController::class, 'signOut'])->name('logout');

Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::get('/verify-email', [VerifyEmailController::class, 'notice'])->name('verification.notice');
Route::get('/verify-email/{id}/{hash}', [VerifyEmailController::class, 'show'])->middleware('signed')->name('verification.show');
Route::post('/verify-email/{id}/{hash}', [VerifyEmailController::class, 'confirm'])->middleware('signed')->name('verification.confirm');
Route::post('/email/verification-notification', [VerifyEmailController::class, 'resend'])->name('verification.send');

Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'send'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'show'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

Route::get('/two-factor', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
Route::post('/two-factor/totp', [TwoFactorChallengeController::class, 'totp'])->name('two-factor.totp');
Route::post('/two-factor/recovery', [TwoFactorChallengeController::class, 'recovery'])->name('two-factor.recovery');

// Emailed links (any session on GET; POST checks the signed-in account).
Route::get('/email-change/{token}', [EmailChangeController::class, 'showNew'])->name('email-change.show');
Route::post('/email-change/{token}', [EmailChangeController::class, 'confirmNew'])->name('email-change.confirm');
Route::get('/email-change-old/{token}', [EmailChangeController::class, 'showOld'])->name('email-change-old.show');
Route::post('/email-change-old/{token}', [EmailChangeController::class, 'confirmOld'])->name('email-change-old.confirm');
Route::get('/account-deletion/{token}', [AccountDeletionController::class, 'showLink'])->name('account-deletion.show');
Route::post('/account-deletion/{token}', [AccountDeletionController::class, 'confirmLink'])->name('account-deletion.confirm');

// Delayed recovery (hosted only; the controller answers 404 on self-host).
Route::post('/account-recovery', [AccountRecoveryController::class, 'request'])->name('account-recovery.request');
Route::get('/account-recovery/finish/{token}', [AccountRecoveryController::class, 'finishShow'])->name('account-recovery.finish.show');
Route::post('/account-recovery/finish/{token}', [AccountRecoveryController::class, 'finish'])->name('account-recovery.finish');
Route::get('/account-recovery/{token}', [AccountRecoveryController::class, 'show'])->name('account-recovery.show');
Route::post('/account-recovery/{token}', [AccountRecoveryController::class, 'confirm'])->name('account-recovery.confirm');
Route::delete('/account-recovery/{token}', [AccountRecoveryController::class, 'decline'])->name('account-recovery.decline');

Route::middleware('auth:web')->group(function () use ($provider): void {
    Route::delete('/account-recovery', [AccountRecoveryController::class, 'cancel'])->name('account-recovery.cancel');

    Route::get('/confirm-auth', [ConfirmAuthController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-auth', [ConfirmAuthController::class, 'store'])->name('confirm-auth.store');
    Route::post('/confirm-auth/{provider}', [ConfirmAuthController::class, 'oauth'])->where('provider', $provider)->name('confirm-auth.oauth');

    Route::get('/account/security', [AccountSecurityController::class, 'show'])->name('account.security');

    // F1: an unverified account changes no credential, factor or identity.
    Route::middleware('verified-email')->group(function () use ($provider): void {
        Route::get('/account/security/review', [AccountSecurityController::class, 'review'])->name('account.security.review');
        Route::patch('/account/security/keys/{keyId}', [AccountSecurityController::class, 'renameKey'])->name('account.keys.rename');
        Route::get('/account/delete', [AccountDeletionController::class, 'show'])->name('account.delete');
        Route::delete('/account', [AccountDeletionController::class, 'destroy'])->name('account.destroy');
        Route::delete('/account/deletion', [AccountDeletionController::class, 'cancel'])->name('account.deletion.cancel');
        Route::delete('/account/email', [EmailChangeController::class, 'cancel'])->name('account.email.cancel');

        Route::middleware('recent-auth')->group(function () use ($provider): void {
            Route::put('/account/password', [AccountSecurityController::class, 'password'])->name('account.password');
            Route::post('/account/security/totp', [AccountSecurityController::class, 'startTotp'])->name('account.totp.start');
            Route::post('/account/security/totp/confirm', [AccountSecurityController::class, 'confirmTotp'])->name('account.totp.confirm');
            Route::delete('/account/security/totp', [AccountSecurityController::class, 'disableTotp'])->name('account.totp.disable');
            Route::post('/account/security/recovery-codes', [AccountSecurityController::class, 'recoveryCodes'])->name('account.recovery-codes');
            Route::delete('/account/security/keys/{keyId}', [AccountSecurityController::class, 'removeKey'])->name('account.keys.remove');
            Route::post('/account/security/keys/{keyId}/unblock', [AccountSecurityController::class, 'unblockKey'])->name('account.keys.unblock');
            Route::post('/account/security/identities/{provider}/connect', [OAuthController::class, 'connect'])->where('provider', $provider)->name('account.identities.connect');
            Route::delete('/account/security/identities/{provider}', [AccountSecurityController::class, 'disconnect'])->where('provider', $provider)->name('account.identities.disconnect');
            Route::delete('/account/sessions/{sessionId}', [AccountSecurityController::class, 'revokeSession'])->name('account.sessions.revoke');
            Route::delete('/account/sessions', [AccountSecurityController::class, 'revokeOthers'])->name('account.sessions.revoke-others');
        });
        Route::post('/account/email', [EmailChangeController::class, 'request'])->middleware('recent-auth:change_email')->name('account.email');

        Route::middleware(['recent-auth', 'same-origin-fetch'])->group(function (): void {
            Route::post('/webauthn/register/options', [WebAuthnController::class, 'registerOptions'])->name('webauthn.register.options');
            Route::post('/webauthn/register', [WebAuthnController::class, 'register'])->name('webauthn.register');
        });
    });
});

// WebAuthn assertion (contracts §3.4): passkey sign-in or second factor; JSON only.
Route::middleware('same-origin-fetch')->group(function (): void {
    Route::post('/webauthn/assert/options', [WebAuthnController::class, 'assertOptions'])->name('webauthn.assert.options');
    Route::post('/webauthn/assert', [WebAuthnController::class, 'assert'])->name('webauthn.assert');
});
