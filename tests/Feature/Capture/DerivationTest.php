<?php

declare(strict_types=1);

use App\Infrastructure\Broadcasting\LiveTrigger;
use App\Modules\Capture\Application\CaptureDerivation;
use App\Modules\Capture\Application\CaptureRederivation;
use App\Modules\Capture\Domain\Capture;
use App\Modules\Capture\Domain\PgArray;
use App\Modules\Capture\Domain\ShapeVerdict;
use App\Modules\Capture\Events\CaptureDerived;
use App\Modules\Capture\Jobs\DeriveCapture;
use App\Modules\Capture\Jobs\RederiveProjectCaptures;
use App\Modules\Packs\Application\PackCatalog;
use App\Modules\Packs\Domain\PackSet;
use App\Modules\Packs\Domain\Shape;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CaptureHelpers.php';

/*
 * Packs slice: capture derivation (domain §4; scenarios-and-judge §10;
 * realistic-data.md §2–3 for the expected values). The test queue is sync,
 * so a delivery is derived before `sendRaw` returns.
 */

/** @return array<string, mixed> realistic-data.json */
function realisticData(): array
{
    static $data = null;

    return $data ??= json_decode((string) file_get_contents(base_path('project/design/realistic-data.json')), true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> one RD delivery by seq */
function rdDelivery(int $seq): array
{
    foreach (realisticData()['deliveries'] as $d) {
        if ($d['seq'] === $seq) {
            return $d;
        }
    }
    throw new RuntimeException("no RD delivery #{$seq}");
}

/** Sends an RD delivery as its provider did (Host and Content-Length come from the request itself). */
function sendRd(string $projectId, int $seq): Capture
{
    $d = rdDelivery($seq);
    $headers = [];
    foreach ($d['headers'] as [$name, $value]) {
        if (! in_array(strtolower($name), ['host', 'content-length'], true)) {
            $headers[$name] = $value;
        }
    }
    sendRaw('POST', '/c/'.activeKey($projectId), $d['body_raw'], $headers)->assertOk();

    return lastCapture($projectId);
}

/** Derived columns of a capture, as stored. */
function derivedOf(string $captureId): array
{
    $row = (array) DB::table('captures')->where('id', $captureId)->first([
        'provider', 'provider_name', 'provider_event_id', 'event_type', 'api_version', 'object_ids',
        'group_key', 'group_label', 'group_anchor', 'shape', 'shape_verdict', 'derived_with_pack',
    ]);
    $row['object_ids'] = PgArray::parse($row['object_ids']);

    return $row;
}

const RD_PROVIDERS = ['Stripe' => 'stripe', 'Shopify' => 'shopify', 'GitHub' => 'github', 'Parcelbird' => 'standard_webhooks', 'Unknown' => null];

const RD_GROUP_LABELS = [
    'grp_stripe_sub_1QH3UF1HXb5oPQT3wfdt4drI' => 'sub_1QH3UF1HXb5oPQT3wfdt4drI',
    'grp_shopify_order_5873201946743' => 'Order #1042',
    'grp_parcelbird_shp_1c263SsdQymuOjhjztsP' => 'shp_1c263SsdQymuOjhjztsP',
    'grp_github_brindle/brindle-app' => 'brindle/brindle-app',
    'grp_shopify_order_5873204390011' => 'Order #1043',
    'grp_stripe_sub_1QGutsMKKaWLefy3yMsaqS1C' => 'sub_1QGutsMKKaWLefy3yMsaqS1C',
];

/*
 * Pack files.
 */

it('loads every pack the manifest names, pinned to one version, in detection order', function (): void {
    $set = PackSet::load(base_path('packs'));

    expect(array_map(fn ($p) => $p->ref(), $set->packs))->toBe(['stripe@1.0.0', 'github@1.0.0', 'shopify@1.0.0', 'standard_webhooks@1.0.0'])
        ->and(app(PackCatalog::class)->currentRefs())->toContain('none@1.0.0')
        ->and(app(PackCatalog::class)->providers())->toBe([
            ['id' => 'stripe', 'name' => 'Stripe'], ['id' => 'github', 'name' => 'GitHub'],
            ['id' => 'shopify', 'name' => 'Shopify'], ['id' => 'standard_webhooks', 'name' => 'Standard Webhooks'],
        ]);
    // Top-level keys follow the JSON Schema shared with the engine (agent/pack).
    $schema = json_decode((string) file_get_contents(base_path('packs/_schema/pack.schema.json')), true, 512, JSON_THROW_ON_ERROR);
    foreach ($set->packs as $pack) {
        $file = json_decode((string) file_get_contents(base_path("packs/{$pack->id}/{$pack->version}.json")), true, 512, JSON_THROW_ON_ERROR);
        expect(array_diff(array_keys($file), array_keys($schema['properties'])))->toBe([], $pack->id)
            ->and(array_diff($schema['required'], array_keys($file)))->toBe([], $pack->id);
    }
    expect(json_decode((string) file_get_contents(base_path('packs/_schema/manifest.schema.json')), true))->toBeArray();
});

it('refuses a pack whose id differs from its path', function (): void {
    $dir = sys_get_temp_dir().'/wt-packs-'.bin2hex(random_bytes(4));
    mkdir($dir.'/stripe', 0777, true);
    file_put_contents($dir.'/manifest.json', json_encode(['format' => 1, 'version' => '1.0.0', 'packs' => [['id' => 'stripe', 'version' => '1.0.0']], 'unknown' => []]));
    $pack = json_decode((string) file_get_contents(base_path('packs/stripe/1.0.0.json')), true);
    $pack['id'] = 'github';
    file_put_contents($dir.'/stripe/1.0.0.json', json_encode($pack));

    expect(fn () => PackSet::load($dir))->toThrow(RuntimeException::class);
});

/*
 * Realistic data: every delivery, in arrival order, into one project.
 */

it('derives provider, event type, ids, object ids and group of every realistic-data delivery', function (): void {
    $project = userProject(makeUser());
    $ids = [];
    foreach (range(1, 13) as $seq) {
        $ids[$seq] = sendRd($project->id, $seq)->id;
    }

    $groups = collect(realisticData()['groups'])->keyBy(fn (array $g) => $g['id'] ?? 'ungrouped');
    foreach (range(1, 13) as $seq) {
        $rd = rdDelivery($seq);
        $row = derivedOf($ids[$seq]);
        $provider = RD_PROVIDERS[$rd['provider']];

        expect($row['provider'])->toBe($provider, "#{$seq} provider")
            ->and($row['provider_name'])->toBe($provider === null ? null : $rd['provider'], "#{$seq} provider name")
            ->and($row['event_type'])->toBe($provider === null ? null : $rd['event_type'], "#{$seq} event type")
            ->and($row['group_key'])->toBe($rd['group'], "#{$seq} group key")
            ->and($row['derived_with_pack'])->toBe($provider === null ? 'none@1.0.0' : "{$provider}@1.0.0");
        if ($rd['provider_event_id'] !== null) {
            expect($row['provider_event_id'])->toBe($rd['provider_event_id'], "#{$seq} event id");
        }
        if ($rd['group'] !== null) {
            expect($row['group_label'])->toBe(RD_GROUP_LABELS[$rd['group']], "#{$seq} group label");
        }
    }

    // GRP-1: the object ids RD lists for each group are among its members' ids.
    foreach ($groups as $key => $group) {
        $union = [];
        foreach ($group['delivery_ids'] as $deliveryId) {
            $seq = collect(realisticData()['deliveries'])->firstWhere('id', $deliveryId)['seq'];
            $union = [...$union, ...derivedOf($ids[$seq])['object_ids']];
        }
        expect(array_diff($group['object_ids'], $union))->toBe([], "group {$key}");
    }

    // Header-derived ids and versions (DUP-1 ids; Stripe's api_version is in the body).
    expect(derivedOf($ids[1])['api_version'])->toBe('2024-06-20')
        ->and(derivedOf($ids[5])['api_version'])->toBe('2025-10')
        ->and(derivedOf($ids[5])['provider_event_id'])->toBe('ed0245a2-cbbb-478f-a0bd-241c1d85e8ac')
        ->and(derivedOf($ids[7])['provider_event_id'])->toBe('c3581c41-6588-44bc-ab89-053c62094f2b')
        ->and(derivedOf($ids[8])['provider_event_id'])->toBe('msg_2pQxtbVXQpZuzuZFIoKZ2QIU2Lb')
        // GRP-2: #03 joins the subscription group through #01's invoice id; customer ids never link.
        ->and(derivedOf($ids[3])['object_ids'])->toBe(['pi_3QK4RAqNlKszZLwoglztTHYj', 'in_1QKImRcZJ8HR9BZYostEV4ui'])
        ->and(derivedOf($ids[1])['group_anchor'])->toBe('subscription');
});

it('detects each provider from its headers', function (array $headers, ?string $provider, ?string $name): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '{"type":"x.y","data":{}}', $headers + ['Content-Type' => 'application/json'])->assertOk();

    $row = derivedOf(lastCapture($project->id)->id);
    expect($row['provider'])->toBe($provider)->and($row['provider_name'])->toBe($name);
})->with([
    'Stripe' => [['Stripe-Signature' => 't=1,v1=ab'], 'stripe', 'Stripe'],
    'GitHub' => [['X-GitHub-Event' => 'ping'], 'github', 'GitHub'],
    'Shopify' => [['X-Shopify-Topic' => 'orders/create'], 'shopify', 'Shopify'],
    'Standard Webhooks, sender from User-Agent' => [['webhook-id' => 'msg_1', 'webhook-signature' => 'v1,abc', 'User-Agent' => 'Acme-Webhooks/1.0'], 'standard_webhooks', 'Acme'],
    'Standard Webhooks, generic client' => [['webhook-id' => 'msg_1', 'webhook-signature' => 'v1,abc', 'User-Agent' => 'python-requests/2.32'], 'standard_webhooks', 'Standard Webhooks'],
    'webhook-id alone is not Standard Webhooks' => [['webhook-id' => 'msg_1'], null, null],
    'empty signature header' => [['Stripe-Signature' => ''], null, null],
    'nothing' => [['User-Agent' => 'curl/8.7.1'], null, null],
]);

