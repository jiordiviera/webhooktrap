<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Identity\Contracts\Principal;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use App\Modules\Workspace\Domain\Workspace;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/WorkspaceHelpers.php';

/** Every owner route of slice 2, as [method, path suffix after /p/{key}]. */
function ownerRoutes(): array
{
    return [
        ['get', ''], ['get', '/settings'], ['get', '/settings/capture'], ['get', '/settings/ci'],
        ['get', '/settings/budgets'], ['get', '/settings/danger'],
        ['patch', '/settings/name'], ['patch', '/settings/retention'], ['delete', ''],
    ];
}

it('lets the owner open every settings page, with the project props', function (): void {
    $user = makeUser();
    $project = userProject($user);
    actAs($user);

    $this->get("/p/{$project->key}/settings")->assertRedirect("/p/{$project->key}/settings/capture");
    $this->get("/p/{$project->key}/settings/capture")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Capture', false)
            ->where('section', 'capture')
            ->where('sections', ['capture', 'ci', 'budgets', 'danger'])
            ->where('project.key', $project->key)
            ->where('project.name', 'Brindle Coffee')
            ->where('project.isGuest', false)
            ->where('project.captureUrl', fn (string $url): bool => preg_match('#/c/[A-Za-z0-9]{12}$#', $url) === 1)
            ->where('project.captureRetentionDays', 30)
            ->where('project.runRetentionDays', 90)
            ->where('project.defaultVisibility', 'private')
            ->where('retentionOptions.captureDays', [1, 7, 14, 30])
            ->where('retentionOptions.runDays', [7, 30, 90])
            ->where('currentProject.key', $project->key)
            ->where('currentProject.name', 'Brindle Coffee')
            ->where('currentProject.captureUrl', fn (string $url): bool => preg_match('#/c/[A-Za-z0-9]{12}$#', $url) === 1)
            ->where('projectSwitcher', [['key' => $project->key, 'name' => 'Brindle Coffee']]));
    foreach (['ci' => 'Settings/Ci', 'budgets' => 'Settings/Budgets', 'danger' => 'Settings/Danger'] as $section => $component) {
        $this->get("/p/{$project->key}/settings/{$section}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component, false)->missing('retentionOptions'));
    }
});

it('answers not found on every owner route to another user, a guest and an anonymous visitor (TM-13)', function (): void {
    $owner = makeUser();
    $project = userProject($owner);
    [$guestCookie] = guestProject();

    foreach (ownerRoutes() as [$method, $suffix]) {
        newBrowser();
        $this->{$method}("/p/{$project->key}{$suffix}")->assertNotFound();
        actAs(makeUser());
        $this->{$method}("/p/{$project->key}{$suffix}")->assertNotFound();
        newBrowser();
        asGuest($guestCookie)->{$method}("/p/{$project->key}{$suffix}")->assertNotFound();
    }

    $fresh = app(Projects::class)->owned(new Principal('user', $owner->id), $project->key);
    expect($fresh?->name)->toBe('Brindle Coffee');
});

it('never lets an agent or runner principal pass the browser policy, even with the owner id (TM-20)', function (): void {
    $user = makeUser();
    $project = userProject($user);
    [$cookie, $guestKey] = guestProject();
    $guestId = (string) DB::table('workspaces')->whereNotNull('guest_session_id')->value('guest_session_id');
    $workspace = Workspace::query()->where('user_id', $user->id)->sole();

    foreach (['agent', 'runner', 'ci', 'api', ''] as $kind) {
        expect($workspace->isOwnedBy(new Principal($kind, $user->id)))->toBeFalse()
            ->and(app(Projects::class)->owned(new Principal($kind, $user->id), $project->key))->toBeNull()
            ->and(app(Projects::class)->owned(new Principal($kind, $guestId), $guestKey))->toBeNull()
            ->and(app(WorkspaceOwnership::class)->owns(new Principal($kind, $user->id), $project->key))->toBeFalse();
    }
    expect($workspace->isOwnedBy(new Principal('user', $user->id)))->toBeTrue()
        ->and($workspace->isOwnedBy(new Principal('guest', $user->id)))->toBeFalse()
        ->and($workspace->isOwnedBy(null))->toBeFalse();
});

