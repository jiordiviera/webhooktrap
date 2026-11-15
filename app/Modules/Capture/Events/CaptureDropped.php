<?php

declare(strict_types=1);

namespace App\Modules\Capture\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A request to `/c/…` was answered and not stored (event-catalogue §2):
 * metrics only. Never the key, the project or anything of the request.
 * Reasons: `quota` (guest 100), `account_quota`, `guest_budget`,
 * `unknown_key`, `rotated`, `expired` (owner or project not live),
 * `store_error` (database failure after the request was read).
 */
final readonly class CaptureDropped
{
    use Dispatchable;

    public function __construct(public string $reason) {}
}
