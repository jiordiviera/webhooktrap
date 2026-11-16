<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Identity\Application\SignInSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bound in place of Filament's LogoutController (decision 74): Filament's
 * only forgets the guard; this one also revokes the `user_sessions` row and
 * lands on the app, not on the panel login (which would hand straight back).
 */
final readonly class AdminSignOut
{
    public function __invoke(Request $request, SignInSessions $sessions): RedirectResponse
    {
        $sessions->signOut($request);

        return redirect()->away(rtrim((string) config('app.url'), '/').'/projects');
    }
}
