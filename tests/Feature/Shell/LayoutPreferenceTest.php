<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Decision 59: the app layout is a per-device cookie, read server-side and
 * shared as the `layout` prop so the first paint has no layout flash.
 */

it('defaults to the top bar, expanded', function (): void {
    $this->get('/')->assertInertia(fn (Assert $p) => $p
        ->where('layout.shell', 'topbar')
        ->where('layout.sidebar', 'expanded'));
});

it('shares the layout from the plain cookies', function (): void {
    $this->withUnencryptedCookie('layout', 'sidebar')
        ->withUnencryptedCookie('layout_sidebar', 'collapsed')
        ->get('/')
        ->assertInertia(fn (Assert $p) => $p
            ->where('layout.shell', 'sidebar')
            ->where('layout.sidebar', 'collapsed'));
});

it('ignores unknown cookie values', function (): void {
    $this->withUnencryptedCookie('layout', 'floating')
        ->withUnencryptedCookie('layout_sidebar', 'tiny')
        ->get('/')
        ->assertInertia(fn (Assert $p) => $p
            ->where('layout.shell', 'topbar')
            ->where('layout.sidebar', 'expanded'));
});

it('serves the preferences page to a signed-in user only', function (): void {
    $this->get('/account/preferences')->assertRedirect('/sign-in');

    signIn(User::factory()->create());
    $this->get('/account/preferences')
        ->assertInertia(fn (Assert $p) => $p->component('Account/Preferences', shouldExist: false));
});
