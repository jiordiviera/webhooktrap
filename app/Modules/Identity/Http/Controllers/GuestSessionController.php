<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class GuestSessionController
{
    /** `POST /session/end` ("End session now"). */
    public function end(GuestSessions $guests): RedirectResponse
    {
        $guest = $guests->current();
        abort_if($guest === null, 404);
        $guests->end($guest, 'user');
        Inertia::clearHistory(); // D1

        return redirect('/')->withCookie($guests->forgetCookie())->with('flash', Answers::flash('info', 'session_ended'));
    }

    /** `GET /expired`: nothing from the session in the props (R59). */
    public function expired(GuestSessions $guests): Response
    {
        return Inertia::render('Expired');
    }
}
