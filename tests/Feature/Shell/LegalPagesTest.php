<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\GuestSession;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Public legal pages linked from the Google and GitHub OAuth apps: open to
 * anyone, no guest workspace, no cookie beyond what the landing sets.
 */

/** @return list<string> */
function legalPageCookieNames(string $uri): array
{
    return collect(test()->get($uri)->headers->getCookies())->map->getName()->sort()->values()->all();
}

it('serves the privacy policy and the terms to an anonymous visitor', function (string $uri, string $component): void {
    $this->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));

    expect(GuestSession::query()->count())->toBe(0);
})->with([
    'privacy' => ['/privacy', 'Legal/Privacy'],
    'terms' => ['/terms', 'Legal/Terms'],
]);

it('sets no cookie beyond what the landing sets', function (string $uri): void {
    $landing = legalPageCookieNames('/');
    $legal = legalPageCookieNames($uri);

    expect($legal)->toBe($landing)
        ->and($legal)->not->toContain(config('identity.guest.cookie'));
})->with(['/privacy', '/terms']);

it('serves both pages to a signed-in user', function (string $uri): void {
    signIn(makeUser());

    $this->get($uri)->assertOk();
})->with(['/privacy', '/terms']);

it('names the routes for Wayfinder', function (): void {
    expect(route('privacy', absolute: false))->toBe('/privacy')
        ->and(route('terms', absolute: false))->toBe('/terms');
});
