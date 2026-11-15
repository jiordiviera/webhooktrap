<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\Persistence\EmailMask;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Domain\AccountDeletionRequest;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\EmailChangeRequest;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionRevoker;
use App\Modules\Identity\Domain\Tokens;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\AccountDeletionCancelled;
use App\Modules\Identity\Events\EmailChangeConfirmed;
use App\Modules\Identity\Events\EmailChanged;
use App\Modules\Identity\Events\EmailChangeRequested;
use Illuminate\Support\Facades\DB;

/**
 * Email change (domain §2.6). Uniform answer whether the new address is free
 * or taken; on an account without a second factor both addresses confirm
 * (EC-1); each half is a POST from a browser signed in to the account.
 */
final readonly class EmailChanges
{
    public function __construct(
        private Clock $clock,
        private Audit $audit,
        private Notifier $notifier,
        private Throttle $throttle,
        private SessionRevoker $revoker,
        private AccountRecovery $recovery,
    ) {}

    public function request(User $user, string $newEmail, RequestContext $context): void
    {
        if (! $user->isVerified()) {
            throw new IdentityRefusal('email_unverified');
        }
        if ($this->recovery->lock($user) !== null) {
            throw new IdentityRefusal('recovery_lock');
        }
        $c = config('identity.throttle.email_change');
        if (! $this->throttle->reserve("email-change:{$user->id}", $c['max'], $c['decay'])) {
            throw new IdentityRefusal('throttled');
        }

        $newEmail = trim($newEmail);
        $now = $this->clock->now();
        $oldRequired = ! $user->hasSecondFactor();
        $token = Tokens::random();
        $oldToken = $oldRequired ? Tokens::random() : null;

        $request = DB::transaction(function () use ($user, $newEmail, $now, $oldRequired, $token, $oldToken): EmailChangeRequest {
            EmailChangeRequest::query()->where('user_id', $user->id)->whereNull('confirmed_at')->whereNull('cancelled_at')
                ->update(['cancelled_at' => $now, 'cancelled_reason' => 'replaced']);

            return EmailChangeRequest::query()->create([
                'user_id' => $user->id,
                'new_email' => $newEmail,
                'token_hash' => Tokens::hash($token),
                'requested_at' => $now,
                'expires_at' => $now->addMinutes((int) config('identity.links.email_change_minutes')),
                'old_required' => $oldRequired,
                'old_token_hash' => $oldToken === null ? null : Tokens::hash($oldToken),
            ]);
        });

        $taken = User::query()->where('email', $newEmail)->exists();
        if ($taken) {
            $this->notifier->send($newEmail, 'email-change-taken', [], url('/sign-in'));
        } else {
            $this->notifier->send($newEmail, 'email-change-verify', [], url('/email-change/'.$token));
        }
        if ($oldToken !== null) {
            $this->notifier->send($user->email, 'email-change-approve', ['masked' => EmailMask::mask($newEmail)], url('/email-change-old/'.$oldToken));
        } else {
            $this->notifier->send($user->email, 'email-change-requested', ['masked' => EmailMask::mask($newEmail)]);
        }
        $this->audit->record('email_change_requested', $user, $context, ['masked' => EmailMask::mask($newEmail)]);
        EmailChangeRequested::dispatch($user->id, $request->id);
    }

    public function cancel(User $user, RequestContext $context): void
    {
        $pending = $this->pending($user);
        if ($pending === null) {
            return;
        }
        $pending->forceFill(['cancelled_at' => $this->clock->now(), 'cancelled_reason' => 'user'])->save();
        $this->audit->record('email_change_cancelled', $user, $context, ['masked' => EmailMask::mask($pending->new_email), 'reason' => 'user']);
    }

    public function pending(User $user): ?EmailChangeRequest
    {
        return EmailChangeRequest::query()->where('user_id', $user->id)->whereNull('confirmed_at')->whereNull('cancelled_at')
            ->where('expires_at', '>', $this->clock->now())->first();
    }

    /** A request whose link (new or old side) still works, or null. */
    public function byToken(string $token, string $side): ?EmailChangeRequest
    {
        $column = $side === 'old' ? 'old_token_hash' : 'token_hash';
        $request = EmailChangeRequest::query()->where($column, Bytea::bind(Tokens::hash($token)))->first();
        if ($request === null || $request->confirmed_at !== null || $request->cancelled_at !== null
            || $request->expires_at->lessThanOrEqualTo($this->clock->now()) || ! $request->user->isLive()) {
            return null;
        }
        if ($side === 'old' && ! $request->old_required) {
            return null;
        }
        if ($side === 'new' && $request->new_confirmed_at !== null || $side === 'old' && $request->old_confirmed_at !== null) {
            return null;
        }

        return $request;
    }

    /** For the owner: the link is unusable when the address was taken since or a recovery locks it. */
    public function blockedFor(EmailChangeRequest $request): bool
    {
        return $this->recovery->lock($request->user) !== null
            || User::query()->where('email', $request->new_email)->exists();
    }

    /**
     * Records one half; applies the change when the other half (if needed)
     * is already recorded. Returns `changed`, `waiting_old` or `waiting_new`.
     */
    public function confirm(EmailChangeRequest $request, string $side, ?string $currentSessionId, RequestContext $context): string
    {
        $user = $request->user;

        return DB::transaction(function () use ($request, $side, $user, $currentSessionId, $context): string {
            $request = EmailChangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $now = $this->clock->now();
            if ($this->blockedFor($request)) {
                throw new IdentityRefusal('link_invalid');
            }
            $request->forceFill([$side === 'old' ? 'old_confirmed_at' : 'new_confirmed_at' => $now])->save();

            $complete = $request->new_confirmed_at !== null && (! $request->old_required || $request->old_confirmed_at !== null);
            if (! $complete) {
                $this->audit->record('email_change_confirmed', $user, $context, ['masked' => EmailMask::mask($request->new_email), 'side' => $side]);
                EmailChangeConfirmed::dispatch($user->id, $request->id, $side);

                return $side === 'new' ? 'waiting_old' : 'waiting_new';
            }
            if ($request->old_required) {
                $this->audit->record('email_change_confirmed', $user, $context, ['masked' => EmailMask::mask($request->new_email), 'side' => $side]);
                EmailChangeConfirmed::dispatch($user->id, $request->id, $side);
            }

            $oldEmail = $user->email;
            $user->forceFill(['email' => $request->new_email, 'email_verified_at' => $now])->save();
            $request->forceFill(['confirmed_at' => $now])->save();
            DB::table('password_reset_tokens')->whereIn('email', [$oldEmail, $request->new_email])->delete();
            $this->revoker->revokeAll($user->id, 'email_changed', $currentSessionId);
            foreach (AccountDeletionRequest::query()->where('user_id', $user->id)->whereNull('used_at')->whereNull('cancelled_at')->get() as $deletion) {
                $deletion->forceFill(['cancelled_at' => $now, 'cancelled_reason' => 'email_changed'])->save();
                AccountDeletionCancelled::dispatch($user->id, $deletion->id, 'email_changed');
            }
            // F12: a recovery link sent to the old address stops working.
            $this->recovery->closeUnconfirmedOnEmailChange($user);

            $masked = EmailMask::mask($request->new_email);
            $this->audit->record('email_changed', $user, $context, ['masked' => $masked]);
            $this->notifier->send($oldEmail, 'email-changed', ['masked' => $masked]);
            $this->notifier->send($request->new_email, 'email-changed-confirmation');
            EmailChanged::dispatch($user->id, $request->id);

            return 'changed';
        });
    }

    /** Hourly job: pending requests past their expiry close as `expired`. */
    public function expire(): int
    {
        return EmailChangeRequest::query()->whereNull('confirmed_at')->whereNull('cancelled_at')
            ->where('expires_at', '<=', $this->clock->now())
            ->update(['cancelled_at' => $this->clock->now(), 'cancelled_reason' => 'expired']);
    }
}
