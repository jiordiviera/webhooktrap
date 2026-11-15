<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One failed second-factor attempt, across challenges; no code value (WA-3).
 *
 * @property string $id
 * @property string $user_id
 * @property CarbonImmutable $failed_at
 * @property string $kind
 */
final class SecondFactorFailure extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'usr';

    public $timestamps = false;

    protected $table = 'second_factor_failures';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'failed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
