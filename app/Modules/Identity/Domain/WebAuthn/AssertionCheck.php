<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\WebAuthn;

/**
 * Outcome of the server-side rules applied to an assertion whose signature
 * has already been verified (domain §2.2).
 */
enum AssertionCheck: string
{
    case Accepted = 'accepted';
    case Flagged = 'flagged';                       // credential already flagged: refused
    case CounterAnomaly = 'counter';                // WA-1: refuse and flag
    case UserHandleMismatch = 'user_handle_mismatch'; // WA-2: refuse and flag
    case BackupEligibilityChanged = 'backup_eligibility_changed';
    case UserVerificationMissing = 'user_verification_missing';
}
