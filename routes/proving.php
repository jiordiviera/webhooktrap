<?php

declare(strict_types=1);

use App\Modules\Agent\Http\Controllers\AgentPageController;
use App\Modules\Agent\Http\Controllers\AgentRevokeController;
use App\Modules\Agent\Http\Controllers\PairingCodeController;
use App\Modules\Proving\Http\Controllers\ExampleRunController;
use App\Modules\Proving\Http\Controllers\RunPageController;
use Illuminate\Support\Facades\Route;

/*
 * Proving routes (contracts §3.2). S-14 example run for everyone; the
 * owner and share run pages arrive with the engine and agent slices.
 */

Route::get('/r/example/{focus?}', [ExampleRunController::class, 'show'])
    ->where('focus', '[DF][0-9]{1,3}')
    ->name('run.example');

// Owner run pages (agent connection notes): users and guests that own the project.
Route::middleware('verified-email')->group(function (): void {
    $key = '[A-Za-z0-9]{10}';
    Route::get('/r/{runId}/{focus?}', [RunPageController::class, 'show'])
        ->where(['runId' => 'run_[0-7][0-9A-HJKMNP-TV-Z]{25}', 'focus' => '[DF][0-9]{1,3}'])->name('run.show');
    Route::get('/p/{projectKey}/runs', [RunPageController::class, 'index'])->where('projectKey', $key)->name('project.runs');
    Route::get('/p/{projectKey}/runs/new', [RunPageController::class, 'draft'])->where('projectKey', $key)->name('project.runs.draft');
    Route::post('/p/{projectKey}/runs', [RunPageController::class, 'store'])->where('projectKey', $key)->name('project.runs.store');
    Route::get('/p/{projectKey}/runs/{n}', [RunPageController::class, 'byNumber'])
        ->where(['projectKey' => $key, 'n' => '[0-9]{1,9}'])->name('project.runs.number');

    // S-06/S-08 pairing (contracts §3.2): guest or user, JSON answer.
    Route::post('/pairing-codes', [PairingCodeController::class, 'store'])->name('pairing-codes.store');
    // Agent pages (contracts §3.2): users; the guest page under its project.
    Route::get('/agents', [AgentPageController::class, 'index'])->name('agents.index');
    Route::get('/agents/pair', [AgentPageController::class, 'pair'])->name('agents.pair');
    Route::get('/agents/{agent}', [AgentPageController::class, 'show'])->where('agent', '[A-Za-z0-9._-]{1,48}')->name('agents.show');
    Route::get('/p/{projectKey}/agent', [AgentPageController::class, 'guest'])->where('projectKey', $key)->name('project.agent');

    // AC-7: "Not your machine? Revoke" and the agent pages' Revoke (owner, user or guest).
    Route::delete('/agents/{agentId}', AgentRevokeController::class)
        ->where('agentId', 'agt_[0-7][0-9A-HJKMNP-TV-Z]{25}')->name('agents.revoke');
});
