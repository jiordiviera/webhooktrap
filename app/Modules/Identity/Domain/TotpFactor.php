<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\Bytea;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The user's TOTP factor; the secret is stored with Laravel's encrypter
 * (schema §2 `totp_factors`).
 *
 * @property string $user_id
 * @property string $secret
 * @property CarbonImmutable|null $confirmed_at
 * @property int|null $last_used_step
 */
final class TotpFactor extends Model
{
    protected $table = 'totp_factors';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'secret' => Bytea::class,
            'confirmed_at' => 'immutable_datetime',
            'last_used_step' => 'integer',
        ];
    }

    public function plainSecret(): string
    {
        return decrypt($this->secret);
    }
}
