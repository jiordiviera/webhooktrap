<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Retention\Application\DeletionLedger;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * `php artisan accounts:apply-ledger`: restore runbook step 6 (hosted plan
 * §5.1, DL-3c). Every `usr_` id in the deletion ledger (the file at
 * `LEDGER_PATH` or `--file`, plus the restored `deletion_ledger` table) whose
 * account exists again in the restored database is purged with the live
 * purge's ownership-tree purge, without mail, events or queue. Idempotent: a
 * second run prints `applied 0, remaining 0`.
 *
 * Exit 0 when nothing in the ledger remains and every line was read; 1
 * otherwise (missing or unreadable file, malformed lines, rows left).
 */
final class ApplyDeletionLedger extends Command
{
    protected $signature = 'accounts:apply-ledger
        {--file= : Ledger file (JSON lines); default LEDGER_PATH}
        {--allow-missing : Go on with the database table alone when there is no ledger file (an instance that never deleted an account)}';

    protected $description = 'Re-apply account deletions from the deletion ledger after a restore (DL-3c)';

    public function handle(AccountDeletion $deletion, DeletionLedger $ledger): int
    {
        $option = $this->option('file');
        $file = is_string($option) && $option !== '' ? $option : DeletionLedger::configuredPath();
        $allowMissing = (bool) $this->option('allow-missing');

        if ($file === null && ! $allowMissing) {
            $this->error('No ledger file: set LEDGER_PATH or pass --file (or --allow-missing to use the database table alone).');

            return self::FAILURE;
        }
        if ($file !== null && ! is_file($file) && $allowMissing) {
            $this->warn('No ledger file at the given path; using the database table alone.');
            $file = null;
        }

        try {
            ['entries' => $entries, 'malformed' => $malformed] = $ledger->entries($file);
        } catch (RuntimeException) {
            $this->error('Ledger file missing or unreadable; nothing applied.');

            return self::FAILURE;
        }

        $applied = 0;
        $subjects = array_keys($entries);
        foreach ($deletion->present($subjects) as $subject) {
            if ($deletion->purgeLedgered($subject, $entries[$subject])) {
                $applied++;
            }
        }
        $remaining = count($deletion->present($subjects));

        $this->line(sprintf('ledger %d, applied %d, remaining %d', count($entries), $applied, $remaining));
        if ($malformed > 0) {
            $this->error("{$malformed} ledger line(s) could not be read; check the file before finishing the restore.");
        }

        return $remaining === 0 && $malformed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
