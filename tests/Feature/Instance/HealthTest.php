<?php

declare(strict_types=1);

use App\Infrastructure\Cache\StoreFailures;
use App\Modules\Agent\Application\RunnerTokens;
use App\Modules\Instance\Application\Heartbeats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

/*
 * `/health` (compose-profile §6; hosted plan §5.1, §6.6, §6.8): counts, ages
 * and booleans for the uptime monitor; no secret, key, address, email or id.
 */

function backupStatusFile(array $data): string
{
    $path = sys_get_temp_dir().'/wt-backup-'.bin2hex(random_bytes(6)).'.json';
    file_put_contents($path, json_encode($data));
    config(['instance.health.backup_status_path' => $path]);

    return $path;
}

beforeEach(function (): void {
    config(['instance.health.backup_status_path' => sys_get_temp_dir().'/wt-no-backup-status.json']);
});

it('answers 200 with every field when the scheduler beats and no backup service exists', function (): void {
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);

    $this->getJson('/health')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('ok', true)
        ->assertJsonPath('checks', ['database' => true, 'store' => true, 'scheduler' => true, 'backup' => true])
        ->assertJsonPath('scheduler_heartbeat_age', 0)
        ->assertJsonPath('store_failures_5m', ['rate_limit' => 0, 'cache' => 0])
        ->assertJsonPath('signin_failures_5m', 0)
        ->assertJsonPath('signin_throttled_5m', 0)
        ->assertJsonPath('signin_alert_level', 'ok')
        ->assertJsonPath('runner_last_poll_age', null)
        ->assertJsonPath('backup_last_full_age', null)
        ->assertJsonPath('ledger_last_ship_age', null)
        ->assertJsonPath('ledger_verify', null)
        ->assertCookieMissing(config('session.cookie'));
});

it('answers 503 when the scheduler heartbeat is missing or stale', function (): void {
    $this->getJson('/health')->assertStatus(503)->assertJsonPath('checks.scheduler', false);

    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    $this->travel(121)->seconds();
    $this->getJson('/health')->assertStatus(503)
        ->assertJsonPath('scheduler_heartbeat_age', 121)
        ->assertJsonPath('checks.scheduler', false);
});

it('counts throttled wt.store_failure reports per kind over 5 minutes (store_failures_5m, §6.8)', function (): void {
    Exceptions::fake();
    config(['cache.failure_reports.store' => 'array']);
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    $failures = app(StoreFailures::class);

    $failures->report('rate_limit', new RuntimeException('down at 10.0.0.9 key wt:secret'));
    $failures->report('rate_limit', new RuntimeException('down'));
    $failures->report('cache', new RuntimeException('down'));
    $this->travel(61)->seconds();
    $failures->report('rate_limit', new RuntimeException('still down'));

    $response = $this->getJson('/health')->assertOk()
        ->assertJsonPath('store_failures_5m', ['rate_limit' => 3, 'cache' => 1]);
    expect($response->getContent())->not->toContain('10.0.0.9')->not->toContain('wt:secret');

    $this->travel(5)->minutes();
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    $this->getJson('/health')->assertJsonPath('store_failures_5m', ['rate_limit' => 0, 'cache' => 0]);
});

it('carries the failed sign-in counts and level, never the email or address (§6.6)', function (): void {
    config(['identity.signin_alert.warn_failures' => 3]);
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    $user = makeUser(['email' => 'canary-health@example.com']);
    foreach (range(1, 3) as $i) {
        $this->post('/sign-in/password', ['email' => $user->email, 'password' => 'wrong-password'], ['REMOTE_ADDR' => '198.51.100.77']);
    }

    $response = $this->getJson('/health')->assertOk()
        ->assertJsonPath('signin_failures_5m', 3)
        ->assertJsonPath('signin_alert_level', 'warn');
    expect($response->getContent())->not->toContain('canary-health')->not->toContain('198.51.100.77')
        ->not->toContain($user->publicId());
});

it('reads backup and ledger ages from the backup status file and fails on a failed or stale backup', function (): void {
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    $now = now()->getTimestamp();
    $path = backupStatusFile([
        'last_full_at' => $now - 3600, 'last_full_ok' => true,
        'last_backup_at' => $now - 600, 'last_backup_ok' => true,
        'ledger_last_ship_at' => $now - 30, 'ledger_verify_at' => $now - 86400, 'ledger_verify_ok' => true,
    ]);

    $this->getJson('/health')->assertOk()
        ->assertJsonPath('backup_last_full_age', 3600)
        ->assertJsonPath('backup_last_full_ok', true)
        ->assertJsonPath('backup_last_age', 600)
        ->assertJsonPath('ledger_last_ship_age', 30)
        ->assertJsonPath('ledger_verify', true)
        ->assertJsonPath('checks.backup', true);

    backupStatusFile(['last_full_at' => $now - 3600, 'last_full_ok' => true, 'ledger_verify_ok' => false]);
    $this->getJson('/health')->assertStatus(503)->assertJsonPath('checks.backup', false);

    backupStatusFile(['last_full_at' => $now - 27 * 3600, 'last_full_ok' => true]);
    $this->getJson('/health')->assertStatus(503)->assertJsonPath('checks.backup', false);

    backupStatusFile(['last_full_at' => $now - 3600, 'last_full_ok' => true, 'ledger_last_ship_at' => $now - 901]);
    $this->getJson('/health')->assertStatus(503)->assertJsonPath('checks.backup', false);

    @unlink($path);
});

it('reports the age of the most recent runner poll', function (): void {
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    config(['identity.instance' => 'selfhost']);
    $issued = app(RunnerTokens::class)->issue('runner', []);
    DB::table('runners')->where('name', $issued['name'])->update(['last_seen_at' => now()->subSeconds(42)]);

    $response = $this->getJson('/health')->assertOk()->assertJsonPath('runner_last_poll_age', 42);
    expect($response->getContent())->not->toContain($issued['token']);
});

it('exposes the WAL archive lag and fails on a lag over 15 min or a stale measurement', function (): void {
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    $now = now()->getTimestamp();
    $base = ['last_full_at' => $now - 3600, 'last_full_ok' => true];

    $path = backupStatusFile($base + ['archive_lag_seconds' => 0, 'archive_lag_at' => $now - 30]);
    $this->getJson('/health')->assertOk()
        ->assertJsonPath('archive_lag_seconds', 0)
        ->assertJsonPath('archive_lag_age', 30)
        ->assertJsonPath('checks.backup', true);

    backupStatusFile($base + ['archive_lag_seconds' => 900, 'archive_lag_at' => $now - 30]);
    $this->getJson('/health')->assertOk()->assertJsonPath('archive_lag_seconds', 900);

    backupStatusFile($base + ['archive_lag_seconds' => 901, 'archive_lag_at' => $now - 30]);
    $this->getJson('/health')->assertStatus(503)
        ->assertJsonPath('archive_lag_seconds', 901)
        ->assertJsonPath('checks.backup', false);

    backupStatusFile($base + ['archive_lag_seconds' => 0, 'archive_lag_at' => $now - 301]);
    $this->getJson('/health')->assertStatus(503)->assertJsonPath('checks.backup', false);

    @unlink($path);
});

it('treats a missing archive lag (an older backup image) as unknown, not failed', function (): void {
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    backupStatusFile(['last_full_at' => now()->getTimestamp() - 3600, 'last_full_ok' => true]);

    $this->getJson('/health')->assertOk()
        ->assertJsonPath('archive_lag_seconds', null)
        ->assertJsonPath('archive_lag_age', null)
        ->assertJsonPath('checks.backup', true);
});
