<?php

declare(strict_types=1);

use App\Infrastructure\Cache\StoreFailures;
use App\Modules\Capture\Application\CaptureUrls;
use App\Modules\Capture\Application\Ingest;
use App\Modules\Capture\Events\CaptureDropped;
use App\Modules\Identity\Domain\GuestSession;
use App\Modules\Workspace\Domain\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\IpUtils;

require_once __DIR__.'/CaptureHelpers.php';

/*
 * A09 slice 3 review fixes (project/security/slice3-review.md, S3-1 to S3-13).
 */

beforeEach(function (): void {
    Mail::fake();
    config(['capture.public_origin' => 'https://in.example.test']);
});

/** The guest session id behind a guest project key. */
function guestSessionOfKey(string $projectKey): string
{
    return (string) DB::table('workspaces')->join('projects', 'projects.workspace_id', '=', 'workspaces.id')
        ->where('projects.project_key', $projectKey)->value('workspaces.guest_session_id');
}

/** Drop reasons raised while running `$send`. */
function dropReasons(Closure $send): array
{
    $reasons = [];
    Event::listen(CaptureDropped::class, function (CaptureDropped $e) use (&$reasons): void {
        $reasons[] = $e->reason;
    });
    $send();

    return $reasons;
}

/*
 * S3-1: provider egress ranges get a per-(address, key hash) bucket.
 */

it('keeps the provider range list small, documented and parseable', function (): void {
    $ranges = (array) config('capture.edge_provider_ranges');
    expect(array_keys($ranges))->toBe(['stripe', 'github', 'shopify']);
    foreach ($ranges as $list) {
        foreach ((array) $list as $cidr) {
            [$address, $bits] = explode('/', (string) $cidr);
            expect(filter_var($address, FILTER_VALIDATE_IP))->not->toBeFalse((string) $cidr)
                ->and(IpUtils::checkIp($address, (string) $cidr))->toBeTrue((string) $cidr)
                ->and((int) $bits)->toBeLessThanOrEqual(str_contains($address, ':') ? 128 : 32);
        }
    }
    expect(Ingest::fromProviderRange('140.82.115.4'))->toBeTrue()          // GitHub hooks
        ->and(Ingest::fromProviderRange('54.187.174.169'))->toBeTrue()     // Stripe webhooks
        ->and(Ingest::fromProviderRange('2606:50c0:1::1'))->toBeTrue()
        ->and(Ingest::fromProviderRange('203.0.113.7'))->toBeFalse();
});

it('counts a provider address per presented key, unknown and malformed keys alike, and an ordinary address per address', function (): void {
    config(['capture.edge_rate' => ['max' => 2, 'decay' => 60]]);
    $a = userProject(makeUser());
    $b = userProject(makeUser());
    $keyA = activeKey($a->id);
    $keyB = activeKey($b->id);
    $github = ['REMOTE_ADDR' => '140.82.115.4'];

    // One tenant fills its own bucket from the shared provider address.
    sendRaw('POST', "/c/{$keyA}", '', [], $github)->assertOk();
    sendRaw('POST', "/c/{$keyA}/sub", '', [], $github)->assertOk();
    $overA = sendRaw('POST', "/c/{$keyA}", '', [], $github);
    // Another tenant behind the same provider address is not affected.
    sendRaw('POST', "/c/{$keyB}", '', [], $github)->assertOk();

    // Unknown and malformed keys are counted exactly the same way.
    sendRaw('POST', '/c/Zz9Zz9Zz9Zz9', '', [], $github)->assertOk();
    sendRaw('POST', '/c/Zz9Zz9Zz9Zz9', '', [], $github)->assertOk();
    $overUnknown = sendRaw('POST', '/c/Zz9Zz9Zz9Zz9', '', [], $github);
    sendRaw('POST', '/c/x', '', [], $github)->assertOk();
    sendRaw('POST', '/c/x', '', [], $github)->assertOk();
    $overMalformed = sendRaw('POST', '/c/x', '', [], $github);

    // An ordinary address keeps one bucket across keys.
    sendRaw('POST', "/c/{$keyA}")->assertOk();
    sendRaw('POST', "/c/{$keyB}")->assertOk();
    $overOrdinary = sendRaw('POST', '/c/Zz9Zz9Zz9Zz9');

    expect($overA->getStatusCode())->toBe(429)
        ->and(observable($overA))->toBe(observable($overUnknown))
        ->and(observable($overA))->toBe(observable($overMalformed))
        ->and(observable($overA))->toBe(observable($overOrdinary))
        ->and(capturesOf($a->id))->toBe(3)
        ->and(capturesOf($b->id))->toBe(2);
});

