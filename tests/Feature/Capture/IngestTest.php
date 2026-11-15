<?php

declare(strict_types=1);

use App\Modules\Capture\Application\CaptureUrls;
use App\Modules\Capture\Domain\CaptureUrl;
use App\Modules\Capture\Events\CaptureDropped;
use App\Modules\Capture\Events\CaptureReceived;
use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Domain\GuestSession;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Domain\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/CaptureHelpers.php';

beforeEach(function (): void {
    Mail::fake();
    config(['capture.public_origin' => 'https://in.example.test']);
});

/*
 * ADR-005 amendment 1 §4 (A-15, TM-01, TM-T1): one answer for every case.
 */

it('answers byte-identically whether the capture was stored or dropped, on every drop path', function (): void {
    Event::fake([CaptureDropped::class, CaptureReceived::class]);
    $answers = [];
    $send = function (string $label, string $key, string $body = '{"id":"evt_1"}', string $rest = '') use (&$answers): void {
        foreach (['POST', 'HEAD'] as $method) {
            $answers[$method][$label] = observable(sendRaw($method, "/c/{$key}{$rest}", $body, ['Content-Type' => 'application/json']));
        }
    };

    // Stored: a live account project, and a sub-path.
    $user = makeUser();
    $live = userProject($user, 'Live');
    $send('stored', activeKey($live->id));
    $send('stored_subpath', activeKey($live->id), rest: '/hooks/stripe?x=1');
    expect(capturesOf($live->id))->toBe(4);

    // Body over the kept limit: stored without its body.
    config(['capture.body_keep_max_bytes' => 4]);
    $send('not_kept', activeKey($live->id), '0123456789');
    config(['capture.body_keep_max_bytes' => 5 * 1024 * 1024]);

    // Unknown and malformed keys.
    $send('unknown_key', 'Zz9Zz9Zz9Zz9');
    $send('malformed_key', 'short');
    $send('empty_key', '');

    // Rotated key.
    $rotatedProject = userProject($user, 'Rotated');
    $oldKey = activeKey($rotatedProject->id);
    app(CaptureUrls::class)->rotate($rotatedProject);
    $send('rotated', $oldKey);

    // Soft-deleted project.
    $deleted = userProject($user, 'Deleted');
    $deletedKey = activeKey($deleted->id);
    app(Projects::class)->delete($deleted);
    $send('project_deleted', $deletedKey);

    // Owner not live (tombstoned account).
    $gone = makeUser();
    $goneProject = userProject($gone);
    app(AccountDeletion::class)->tombstone($gone, 'admin', RequestContext::console());
    $send('owner_deleted', activeKey($goneProject->id));

    // Account quotas: per day and stored bytes.
    $daily = userProject(makeUser());
    Project::query()->whereKey($daily->id)->update(['captures_today' => 5000, 'captures_day' => now()->utc()->toDateString()]);
    $send('account_daily_quota', activeKey($daily->id));
    $bytes = userProject(makeUser());
    Project::query()->whereKey($bytes->id)->update(['stored_body_bytes' => 512 * 1024 * 1024]);
    $send('account_bytes_quota', activeKey($bytes->id));

    // Guests: over 100, expired, ended, global budget.
    [, $guestKey] = guestProject();
    $guestProjectId = projectIdOfKey($guestKey);
    $guestId = (string) DB::table('workspaces')->join('projects', 'projects.workspace_id', '=', 'workspaces.id')
        ->where('projects.id', $guestProjectId)->value('workspaces.guest_session_id');
    GuestSession::query()->whereKey($guestId)->update(['stored_captures' => 100]);
    $send('guest_quota', activeKey($guestProjectId));

    [, $expiredKey] = guestProject();
    $expiredProjectId = projectIdOfKey($expiredKey);
    DB::table('guest_sessions')->whereIn('id', DB::table('workspaces')->join('projects', 'projects.workspace_id', '=', 'workspaces.id')
        ->where('projects.id', $expiredProjectId)->select('workspaces.guest_session_id'))->update(['expires_at' => now()->subMinute()]);
    $send('guest_expired', activeKey($expiredProjectId));

    [, $budgetKey] = guestProject();
    config(['capture.guest_storage_budget_bytes' => 5]);
    $send('guest_budget', activeKey(projectIdOfKey($budgetKey)));
    config(['capture.guest_storage_budget_bytes' => 5 * 1024 * 1024 * 1024]);

    // A database failure after the request was read: the body passes the
    // configured kept limit but not the column CHECK, so the insert fails.
    $broken = userProject(makeUser());
    config(['capture.body_keep_max_bytes' => 6 * 1024 * 1024]);
    $send('store_error', activeKey($broken->id), str_repeat('x', 5 * 1024 * 1024 + 1));
    config(['capture.body_keep_max_bytes' => 5 * 1024 * 1024]);

    foreach (['POST', 'HEAD'] as $method) {
        $reference = $answers[$method]['stored'];
        foreach ($answers[$method] as $label => $answer) {
            expect($answer)->toBe($reference, "{$method} {$label} differs from a stored capture");
        }
    }
    expect($answers['POST']['stored'][0])->toBe(200)
        ->and($answers['POST']['stored'][1])->toBe('{"received":true}')
        ->and($answers['POST']['stored'][2]['content-type'])->toBe(['application/json'])
        ->and($answers['POST']['stored'][2]['cache-control'][0])->toContain('no-store')
        ->and($answers['HEAD']['stored'][1])->toBe('')
        ->and($answers['HEAD']['stored'][2])->toBe($answers['POST']['stored'][2])
        ->and(collect($answers['POST']['stored'][2])->keys()->all())->not->toContain('set-cookie');

    // Nothing stored on the drop paths; the reasons reach metrics only.
    expect(capturesOf($rotatedProject->id))->toBe(0)
        ->and(capturesOf($deleted->id))->toBe(0)
        ->and(capturesOf($goneProject->id))->toBe(0)
        ->and(capturesOf($daily->id))->toBe(0)
        ->and(capturesOf($bytes->id))->toBe(0)
        ->and(capturesOf($guestProjectId))->toBe(0)
        ->and(capturesOf($expiredProjectId))->toBe(0)
        ->and(capturesOf(projectIdOfKey($budgetKey)))->toBe(0)
        ->and(capturesOf($broken->id))->toBe(0)
        ->and(capturesOf($live->id))->toBe(6);
    $reasons = Event::dispatched(CaptureDropped::class)->map(fn (array $args): string => $args[0]->reason)->countBy()->all();
    expect($reasons)->toEqual([
        'unknown_key' => 6, 'rotated' => 2, 'expired' => 6, 'account_quota' => 4,
        'quota' => 2, 'guest_budget' => 2, 'store_error' => 2,
    ]);
    Event::assertDispatchedTimes(CaptureReceived::class, 6);
});

