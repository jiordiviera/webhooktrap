<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\HasUuidV7;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A paired local agent (domain §5, schema §5). Mirrors (secret names,
 * targets, probe hash) are display-only and written by `hello` after the
 * first confirmation only (RE-1).
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property string $public_key
 * @property string $key_fingerprint
 * @property string $version
 * @property string|null $os
 * @property string|null $arch
 * @property int $proto
 * @property string $state
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable $paired_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_reason
 * @property bool $guest_slot
 * @property bool $needs_reconfirm
 * @property CarbonImmutable|null $confirmed_at
 * @property string|null $probe_config_hash
 * @property string|null $replaces_agent_id
 */
final class Agent extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'agt';

    protected $table = 'agents';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'public_key' => Bytea::class,
            'probe_config_hash' => Bytea::class,
            'proto' => 'integer',
            'last_seen_at' => 'immutable_datetime',
            'paired_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'guest_slot' => 'boolean',
            'needs_reconfirm' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Agent>  $query
     * @return Builder<Agent>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** `SHA256:` + unpadded base64 of the key's SHA-256, as OpenSSH prints it. */
    /**
     * AC-3: a name not taken by any live agent of the workspace. A collision
     * gets the first free suffix (`maya-mbp-2`), the base shortened so the
     * result stays within the name grammar (48 characters).
     *
     * @param  list<string>  $taken
     */
    public static function freeName(string $name, array $taken): string
    {
        $taken = array_flip(array_map('strtolower', $taken));
        if (! isset($taken[strtolower($name)])) {
            return $name;
        }
        for ($i = 2; ; $i++) {
            $suffix = '-'.$i;
            $candidate = substr($name, 0, 48 - strlen($suffix)).$suffix;
            if (! isset($taken[strtolower($candidate)])) {
                return $candidate;
            }
        }
    }

    public static function fingerprint(string $publicKey): string
    {
        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $publicKey, true)), '=');
    }
}
