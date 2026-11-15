<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A linked GitHub or Google identity (schema §2, domain §2.5).
 *
 * @property string $id
 * @property string $user_id
 * @property string $provider
 * @property string $provider_subject
 * @property bool $provider_email_verified
 * @property string $provider_label
 * @property CarbonImmutable $linked_at
 * @property string $linked_via
 * @property-read User $user
 */
final class UserIdentity extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'usr';

    public $timestamps = true;

    protected $table = 'user_identities';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_email_verified' => 'boolean',
            'linked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