it('gives the same not-found answer for someone else\'s, a deleted and an unknown project (IA5)', function (): void {
    $owner = makeUser();
    $foreign = userProject($owner, 'Foreign');
    $mine = makeUser();
    $deleted = userProject($mine, 'Gone');
    app(Projects::class)->delete($deleted);

    foreach (['/settings/capture', '/settings/danger', ''] as $suffix) {
        $inertia = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];
        foreach (['html' => [], 'inertia' => $inertia, 'json' => ['Accept' => 'application/json']] as $kind => $headers) {
            actAs($mine);
            $answers = array_map(function (string $key) use ($suffix, $headers): array {
                $response = $this->flushHeaders()->withHeaders($headers)->get("/p/{$key}{$suffix}");
                // The branded page (S-18) echoes the requested path as the
                // Inertia page URL, the visitor's own input; everything else
                // must be byte-identical.
                $path = "/p/{$key}{$suffix}";
                $body = str_replace([$path, str_replace('/', '\\/', $path)], '{path}', (string) $response->getContent());

                return [$response->status(), $body, $response->headers->get('Content-Type')];
            }, [$foreign->key, $deleted->key, 'AAAAAAAAAA']);

            expect($answers[0][0])->toBe(404)
                ->and($answers[1])->toBe($answers[0])
                ->and($answers[2])->toBe($answers[0])
                ->and($answers[0][1])->not->toContain('Foreign')->not->toContain('Gone')
                ->not->toContain($foreign->key)->not->toContain($deleted->key);

            if ($kind === 'json') {
                // Plain JSON for JSON clients, never the page.
                expect($answers[0][2])->toContain('application/json')
                    ->and($answers[0][1])->not->toContain('"component"');
            } else {
                expect($answers[0][1])->toContain('"component":"Error"')->toContain('"status":404');
            }
        }
    }
});

it('shows a guest owner the Danger page only (contracts §3.2)', function (): void {
    [$cookie, $key] = guestProject();

    asGuest($cookie)->get("/p/{$key}/settings")->assertRedirect("/p/{$key}/settings/danger");
    asGuest($cookie)->get("/p/{$key}/settings/danger")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Danger', false)
            ->where('sections', ['danger'])
            ->where('project.isGuest', true)
            ->where('project.name', 'Guest session')
            ->where('currentProject.key', $key)
            ->missing('projectSwitcher'));
    foreach (['capture', 'ci', 'budgets'] as $section) {
        asGuest($cookie)->get("/p/{$key}/settings/{$section}")->assertNotFound();
    }
    asGuest($cookie)->patch("/p/{$key}/settings/name", ['name' => 'Mine'])->assertNotFound();
    asGuest($cookie)->patch("/p/{$key}/settings/retention", ['captureRetentionDays' => 7, 'runRetentionDays' => 7])->assertNotFound();
    asGuest($cookie)->delete("/p/{$key}")->assertNotFound();
    expect(DB::table('projects')->where('project_key', $key)->value('name'))->toBe('Guest session')
        ->and(DB::table('projects')->where('project_key', $key)->value('deleted_at'))->toBeNull();
});

it('shares the current project only with its owner and the switcher only with users', function (): void {
    $user = makeUser();
    $b = userProject($user, 'beta');
    $a = userProject($user, 'Alpha');
    actAs($user);

    $this->get('/projects')->assertInertia(fn (Assert $page) => $page->component('Projects/Index', false)
        ->missing('currentProject')
        ->where('projectSwitcher', [['key' => $a->key, 'name' => 'Alpha'], ['key' => $b->key, 'name' => 'beta']]));
});

it('redirects an unverified user away from workspace pages (S-00)', function (): void {
    $user = makeUser(['email_verified_at' => null]);
    $project = userProject($user);
    actAs($user);

    $this->get('/projects')->assertRedirect('/verify-email');
    $this->get("/p/{$project->key}/settings/capture")->assertRedirect('/verify-email');
});
