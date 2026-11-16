<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Application\OperatorAccess;
use Illuminate\Console\Command;

/**
 * `php artisan operator:revoke {email}`: removes operator access (decision
 * 74). Takes effect on the next panel request, Livewire updates included.
 */
final class OperatorRevoke extends Command
{
    protected $signature = 'operator:revoke {email : Address of an existing account}';

    protected $description = 'Remove an account\'s access to the operator admin panel';

    public function handle(OperatorAccess $access): int
    {
        $outcome = $access->revoke((string) $this->argument('email'));
        if ($outcome === OperatorAccess::NOT_FOUND) {
            $this->error('No account with that address.');

            return self::FAILURE;
        }
        $this->info($outcome === OperatorAccess::REVOKED ? 'Operator access revoked.' : 'Not an operator.');

        return self::SUCCESS;
    }
}
