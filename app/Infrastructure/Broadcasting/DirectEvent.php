<?php

declare(strict_types=1);

namespace App\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * One direct event (event-catalogue §3.2: `run.*`, `agent.*`, `pairing.*`,
 * `job.available`) on the `broadcasts` queue. Unlike a Live Prop trigger it
 * carries data and is never coalesced: every event is sent. Payloads are
 * small (rule 4) and hold no capture, secret or probe value (TM-23).
 */
final class DirectEvent implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    /**
     * @param  string  $channel  private channel name without the `private-` prefix
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $event,
        public readonly array $payload,
    ) {
        $this->onQueue('broadcasts');
    }

    public function handle(Factory $broadcasts): void
    {
        $broadcasts->connection()->broadcast([new PrivateChannel($this->channel)], $this->event, $this->payload);
    }
}
