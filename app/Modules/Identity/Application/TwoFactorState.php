<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\RecoveryCode;
use App\Modules\Identity\Domain\RecoveryCodeGenerator;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\RecoveryCodesRegenerated;
use App\Modules\Identity\Events\TwoFactorStateChanged;

/**
 * Keeps `users.two_factor_enabled_at` and the recovery codes in step with the
 * factors held (domain §2.1, §2.3): codes are generated when the first second
 * factor is enrolled and deleted when the last one is removed.
 */
final readonly class TwoFactorState
{
    public function __construct(private Clock $clock, private Audit $audit, private Notifier $notifier) {}

    /** @return list<string>|null recovery codes to show once, when 2FA just turned on */
    public function refresh(User $user, ?RequestContext $context): ?array
    {
        $enabled = $user->hasSecondFactor();

        if ($enabled && $user->two_factor_enabled_at === null) {
            $user->forceFill(['two_factor_enabled_at' => $this->clock->now()])->save();
            TwoFactorStateChanged::dispatch($user->id, true);

            return $this->generateCodes($user);
        }

        if (! $enabled && $user->two_factor_enabled_at !== null) {
            $user->forceFill(['two_factor_enabled_at' => null])->save();
            $user->recoveryCodes()->delete();
            $this->audit->record('two_factor_disabled', $user, $context);
            $this->notifier->send($user->email, 'two-factor-disabled');
            TwoFactorStateChanged::dispatch($user->id, false);
        }

        return null;
    }

    /** @return list<string> */
    public function regenerate(User $user, ?RequestContext $context): array
    {
        $codes = $this->generateCodes($user);
        $this->audit->record('recovery_codes_regenerated', $user, $context);
        $this->notifier->send($user->email, 'recovery-codes-regenerated');
        RecoveryCodesRegenerated::dispatch($user->id, count($codes));

        return $codes;
    }

    public function codesLeft(User $user): ?int
    {
        return $user->two_factor_enabled_at === null ? null : $user->recoveryCodes()->whereNull('used_at')->count();
    }

    /** @return list<string> */
    private function generateCodes(User $user): array
    {
        $user->recoveryCodes()->delete();
        $codes = RecoveryCodeGenerator::generate();
        $now = $this->clock->now();
        foreach ($codes as $code) {
            RecoveryCode::query()->create([
                'user_id' => $user->id,
                'code_hash' => RecoveryCodeGenerator::hash($code),
                'created_at' => $now,
            ]);
        }

        return $codes;
    }
}
