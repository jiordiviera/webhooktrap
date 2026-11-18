<?php

declare(strict_types=1);

use App\Modules\Capture\Domain\Capture;
use App\Modules\Workspace\Application\Projects;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CaptureHelpers.php';

/*
 * Slice 4: S-03 capture stream, S-04 delivery detail, raw download and the
 * guest meter (contracts.md "Slice 4 notes").
 */

beforeEach(function (): void {
    Mail::fake();
    config(['capture.public_origin' => 'https://in.example.test']);
});

/** Sends one delivery to the project's active capture URL. */
function deliver(string $projectId, string $body = '{"ping":true}', array $headers = ['Content-Type' => 'application/json'], string $rest = ''): void
{
    sendRaw('POST', '/c/'.activeKey($projectId).$rest, $body, $headers)->assertOk();
}

/** Derived facts are written by the Packs derivation job later; tests set them directly. */
function derive(string $projectId, int $seq, array $facts): void
{
    Capture::query()->where('project_id', $projectId)->where('seq', $seq)->update($facts);
}

/*
 * Ownership: one not-found for everyone but the owner, on every route.
 */

it('answers not found on the stream, the detail page and the raw download to anyone but the owner (TM-13)', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id);
    [$guestCookie, $guestKey] = guestProject();
    deliver(projectIdOfKey($guestKey));

    foreach (['', '/d/1', '/d/1/raw'] as $suffix) {
        newBrowser();
        $this->get("/p/{$project->key}{$suffix}")->assertNotFound();
        actAs(makeUser());
        $this->get("/p/{$project->key}{$suffix}")->assertNotFound();
        newBrowser();
        asGuest($guestCookie)->get("/p/{$project->key}{$suffix}")->assertNotFound();

        // A user is not the owner of a guest project, and another guest is not either.
        actAs($owner);
        $this->get("/p/{$guestKey}{$suffix}")->assertNotFound();
        newBrowser();
        [$otherGuest] = guestProject();
        asGuest($otherGuest)->get("/p/{$guestKey}{$suffix}")->assertNotFound();

        // The owners themselves.
        actAs($owner);
        $this->get("/p/{$project->key}{$suffix}")->assertOk();
        newBrowser();
        asGuest($guestCookie)->get("/p/{$guestKey}{$suffix}")->assertOk();
    }
});

it('answers not found for an unknown capture number and for a deleted project', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id);
    actAs($owner);

    $this->get("/p/{$project->key}/d/2")->assertNotFound();
    $this->get("/p/{$project->key}/d/2/raw")->assertNotFound();
    $this->get("/p/{$project->key}/d/0")->assertNotFound();
    $this->get("/p/{$project->key}/d/abc")->assertNotFound();

    app(Projects::class)->delete($project);
    foreach (['', '/d/1', '/d/1/raw'] as $suffix) {
        $this->get("/p/{$project->key}{$suffix}")->assertNotFound();
    }
});

it('never reads another project\'s capture through a known number', function (): void {
    $owner = makeUser();
    $mine = userProject($owner, 'Mine');
    $theirs = userProject(makeUser(), 'Theirs');
    deliver($theirs->id, '{"secret":"theirs"}');
    actAs($owner);

    $this->get("/p/{$mine->key}/d/1")->assertNotFound();
    $this->get("/p/{$mine->key}/d/1/raw")->assertNotFound();
    $this->get("/p/{$mine->key}")->assertInertia(fn (Assert $page) => $page
        ->where('captures.latest', [])->where('selected', null));
});

/*
 * Stream: order, pagination, search, groups.
 */

