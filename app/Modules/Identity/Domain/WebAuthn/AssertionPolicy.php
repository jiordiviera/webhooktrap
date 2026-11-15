<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\WebAuthn;

use App\Modules\Identity\Domain\WebAuthnCredential;

/**
 * Server rules for a WebAuthn assertion, independent of the library that
 * verifies the signature (pending: see the slice 1 report).
 *
 * - WA-1 (TM-T120): refused when either the stored or the received count is
 *   non-zero and the received one is not greater; only 0 → 0 passes.
 * - WA-2 (TM-T121): on discoverable sign-in the userHandle must equal the
 *   owner's handle.
 * - BE is fixed at registration; UV is required for passkey sign-in.
 */
final class AssertionPolicy
{
    public static function check(
        WebAuthnCredential $credential,
        string $ownerHandle,
        int $receivedCount,
        bool $backupEligible,
        bool $userVerified,
        bool $discoverableSignIn,
        ?string $userHandle,
    ): AssertionCheck {
        if ($credential->flagged_at !== null) {
            return AssertionCheck::Flagged;
        }
        if ($discoverableSignIn && ($userHandle === null || ! hash_equals($ownerHandle, $userHandle))) {
            return AssertionCheck::UserHandleMismatch;
        }
        if (self::counterIsAnomalous($credential->sign_count, $receivedCount)) {
            return AssertionCheck::CounterAnomaly;
        }
        if ($backupEligible !== $credential->backup_eligible) {
            return AssertionCheck::BackupEligibilityChanged;
        }
        if ($discoverableSignIn && ! $userVerified) {
            return AssertionCheck::UserVerificationMissing;
        }

        return AssertionCheck::Accepted;
    }

    public static function counterIsAnomalous(int $stored, int $received): bool
    {
        if ($stored === 0 && $received === 0) {
            return false;
        }

        return $received <= $stored;
    }

    public static function isPasskey(WebAuthnCredential $credential): bool
    {
        return $credential->discoverable && $credential->uv_at_registration;
    }
}
