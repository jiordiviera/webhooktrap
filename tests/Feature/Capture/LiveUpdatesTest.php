<?php

declare(strict_types=1);

use App\Infrastructure\Broadcasting\LiveTrigger;
use App\Infrastructure\Broadcasting\LiveUpdates;
use App\Modules\Capture\Application\CaptureUrls;
use App\Modules\Identity\Domain\User;
use App\Modules\Workspace\Application\Projects;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

require_once __DIR__.'/CaptureHelpers.php';

/*
 * Slice 4 live updates: `CaptureReceived` → `project.captures` trigger after
 * commit on `broadcasts`, and `POST /broadcasting/auth` for browser channels
 * (event-catalogue §1, §3.1; decision 50).
 */

const REVERB_TEST_KEY = 'test-reverb-key';
const REVERB_TEST_SECRET = 'test-reverb-secret';

beforeEach(function (): void {
    Mail::fake();
    config([
        'capture.public_origin' => 'https://in.example.test',
        'broadcasting.connections.reverb.key' => REVERB_TEST_KEY,
        'broadcasting.connections.reverb.secret' => REVERB_TEST_SECRET,
    ]);
});

function channelAuth(string $channel, string $socketId = '1234.5678'): TestResponse
{
    return test()->post('/broadcasting/auth', ['socket_id' => $socketId, 'channel_name' => $channel]);
}

/** The guest session id behind a guest project key. */
function guestIdOfKey(string $projectKey): string
{
    return (string) DB::table('workspaces')->join('projects', 'projects.workspace_id', '=', 'workspaces.id')
        ->where('projects.project_key', $projectKey)->value('workspaces.guest_session_id');
}

/*
 * Broadcast triggers.
 */

it('queues one project.captures trigger after a stored capture, on the broadcasts queue, with no capture data', function (): void {
    Queue::fake();
    $project = userProject(makeUser());

    sendRaw('POST', '/c/'.activeKey($project->id), '{"email":"jonah.reyes@example.com"}', ['Content-Type' => 'application/json']);

    Queue::assertPushedOn('broadcasts', LiveTrigger::class, fn (LiveTrigger $job): bool => $job->channel === 'project.'.$project->id
        && $job->event === 'project.captures'
        && $job->payload === ['v' => 1]);
    Queue::assertPushed(LiveTrigger::class, 1);
});

it('coalesces triggers per project while one waits in the queue', function (): void {
    Queue::fake();
    $a = userProject(makeUser(), 'A');
    $b = userProject(makeUser(), 'B');

    foreach (range(1, 3) as $i) {
        sendRaw('POST', '/c/'.activeKey($a->id), '{}');
    }
    sendRaw('POST', '/c/'.activeKey($b->id), '{}');

    Queue::assertPushed(LiveTrigger::class, 2);
    Queue::assertPushed(LiveTrigger::class, fn (LiveTrigger $job): bool => $job->channel === 'project.'.$b->id);
});

it('queues nothing for a dropped capture', function (): void {
    Queue::fake();
    $user = makeUser();
    $project = userProject($user);
    $oldKey = activeKey($project->id);
    app(CaptureUrls::class)->rotate($project);
    Queue::fake(); // forget the capture_url trigger of the rotation

    sendRaw('POST', '/c/Zz9Zz9Zz9Zz9', '{}');                    // unknown key
    sendRaw('POST', '/c/'.$oldKey, '{}');                         // rotated key
    $deleted = userProject($user, 'Deleted');
    $deletedKey = activeKey($deleted->id);
    app(Projects::class)->delete($deleted);
    sendRaw('POST', '/c/'.$deletedKey, '{}');                     // deleted project
    [, $guestKey] = guestProject();
    DB::table('guest_sessions')->where('id', guestIdOfKey($guestKey))->update(['stored_captures' => 100]);
    sendRaw('POST', '/c/'.activeKey(projectIdOfKey($guestKey)), '{}'); // guest quota

    Queue::assertNothingPushed();
    expect(DB::table('captures')->count())->toBe(0);
});

