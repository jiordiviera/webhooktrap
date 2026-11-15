<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project (domain §3, schema §3). `project_key` is never a capability:
 * every route checks the owner (TM-13). Soft-deleted rows (`deleted_at`) are
 * invisible to every product surface and hard-deleted after 24 h. Counters
 * are allocated under a row lock only (ProjectCounters); `is_guest` is set by
 * a database trigger from the owning workspace.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $project_key
 * @property string $name
 * @property int $capture_retention_days
 * @property int $run_retention_days
 * @property string $default_visibility
 * @property int $captures_today
 * @property CarbonImmutable|null $captures_day
 * @property int $stored_body_bytes
 * @property int $next_capture_seq
 * @property int $next_run_number
 * @property string|null $last_agent_id
 * @property bool $is_guest
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Workspace $workspace
 */
final class Project extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'prj';

    private const string KEY_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    protected $table = 'projects';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'capture_retention_days' => 'integer',
            'run_retention_days' => 'integer',
            'captures_today' => 'integer',
            'captures_day' => 'immutable_date',
            'stored_body_bytes' => 'integer',
            'next_capture_seq' => 'integer',
            'next_run_number' => 'integer',
            'is_guest' => 'boolean',
            'deleted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Not soft-deleted.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('deleted_at');
    }

    /**
     * 10 chars base62 from a CSPRNG (`random_int`), not derived from anything
     * (schema §1.1, ≈ 59 bits). Non-reuse holds by randomness and the unique
     * index (domain §2.10).
     */
    public static function newKey(): string
    {
        $key = '';
        for ($i = 0; $i < 10; $i++) {
            $key .= self::KEY_ALPHABET[random_int(0, 61)];
        }

        return $key;
    }

    /** A fresh key that no project row holds now. */
    public static function unusedKey(): string
    {
        do {
            $key = self::newKey();
        } while (self::query()->where('project_key', $key)->exists());

        return $key;
    }
}
