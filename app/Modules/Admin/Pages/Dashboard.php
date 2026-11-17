<?php

declare(strict_types=1);

namespace App\Modules\Admin\Pages;

use App\Modules\Admin\Widgets\ActivationFunnel;
use App\Modules\Admin\Widgets\ActivityStats;
use App\Modules\Admin\Widgets\AlertStats;
use App\Modules\Admin\Widgets\CapturesPerDay;
use App\Modules\Admin\Widgets\HealthStats;
use Filament\Pages\Dashboard as BaseDashboard;

/** Operations dashboard (decision 74): `/health` checks, activity, backups and alerts. */
final class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return 'Operations';
    }

    public function getWidgets(): array
    {
        return [HealthStats::class, AlertStats::class, ActivityStats::class, ActivationFunnel::class, CapturesPerDay::class];
    }

    public function getColumns(): int
    {
        return 1;
    }
}
