<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The starter kit's /dashboard placeholder is gone: the signed-in home is
 * /projects. Old bookmarks redirect, and nothing in the frontend links there.
 */

it('redirects /dashboard to the projects index for a signed-in user', function (): void {
    signIn(User::factory()->create());

    $this->get('/dashboard')->assertStatus(301)->assertRedirect('/projects');
});

it('redirects the starter appearance page to account preferences', function (): void {
    signIn(User::factory()->create());

    $this->get('/settings/appearance')->assertStatus(301)->assertRedirect('/account/preferences');
});

it('no longer names a dashboard route', function (): void {
    expect(Route::has('dashboard'))->toBeFalse()
        ->and(Route::has('appearance.edit'))->toBeFalse();
});

it('renders the landing for a signed-in user without a dashboard link', function (): void {
    signIn(User::factory()->create());

    $this->get('/')
        ->assertOk()
        ->assertDontSee('/dashboard')
        ->assertInertia(fn (Assert $p) => $p->component('welcome', shouldExist: false));
});

it('has no frontend source that links to /dashboard', function (): void {
    $root = resource_path('js');
    $generated = ['actions', 'routes', 'wayfinder'];
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if (in_array(explode(DIRECTORY_SEPARATOR, $relative)[0], $generated, true)) {
            continue;
        }
        if (! in_array($file->getExtension(), ['ts', 'tsx'], true)) {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (preg_match("~['\"`]/dashboard\\b|\\bdashboard\\(\\)~", $source) === 1) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
});
