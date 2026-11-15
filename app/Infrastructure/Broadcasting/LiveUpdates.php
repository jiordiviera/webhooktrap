<?php

declare(strict_types=1);

namespace App\Infrastructure\Broadcasting;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Sends Live Prop triggers (ADR-004 amendment 1, event-catalogue §3.1):
 * after the surrounding transaction commits (at once when there is none), on
 * the `broadcasts` queue. Persistence never depends on it: a failure to
 * queue is reported and swallowed (ADR-004).
 */
final readonly class LiveUpdates
{
    /**
     * @param  string  $channel  private channel name without the `private-` prefix
     * @param  array<string, scalar|null>  $payload
     */
    public function trigger(string $channel, string $event, array $payload = ['v' => 1]): void
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) >= LiveTrigger::MAX_PAYLOAD_BYTES) {
            throw new InvalidArgumentException('Broadcast payloads stay under 1 KiB.');
        }

        DB::afterCommit(function () use ($channel, $event, $payload): void {
            try {
                LiveTrigger::dispatch($channel, $event, $payload);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * A direct event (event-catalogue §3.2): sent after commit, never
     * coalesced. Same failure rule as a trigger.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(string $channel, string $event, array $payload): void
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) >= LiveTrigger::MAX_PAYLOAD_BYTES) {
            throw new InvalidArgumentException('Broadcast payloads stay under 1 KiB.');
        }

        DB::afterCommit(function () use ($channel, $event, $payload): void {
            try {
                DirectEvent::dispatch($channel, $event, $payload);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
