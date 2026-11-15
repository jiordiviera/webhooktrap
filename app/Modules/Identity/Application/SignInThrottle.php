<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\RateLimit\ClientAddress;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\PasswordSignInHeld;
use App\Modules\Identity\Events\SignInFailed;
use Carbon\CarbonImmutable;

/**
 * Sign-in throttles (domain §2.1 "Throttle values", TH-1). Per-email keys
 * are HMACs of the normalised email and count unknown addresses too; holds
 * are checked before any hash runs (TM-T159).
 */
final readonly class SignInThrottle
{
    public function __construct(private Throttle $throttle, private Audit $audit) {}

    public function emailKey(string $email): string
    {
        return ClientAddress::emailKey($email, (string) config('app.key'));
    }

    /** End of the address block that stops every sign-in method, or null. */
    public function addressBlockedUntil(string $addressKey): ?CarbonImmutable
    {
        $c = config('identity.throttle');
        $ends = [];
        if ($this->throttle->tooMany("signin:addr:fail:{$addressKey}", $c['address_failures']['max'])) {
            $ends[] = $this->throttle->availableAt("signin:addr:fail:{$addressKey}");
        }
        if ($this->throttle->tooMany("signin:addr:req:{$addressKey}", $c['address_requests']['max'])) {
            $ends[] = $this->throttle->availableAt("signin:addr:req:{$addressKey}");
        }

        return $this->latest($ends);
    }

    /**
     * Counts this password sign-in request and reserves its slot in the
     * pair, account and address-failure counters **before** any hash runs
     * (A09 F2: atomic reserve-then-check, so concurrent requests cannot
     * exceed a limit). A non-null value is the `retryAt` to show; nothing is
     * then verified (TH-1a).
     */
    public function reservePassword(string $emailKey, string $addressKey): ?CarbonImmutable
    {
        $c = config('identity.throttle');
        if ($until = $this->addressBlockedUntil($addressKey)) {
            return $until;
        }
        $this->throttle->hit("signin:addr:req:{$addressKey}", $c['address_requests']['decay']);
        if ($held = $this->accountHeldUntil($emailKey)) {
            return $held;
        }

        $pair = "signin:pair:{$emailKey}:{$addressKey}";
        $acct = "signin:acct:{$emailKey}";
        $fail = "signin:addr:fail:{$addressKey}";
        if (! $this->throttle->reserve($pair, $c['pair']['max'], $c['pair']['decay'])) {
            return $this->throttle->availableAt($pair);
        }
        if (! $this->throttle->reserve($acct, $c['account']['max'], $c['account']['decay'])) {
            $this->throttle->refund($pair, $c['pair']['decay']);

            return $this->throttle->availableAt($acct);
        }
        if (! $this->throttle->reserve($fail, $c['address_failures']['max'], $c['address_failures']['decay'])) {
            $this->throttle->refund($pair, $c['pair']['decay']);
            $this->throttle->refund($acct, $c['account']['decay']);

            return $this->throttle->availableAt($fail);
        }

        return null;
    }

    /** The reserved slots stay counted as failures; places the account hold when due. */
    public function recordFailure(string $emailKey, string $addressKey, ?User $user, ?RequestContext $context): void
    {
        $this->accountFailed($emailKey, $user, $context);
        SignInFailed::dispatch('password', hash('sha256', $addressKey));
    }

    public function accountHeldUntil(string $emailKey): ?CarbonImmutable
    {
        return $this->throttle->heldUntil("signin:acct:{$emailKey}");
    }

    /**
     * Reserves one slot of the per-account 20 / h key before a password is
     * verified (shared with `/confirm-auth`, TH-1b). False during a hold or
     * once the window is full.
     */
    public function reserveAccount(string $emailKey): bool
    {
        $c = config('identity.throttle.account');
        if ($this->accountHeldUntil($emailKey) !== null) {
            return false;
        }

        return $this->throttle->reserve("signin:acct:{$emailKey}", $c['max'], $c['decay']);
    }

    public function refundAccount(string $emailKey): void
    {
        $this->throttle->refund("signin:acct:{$emailKey}", (int) config('identity.throttle.account.decay'));
    }

    /** After a failed (already reserved) password check: places the hold at the limit. */
    public function accountFailed(string $emailKey, ?User $user, ?RequestContext $context): void
    {
        $c = config('identity.throttle');
        $key = "signin:acct:{$emailKey}";
        if ($this->throttle->attempts($key) >= $c['account']['max'] && $this->throttle->heldUntil($key) === null) {
            $until = $this->throttle->hold($key, $c['account']['hold']);
            $this->throttle->clear($key);
            if ($user !== null) {
                $this->audit->record('sign_in_held', $user, $context, ['until' => $until->toIso8601ZuluString('millisecond')]);
                PasswordSignInHeld::dispatch($user->id, $until->toIso8601ZuluString('millisecond'));
            }
        }
    }

    /** Reserves one per-address failure slot before a passkey assertion is verified (F2). */
    public function reserveAddressFailure(string $addressKey): ?CarbonImmutable
    {
        $c = config('identity.throttle');
        if ($until = $this->addressBlockedUntil($addressKey)) {
            return $until;
        }
        $this->throttle->hit("signin:addr:req:{$addressKey}", $c['address_requests']['decay']);
        $key = "signin:addr:fail:{$addressKey}";

        return $this->throttle->reserve($key, $c['address_failures']['max'], $c['address_failures']['decay'])
            ? null : $this->throttle->availableAt($key);
    }

    public function refundAddressFailure(string $addressKey): void
    {
        $this->throttle->refund("signin:addr:fail:{$addressKey}", (int) config('identity.throttle.address_failures.decay'));
    }

    /** A verified password gives its reservations back. */
    public function recordSuccess(string $emailKey, string $addressKey): void
    {
        $c = config('identity.throttle');
        $this->throttle->clear("signin:pair:{$emailKey}:{$addressKey}");
        $this->refundAccount($emailKey);
        $this->throttle->refund("signin:addr:fail:{$addressKey}", $c['address_failures']['decay']);
    }

    /**
     * A generic visible limit (register, forgot-password address, …):
     * counts the request and returns the block end once over the limit.
     */
    public function limit(string $key, int $max, int $decay): ?CarbonImmutable
    {
        // Reserve-then-check (F2): the request is counted before it is acted on.
        return $this->throttle->reserve($key, $max, $decay) ? null : $this->throttle->availableAt($key);
    }

    /** @param  list<CarbonImmutable>  $ends */
    private function latest(array $ends): ?CarbonImmutable
    {
        $latest = null;
        foreach ($ends as $end) {
            if ($latest === null || $end->greaterThan($latest)) {
                $latest = $end;
            }
        }

        return $latest;
    }
}
