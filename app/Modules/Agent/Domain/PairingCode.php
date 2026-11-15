<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A pairing code (domain §5, schema §5, S-1): stored as an HMAC only; one
 * live code per principal; single use; 10 minutes.
 *
 * @property string $id
 * @property string $code_hash
 * @property string $workspace_id
 * @property string|null $user_id
 * @property string|null $guest_session_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property string|null $used_by_agent_id
 * @property int $failed_attempts
 * @property string|null $replaces_agent_id
 * @property CarbonImmutable $created_at
 */
final class PairingCode extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'key';

    public $timestamps = false;

    protected $table = 'pairing_codes';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'code_hash' => Bytea::class,
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'failed_attempts' => 'integer',
        ];
    }
}
