<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending email change; both halves when old_required (domain §2.6, EC-1).
 *
 * @property string $id
 * @property string $user_id
 * @property string $new_email
 * @property string $token_hash
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable $expires_at
 * @property bool $old_required
 * @property string|null $old_token_hash
 * @property CarbonImmutable|null $new_confirmed_at
 * @property CarbonImmutable|null $old_confirmed_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancelled_reason
 * @property-read User $user
 */
final class EmailChangeRequest extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'usr';

    public $timestamps = true;

    protected $table = 'email_change_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_hash' => Bytea::class,
            'old_token_hash' => Bytea::class,
            'old_required' => 'boolean',
            'requested_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'new_confirmed_at' => 'immutable_datetime',
            'old_confirmed_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
