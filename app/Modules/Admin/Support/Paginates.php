<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Turns a read model's page (`rows` + `total`) into what Filament's
 * custom-data tables take: records keyed by their external id.
 */
final class Paginates
{
    /**
     * @param  array{rows: list<array<string, mixed>>, total: int}  $result
     * @return LengthAwarePaginator<string, array<string, mixed>>
     */
    public static function page(array $result, int $page, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect($result['rows'])->keyBy('id'),
            total: $result['total'],
            perPage: $perPage,
            currentPage: $page,
        );
    }

    public static function bytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) $bytes : number_format($value, 1)).' '.$units[$i];
    }

    public static function age(?int $seconds): string
    {
        if ($seconds === null) {
            return 'unknown';
        }

        return match (true) {
            $seconds < 120 => $seconds.' s',
            $seconds < 7200 => intdiv($seconds, 60).' min',
            $seconds < 172800 => intdiv($seconds, 3600).' h',
            default => intdiv($seconds, 86400).' d',
        };
    }
}
