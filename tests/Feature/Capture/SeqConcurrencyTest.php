<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CaptureHelpers.php';

/*
 * Capture `seq` is allocated under the project row lock (domain §3, IA §2.3):
 * several processes ingesting into one project at once get distinct,
 * gap-free numbers. Real processes and committed rows, outside the test
 * transaction, cleaned up afterwards.
 */
it('allocates distinct, gap-free seqs to concurrent ingest processes', function (): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl is not available.');
    }
    $base = config('database.connections.'.config('database.default'));
    config(['database.connections.concurrent' => $base]);
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('concurrent');
    $user = null;
    $workers = 4;
    $perWorker = 15;

    try {
        $user = User::factory()->create();
        $project = userProject($user);
        $key = activeKey($project->id);
        DB::disconnect('concurrent');

        $pids = [];
        for ($w = 0; $w < $workers; $w++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                // Child: its own connection; never runs the parent's destructors.
                DB::purge('concurrent');
                $code = 0;
                try {
                    for ($i = 0; $i < $perWorker; $i++) {
                        if (sendRaw('POST', "/c/{$key}", "w{$w}-{$i}", [], ['REMOTE_ADDR' => "198.51.100.{$w}"])->getStatusCode() !== 200) {
                            $code = 2;
                        }
                    }
                } catch (Throwable) {
                    $code = 3;
                }
                DB::disconnect('concurrent');
                posix_kill(posix_getpid(), $code === 0 ? SIGKILL : SIGTERM);
            }
            $pids[] = $pid;
        }
        $signals = [];
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $signals[] = pcntl_wifsignaled($status) ? pcntl_wtermsig($status) : -1;
        }

        DB::purge('concurrent');
        $seqs = DB::table('captures')->where('project_id', $project->id)->orderBy('seq')->pluck('seq')->all();
        expect($signals)->each->toBe(SIGKILL)
            ->and($seqs)->toBe(range(1, $workers * $perWorker))
            ->and((int) DB::table('projects')->where('id', $project->id)->value('next_capture_seq'))->toBe($workers * $perWorker + 1);
    } finally {
        if ($user !== null) {
            DB::table('users')->where('id', $user->id)->delete();   // cascades workspace → projects → captures
        }
        DB::setDefaultConnection($default);
        DB::disconnect('concurrent');
    }
});