it('lists the newest captures first, a page at a time, with older pages by `before`', function (): void {
    config(['capture.stream.page_size' => 3]);
    $owner = makeUser();
    $project = userProject($owner);
    foreach (range(1, 7) as $i) {
        deliver($project->id, '{"n":'.$i.'}');
    }
    actAs($owner);

    $this->get("/p/{$project->key}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Capture/Stream')
        ->where('project', ['key' => $project->key, 'name' => 'Brindle Coffee', 'isGuest' => false])
        ->where('captureUrl', 'https://in.example.test/c/'.activeKey($project->id))
        ->where('captures.latest.0.seq', 7)
        ->where('captures.latest.1.seq', 6)
        ->where('captures.latest.2.seq', 5)
        ->count('captures.latest', 3)
        ->where('captures.stats.total', 7)
        ->where('captures.stats.matching', 7)
        ->where('captures.stats.hasOlder', true)
        ->where('captures.stats.lastReceivedAt', fn (string $at): bool => str_ends_with($at, 'Z'))
        ->missing('captures.older')
        ->missing('groups')
        ->where('query', ['sel' => null, 'tab' => 'verdict', 'view' => 'flat', 'q' => null, 'group' => null])
        ->where('selected.seq', 7)
        ->count('providers', 7)); // six packs + curl

    $this->get("/p/{$project->key}?before=5")->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('captures.older', fn (Assert $reload) => $reload
            ->where('captures.older.rows.0.seq', 4)
            ->where('captures.older.rows.2.seq', 2)
            ->where('captures.older.nextBefore', 2)));
    $this->get("/p/{$project->key}?before=2")->assertInertia(fn (Assert $page) => $page
        ->reloadOnly('captures.older', fn (Assert $reload) => $reload
            ->where('captures.older.rows', fn ($rows): bool => count($rows) === 1 && $rows[0]['seq'] === 1)
            ->where('captures.older.nextBefore', null)));
});

it('gives each row the CaptureRow shape with the signature not checked yet', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id, '{"id":"evt_1"}', ['Content-Type' => 'application/json', 'Stripe-Signature' => 't=1,v1=ab'], '/hooks/stripe');
    actAs($owner);

    $this->get("/p/{$project->key}")->assertInertia(fn (Assert $page) => $page
        ->where('captures.latest.0', fn ($row): bool => array_keys($row->all()) === [
            'seq', 'receivedAt', 'method', 'path', 'provider', 'providerName', 'eventType', 'groupKey', 'groupLabel',
            'signature', 'bodySize', 'bodyKept', 'shapeVerdict',
        ])
        ->where('captures.latest.0.method', 'POST')
        ->where('captures.latest.0.path', '/c/'.activeKey($project->id).'/hooks/stripe')
        // Derived at once (sync queue in tests): Stripe-Signature names the provider.
        ->where('captures.latest.0.provider', 'stripe')
        ->where('captures.latest.0.providerName', 'Stripe')
        ->where('captures.latest.0.signature', ['state' => 'not_checked'])
        ->where('captures.latest.0.bodySize', 14)
        ->where('captures.latest.0.bodyKept', true));
});

it('filters by event type, path, provider event id, object id and #seq, never by body', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id, '{"email":"jonah.reyes@example.com"}', rest: '/hooks/stripe');
    deliver($project->id, '{}', rest: '/hooks/github');
    deliver($project->id, '{}', rest: '/hooks/shopify');
    derive($project->id, 1, ['event_type' => 'invoice.paid', 'provider' => 'stripe', 'provider_event_id' => 'evt_1QK9']);
    DB::statement("UPDATE captures SET object_ids = ARRAY['sub_1QH3UF1H'] WHERE project_id = ? AND seq = 3", [$project->id]);
    actAs($owner);

    $seqs = fn (string $q): array => array_column(
        $this->get("/p/{$project->key}?q=".urlencode($q))->viewData('page')['props']['captures']['latest'], 'seq');

    expect($seqs('invoice.paid'))->toBe([1])
        ->and($seqs('INVOICE'))->toBe([1])
        ->and($seqs('github'))->toBe([2])
        ->and($seqs('evt_1QK9'))->toBe([1])
        ->and($seqs('sub_1QH3UF1H'))->toBe([3])
        ->and($seqs('#2'))->toBe([2])
        ->and($seqs('hooks'))->toBe([3, 2, 1])
        ->and($seqs('jonah.reyes@example.com'))->toBe([])
        ->and($seqs('%'))->toBe([])
        ->and($seqs('_'))->toBe([]);

    $this->get("/p/{$project->key}?q=github")->assertInertia(fn (Assert $page) => $page
        ->where('query.q', 'github')
        ->where('captures.stats.total', 3)
        ->where('captures.stats.matching', 1)
        ->where('captures.stats.hasOlder', false));
});