it('leaves an unknown provider null, ungrouped and without a shape verdict', function (): void {
    $project = userProject(makeUser());
    $capture = sendRd($project->id, 13);

    $row = derivedOf($capture->id);
    expect($row['provider'])->toBeNull()
        ->and($row['provider_name'])->toBeNull()
        ->and($row['event_type'])->toBeNull()
        ->and($row['group_key'])->toBeNull()
        ->and($row['shape_verdict'])->toBeNull()
        ->and(json_decode($row['shape'], true))->toBe([['from', 'string'], ['note', 'string'], ['ping', 'boolean']])
        ->and($row['derived_with_pack'])->toBe('none@1.0.0');
});

it('groups an unknown sender on the first data id (GRP-4)', function (): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '{"data":{"name":"x","order_id":42,"id":"later"}}', ['Content-Type' => 'application/json'])->assertOk();

    $row = derivedOf(lastCapture($project->id)->id);
    expect($row['group_key'])->toBe('grp_unknown_42')->and($row['group_anchor'])->toBe('order')->and($row['object_ids'])->toBe(['42']);
});

/*
 * Grouping and shape across captures.
 */

it('merges groups whatever the arrival order and re-keys the earlier rows (GRP-2, GRP-3)', function (): void {
    $project = userProject(makeUser());
    $three = sendRd($project->id, 3);
    expect(derivedOf($three->id)['group_key'])->toBe('grp_stripe_in_1QKImRcZJ8HR9BZYostEV4ui');

    $one = sendRd($project->id, 1);

    expect(derivedOf($one->id)['group_key'])->toBe('grp_stripe_sub_1QH3UF1HXb5oPQT3wfdt4drI')
        ->and(derivedOf($three->id)['group_key'])->toBe('grp_stripe_sub_1QH3UF1HXb5oPQT3wfdt4drI')
        ->and(derivedOf($three->id)['group_anchor'])->toBe('subscription');
});

