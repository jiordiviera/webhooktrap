<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Agent\Application\AgentAccountDeletion;
use App\Modules\Agent\Application\AgentWorkspaceMove;
use App\Modules\Agent\Console\RunnerToken;
use App\Modules\Agent\Domain\JsonSchema;
use App\Modules\Agent\Events\AgentPaired;
use App\Modules\Agent\Http\Middleware\AuthenticateAgent;
use App\Modules\Agent\Http\Middleware\LimitAgentBody;
use App\Modules\Agent\Listeners\AgentReactions;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Identity\Events\AccountRecoveryCompleted;
use App\Modules\Identity\Events\GuestSessionEnded;
use App\Modules\Proving\Events\RunAccepted;
use App\Modules\Proving\Events\RunQueued;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Agent wiring (decision 64): the protocol schemas (`contracts/agent/v1`),
 * the `/agent/v1` routes outside the web group, the agent middleware, the
 * claim move and account deletion participants, and the listeners on
 * Identity, Proving and its own events; the host command `runner:token`.
 */
final class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JsonSchema::class, fn (): JsonSchema => new JsonSchema(base_path('contracts/agent/v1')));
        $this->app->tag([AgentWorkspaceMove::class], 'workspace.move-participants');
        $this->app->tag([AgentAccountDeletion::class], 'identity.deletion-participants');
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('agent.auth', AuthenticateAgent::class);
        $router->aliasMiddleware('agent.body', LimitAgentBody::class);

        Event::listen(RunQueued::class, [AgentReactions::class, 'runQueued']);
        Event::listen(RunAccepted::class, [AgentReactions::class, 'runAccepted']);
        Event::listen(AgentPaired::class, [AgentReactions::class, 'agentPaired']);
        Event::listen(GuestSessionEnded::class, [AgentReactions::class, 'guestEnded']);
        Event::listen(AccountDeleted::class, [AgentReactions::class, 'accountDeleted']);
        Event::listen(AccountRecoveryCompleted::class, [AgentReactions::class, 'recoveryCompleted']);

        if ($this->app->runningInConsole()) {
            $this->commands([RunnerToken::class]);
        }

        if (! $this->app->routesAreCached()) {
            Route::prefix('agent/v1')->group(base_path('routes/agent.php'));
        }
    }
}
