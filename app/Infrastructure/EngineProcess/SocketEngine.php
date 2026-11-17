<?php

declare(strict_types=1);

namespace App\Infrastructure\EngineProcess;

use App\Infrastructure\Clock\Clock;
use JsonException;

/**
 * Client of `webhooktrap serve-sandbox` (agent/internal/sandbox): one call
 * per connection, a 4-byte big-endian length then the JSON request, write
 * side half-closed, one NDJSON reply line of at most 1 MiB. One deadline
 * covers connect, write and read. Holds no state between calls (worker
 * mode); the reply goes through `ReplyDecoder`.
 */
final readonly class SocketEngine implements Engine
{
    public const int MAX_FRAME = 6 << 20;

    public const int MAX_REPLY = 1 << 20;

    public function __construct(
        private Clock $clock,
        private string $socketPath,
        private int $timeoutMs = 8000,
        private int $connectTimeoutMs = 1000,
        private ReplyDecoder $decoder = new ReplyDecoder,
    ) {}

    public function plan(PlanRequest $request): PlanResult
    {
        try {
            $frame = json_encode(['op' => 'plan', 'input' => $request->input()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return PlanResult::failed('bad_input');
        }
        if (strlen($frame) > self::MAX_FRAME) {
            return PlanResult::failed(PlanResult::FRAME_TOO_LARGE);
        }
        $line = $this->call($frame);

        return $line instanceof PlanResult ? $line : $this->decoder->plan($line, $request);
    }

    /** The reply line (without its newline), or the failure. */
    private function call(string $frame): string|PlanResult
    {
        $deadline = $this->nowMs() + $this->timeoutMs;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client('unix://'.$this->socketPath, $errno, $errstr,
            min($this->connectTimeoutMs, $this->timeoutMs) / 1000, STREAM_CLIENT_CONNECT);
        if (! is_resource($socket)) {
            return PlanResult::failed(PlanResult::UNAVAILABLE);
        }
        try {
            stream_set_blocking($socket, true);
            $data = pack('N', strlen($frame)).$frame;
            while ($data !== '') {
                if (! $this->arm($socket, $deadline)) {
                    return PlanResult::failed(PlanResult::TIMEOUT);
                }
                $n = @fwrite($socket, $data);
                if ($n === false || $n === 0) {
                    if (self::timedOut($socket)) {
                        return PlanResult::failed(PlanResult::TIMEOUT);
                    }
                    break; // the engine may have answered early (busy, frame_too_large): read it
                }
                $data = substr($data, $n);
            }
            @stream_socket_shutdown($socket, STREAM_SHUT_WR);

            $buf = '';
            while (true) {
                $nl = strpos($buf, "\n");
                if ($nl !== false) {
                    return $nl > self::MAX_REPLY ? PlanResult::failed(PlanResult::BAD_REPLY) : substr($buf, 0, $nl);
                }
                if (strlen($buf) > self::MAX_REPLY) {
                    return PlanResult::failed(PlanResult::BAD_REPLY);
                }
                if (! $this->arm($socket, $deadline)) {
                    return PlanResult::failed(PlanResult::TIMEOUT);
                }
                $chunk = @fread($socket, 65536);
                if ($chunk === false || $chunk === '') {
                    if (self::timedOut($socket)) {
                        return PlanResult::failed(PlanResult::TIMEOUT);
                    }
                    if (feof($socket) || $chunk === false) {
                        return PlanResult::failed($buf === '' && $data !== '' ? PlanResult::UNAVAILABLE : PlanResult::BAD_REPLY);
                    }

                    continue;
                }
                $buf .= $chunk;
            }
        } finally {
            fclose($socket);
        }
    }

    /** Sets the stream timeout to what is left of the deadline; false when nothing is left. */
    private function arm(mixed $socket, int $deadline): bool
    {
        $left = $deadline - $this->nowMs();
        if ($left <= 0) {
            return false;
        }
        stream_set_timeout($socket, intdiv($left, 1000), ($left % 1000) * 1000);

        return true;
    }

    private function nowMs(): int
    {
        return (int) $this->clock->now()->getTimestampMs();
    }

    private static function timedOut(mixed $socket): bool
    {
        return stream_get_meta_data($socket)['timed_out'];
    }
}
