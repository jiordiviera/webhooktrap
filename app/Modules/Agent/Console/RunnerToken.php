<?php

declare(strict_types=1);

namespace App\Modules\Agent\Console;

use App\Modules\Agent\Application\RunnerTokens;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php artisan runner:token [name] [--cidr=…]`: issues a runner token (RN-1,
 * RN-2, compose.yaml `runner_token`). The token is the only thing written to
 * stdout, once, so it can go straight into the secret file:
 *
 *     docker compose run --rm -T app php artisan runner:token runner > secrets/runner_token
 *
 * (`-T`: without it Compose allocates a TTY and stderr lands in the file
 * too.) Everything else goes to stderr. Issuing again under a taken name rotates:
 * a new `<name>-N` token, the older one stays live until `--revoke=<name>`.
 * Only the hash and the last 4 are stored; a lost token is replaced, never shown again.
 */
final class RunnerToken extends Command
{
    protected $signature = 'runner:token
        {name=runner : Runner name (rotation adds -2, -3, …)}
        {--cidr=* : Allowed source network, CIDR form (required on hosted, RN-1); repeat for several}
        {--revoke= : Revoke the live token with exactly this name instead of issuing one}
        {--list : List tokens (name, last 4, age, last seen) instead of issuing one}';

    protected $description = 'Issue, rotate, revoke or list server runner tokens';

    public function handle(RunnerTokens $tokens): int
    {
        $err = $this->output->getErrorStyle();

        if ($this->option('list')) {
            $this->table(['name', 'last 4', 'age (days)', 'last seen', 'revoked'], array_map(
                fn (array $r): array => [$r['name'], $r['last4'], $r['age_days'], $r['last_seen_at'] ?? '-', $r['revoked'] ? 'yes' : 'no'],
                $tokens->all(),
            ));

            return self::SUCCESS;
        }

        $revoke = $this->option('revoke');
        if (is_string($revoke) && $revoke !== '') {
            if (! $tokens->revoke($revoke)) {
                $err->error("No live runner token named {$revoke}.");

                return self::FAILURE;
            }
            $err->writeln("Revoked runner token {$revoke}.");

            return self::SUCCESS;
        }

        $cidrs = array_values(array_filter(array_map('strval', (array) $this->option('cidr')), fn (string $c): bool => $c !== ''));
        try {
            $issued = $tokens->issue((string) $this->argument('name'), $cidrs);
        } catch (InvalidArgumentException $e) {
            $err->error($e->getMessage());

            return self::FAILURE;
        }

        $this->output->writeln($issued['token'], OutputInterface::OUTPUT_RAW);
        $err->writeln("Issued runner token {$issued['name']} (…{$issued['last4']}). It is not shown again.");
        foreach ($issued['others'] as $other) {
            $stale = $other['age_days'] >= RunnerTokens::ROTATE_AFTER_DAYS ? ', past the 90-day rotation' : '';
            $err->writeln("Still live: {$other['name']} ({$other['age_days']} days{$stale}). Revoke it with --revoke={$other['name']} once the new token is in use.");
        }

        return self::SUCCESS;
    }
}
