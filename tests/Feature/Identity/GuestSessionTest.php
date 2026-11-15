<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\GuestSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function startGuest(): array
{
    $response = test()->post('/new');
    $cookie = $response->getCookie('__Host-wt_guest', false);
    $key = (string) str($response->headers->get('Location'))->afterLast('/p/');

    return [$cookie, $key, $response];
}

it('creates a guest session, workspace and project, with a __Host- cookie (TM-T10)', function (): void {
    [$cookie, $key, $response] = startGuest();

    $response->assertRedirect('/p/'.$key);
    expect($key)->toMatch('/^[A-Za-z0-9]{10}$/')
        ->and($cookie->getName())->toBe('__Host-wt_guest')
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getPath())->toBe('/')
        ->and($cookie->getDomain())->toBeNull()
        ->and(strlen((string) $cookie->getValue()))->toBeGreaterThanOrEqual(43);

    $guest = GuestSession::query()->sole();
    expect($guest->expires_at->diffInHours($guest->created_at, true))->toEqual(24)
        ->and($guest->token_hash)->toBe(hash('sha256', (string) $cookie->getValue(), true))
        ->and(DB::table('workspaces')->where('guest_session_id', $guest->id)->count())->toBe(1);

    $this->withUnencryptedCookie('__Host-wt_guest', (string) $cookie->getValue())
        ->get('/p/'.$key)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Capture/Stream')
            ->where('principal.kind', 'guest')
            ->where('meter.captures.limit', 100)
            ->where('meter.runs.limit', 10));
});

it('never replaces a live session: POST /new with a live cookie returns the existing project (TM-T11)', function (): void {
    [$cookie, $key] = startGuest();

    $this->withUnencryptedCookie('__Host-wt_guest', (string) $cookie->getValue())
        ->post('/new')
        ->assertRedirect('/p/'.$key);

    expect(GuestSession::query()->count())->toBe(1);
});

it('keeps POST /new behind the web group CSRF check (TM-T11)', function (): void {
    $route = Route::getRoutes()->match(request()->create('/new', 'POST'));

    expect($route->gatherMiddleware())->toContain('web')
        ->and($route->excludedMiddleware())->toBe([]);
});

it('ignores a token in the URL or a header (TM-T10)', function (): void {
    [$cookie, $key] = startGuest();
    $token = (string) $cookie->getValue();

    $this->get('/p/'.$key.'?token='.$token.'&__Host-wt_guest='.$token)->assertNotFound();
    $this->withHeaders(['X-Guest-Token' => $token, 'Authorization' => 'Bearer '.$token])->get('/p/'.$key)->assertNotFound();
});

it('answers not found to anyone but the owner (TM-T13)', function (): void {
    [, $key] = startGuest();
    [$other] = startGuest();

    $this->get('/p/'.$key)->assertNotFound();
    $this->withUnencryptedCookie('__Host-wt_guest', (string) $other->getValue())->get('/p/'.$key)->assertNotFound();
    signIn(makeUser());
    $this->get('/p/'.$key)->assertNotFound();
    $this->get('/p/AAAAAAAAAA')->assertNotFound();
});

it('expires after 24 h and renders /expired with nothing from the session (R59)', function (): void {
    [$cookie, $key] = startGuest();
    $this->travel(24)->hours();

    $this->withUnencryptedCookie('__Host-wt_guest', (string) $cookie->getValue())
        ->get('/p/'.$key)->assertRedirect('/expired');
    $this->withUnencryptedCookie('__Host-wt_guest', (string) $cookie->getValue())
        ->get('/expired')
        ->assertInertia(fn (Assert $page) => $page->component('Expired')
            ->where('principal.kind', 'anonymous')
            ->missing('meter')->missing('project'));
});

it('ends the session now and deletes its data', function (): void {
    [$cookie, $key] = startGuest();

    $response = $this->withUnencryptedCookie('__Host-wt_guest', (string) $cookie->getValue())->post('/session/end');

    $response->assertRedirect('/');
    expect(GuestSession::query()->sole()->ended_at)->not->toBeNull()
        ->and(DB::table('workspaces')->count())->toBe(0)
        ->and($response->getCookie('__Host-wt_guest', false)?->getExpiresTime())->toBeLessThan(time());
    $this->withUnencryptedCookie('__Host-wt_guest', (string) $cookie->getValue())->get('/p/'.$key)->assertNotFound();
});

it('limits guest creation per address', function (): void {
    config(['identity.guest.create_per_address_per_hour' => 2]);
    $this->post('/new')->assertRedirect();
    $this->post('/new')->assertRedirect();
    $this->post('/new')->assertSessionHasErrors(['form' => 'throttled']);
    expect(GuestSession::query()->count())->toBe(2);
});

it('refuses POST /new when guest mode is off', function (): void {
    config(['identity.guest_mode' => false]);
    $this->post('/new')->assertSessionHasErrors(['form' => 'guest_mode_off']);
});
