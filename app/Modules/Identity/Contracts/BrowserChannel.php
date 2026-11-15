<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * A private browser channel family (event-catalogue §1): `private-{name}.{uuid}`.
 * The module that owns the entity implements it and tags it
 * `identity.browser-channels`; `POST /broadcasting/auth` (Identity) checks the
 * principal type, then asks the matching implementation. Agents never reach
 * browser channels (rule 2): the endpoint only knows `user` and `guest`.
 */
interface BrowserChannel
{
    /** The family name, e.g. `project` for `private-project.{projectId}`. */
    public function name(): string;

    /** Whether this principal may subscribe to the channel of this entity uuid. */
    public function allows(Principal $principal, string $id): bool;
}
