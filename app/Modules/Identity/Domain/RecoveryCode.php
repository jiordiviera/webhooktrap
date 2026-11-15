<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recovery code of the current set, hashed (domain §2.3).
 *
 * @property string $id
 * @property string $user_id
 * @property string $code_hash
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable $created_at
 */
final class RecoveryCode extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'usr';

    public $timestamps = false;

    protected $table = 'recovery_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'code_hash' => Bytea::class,
            'used_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
