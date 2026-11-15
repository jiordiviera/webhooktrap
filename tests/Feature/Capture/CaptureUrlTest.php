<?php

declare(strict_types=1);

use App\Modules\Capture\Application\CaptureRetention;
use App\Modules\Capture\Domain\CaptureUrl;
use App\Modules\Capture\Events\CaptureUrlRotated;
use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Domain\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CaptureHelpers.php';

beforeEach(function (): void {
    Mail::fake();
    config(['capture.public_origin' => 'https://in.example.test']);
});

/*
 * Rotation (contracts §3.2 `POST /p/{projectKey}/settings/capture/rotate`).
 */

it('rotates: a new active key, the old one answers identically and stores nothing', function (): void {
    Event::fake([CaptureUrlRotated::class]);
    $user = makeUser();
    $project = userProject($user);
    $old = activeKey($project->id);
    sendRaw('POST', "/c/{$old}", 'before')->assertOk();   // warm cache for the old key
    actAs($user);

    $this->from("/p/{$project->key}/settings/capture")->post("/p/{$project->key}/settings/capture/rotate")
        ->assertRedirect("/p/{$project->key}/settings/capture")
        ->assertSessionHas('flash.code', 'capture_url_rotated');

    $new = activeKey($project->id);
    expect($new)->not->toBe($old)->toMatch('/^[A-Za-z0-9]{12}$/')
        ->and(CaptureUrl::query()->where('capture_key', $old)->value('state'))->toBe('rotated')
        ->and(CaptureUrl::query()->where('capture_key', $old)->value('rotated_at'))->not->toBeNull()
        ->and(CaptureUrl::query()->where('project_id', $project->id)->where('state', 'active')->count())->toBe(1);

    $toOld = sendRaw('POST', "/c/{$old}", 'after');
    $toNew = sendRaw('POST', "/c/{$new}", 'after');
    expect(observable($toOld))->toBe(observable($toNew))
        ->and(capturesOf($project->id))->toBe(2)
        ->and(lastCapture($project->id)->capture_url_id)->toBe(CaptureUrl::query()->where('capture_key', $new)->value('id'));

    $this->get("/p/{$project->key}/settings/capture")->assertInertia(fn (Assert $page) => $page
        ->where('project.captureUrl', "https://in.example.test/c/{$new}")
        ->where('currentProject.captureUrl', "https://in.example.test/c/{$new}"));
    Event::assertDispatched(CaptureUrlRotated::class, fn (CaptureUrlRotated $e): bool => $e->projectId === $project->id);
});

it('keeps seq across rotations (one counter per project)', function (): void {
    $user = makeUser();
    $project = userProject($user);
    sendRaw('POST', '/c/'.activeKey($project->id));
    actAs($user);
    $this->post("/p/{$project->key}/settings/capture/rotate");
    sendRaw('POST', '/c/'.activeKey($project->id));

    expect(DB::table('captures')->where('project_id', $project->id)->orderBy('seq')->pluck('seq')->all())->toBe([1, 2]);
});

it('answers the same not-found to anyone but the user owner on the rotate route', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    $key = activeKey($project->id);
    $deleted = userProject($owner, 'Deleted');
    app(Projects::class)->delete($deleted);
    [$guestCookie, $guestKey] = guestProject();

    // Anonymous.
    $this->post("/p/{$project->key}/settings/capture/rotate")->assertNotFound();
    // Another account.
    actAs(makeUser());
    $this->post("/p/{$project->key}/settings/capture/rotate")->assertNotFound();
    $this->post('/p/AAAAAAAAAA/settings/capture/rotate')->assertNotFound();
    // A guest owner (account-only route), and a guest on someone else's project.
    newBrowser();
    asGuest($guestCookie)->post("/p/{$guestKey}/settings/capture/rotate")->assertNotFound();
    asGuest($guestCookie)->post("/p/{$project->key}/settings/capture/rotate")->assertNotFound();
    // The owner, on a deleted project.
    actAs($owner);
    $this->post("/p/{$deleted->key}/settings/capture/rotate")->assertNotFound();

    expect(activeKey($project->id))->toBe($key)
        ->and(CaptureUrl::query()->where('project_id', $project->id)->count())->toBe(1);
});

/*
 * Owner-facing props (contracts §3.1, slice 2 notes).
 */

