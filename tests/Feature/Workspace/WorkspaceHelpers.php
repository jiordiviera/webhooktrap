<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Principal;
use App\Modules\Identity\Domain\User;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Contracts\OwnedProject;

/*
 * Slice 2 helpers. Loaded once by the workspace test files.
 */

function userProject(User $user, string $name = 'Brindle Coffee'): OwnedProject
{
    return app(Projects::class)->create(new Principal('user', $user->id), $name);
}

/** Starts a guest session through `POST /new`; returns [cookie value, project key]. */
function guestProject(): array
{
    $response = test()->post('/new');
    $cookie = (string) $response->getCookie('__Host-wt_guest', false)?->getValue();
    $key = (string) str((string) $response->headers->get('Location'))->afterLast('/p/');
    newBrowser();

    return [$cookie, $key];
}

/** As the guest whose cookie this is (a fresh browser otherwise). */
function asGuest(string $cookie): mixed
{
    return test()->withUnencryptedCookie('__Host-wt_guest', $cookie);
}
