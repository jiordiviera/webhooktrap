<?php

declare(strict_types=1);

use App\Modules\Agent\Jobs\RunAgentMaintenance;
use App\Modules\Capture\Jobs\PurgeExpiredCaptures;
use App\Modules\Identity\Jobs\PurgeDeletedAccounts;
use App\Modules\Identity\Jobs\RunIdentityMaintenance;
use App\Modules\Instance\Application\Heartbeats;
use App\Modules\Proving\Jobs\ExpireRunJobs;
use App\Modules\Workspace\Jobs\PurgeDeletedProjects;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Identity maintenance (queue `maintenance`): recovery completion, link
// expiry, guest expiry; account purge within 1 hour (domain §2.10).
Schedule::job(new RunIdentityMaintenance)->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new PurgeDeletedAccounts)->everyFiveMinutes()->withoutOverlapping();

// Workspace: projects soft-deleted more than 24 h ago are hard-deleted (schema.md §8).
Schedule::job(new PurgeDeletedProjects)->hourly()->withoutOverlapping();

// Capture: account captures past the project's capture retention (domain §12).
Schedule::job(new PurgeExpiredCaptures)->dailyAt('03:15')->withoutOverlapping();

// Capture: re-derive stale captures (a pack update, or a failed derivation job);
// queues one batched job per project on `maintenance`.
Schedule::command('captures:rederive')->hourly()->withoutOverlapping();

// Agent: unconfirmed agents revoked 10 minutes after pair (RE-1); expired tokens, nonces, old codes.
Schedule::job(new RunAgentMaintenance)->everyMinute()->withoutOverlapping();

// Proving: runs whose job expired end Aborted `agent_lost` (domain §6).
Schedule::job(new ExpireRunJobs)->everyMinute()->withoutOverlapping();

// Instance: the scheduler's heartbeat, written inline by `schedule:work` (not
// queued), read by `app:heartbeat-check` (its Compose healthcheck) and `/health`.
Schedule::call(fn (Heartbeats $heartbeats) => $heartbeats->beat(Heartbeats::SCHEDULER))
    ->everyMinute()->name('instance:heartbeat');
