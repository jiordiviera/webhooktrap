<?php

declare(strict_types=1);

namespace App\Modules\Admin\Widgets;

use App\Modules\Workspace\Application\Activation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Guest activation over the last 7 UTC days (issue #18, decision 79): URLs
 * created, first delivery, first verdict, claimed. Aggregate counts only.
 */
final class ActivationFunnel extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Guest activation (7 days, UTC)';

    protected function getStats(): array
    {
        $f = app(Activation::class)->summary(7);
        $urls = $f[Activation::PROJECT_CREATED]['guest'];
        $share = fn (int $n): string => $urls > 0 ? round($n * 100 / $urls).'% of URLs' : 'no URLs yet';
        $after = fn (?int $s): string => $s === null ? '' : ', avg '.self::duration($s).' after the URL';

        return [
            Stat::make('Guest URLs created', (string) $urls)
                ->description($f[Activation::PROJECT_CREATED]['user'].' projects by accounts'),
            Stat::make('First delivery', (string) $f[Activation::FIRST_CAPTURE]['guest'])
                ->description($share($f[Activation::FIRST_CAPTURE]['guest']).$after($f[Activation::FIRST_CAPTURE]['guestAvgSeconds'])),
            Stat::make('First verdict', (string) $f[Activation::FIRST_RUN]['guest'])
                ->description($share($f[Activation::FIRST_RUN]['guest']).$after($f[Activation::FIRST_RUN]['guestAvgSeconds'])),
            Stat::make('Claimed by an account', (string) $f[Activation::CLAIMED]['guest'])
                ->description($share($f[Activation::CLAIMED]['guest'])),
        ];
    }

    private static function duration(int $seconds): string
    {
        return match (true) {
            $seconds < 60 => $seconds.' s',
            $seconds < 3600 => intdiv($seconds, 60).' min',
            default => intdiv($seconds, 3600).' h '.intdiv($seconds % 3600, 60).' min',
        };
    }
}
