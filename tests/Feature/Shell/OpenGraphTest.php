<?php

declare(strict_types=1);

it('serves Open Graph and X tags for the landing', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.rtrim((string) config('app.url'), '/').'/og/landing.png">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta property="og:title" content="Your webhook handler works. Once.">', false);
});

it('uses the page image for the legal pages and the default elsewhere', function (): void {
    $this->get('/privacy')->assertOk()->assertSee('/og/privacy.png', false);
    $this->get('/terms')->assertOk()->assertSee('/og/terms.png', false);
    $this->get('/sign-in')->assertOk()->assertSee('/og/default.png', false);
});

it('ships every image the tags point to', function (string $name): void {
    expect(public_path("og/{$name}.png"))->toBeFile();
})->with(['landing', 'privacy', 'terms', 'default']);
