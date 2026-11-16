<?php

declare(strict_types=1);

namespace App\Modules\Instance\Console;

use App\Modules\Instance\Application\Heartbeats;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan app:heartbeat-check --max-age=120`: the scheduler's Compose
 * healthcheck (compose-profile §2, §4, §6). Exit 0 when the last heartbeat is
 * at most `--max-age` seconds old; 1 when it is older, missing, or the
 * database cannot be read.
 */
final class HeartbeatCheck extends Command
{
    protected $signature = 'app:heartbeat-check
        {--max-age=120 : Oldest acceptable heartbeat, in seconds}
        {--name=scheduler : Which heartbeat to check}';

    protected $description = 'Exit 0 when the scheduler heartbeat is recent (Compose healthcheck)';

    public function handle(Heartbeats $heartbeats): int
    {
        $maxAge = filter_var($this->option('max-age'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($maxAge === false) {
            $this->error('--max-age must be a positive number of seconds.');

            return self::INVALID;
        }
        $name = (string) $this->option('name');

        try {
            $age = $heartbeats->age($name);
        } catch (Throwable) {
            $this->error('heartbeat unreadable: database unavailable');

            return self::FAILURE;
        }

        if ($age === null) {
            $this->error("no {$name} heartbeat yet");

            return self::FAILURE;
        }
        if ($age > $maxAge) {
            $this->error("{$name} heartbeat is {$age}s old (max {$maxAge}s)");

            return self::FAILURE;
        }
        $this->line("{$name} heartbeat {$age}s old");

        return self::SUCCESS;
    }
}
