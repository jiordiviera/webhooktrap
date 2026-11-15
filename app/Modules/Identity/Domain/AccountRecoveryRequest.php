<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Delayed email recovery on hosted (domain §2.3, RC-1..RC-6).
 *
 * @property string $id
 * @property string $user_id
 * @property string $token_hash
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $completes_at
 * @property CarbonImmutable|null $wait_extended_at
 * @property CarbonImmutable|null $reminder_sent_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancelled_by
 * @property CarbonImmutable|null $completed_at
 * @property string|null $finish_token_hash
 * @property CarbonImmutable|null $finish_token_expires_at
 * @property CarbonImmutable|null $lock_until
 * @property-read User $user
 */
final class AccountRecoveryRequest extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'usr';

    public $timestamps = true;

    protected $table = 'account_recovery_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_hash' => Bytea::class,
            'finish_token_hash' => Bytea::class,
            'requested_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'completes_at' => 'immutable_datetime',
            'wait_extended_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'finish_token_expires_at' => 'immutable_datetime',
            'lock_until' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
