<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Domain;

use App\Infrastructure\Persistence\HasUuidV7;
use App\Modules\Identity\Contracts\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Exactly one owner: a user or a guest session (DB CHECK, R61; domain §3).
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $guest_session_id
 */
final class Workspace extends Model
{
    use HasUuidV7;

    public const string ID_PREFIX = 'wsp';

    protected $table = 'workspaces';

    protected $guarded = [];

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function isGuest(): bool
    {
        return $this->guest_session_id !== null;
    }

    /**
     * The one ownership check (domain §2 "Guards", TM-13). Only browser
     * principals pass: an agent or runner principal never does, whatever its
     * id (TM-20). The principal is live by construction (the `web` provider
     * skips tombstoned accounts; the `guest` guard skips ended, claimed or
     * expired sessions).
     */
    public function isOwnedBy(?Principal $principal): bool
    {
        if ($principal === null) {
            return false;
        }

        return match ($principal->kind) {
            'user' => $this->user_id !== null && $this->user_id === $principal->id,
            'guest' => $this->guest_session_id !== null && $this->guest_session_id === $principal->id,
            default => false,
        };
    }
}
