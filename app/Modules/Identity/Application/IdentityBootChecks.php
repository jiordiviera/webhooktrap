<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\IdentityKeys;
use App\Modules\Identity\Domain\PasswordPolicy;

/**
 * Fail-closed configuration checks (domain §2.1, A09 F6, F8, F9, F10). The
 * service provider refuses to serve while any of them reports a problem.
 */
final class IdentityBootChecks
{
    /** OWASP floor for argon2id (domain §2.1 "never below"). */
    public const int MIN_ARGON_MEMORY_KIB = 19456;

    public const int MIN_ARGON_TIME = 2;

    /** @return list<string> argon2id parameters below the floor */
    public static function hashingProblems(): array
    {
        $problems = [];
        if ((int) config('hashing.argon.memory') < self::MIN_ARGON_MEMORY_KIB) {
            $problems[] = 'ARGON_MEMORY is below '.self::MIN_ARGON_MEMORY_KIB.' KiB';
        }
        if ((int) config('hashing.argon.time') < self::MIN_ARGON_TIME) {
            $problems[] = 'ARGON_TIME is below '.self::MIN_ARGON_TIME;
        }
        if ((int) config('hashing.argon.threads') < 1) {
            $problems[] = 'ARGON_THREADS is below 1';
        }

        return $problems;
    }

    /**
     * Whether this process must pass `serviceProblems()` before serving:
     * every role but `ingest` (`WT_ROLE`), which serves only captures and
     * never reads the Identity keys or the common-password list, so the keys
     * need not be mounted there. Unknown or unset roles stay strict.
     */
    public static function servesIdentity(): bool
    {
        return config('identity.runtime_role') !== 'ingest';
    }

    /** @return list<string> missing keys and an unreadable common-password list */
    public static function serviceProblems(): array
    {
        $problems = [];
        foreach (IdentityKeys::problems() as $env) {
            $problems[] = "{$env} is missing or shorter than 32 bytes";
        }
        if (! PasswordPolicy::fromConfig()->listIsAvailable()) {
            $problems[] = 'the common-password list is missing or empty';
        }

        return $problems;
    }
}
