<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use Symfony\Component\Finder\Finder;

/* `operator:grant` / `operator:revoke` (decision 74): the only way to set the flag. */

it('grants and revokes operator access by email', function (): void {
    $user = makeUser(['email' => 'ops@example.com']);
    addKey($user);

    test()->artisan('operator:grant', ['email' => 'OPS@example.com'])->expectsOutput('Operator access granted.')->assertSuccessful();
    expect($user->refresh()->is_operator)->toBeTrue()->and(auditCount('operator_granted', $user))->toBe(1);

    test()->artisan('operator:grant', ['email' => 'ops@example.com'])->expectsOutput('Already an operator.')->assertSuccessful();

    test()->artisan('operator:revoke', ['email' => 'ops@example.com'])->expectsOutput('Operator access revoked.')->assertSuccessful();
    expect($user->refresh()->is_operator)->toBeFalse()->and(auditCount('operator_revoked', $user))->toBe(1);
});

it('warns when the account cannot enter the panel yet', function (): void {
    makeUser(['email' => 'ops@example.com']);

    test()->artisan('operator:grant', ['email' => 'ops@example.com'])
        ->expectsOutputToContain('stays closed')->assertSuccessful();
});

it('fails for an unknown address', function (string $command): void {
    test()->artisan($command, ['email' => 'nobody@example.com'])->assertFailed();
})->with(['operator:grant', 'operator:revoke']);

it('never sets the flag through mass assignment', function (): void {
    $user = makeUser();
    $user->fill(['is_operator' => true, 'name' => 'Maya'])->save();
    expect($user->refresh()->is_operator)->toBeFalse();

    $created = User::query()->create(['email' => 'new@example.com', 'webauthn_user_handle' => random_bytes(32), 'is_operator' => true]);
    expect($created->refresh()->is_operator)->toBeFalse();
});

it('exposes no HTTP route that can set the flag', function (): void {
    $user = makeUser();
    actAs($user, 'passkey');

    foreach (['/account/preferences', '/account/security'] as $path) {
        test()->patch('https://webhooktrap.test'.$path, ['is_operator' => true]);
    }
    expect($user->refresh()->is_operator)->toBeFalse();

    // Source scan: only the console service writes the flag.
    $files = collect(Finder::create()->files()->name('*.php')->in(base_path('app'))->contains('is_operator'))
        ->map(fn ($f) => $f->getRelativePathname())->sort()->values()->all();
    expect($files)->toBe([
        'Modules/Identity/Application/OperatorAccess.php',
        'Modules/Identity/Application/OperatorDirectory.php',
        'Modules/Identity/Domain/User.php',
    ]);
    expect((string) file_get_contents(base_path('app/Modules/Identity/Application/OperatorDirectory.php')))->not->toMatch('/forceFill|update\(|insert\(/');
});