it('never sets a cookie or starts a session on the ingest path', function (): void {
    $project = userProject(makeUser());
    $response = sendRaw('POST', '/c/'.activeKey($project->id), '{}', ['Cookie' => 'laravel_session=abc']);

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});

/*
 * Request facts (ADR-005 amendment 1 §1–§3).
 */

it('stores the request facts: method, raw target, path, raw query, ordered headers, sapi fidelity', function (): void {
    $project = userProject(makeUser());
    $key = activeKey($project->id);
    $target = "/c/{$key}/hooks/stripe?b=2&a=1&a=%20x&empty=";

    sendRaw('POST', $target, '{"ok":true}', [
        'Content-Type' => 'application/json; charset=utf-8',
        'Stripe-Signature' => 't=1,v1=abc',
        'X-Custom' => 'one',
    ])->assertOk();

    $capture = lastCapture($project->id);
    $names = array_map(fn (array $p): string => $p[0], $capture->headers);
    expect($capture->seq)->toBe(1)
        ->and($capture->method)->toBe('POST')
        ->and($capture->raw_target)->toBe($target)
        ->and($capture->path)->toBe("/c/{$key}/hooks/stripe")
        ->and($capture->raw_query)->toBe('b=2&a=1&a=%20x&empty=')
        ->and($capture->header_fidelity)->toBe('sapi')
        ->and($capture->content_type)->toBe('application/json; charset=utf-8')
        ->and(storedHeader($capture, 'Stripe-Signature'))->toBe('t=1,v1=abc')
        ->and($names)->toContain('Content-Type', 'Stripe-Signature', 'X-Custom', 'Host')
        ->and($capture->public_origin)->toBe('https://in.example.test')
        ->and($capture->public_origin_source)->toBe('configured');
});

