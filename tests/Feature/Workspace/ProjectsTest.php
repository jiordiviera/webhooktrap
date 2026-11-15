<?php

declare(strict_types=1);

use App\Modules\Identity\Contracts\Principal;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use App\Modules\Workspace\Domain\Project;
use App\Modules\Workspace\Events\ProjectCreated;
use App\Modules\Workspace\Events\ProjectDeleted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/WorkspaceHelpers.php';

it('creates a project with a fresh key and the default settings (S-15)', function (): void {
    Event::fake([ProjectCreated::class]);
    $user = makeUser();
    actAs($user);

    $this->get('/projects/new')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Projects/New', false)->where('defaultName', 'My project'));
    $response = $this->post('/projects/new', ['name' => 'Brindle Coffee']);

    $project = Project::query()->sole();
    $response->assertRedirect('/p/'.$project->project_key);
    expect($project->project_key)->toMatch('/^[A-Za-z0-9]{10}$/')
        ->and($project->publicId())->toStartWith('prj_')
        ->and($project->name)->toBe('Brindle Coffee')
        ->and($project->is_guest)->toBeFalse()
        ->and($project->capture_retention_days)->toBe(30)
        ->and($project->run_retention_days)->toBe(90)
        ->and($project->default_visibility)->toBe('private')
        ->and($project->next_capture_seq)->toBe(1)
        ->and($project->next_run_number)->toBe(1)
        ->and($project->captures_today)->toBe(0)
        ->and($project->stored_body_bytes)->toBe(0)
        ->and($project->workspace->user_id)->toBe($user->id);
    Event::assertDispatched(ProjectCreated::class, fn (ProjectCreated $e): bool => $e->projectId === $project->id && $e->workspaceId === $project->workspace_id);
    $this->get('/p/'.$project->project_key)->assertOk();
});

it('refuses /projects to guests and anonymous visitors', function (): void {
    $this->get('/projects')->assertRedirect('/sign-in');
    $this->post('/projects/new', ['name' => 'X'])->assertRedirect('/sign-in');
    [$cookie] = guestProject();
    asGuest($cookie)->post('/projects/new', ['name' => 'X'])->assertRedirect('/sign-in');
    expect(Project::query()->where('is_guest', false)->count())->toBe(0);
});

it('lists projects only with two or more; otherwise goes to the one or to New project', function (): void {
    $user = makeUser();
    actAs($user);
    $this->get('/projects')->assertRedirect('/projects/new');

    $first = userProject($user, 'Brindle Coffee');
    $this->get('/projects')->assertRedirect('/p/'.$first->key);

    $second = userProject($user, 'Atlas');
    userProject(makeUser(), 'Not mine');
    $this->get('/projects')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Projects/Index', false)
        ->has('projects', 2)
        ->where('projects.0.key', $second->key)
        ->where('projects.0.name', 'Atlas')
        ->where('projects.0.captureUrl', fn (string $url): bool => preg_match('#/c/[A-Za-z0-9]{12}$#', $url) === 1)
        ->where('projects.0.deliveries', 0)
        ->where('projects.0.lastActivityAt', null)
        ->where('projects.0.lastRunStatus', null)
        ->has('projects.0.createdAt')
        ->where('projects.1.name', 'Brindle Coffee'));
});

it('validates the project name and the retention choices', function (): void {
    $user = makeUser();
    actAs($user);

    $this->post('/projects/new', ['name' => ''])->assertSessionHasErrors(['name' => 'Enter a name.']);
    $this->post('/projects/new', ['name' => '   '])->assertSessionHasErrors(['name' => 'Enter a name.']);
    $this->post('/projects/new', [])->assertSessionHasErrors(['name' => 'Enter a name.']);
    $this->post('/projects/new', ['name' => str_repeat('a', 81)])->assertSessionHasErrors(['name' => 'Use 80 characters or fewer.']);
    $this->post('/projects/new', ['name' => ['x']])->assertSessionHasErrors('name');
    expect(Project::query()->count())->toBe(0);

    $project = userProject($user);
    $this->patch("/p/{$project->key}/settings/name", ['name' => ''])->assertSessionHasErrors(['name' => 'Enter a name.']);
    $this->patch("/p/{$project->key}/settings/retention", ['captureRetentionDays' => 31, 'runRetentionDays' => 90])
        ->assertSessionHasErrors(['captureRetentionDays' => 'Choose how long deliveries are kept.']);
    $this->patch("/p/{$project->key}/settings/retention", ['captureRetentionDays' => 30, 'runRetentionDays' => 'all'])
        ->assertSessionHasErrors(['runRetentionDays' => 'Choose how long runs are kept.']);
    $this->patch("/p/{$project->key}/settings/retention", [])->assertSessionHasErrors(['captureRetentionDays', 'runRetentionDays']);

    $row = Project::query()->sole();
    expect($row->name)->toBe('Brindle Coffee')->and($row->capture_retention_days)->toBe(30)->and($row->run_retention_days)->toBe(90);
});

it('renames a project and changes its retention', function (): void {
    $user = makeUser();
    $project = userProject($user);
    actAs($user);

    $this->from("/p/{$project->key}/settings/capture")->patch("/p/{$project->key}/settings/name", ['name' => '  Atlas  '])
        ->assertRedirect("/p/{$project->key}/settings/capture")->assertSessionHas('flash.code', 'project_renamed');
    $this->patch("/p/{$project->key}/settings/retention", ['captureRetentionDays' => 7, 'runRetentionDays' => 30])
        ->assertSessionHas('flash.code', 'retention_saved');

    $row = Project::query()->sole();
    expect($row->name)->toBe('Atlas')->and($row->capture_retention_days)->toBe(7)->and($row->run_retention_days)->toBe(30)
        ->and($row->project_key)->toBe($project->key);
});

