<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\LegacyExportReader;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\LegacyAccountImported;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-off import of the 1.x accounts (decision 41, domain §2.4). Host
 * console only. Reads `users.json` from a path outside the repository,
 * checks it against the export's SHA256SUMS, never reads the hash fields,
 * creates unverified accounts with no password, prints counts only.
 */
final class ImportLegacyAccounts extends Command
{
    protected $signature = 'webhooktrap:import-1x {path : Path to users.json of the 1.x export} {--dry-run : Validate and count without writing}';

    protected $description = 'Import the 1.x accounts (forced reset: no password hash is read)';

    public function handle(Clock $clock, Audit $audit): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Export file not found.');

            return self::FAILURE;
        }
        $sums = dirname($path).DIRECTORY_SEPARATOR.'SHA256SUMS';
        if (! $this->checksumMatches($path, $sums)) {
            $this->error('Checksum mismatch or missing SHA256SUMS entry; nothing imported.');

            return self::FAILURE;
        }

        try {
            $rows = (new LegacyExportReader)->read((string) file_get_contents($path));
        } catch (Throwable) {
            $this->error('The export could not be read; nothing imported.');

            return self::FAILURE;
        }

        $created = $skipped = $invalid = 0;
        $dry = (bool) $this->option('dry-run');
        foreach ($rows as $row) {
            $email = $row['email'] === null ? null : trim($row['email']);
            if ($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $invalid++;

                continue;
            }
            if (User::query()->where('email', $email)->exists()) {
                $skipped++;

                continue;
            }
            if ($dry) {
                $created++;

                continue;
            }
            $createdAt = $this->date($row['created_at']) ?? $clock->now();
            DB::transaction(function () use ($email, $row, $createdAt, $clock, $audit): void {
                $user = User::query()->create([
                    'email' => $email,
                    'name' => $row['name'] === null ? null : mb_substr($row['name'], 0, 200),
                    'email_verified_at' => null,
                    'password_hash' => null,
                    'webauthn_user_handle' => random_bytes(32),
                    'legacy_source' => '1x',
                    'legacy_imported_at' => $clock->now(),
                    'created_at' => $createdAt,
                ]);
                $audit->record('legacy.imported', $user, RequestContext::console(), [], null);
                LegacyAccountImported::dispatch($user->id);
            });
            $created++;
        }

        $this->line(($dry ? 'Dry run. ' : '')."Imported: {$created}. Skipped (email exists): {$skipped}. Invalid: {$invalid}.");

        return self::SUCCESS;
    }

    private function checksumMatches(string $path, string $sums): bool
    {
        if (! is_file($sums)) {
            return false;
        }
        $expected = null;
        foreach (file($sums, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([0-9a-f]{64})\s+\*?(.+)$/i', trim($line), $m) === 1 && basename($m[2]) === basename($path)) {
                $expected = strtolower($m[1]);
            }
        }

        return $expected !== null && hash_equals($expected, (string) hash_file('sha256', $path));
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