it('gives shape verdicts against earlier captures of the same event type and API version (SHP-2, SHP-3)', function (): void {
    $project = userProject(makeUser());
    $first = sendRd($project->id, 5);      // orders/create, first one
    $paid = sendRd($project->id, 6);       // orders/paid: its own baseline
    $second = sendRd($project->id, 10);    // orders/create again

    $d = rdDelivery(10);
    $headers = collect($d['headers'])->reject(fn ($h) => in_array(strtolower($h[0]), ['host', 'content-length'], true))
        ->mapWithKeys(fn ($h) => [$h[0] => $h[1]])->all();
    $extra = json_decode($d['body_raw'], true);
    $extra['note'] = 'gift';
    sendRaw('POST', '/c/'.activeKey($project->id), json_encode($extra), $headers)->assertOk();
    $third = lastCapture($project->id);

    expect(derivedOf($first->id)['shape_verdict'])->toBe('new')
        ->and(derivedOf($paid->id)['shape_verdict'])->toBe('new')
        ->and(derivedOf($second->id)['shape_verdict'])->toBe('matches')
        ->and(derivedOf($third->id)['shape_verdict'])->toBe('differs');
});

/*
 * Hostile bodies and headers: bounded, never fatal.
 */

it('survives hostile bodies without crashing or holding much memory', function (string $body, array $headers, ?string $provider, ?string $eventType): void {
    $project = userProject(makeUser());
    $before = memory_get_usage(true);
    sendRaw('POST', '/c/'.activeKey($project->id), $body, $headers)->assertOk();

    $row = derivedOf(lastCapture($project->id)->id);
    expect($row['derived_with_pack'])->not->toBeNull()
        ->and($row['provider'])->toBe($provider)
        ->and($row['event_type'])->toBe($eventType)
        ->and($row['shape'])->toBeNull()
        ->and(memory_get_usage(true) - $before)->toBeLessThan(96 * 1024 * 1024);
})->with([
    'over the JSON size limit' => [fn () => '{"type":"invoice.paid","pad":"'.str_repeat('a', 2 * 1024 * 1024).'"}', ['Stripe-Signature' => 't=1,v1=a'], 'stripe', null],
    'nested 100 000 deep' => [fn () => str_repeat('[', 100000).str_repeat(']', 100000), ['Stripe-Signature' => 't=1,v1=a'], 'stripe', null],
    'nested 65 objects deep' => [fn () => str_repeat('{"a":', 65).'1'.str_repeat('}', 65), ['X-Shopify-Topic' => 'orders/create'], 'shopify', 'orders/create'],
    'invalid UTF-8' => ["{\"type\":\"a\xC3\x28\"}", ['X-GitHub-Event' => 'push'], 'github', 'push'],
    'too many paths' => [fn () => json_encode(array_combine(array_map(fn ($i) => "k{$i}", range(1, 5000)), range(1, 5000))), ['X-GitHub-Event' => 'push'], 'github', 'push'],
    'too many values' => [fn () => '{"a":['.implode(',', array_fill(0, 120000, '1')).']}', ['X-GitHub-Event' => 'push'], 'github', 'push'],
    'not JSON' => ["\x00\xFF\xFE binary", ['X-GitHub-Event' => 'push'], 'github', 'push'],
]);

