<?php

declare(strict_types=1);

namespace App\Modules\Capture\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A capture (domain §4, schema §4). Request facts are immutable after insert
 * (a database trigger refuses any change); only derived facts and the
 * signature verdict change. Rows are inserted by Ingest only.
 *
 * @property string $id
 * @property string $project_id
 * @property string $capture_url_id
 * @property int $seq
 * @property CarbonImmutable $received_at
 * @property string $method
 * @property string $raw_target
 * @property string $path
 * @property string|null $raw_query
 * @property list<array{0: string, 1: string}> $headers
 * @property string $header_fidelity
 * @property string $public_origin
 * @property string $public_origin_source
 * @property array<string, mixed>|null $forwarded
 * @property string $source_ip
 * @property string|null $content_type
 * @property int $body_size
 * @property string $body_sha256
 * @property bool $body_kept
 */
final class Capture extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'cap';

    public $timestamps = false;

    protected $table = 'captures';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'received_at' => 'immutable_datetime',
            'headers' => 'array',
            'forwarded' => 'array',
            'body_size' => 'integer',
            'body_sha256' => Bytea::class,
            'body_kept' => 'boolean',
        ];
    }

    /**
     * `redacted_headers` is a PostgreSQL `text[]`.
     *
     * @return list<string>
     */
    public function redactedHeaders(): array
    {
        $raw = $this->getAttributes()['redacted_headers'] ?? '{}';
        $inner = trim((string) $raw, '{}');

        return $inner === '' ? [] : array_map(fn (string $n): string => trim($n, '"'), explode(',', $inner));
    }
}
