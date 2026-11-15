<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\EmailMask;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Domain\AccountDeletionRequest;
use App\Modules\Identity\Domain\AccountRecoveryRequest;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\EmailChangeRequest;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionRevoker;
use App\Modules\Identity\Domain\Tokens;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserIdentity;
use App\Modules\Identity\Events\AccountDeletionCancelled;
use App\Modules\Identity\Events\AccountRecoveryCancelled;
use App\Modules\Identity\Events\AccountRecoveryCompleted;
use App\Modules\Identity\Events\AccountRecoveryConfirmed;
use App\Modules\Identity\Events\AccountRecoveryExtended;
use App\Modules\Identity\Events\AccountRecoveryRequested;
use App\Modules\Identity\Events\WebAuthnCredentialRemoved;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Delayed email recovery on hosted (PO-J, domain §2.3) with A09's conditions:
 * RC-1 a second-factor sign-in cancels it; RC-2 a reset by email extends the
 * wait to 7 days; RC-3 locks; RC-4 agents re-confirm (Agent listener on
 * AccountRecoveryCompleted); RC-5 one open request, 30-day cool-down after a
 * confirmed cancel, uniform answers; RC-6 an audit row for every step.
 */
final readonly class AccountRecovery
{
    public function __construct(
        private Clock $clock,
        private Audit $audit,
        private Notifier $notifier,
        private Throttle $throttle,
        private SessionRevoker $revoker,
    ) {}

    public static function hostedOnly(): bool
    {
        return config('identity.instance') === 'hosted';
    }

    /** Uniform whatever the account state; silently does nothing when refused. */
    public function request(string $email, RequestContext $context): void
    {
        $c = config('identity.throttle');
        $emailKey = 'recovery:email:'.hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key'));
        $addressKey = 'recovery:addr:'.$context->addressKey;
        if (! $this->throttle->reserve($addressKey, $c['recovery_address']['max'], $c['recovery_address']['decay'])
            || ! $this->throttle->reserve($emailKey, $c['recovery_email']['max'], $c['recovery_email']['decay'])) {
            return;
        }

        $user = User::query()->where('email', trim($email))->first();
        if ($user === null || ! $user->isLive() || ! $user->hasSecondFactor() || $this->open($user) !== null || $this->inCooldown($user)) {
            return;
        }

        $token = Tokens::random();
        $request = AccountRecoveryRequest::query()->create([
            'user_id' => $user->id,
            'token_hash' => Tokens::hash($token),
            'requested_at' => $this->clock->now(),
        ]);
        $this->audit->record('recovery_requested', $user, $context);
        $this->notifier->send($user->email, 'recovery-request', [], url('/account-recovery/'.$token));
        AccountRecoveryRequested::dispatch($user->id, $request->id);
    }

    /** An unconfirmed request whose link still works (60 min), or null. */
    public function pendingLink(string $token): ?AccountRecoveryRequest
    {
        $request = AccountRecoveryRequest::query()->where('token_hash', Bytea::bind(Tokens::hash($token)))->first();
        if ($request === null || $request->confirmed_at !== null || $request->cancelled_at !== null || $request->completed_at !== null) {
            return null;
        }
        $minutes = (int) config('identity.links.recovery_request_minutes');

        return $request->requested_at->addMinutes($minutes)->greaterThan($this->clock->now()) && $request->user->isLive() ? $request : null;
    }

    /** "Start the 72-hour wait" on the interstitial's button. */
    public function confirm(AccountRecoveryRequest $request, RequestContext $context): void
    {
        $now = $this->clock->now();
        $user = $request->user;
        $completesAt = $now->addHours((int) config('identity.recovery.wait_hours'));
        $extended = null;
        $lastReset = $this->lastResetAt($user);
        if ($lastReset !== null && $lastReset->greaterThan($request->requested_at->subDays(7))) {
            $candidate = $lastReset->max($request->requested_at)->addDays((int) config('identity.recovery.reset_extension_days'));
            if ($candidate->greaterThan($completesAt)) {
                $completesAt = $candidate;
                $extended = $now;
            }
        }

        DB::transaction(function () use ($request, $user, $now, $completesAt, $extended, $context): void {
            $request->forceFill(['confirmed_at' => $now, 'completes_at' => $completesAt, 'wait_extended_at' => $extended])->save();
            $this->cancelPendingRequestsFor($user, $context);
        });

        $iso = $completesAt->toIso8601ZuluString('millisecond');
        $this->audit->record('recovery_confirmed', $user, $context, ['completesAt' => $iso]);
        $this->notifier->send($user->email, 'recovery-started', ['completesAt' => $iso]);
        AccountRecoveryConfirmed::dispatch($user->id, $request->id, $iso);
    }

    /** "Cancel, I found my key" before the wait starts: no cool-down, no email. */
    public function decline(AccountRecoveryRequest $request): void
    {
        $request->forceFill(['cancelled_at' => $this->clock->now(), 'cancelled_by' => 'declined'])->save();
        $this->audit->record('recovery_declined', $request->user);
        AccountRecoveryCancelled::dispatch($request->user_id, $request->id, 'declined');
    }

    public function cancelByUser(User $user, RequestContext $context): bool
    {
        $open = $this->open($user);
        if ($open === null || $open->confirmed_at === null) {
            return false;
        }
        $this->cancel($open, 'user', $context);

        return true;
    }

    /**
     * An email change closes a not yet confirmed request (A09 F12): its link
     * went to the old address. A confirmed one already locks the change (RC-3).
     */
    public function closeUnconfirmedOnEmailChange(User $user): void
    {
        foreach (AccountRecoveryRequest::query()->where('user_id', $user->id)->whereNull('confirmed_at')->whereNull('cancelled_at')->whereNull('completed_at')->get() as $request) {
            $request->forceFill(['cancelled_at' => $this->clock->now(), 'cancelled_by' => 'email_changed'])->save();
            $this->audit->record('recovery_declined', $user, null, ['reason' => 'email_changed']);
            AccountRecoveryCancelled::dispatch($user->id, $request->id, 'email_changed');
        }
    }

    /** RC-1: a sign-in or `/confirm-auth` that completed a second factor cancels the open wait. */
    public function cancelOnSecondFactor(User $user, RequestContext $context): void
    {
        $open = $this->open($user);
        if ($open !== null && $open->confirmed_at !== null) {
            $this->cancel($open, 'second_factor_signin', $context);
        }
    }

    /** RC-2: a password reset by email during the wait extends it to 7 days from now. */
    public function extendOnReset(User $user, RequestContext $context): void
    {
        $open = $this->open($user);
        if ($open === null || $open->confirmed_at === null || $open->completes_at === null) {
            return;
        }
        $candidate = $this->clock->now()->addDays((int) config('identity.recovery.reset_extension_days'));
        if ($candidate->lessThanOrEqualTo($open->completes_at)) {
            return;
        }
        $open->forceFill(['completes_at' => $candidate, 'wait_extended_at' => $this->clock->now()])->save();
        $iso = $candidate->toIso8601ZuluString('millisecond');
        $this->audit->record('recovery_extended', $user, $context, ['completesAt' => $iso]);
        $this->notifier->send($user->email, 'recovery-extended', ['completesAt' => $iso]);
        AccountRecoveryExtended::dispatch($user->id, $open->id, $iso);
    }

    /** The open (not cancelled, not completed) request, confirmed or not. */
    public function open(User $user): ?AccountRecoveryRequest
    {
        return AccountRecoveryRequest::query()
            ->where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->first();
    }

    /**
     * RC-3 lock: a confirmed open request, or `now < lock_until` after a
     * completed one. An unconfirmed request someone else started locks nothing.
     *
     * @return array{reason: 'recovery_open'|'recovery_completed', until: CarbonImmutable|null, requestedAt: CarbonImmutable}|null
     */
    public function lock(User $user): ?array
    {
        $open = $this->open($user);
        if ($open !== null && $open->confirmed_at !== null) {
            return ['reason' => 'recovery_open', 'until' => $open->completes_at, 'requestedAt' => $open->requested_at];
        }
        $done = AccountRecoveryRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('lock_until', '>', $this->clock->now())
            ->orderByDesc('completed_at')
            ->first();

        return $done === null ? null : ['reason' => 'recovery_completed', 'until' => $done->lock_until, 'requestedAt' => $done->requested_at];
    }

    /** AR-1: only identities linked before the recovery was requested are shielded. */
    public function shieldsIdentity(User $user, UserIdentity $identity): bool
    {
        $lock = $this->lock($user);

        return $lock !== null && $identity->linked_at->lessThan($lock['requestedAt']);
    }

    /** Completion job: every confirmed request whose wait is over. */
    public function completeDue(): int
    {
        $due = AccountRecoveryRequest::query()
            ->whereNotNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->where('completes_at', '<=', $this->clock->now())
            ->get();
        foreach ($due as $request) {
            $this->complete($request);
        }

        return $due->count();
    }

    /** Unconfirmed requests older than 60 min close as `link_expired` (no cool-down). */
    public function expireUnconfirmed(): int
    {
        $minutes = (int) config('identity.links.recovery_request_minutes');

        return AccountRecoveryRequest::query()
            ->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->where('requested_at', '<=', $this->clock->now()->subMinutes($minutes))
            ->update(['cancelled_at' => $this->clock->now(), 'cancelled_by' => 'link_expired']);
    }

    /** The second email, 24 h into the wait. */
    public function sendReminders(): int
    {
        $requests = AccountRecoveryRequest::query()
            ->whereNotNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->whereNull('reminder_sent_at')
            ->where('confirmed_at', '<=', $this->clock->now()->subHours(24))
            ->get();
        foreach ($requests as $request) {
            $request->forceFill(['reminder_sent_at' => $this->clock->now()])->save();
            $this->notifier->send($request->user->email, 'recovery-reminder', [
                'completesAt' => $request->completes_at?->toIso8601ZuluString('millisecond'),
            ]);
        }

        return $requests->count();
    }

    public function finishLink(string $token): ?AccountRecoveryRequest
    {
        $request = AccountRecoveryRequest::query()->where('finish_token_hash', Bytea::bind(Tokens::hash($token)))->first();

        return $request !== null && $request->finish_token_expires_at?->greaterThan($this->clock->now()) && $request->user->isLive()
            ? $request : null;
    }

    public function consumeFinish(AccountRecoveryRequest $request): void
    {
        $request->forceFill(['finish_token_hash' => null, 'finish_token_expires_at' => null])->save();
    }

    /** The completed request whose 7-day review window is open, if any. */
    public function reviewable(User $user): ?AccountRecoveryRequest
    {
        return AccountRecoveryRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->where('lock_until', '>', $this->clock->now())
            ->orderByDesc('completed_at')
            ->first();
    }

    private function complete(AccountRecoveryRequest $request): void
    {
        $user = $request->user;
        $now = $this->clock->now();
        $token = Tokens::random();
        $context = RequestContext::console();

        DB::transaction(function () use ($request, $user, $now, $token, $context): void {
            foreach ($user->webauthnCredentials()->get() as $credential) {
                $credential->delete();
                WebAuthnCredentialRemoved::dispatch($user->id, $credential->id, 'recovery');
            }
            $user->totp()->delete();
            $user->recoveryCodes()->delete();
            $user->forceFill(['two_factor_enabled_at' => null, 'password_hash' => null])->save();
            $this->revoker->revokeAll($user->id, 'recovery');
            $this->cancelPendingRequestsFor($user, $context);
            // TODO(tokens slice): revoke personal tokens; list CI tokens for review (RC-4).
            $request->forceFill([
                'completed_at' => $now,
                'lock_until' => $now->addDays((int) config('identity.recovery.lock_days')),
                'finish_token_hash' => Tokens::hash($token),
                'finish_token_expires_at' => $now->addHours((int) config('identity.links.recovery_finish_hours')),
            ])->save();
        });

        $this->audit->record('recovery_completed', $user, null);
        $this->notifier->send($user->email, 'recovery-completed', [], url('/account-recovery/finish/'.$token));
        AccountRecoveryCompleted::dispatch($user->id, $request->id);
    }

    private function cancel(AccountRecoveryRequest $request, string $by, RequestContext $context): void
    {
        $request->forceFill(['cancelled_at' => $this->clock->now(), 'cancelled_by' => $by])->save();
        $this->audit->record('recovery_cancelled', $request->user, $context, ['by' => $by]);
        $this->notifier->send($request->user->email, 'recovery-cancelled', ['by' => $by]);
        AccountRecoveryCancelled::dispatch($request->user_id, $request->id, $by);
    }

    /** Opening a recovery cancels a pending email change and deletion request (domain §2.6, §2.10). */
    private function cancelPendingRequestsFor(User $user, RequestContext $context): void
    {
        $now = $this->clock->now();
        foreach (EmailChangeRequest::query()->where('user_id', $user->id)->whereNull('confirmed_at')->whereNull('cancelled_at')->get() as $change) {
            $change->forceFill(['cancelled_at' => $now, 'cancelled_reason' => 'recovery'])->save();
            $this->audit->record('email_change_cancelled', $user, $context, ['masked' => EmailMask::mask($change->new_email), 'reason' => 'recovery']);
        }
        foreach (AccountDeletionRequest::query()->where('user_id', $user->id)->whereNull('used_at')->whereNull('cancelled_at')->get() as $deletion) {
            $deletion->forceFill(['cancelled_at' => $now, 'cancelled_reason' => 'recovery'])->save();
            $this->audit->record('account_deletion_cancelled', $user, $context, ['reason' => 'recovery']);
            AccountDeletionCancelled::dispatch($user->id, $deletion->id, 'recovery');
        }
    }

    private function inCooldown(User $user): bool
    {
        return AccountRecoveryRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('cancelled_by', ['user', 'second_factor_signin'])
            ->where('cancelled_at', '>', $this->clock->now()->subDays((int) config('identity.recovery.cooldown_days')))
            ->exists();
    }

    private function lastResetAt(User $user): ?CarbonImmutable
    {
        $at = DB::table('audit_log')->where('subject_user_id', $user->id)->where('action', 'password_reset')->max('at');

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }
}
