<?php

declare(strict_types=1);

namespace App\Modules\Agent\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Agent\Domain\Runner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Runner tokens (contracts §1.1, schema `runners`, ADR-010 §12.1), issued on
 * the host by `runner:token` only.
 *
 * - Value: `wt_rnr_` + 32 base62 (CSPRNG) + 6-char checksum (CRC32 of the
 *   prefix and the random part, base62, zero-padded), so secret scanners can
 *   recognise a leak without a server call. Shown once; only the SHA-256 and
 *   the last 4 are stored (TM-60).
 * - Source binding (RN-1): on hosted (`identity.instance`) a token needs at
 *   least one CIDR; on self-host it is optional.
 * - Rotation (RN-2): issuing again under a name that is already taken creates
 *   `<name>-2`, `<name>-3`, … and leaves the older token live, so two tokens
 *   overlap until the operator revokes the old one once the new one is seen.
 */
final readonly class RunnerTokens
{
    public const int ROTATE_AFTER_DAYS = 90;

    private const string PREFIX = 'wt_rnr_';

    private const string BASE62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function __construct(private Clock $clock) {}

    /**
     * @param  list<string>  $cidrs
     * @return array{name: string, token: string, last4: string, others: list<array{name: string, age_days: int}>}
     *
     * @throws InvalidArgumentException with a message fit for the operator
     */
    public function issue(string $name, array $cidrs): array
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,62}$/', $name) !== 1) {
            throw new InvalidArgumentException('Name: lowercase letters, digits, ".", "_" or "-", at most 63 characters.');
        }
        foreach ($cidrs as $cidr) {
            if (! self::isNetwork($cidr)) {
                throw new InvalidArgumentException('Not a network address in CIDR form (host bits must be zero).');
            }
        }
        $bindingRequired = config('identity.instance') === 'hosted';
        if ($bindingRequired && $cidrs === []) {
            throw new InvalidArgumentException('Hosted instances need --cidr with the runner\'s egress address (RN-1).');
        }

        $others = $this->liveFamily($name);
        $rowName = $this->freeName($name);
        $token = self::newToken();
        Runner::query()->create([
            'name' => $rowName,
            'token_hash' => hash('sha256', $token, true),
            'last4' => substr($token, -4),
            'binding_required' => $bindingRequired,
            'allowed_cidrs' => $cidrs === [] ? null : '{'.implode(',', $cidrs).'}',
            'issued_at' => $this->clock->now(),
        ]);

        return ['name' => $rowName, 'token' => $token, 'last4' => substr($token, -4), 'others' => $others];
    }

    /** Revokes the live token with exactly this name; false when there is none. */
    public function revoke(string $name): bool
    {
        return Runner::query()->where('name', $name)->whereNull('revoked_at')
            ->update(['revoked_at' => $this->clock->now()]) > 0;
    }

    /**
     * Every token, newest first, for the operator: no hash, no value.
     *
     * @return list<array{name: string, last4: string, age_days: int, last_seen_at: string|null, revoked: bool}>
     */
    public function all(): array
    {
        $rows = [];
        foreach (Runner::query()->orderByDesc('issued_at')->get() as $runner) {
            $rows[] = [
                'name' => $runner->name,
                'last4' => $runner->last4,
                'age_days' => $this->ageDays($runner->issued_at),
                'last_seen_at' => $runner->last_seen_at?->toIso8601ZuluString(),
                'revoked' => $runner->revoked_at !== null,
            ];
        }

        return $rows;
    }

    /** Seconds since the most recent poll of any live runner, or null when none has polled (`/health`). */
    public function lastSeenAge(): ?int
    {
        $seen = Runner::query()->whereNull('revoked_at')->max('last_seen_at');
        if (! is_string($seen)) {
            return null;
        }

        return max(0, $this->clock->now()->getTimestamp() - CarbonImmutable::parse($seen)->getTimestamp());
    }

    public static function wellFormed(string $token): bool
    {
        if (preg_match('/^wt_rnr_([0-9A-Za-z]{32})([0-9A-Za-z]{6})$/', $token, $m) !== 1) {
            return false;
        }

        return hash_equals(self::checksum(self::PREFIX.$m[1]), $m[2]);
    }

    private static function newToken(): string
    {
        $random = '';
        for ($i = 0; $i < 32; $i++) {
            $random .= self::BASE62[random_int(0, 61)];
        }

        return self::PREFIX.$random.self::checksum(self::PREFIX.$random);
    }

    private static function checksum(string $value): string
    {
        $n = crc32($value);
        $out = '';
        for ($i = 0; $i < 6; $i++) {
            $out = self::BASE62[$n % 62].$out;
            $n = intdiv($n, 62);
        }

        return $out;
    }

    private static function isNetwork(string $cidr): bool
    {
        if (preg_match('#^([0-9A-Fa-f:.]+)/(\d{1,3})$#', $cidr, $m) !== 1) {
            return false;
        }
        $bytes = @inet_pton($m[1]);
        if ($bytes === false) {
            return false;
        }
        $bits = strlen($bytes) * 8;
        $prefix = (int) $m[2];
        if ($prefix > $bits) {
            return false;
        }
        for ($bit = $prefix; $bit < $bits; $bit++) {
            if ((ord($bytes[intdiv($bit, 8)]) >> (7 - $bit % 8)) & 1) {
                return false;
            }
        }

        return true;
    }

    /** @return list<array{name: string, age_days: int}> */
    private function liveFamily(string $name): array
    {
        $rows = [];
        foreach ($this->family($name)->whereNull('revoked_at')->orderBy('issued_at')->get() as $runner) {
            $rows[] = ['name' => $runner->name, 'age_days' => $this->ageDays($runner->issued_at)];
        }

        return $rows;
    }

    private function freeName(string $name): string
    {
        $taken = array_flip(array_map('strval', $this->family($name)->pluck('name')->all()));
        if (! isset($taken[$name])) {
            return $name;
        }
        for ($n = 2; ; $n++) {
            if (! isset($taken["{$name}-{$n}"])) {
                return "{$name}-{$n}";
            }
        }
    }

    /** @return Builder<Runner> */
    private function family(string $name): Builder
    {
        $suffix = '^'.preg_quote($name, null).'-[0-9]+$';

        return Runner::query()->where(fn (Builder $q) => $q->where('name', $name)->orWhere('name', '~', $suffix));
    }

    private function ageDays(CarbonImmutable $issuedAt): int
    {
        return max(0, intdiv($this->clock->now()->getTimestamp() - $issuedAt->getTimestamp(), 86400));
    }
}
