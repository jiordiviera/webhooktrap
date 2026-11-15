<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Carbon\CarbonImmutable;

/** Result of a sign-in step; `retryAt` is set when throttled or held. */
final readonly class SignInOutcome
{
    public const string SIGNED_IN = 'signed_in';

    public const string CHALLENGE = 'challenge';

    public const string FAILED = 'failed';

    public const string THROTTLED = 'throttled';

    public const string RESTART = 'restart';

    /** Correct password on an unverified account: no session, verify page (A09 F1). */
    public const string VERIFY_EMAIL = 'verify_email';

    public function __construct(public string $status, public ?CarbonImmutable $retryAt = null, public ?User $user = null) {}
}