it('keeps an empty query distinct from no query', function (): void {
    $project = userProject(makeUser());
    $key = activeKey($project->id);
    sendRaw('POST', "/c/{$key}?", '');
    sendRaw('POST', "/c/{$key}", '');

    $rows = DB::table('captures')->where('project_id', $project->id)->orderBy('seq')->pluck('raw_query')->all();
    expect($rows)->toBe(['', null]);
});

it('redacts each credential header to [redacted · N bytes] and keeps signature headers (S-7)', function (string $header, string $value): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '{}', [$header => $value, 'X-Hub-Signature-256' => 'sha256=deadbeef']);

    $capture = lastCapture($project->id);
    $stored = json_encode($capture->headers);
    expect(storedHeader($capture, $header))->toBe('[redacted · '.strlen($value).' bytes]')
        ->and($capture->redactedHeaders())->toBe([ucwords(strtolower($header), '-')])
        ->and($stored)->not->toContain($value)
        ->and(storedHeader($capture, 'X-Hub-Signature-256'))->toBe('sha256=deadbeef');
})->with([
    'Authorization' => ['Authorization', 'Basic dXNlcjpwYXNzd29yZA=='],
    'Proxy-Authorization' => ['Proxy-Authorization', 'Bearer s3cr3t-token-value'],
    'Cookie' => ['Cookie', '__Host-wt_session=abcdef; theme=dark'],
]);

it('redacts every credential header at once and lists each name', function (): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '', [
        'Authorization' => 'Bearer a', 'Proxy-Authorization' => 'Basic b', 'Cookie' => 'c=d',
    ]);

    expect(lastCapture($project->id)->redactedHeaders())->toEqualCanonicalizing(['Authorization', 'Proxy-Authorization', 'Cookie']);
});

it('never stores the method override; stores any method token as received', function (string $method): void {
    $project = userProject(makeUser());
    sendRaw($method, '/c/'.activeKey($project->id), '')->assertOk();

    expect(lastCapture($project->id)->method)->toBe($method);
})->with(['GET', 'HEAD', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE', 'PURGE', 'PROPFIND']);

it('stores POST when a method override is sent', function (): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '_method=DELETE', [
        'Content-Type' => 'application/x-www-form-urlencoded', 'X-HTTP-Method-Override' => 'PUT',
    ])->assertOk();

    expect(lastCapture($project->id)->method)->toBe('POST');
});

it('stores an empty body: size 0, SHA-256 of nothing, kept', function (): void {
    $project = userProject(makeUser());
    sendRaw('GET', '/c/'.activeKey($project->id))->assertOk()->assertExactJson(['received' => true]);

    $capture = lastCapture($project->id);
    expect($capture->body_size)->toBe(0)
        ->and(bin2hex($capture->body_sha256))->toBe(hash('sha256', ''))
        ->and($capture->body_kept)->toBeTrue()
        ->and($capture->content_type)->toBeNull()
        ->and(DB::table('capture_bodies')->where('capture_id', $capture->id)->count())->toBe(1);
});

/*
 * Body limits and hashing (§5): boundary 5 242 879 / 5 242 880 / 5 242 881.
 */

