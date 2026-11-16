<?php

declare(strict_types=1);

use App\Modules\Instance\Application\Heartbeats;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

/*
 * `app:heartbeat-check --max-age=120`: the scheduler's Compose healthcheck
 * (compose-profile §2, §6).
 */

it('fails while no heartbeat was ever written', function (): void {
    $this->artisan('app:heartbeat-check', ['--max-age' => 120])->assertExitCode(1);
});

it('passes on a recent heartbeat and fails once it is older than --max-age', function (): void {
    app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
    $this->artisan('app:heartbeat-check', ['--max-age' => 120])->assertExitCode(0);

    $this->travel(119)->seconds();
    $this->artisan('app:heartbeat-check', ['--max-age' => 120])->assertExitCode(0);

    $this->travel(5)->seconds();
    $this->artisan('app:heartbeat-check', ['--max-age' => 120])->assertExitCode(1);
});

it('refuses a --max-age that is not a positive number', function (): void {
    $this->artisan('app:heartbeat-check', ['--max-age' => 'soon'])->assertExitCode(2);
    $this->artisan('app:heartbeat-check', ['--max-age' => 0])->assertExitCode(2);
});

it('writes the heartbeat from a scheduled task every minute, inline in the scheduler', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => $event->description === 'instance:heartbeat');

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');

    $events->first()->run(app());

    expect(DB::table('instance_heartbeats')->where('name', 'scheduler')->exists())->toBeTrue();
    $this->artisan('app:heartbeat-check', ['--max-age' => 120])->assertExitCode(0);
});

it('upserts one row per heartbeat name', function (): void {
    $heartbeats = app(Heartbeats::class);
    $heartbeats->beat(Heartbeats::SCHEDULER);
    $this->travel(30)->seconds();
    $heartbeats->beat(Heartbeats::SCHEDULER);

    expect(DB::table('instance_heartbeats')->count())->toBe(1)
        ->and($heartbeats->age(Heartbeats::SCHEDULER))->toBe(0);
});
