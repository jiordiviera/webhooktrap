<?php

declare(strict_types=1);

namespace App\Modules\Admin\Widgets;

use App\Modules\Agent\Application\OperatorFleet;
use App\Modules\Identity\Application\OperatorDirectory;
use App\Modules\Proving\Application\RunActivity;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Live activity: open runs, connected agents, polling runners, guest sessions. */
final class ActivityStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Activity';

    protected function getStats(): array
    {
        $runs = app(RunActivity::class)->active();
        $fleet = app(OperatorFleet::class)->counts();
        $guests = app(OperatorDirectory::class)->guestSessionCounts();

        return [
            Stat::make('Active runs', (string) ($runs['queued'] + $runs['running']))
                ->description($runs['running'].' running, '.$runs['queued'].' queued'),
            Stat::make('Connected agents', (string) $fleet['agents_connected'])
                ->description($fleet['agents_paired'].' paired'),
            Stat::make('Runners polling (5 min)', (string) $fleet['runners_polling'])
                ->description($fleet['runners_live'].' live tokens'),
            Stat::make('Live guest sessions', (string) $guests['live'])
                ->description($guests['started_24h'].' started, '.$guests['claimed_24h'].' claimed in 24 h'),
        ];
    }
}