it('drops derived values that are too long or carry control characters', function (): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), '{"type":"'.str_repeat('x', 300).'","id":"evt_\u0007bell"}', ['Stripe-Signature' => 't=1,v1=a'])->assertOk();
    $row = derivedOf(lastCapture($project->id)->id);
    expect($row['provider'])->toBe('stripe')->and($row['event_type'])->toBeNull()->and($row['provider_event_id'])->toBeNull();

    sendRaw('POST', '/c/'.activeKey($project->id), '{}', ['X-GitHub-Event' => str_repeat('p', 300)])->assertOk();
    expect(derivedOf(lastCapture($project->id)->id)['event_type'])->toBeNull();
});

/*
 * Idempotence, live trigger, request facts.
 */

it('is idempotent: a second run changes nothing', function (): void {
    $project = userProject(makeUser());
    foreach ([1, 2, 3, 4, 5] as $seq) {
        sendRd($project->id, $seq);
    }
    $snapshot = fn (): array => DB::table('captures')->where('project_id', $project->id)->orderBy('seq')
        ->get(['seq', 'provider', 'event_type', 'object_ids', 'group_key', 'group_label', 'group_anchor', 'shape', 'shape_verdict', 'derived_with_pack'])
        ->map(fn ($r) => (array) $r)->all();
    $before = $snapshot();

    foreach (Capture::query()->where('project_id', $project->id)->pluck('id') as $id) {
        app(CaptureDerivation::class)->derive($id);
    }

    expect($snapshot())->toBe($before);
});

