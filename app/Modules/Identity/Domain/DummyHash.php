<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Uniform hash cost (TH-1c, TM-T160): an unknown email or an account with no
 * password runs the same argon2id verification as a wrong password, against
 * a fixed dummy hash made with the current parameters.
 */
final class DummyHash
{
    public static function burn(string $password): void
    {
        $params = (array) config('hashing.argon');
        $hash = Cache::rememberForever(
            'identity:dummy-hash:'.hash('sha256', (string) json_encode($params)),
            fn (): string => Hash::make(Str::random(40)),
        );
        Hash::check($password, $hash);
    }

    /** Verify against the real hash when there is one, else burn the same cost. */
    public static function verify(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            self::burn($password);

            return false;
        }

        return Hash::check($password, $hash);
    }
}
