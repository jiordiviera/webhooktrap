<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The principal of the `guest` guard (domain §2, ADR-003 amendment 1).
 * No password, no second factor, no WebAuthn credential.
 *
 * @property string $id
 * @property string $token_hash
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $ended_at
 * @property string|null $claimed_by_user_id
 * @property CarbonImmutable|null $claimed_at
 * @property int $stored_captures
 * @property int $counted_runs
 * @property int $accepted_runs
 * @property int $stored_bytes
 * @property string|null $claim_nonce_hash
 * @property CarbonImmutable|null $claim_nonce_expires_at
 */
final class GuestSession extends Model implements Authenticatable
{
    use HasUuidV7;

    public const string ID_PREFIX = 'gst';

    public $timestamps = false;

    protected $table = 'guest_sessions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_hash' => Bytea::class,
            'claim_nonce_hash' => Bytea::class,
            'created_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'claim_nonce_expires_at' => 'immutable_datetime',
            'stored_captures' => 'integer',
            'counted_runs' => 'integer',
            'accepted_runs' => 'integer',
            'stored_bytes' => 'integer',
        ];
    }

    /** Usable only while now < expires_at, not ended and not claimed (domain §2). */
    public function isLiveAt(CarbonImmutable $now): bool
    {
        return $now->lessThan($this->expires_at) && $this->ended_at === null && $this->claimed_at === null;
    }

    public function isEmpty(): bool
    {
        return $this->stored_captures === 0 && $this->counted_runs === 0 && $this->accepted_runs === 0;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