it('groups by the derived group key, ungrouped last-resort, most recent activity first', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    foreach (range(1, 4) as $i) {
        deliver($project->id);
    }
    actAs($owner);

    // Nothing derived yet (Packs): flat by default, one Ungrouped summary.
    $this->get("/p/{$project->key}")->assertInertia(fn (Assert $page) => $page
        ->where('query.view', 'flat')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->count('groups', 1)
            ->where('groups.0.key', null)
            ->where('groups.0.count', 4)
            ->where('groups.0.worstSignature', 'not_checked')));

    derive($project->id, 1, ['group_key' => 'sub_1QH', 'provider' => 'stripe', 'sig_state' => 'valid']);
    derive($project->id, 2, ['group_key' => 'sub_1QH', 'provider' => 'stripe', 'sig_state' => 'invalid', 'sig_reason' => 'different_secret']);
    derive($project->id, 3, ['group_key' => 'order_1042', 'provider' => 'shopify', 'sig_state' => 'valid']);

    $this->get("/p/{$project->key}")->assertInertia(fn (Assert $page) => $page
        ->where('query.view', 'grouped')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->count('groups', 3)
            ->where('groups.0.key', null)          // #04, newest
            ->where('groups.1.key', 'order_1042')  // #03
            ->where('groups.2.key', 'sub_1QH')     // #01, #02
            ->where('groups.2.count', 2)
            ->where('groups.2.firstSeq', 1)
            ->where('groups.2.lastSeq', 2)
            ->where('groups.2.provider', 'stripe')
            ->where('groups.2.worstSignature', 'invalid')));

    $seqs = fn (string $query): array => array_column(
        $this->get("/p/{$project->key}?{$query}")->viewData('page')['props']['captures']['latest'], 'seq');
    expect($seqs('group=sub_1QH'))->toBe([2, 1])
        ->and($seqs('group=ungrouped'))->toBe([4])
        ->and($seqs('group=nope'))->toBe([])
        ->and($seqs('view=flat'))->toBe([4, 3, 2, 1]);

    $this->get("/p/{$project->key}?view=flat&tab=raw&sel=02")->assertInertia(fn (Assert $page) => $page
        ->where('query', ['sel' => 2, 'tab' => 'raw', 'view' => 'flat', 'q' => null, 'group' => null])
        ->where('selected.seq', 2)
        ->where('selected.signature', ['state' => 'invalid', 'reason' => 'different_secret']));
});

it('ignores malformed query values and selects nothing for an unknown number', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id);
    actAs($owner);

    $this->get("/p/{$project->key}?sel=x&tab=evil&view=tree&q=&group=")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('query', ['sel' => null, 'tab' => 'verdict', 'view' => 'flat', 'q' => null, 'group' => null])
        ->where('selected.seq', 1));
    $this->get("/p/{$project->key}?sel=99")->assertInertia(fn (Assert $page) => $page
        ->where('query.sel', 99)->where('selected', null));
});

