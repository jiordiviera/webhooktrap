<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * A module's part of account deletion (domain §2.10 "Cascade per module").
 * Identity never reads other modules' tables; each module that holds data of
 * a user implements this and is tagged `identity.deletion-participants`
 * (A09 slice 1 review F13: the cascade lands in the same transactions).
 */
interface AccountDeletionParticipant
{
    /** Phase 1, inside the tombstone transaction, with the `users` row locked. */
    public function tombstone(string $userId): void;

    /**
     * Phase 2, inside the purge transaction, after the ledger entry is durable
     * and before the identity tree and the `users` row are deleted.
     */
    public function purge(string $userId): void;

    /**
     * What the deletion screen lists for this module (AccountDeletionView),
     * e.g. `['projects' => [['name' => 'Brindle Coffee']]]`.
     *
     * @return array<string, mixed>
     */
    public function summary(string $userId): array;
}
