<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public legal pages (Google and GitHub OAuth apps link them): no session
// state of their own, the same for anonymous visitors, guests and users.
Route::inertia('/privacy', 'Legal/Privacy')->name('privacy');
Route::inertia('/terms', 'Legal/Terms')->name('terms');

Route::middleware(['auth:web', 'verified-email'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    // Per-device preferences (decision 59): layout and appearance, stored in cookies.
    Route::inertia('account/preferences', 'Account/Preferences')->name('account.preferences');
});

require __DIR__.'/identity.php';
require __DIR__.'/workspace.php';
require __DIR__.'/proving.php';