it('hashes every body and keeps it up to and including 5 MiB', function (int $size, bool $kept): void {
    $project = userProject(makeUser());
    $body = random_bytes($size);
    sendRaw('POST', '/c/'.activeKey($project->id), $body, ['Content-Type' => 'application/octet-stream'])->assertOk();

    $capture = lastCapture($project->id);
    $stored = DB::table('capture_bodies')->where('capture_id', $capture->id)->value('body');
    expect($capture->body_size)->toBe($size)
        ->and($capture->body_sha256)->toBe(hash('sha256', $body, true))
        ->and($capture->body_kept)->toBe($kept);
    if ($kept) {
        expect(is_resource($stored) ? stream_get_contents($stored) : $stored)->toBe($body);
    } else {
        expect($stored)->toBeNull();
    }
})->with([
    '5 242 879 bytes' => [5_242_879, true],
    '5 242 880 bytes' => [5_242_880, true],
    '5 242 881 bytes' => [5_242_881, false],
]);

it('answers 413 above the hard limit, the same for every key, and stores nothing', function (): void {
    config(['capture.request_max_bytes' => 1000]);
    $project = userProject(makeUser());
    $key = activeKey($project->id);

    $atLimit = sendRaw('POST', "/c/{$key}", str_repeat('a', 1000));
    $over = sendRaw('POST', "/c/{$key}", str_repeat('a', 1001));
    $overUnknown = sendRaw('POST', '/c/Zz9Zz9Zz9Zz9', str_repeat('a', 1001));
    $declared = sendRaw('POST', "/c/{$key}", 'tiny', ['Content-Length' => '5000']);

    expect($atLimit->getStatusCode())->toBe(200)
        ->and($over->getStatusCode())->toBe(413)
        ->and(observable($over))->toBe(observable($overUnknown))
        ->and(observable($declared))->toBe(observable($over))
        ->and(capturesOf($project->id))->toBe(1);
});

/*
 * Trusted-proxy chain (TM-05, SV-2, §9.4).
 */

it('takes the source IP from the trusted proxy only, removes our proxy headers and records the forwarding facts', function (): void {
    config(['capture.proxy_trust_mode' => 'secret', 'capture.trust_forwarded_origin' => true]);
    $project = userProject(makeUser());
    $key = activeKey($project->id);

    // Through the host proxy: the image Caddyfile set X-WT-Verified.
    sendRaw('POST', "/c/{$key}", '{}', [
        'X-WT-Verified' => '1', 'X-Forwarded-For' => '198.51.100.23', 'X-Forwarded-Proto' => 'http', 'X-Provider' => 'kept',
    ], ['REMOTE_ADDR' => '172.18.0.1']);
    $trusted = lastCapture($project->id);

    // A direct local process forging the same headers (TM-T141).
    sendRaw('POST', "/c/{$key}", '{}', [
        'X-Forwarded-For' => '198.51.100.99', 'X-Forwarded-Proto' => 'http',
    ], ['REMOTE_ADDR' => '172.18.0.1']);
    $forged = lastCapture($project->id);

    expect($trusted->source_ip)->toBe('198.51.100.23')
        ->and(storedHeader($trusted, 'X-Forwarded-For'))->toBeNull()
        ->and(storedHeader($trusted, 'X-Wt-Verified'))->toBeNull()
        ->and(storedHeader($trusted, 'X-Provider'))->toBe('kept')
        ->and($trusted->forwarded['trusted'])->toBeTrue()
        ->and($trusted->forwarded['removed'])->toEqualCanonicalizing(['X-Wt-Verified', 'X-Forwarded-For', 'X-Forwarded-Proto'])
        ->and($trusted->forwarded['observed'])->toContain(['X-Forwarded-For', '198.51.100.23'])
        ->and($trusted->public_origin)->toBe('http://in.example.test')
        ->and($trusted->public_origin_source)->toBe('forwarded_trusted');

    expect($forged->source_ip)->toBe('172.18.0.1')
        ->and(storedHeader($forged, 'X-Forwarded-For'))->toBe('198.51.100.99')
        ->and($forged->forwarded['trusted'])->toBeFalse()
        ->and($forged->forwarded['removed'])->toBe([])
        ->and($forged->public_origin)->toBe('https://in.example.test')
        ->and($forged->public_origin_source)->toBe('configured');
});