/*
 * S3-2: redaction list of A09 decision 3.
 */

it('redacts each header of A09 decision 3, whatever its case, and keeps signature headers', function (string $header): void {
    $project = userProject(makeUser());
    $value = 'secret-value-for-'.strtolower($header);
    sendRaw('POST', '/c/'.activeKey($project->id), '{}', [
        strtolower($header) => $value,
        'Stripe-Signature' => 't=1,v1=abc', 'X-Hub-Signature-256' => 'sha256=deadbeef', 'X-Shopify-Hmac-Sha256' => 'aGk=',
    ]);

    $capture = lastCapture($project->id);
    expect(storedHeader($capture, $header))->toBe('[redacted · '.strlen($value).' bytes]')
        ->and(strtolower($capture->redactedHeaders()[0]))->toBe(strtolower($header))
        ->and(json_encode($capture->headers))->not->toContain($value)
        ->and(storedHeader($capture, 'Stripe-Signature'))->toBe('t=1,v1=abc')
        ->and(storedHeader($capture, 'X-Hub-Signature-256'))->toBe('sha256=deadbeef')
        ->and(storedHeader($capture, 'X-Shopify-Hmac-Sha256'))->toBe('aGk=');
})->with([
    'Authorization', 'Proxy-Authorization', 'Cookie', 'X-Gitlab-Token', 'X-Telegram-Bot-Api-Secret-Token',
    'X-Hook-Secret', 'X-Api-Key', 'Api-Key', 'X-Auth-Token',
]);

/*
 * S3-5: the app default trusts no forwarded header.
 */

it('does not trust a client-sent X-WT-Verified under the default trust mode', function (): void {
    expect(config('capture.proxy_trust_mode'))->toBe('none');
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '{}', [
        'X-WT-Verified' => '1', 'X-Forwarded-For' => '198.51.100.23', 'X-Forwarded-Proto' => 'http', 'X-Real-IP' => '198.51.100.24',
    ], ['REMOTE_ADDR' => '203.0.113.9']);

    $capture = lastCapture($project->id);
    expect($capture->source_ip)->toBe('203.0.113.9')
        ->and($capture->forwarded['trusted'])->toBeFalse()
        ->and($capture->forwarded['removed'])->toBe(['X-Wt-Verified'])
        ->and(storedHeader($capture, 'X-Forwarded-For'))->toBe('198.51.100.23')
        ->and(storedHeader($capture, 'X-Real-Ip'))->toBe('198.51.100.24')
        ->and($capture->public_origin_source)->toBe('configured');
});

/*
 * S3-6: nothing is stored after a rotation, even from a stale resolution.
 */

it('stores nothing on a rotated key even when the cached resolution still says active', function (): void {
    $project = userProject(makeUser());
    $oldKey = activeKey($project->id);
    sendRaw('POST', "/c/{$oldKey}", 'warm')->assertOk();   // the resolution is cached as active

    // Rotation whose eviction lost a race with a cache write: the stale entry stays.
    DB::table('capture_urls')->where('capture_key', $oldKey)->update(['state' => 'rotated', 'rotated_at' => now()]);

    $reasons = dropReasons(fn () => sendRaw('POST', "/c/{$oldKey}", 'late')->assertOk());
    expect($reasons)->toBe(['rotated'])
        ->and(capturesOf($project->id))->toBe(1);
});

it('stores nothing on the old key after a real rotation', function (): void {
    $project = userProject(makeUser());
    $oldKey = activeKey($project->id);
    sendRaw('POST', "/c/{$oldKey}", 'warm')->assertOk();
    app(CaptureUrls::class)->rotate($project);

    sendRaw('POST', "/c/{$oldKey}", 'late')->assertOk();
    sendRaw('POST', '/c/'.activeKey($project->id), 'new')->assertOk();
    expect(DB::table('captures')->where('project_id', $project->id)->orderBy('seq')->pluck('capture_url_id')->unique()->count())->toBe(2)
        ->and(capturesOf($project->id))->toBe(2);
});

