<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A capture URL (domain §4, schema §4): `capture_key` is 12 chars base62
 * from a CSPRNG (~71 bits). One active per project (P9, partial unique
 * index). Rotating marks it `rotated`; a rotated key keeps answering the
 * ingest invariant and stores nothing. Rows go with the project.
 *
 * @property string $id
 * @property string $project_id
 * @property string $capture_key
 * @property string $state
 * @property CarbonImmutable|null $rotated_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class CaptureUrl extends Model
{
    use HasUuidV7;

    // Capture URLs have no external id; required by HasUuidV7.
    public const string ID_PREFIX = 'cap';

    public const string KEY_PATTERN = '/^[A-Za-z0-9]{12}$/';

    private const string KEY_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    protected $table = 'capture_urls';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rotated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }

    /** 12 chars base62 from `random_int` (CSPRNG), not derived from anything. */
    public static function newKey(): string
    {
        $key = '';
        for ($i = 0; $i < 12; $i++) {
            $key .= self::KEY_ALPHABET[random_int(0, 61)];
        }

        return $key;
    }

    /** A fresh key no row holds now (rotated keys included: they are never reused). */
    public static function unusedKey(): string
    {
        do {
            $key = self::newKey();
        } while (self::query()->where('capture_key', $key)->exists());

        return $key;
    }
}
