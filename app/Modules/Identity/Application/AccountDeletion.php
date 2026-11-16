<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\AuditLog;
use App\Infrastructure\Persistence\Bytea;
use App\Modules\Identity\Contracts\AccountDeletionParticipant;
use App\Modules\Identity\Domain\AccountDeletionRequest;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\EmailChangeRequest;
use App\Modules\Identity\Domain\IdentityKeys;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionRevoker;
use App\Modules\Identity\Domain\Tokens;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Identity\Events\AccountDeletionCancelled;
use App\Modules\Identity\Events\AccountDeletionRequested;
use App\Modules\Retention\Application\DeletionLedger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Account deletion (domain §2.10): immediate tombstone (PO-L), purge within
 * 1 hour after the ledger entry is durable (DL-3a); accounts without a second
 * factor confirm by an emailed link (DL-4). During the tombstone every path
 * keyed on the address or an identity is a uniform no-op (DL-2): the guards,
 * the user provider and every lookup treat `deleted_at` as unknown.
 */
final readonly class AccountDeletion
{
    /** @param  iterable<AccountDeletionParticipant>  $participants  other modules' parts, in the same transactions (F13) */
    public function __construct(
        private Clock $clock,
        private Audit $audit,
        private AuditLog $auditLog,
        private Notifier $notifier,
        private SessionRevoker $revoker,
        private AccountRecovery $recovery,
        private DeletionLedger $ledger,
        private iterable $participants = [],
    ) {}

    /**
     * Refusals checked before the confirmation and again in the transaction.
     *
     * @return array{reason: 'recovery_open'|'recovery_completed'|'last_instance_admin', until?: string}|null
     */
    public function blocked(User $user): ?array
    {
        if (AccountRecovery::hostedOnly() && ($lock = $this->recovery->lock($user)) !== null) {
            return ['reason' => $lock['reason']] + ($lock['until'] !== null ? ['until' => $lock['until']->toIso8601ZuluString('millisecond')] : []);
        }
        if (config('identity.instance') === 'selfhost' && $user->is_instance_admin
            && ! User::query()->where('is_instance_admin', true)->whereNull('deleted_at')->whereNull('disabled_at')->whereKeyNot($user->id)->exists()) {
            return ['reason' => 'last_instance_admin'];
        }

        return null;
    }

    /** @param  array{reason: string, until?: string}  $blocked */
    public static function refusalCode(array $blocked): string
    {
        return $blocked['reason'] === 'last_instance_admin' ? 'last_instance_admin' : 'recovery_lock';
    }

    /** DL-4: creates the pending request and emails the link to the current address. */
    public function requestLink(User $user, RequestContext $context): AccountDeletionRequest
    {
        $token = Tokens::random();
        $now = $this->clock->now();
        $request = DB::transaction(function () use ($user, $token, $now): AccountDeletionRequest {
            foreach ($this->pendingQuery($user)->get() as $old) {
                $old->forceFill(['cancelled_at' => $now, 'cancelled_reason' => 'replaced'])->save();
                AccountDeletionCancelled::dispatch($user->id, $old->id, 'replaced');
            }

            return AccountDeletionRequest::query()->create([
                'user_id' => $user->id,
                'token_hash' => Tokens::hash($token),
                'requested_at' => $now,
                'expires_at' => $now->addMinutes((int) config('identity.links.deletion_minutes')),
            ]);
        });
        $this->audit->record('account_deletion_requested', $user, $context);
        $this->notifier->send($user->email, 'account-deletion-confirm', [], url('/account-deletion/'.$token));
        AccountDeletionRequested::dispatch($user->id, $request->id);

        return $request;
    }

    public function pending(User $user): ?AccountDeletionRequest
    {
        return $this->pendingQuery($user)->where('expires_at', '>', $this->clock->now())->first();
    }

    public function cancelPending(User $user, RequestContext $context): void
    {
        foreach ($this->pendingQuery($user)->get() as $pending) {
            $pending->forceFill(['cancelled_at' => $this->clock->now(), 'cancelled_reason' => 'user'])->save();
            $this->audit->record('account_deletion_cancelled', $user, $context, ['reason' => 'user']);
            AccountDeletionCancelled::dispatch($user->id, $pending->id, 'user');
        }
    }

    public function byToken(string $token): ?AccountDeletionRequest
    {
        $request = AccountDeletionRequest::query()->where('token_hash', Bytea::bind(Tokens::hash($token)))->first();
        if ($request === null || $request->used_at !== null || $request->cancelled_at !== null
            || $request->expires_at->lessThanOrEqualTo($this->clock->now()) || ! $request->user->isLive()
            || $request->user->hasSecondFactor()) {
            return null;
        }

        return $request;
    }

    /**
     * Phase 1, one transaction: lock the user row, re-check the refusals,
     * tombstone, revoke every session (the current one included), cancel and
     * close pending requests, delete reset tokens, audit with `email_hmac`
     * and queue the `account-deleted` mail with the address copied in.
     */
    public function tombstone(User $user, string $actor, RequestContext $context, ?AccountDeletionRequest $usedRequest = null): void
    {
        $now = $this->clock->now();
        DB::transaction(function () use ($user, $actor, $context, $usedRequest, $now): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->deleted_at !== null) {
                throw new IdentityRefusal('confirmation_stale');
            }
            if (($blocked = $this->blocked($locked)) !== null) {
                throw new IdentityRefusal(self::refusalCode($blocked));
            }
            if ($usedRequest !== null) {
                $fresh = AccountDeletionRequest::query()->lockForUpdate()->find($usedRequest->id);
                if ($fresh === null || $fresh->used_at !== null || $fresh->cancelled_at !== null || $locked->hasSecondFactor()) {
                    throw new IdentityRefusal('link_invalid');
                }
                $fresh->forceFill(['used_at' => $now])->save();
            }

            $locked->forceFill(['deleted_at' => $now])->save();
            $this->revoker->revokeAll($locked->id, 'account_deleted');
            EmailChangeRequest::query()->where('user_id', $locked->id)->whereNull('confirmed_at')->whereNull('cancelled_at')
                ->update(['cancelled_at' => $now, 'cancelled_reason' => 'user']);
            $this->pendingQuery($locked)->update(['cancelled_at' => $now, 'cancelled_reason' => 'user']);
            DB::table('password_reset_tokens')->where('email', $locked->email)->delete();
            // Other modules' phase 1 (domain §2.10), in this transaction.
            // Agent and Proving revoke agents, delete pairing codes and access
            // tokens and abort open runs here (AC-4).
            // TODO(tokens, sharing slices): personal/CI tokens, share tokens.
            foreach ($this->participants as $participant) {
                $participant->tombstone($locked->id);
            }

            $this->auditLog->record(
                action: 'account.deleted',
                subjectUserId: $locked->id,
                subject: $locked->publicId(),
                actorUserId: $actor === 'user' ? $locked->id : null,
                ipPrefix: $actor === 'user' ? $context->ipPrefix : null,
                uaLabel: $actor === 'user' ? $context->uaLabel : null,
                emailHmac: IdentityKeys::emailHmacOf($locked->email),
            );
            $this->notifier->send($locked->email, 'account-deleted', $actor === 'user'
                ? ['browser' => $context->uaLabel, 'network' => $context->ipPrefix] : []);
        });

        AccountDeleted::dispatch($user->id, $user->publicId(), $actor);
    }

    /**
     * Phase 2 (maintenance job, within 1 h): ledger first, then mask the audit
     * rows, let each module purge its tree (workspace first), then delete the
     * user (the FKs cascade the identity tree).
     */
    public function purgeDue(): int
    {
        $purged = 0;
        foreach (User::query()->whereNotNull('deleted_at')->orderBy('deleted_at')->limit(100)->get() as $user) {
            try {
                $this->ledger->append($user->publicId(), $user->deleted_at ?? $this->clock->now());
            } catch (Throwable $e) {
                report($e); // purge-late alert; the row is kept (DL-3a)

                continue;
            }
            DB::transaction(fn () => $this->purgeTree($user));
            $purged++;
        }

        return $purged;
    }

    /**
     * DL-3c, after a restore (`accounts:apply-ledger`): an account in the
     * deletion ledger that exists again is purged at once with the same
     * ownership-tree purge as phase 2, whether or not the restored row carries
     * its tombstone. No mail, no event, no queue: nothing else runs during a
     * restore, and the purge deletes the sessions, tokens and agents with the
     * tree. The ledger row is written again first (the restored table may
     * predate it). Idempotent: false when the account is not there.
     */
    public function purgeLedgered(string $subject, CarbonImmutable $deletedAt): bool
    {
        $id = User::uuidFromPublicId($subject);
        if ($id === null) {
            return false;
        }
        $this->ledger->record($subject, $deletedAt);

        return DB::transaction(function () use ($id): bool {
            $user = User::query()->lockForUpdate()->find($id);
            if ($user === null) {
                return false;
            }
            $this->purgeTree($user);

            return true;
        });
    }

    /**
     * The `usr_` ids among `$subjects` whose `users` row exists.
     *
     * @param  list<string>  $subjects
     * @return list<string>
     */
    public function present(array $subjects): array
    {
        $ids = [];
        foreach ($subjects as $subject) {
            if (($id = User::uuidFromPublicId($subject)) !== null) {
                $ids[$id] = $subject;
            }
        }
        $found = [];
        foreach (array_chunk(array_keys($ids), 500) as $chunk) {
            foreach (User::query()->whereKey($chunk)->pluck('id') as $id) {
                $found[] = $ids[(string) $id];
            }
        }
        sort($found);

        return $found;
    }

    /** Phase 2 of one account, inside the caller's transaction. */
    private function purgeTree(User $user): void
    {
        $this->auditLog->maskEmailsFor($user->id);
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        foreach ($this->participants as $participant) {
            $participant->purge($user->id);
        }
        $user->delete();
    }

    /** Hourly job: pending deletion requests past their hour close as `expired`. */
    public function expire(): int
    {
        $expired = AccountDeletionRequest::query()->whereNull('used_at')->whereNull('cancelled_at')
            ->where('expires_at', '<=', $this->clock->now())->get();
        foreach ($expired as $request) {
            $request->forceFill(['cancelled_at' => $this->clock->now(), 'cancelled_reason' => 'expired'])->save();
            AccountDeletionCancelled::dispatch($request->user_id, $request->id, 'expired');
        }

        return $expired->count();
    }

    /** @return Builder<AccountDeletionRequest> */
    private function pendingQuery(User $user): Builder
    {
        return AccountDeletionRequest::query()->where('user_id', $user->id)->whereNull('used_at')->whereNull('cancelled_at');
    }
}
