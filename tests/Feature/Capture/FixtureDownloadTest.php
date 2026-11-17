<?php

declare(strict_types=1);

use App\Modules\Capture\Domain\Capture;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CaptureHelpers.php';

/*
 * Issue #15: a capture downloaded as a fixture file in the format
 * `webhooktrap test` reads (agent/internal/fixture, docs reference/fixtures).
 */

beforeEach(function (): void {
    Mail::fake();
    config(['capture.public_origin' => 'https://in.example.test']);
});

function fixtureDelivery(string $projectId, string $body, array $headers, array $facts): void
{
    sendRaw('POST', '/c/'.activeKey($projectId), $body, $headers)->assertOk();
    if ($facts !== []) {
        Capture::query()->where('project_id', $projectId)->orderByDesc('seq')->limit(1)->update($facts);
    }
}

it('downloads the body byte for byte with only the allow-listed headers', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    $body = "{\"action\":\"opened\",  \"title\":\"Zoë / \\u00e9t\u{e9}\",\n\"n\":1.50}";
    fixtureDelivery($project->id, $body, [
        'Content-Type' => 'application/json',
        'User-Agent' => 'GitHub-Hookshot/abc',
        'X-GitHub-Event' => 'pull_request',
        'X-GitHub-Delivery' => 'd-1',
        'X-Hub-Signature-256' => 'sha256=deadbeef',
        'X-Other' => 'dropped',
    ], ['provider' => 'github', 'event_type' => 'pull_request.opened', 'derived_with_pack' => 'github@1.0.0']);
    actAs($owner);

    $response = $this->get("/p/{$project->key}/d/1/fixture")->assertOk();
    $response->assertHeader('Content-Disposition', 'attachment; filename="pull_request.opened.01.json"');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');

    $file = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    expect(array_keys($file))->toBe(['provider', 'event_type', 'capture_order', 'source', 'headers', 'signature', 'body_sha256', 'body'])
        ->and($file['provider'])->toBe('github')
        ->and($file['event_type'])->toBe('pull_request.opened')
        ->and($file['capture_order'])->toBe(1)
        ->and($file['signature'])->toBe('resign')
        ->and($file['body'])->toBe($body)
        ->and($file['body_sha256'])->toBe(hash('sha256', $body))
        ->and($file['source'])->toContain('cap_')->toContain('github@1.0.0');

    $names = array_map(fn (array $h): string => strtolower($h[0]), $file['headers']);
    expect($names)->toContain('content-type', 'user-agent', 'x-github-event', 'x-github-delivery')
        ->not->toContain('x-hub-signature-256', 'x-other');
});

it('answers not found to anyone but the owner, and when the capture has no fixture', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    fixtureDelivery($project->id, '{"id":"evt_1"}', ['Content-Type' => 'application/json'], ['provider' => 'stripe', 'event_type' => 'invoice.paid']);
    fixtureDelivery($project->id, '{"ping":true}', ['Content-Type' => 'application/json'], []);
    fixtureDelivery($project->id, "\xff\xfe\x00binary", ['Content-Type' => 'application/octet-stream'], ['provider' => 'stripe']);

    actAs(makeUser());
    $this->get("/p/{$project->key}/d/1/fixture")->assertNotFound();

    actAs($owner);
    $this->get("/p/{$project->key}/d/1/fixture")->assertOk();
    $this->get("/p/{$project->key}/d/2/fixture")->assertNotFound();   // no provider
    $this->get("/p/{$project->key}/d/3/fixture")->assertNotFound();   // not UTF-8 text
    $this->get("/p/{$project->key}/d/9/fixture")->assertNotFound();

    $this->get("/p/{$project->key}/d/2")->assertInertia(fn (Assert $page) => $page
        ->where('capture.fixture', ['available' => false, 'reason' => 'no_provider', 'path' => null, 'secrets' => []]));
});

it('names the secret patterns in the body without their values', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    fixtureDelivery($project->id, '{"key":"sk_live_51Habc","hook":"whsec_xyz"}', ['Content-Type' => 'application/json'], ['provider' => 'stripe', 'event_type' => 'invoice.paid']);
    actAs($owner);

    $this->get("/p/{$project->key}/d/1")->assertInertia(fn (Assert $page) => $page
        ->where('capture.fixture.available', true)
        ->where('capture.fixture.path', 'webhooktrap/fixtures/stripe/invoice.paid.01.json')
        ->where('capture.fixture.secrets', ['sk_live_', 'whsec_']));
});
