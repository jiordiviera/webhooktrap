<?php

declare(strict_types=1);

use App\Infrastructure\Clock\Clock;
use App\Modules\Capture\Events\CaptureReceived;
use App\Modules\Identity\Events\GuestSessionClaimed;
use App\Modules\Proving\Events\RunFinished;
use App\Modules\Workspace\Application\Activation;
use App\Modules\Workspace\Events\ProjectCreated;
use App\Modules\Workspace\Listeners\ActivationListener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// Some suites commit outside the test transaction; start every test from no counts.
beforeEach(fn () => DB::table('activation_daily')->delete());

/** Calls the funnel listener directly: other listeners of these events need real rows. */
function activationOn(string $method, object $event): void
{
    app(ActivationListener::class)->{$method}($event);
}

/** A guest URL through POST /new; returns the project id. */
function activationGuestProject(): string
{
    $response = test()->post('/new');
    $key = (string) str($response->headers->get('Location'))->afterLast('/p/');

    return (string) DB::table('projects')->where('project_key', $key)->value('id');
}

it('counts a guest URL, its first delivery and first verdict once each, with no identifiers (issue #18)', function (): void {
    $projectId = activationGuestProject();

    $this->travel(90)->seconds();
    activationOn('captureReceived', new CaptureReceived('cap-1', $projectId, 1));
    activationOn('captureReceived', new CaptureReceived('cap-2', $projectId, 2));

    activationOn('runFinished', new RunFinished('run-1', $projectId, 'couldnt_start', 'no_agent', 2, null));
    $this->travel(60)->seconds();
    activationOn('runFinished', new RunFinished('run-2', $projectId, 'findings', null, 1, null));
    activationOn('runFinished', new RunFinished('run-3', $projectId, 'passed', null, 0, null));

    $f = app(Activation::class)->summary(7);

    expect($f['project_created']['guest'])->toBe(1)
        ->and($f['first_capture']['guest'])->toBe(1)
        ->and($f['first_capture']['guestAvgSeconds'])->toBeGreaterThanOrEqual(90)
        ->and($f['first_run']['guest'])->toBe(1)
        ->and($f['first_run']['guestAvgSeconds'])->toBeGreaterThanOrEqual(150)
        ->and($f['claimed']['guest'])->toBe(0);

    // Aggregate rows only: day, milestone, guest flag, count, seconds.
    expect(array_keys((array) DB::table('activation_daily')->first()))
        ->toEqualCanonicalizing(['day', 'milestone', 'guest', 'count', 'seconds_total']);

    $project = DB::table('projects')->where('id', $projectId)->first();
    expect($project->first_capture_at)->not->toBeNull()
        ->and($project->first_run_at)->not->toBeNull();
});

it('counts a claim only when the guest session had something to keep', function (): void {
    activationOn('claimed', new GuestSessionClaimed('gs-1', 'user-1', true));
    activationOn('claimed', new GuestSessionClaimed('gs-2', 'user-2', false));

    expect(app(Activation::class)->summary(1)['claimed']['guest'])->toBe(1);
});

it('keeps the daily counts after the project is gone', function (): void {
    $projectId = activationGuestProject();
    activationOn('captureReceived', new CaptureReceived('cap-1', $projectId, 1));

    DB::table('projects')->where('id', $projectId)->delete();

    expect(app(Activation::class)->summary(1)['first_capture']['guest'])->toBe(1);
});

it('never lets a counting failure reach the caller', function (): void {
    $before = DB::table('activation_daily')->sum('count');
    activationOn('captureReceived', new CaptureReceived('cap-1', 'not-a-uuid', 1));

    expect(DB::table('activation_daily')->sum('count'))->toEqual($before);
});

it('sums days in UTC and ignores older days', function (): void {
    $today = app(Clock::class)->now()->utc()->toDateString();
    DB::table('activation_daily')->insert([
        ['day' => $today, 'milestone' => 'first_capture', 'guest' => true, 'count' => 2, 'seconds_total' => 200],
        ['day' => '2020-01-01', 'milestone' => 'first_capture', 'guest' => true, 'count' => 9, 'seconds_total' => 9],
    ]);

    $f = app(Activation::class)->summary(7);

    expect($f['first_capture']['guest'])->toBe(2)
        ->and($f['first_capture']['guestAvgSeconds'])->toBe(100);
});

it('listens to the public events that mark each milestone', function (): void {
    Event::fake();

    Event::assertListening(ProjectCreated::class, [ActivationListener::class, 'projectCreated']);
    Event::assertListening(CaptureReceived::class, [ActivationListener::class, 'captureReceived']);
    Event::assertListening(RunFinished::class, [ActivationListener::class, 'runFinished']);
    Event::assertListening(GuestSessionClaimed::class, [ActivationListener::class, 'claimed']);
    $this->addToAssertionCount(4);
});
