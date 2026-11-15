<?php

declare(strict_types=1);

namespace App\Modules\Capture\Jobs;

use App\Modules\Capture\Application\CaptureRederivation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Re-derives one project's captures after a pack update, oldest first, in
 * bounded runs on `maintenance`; a run that stops at its budget queues the
 * next one from where it stopped.
 */
final class RederiveProjectCaptures implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 280;

    /** @param  list<string>|null  $providers  null: every provider; `unknown` selects captures no pack matched */
    public function __construct(
        public readonly string $projectId,
        public readonly bool $all,
        public readonly ?array $providers,
        public readonly int $afterSeq = 0,
    ) {
        $this->onQueue('maintenance');
    }

    public function handle(CaptureRederivation $rederivation): void
    {
        $next = $rederivation->run($this->projectId, $this->all, $this->providers, $this->afterSeq);
        if ($next !== null) {
            self::dispatch($this->projectId, $this->all, $this->providers, $next);
        }
    }
}
