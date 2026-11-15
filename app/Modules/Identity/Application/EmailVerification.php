<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

/**
 * Email verification links (domain §2.9): signed URL, 60 min, idempotent;
 * the `GET` shows a page, the `POST` verifies. Resend: one per 60 s and 10
 * per day per user.
 */
final readonly class EmailVerification
{
    public function __construct(private Clock $clock, private Notifier $notifier, private Throttle $throttle, private EmailOwnership $ownership) {}

    public function url(User $user): string
    {
        return URL::temporarySignedRoute('verification.show', $this->clock->now()->addMinutes((int) config('identity.links.verify_minutes')), [
            'id' => $user->publicId(),
            'hash' => sha1($user->email),
        ]);
    }

    public function send(User $user): void
    {
        $this->notifier->send($user->email, 'email-verify', [], $this->url($user));
    }

    /** Resend; returns the time to show when throttled. */
    public function resend(User $user): ?CarbonImmutable
    {
        if ($user->isVerified() || ! $user->isLive()) {
            return null;
        }
        $gap = "verify:gap:{$user->id}";
        $daily = "verify:day:{$user->id}";
        $gapSeconds = (int) config('identity.throttle.verify_resend_gap');
        if (! $this->throttle->reserve($gap, 1, $gapSeconds)) {
            return $this->throttle->availableAt($gap);
        }
        if (! $this->throttle->reserve($daily, (int) config('identity.throttle.verify_resend_daily'), 86400)) {
            return $this->throttle->availableAt($daily);
        }
        $this->send($user);

        return null;
    }

    /** The user a verification link names, when the hash still matches the address. */
    public function resolve(string $publicId, string $hash): ?User
    {
        $uuid = User::uuidFromPublicId($publicId);
        $user = $uuid === null ? null : User::query()->find($uuid);
        if ($user === null || ! $user->isLive() || ! hash_equals(sha1($user->email), $hash)) {
            return null;
        }

        return $user;
    }

    /** @return bool whether pre-verification credentials were discarded (F1) */
    public function verify(User $user, bool $byRegistrant): bool
    {
        return $this->ownership->proven($user, 'link', $byRegistrant);
    }
}
