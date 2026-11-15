<?php

declare(strict_types=1);

use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Contracts\AccountDeletionParticipant;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Workspace\Application\Projects;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/WorkspaceHelpers.php';

beforeEach(fn () => Mail::fake());

it('lists the account\'s live projects on the deletion screen', function (): void {
    $user = makeUser();
    userProject($user, 'beta');
    userProject($user, 'Alpha');
    app(Projects::class)->delete(userProject($user, 'Deleted'));
    userProject(makeUser(), 'Not mine');
    actAs($user);

    $this->get('/account/delete')->assertInertia(fn (Assert $page) => $page->component('Account/Delete', false)
        ->where('deletion.projects', [['name' => 'Alpha'], ['name' => 'beta']]));
});

it('keeps the workspace through the tombstone and purges the whole tree, soft-deleted projects included (domain §2.10)', function (): void {
    $user = makeUser();
    $live = userProject($user, 'Live');
    $deleted = userProject($user, 'Deleted');
    app(Projects::class)->delete($deleted);
    $other = userProject(makeUser(), 'Other');
    signIn($user);
    $this->get("/p/{$live->key}/settings/capture")->assertOk();

    app(AccountDeletion::class)->tombstone($user, 'admin', RequestContext::console());

    // Phase 1: nothing of the workspace is written; the owner is not live, so
    // the same browser session no longer yields a principal for it.
    expect(DB::table('projects')->whereIn('id', [$live->id, $deleted->id])->count())->toBe(2);
    $this->get("/p/{$live->key}/settings/capture")->assertRedirect(); // the revoked session ends
    $this->get("/p/{$live->key}/settings/capture")->assertNotFound();
    $this->get("/p/{$live->key}")->assertNotFound();

    expect(app(AccountDeletion::class)->purgeDue())->toBe(1)
        ->and(DB::table('workspaces')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('projects')->whereIn('id', [$live->id, $deleted->id])->count())->toBe(0)
        ->and(DB::table('projects')->where('id', $other->id)->count())->toBe(1)
        ->and(DB::table('deletion_ledger')->where('subject', $user->publicId())->count())->toBe(1);
});

it('runs every module\'s part inside the tombstone and purge transactions (F13)', function (): void {
    $user = makeUser();
    $project = userProject($user);
    app()->instance('test.failing-participant', new class implements AccountDeletionParticipant
    {
        public function tombstone(string $userId): void
        {
            throw new RuntimeException('tombstone failed');
        }

        public function purge(string $userId): void
        {
            throw new RuntimeException('purge failed');
        }

        public function summary(string $userId): array
        {
            return [];
        }
    });
    app()->tag(['test.failing-participant'], 'identity.deletion-participants');

    expect(fn () => app(AccountDeletion::class)->tombstone($user, 'admin', RequestContext::console()))
        ->toThrow(RuntimeException::class, 'tombstone failed');
    expect($user->refresh()->deleted_at)->toBeNull();

    // Purge: the workspace delete is rolled back with the failing participant.
    $user->forceFill(['deleted_at' => now()])->save();
    expect(fn () => app(AccountDeletion::class)->purgeDue())->toThrow(RuntimeException::class, 'purge failed');
    expect(DB::table('projects')->where('id', $project->id)->count())->toBe(1)
        ->and(DB::table('users')->where('id', $user->id)->count())->toBe(1);
});
