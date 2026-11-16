<?php

declare(strict_types=1);

namespace App\Modules\Instance\Application;

use App\Infrastructure\Clock\Clock;

/**
 * The `backup` service's status file (`/status/backup.json`, written by
 * `wt-backup`: unix timestamps, booleans and the WAL archive lag in seconds)
 * as ages for `/health`. Missing or unreadable file, or a field an older
 * backup image does not write: null (unknown).
 */
final readonly class BackupStatus
{
    public function __construct(private Clock $clock) {}

    /**
     * @return array{backup_last_full_age: int|null, backup_last_full_ok: bool|null, backup_last_age: int|null, backup_last_ok: bool|null, ledger_last_ship_age: int|null, ledger_verify: bool|null, archive_lag_seconds: int|null, archive_lag_age: int|null}
     */
    public function read(): array
    {
        $data = $this->load();

        return [
            'backup_last_full_age' => $this->age($data['last_full_at'] ?? null),
            'backup_last_full_ok' => $this->flag($data['last_full_ok'] ?? null),
            'backup_last_age' => $this->age($data['last_backup_at'] ?? null),
            'backup_last_ok' => $this->flag($data['last_backup_ok'] ?? null),
            'ledger_last_ship_age' => $this->age($data['ledger_last_ship_at'] ?? null),
            'ledger_verify' => $this->flag($data['ledger_verify_ok'] ?? null),
            'archive_lag_seconds' => is_int($lag = $data['archive_lag_seconds'] ?? null) && $lag >= 0 ? $lag : null,
            'archive_lag_age' => $this->age($data['archive_lag_at'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        $path = config('instance.health.backup_status_path');
        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
            return [];
        }
        $raw = @file_get_contents($path, length: 4096);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }

    private function age(mixed $at): ?int
    {
        if (! is_int($at) || $at <= 0) {
            return null;
        }

        return max(0, $this->clock->now()->getTimestamp() - $at);
    }

    private function flag(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