it('writes derived facts only: the request facts trigger stays untouched', function (): void {
    $project = userProject(makeUser());
    $capture = sendRd($project->id, 1);
    $facts = fn () => (array) DB::table('captures')->where('id', $capture->id)->first(['method', 'raw_target', 'headers', 'body_size', 'counted_bytes']);
    $before = $facts();

    app(CaptureDerivation::class)->derive($capture->id);

    expect($facts())->toBe($before);
    expect(fn () => DB::table('captures')->where('id', $capture->id)->update(['method' => 'PUT']))->toThrow(QueryException::class);
});

it('queues derivation on default after a capture, then sends the live trigger when it finishes', function (): void {
    Queue::fake();
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), rdDelivery(1)['body_raw'], ['Stripe-Signature' => 't=1,v1=a'])->assertOk();
    $capture = lastCapture($project->id);

    Queue::assertPushedOn('default', DeriveCapture::class, fn (DeriveCapture $job): bool => $job->captureId === $capture->id);
    expect(derivedOf($capture->id)['provider'])->toBeNull();

    // The ingest trigger already went out; a fresh queue and lock store see only the derivation's.
    Queue::fake();
    Cache::flush();
    $seen = [];
    Event::listen(CaptureDerived::class, function (CaptureDerived $e) use (&$seen): void {
        $seen[] = $e;
    });
    (new DeriveCapture($capture->id))->handle(app(CaptureDerivation::class));

    expect(derivedOf($capture->id)['provider'])->toBe('stripe')
        ->and($seen)->toHaveCount(1)
        ->and([$seen[0]->captureId, $seen[0]->projectId, $seen[0]->provider, $seen[0]->eventType, $seen[0]->groupKey, $seen[0]->shapeVerdict])
        ->toBe([$capture->id, $project->id, 'stripe', 'invoice.paid', 'grp_stripe_sub_1QH3UF1HXb5oPQT3wfdt4drI', 'new']);
    Queue::assertPushedOn('broadcasts', LiveTrigger::class, fn (LiveTrigger $job): bool => $job->channel === 'project.'.$project->id
        && $job->event === 'project.captures' && $job->payload === ['v' => 1]);
    Queue::assertPushed(LiveTrigger::class, 1);
});

it('stores the capture even when derivation cannot be queued', function (): void {
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('queue down'));
    $project = userProject(makeUser());

    sendRaw('POST', '/c/'.activeKey($project->id), '{}', ['Stripe-Signature' => 't=1,v1=a'])->assertOk();

    expect(capturesOf($project->id))->toBe(1);
});

/*
 * Re-derive after a pack update.
 */

