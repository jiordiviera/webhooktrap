<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jobs;

use App\Modules\Identity\Application\AccountDeletion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Phase 2 of account deletion (domain §2.10): ledger first, then purge, in batches. */
final class PurgeDeletedAccounts implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(AccountDeletion $deletion): void
    {
        $deletion->purgeDue();
    }
}
