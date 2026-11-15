<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserIdentity;
use App\Modules\Identity\Events\IdentityLinked;

/**
 * Creates `user_identities` rows (domain §2.5). Every link of a new identity
 * to an existing account is notified and audited (WA-5). An email-match link
 * on a 2FA account is only created once the challenge succeeds (A09 F3).
 */
final readonly class IdentityLinks
{
    public function __construct(
        private Clock $clock,
        private Audit $audit,
        private Notifier $notifier,
        private AccountRecovery $recovery,
    ) {}

    /**
     * @param  array{provider: string, subject: string, label: string, verified: bool}  $identity
     * @param  string  $via  signup|oauth_email_match|settings
     */
    public function create(User $user, array $identity, string $via, ?RequestContext $context): bool
    {
        // Re-checked at creation: a deferred link may be stale by now.
        if (UserIdentity::query()->where('provider', $identity['provider'])->where('provider_subject', $identity['subject'])->exists()
            || $user->identities()->where('provider', $identity['provider'])->exists()) {
            return false;
        }
        UserIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => $identity['provider'],
            'provider_subject' => $identity['subject'],
            'provider_email_verified' => $identity['verified'],
            'provider_label' => $identity['label'],
            'linked_at' => $this->clock->now(),
            'linked_via' => $via,
        ]);
        if ($via === 'signup') {
            return true;
        }
        $this->audit->record('identity_linked', $user, $context, ['provider' => $identity['provider'], 'label' => $identity['label']]);
        $this->notifier->send($user->email, 'method-linked', [
            'provider' => $identity['provider'],
            'recovery' => $this->recovery->lock($user) !== null ? 'open_or_recent' : null,
        ]);
        IdentityLinked::dispatch($user->id, $identity['provider'], $via);

        return true;
    }
}