it('sends a trigger only after the transaction commits, and never for a rolled-back one', function (): void {
    Queue::fake();
    $live = app(LiveUpdates::class);

    try {
        DB::transaction(function () use ($live): void {
            $live->trigger('project.00000000-0000-0000-0000-000000000001', 'project.captures');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    Queue::assertNothingPushed();

    $pushedInside = null;
    DB::transaction(function () use ($live, &$pushedInside): void {
        $live->trigger('project.00000000-0000-0000-0000-000000000002', 'project.captures');
        $pushedInside = Queue::pushed(LiveTrigger::class)->count();
    });
    expect($pushedInside)->toBe(0);
    Queue::assertPushed(LiveTrigger::class, 1);
});

it('keeps the capture when queueing the broadcast fails (persistence never depends on Reverb)', function (): void {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('valkey down'));
    $project = userProject(makeUser());

    sendRaw('POST', '/c/'.activeKey($project->id), '{}')->assertOk();

    expect(capturesOf($project->id))->toBe(1);
});

it('refuses payloads of 1 KiB or more', function (): void {
    app(LiveUpdates::class)->trigger('project.x', 'project.captures', ['v' => 1, 'pad' => str_repeat('a', 1024)]);
})->throws(InvalidArgumentException::class);

it('broadcasts the trigger on the private project channel', function (): void {
    $broadcaster = Mockery::mock(Broadcaster::class);
    $broadcaster->shouldReceive('broadcast')->once()->withArgs(fn (array $channels, string $event, array $payload): bool => count($channels) === 1
        && $channels[0] instanceof PrivateChannel && $channels[0]->name === 'private-project.abc'
        && $event === 'project.captures' && $payload === ['v' => 1]);
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('connection')->andReturn($broadcaster);

    (new LiveTrigger('project.abc', 'project.captures', ['v' => 1]))->handle($factory);
});

it('triggers project.capture_url when the capture URL rotates', function (): void {
    Queue::fake();
    $project = userProject(makeUser());

    app(CaptureUrls::class)->rotate($project);

    Queue::assertPushed(LiveTrigger::class, fn (LiveTrigger $job): bool => $job->event === 'project.capture_url'
        && $job->channel === 'project.'.$project->id && $job->payload === ['v' => 1]);
});

/*
 * Channel authorisation (TM-20): owner, other user, guest, anonymous.
 */

it('authorises the project channel for the owning user only, signing the principal into channel_data', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    $channel = 'private-project.'.$project->id;

    actAs($owner);
    $response = channelAuth($channel)->assertOk();
    $channelData = json_encode(['user_id' => 'user:'.$owner->id]);
    expect($response->json())->toBe([
        'auth' => REVERB_TEST_KEY.':'.hash_hmac('sha256', '1234.5678:'.$channel.':'.$channelData, REVERB_TEST_SECRET),
        'channel_data' => $channelData,
    ]);

    actAs(makeUser());
    channelAuth($channel)->assertForbidden();
    newBrowser();
    channelAuth($channel)->assertForbidden();
    [$guestCookie] = guestProject();
    asGuest($guestCookie);
    channelAuth($channel)->assertForbidden();
});

it('authorises the guest project and session channels for that guest only', function (): void {
    [$cookie, $key] = guestProject();
    $projectChannel = 'private-project.'.projectIdOfKey($key);
    $guestId = guestIdOfKey($key);
    $sessionChannel = 'private-session.'.$guestId;

    asGuest($cookie);
    expect(channelAuth($projectChannel)->assertOk()->json('channel_data'))->toBe(json_encode(['user_id' => 'guest:'.$guestId]));
    channelAuth($sessionChannel)->assertOk();

    newBrowser();
    [$other] = guestProject();
    asGuest($other);
    channelAuth($projectChannel)->assertForbidden();
    channelAuth($sessionChannel)->assertForbidden();

    actAs(makeUser());
    channelAuth($projectChannel)->assertForbidden();
    channelAuth($sessionChannel)->assertForbidden();

    newBrowser();
    channelAuth($projectChannel)->assertForbidden();
    channelAuth($sessionChannel)->assertForbidden();
});

it('refuses deleted projects, ended guest sessions, unverified users and malformed requests with a bare 403', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    $deleted = userProject($owner, 'Deleted');
    app(Projects::class)->delete($deleted);
    actAs($owner);

    $denials = [
        ['private-project.'.$deleted->id, '1234.5678'],
        ['project.'.$project->id, '1234.5678'],                       // public channel
        ['presence-project.'.$project->id, '1234.5678'],
        ['private-encrypted-project.'.$project->id, '1234.5678'],
        ['private-project.'.strtoupper($project->id), '1234.5678'],
        ['private-project.not-a-uuid', '1234.5678'],
        ['private-agent.'.$project->id, '1234.5678'],                  // never on the browser endpoint
        ['private-workspace.'.$project->id, '1234.5678'],              // not built yet
        ['private-project.'.$project->id, 'abc'],
        ['private-project.'.$project->id, '1234.5678:x'],
    ];
    foreach ($denials as [$channel, $socket]) {
        $response = channelAuth($channel, $socket);
        expect($response->getStatusCode())->toBe(403, $channel.' '.$socket)
            ->and($response->getContent())->toBe('');
    }
    $this->post('/broadcasting/auth', [])->assertForbidden();

    User::query()->whereKey($owner->id)->update(['email_verified_at' => null]);
    actAs(User::query()->findOrFail($owner->id));
    channelAuth('private-project.'.$project->id)->assertForbidden();

    [$cookie, $key] = guestProject();
    $guestChannel = 'private-project.'.projectIdOfKey($key);
    newBrowser();
    asGuest($cookie)->post('/session/end');
    newBrowser();
    asGuest($cookie);
    channelAuth($guestChannel)->assertForbidden();
});

it('answers 403, not a signature, when Reverb credentials are missing', function (): void {
    config(['broadcasting.connections.reverb.secret' => null]);
    $owner = makeUser();
    $project = userProject($owner);
    actAs($owner);

    channelAuth('private-project.'.$project->id)->assertForbidden();
});

it('does not register Laravel broadcast routes (decision 50, C-3)', function (): void {
    $route = app('router')->getRoutes()->match(request()->create('/broadcasting/auth', 'POST'));

    expect($route->getActionName())->toContain('BroadcastAuthController')
        ->and($route->gatherMiddleware())->toContain('web');
});
