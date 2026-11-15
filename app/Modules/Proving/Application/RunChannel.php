<?php

declare(strict_types=1);

namespace App\Modules\Proving\Application;

use App\Modules\Identity\Contracts\BrowserChannel;
use App\Modules\Identity\Contracts\Principal;

/**
 * `private-run.{runId}` (event-catalogue §1): the owner principal of the
 * run's project only; share viewers never subscribe (they poll).
 */
final readonly class RunChannel implements BrowserChannel
{
    public function __construct(private RunViews $views) {}

    public function name(): string
    {
        return 'run';
    }

    public function allows(Principal $principal, string $id): bool
    {
        return $this->views->ownsRunId($principal, $id);
    }
}
