<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A passkey or security key (domain §2.2). Passkey = discoverable and UV at registration.
 *
 * @property string $id
 * @property string $user_id
 * @property string $credential_id
 * @property string $public_key
 * @property int $alg
 * @property int $sign_count
 * @property string|null $aaguid
 * @property bool $discoverable
 * @property bool $uv_at_registration
 * @property bool $backup_eligible
 * @property bool $backup_state
 * @property string $name
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $flagged_at
 * @property string|null $flag_cause
 * @property int $flag_count
 * @property CarbonImmutable $created_at
 * @property-read User $user
 */
final class WebAuthnCredential extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'key';

    public $timestamps = true;

    protected $table = 'webauthn_credentials';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credential_id' => Bytea::class,
            'public_key' => Bytea::class,
            'alg' => 'integer',
            'sign_count' => 'integer',
            'discoverable' => 'boolean',
            'uv_at_registration' => 'boolean',
            'backup_eligible' => 'boolean',
            'backup_state' => 'boolean',
            'flag_count' => 'integer',
            'last_used_at' => 'immutable_datetime',
            'flagged_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