it('walks the X-Forwarded-For chain past trusted proxies in ip mode, and ignores it in none mode', function (): void {
    $project = userProject(makeUser());
    $key = activeKey($project->id);
    $headers = ['X-Forwarded-For' => '192.0.2.200, 198.51.100.7, 10.0.0.9'];

    config(['capture.proxy_trust_mode' => 'ip', 'capture.trusted_proxies' => ['10.0.0.0/8']]);
    sendRaw('POST', "/c/{$key}", '', $headers, ['REMOTE_ADDR' => '10.0.0.2']);
    expect(lastCapture($project->id)->source_ip)->toBe('198.51.100.7');

    sendRaw('POST', "/c/{$key}", '', $headers, ['REMOTE_ADDR' => '203.0.113.50']);
    expect(lastCapture($project->id)->source_ip)->toBe('203.0.113.50');

    config(['capture.proxy_trust_mode' => 'none']);
    sendRaw('POST', "/c/{$key}", '', $headers + ['X-WT-Verified' => '1'], ['REMOTE_ADDR' => '10.0.0.2']);
    $capture = lastCapture($project->id);
    expect($capture->source_ip)->toBe('10.0.0.2')
        ->and(storedHeader($capture, 'X-Wt-Verified'))->toBeNull()
        ->and(storedHeader($capture, 'X-Forwarded-For'))->toBe('192.0.2.200, 198.51.100.7, 10.0.0.9');
});

it('uses the request origin, source direct, when no capture origin is configured', function (): void {
    config(['capture.public_origin' => null]);
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '', [], ['HTTP_HOST' => 'hooks.local:8080']);

    $capture = lastCapture($project->id);
    expect($capture->public_origin)->toBe('http://hooks.local:8080')
        ->and($capture->public_origin_source)->toBe('direct');
});

it('refuses any change to the request facts after insert', function (): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '{}');
    $capture = lastCapture($project->id);

    expect(fn () => DB::transaction(fn () => DB::table('captures')->where('id', $capture->id)->update(['method' => 'GET'])))->toThrow(QueryException::class);
    DB::table('captures')->where('id', $capture->id)->update(['provider' => 'stripe', 'sig_state' => 'not_checked']);
    expect(lastCapture($project->id)->method)->toBe('POST');
});

/*
 * Edge rate limit (§4): per client address, never per key.
 */

it('rate-limits per client address with the same 429 for every key', function (): void {
    config(['capture.edge_rate' => ['max' => 2, 'decay' => 60]]);
    $project = userProject(makeUser());
    $key = activeKey($project->id);

    sendRaw('POST', "/c/{$key}")->assertOk();
    sendRaw('POST', '/c/Zz9Zz9Zz9Zz9')->assertOk();
    $known = sendRaw('POST', "/c/{$key}");
    $unknown = sendRaw('POST', '/c/Zz9Zz9Zz9Zz9');
    $otherAddress = sendRaw('POST', "/c/{$key}", '', [], ['REMOTE_ADDR' => '198.51.100.1']);

    expect($known->getStatusCode())->toBe(429)
        ->and(observable($known))->toBe(observable($unknown))
        ->and($otherAddress->getStatusCode())->toBe(200)
        ->and(capturesOf($project->id))->toBe(2);
});

/*
 * Quotas (ADR-005 amendment 1 §5, TM-02, decision 58).
 */

