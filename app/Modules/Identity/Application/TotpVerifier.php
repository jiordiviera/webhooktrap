<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\Totp;
use App\Modules\Identity\Domain\TotpFactor;
use App\Modules\Identity\Domain\User;

/** Verifies a code against the user's confirmed TOTP factor, at most once per step. */
final readonly class TotpVerifier
{
    public function __construct(private Clock $clock) {}

    public function verify(User $user, string $code, bool $requireConfirmed = true): bool
    {
        $factor = TotpFactor::query()->find($user->id);
        if ($factor === null || ($requireConfirmed && $factor->confirmed_at === null)) {
            return false;
        }
        $step = Totp::verify($factor->plainSecret(), $code, $this->clock->now()->getTimestamp(), $factor->last_used_step);
        if ($step === null) {
            return false;
        }
        // Conditional update: a code (step) is accepted at most once, even under races.
        $updated = TotpFactor::query()
            ->whereKey($user->id)
            ->where(fn ($q) => $q->whereNull('last_used_step')->orWhere('last_used_step', '<', $step))
            ->update(['last_used_step' => $step]);

        return $updated === 1;
    }
}
