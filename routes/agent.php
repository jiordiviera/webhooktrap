<?php

declare(strict_types=1);

use App\Modules\Agent\Http\Controllers\AgentBroadcastAuthController;
use App\Modules\Agent\Http\Controllers\AgentJobsController;
use App\Modules\Agent\Http\Controllers\AgentSessionController;
use Illuminate\Support\Facades\Route;

/*
 * Agent ↔ server protocol v1 (contracts §5), under /agent/v1, outside the
 * `web` group: no session, no cookies, no CSRF. Bearer tokens only
 * (`agent.auth`); every endpoint has a body limit (`agent.body:<limit>`).
 */

$job = 'job_[0-7][0-9A-HJKMNP-TV-Z]{25}';
$run = 'run_[0-7][0-9A-HJKMNP-TV-Z]{25}';
$capture = 'cap_[0-7][0-9A-HJKMNP-TV-Z]{25}';

Route::post('/pair', [AgentSessionController::class, 'pair'])->middleware('agent.body:small')->name('agent.pair');
Route::post('/auth/challenge', [AgentSessionController::class, 'challenge'])->middleware('agent.body:small')->name('agent.auth.challenge');
Route::post('/auth/token', [AgentSessionController::class, 'token'])->middleware('agent.body:small')->name('agent.auth.token');
Route::post('/broadcasting/auth', AgentBroadcastAuthController::class)->middleware('agent.body:small')->name('agent.broadcasting.auth');

Route::middleware('agent.auth')->group(function () use ($job, $run, $capture): void {
    Route::post('/confirm', [AgentSessionController::class, 'confirm'])->middleware('agent.body:small')->name('agent.confirm');
    Route::post('/hello', [AgentSessionController::class, 'hello'])->middleware('agent.body:hello')->name('agent.hello');
    Route::delete('/agent', [AgentSessionController::class, 'destroy'])->middleware('agent.body:small')->name('agent.destroy');

    Route::get('/jobs/next', [AgentJobsController::class, 'next'])->name('agent.jobs.next');
    Route::post('/jobs/{jobId}/accept', [AgentJobsController::class, 'accept'])->where('jobId', $job)->middleware('agent.body:small')->name('agent.jobs.accept');
    Route::post('/jobs/{jobId}/decline', [AgentJobsController::class, 'decline'])->where('jobId', $job)->middleware('agent.body:small')->name('agent.jobs.decline');
    Route::post('/jobs/{jobId}/abort', [AgentJobsController::class, 'abort'])->where('jobId', $job)->middleware('agent.body:small')->name('agent.jobs.abort');
    Route::get('/jobs/{jobId}/captures/{captureId}/request', [AgentJobsController::class, 'capture'])
        ->where(['jobId' => $job, 'captureId' => $capture])->name('agent.jobs.capture');
    Route::post('/runs/{runId}/observations', [AgentJobsController::class, 'observations'])->where('runId', $run)->middleware('agent.body:observations')->name('agent.runs.observations');
    Route::post('/runs/{runId}/final', [AgentJobsController::class, 'final'])->where('runId', $run)->middleware('agent.body:final')->name('agent.runs.final');
});