it('stops a guest at 100 stored captures', function (): void {
    [, $key] = guestProject();
    $projectId = projectIdOfKey($key);
    $guest = GuestSession::query()->whereIn('id', DB::table('workspaces')->join('projects', 'projects.workspace_id', '=', 'workspaces.id')
        ->where('projects.id', $projectId)->select('workspaces.guest_session_id'))->sole();
    $guest->forceFill(['stored_captures' => 98])->save();

    foreach (range(1, 4) as $i) {
        sendRaw('POST', '/c/'.activeKey($projectId), 'abc')->assertOk();
    }

    expect(capturesOf($projectId))->toBe(2)
        ->and($guest->refresh()->stored_captures)->toBe(100)
        ->and($guest->stored_bytes)->toBe((int) DB::table('captures')->where('project_id', $projectId)->sum('counted_bytes'));
});

it('holds the global guest storage budget and gives bytes back when a guest session ends', function (): void {
    [$cookieA, $keyA] = guestProject();
    [, $keyB] = guestProject();

    // Same headers and target length on both keys: the same metadata bytes.
    sendRaw('POST', '/c/'.activeKey(projectIdOfKey($keyA)), '123456')->assertOk();
    $a = (int) DB::table('instance_counters')->value('guest_stored_bytes');
    $meta = $a - 6;
    expect($meta)->toBeGreaterThan(0);
    config(['capture.guest_storage_budget_bytes' => $a + $meta + 4]);

    sendRaw('POST', '/c/'.activeKey(projectIdOfKey($keyB)), '123456')->assertOk();   // over budget
    sendRaw('POST', '/c/'.activeKey(projectIdOfKey($keyB)), '1234')->assertOk();     // fits exactly
    expect(capturesOf(projectIdOfKey($keyA)))->toBe(1)
        ->and(capturesOf(projectIdOfKey($keyB)))->toBe(1)
        ->and((int) DB::table('instance_counters')->value('guest_stored_bytes'))->toBe($a + $meta + 4);

    asGuest($cookieA)->post('/session/end');
    expect((int) DB::table('instance_counters')->value('guest_stored_bytes'))->toBe($meta + 4);
    sendRaw('POST', '/c/'.activeKey(projectIdOfKey($keyB)), '123456')->assertOk();
    expect(capturesOf(projectIdOfKey($keyB)))->toBe(2);
});

it('counts the account quota per day and per stored bytes (decision 58)', function (): void {
    $probe = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($probe->id), '');
    $meta = (int) DB::table('captures')->where('project_id', $probe->id)->value('counted_bytes');

    config(['workspace.quota.captures_per_day' => 2, 'workspace.quota.stored_body_bytes' => 2 * $meta + 10]);
    $project = userProject(makeUser());
    $key = activeKey($project->id);

    sendRaw('POST', "/c/{$key}", '12345')->assertOk();
    sendRaw('POST', "/c/{$key}", '123456')->assertOk();   // bytes over
    sendRaw('POST', "/c/{$key}", '12345')->assertOk();
    sendRaw('POST', "/c/{$key}", '')->assertOk();         // daily count over
    expect(capturesOf($project->id))->toBe(2);

    $this->travel(1)->days();
    config(['workspace.quota.stored_body_bytes' => 3 * $meta + 100]);
    sendRaw('POST', "/c/{$key}", '')->assertOk();
    expect(capturesOf($project->id))->toBe(3)
        ->and(DB::table('captures')->where('project_id', $project->id)->orderBy('seq')->pluck('seq')->all())->toBe([1, 2, 3]);
});

it('never reuses a seq: a dropped request does not consume one', function (): void {
    config(['workspace.quota.captures_per_day' => 1]);
    $project = userProject(makeUser());
    $key = activeKey($project->id);
    sendRaw('POST', "/c/{$key}");
    sendRaw('POST', "/c/{$key}");
    $this->travel(1)->days();
    sendRaw('POST', "/c/{$key}");

    expect(DB::table('captures')->where('project_id', $project->id)->orderBy('seq')->pluck('seq')->all())->toBe([1, 2])
        ->and(Project::query()->whereKey($project->id)->value('next_capture_seq'))->toBe(3);
});

