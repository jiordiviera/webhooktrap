<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Infrastructure\Ids\EntityId;
use App\Infrastructure\Ids\IdGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * UUIDv7 primary key generated in PHP (schema.md §1.1). A model using this
 * trait declares `public const string ID_PREFIX`, used for the external form.
 *
 * @phpstan-require-extends Model
 */
trait HasUuidV7
{
    public static function bootHasUuidV7(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), app(IdGenerator::class)->uuid());
            }
        });
    }

    public function initializeHasUuidV7(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    /** The external id, e.g. `usr_01M75F1M56RSH0CVKCTFX4FQWC`. */
    public function publicId(): string
    {
        return EntityId::encode(static::ID_PREFIX, (string) $this->getKey());
    }

    /** The stored uuid for an external id of this model's kind, or null. */
    public static function uuidFromPublicId(string $publicId): ?string
    {
        try {
            [$prefix, $uuid] = EntityId::decode($publicId);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $prefix === static::ID_PREFIX ? $uuid : null;
    }
}
