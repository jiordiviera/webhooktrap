<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Packs\Application\PackCatalog;
use Illuminate\Support\ServiceProvider;

/**
 * Packs wiring: one read-only catalog of the files in `packs/` per container
 * (domain §8). Read-only data, so sharing it across worker requests is safe.
 */
final class PacksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PackCatalog::class, fn (): PackCatalog => new PackCatalog((string) config('packs.path')));
    }
}
