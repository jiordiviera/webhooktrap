<?php

declare(strict_types=1);

namespace App\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * One Live Prop trigger (event-catalogue §3.1) on the `broadcasts` queue: an
 * event with a tiny payload on one private channel; the client reloads the
 * props that listen for it.
 *
 * Coalesced per (channel, event): while one trigger waits in the queue, more
 * are dropped (unique until processing starts). A trigger carries no data, so
 * the waiting one covers them; one dispatched after processing starts is
 * queued again, so nothing committed after the broadcast is missed.
 */
final class LiveTrigger implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, Queueable;

    /** Payload limit (event-catalogue rule 4). */
    public const int MAX_PAYLOAD_BYTES = 1024;

    public int $tries = 3;

    /** The uniqueness lock outlives a lost job by at most this long. */
    public int $uniqueFor = 30;

    /**
     * @param  string  $channel  private channel name without the `private-` prefix, e.g. `project.<uuid>`
     * @param  array<string, scalar|null>  $payload
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $event,
        public readonly array $payload,
    ) {
        $this->onQueue('broadcasts');
    }

    public function uniqueId(): string
    {
        return $this->channel.'|'.$this->event;
    }

    public function handle(Factory $broadcasts): void
    {
        $broadcasts->connection()->broadcast([new PrivateChannel($this->channel)], $this->event, $this->payload);
    }
}
