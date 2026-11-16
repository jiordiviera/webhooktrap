<?php

declare(strict_types=1);

namespace App\Modules\Retention\Application;

use App\Infrastructure\Clock\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The deletion ledger (domain §2.10, DL-3): `(usr_ id, deleted_at)`, never the
 * address. Two copies:
 * - the `deletion_ledger` table (restored with the database);
 * - the file at `retention.ledger_path` (`/ledger/deletions.jsonl`), outside
 *   the database, which `backup` ships off-server (DL-3b). One JSON line per
 *   entry: `{"subject":"usr_…","deleted_at":"<ISO 8601 UTC>"}`; a subject may
 *   repeat (a retried append), readers take the union by subject.
 *
 * `append()` is durable once it returns (file line fsync'd, then the row):
 * the purge job deletes the `users` row only after it (DL-3a). After a
 * restore, `accounts:apply-ledger` reads `entries()` and re-applies them
 * (DL-3c).
 */
final readonly class DeletionLedger
{
    public function __construct(private Clock $clock) {}

    /** Durable once this returns; throws when a write fails (the purge then keeps the row). */
    public function append(string $subject, CarbonImmutable $deletedAt): void
    {
        // Own (sub)transaction, as in record(): a failed read never poisons the caller's.
        if (DB::transaction(fn (): bool => $this->has($subject))) {
            return; // the file line was written before the row
        }
        $path = self::configuredPath();
        if ($path !== null) {
            $this->appendLine($path, $subject, $deletedAt);
        }
        $this->record($subject, $deletedAt);
    }

    /** The table copy only (idempotent): used when re-applying a restored ledger. */
    public function record(string $subject, CarbonImmutable $deletedAt): void
    {
        // Own (sub)transaction: a failed write never poisons the caller's.
        DB::transaction(fn () => DB::table('deletion_ledger')->insertOrIgnore([
            'subject' => $subject,
            'deleted_at' => $deletedAt,
            'appended_at' => $this->clock->now(),
        ]));
    }

    public function has(string $subject): bool
    {
        return DB::table('deletion_ledger')->where('subject', $subject)->exists();
    }

    public static function configuredPath(): ?string
    {
        $path = config('retention.ledger_path');

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Union of the table and, when given, a ledger file: subject => the
     * earliest deletion time seen. Lines that are not a valid entry are
     * counted in `malformed`, never guessed at.
     *
     * @return array{entries: array<string, CarbonImmutable>, malformed: int}
     *
     * @throws RuntimeException when the file cannot be read
     */
    public function entries(?string $file): array
    {
        $entries = [];
        foreach (DB::table('deletion_ledger')->orderBy('subject')->get(['subject', 'deleted_at']) as $row) {
            $entries[(string) $row->subject] = CarbonImmutable::parse((string) $row->deleted_at)->utc();
        }

        $malformed = 0;
        if ($file !== null) {
            $handle = is_file($file) && is_readable($file) ? fopen($file, 'rb') : false;
            if ($handle === false) {
                throw new RuntimeException('ledger file not readable');
            }
            try {
                while (($line = fgets($handle)) !== false) {
                    if (trim($line) === '') {
                        continue;
                    }
                    $entry = self::parse($line);
                    if ($entry === null) {
                        $malformed++;

                        continue;
                    }
                    [$subject, $at] = $entry;
                    if (! isset($entries[$subject]) || $at->lessThan($entries[$subject])) {
                        $entries[$subject] = $at;
                    }
                }
            } finally {
                fclose($handle);
            }
        }
        ksort($entries);

        return ['entries' => $entries, 'malformed' => $malformed];
    }

    /** @return array{0: string, 1: CarbonImmutable}|null */
    private static function parse(string $line): ?array
    {
        $data = json_decode($line, true);
        if (! is_array($data) || ! is_string($data['subject'] ?? null) || ! is_string($data['deleted_at'] ?? null)
            || preg_match('/^usr_[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $data['subject']) !== 1) {
            return null;
        }
        try {
            return [$data['subject'], CarbonImmutable::parse($data['deleted_at'])->utc()];
        } catch (Throwable) {
            return null;
        }
    }

    private function appendLine(string $path, string $subject, CarbonImmutable $deletedAt): void
    {
        $line = json_encode([
            'subject' => $subject,
            'deleted_at' => $deletedAt->utc()->toIso8601ZuluString('microsecond'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";

        $handle = @fopen($path, 'ab');
        if ($handle === false) {
            throw new RuntimeException('deletion ledger file not writable');
        }
        try {
            if (! flock($handle, LOCK_EX) || fwrite($handle, $line) !== strlen($line) || ! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('deletion ledger write failed');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
