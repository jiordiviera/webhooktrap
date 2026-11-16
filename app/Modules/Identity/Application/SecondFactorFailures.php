<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SecondFactorFailure;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\SecondFactorFailed;
use App\Modules\Identity\Events\SecondFactorHold;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Per-account second-factor failure counter across challenges (WA-3,
 * TM-T122): 20 failures in a rolling 24 h hold second-factor sign-in for
 * 1 hour. A success does not reset it; every failure is audited, never the code.
 */
final readonly class SecondFactorFailures
{
    public function __construct(private Clock $clock, private Audit $audit, private Notifier $notifier, private SignInAlerts $alerts) {}

    public function heldUntil(User $user): ?CarbonImmutable
    {
        $until = $user->second_factor_hold_until;

        return $until !== null && $until->greaterThan($this->clock->now()) ? $until : null;
    }

    /**
     * Runs one second-factor check with the per-account counter read and
     * written under a row lock on the user (A09 F2), so concurrent attempts
     * are evaluated one at a time and none passes once the hold is set.
     * Returns the check result, or the hold end when nothing was verified.
     *
     * @param  callable(User): bool  $check
     */
    public function guarded(User $user, string $kind, ?RequestContext $context, callable $check): bool|CarbonImmutable
    {
        return DB::transaction(function () use ($user, $kind, $context, $check): bool|CarbonImmutable {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($until = $this->heldUntil($locked)) {
                return $until;
            }
            if ($check($locked)) {
                return true;
            }
            $this->record($locked, $kind, $context);

            return false;
        });
    }

    public function record(User $user, string $kind, ?RequestContext $context): void
    {
        $now = $this->clock->now();
        $c = config('identity.throttle.second_factor');
        SecondFactorFailure::query()->create(['user_id' => $user->id, 'failed_at' => $now, 'kind' => $kind]);
        $count = SecondFactorFailure::query()
            ->where('user_id', $user->id)
            ->where('failed_at', '>', $now->subHours((int) $c['window_hours']))
            ->count();

        $this->audit->record('second_factor_failed', $user, $context, ['kind' => $kind]);
        $this->alerts->failed();
        SecondFactorFailed::dispatch($user->id, $kind, $count);

        if ($count >= (int) $c['max'] && $this->heldUntil($user) === null) {
            $until = $now->addMinutes((int) $c['hold_minutes']);
            $user->forceFill(['second_factor_hold_until' => $until])->save();
            $iso = $until->toIso8601ZuluString('millisecond');
            $this->audit->record('second_factor_hold', $user, $context, ['until' => $iso]);
            $this->notifier->send($user->email, 'second-factor-hold', ['until' => $iso]);
            SecondFactorHold::dispatch($user->id, $iso);
        }
    }
}
