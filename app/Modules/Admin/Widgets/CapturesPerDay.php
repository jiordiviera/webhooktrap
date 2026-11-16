<?php

declare(strict_types=1);

namespace App\Modules\Admin\Widgets;

use App\Modules\Capture\Application\CaptureVolume;
use Filament\Widgets\ChartWidget;

/** Stored captures per UTC day over the last 14 days (purged captures no longer count). */
final class CapturesPerDay extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Captures per day (UTC, 14 days)';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = app(CaptureVolume::class)->perDay(14);

        return [
            'datasets' => [['label' => 'Captures', 'data' => array_values($days)]],
            'labels' => array_map(fn (string $d): string => substr($d, 5), array_keys($days)),
        ];
    }
}
