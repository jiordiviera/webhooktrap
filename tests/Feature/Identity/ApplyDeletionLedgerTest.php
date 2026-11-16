<?php

declare(strict_types=1);

use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Domain\User;
use App\Modules\Retention\Application\DeletionLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
 * `accounts:apply-ledger` (DL-3c, restore runbook step 6, TM-T158): accounts
 * in the deletion ledger that exist again after a restore are purged with the
 * live ownership-tree purge, without mail; a second run applies nothing.
 */

function ledgerFile(array $lines): string
{
    $path = sys_get_temp_dir().'/wt-ledger-'.bin2hex(random_bytes(6)).'.jsonl';
    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}

function ledgerLine(User $user): string
{
    return json_encode(['subject' => $user->publicId(), 'deleted_at' => '2026-11-10T12:00:00.000000Z']);
}

it('purges a restored account listed in the ledger file, with its sessions, and is idempotent', function (): void {
    $gone = makeUser(['email' => 'restored@example.com']);
    signIn($gone);
    Mail::fake();
    $kept = makeUser();
    expect(DB::table('user_sessions')->where('user_id', $gone->id)->exists())->toBeTrue();
    $file = ledgerFile([ledgerLine($gone)]);

    $this->artisan('accounts:apply-ledger', ['--file' => $file])
        ->expectsOutputToContain('applied 1, remaining 0')
        ->assertExitCode(0);

    expect(User::query()->find($gone->id))->toBeNull()
        ->and(User::query()->find($kept->id))->not->toBeNull()
        ->and(DB::table('user_sessions')->where('user_id', $gone->id)->exists())->toBeFalse()
        ->and(app(DeletionLedger::class)->has($gone->publicId()))->toBeTrue();
    Mail::assertNothingOutgoing();

    $this->artisan('accounts:apply-ledger', ['--file' => $file])
        ->expectsOutputToContain('applied 0, remaining 0')
        ->assertExitCode(0);

    @unlink($file);
});

it('also re-applies entries of the restored deletion_ledger table', function (): void {
    $user = makeUser();
    app(DeletionLedger::class)->record($user->publicId(), now()->subDay());

    $this->artisan('accounts:apply-ledger', ['--allow-missing' => true])
        ->expectsOutputToContain('applied 1, remaining 0')
        ->assertExitCode(0);

    expect(User::query()->find($user->id))->toBeNull();
});

it('refuses without a ledger file unless --allow-missing is given', function (): void {
    $user = makeUser();
    app(DeletionLedger::class)->record($user->publicId(), now()->subDay());

    $this->artisan('accounts:apply-ledger')->assertExitCode(1);
    $this->artisan('accounts:apply-ledger', ['--file' => sys_get_temp_dir().'/wt-no-such-ledger.jsonl'])->assertExitCode(1);

    expect(User::query()->find($user->id))->not->toBeNull();
});

it('applies the readable lines but exits non-zero on malformed ones', function (): void {
    $user = makeUser();
    $file = ledgerFile([ledgerLine($user), '{"subject":"someone@example.com","deleted_at":"2026-11-10T12:00:00Z"}', 'not json']);

    $this->artisan('accounts:apply-ledger', ['--file' => $file])
        ->expectsOutputToContain('applied 1, remaining 0')
        ->assertExitCode(1);

    expect(User::query()->find($user->id))->toBeNull();
    @unlink($file);
});

it('re-applies a deletion the live purge wrote to the ledger file, after the row came back (TM-T158)', function (): void {
    $path = sys_get_temp_dir().'/wt-ledger-'.bin2hex(random_bytes(6)).'.jsonl';
    config(['retention.ledger_path' => $path]);
    $user = makeUser();
    $row = (array) DB::table('users')->where('id', $user->id)->first();
    $user->forceFill(['deleted_at' => now()])->save();

    app(AccountDeletion::class)->purgeDue();
    $line = json_decode((string) file_get_contents($path), true);
    expect($line)->toBe(['subject' => $user->publicId(), 'deleted_at' => $line['deleted_at']])
        ->and(User::query()->find($user->id))->toBeNull();

    // "Restore": the row is back and the table copy of the ledger predates the deletion.
    DB::table('users')->insert($row);
    DB::table('deletion_ledger')->delete();

    $this->artisan('accounts:apply-ledger')
        ->expectsOutputToContain('ledger 1, applied 1, remaining 0')
        ->assertExitCode(0);
    expect(User::query()->find($user->id))->toBeNull();

    @unlink($path);
});
