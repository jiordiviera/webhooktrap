<?php

declare(strict_types=1);

namespace App\Infrastructure\EngineProcess;

/**
 * A validated preview, or an error code (`^[a-z0-9_]{1,64}$`): the adapter's
 * own (`engine_unavailable`, `timeout`, `busy`, `bad_reply`,
 * `frame_too_large`) or the engine's when it matches the charset. Never
 * engine text.
 */
final readonly class PlanResult
{
    public const string UNAVAILABLE = 'engine_unavailable';

    public const string TIMEOUT = 'timeout';

    public const string BUSY = 'busy';

    public const string BAD_REPLY = 'bad_reply';

    public const string FRAME_TOO_LARGE = 'frame_too_large';

    /** Faults of the call, not of its input: never cached. */
    public const array TRANSIENT = [self::UNAVAILABLE, self::TIMEOUT, self::BUSY, self::BAD_REPLY, 'memory_limit', 'internal_error'];

    private function __construct(public ?PlanPreview $preview, public ?string $error) {}

    public static function ok(PlanPreview $preview): self
    {
        return new self($preview, null);
    }

    public static function failed(string $code): self
    {
        return new self(null, preg_match('/^[a-z0-9_]{1,64}$/', $code) === 1 ? $code : self::BAD_REPLY);
    }

    public function transient(): bool
    {
        return $this->error !== null && in_array($this->error, self::TRANSIENT, true);
    }
}
