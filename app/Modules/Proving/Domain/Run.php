<?php

declare(strict_types=1);

namespace App\Modules\Proving\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A run (domain §6, schema §6): the server's record of what the engine did.
 * Status, reason, counts and exit code come from the engine's verdict; the
 * server checks Level 1 consistency only (S-5) and renders finding prose
 * itself (TM-16). Array columns (`capture_ids`, `source_seqs`, `scenarios`,
 * `levels`) are PostgreSQL arrays, read through `PgArrays`.
 *
 * @property string $id
 * @property string $project_id
 * @property int $number
 * @property string $source
 * @property string $executor
 * @property string|null $agent_id
 * @property bool $remote
 * @property array<string, mixed> $provenance
 * @property string|null $initiated_by_user_id
 * @property string|null $initiated_by_guest_session_id
 * @property string $status
 * @property string|null $status_reason
 * @property int|null $exit_code
 * @property string $fail_on
 * @property bool $fail_on_inconclusive
 * @property string $provider
 * @property string $pack_id
 * @property string $pack_version
 * @property string|null $target_scheme
 * @property string|null $target_host
 * @property int|null $target_port
 * @property string|null $target_path
 * @property int|null $seed
 * @property string|null $plan_hash
 * @property array<string, mixed>|null $plan
 * @property string|null $probe_display_policy
 * @property int|null $label_bits
 * @property list<array<string, mixed>>|null $probe_entries
 * @property int $counts_critical
 * @property int $counts_high
 * @property int $counts_medium
 * @property int $counts_info
 * @property int $delivery_count
 * @property bool $quota_counted
 * @property string $visibility
 * @property int $last_seq
 * @property int $observation_bytes
 * @property array<string, array<string, mixed>>|null $provisional_findings
 * @property array<string, mixed>|null $document
 * @property string|null $document_sha256
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property string|null $job_id
 * @property CarbonImmutable|null $job_expires_at
 */
final class Run extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'run';

    public const array FINAL = ['couldnt_start', 'aborted', 'findings', 'inconclusive', 'passed'];

    protected $table = 'runs';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'remote' => 'boolean',
            'provenance' => 'array',
            'exit_code' => 'integer',
            'fail_on_inconclusive' => 'boolean',
            'target_port' => 'integer',
            'seed' => 'integer',
            'plan_hash' => Bytea::class,
            'plan' => 'array',
            'label_bits' => 'integer',
            'probe_entries' => 'array',
            'counts_critical' => 'integer',
            'counts_high' => 'integer',
            'counts_medium' => 'integer',
            'counts_info' => 'integer',
            'delivery_count' => 'integer',
            'quota_counted' => 'boolean',
            'last_seq' => 'integer',
            'observation_bytes' => 'integer',
            'provisional_findings' => 'array',
            'document' => 'array',
            'document_sha256' => Bytea::class,
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'job_expires_at' => 'immutable_datetime',
        ];
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }

    /** @return list<string> */
    public function scenarioList(): array
    {
        return PgArrays::strings($this->getAttributes()['scenarios'] ?? null);
    }

    /** @return list<string> */
    public function captureIds(): array
    {
        return PgArrays::strings($this->getAttributes()['capture_ids'] ?? null);
    }

    /** @return list<string> hex SHA-256 per selected capture */
    public function captureHashes(): array
    {
        return PgArrays::strings($this->getAttributes()['capture_sha256'] ?? null);
    }

    /** @return list<int> */
    public function sourceSeqs(): array
    {
        return PgArrays::ints($this->getAttributes()['source_seqs'] ?? null);
    }

    /** @return list<int> */
    public function levels(): array
    {
        return PgArrays::ints($this->getAttributes()['levels'] ?? null);
    }
}
