<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Emailed deletion confirmation for accounts without a second factor (DL-4).
 *
 * @property string $id
 * @property string $user_id
 * @property string $token_hash
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancelled_reason
 * @property-read User $user
 */
final class AccountDeletionRequest extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'usr';

    public $timestamps = true;

    protected $table = 'account_deletion_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_hash' => Bytea::class,
            'requested_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
