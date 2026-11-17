<?php

declare(strict_types=1);

use App\Modules\Capture\Http\Controllers\CaptureStreamController;
use App\Modules\Capture\Http\Controllers\CaptureUrlController;
use Illuminate\Support\Facades\Route;

/*
 * Capture owner routes (contracts §3.2; slice 3 notes), in the `web` group.
 * Ingest (`/c/{captureKey}`) is not a route: ServeCaptureHost answers it
 * before the router, for any method (contracts §2).
 */

Route::middleware('verified-email')->group(function (): void {
    // S-03 stream and S-04 detail (slice 4 notes): users and guests that own the project.
    Route::get('/p/{projectKey}', [CaptureStreamController::class, 'index'])
        ->where('projectKey', '[A-Za-z0-9]{10}')->name('project.show');
    Route::get('/p/{projectKey}/d/{n}', [CaptureStreamController::class, 'show'])
        ->where(['projectKey' => '[A-Za-z0-9]{10}', 'n' => '[0-9]{1,9}'])->name('project.delivery');
    Route::get('/p/{projectKey}/d/{n}/raw', [CaptureStreamController::class, 'raw'])
        ->where(['projectKey' => '[A-Za-z0-9]{10}', 'n' => '[0-9]{1,9}'])->name('project.delivery.raw');
    Route::get('/p/{projectKey}/d/{n}/fixture', [CaptureStreamController::class, 'fixture'])
        ->where(['projectKey' => '[A-Za-z0-9]{10}', 'n' => '[0-9]{1,9}'])->name('project.delivery.fixture');

    Route::post('/p/{projectKey}/settings/capture/rotate', [CaptureUrlController::class, 'rotate'])
        ->where('projectKey', '[A-Za-z0-9]{10}')->name('project.settings.rotateCaptureUrl');
});
