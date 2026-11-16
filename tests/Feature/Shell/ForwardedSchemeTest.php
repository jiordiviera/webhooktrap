<?php

declare(strict_types=1);

it('builds https URLs when the image proxy forwards https', function (): void {
    $this->get('/', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])
        ->assertOk()
        ->assertDontSee('http://localhost/build/', false);

    expect(request()->isSecure())->toBeTrue()
        ->and(request()->ip())->toBe('203.0.113.9');
});