it('declares the stream props live on the project channel (ADR-004 amendment 1)', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    actAs($owner);

    $page = $this->get("/p/{$project->key}")->viewData('page');
    $listener = [['channel' => ['name' => 'project.'.$project->id, 'type' => 'private'], 'events' => ['project.captures']]];

    expect($page['liveProps']['captures.latest'])->toBe(['listeners' => $listener, 'throttle' => 250])
        ->and($page['liveProps']['captures.stats'])->toBe(['listeners' => $listener, 'throttle' => 250])
        ->and($page['liveProps']['groups'])->toBe(['listeners' => $listener, 'throttle' => 250])
        ->and($page['liveProps']['captureUrl']['listeners'][0]['events'])->toBe(['project.capture_url'])
        ->and($page['liveProps'])->not->toHaveKey('meter')
        ->and($page['liveProps'])->not->toHaveKey('selected');
});

/*
 * Guest meter.
 */

it('shows the guest meter: deliveries of 100, runs of 10 and the time left, live with the stream', function (): void {
    [$cookie, $key] = guestProject();
    $projectId = projectIdOfKey($key);
    deliver($projectId);
    deliver($projectId);
    deliver($projectId);

    $page = asGuest($cookie)->get("/p/{$key}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('project.isGuest', true)
        ->where('meter.captures', ['used' => 3, 'limit' => 100])
        ->where('meter.runs', ['used' => 0, 'limit' => 10])
        ->where('meter.secondsLeft', fn (int $s): bool => $s > 86_000 && $s <= 86_400)
        ->where('meter.expiresAt', fn (string $at): bool => str_ends_with($at, 'Z')))->viewData('page');
    expect($page['liveProps']['meter']['listeners'][0]['events'])->toBe(['project.captures']);

    $this->travel(23)->hours();
    asGuest($cookie)->get("/p/{$key}")->assertInertia(fn (Assert $page) => $page
        ->where('meter.secondsLeft', fn (int $s): bool => $s > 3_000 && $s <= 3_600));
});

/*
 * Detail.
 */

it('renders the delivery detail with headers in order and redacted values as their placeholder', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id, '{"id":"evt_1QK9BpyOmOFtoVkdOlN4pjPU","object":"event"}', [
        'Content-Type' => 'application/json; charset=utf-8',
        'Authorization' => 'Bearer sk_live_not_a_real_secret_0123456789',
        'Stripe-Signature' => 't=1794405484,v1=e8bc163c',
        'Cookie' => 'session=abc',
    ], '/hooks/stripe?source=test');
    deliver($project->id);
    deliver($project->id);
    actAs($owner);

    $response = $this->get("/p/{$project->key}/d/1")->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Capture/Detail')
        ->where('capture.seq', 1)
        ->where('capture.id', fn (string $id): bool => str_starts_with($id, 'cap_'))
        ->where('capture.rawQuery', 'source=test')
        ->where('capture.rawTarget', '/c/'.activeKey($project->id).'/hooks/stripe?source=test')
        ->where('capture.redactedHeaders', ['Authorization', 'Cookie'])
        ->where('capture.headerFidelity', 'sapi')
        ->where('capture.signature', ['state' => 'not_checked'])
        ->where('capture.bodySha256', hash('sha256', '{"id":"evt_1QK9BpyOmOFtoVkdOlN4pjPU","object":"event"}'))
        ->where('capture.body', [
            'encoding' => 'utf8', 'text' => '{"id":"evt_1QK9BpyOmOFtoVkdOlN4pjPU","object":"event"}',
            'truncated' => false, 'shownBytes' => 54, 'escaped' => false,
        ])
        ->where('capture.hiddenCharacters', false)
        ->where('prev', null)
        ->where('next', 2));

    $headers = $response->viewData('page')['props']['capture']['headers'];
    $byName = array_column($headers, 1, 0);
    expect($byName['Authorization'])->toBe('[redacted · '.strlen('Bearer sk_live_not_a_real_secret_0123456789').' bytes]')
        ->and($byName['Cookie'])->toBe('[redacted · 11 bytes]')
        ->and($byName['Stripe-Signature'])->toBe('t=1794405484,v1=e8bc163c')
        ->and(json_encode($response->viewData('page')))->not->toContain('sk_live_not_a_real_secret')
        // Received order: the stored order, index for index.
        ->and($headers)->toBe(Capture::query()->where('project_id', $project->id)->where('seq', 1)->sole()->headers);

    $this->get("/p/{$project->key}/d/2")->assertInertia(fn (Assert $page) => $page->where('prev', 1)->where('next', 3));
});