/*
 * DL-2 (TM-T157): a capture right after DELETE /account stores nothing.
 */

it('stores nothing for a capture sent right after DELETE /account, even with a warm key cache', function (): void {
    $user = makeUser();
    enableTotp($user);
    $project = userProject($user);
    $key = activeKey($project->id);
    sendRaw('POST', "/c/{$key}", 'before')->assertOk();   // warms the cache: owner live
    expect(capturesOf($project->id))->toBe(1);

    actAs($user, 'totp');
    confirmDeletion();
    $this->delete('/account', ['confirm' => true])->assertRedirect('/');

    $after = sendRaw('POST', "/c/{$key}", 'after');
    expect(observable($after))->toBe(observable(sendRaw('POST', '/c/Zz9Zz9Zz9Zz9', 'after')))
        ->and(capturesOf($project->id))->toBe(1);
});

it('stores nothing after the tombstone even when the cached resolution was not evicted', function (): void {
    config(['capture.resolve_cache_seconds' => 3600]);
    $user = makeUser();
    $project = userProject($user);
    $key = activeKey($project->id);
    sendRaw('POST', "/c/{$key}")->assertOk();

    // Tombstone without the event (a lost listener): the transaction re-check still holds.
    Event::fake([AccountDeleted::class]);
    app(AccountDeletion::class)->tombstone($user, 'admin', RequestContext::console());
    sendRaw('POST', "/c/{$key}")->assertOk();

    expect(capturesOf($project->id))->toBe(1);
});

it('stores into the claimed project at once after a claim (cache evicted)', function (): void {
    [$cookie, $key] = guestProject();
    $projectId = projectIdOfKey($key);
    $captureKey = activeKey($projectId);
    sendRaw('POST', "/c/{$captureKey}", 'as guest')->assertOk();

    $user = makeUser();
    asGuest($cookie)->get('/sign-in');
    asGuest($cookie)->post('/sign-in/password', ['email' => $user->email, 'password' => FIXTURE_PASSWORD]);
    expect(Project::query()->whereKey($projectId)->value('is_guest'))->toBeFalse();

    sendRaw('POST', "/c/{$captureKey}", 'as user')->assertOk();
    expect(capturesOf($projectId))->toBe(2);
});

/*
 * Capture host (HD-7).
 */

it('serves only /c/… on the capture host, and no /c/… on the app host', function (): void {
    config(['capture.host' => 'in.example.test']);
    $project = userProject(makeUser());
    $key = activeKey($project->id);

    sendRaw('GET', '/projects', '', [], ['HTTP_HOST' => 'in.example.test'])->assertNotFound();
    sendRaw('POST', "/c/{$key}", '', [], ['HTTP_HOST' => 'in.example.test:443'])->assertOk()->assertExactJson(['received' => true]);
    sendRaw('POST', "/c/{$key}", '', [], ['HTTP_HOST' => 'app.example.test'])->assertNotFound();

    expect(capturesOf($project->id))->toBe(1);
});

it('raises CaptureReceived after commit with id, project and seq', function (): void {
    Event::fake([CaptureReceived::class]);
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id));

    $capture = lastCapture($project->id);
    Event::assertDispatched(CaptureReceived::class, fn (CaptureReceived $e): bool => $e->captureId === $capture->id
        && $e->projectId === $project->id && $e->seq === 1);
});

it('provisions one active 12-character key per project, guest projects included', function (): void {
    $project = userProject(makeUser());
    [, $guestKey] = guestProject();

    foreach ([$project->id, projectIdOfKey($guestKey)] as $id) {
        $urls = CaptureUrl::query()->where('project_id', $id)->get();
        expect($urls)->toHaveCount(1)
            ->and($urls[0]->state)->toBe('active')
            ->and($urls[0]->capture_key)->toMatch('/^[A-Za-z0-9]{12}$/');
    }
});