/*
 * S3-7: headers, the target and oversize metadata count.
 */

it('counts headers, the raw target and the metadata of a body over the kept limit', function (): void {
    config(['capture.body_keep_max_bytes' => 4]);
    $project = userProject(makeUser());
    $key = activeKey($project->id);
    $bigHeader = str_repeat('h', 4000);
    sendRaw('POST', "/c/{$key}/a/long/path?q=".str_repeat('q', 500), '0123456789', ['X-Big' => $bigHeader]);

    $capture = lastCapture($project->id);
    $counted = (int) DB::table('captures')->where('id', $capture->id)->value('counted_bytes');
    expect($capture->body_kept)->toBeFalse()
        ->and($counted)->toBeGreaterThan(4000 + 2 * 500)
        ->and(Project::query()->whereKey($project->id)->value('stored_body_bytes'))->toBe($counted);
});

/*
 * S3-8: per-guest byte cap.
 */

it('stops a guest session at its byte cap and answers identically', function (): void {
    [, $key] = guestProject();
    $projectId = projectIdOfKey($key);
    sendRaw('POST', '/c/'.activeKey($projectId), '12345')->assertOk();
    $used = (int) GuestSession::query()->whereKey(guestSessionOfKey($key))->value('stored_bytes');
    config(['identity.guest.quotas.stored_bytes' => $used * 2]);   // one more of the same size fits exactly

    $stored = sendRaw('POST', '/c/'.activeKey($projectId), '12345');
    $reasons = dropReasons(function () use (&$over, $projectId): void {
        $over = sendRaw('POST', '/c/'.activeKey($projectId), '1');
    });

    expect($reasons)->toBe(['quota'])
        ->and(observable($over))->toBe(observable($stored))
        ->and(capturesOf($projectId))->toBe(2)
        ->and((int) GuestSession::query()->whereKey(guestSessionOfKey($key))->value('stored_bytes'))->toBe($used * 2);
});

/*
 * S3-9: claim moves the guest bytes into the account project's counters.
 */

it('carries the guest bytes and today\'s count into the claimed project\'s counters', function (): void {
    $user = makeUser();
    $response = $this->post('/new');
    $token = (string) $response->getCookie('__Host-wt_guest', false)->getValue();
    $key = (string) str($response->headers->get('Location'))->afterLast('/p/');
    $projectId = projectIdOfKey($key);
    sendRaw('POST', '/c/'.activeKey($projectId), '123456')->assertOk();
    sendRaw('POST', '/c/'.activeKey($projectId), '789')->assertOk();
    $guestBytes = (int) GuestSession::query()->whereKey(guestSessionOfKey($key))->value('stored_bytes');
    expect(Project::query()->whereKey($projectId)->value('stored_body_bytes'))->toBe(0);

    $this->withUnencryptedCookie('__Host-wt_guest', $token)->get('/sign-in')->assertOk();
    $this->withUnencryptedCookie('__Host-wt_guest', $token)
        ->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD])
        ->assertSessionHas('flash.code', 'claimed');

    $project = Project::query()->whereKey($projectId)->sole();
    expect($project->is_guest)->toBeFalse()
        ->and($project->stored_body_bytes)->toBe($guestBytes)
        ->and($project->captures_today)->toBe(2)
        ->and($project->captures_day?->toDateString())->toBe(now()->utc()->toDateString())
        ->and((int) DB::table('instance_counters')->value('guest_stored_bytes'))->toBe(0);
});

/*
 * S3-10: a lock wait on the global guest budget row gives up and drops.
 */

