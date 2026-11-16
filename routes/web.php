<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Public legal pages (Google and GitHub OAuth apps link them): no session
// state of their own, the same for anonymous visitors, guests and users.
Route::inertia('/privacy', 'Legal/Privacy')->name('privacy');
Route::inertia('/terms', 'Legal/Terms')->name('terms');

// Starter-kit URLs that may still be bookmarked: the signed-in home is
// /projects, and appearance lives in Account › Preferences (decision 59).
Route::permanentRedirect('dashboard', '/projects');
Route::permanentRedirect('settings/appearance', '/account/preferences');

Route::middleware(['auth:web', 'verified-email'])->group(function () {
    // Per-device preferences (decision 59): layout and appearance, stored in cookies.
    Route::inertia('account/preferences', 'Account/Preferences')->name('account.preferences');
});

require __DIR__.'/identity.php';
require __DIR__.'/workspace.php';
require __DIR__.'/proving.php';
