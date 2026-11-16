<?php

declare(strict_types=1);

use App\Modules\Admin\Pages\Agents;
use App\Modules\Admin\Pages\Runners;
use App\Modules\Admin\Widgets\ActivityStats;
use App\Modules\Admin\Widgets\AlertStats;
use App\Modules\Admin\Widgets\CapturesPerDay;
use App\Modules\Admin\Widgets\HealthStats;
use App\Modules\Agent\Application\RunnerTokens;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once dirname(__DIR__).'/Agent/AgentHelpers.php';

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $operator = makeUser();
    $operator->forceFill(['is_operator' => true])->save();
    addKey($operator);
    test()->actingAs($operator->refresh());
});

it('lists paired agents with version and workspace, never their keys', function (): void {
    $agent = pairedAgent(userPrincipal(makeUser()));

    Livewire::test(Agents::class)
        ->assertSee('maya-mbp')
        ->assertSee($agent['id'])
        ->assertSee('wsp_')
        ->assertDontSee($agent['public'])
        ->assertDontSee($agent['token']);
});

it('lists runner tokens by name, last 4 and networks, never the token', function (): void {
    $issued = app(RunnerTokens::class)->issue('runner', ['203.0.113.0/24']);

    Livewire::test(Runners::class)
        ->assertSee('runner')
        ->assertSee($issued['last4'])
        ->assertSee('203.0.113.0/24')
        ->assertSee('Versions in use')
        ->assertDontSee($issued['token']);
});

it('renders the dashboard widgets from /health and the activity counts', function (string $widget, string $text): void {
    Livewire::test($widget)->assertOk()->assertSee($text);
})->with([
    [HealthStats::class, 'Scheduler heartbeat'],
    [AlertStats::class, 'Failed sign-ins (5 min)'],
    [ActivityStats::class, 'Live guest sessions'],
    [CapturesPerDay::class, 'Captures per day'],
]);
