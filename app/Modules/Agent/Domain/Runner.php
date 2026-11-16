<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A server runner (domain §5 "Runner", schema `runners`, ADR-010 §12):
 * instance-level, no workspace. Only the SHA-256 of its token and the last 4
 * characters are stored (TM-60). `allowed_cidrs` (PostgreSQL `cidr[]`) is
 * written by `RunnerTokens` as a literal and not read here.
 *
 * @property string $id
 * @property string $name
 * @property string $token_hash
 * @property string $last4
 * @property bool $binding_required
 * @property CarbonImmutable $issued_at
 * @property string|null $version
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $revoked_at
 */
final class Runner extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'rnr';

    public $timestamps = false;

    protected $table = 'runners';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'token_hash' => Bytea::class,
            'binding_required' => 'boolean',
            'issued_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