it('drops as store_error, with the same answer, when the guest budget row stays locked', function (): void {
    config(['capture.store_lock_timeout_ms' => 50]);
    [, $key] = guestProject();
    $projectId = projectIdOfKey($key);
    // Nothing on this connection may touch the budget row before the holder
    // locks it (the test transaction would hold it until the end).
    $reference = sendRaw('POST', '/c/Zz9Zz9Zz9Zz9', 'x');

    config(['database.connections.pgsql_holder' => config('database.connections.pgsql')]);
    $holder = DB::connection('pgsql_holder');
    $holder->beginTransaction();
    try {
        $holder->statement('SET LOCAL lock_timeout = 5000');
        $holder->select('SELECT guest_stored_bytes FROM instance_counters WHERE id = 1 FOR UPDATE');
        $reasons = dropReasons(function () use (&$blocked, $projectId): void {
            $blocked = sendRaw('POST', '/c/'.activeKey($projectId), 'y');
        });
    } finally {
        $holder->rollBack();
        DB::purge('pgsql_holder');
    }

    expect($reasons)->toBe(['store_error'])
        ->and(observable($blocked))->toBe(observable($reference))
        ->and(capturesOf($projectId))->toBe(0);
});

/*
 * S3-11: fail-open store errors are reported at most once a minute per kind.
 */

it('reports a failing store at most once a minute per kind, with a log line to alert on', function (): void {
    Exceptions::fake();
    Log::spy();
    $failures = app(StoreFailures::class);

    foreach (range(1, 5) as $i) {
        $failures->report('rate_limit', new RuntimeException('down'));
    }
    $failures->report('cache', new RuntimeException('down'));
    $this->travel(61)->seconds();
    $failures->report('rate_limit', new RuntimeException('still down'));

    Exceptions::assertReportedCount(3);
    Log::shouldHaveReceived('warning')->with('wt.store_failure', Mockery::on(fn (array $c): bool => $c['kind'] === 'rate_limit' && $c['suppressed'] === 0))->once();
    Log::shouldHaveReceived('warning')->with('wt.store_failure', Mockery::on(fn (array $c): bool => $c['kind'] === 'cache'))->once();
    Log::shouldHaveReceived('warning')->with('wt.store_failure', Mockery::on(fn (array $c): bool => $c['kind'] === 'rate_limit' && $c['suppressed'] === 4))->once();
});

/*
 * S3-12: the rotate route is throttled per user.
 */

it('throttles rotation per user with the uniform throttle answer', function (): void {
    config(['capture.rotate_rate' => ['max' => 2, 'decay' => 3600]]);
    $user = makeUser();
    signIn($user);
    $project = userProject($user);

    $this->from("/p/{$project->key}/settings/capture")->post("/p/{$project->key}/settings/capture/rotate")->assertSessionHas('flash.code', 'capture_url_rotated');
    $this->from("/p/{$project->key}/settings/capture")->post("/p/{$project->key}/settings/capture/rotate")->assertSessionHas('flash.code', 'capture_url_rotated');
    $keyBefore = activeKey($project->id);
    $this->from("/p/{$project->key}/settings/capture")->post("/p/{$project->key}/settings/capture/rotate")
        ->assertRedirect("/p/{$project->key}/settings/capture")
        ->assertSessionHasErrors(['form' => 'throttled']);
    $this->postJson("/p/{$project->key}/settings/capture/rotate")->assertStatus(429)->assertJson(['error' => 'throttled'])->assertHeader('Retry-After');

    expect(activeKey($project->id))->toBe($keyBefore)
        ->and(DB::table('capture_urls')->where('project_id', $project->id)->count())->toBe(3);
});

/*
 * S3-13: bodies are immutable and the facts trigger covers `id`.
 */

it('refuses any update of a capture body, and of a capture id', function (): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), 'body');
    $capture = lastCapture($project->id);

    expect(fn () => DB::transaction(fn () => DB::table('capture_bodies')->where('capture_id', $capture->id)->update(['body' => 'other'])))
        ->toThrow(QueryException::class, 'capture bodies are immutable')
        ->and(fn () => DB::transaction(fn () => DB::table('captures')->where('id', $capture->id)->update(['id' => DB::raw('uuidv7()')])))
        ->toThrow(QueryException::class, 'capture request facts are immutable')
        ->and(fn () => DB::transaction(fn () => DB::table('captures')->where('id', $capture->id)->update(['counted_bytes' => 0])))
        ->toThrow(QueryException::class, 'capture request facts are immutable');
});
