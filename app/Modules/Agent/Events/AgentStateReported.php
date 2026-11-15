<?php

declare(strict_types=1);

namespace App\Modules\Agent\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A hello was accepted (event-catalogue §2). Never secret names or hosts. */
final readonly class AgentStateReported
{
    use Dispatchable;

    public function __construct(public string $agentId, public string $version) {}
}
