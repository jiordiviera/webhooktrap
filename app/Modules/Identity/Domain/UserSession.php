<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One signed-in browser of a user, independent of the session driver (domain §2.7).
 *
 * @property string $id
 * @property string $user_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $last_seen_at
 * @property CarbonImmutable $idle_expires_at
 * @property CarbonImmutable $absolute_expires_at
 * @property string $first_factor
 * @property string|null $second_factor
 * @property string $ua_label
 * @property string $ip_prefix
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_reason
 * @property-read User $user
 */
final class UserSession extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'ses';

    public $timestamps = false;

    protected $table = 'user_sessions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'idle_expires_at' => 'immutable_datetime',
            'absolute_expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
