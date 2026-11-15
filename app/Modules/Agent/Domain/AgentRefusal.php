<?php

declare(strict_types=1);

namespace App\Modules\Agent\Domain;

use RuntimeException;

/**
 * A refused agent request and its answer (contracts §5): status, an error
 * code, optional fields (`reason`, `min_agent_version`) and headers
 * (`Retry-After`). The message is the code, never content.
 */
final class AgentRefusal extends RuntimeException
{
    /**
     * @param  array<string, scalar|null>  $extra
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $error,
        public readonly array $extra = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($error);
    }

    public static function revoked(string $reason): self
    {
        return new self(401, 'revoked', ['reason' => $reason]);
    }

    public static function needsReconfirm(): self
    {
        return new self(403, 'needs_reconfirm');
    }

    public static function tooOld(): self
    {
        return new self(426, 'agent_too_old', ['min_agent_version' => (string) config('agent.min_agent_version')]);
    }
}
