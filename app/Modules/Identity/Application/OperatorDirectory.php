<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Ids\EntityId;
use App\Infrastructure\Persistence\EmailMask;
use App\Modules\Identity\Domain\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Read model of accounts for the operator panel (decision 74). Arrays only,
 * built from an explicit column list: no hash, secret, recovery code, token,
 * key material or WebAuthn handle is ever selected. Addresses are masked,
 * except on the row whose address the operator typed in full.
 */
final readonly class OperatorDirectory
{
    public function __construct(private Clock $clock) {}

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function search(?string $term, int $page, int $perPage, ?string $sort = null, bool $descending = true): array
    {
        $term = self::term($term);
        $query = DB::table('users');
        if ($term !== null) {
            $uuid = str_starts_with($term, User::ID_PREFIX.'_') ? User::uuidFromPublicId($term) : null;
            $uuid !== null
                ? $query->where('users.id', $uuid)
                : $query->where('users.email', 'ilike', '%'.addcslashes($term, '%_\\').'%');
        }
        $total = (clone $query)->count();

        $column = match ($sort) {
            'last_sign_in_at' => 'last_sign_in_at',
            'email' => 'users.email',
            default => 'users.created_at',
        };
        $rows = $this->select($query)
            ->orderByRaw($column.($descending ? ' DESC NULLS LAST' : ' ASC NULLS FIRST'))
            ->orderBy('users.id')
            ->forPage(max(1, $page), max(1, min(100, $perPage)))
            ->get();

        return ['rows' => array_values($rows->map(fn (stdClass $row): array => $this->row($row, $term))->all()), 'total' => $total];
    }

    /**
     * One account by its external id, or null.
     *
     * @return array<string, mixed>|null
     */
    public function account(string $publicId): ?array
    {
        $uuid = User::uuidFromPublicId($publicId);
        if ($uuid === null) {
            return null;
        }
        $row = $this->select(DB::table('users')->where('users.id', $uuid))->first();

        return $row === null ? null : $this->row($row, null) + ['user_id' => $uuid];
    }

    /**
     * Guest sessions across the instance (counts only).
     *
     * @return array{live: int, started_24h: int, claimed_24h: int}
     */
    public function guestSessionCounts(): array
    {
        $now = $this->clock->now();
        $since = $now->subDay();

        return [
            'live' => DB::table('guest_sessions')->whereNull('ended_at')->whereNull('claimed_at')->where('expires_at', '>', $now)->count(),
            'started_24h' => DB::table('guest_sessions')->where('created_at', '>=', $since)->count(),
            'claimed_24h' => DB::table('guest_sessions')->where('claimed_at', '>=', $since)->count(),
        ];
    }

    private function select(Builder $query): Builder
    {
        return $query->select([
            'users.id', 'users.email', 'users.created_at', 'users.email_verified_at',
            'users.disabled_at', 'users.deleted_at', 'users.is_operator',
        ])
            ->selectRaw('EXISTS (SELECT 1 FROM totp_factors t WHERE t.user_id = users.id AND t.confirmed_at IS NOT NULL) AS has_totp')
            ->selectRaw('(SELECT count(*) FROM webauthn_credentials w WHERE w.user_id = users.id AND w.flagged_at IS NULL) AS key_count')
            ->selectRaw("(SELECT string_agg(i.provider, ', ' ORDER BY i.provider) FROM user_identities i WHERE i.user_id = users.id) AS providers")
            ->selectRaw('(SELECT max(s.created_at) FROM user_sessions s WHERE s.user_id = users.id) AS last_sign_in_at')
            ->selectRaw('(SELECT count(*) FROM guest_sessions g WHERE g.claimed_by_user_id = users.id) AS claimed_guest_sessions');
    }

    /** @return array<string, mixed> */
    private function row(stdClass $row, ?string $term): array
    {
        $email = (string) $row->email;
        $exact = $term !== null && str_contains($term, '@') && mb_strtolower($term) === mb_strtolower($email);

        return [
            'id' => EntityId::encode(User::ID_PREFIX, (string) $row->id),
            'email' => $exact ? $email : EmailMask::mask($email),
            'email_unmasked' => $exact,
            'created_at' => self::time($row->created_at),
            'verified' => $row->email_verified_at !== null,
            'totp' => (bool) $row->has_totp,
            'keys' => (int) $row->key_count,
            'providers' => is_string($row->providers) && $row->providers !== '' ? $row->providers : null,
            'last_sign_in_at' => self::time($row->last_sign_in_at),
            'operator' => (bool) $row->is_operator,
            'status' => $row->deleted_at !== null ? 'deleted' : ($row->disabled_at !== null ? 'disabled' : 'active'),
            'claimed_guest_sessions' => (int) $row->claimed_guest_sessions,
        ];
    }

    private static function term(?string $term): ?string
    {
        $term = trim((string) $term);

        return $term === '' ? null : mb_substr($term, 0, 254);
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->utc() : null;
    }
}
