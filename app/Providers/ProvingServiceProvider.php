<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Agent\Events\AgentRevoked;
use App\Modules\Proving\Application\ProvingAccountDeletion;
use App\Modules\Proving\Application\RunChannel;
use App\Modules\Proving\Listeners\ProvingReactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Proving wiring: the `private-run.{id}` channel, the account deletion participant and the reaction to revoked agents. */
final class ProvingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([RunChannel::class], 'identity.browser-channels');
        $this->app->tag([ProvingAccountDeletion::class], 'identity.deletion-participants');
    }

    public function boot(): void
    {
        Event::listen(AgentRevoked::class, [ProvingReactions::class, 'agentRevoked']);
    }
}
