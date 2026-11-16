<?php

declare(strict_types=1);

namespace App\Modules\Instance\Application;

use App\Infrastructure\Cache\StoreFailures;
use App\Infrastructure\Cache\StoreProbe;
use App\Modules\Agent\Application\RunnerTokens;
use App\Modules\Identity\Application\SignInAlerts;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The deep check behind `/health` (compose-profile §6; hosted plan §5, §5.1,
 * §6.6, §6.8), for the external uptime monitor, not for Docker. Counts, ages
 * and booleans only: no secret, host value, key, address, email or user id.
 *
 * `ok` is false (HTTP 503) when the database or the store is unreachable,
 * the scheduler heartbeat is older than its limit, or the backup status file
 * exists and reports a failed or stale backup, a stale ledger ship, a
 * failed ledger verify, a WAL archive lag over 15 min or a stale lag measurement. The alert fields (`signin_*`, `store_failures_5m`)
 * never fail the check: the monitor alerts on their values.
 */
final readonly class Health
{
    public function __construct(
        private Heartbeats $heartbeats,
        private BackupStatus $backup,
        private StoreProbe $store,
        private StoreFailures $storeFailures,
        private SignInAlerts $signInAlerts,
        private RunnerTokens $runners,
    ) {}

    /** @return array<string, mixed> */
    public function report(): array
    {
        $c = config('instance.health');
        $database = $this->databaseReachable();
        $store = $this->store->reachable();
        $scheduler = $database ? $this->guard(fn (): ?int => $this->heartbeats->age(Heartbeats::SCHEDULER)) : null;
        $backup = $this->backup->read();
        $signIn = $this->signInAlerts->snapshot();

        $checks = [
            'database' => $database,
            'store' => $store,
            'scheduler' => $scheduler !== null && $scheduler <= (int) $c['scheduler_max_age'],
            'backup' => $this->backupOk($backup, $c),
        ];

        return [
            'ok' => ! in_array(false, $checks, true),
            'checks' => $checks,
            'scheduler_heartbeat_age' => $scheduler,
            ...$backup,
            'signin_failures_5m' => $signIn['failures'],
            'signin_throttled_5m' => $signIn['throttled'],
            'signin_alert_level' => $signIn['level'],
            'store_failures_5m' => $database ? $this->guard(fn (): array => $this->storeFailures->recent(5)) : null,
            'runner_last_poll_age' => $database ? $this->guard(fn (): ?int => $this->runners->lastSeenAge()) : null,
        ];
    }

    private function databaseReachable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array{backup_last_full_age: int|null, backup_last_full_ok: bool|null, backup_last_age: int|null, backup_last_ok: bool|null, ledger_last_ship_age: int|null, ledger_verify: bool|null, archive_lag_seconds: int|null, archive_lag_age: int|null}  $b
     * @param  array<string, mixed>  $c
     */
    private function backupOk(array $b, array $c): bool
    {
        $maxFullAge = (int) $c['backup_max_full_age'];
        $maxShipAge = (int) $c['ledger_max_ship_age'];
        if ($b['backup_last_full_ok'] === false || $b['backup_last_ok'] === false || $b['ledger_verify'] === false) {
            return false;
        }
        if ($b['backup_last_full_age'] !== null && $b['backup_last_full_age'] > $maxFullAge) {
            return false;
        }

        if ($b['ledger_last_ship_age'] !== null && $b['ledger_last_ship_age'] > $maxShipAge) {
            return false;
        }
        // WAL archive lag (hosted plan §5: alert at 15 min), measured once a
        // minute by `backup-loop`; a stale measurement fails too. Both fields
        // absent (an older backup image): unknown, not failed.
        if ($b['archive_lag_seconds'] !== null && $b['archive_lag_seconds'] > (int) $c['archive_max_lag']) {
            return false;
        }

        return $b['archive_lag_age'] === null || $b['archive_lag_age'] <= (int) $c['archive_lag_max_age'];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $read
     * @return T|null
     */
    private function guard(callable $read): mixed
    {
        try {
            return $read();
        } catch (Throwable) {
            return null;
        }
    }
}