it('generates 10-char base62 keys from the whole alphabet, all distinct', function (): void {
    $keys = array_map(fn (): string => Project::newKey(), range(1, 3000));

    expect(array_filter($keys, fn (string $k): bool => preg_match('/^[A-Za-z0-9]{10}$/', $k) !== 1))->toBe([])
        ->and(count(array_unique($keys)))->toBe(3000)
        ->and(count(array_unique(str_split(implode('', $keys)))))->toBe(62);
});

it('keeps project keys unique and well-formed in the database', function (): void {
    $a = userProject(makeUser());
    $b = userProject(makeUser());
    expect($a->key)->not->toBe($b->key);

    $workspaceId = Project::query()->whereKey($a->id)->value('workspace_id');
    expect(fn () => DB::transaction(fn () => Project::query()->create(['workspace_id' => $workspaceId, 'project_key' => $a->key, 'name' => 'Dup'])))
        ->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => Project::query()->create(['workspace_id' => $workspaceId, 'project_key' => 'short', 'name' => 'Bad'])))
        ->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => Project::query()->create(['workspace_id' => $workspaceId, 'project_key' => Project::newKey(), 'name' => ''])))
        ->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => Project::query()->whereKey($a->id)->update(['default_visibility' => 'public'])))
        ->toThrow(QueryException::class);
});

it('gives the guest workspace exactly one project, "Guest session", enforced by the database (domain §3)', function (): void {
    Event::fake([ProjectCreated::class]);
    [, $key] = guestProject();

    $project = Project::query()->where('project_key', $key)->sole();
    expect($project->is_guest)->toBeTrue()
        ->and($project->name)->toBe('Guest session')
        ->and($project->workspace->guest_session_id)->not->toBeNull();
    Event::assertDispatched(ProjectCreated::class, fn (ProjectCreated $e): bool => $e->projectId === $project->id);

    expect(fn () => DB::transaction(fn () => Project::query()->create([
        'workspace_id' => $project->workspace_id, 'project_key' => Project::newKey(), 'name' => 'Second',
    ])))->toThrow(QueryException::class);
    // The app cannot mark a project as an account project by hand either.
    Project::query()->whereKey($project->id)->update(['is_guest' => false]);
    expect(Project::query()->whereKey($project->id)->value('is_guest'))->toBeTrue();
    expect(app(Projects::class)->of(new Principal('guest', (string) $project->workspace->guest_session_id)))->toHaveCount(1);
});

it('enforces exactly one owner per workspace in the database (R61)', function (): void {
    $user = makeUser();
    [, $key] = guestProject();
    $guestId = Project::query()->where('project_key', $key)->sole()->workspace->guest_session_id;

    expect(fn () => DB::transaction(fn () => DB::table('workspaces')->insert(['id' => (string) str()->uuid(), 'user_id' => null, 'guest_session_id' => null])))
        ->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('workspaces')->insert(['id' => (string) str()->uuid(), 'user_id' => $user->id, 'guest_session_id' => $guestId])))
        ->toThrow(QueryException::class);
});

it('turns a claimed guest project into an account project; several claims fit one account', function (): void {
    $user = makeUser();
    [, $k1] = guestProject();
    [, $k2] = guestProject();
    foreach (DB::table('workspaces')->whereNotNull('guest_session_id')->pluck('guest_session_id') as $guestId) {
        app(WorkspaceOwnership::class)->absorbGuestWorkspace((string) $guestId, $user->id, false);
    }

    expect(Project::query()->whereIn('project_key', [$k1, $k2])->where('is_guest', false)->count())->toBe(2)
        ->and(app(Projects::class)->of(new Principal('user', $user->id)))->toHaveCount(2);
});

it('soft-deletes a project at once and hard-deletes it after 24 h (schema §3, §8)', function (): void {
    Event::fake([ProjectDeleted::class]);
    $user = makeUser();
    $keep = userProject($user, 'Keep');
    $gone = userProject($user, 'Gone');
    actAs($user);

    $this->delete("/p/{$gone->key}")->assertRedirect('/projects')
        ->assertSessionHas('flash.code', 'project_deleted')->assertSessionHas('flash.message', 'Gone');
    Event::assertDispatched(ProjectDeleted::class, fn (ProjectDeleted $e): bool => $e->projectId === $gone->id);

    $this->get("/p/{$gone->key}")->assertNotFound();
    $this->get("/p/{$gone->key}/settings/capture")->assertNotFound();
    $this->delete("/p/{$gone->key}")->assertNotFound();
    $this->get('/projects')->assertRedirect('/p/'.$keep->key);
    Event::assertDispatchedTimes(ProjectDeleted::class, 1);

    $this->travel(23)->hours();
    expect(app(Projects::class)->purgeDeleted())->toBe(0)
        ->and(Project::query()->whereKey($gone->id)->exists())->toBeTrue();
    $this->travel(2)->hours();
    expect(app(Projects::class)->purgeDeleted())->toBe(1)
        ->and(Project::query()->whereKey($gone->id)->exists())->toBeFalse()
        ->and(Project::query()->whereKey($keep->id)->exists())->toBeTrue();
});
