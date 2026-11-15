<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A09 slice 1 delta review (project/security/slice1-delta-review.md): D1 to D3.
 */

it('encrypts the Inertia history on every page, fixed in config (D1)', function (): void {
    expect(config('inertia.history.encrypt'))->toBeTrue();

    $user = makeUser();
    actAs($user);
    $this->get('/account/security')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Account/Security', false));
    $page = $this->get('/account/security', ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())]);
    expect($page->json('encryptHistory'))->toBeTrue();
});

it('sends Cache-Control: no-store on the security, two-step and confirm pages (D1)', function (): void {
    $user = makeUser();
    enableTotp($user);
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])->assertRedirect('/two-factor');
    expect((string) $this->get('/two-factor')->headers->get('Cache-Control'))->toContain('no-store');

    actAs($user);
    expect((string) $this->get('/account/security')->headers->get('Cache-Control'))->toContain('no-store')
        ->and((string) $this->get('/confirm-auth')->headers->get('Cache-Control'))->toContain('no-store')
        ->and((string) $this->get('/projects/new')->headers->get('Cache-Control'))->not->toContain('no-store');
});

it('clears the browser history on sign-out and on guest session end (D1)', function (): void {
    $user = makeUser();
    signIn($user);
    $this->post('/sign-out')->assertRedirect('/')->assertSessionHas('inertia.clear_history', true);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->component('welcome', false));
    expect(session()->has('inertia.clear_history'))->toBeFalse(); // consumed by the next page

    newBrowser();
    $response = $this->post('/new');
    $cookie = (string) $response->getCookie('__Host-wt_guest', false)?->getValue();
    newBrowser();
    $this->withUnencryptedCookie('__Host-wt_guest', $cookie)->post('/session/end')
        ->assertRedirect('/')->assertSessionHas('inertia.clear_history', true);
});

it('starts an OAuth connect with a POST only, answered with Inertia::location (D2)', function (): void {
    $user = makeUser();
    actAs($user);

    $this->get('/account/security/identities/github/connect')->assertStatus(405);
    $response = $this->post('/account/security/identities/github/connect', [], ['X-Inertia' => 'true']);
    $response->assertStatus(409);
    expect((string) $response->headers->get('X-Inertia-Location'))->toStartWith('https://github.com/')
        ->and(session('identity.oauth.purpose'))->toBe('link');
});

it('masks the account email on the two-step challenge (D3)', function (): void {
    $user = makeUser(['email' => 'maya@bakery.example']);
    enableTotp($user);
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD]);

    $this->get('/two-factor')->assertInertia(fn (Assert $page) => $page->component('TwoFactor/Challenge', false)
        ->where('email', 'm***@b***.example'));
});
