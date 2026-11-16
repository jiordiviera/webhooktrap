<?php

declare(strict_types=1);

namespace App\Modules\Admin\Widgets;

use App\Modules\Admin\Support\Paginates;
use App\Modules\Instance\Application\Health;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The `/health` report (Instance\Application\Health), as the uptime monitor
 * sees it: the four checks, the scheduler heartbeat, backup ages and the
 * WAL archive lag. No polling: an idle panel must time out.
 */
final class HealthStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Health';

    protected function getStats(): array
    {
        $report = app(Health::class)->report();
        $stats = [Stat::make('Overall', $report['ok'] === true ? 'OK' : 'Failing')->color($report['ok'] === true ? 'success' : 'danger')];
        foreach ((array) $report['checks'] as $name => $ok) {
            $stats[] = Stat::make(ucfirst((string) $name), $ok === true ? 'OK' : 'Failing')->color($ok === true ? 'success' : 'danger');
        }
        $stats[] = Stat::make('Scheduler heartbeat', Paginates::age(self::int($report['scheduler_heartbeat_age'])));
        $stats[] = Stat::make('Last full backup', Paginates::age(self::int($report['backup_last_full_age'])))
            ->description(self::flag($report['backup_last_full_ok']));
        $stats[] = Stat::make('Last backup', Paginates::age(self::int($report['backup_last_age'])))
            ->description(self::flag($report['backup_last_ok']));
        $stats[] = Stat::make('Ledger last ship', Paginates::age(self::int($report['ledger_last_ship_age'])))
            ->description('verify: '.self::flag($report['ledger_verify']));
        $stats[] = Stat::make('WAL archive lag', Paginates::age(self::int($report['archive_lag_seconds'])))
            ->description('measured '.Paginates::age(self::int($report['archive_lag_age'])).' ago');

        return $stats;
    }

    private static function int(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private static function flag(mixed $value): string
    {
        return match ($value) {
            true => 'ok',
            false => 'failed',
            default => 'unknown',
        };
    }
}
