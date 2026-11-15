<?php

declare(strict_types=1);

namespace App\Modules\Agent\Jobs;

use App\Infrastructure\Clock\Clock;
use App\Modules\Agent\Application\AgentLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

/**
 * Every minute (domain §5, §12): revoke agents unconfirmed 10 minutes after
 * `pair` (RE-1), delete expired access tokens and nonces, and pairing codes
 * older than 24 hours.
 */
final class RunAgentMaintenance implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(AgentLifecycle $lifecycle, Clock $clock): void
    {
        $lifecycle->expireUnconfirmed();
        $now = $clock->now();
        DB::table('agent_access_tokens')->where('expires_at', '<', $now)->delete();
        DB::table('agent_auth_nonces')->where('expires_at', '<', $now)->delete();
        DB::table('pairing_codes')->where('created_at', '<', $now->subDay())->delete();
    }
}
