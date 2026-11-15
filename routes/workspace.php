<?php

declare(strict_types=1);

use App\Modules\Workspace\Http\Controllers\ProjectsController;
use App\Modules\Workspace\Http\Controllers\ProjectSettingsController;
use Illuminate\Support\Facades\Route;

/*
 * Workspace routes (contracts §3.2; slice 2 notes). `project_key` is never a
 * capability: every controller checks the owner and answers the same
 * not-found to anyone else (TM-13). `/p/{projectKey}` itself lives with the
 * guest entry points in identity.php.
 */

$key = '[A-Za-z0-9]{10}';

Route::middleware(['auth:web', 'verified-email'])->group(function (): void {
    Route::get('/projects', [ProjectsController::class, 'index'])->name('projects.index');
    Route::get('/projects/new', [ProjectsController::class, 'create'])->name('projects.create');
    Route::post('/projects/new', [ProjectsController::class, 'store'])->name('projects.store');
});

// Owner routes for users and guests (guest: danger only).
Route::middleware('verified-email')->group(function () use ($key): void {
    Route::get('/p/{projectKey}/settings', [ProjectSettingsController::class, 'index'])->where('projectKey', $key)->name('project.settings');
    Route::get('/p/{projectKey}/settings/{section}', [ProjectSettingsController::class, 'show'])
        ->where(['projectKey' => $key, 'section' => 'capture|ci|budgets|danger'])->name('project.settings.show');
    Route::patch('/p/{projectKey}/settings/name', [ProjectSettingsController::class, 'rename'])->where('projectKey', $key)->name('project.settings.rename');
    Route::patch('/p/{projectKey}/settings/retention', [ProjectSettingsController::class, 'retention'])->where('projectKey', $key)->name('project.settings.retention');
    Route::delete('/p/{projectKey}', [ProjectSettingsController::class, 'destroy'])->where('projectKey', $key)->name('project.destroy');
});
