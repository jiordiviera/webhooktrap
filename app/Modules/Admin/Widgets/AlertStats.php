<?php

declare(strict_types=1);

namespace App\Modules\Admin\Widgets;

use App\Modules\Admin\Support\Paginates;
use App\Modules\Instance\Application\Health;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Alert fields of `/health`: sign-in failures over 5 minutes, the alert level, store failures, runner polls. */
final class AlertStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Alerts';

    protected function getStats(): array
    {
        $report = app(Health::class)->report();
        $level = $report['signin_alert_level'];
        $store = is_array($report['store_failures_5m']) ? $report['store_failures_5m'] : null;

        return [
            Stat::make('Sign-in alert level', is_string($level) ? strtoupper($level) : 'unknown')
                ->color(match ($level) {
                    'ok' => 'success', 'warn' => 'warning', 'crit' => 'danger', default => 'gray',
                }),
            Stat::make('Failed sign-ins (5 min)', self::count($report['signin_failures_5m'])),
            Stat::make('Throttled answers (5 min)', self::count($report['signin_throttled_5m'])),
            Stat::make('Store failures (5 min)', $store === null ? 'unknown' : (string) array_sum(array_map('intval', $store)))
                ->description($store === null || $store === [] ? null : implode(', ', array_map(
                    fn (string $kind, mixed $n): string => $kind.': '.(int) $n,
                    array_keys($store),
                    $store,
                ))),
            Stat::make('Last runner poll', Paginates::age(is_int($report['runner_last_poll_age']) ? $report['runner_last_poll_age'] : null)),
        ];
    }

    private static function count(mixed $value): string
    {
        return is_int($value) ? (string) $value : 'unknown';
    }
}
