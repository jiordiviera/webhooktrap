<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Application\OperatorAccess;
use Illuminate\Console\Command;

/**
 * `php artisan operator:grant {email}`: the only way to make an account an
 * operator (decision 74). The panel still requires a verified email and a
 * passkey, security key or authenticator app on that account.
 */
final class OperatorGrant extends Command
{
    protected $signature = 'operator:grant {email : Address of an existing account}';

    protected $description = 'Give an account access to the operator admin panel';

    public function handle(OperatorAccess $access): int
    {
        $email = (string) $this->argument('email');
        $outcome = $access->grant($email);
        if ($outcome === OperatorAccess::NOT_FOUND) {
            $this->error('No account with that address.');

            return self::FAILURE;
        }
        $this->info($outcome === OperatorAccess::GRANTED ? 'Operator access granted.' : 'Already an operator.');
        if (! $access->stillEligible($email)) {
            $this->warn('The panel stays closed until the account has a verified email and a passkey, security key or authenticator app.');
        }

        return self::SUCCESS;
    }
}
