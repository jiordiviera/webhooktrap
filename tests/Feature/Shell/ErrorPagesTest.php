<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * S-18 system pages (QA F-06): HTML and Inertia requests get the branded
 * `Error` page with the status; JSON requests keep the plain JSON answer;
 * ingest on `/c/…` is never touched.
 */

function inertiaHeaders(): array
{
    return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];
}

beforeEach(function (): void {
    foreach ([403, 419, 429, 500, 503] as $status) {
        Route::middleware('web')->get("/__test/error/{$status}", fn () => throw new HttpException($status));
    }
});

it('renders the branded page for an unknown URL, a status only', function (): void {
    $this->get('/no/such/page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error', false)->where('status', 404));
});

it('renders the branded page for each system status', function (int $status): void {
    config(['app.debug' => false]);

    $this->get("/__test/error/{$status}")
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->component('Error', false)->where('status', $status));
})->with([403, 419, 429, 500, 503]);

it('answers an Inertia visit with the page as JSON, keeping the status', function (): void {
    $response = $this->withHeaders(inertiaHeaders())->get('/__test/error/403');

    $response->assertStatus(403)->assertHeader('X-Inertia', 'true');
    expect($response->json('component'))->toBe('Error')
        ->and($response->json('props.status'))->toBe(403);
});

it('keeps the plain JSON answer for JSON requests', function (): void {
    $response = $this->getJson('/no/such/page');

    $response->assertNotFound();
    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and((string) $response->getContent())->not->toContain('"component"');
});

it('leaves the debug page for a server error while debugging', function (): void {
    config(['app.debug' => true]);

    $response = $this->get('/__test/error/500');

    $response->assertStatus(500);
    expect((string) $response->getContent())->not->toContain('"component":"Error"');
});

it('never renders the page on the ingest path', function (): void {
    foreach (['get', 'post', 'put', 'delete'] as $method) {
        $response = $this->{$method}('/c/AAAAAAAAAAAA');

        expect((string) $response->getContent())->not->toContain('"component":"Error"');
    }
});
