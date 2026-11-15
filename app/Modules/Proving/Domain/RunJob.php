<?php

declare(strict_types=1);

namespace App\Modules\Proving\Domain;

use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The instruction for one executor (domain §6, schema §6 `run_jobs`). The
 * captures it references are the only ones its executor may fetch, and only
 * while it is neither expired nor completed (TM-35).
 *
 * @property string $id
 * @property string|null $run_id
 * @property string $kind
 * @property string|null $agent_id
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $offer_after
 * @property CarbonImmutable $created_at
 */
final class RunJob extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'job';

    public $timestamps = false;

    protected $table = 'run_jobs';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'offer_after' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return list<string> */
    public function captureIds(): array
    {
        return PgArrays::strings($this->getAttributes()['capture_ids'] ?? null);
    }

    public function isLiveAt(CarbonImmutable $now): bool
    {
        return $this->completed_at === null && $this->expires_at->greaterThan($now);
    }
}
