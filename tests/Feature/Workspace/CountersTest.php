<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use App\Modules\Workspace\Application\ProjectCounters;
use App\Modules\Workspace\Application\Projects;
use App\Modules\Workspace\Domain\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/WorkspaceHelpers.php';

it('allocates capture seqs and run numbers in order, independently, never twice', function (): void {
    $project = userProject(makeUser());
    $counters = app(ProjectCounters::class);

    $seqs = array_map(fn (): int => $counters->nextCaptureSeq($project->id), range(1, 25));
    $runs = array_map(fn (): int => $counters->nextRunNumber($project->id), range(1, 5));

    expect($seqs)->toBe(range(1, 25))
        ->and($runs)->toBe(range(1, 5))
        ->and(Project::query()->whereKey($project->id)->value('next_capture_seq'))->toBe(26);
});

it('allocates under a row lock (SELECT … FOR UPDATE)', function (): void {
    $project = userProject(makeUser());
    $sql = [];
    DB::listen(function ($query) use (&$sql): void {
        $sql[] = $query->sql;
    });

    app(ProjectCounters::class)->nextRunNumber($project->id);

    expect(collect($sql)->contains(fn (string $q): bool => str_contains($q, 'for update') && str_contains($q, '"projects"')))->toBeTrue();
});

it('refuses to allocate on a deleted project', function (): void {
    $project = userProject(makeUser());
    app(Projects::class)->delete($project);

    expect(fn () => app(ProjectCounters::class)->nextCaptureSeq($project->id))->toThrow(RuntimeException::class);
});

it('holds the row lock against a second connection until commit, then continues the sequence', function (): void {
    // Two extra connections outside the test transaction, so rows are really committed.
    $base = config('database.connections.'.config('database.default'));
    config(['database.connections.lock_a' => $base, 'database.connections.lock_b' => $base]);
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('lock_a');
    $a = DB::connection('lock_a');
    $b = DB::connection('lock_b');
    $user = null;

    try {
        $user = User::factory()->create();
        $project = userProject($user);

        $a->beginTransaction();
        expect(app(ProjectCounters::class)->nextCaptureSeq($project->id))->toBe(1);

        $b->beginTransaction();
        $b->statement("SET LOCAL lock_timeout = '300ms'");
        expect(fn () => $b->select('SELECT next_capture_seq FROM projects WHERE id = ? FOR UPDATE', [$project->id]))
            ->toThrow(QueryException::class);
        $b->rollBack();

        $a->commit();
        $b->beginTransaction();
        $row = $b->selectOne('SELECT next_capture_seq FROM projects WHERE id = ? FOR UPDATE', [$project->id]);
        $b->rollBack();
        expect((int) $row->next_capture_seq)->toBe(2)
            ->and(app(ProjectCounters::class)->nextCaptureSeq($project->id))->toBe(2);
    } finally {
        while ($a->transactionLevel() > 0) {
            $a->rollBack();
        }
        if ($user !== null) {
            $a->table('users')->where('id', $user->id)->delete();
        }
        DB::setDefaultConnection($default);
        $a->disconnect();
        $b->disconnect();
    }
});

it('counts the account capture quota atomically: daily count, stored bytes, day reset', function (): void {
    config(['workspace.quota.captures_per_day' => 3, 'workspace.quota.stored_body_bytes' => 1000]);
    $project = userProject(makeUser());
    $counters = app(ProjectCounters::class);

    expect($counters->reserveCapture($project->id, 100))->toBeTrue()
        ->and($counters->reserveCapture($project->id, 100))->toBeTrue()
        ->and($counters->reserveCapture($project->id, 100))->toBeTrue()
        ->and($counters->reserveCapture($project->id, 1))->toBeFalse();
    $row = Project::query()->whereKey($project->id)->sole();
    expect($row->captures_today)->toBe(3)->and($row->stored_body_bytes)->toBe(300);

    $this->travel(1)->days();
    expect($counters->reserveCapture($project->id, 701))->toBeFalse()
        ->and($counters->reserveCapture($project->id, 700))->toBeTrue()
        ->and(Project::query()->whereKey($project->id)->value('captures_today'))->toBe(1);

    $counters->releaseStoredBytes($project->id, 5000);
    expect(Project::query()->whereKey($project->id)->value('stored_body_bytes'))->toBe(0);
});

it('never counts the account quota on a guest or deleted project', function (): void {
    [, $key] = guestProject();
    $guest = Project::query()->where('project_key', $key)->sole();
    $deleted = userProject(makeUser());
    app(Projects::class)->delete($deleted);

    expect(app(ProjectCounters::class)->reserveCapture($guest->id, 1))->toBeFalse()
        ->and(app(ProjectCounters::class)->reserveCapture($deleted->id, 1))->toBeFalse();
});
