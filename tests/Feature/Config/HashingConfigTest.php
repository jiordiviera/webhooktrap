<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;

/*
 * argon2id only (domain-model §2.1, revision 6; compose-profile §3.1).
 */
it('hashes passwords with argon2id', function (): void {
    expect(config('hashing.driver'))->toBe('argon2id')
        ->and(Hash::make('correct horse battery staple'))->toStartWith('$argon2id$');
});

it('keeps argon2id whatever the environment says', function (): void {
    putenv('HASH_DRIVER=bcrypt');
    $_ENV['HASH_DRIVER'] = $_SERVER['HASH_DRIVER'] = 'bcrypt';

    try {
        $config = require config_path('hashing.php');
    } finally {
        putenv('HASH_DRIVER');
        unset($_ENV['HASH_DRIVER'], $_SERVER['HASH_DRIVER']);
    }

    expect($config['driver'])->toBe('argon2id');
});

it('reads the argon2id cost from the environment with the production defaults', function (): void {
    $source = file_get_contents(config_path('hashing.php'));

    expect($source)
        ->toContain("env('ARGON_MEMORY', 65536)")
        ->toContain("env('ARGON_TIME', 4)")
        ->toContain("env('ARGON_THREADS', 1)");
});
