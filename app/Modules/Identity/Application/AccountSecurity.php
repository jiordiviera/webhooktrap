<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionRevoker;
use App\Modules\Identity\Domain\Totp;
use App\Modules\Identity\Domain\TotpFactor;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserIdentity;
use App\Modules\Identity\Domain\UserSession;
use App\Modules\Identity\Domain\WebAuthn\AssertionPolicy;
use App\Modules\Identity\Domain\WebAuthnCredential;
use App\Modules\Identity\Events\IdentityUnlinked;
use App\Modules\Identity\Events\PasswordChanged;
use App\Modules\Identity\Events\TotpDisabled;
use App\Modules\Identity\Events\TotpEnabled;
use App\Modules\Identity\Events\WebAuthnCredentialRemoved;
use App\Modules\Identity\Events\WebAuthnCredentialUnblocked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * `/account/security` actions (contracts §3.4): password, TOTP, recovery
 * codes, WebAuthn key management, identity disconnect and sessions. The
 * routes require recent authentication; these services hold the rules.
 */
final readonly class AccountSecurity
{
    public function __construct(
        private Clock $clock,
        private Audit $audit,
        private Notifier $notifier,
        private SessionRevoker $revoker,
        private TwoFactorState $twoFactor,
        private TotpVerifier $totp,
        private AccountRecovery $recovery,
    ) {}

    /** Sets or changes the password; revokes every other session (domain §2.1). */
    public function changePassword(User $user, string $password, ?string $currentSessionId, RequestContext $context): void
    {
        self::requireVerified($user);
        if ($error = PasswordPolicy::fromConfig()->violation($password, $user->email)) {
            throw ValidationException::withMessages(['password' => $error]);
        }
        $hadPassword = $user->hasPassword();
        $user->forceFill(['password_hash' => Hash::make($password)])->save();
        $this->revoker->revokeAll($user->id, 'password_changed', $currentSessionId);
        $this->audit->record($hadPassword ? 'password_changed' : 'password_set', $user, $context);
        $this->notifier->send($user->email, 'password-changed', ['browser' => $context->uaLabel, 'network' => $context->ipPrefix]);
        PasswordChanged::dispatch($user->id, $currentSessionId);
    }

    /** @return array{secret: string, uri: string} shown once */
    public function startTotp(User $user): array
    {
        self::requireVerified($user);
        $existing = TotpFactor::query()->find($user->id);
        if ($existing !== null && $existing->confirmed_at !== null) {
            throw new IdentityRefusal('totp_already_enabled');
        }
        $secret = Totp::newSecret();
        TotpFactor::query()->updateOrCreate(['user_id' => $user->id], [
            'secret' => encrypt($secret),
            'confirmed_at' => null,
            'last_used_step' => null,
        ]);

        return ['secret' => Totp::base32($secret), 'uri' => Totp::uri($secret, $user->email, (string) config('app.name'))];
    }

    /** @return list<string>|null recovery codes when this was the first second factor */
    public function confirmTotp(User $user, string $code, RequestContext $context): ?array
    {
        $factor = TotpFactor::query()->find($user->id);
        if ($factor === null || $factor->confirmed_at !== null) {
            throw new IdentityRefusal('totp_not_started');
        }
        if (! $this->totp->verify($user, $code, false)) {
            throw ValidationException::withMessages(['code' => 'code_invalid']);
        }
        $factor->refresh()->forceFill(['confirmed_at' => $this->clock->now()])->save();
        $this->audit->record('totp_enabled', $user, $context);
        $this->notifier->send($user->email, 'totp-enabled');
        TotpEnabled::dispatch($user->id);

        return $this->twoFactor->refresh($user, $context);
    }

    public function disableTotp(User $user, RequestContext $context): void
    {
        $factor = TotpFactor::query()->find($user->id);
        if ($factor === null) {
            return;
        }
        $wasConfirmed = $factor->confirmed_at !== null;
        $factor->delete();
        if ($wasConfirmed) {
            $this->audit->record('totp_disabled', $user, $context);
            $this->notifier->send($user->email, 'totp-disabled');
            TotpDisabled::dispatch($user->id);
        }
        $this->twoFactor->refresh($user, $context);
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user, RequestContext $context): array
    {
        if ($user->two_factor_enabled_at === null) {
            throw new IdentityRefusal('two_factor_required');
        }

        return $this->twoFactor->regenerate($user, $context);
    }

    public function renameKey(User $user, string $keyId, string $name): WebAuthnCredential
    {
        $key = $this->ownKey($user, $keyId);
        $key->forceFill(['name' => self::cleanName($name)])->save();

        return $key;
    }

    public function removeKey(User $user, string $keyId, RequestContext $context): void
    {
        $key = $this->ownKey($user, $keyId);
        if ($this->isLastFirstFactor($user, key: $key)) {
            throw new IdentityRefusal('last_sign_in_method');
        }
        $kind = AssertionPolicy::isPasskey($key) ? 'passkey' : 'security_key';
        DB::transaction(function () use ($key, $user, $kind, $context): void {
            $key->delete();
            $this->audit->record('key_removed', $user, $context, ['kind' => $kind, 'keyName' => $key->name]);
            $this->twoFactor->refresh($user, $context);
        });
        $this->notifier->send($user->email, 'key-removed', ['keyName' => $key->name]);
        WebAuthnCredentialRemoved::dispatch($user->id, $key->id, 'user');
    }

    /**
     * Unblocks a flagged credential; keeps the stored sign count so a cloned
     * key with a lower count is refused again (domain §2.2, TM-T155).
     */
    public function unblockKey(User $user, string $keyId, RequestContext $context): WebAuthnCredential
    {
        $key = $this->ownKey($user, $keyId);
        if ($key->flagged_at === null || $key->flag_count >= 2) {
            throw new IdentityRefusal('unblock_not_allowed');
        }
        $key->forceFill(['flagged_at' => null, 'flag_cause' => null])->save();
        $this->audit->record('key_unblocked', $user, $context, ['keyName' => $key->name]);
        $this->notifier->send($user->email, 'key-unblocked', ['keyName' => $key->name]);
        WebAuthnCredentialUnblocked::dispatch($user->id, $key->id);
        $this->twoFactor->refresh($user, $context);

        return $key;
    }

    public function disconnectIdentity(User $user, string $provider, RequestContext $context): void
    {
        $identity = $user->identities()->where('provider', $provider)->first();
        if ($identity === null) {
            return;
        }
        if ($this->recovery->shieldsIdentity($user, $identity)) {
            throw new IdentityRefusal('recovery_lock');
        }
        if ($this->isLastFirstFactor($user, identity: $identity)) {
            throw new IdentityRefusal('last_sign_in_method');
        }
        $identity->delete();
        $this->audit->record('identity_unlinked', $user, $context, ['provider' => $provider, 'label' => $identity->provider_label]);
        $this->notifier->send($user->email, 'method-unlinked', ['provider' => $provider]);
        IdentityUnlinked::dispatch($user->id, $provider);
    }

    /**
     * Idempotent revoke (contracts §3.4): unknown, foreign or ended sessions
     * answer like success with no audit row and no event. Returns
     * `revoked`, `already_ended` or throws `current_session`.
     */
    public function revokeSession(User $user, string $publicId, ?string $currentSessionId, RequestContext $context): string
    {
        $uuid = UserSession::uuidFromPublicId($publicId);
        if ($uuid !== null && $uuid === $currentSessionId) {
            throw new IdentityRefusal('current_session');
        }
        $session = $uuid === null ? null : UserSession::query()->where('user_id', $user->id)->find($uuid);
        if ($session === null || ! $this->revoker->isLive($session)) {
            return 'already_ended';
        }
        $this->revoker->revoke($session, 'user');
        $this->audit->record('session_revoked', $user, $context, ['browser' => $session->ua_label, 'network' => $session->ip_prefix]);

        return 'revoked';
    }

    public function revokeOtherSessions(User $user, ?string $currentSessionId, RequestContext $context): int
    {
        $others = UserSession::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->when($currentSessionId !== null, fn ($q) => $q->where('id', '<>', $currentSessionId))->get()
            ->filter(fn (UserSession $s): bool => $this->revoker->isLive($s));
        foreach ($others as $session) {
            $this->revoker->revoke($session, 'user');
            $this->audit->record('session_revoked', $user, $context, ['browser' => $session->ua_label, 'network' => $session->ip_prefix]);
        }

        return $others->count();
    }

    /**
     * Last-method rule (domain §2.1): a user always keeps a password, a
     * linked identity or an unflagged passkey.
     */
    public function isLastFirstFactor(User $user, ?WebAuthnCredential $key = null, ?UserIdentity $identity = null): bool
    {
        $passkeys = $user->webauthnCredentials()->whereNull('flagged_at')->get()
            ->filter(fn (WebAuthnCredential $c): bool => AssertionPolicy::isPasskey($c))
            ->reject(fn (WebAuthnCredential $c): bool => $key !== null && $c->id === $key->id);
        $identities = $user->identities()->when($identity !== null, fn ($q) => $q->where('id', '<>', $identity?->id))->count();

        return ! $user->hasPassword() && $passkeys->isEmpty() && $identities === 0;
    }

    /** F1: an unverified account (registrant not yet proven) sets no credential. */
    public static function requireVerified(User $user): void
    {
        if (! $user->isVerified()) {
            throw new IdentityRefusal('email_unverified');
        }
    }

    public static function cleanName(string $name): string
    {
        // Strip control, bidi and tag characters (R-H); 1-48 printable characters.
        $clean = (string) preg_replace('/[\p{Cc}\p{Cf}\x{E0000}-\x{E007F}]/u', '', $name);
        $clean = trim($clean);
        if ($clean === '' || mb_strlen($clean) > 48) {
            throw ValidationException::withMessages(['name' => 'name_invalid']);
        }

        return $clean;
    }

    private function ownKey(User $user, string $keyId): WebAuthnCredential
    {
        $uuid = WebAuthnCredential::uuidFromPublicId($keyId);
        $key = $uuid === null ? null : $user->webauthnCredentials()->find($uuid);
        if (! $key instanceof WebAuthnCredential) {
            abort(404);
        }

        return $key;
    }
}
