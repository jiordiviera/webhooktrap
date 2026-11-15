<?php

declare(strict_types=1);

require_once __DIR__.'/Modules.php';

/*
 * domain-model.md §1.2, rules enforced from the foundation onwards.
 */

arch('every module and adapter folder exists')
    ->expect(fn () => array_map(
        fn (string $module): bool => is_dir(dirname(__DIR__, 2)."/app/Modules/{$module}"),
        webhooktrapModules(),
    ))
    ->not->toContain(false);

// Rules 1 and 2: modules never reach into another module's private layers,
// so models, controllers and jobs never cross a module boundary.
foreach (webhooktrapModules() as $module) {
    $forbidden = [];
    foreach (webhooktrapModules() as $other) {
        if ($other === $module) {
            continue;
        }
        foreach (webhooktrapPrivateLayers() as $layer) {
            $forbidden[] = "App\\Modules\\{$other}\\{$layer}";
        }
    }

    arch("{$module} does not use another module's models or internals")
        ->expect("App\\Modules\\{$module}")
        ->not->toUse($forbidden);
}

// Eloquent models live in a module's private Domain layer (read-only models
// a module exposes on purpose may also live in its Contracts).
foreach (webhooktrapModules() as $module) {
    arch("{$module} keeps Eloquent models out of its public and HTTP layers")
        ->expect([
            "App\\Modules\\{$module}\\Application",
            "App\\Modules\\{$module}\\Events",
            "App\\Modules\\{$module}\\Http",
        ])
        ->not->toExtend('Illuminate\Database\Eloquent\Model');
}

// Infrastructure adapters hold no business rules: they never depend on modules.
arch('infrastructure does not depend on modules')
    ->expect('App\Infrastructure')
    ->not->toUse('App\Modules');

// Rule 3 (decision 32 §4, HD-8): only Infrastructure/Queue, Cache and RateLimit
// may talk to the Redis-compatible store directly. Valkey and Redis are both
// reached through the same client, so nothing depends on which one runs.
arch('Redis facade and clients are only used by the Queue, Cache and RateLimit adapters')
    ->expect([
        'Illuminate\Support\Facades\Redis',
        'Illuminate\Redis',
        'Illuminate\Contracts\Redis',
        'Redis',
        'RedisCluster',
        'Predis',
    ])
    ->toOnlyBeUsedIn([
        'App\Infrastructure\Queue',
        'App\Infrastructure\Cache',
        'App\Infrastructure\RateLimit',
    ]);

// Rule 4 (D-10): the current time comes from the Clock adapter only.
arch('time helpers are only used by the Clock adapter')
    ->expect('App')
    ->not->toUse(['now', 'today', 'time', 'microtime', 'hrtime', 'date', 'Illuminate\Support\Facades\Date'])
    ->ignoring(['App\Infrastructure\Clock', 'App\Providers\AppServiceProvider']);

arch('PHP code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();