it('fills captureUrl in currentProject, ProjectRow, the settings and the stream page', function (): void {
    $user = makeUser();
    $a = userProject($user, 'Alpha');
    $b = userProject($user, 'Beta');
    sendRaw('POST', '/c/'.activeKey($a->id), 'x');
    sendRaw('POST', '/c/'.activeKey($a->id), 'y');
    actAs($user);
    $urlA = 'https://in.example.test/c/'.activeKey($a->id);

    $this->get('/projects')->assertInertia(fn (Assert $page) => $page->component('Projects/Index', false)
        ->where('projects.0.captureUrl', $urlA)
        ->where('projects.0.deliveries', 2)
        ->where('projects.0.lastActivityAt', fn (?string $at): bool => $at !== null && str_ends_with($at, 'Z'))
        ->where('projects.1.captureUrl', 'https://in.example.test/c/'.activeKey($b->id))
        ->where('projects.1.deliveries', 0)
        ->where('projects.1.lastActivityAt', null));

    $this->get("/p/{$a->key}/settings/capture")->assertInertia(fn (Assert $page) => $page
        ->where('project.captureUrl', $urlA)
        ->where('currentProject', ['key' => $a->key, 'name' => 'Alpha', 'captureUrl' => $urlA]));

    $this->get("/p/{$a->key}")->assertInertia(fn (Assert $page) => $page->component('Capture/Stream', false)
        ->where('captureUrl', $urlA));
});

it('uses APP_URL for the capture URL when no capture origin is configured', function (): void {
    config(['capture.public_origin' => null, 'app.url' => 'http://webhooktrap.test']);
    $project = userProject(makeUser());

    expect(app(Projects::class)->captureUrl($project))->toBe('http://webhooktrap.test/c/'.activeKey($project->id));
});

/*
 * Deletion and retention.
 */

it('purges every capture table of the account, soft-deleted projects included (domain §2.10)', function (): void {
    $user = makeUser();
    $live = userProject($user, 'Live');
    $gone = userProject($user, 'Gone');
    sendRaw('POST', '/c/'.activeKey($live->id), 'x');
    sendRaw('POST', '/c/'.activeKey($gone->id), 'y');
    app(Projects::class)->delete($gone);
    $other = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($other->id), 'z');

    app(AccountDeletion::class)->tombstone($user, 'admin', RequestContext::console());
    expect(capturesOf($live->id))->toBe(1);   // phase 1 deletes nothing
    app(AccountDeletion::class)->purgeDue();

    expect(DB::table('captures')->whereIn('project_id', [$live->id, $gone->id])->count())->toBe(0)
        ->and(DB::table('capture_urls')->whereIn('project_id', [$live->id, $gone->id])->count())->toBe(0)
        ->and(DB::table('capture_bodies')->count())->toBe(1)
        ->and(capturesOf($other->id))->toBe(1);
});

it('removes the capture tree with the project at the hard delete', function (): void {
    $project = userProject(makeUser());
    sendRaw('POST', '/c/'.activeKey($project->id), 'x');
    app(Projects::class)->delete($project);
    $this->travel(25)->hours();
    app(Projects::class)->purgeDeleted();

    expect(DB::table('captures')->where('project_id', $project->id)->count())->toBe(0)
        ->and(DB::table('capture_urls')->where('project_id', $project->id)->count())->toBe(0)
        ->and(DB::table('capture_bodies')->count())->toBe(0);
});

it('deletes captures past the project\'s capture retention and gives the bytes back', function (): void {
    $project = userProject(makeUser());
    $key = activeKey($project->id);
    Project::query()->whereKey($project->id)->update(['capture_retention_days' => 7]);
    sendRaw('POST', "/c/{$key}", '12345');
    $this->travel(5)->days();
    sendRaw('POST', "/c/{$key}", '123');
    $this->travel(3)->days();

    expect(app(CaptureRetention::class)->purgeExpired())->toBe(1)
        ->and(DB::table('captures')->where('project_id', $project->id)->pluck('seq')->all())->toBe([2])
        ->and(Project::query()->whereKey($project->id)->value('stored_body_bytes'))
        ->toBe((int) DB::table('captures')->where('project_id', $project->id)->value('counted_bytes'))
        ->and(DB::table('capture_bodies')->count())->toBe(1);
});
