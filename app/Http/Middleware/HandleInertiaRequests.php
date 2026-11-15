<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Agent\Application\AgentSharedProps;
use App\Modules\Identity\Application\SharedProps;
use App\Modules\Workspace\Application\WorkspaceSharedProps;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $identity = app(SharedProps::class)->for($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            ...$identity,
            ...app(WorkspaceSharedProps::class)->for($request),
            ...app(AgentSharedProps::class)->for($request),
            // Starter-kit shell compatibility until A07 moves it to `principal`.
            'auth' => [
                'user' => ($identity['principal']['kind'] ?? null) === 'user'
                    ? ['id' => $identity['principal']['id'], 'name' => $identity['principal']['name'], 'email' => $identity['principal']['email']]
                    : null,
            ],
            // Decision 59: per-device layout, set by HandleAppearance.
            'layout' => $request->attributes->get('layout', ['shell' => 'topbar', 'sidebar' => 'expanded']),
            // Live updates (decision 62): the public Reverb app key; the socket uses this origin.
            'realtime' => [
                'key' => config('broadcasting.default') === 'reverb' ? config('broadcasting.connections.reverb.key') : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
