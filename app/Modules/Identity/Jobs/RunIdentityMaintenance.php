<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jobs;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Application\AccountRecovery;
use App\Modules\Identity\Application\EmailChanges;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Identity\Domain\GuestSession;
use App\Modules\Identity\Domain\SecondFactorFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Scheduled Identity maintenance (queue `maintenance`): recovery completion,
 * reminders and link expiry, expiry of pending email-change and deletion
 * requests, second-factor failure rows older than 24 h, expired guest
 * sessions (GuestSessionEnded `expired`).
 */
final class RunIdentityMaintenance implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(Clock $clock, AccountRecovery $recovery, EmailChanges $emailChanges, AccountDeletion $deletion, GuestSessions $guests): void
    {
        if (AccountRecovery::hostedOnly()) {
            $recovery->expireUnconfirmed();
            $recovery->sendReminders();
            $recovery->completeDue();
        }
        $emailChanges->expire();
        $deletion->expire();
        SecondFactorFailure::query()->where('failed_at', '<', $clock->now()->subHours(24))->delete();

        GuestSession::query()->whereNull('ended_at')->whereNull('claimed_at')->where('expires_at', '<=', $clock->now())
            ->limit(500)->get()->each(fn (GuestSession $g) => $guests->end($g, 'expired'));
    }
}
