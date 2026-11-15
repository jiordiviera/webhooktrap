<?php

declare(strict_types=1);

/*
 * Decision 50 (ADR-004 amendment 1): Reverb refuses client events, set
 * explicitly and not overridable from the environment (TM-T160, proposed).
 */
it('refuses Reverb client events', function (): void {
    foreach (config('reverb.apps.apps') as $app) {
        expect($app['accept_client_events_from'])->toBe('none');
    }
});

it('keeps client events refused whatever the environment says', function (): void {
    foreach (['REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'REVERB_ACCEPT_CLIENT_EVENTS_FROM'] as $name) {
        putenv("{$name}=all");
        $_ENV[$name] = $_SERVER[$name] = 'all';
    }

    try {
        $config = require config_path('reverb.php');
    } finally {
        foreach (['REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'REVERB_ACCEPT_CLIENT_EVENTS_FROM'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    foreach ($config['apps']['apps'] as $app) {
        expect($app['accept_client_events_from'])->toBe('none');
    }
});

it('broadcasts through Reverb by default', function (): void {
    $config = require config_path('broadcasting.php');

    expect($config['connections'])->toHaveKey('reverb');
});
