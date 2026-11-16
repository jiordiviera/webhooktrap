<?php

declare(strict_types=1);

use App\Providers\AdminPanelProvider;
use App\Providers\AgentServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CaptureServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\IdentityServiceProvider;
use App\Providers\InstanceServiceProvider;
use App\Providers\PacksServiceProvider;
use App\Providers\ProvingServiceProvider;
use App\Providers\WorkspaceServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    IdentityServiceProvider::class,
    WorkspaceServiceProvider::class,
    PacksServiceProvider::class,
    CaptureServiceProvider::class,
    AgentServiceProvider::class,
    ProvingServiceProvider::class,
    InstanceServiceProvider::class,
    AdminPanelProvider::class,
];
