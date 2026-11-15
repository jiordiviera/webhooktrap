<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * `web` guard provider that never returns a tombstoned or disabled account:
 * "a row with deleted_at set is never a live principal" (schema §2, DL-2).
 */
final class LiveUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $user = parent::retrieveById($identifier);

        return $user instanceof User && $user->isLive() ? $user : null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    /** @param  array<string, mixed>  $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $user = parent::retrieveByCredentials($credentials);

        return $user instanceof User && $user->isLive() ? $user : null;
    }
}
