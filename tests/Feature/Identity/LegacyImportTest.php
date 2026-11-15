<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Events\LegacyAccountImported;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;

/*
 * The 1.x import uses fixtures only; the real export is never read in tests.
 */

function fixtureExport(?string $override = null): string
{
    $dir = sys_get_temp_dir().'/wt-legacy-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $json = $override ?? (string) file_get_contents(base_path('tests/Fixtures/legacy-1x/users.json'));
    file_put_contents($dir.'/users.json', $json);
    file_put_contents($dir.'/SHA256SUMS', hash('sha256', $json).'  users.json'."\n");

    return $dir.'/users.json';
}

it('imports unverified accounts with no password, skips existing emails, prints counts only', function (): void {
    Event::fake([LegacyAccountImported::class]);
    makeUser(['email' => 'taken@example.com']);

    $this->artisan('webhooktrap:import-1x', ['path' => fixtureExport()])
        ->expectsOutput('Imported: 2. Skipped (email exists): 1. Invalid: 1.')
        ->doesntExpectOutputToContain('@')
        ->assertSuccessful();

    $first = User::query()->where('email', 'first@example.com')->sole();
    expect($first->password_hash)->toBeNull()
        ->and($first->email_verified_at)->toBeNull()
        ->and($first->legacy_source)->toBe('1x')
        ->and($first->created_at->toDateString())->toBe('2025-03-01')
        ->and($first->hasSecondFactor())->toBeFalse()
        ->and(User::query()->where('email', 'second@example.com')->exists())->toBeTrue()
        ->and(auditCount('legacy.imported'))->toBe(2);
    Event::assertDispatchedTimes(LegacyAccountImported::class, 2);
});

it('imports identically whatever the hash fields hold (PO-H option B)', function (): void {
    $base = json_decode((string) file_get_contents(base_path('tests/Fixtures/legacy-1x/users.json')), true);
    $mangled = array_map(fn (array $u): array => ['password' => ['broken' => '"}]'], 'passwordHash' => 42] + $u, $base);

    $this->artisan('webhooktrap:import-1x', ['path' => fixtureExport(json_encode($mangled))])
        ->expectsOutput('Imported: 3. Skipped (email exists): 0. Invalid: 1.')->assertSuccessful();
    expect(User::query()->whereNotNull('password_hash')->count())->toBe(0);
});

it('refuses an export that does not match its SHA256SUMS, and writes nothing on --dry-run', function (): void {
    $path = fixtureExport();
    file_put_contents(dirname($path).'/SHA256SUMS', str_repeat('0', 64).'  users.json');
    $this->artisan('webhooktrap:import-1x', ['path' => $path])->assertFailed();

    $this->artisan('webhooktrap:import-1x', ['path' => fixtureExport(), '--dry-run' => true])
        ->expectsOutput('Dry run. Imported: 3. Skipped (email exists): 0. Invalid: 1.')->assertSuccessful();
    expect(User::query()->count())->toBe(0);
});

it('keeps a password off an imported account until its email is proven (schema CHECK)', function (): void {
    $user = makeUser(['password_hash' => null, 'email_verified_at' => null, 'legacy_source' => '1x']);
    expect(fn () => $user->forceFill(['password_hash' => 'x'])->save())->toThrow(QueryException::class);
});

it('cannot start from a passkey: an imported account is not a sign-in target until activated', function (): void {
    $user = makeUser(['password_hash' => null, 'email_verified_at' => null, 'legacy_source' => '1x']);
    $this->post('/sign-in/password', ['email' => $user->email, 'password' => 'anything at all'])->assertSessionHasErrors(['form' => 'invalid_credentials']);
});
