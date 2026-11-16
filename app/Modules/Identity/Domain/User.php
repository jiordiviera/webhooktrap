<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\EmailMask;
use App\Infrastructure\Persistence\HasUuidV7;
use App\Modules\Identity\Database\Factories\UserFactory;
use Carbon\CarbonImmutable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The account principal of the `web` guard (domain-model §2).
 *
 * @property string $id
 * @property string $email
 * @property string|null $name
 * @property bool $is_instance_admin
 * @property bool $is_operator
 * @property bool $can_private_replay
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $disabled_at
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $invited_at
 * @property string|null $password_hash
 * @property string $webauthn_user_handle
 * @property CarbonImmutable|null $two_factor_enabled_at
 * @property string|null $legacy_source
 * @property CarbonImmutable|null $legacy_imported_at
 * @property CarbonImmutable|null $legacy_activated_at
 * @property CarbonImmutable|null $second_factor_hold_until
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read TotpFactor|null $totp
 */
final class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuidV7;

    public const string ID_PREFIX = 'usr';

    protected $table = 'users';

    /**
     * `is_operator` is set only by `operator:grant` / `operator:revoke`
     * (forceFill), never by mass assignment (decision 74).
     */
    protected $guarded = ['is_operator'];

    protected $hidden = ['password_hash', 'webauthn_user_handle'];

    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'is_instance_admin' => 'boolean',
            'is_operator' => 'boolean',
            'can_private_replay' => 'boolean',
            'email_verified_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'invited_at' => 'immutable_datetime',
            'two_factor_enabled_at' => 'immutable_datetime',
            'legacy_imported_at' => 'immutable_datetime',
            'legacy_activated_at' => 'immutable_datetime',
            'second_factor_hold_until' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'webauthn_user_handle' => Bytea::class,
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    public function isLive(): bool
    {
        return $this->deleted_at === null && $this->disabled_at === null;
    }

    public function hasPassword(): bool
    {
        return $this->password_hash !== null;
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** @return HasMany<UserIdentity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /** @return HasMany<WebAuthnCredential, $this> */
    public function webauthnCredentials(): HasMany
    {
        return $this->hasMany(WebAuthnCredential::class);
    }

    /** @return HasOne<TotpFactor, $this> */
    public function totp(): HasOne
    {
        return $this->hasOne(TotpFactor::class);
    }

    /** @return HasMany<RecoveryCode, $this> */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class);
    }

    /** @return HasMany<UserSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function hasConfirmedTotp(): bool
    {
        return $this->totp()->whereNotNull('confirmed_at')->exists();
    }

    /**
     * Holds at least one second factor: a confirmed TOTP factor or an
     * unflagged WebAuthn credential (domain §2.1 "2FA state", §2.2).
     */
    public function hasSecondFactor(): bool
    {
        return $this->hasConfirmedTotp()
            || $this->webauthnCredentials()->whereNull('flagged_at')->exists();
    }

    /** OAuth-only: no password, no WebAuthn credential, no TOTP (domain §2.1). */
    public function isOAuthOnly(): bool
    {
        return ! $this->hasPassword()
            && ! $this->webauthnCredentials()->exists()
            && ! $this->totp()->exists()
            && $this->identities()->exists();
    }

    /**
     * The operator panel (decision 74): the operator flag, a verified email,
     * a live account and a second factor (TOTP or an unflagged passkey or
     * security key). Checked on every panel request, Livewire updates too.
     */
    public function isEligibleOperator(): bool
    {
        return $this->is_operator === true
            && $this->isLive()
            && $this->isVerified()
            && $this->hasSecondFactor();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->isEligibleOperator();
    }

    /** Shown in the panel's user menu: never the full address. */
    public function getFilamentName(): string
    {
        return $this->name !== null && $this->name !== '' ? $this->name : EmailMask::mask($this->email);
    }
}