it('re-derives stale captures in place with --now, and leaves fresh ones alone', function (): void {
    $project = userProject(makeUser());
    foreach ([1, 3, 5, 13] as $seq) {
        sendRd($project->id, $seq);
    }
    $expected = DB::table('captures')->where('project_id', $project->id)->orderBy('seq')
        ->get(['seq', 'provider', 'event_type', 'group_key', 'group_label', 'shape_verdict', 'derived_with_pack'])->map(fn ($r) => (array) $r)->all();

    // As if #1 and #2 (seq) were derived by an older Stripe pack that grouped nothing.
    DB::table('captures')->where('project_id', $project->id)->whereIn('seq', [1, 2])->update([
        'derived_with_pack' => 'stripe@0.9.0', 'event_type' => null, 'group_key' => null, 'group_label' => null, 'group_anchor' => null,
    ]);
    $freshPack = DB::table('captures')->where('project_id', $project->id)->where('seq', 3)->value('derived_with_pack');

    $this->artisan('captures:rederive', ['--now' => true])
        ->expectsOutput('Re-derived 2 captures in 1 project.')
        ->assertSuccessful();

    expect(DB::table('captures')->where('project_id', $project->id)->orderBy('seq')
        ->get(['seq', 'provider', 'event_type', 'group_key', 'group_label', 'shape_verdict', 'derived_with_pack'])->map(fn ($r) => (array) $r)->all())
        ->toBe($expected)
        ->and(DB::table('captures')->where('project_id', $project->id)->where('seq', 3)->value('derived_with_pack'))->toBe($freshPack);

    $this->artisan('captures:rederive', ['--now' => true])->expectsOutput('Nothing to re-derive.')->assertSuccessful();
});

it('queues one batched job per project on maintenance, filtered by provider and project', function (): void {
    $a = userProject(makeUser(), 'A');
    $b = userProject(makeUser(), 'B');
    sendRd($a->id, 1);
    sendRd($a->id, 13);
    sendRd($b->id, 5);
    Queue::fake();

    $this->artisan('captures:rederive', ['--all' => true])->expectsOutput('Queued 3 captures in 2 projects.')->assertSuccessful();
    Queue::assertPushedOn('maintenance', RederiveProjectCaptures::class);
    Queue::assertPushed(RederiveProjectCaptures::class, 2);

    Queue::fake();
    $this->artisan('captures:rederive', ['--all' => true, '--provider' => ['unknown']])->expectsOutput('Queued 1 capture in 1 project.')->assertSuccessful();
    Queue::assertPushed(RederiveProjectCaptures::class, fn (RederiveProjectCaptures $job): bool => $job->projectId === $a->id && $job->providers === ['unknown']);

    Queue::fake();
    $this->artisan('captures:rederive', ['--all' => true, '--project' => $b->key])->expectsOutput('Queued 1 capture in 1 project.')->assertSuccessful();
    Queue::assertPushed(RederiveProjectCaptures::class, fn (RederiveProjectCaptures $job): bool => $job->projectId === $b->id);

    $this->artisan('captures:rederive', ['--project' => 'nope000000'])->assertFailed();
});

it('continues a long project in bounded runs', function (): void {
    $project = userProject(makeUser());
    foreach ([1, 2, 3, 4] as $seq) {
        sendRd($project->id, $seq);
    }
    DB::table('captures')->where('project_id', $project->id)->update(['derived_with_pack' => null]);

    $rederive = app(CaptureRederivation::class);
    $next = $rederive->run($project->id, false, null, 0, 3);
    expect($next)->toBe(3);
    expect($rederive->run($project->id, false, null, $next, 3))->toBeNull()
        ->and(DB::table('captures')->where('project_id', $project->id)->whereNull('derived_with_pack')->count())->toBe(0)
        ->and(DB::table('captures')->where('project_id', $project->id)->distinct()->pluck('group_key')->all())->toBe(['grp_stripe_sub_1QH3UF1HXb5oPQT3wfdt4drI']);
});

/*
 * Stream and detail props.
 */