it('shows hidden control, bidi and format characters escaped in headers and bodies (A09 S3-16)', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id, "line1\n\tline2\u{202E}evil\u{200B}\x1B[31m", [
        'Content-Type' => 'text/plain',
        'X-Note' => "abc\u{202E}fed\u{2066}",
    ]);
    actAs($owner);

    $page = $this->get("/p/{$project->key}/d/1")->viewData('page')['props']['capture'];
    $note = array_column($page['headers'], 1, 0)['X-Note'];

    expect($note)->toBe('abc\u{202E}fed\u{2066}')
        ->and($page['body']['text'])->toBe("line1\n\tline2".'\u{202E}evil\u{200B}\u{001B}[31m')
        ->and($page['body']['escaped'])->toBeTrue()
        ->and($page['hiddenCharacters'])->toBeTrue();
});

it('sends binary bodies as base64 and truncates large bodies', function (): void {
    config(['capture.inspector.text_max_bytes' => 10, 'capture.inspector.binary_max_bytes' => 4]);
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id, "\x89PNG\r\n\x1A\n\x00\x00", ['Content-Type' => 'image/png']);
    deliver($project->id, str_repeat('é', 8), ['Content-Type' => 'text/plain']); // 16 bytes
    actAs($owner);

    $this->get("/p/{$project->key}/d/1")->assertInertia(fn (Assert $page) => $page
        ->where('capture.body', ['encoding' => 'base64', 'text' => base64_encode("\x89PNG"), 'truncated' => true, 'shownBytes' => 4, 'escaped' => false])
        ->where('capture.bodySize', 10));
    $this->get("/p/{$project->key}/d/2")->assertInertia(fn (Assert $page) => $page
        ->where('capture.body', ['encoding' => 'utf8', 'text' => str_repeat('é', 5), 'truncated' => true, 'shownBytes' => 10, 'escaped' => false]));
});

it('explains a body that was not kept: no body, the hash, and no raw download', function (): void {
    config(['capture.body_keep_max_bytes' => 4]);
    $owner = makeUser();
    $project = userProject($owner);
    deliver($project->id, '0123456789');
    actAs($owner);

    $this->get("/p/{$project->key}/d/1")->assertInertia(fn (Assert $page) => $page
        ->where('capture.bodyKept', false)
        ->where('capture.bodySize', 10)
        ->where('capture.bodySha256', hash('sha256', '0123456789'))
        ->missing('capture.body'));
    $this->get("/p/{$project->key}/d/1/raw")->assertNotFound();
});

/*
 * Raw download.
 */

it('downloads the exact raw bytes with safe headers, never inline (ADR-005 am. 1 §7, TM-06)', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    $body = "<html><script>alert(1)</script>\x00\xFF</html>";
    deliver($project->id, $body, ['Content-Type' => 'text/html']);
    actAs($owner);

    $response = $this->get("/p/{$project->key}/d/1/raw")->assertOk();

    expect($response->getContent())->toBe($body)
        ->and($response->headers->get('Content-Type'))->toBe('application/octet-stream')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="delivery-01.bin"')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Content-Security-Policy'))->toStartWith('sandbox')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
        ->and($response->headers->get('Content-Length'))->toBe((string) strlen($body));
});

it('serves the raw download to a guest owner too', function (): void {
    [$cookie, $key] = guestProject();
    deliver(projectIdOfKey($key), 'guest-bytes');

    expect(asGuest($cookie)->get("/p/{$key}/d/1/raw")->assertOk()->getContent())->toBe('guest-bytes');
});