it('fills the stream rows, groups, providers and the detail Decoded facts', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    foreach ([5, 6, 8, 13] as $seq) {
        sendRd($project->id, $seq);
    }
    actAs($owner);

    $this->get("/p/{$project->key}?sel=3")->assertInertia(fn (Assert $page) => $page
        ->where('query.view', 'grouped')
        ->where('providers', [
            ['id' => 'stripe', 'name' => 'Stripe'], ['id' => 'github', 'name' => 'GitHub'],
            ['id' => 'shopify', 'name' => 'Shopify'], ['id' => 'standard_webhooks', 'name' => 'Standard Webhooks'],
            ['id' => 'curl', 'name' => 'curl'],
        ])
        ->where('captures.latest.1.provider', 'standard_webhooks')
        ->where('captures.latest.1.providerName', 'Parcelbird')
        ->where('captures.latest.1.eventType', 'shipment.label_created')
        ->where('captures.latest.1.groupKey', 'grp_parcelbird_shp_1c263SsdQymuOjhjztsP')
        ->where('captures.latest.1.groupLabel', 'shp_1c263SsdQymuOjhjztsP')
        ->where('captures.latest.2.groupLabel', 'Order #1042')
        ->where('captures.latest.0.provider', null)
        ->where('captures.latest.0.groupLabel', null)
        ->where('selected.decoded', [
            'provider' => 'standard_webhooks', 'providerName' => 'Parcelbird', 'eventType' => 'shipment.label_created',
            'providerEventId' => 'msg_2pQxtbVXQpZuzuZFIoKZ2QIU2Lb', 'apiVersion' => null, 'objectIds' => ['shp_1c263SsdQymuOjhjztsP'],
            'group' => ['key' => 'grp_parcelbird_shp_1c263SsdQymuOjhjztsP', 'label' => 'shp_1c263SsdQymuOjhjztsP', 'kind' => 'Shipment'],
            'pack' => 'standard_webhooks@1.0.0',
        ])
        ->where('selected.shape', fn ($shape): bool => in_array(['data.shipment_id', 'string'], $shape->all(), true)
            && in_array(['data.rate', 'object'], $shape->all(), true))
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('groups', fn ($groups): bool => collect($groups)->firstWhere('key', 'grp_shopify_order_5873201946743') === [
                'key' => 'grp_shopify_order_5873201946743', 'label' => 'Order #1042', 'kind' => 'Order',
                'provider' => 'shopify', 'providerName' => 'Shopify', 'count' => 2, 'firstSeq' => 1, 'lastSeq' => 2,
                'firstAt' => collect($groups)->firstWhere('key', 'grp_shopify_order_5873201946743')['firstAt'],
                'lastAt' => collect($groups)->firstWhere('key', 'grp_shopify_order_5873201946743')['lastAt'],
                'worstSignature' => 'not_checked',
            ])));

    $seqs = fn (string $q): array => array_column(
        $this->get("/p/{$project->key}?q=".urlencode($q))->viewData('page')['props']['captures']['latest'], 'seq');
    expect($seqs('parcel'))->toBe([3])
        ->and($seqs('shp_1c263SsdQymuOjhjztsP'))->toBe([3])
        ->and($seqs('5873201946743'))->toBe([2, 1]);
});

/*
 * SHP-1 and SHP-3 rules, pure.
 */

it('writes arrays as path[] with the union of their elements, and an empty array alone (SHP-1)', function (): void {
    $root = json_decode('{"a":{"b":[{"c":1},{"d":null}],"e":[],"f":["x",2]},"g":true}', false);

    expect(Shape::of($root, 1000, 1000))->toBe([
        ['a', 'object'], ['a.b[]', 'array'], ['a.b[]', 'object'], ['a.b[].c', 'number'], ['a.b[].d', 'null'],
        ['a.e[]', 'array'], ['a.f[]', 'array'], ['a.f[]', 'number'], ['a.f[]', 'string'], ['g', 'boolean'],
    ]);
});

it('says differs for a missing path only against 5 or more baseline captures that all have it (SHP-3)', function (): void {
    $full = [['a', 'string'], ['b', 'number']];
    $without = [['a', 'string']];

    expect(ShapeVerdict::of($without, array_fill(0, 4, $full)))->toBe('matches')
        ->and(ShapeVerdict::of($without, array_fill(0, 5, $full)))->toBe('differs')
        ->and(ShapeVerdict::of($without, [...array_fill(0, 5, $full), $without]))->toBe('matches')
        ->and(ShapeVerdict::of([['a', 'number']], [$full]))->toBe('differs')
        ->and(ShapeVerdict::of($full, []))->toBe('new');
});
