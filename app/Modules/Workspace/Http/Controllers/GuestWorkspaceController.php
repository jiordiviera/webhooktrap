<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers;

use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Workspace\Application\WorkspaceOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** `POST /new` (contracts §3.2). The project page itself is Capture's stream (slice 4). */
final readonly class GuestWorkspaceController
{
    public function __construct(
        private GuestSessions $guests,
        private WorkspaceOwnership $ownership,
    ) {}

    /**
     * `POST /new` (contracts §3.2): with a live guest cookie, redirects to the
     * existing project and creates nothing, so a cross-site post never
     * replaces a live session (TM-11). CSRF is enforced by the web group.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/');
        }
        if ($live = $this->guests->currentId()) {
            return redirect('/p/'.$this->ownership->projectKeyOfGuest($live));
        }

        [$started, $key] = DB::transaction(function () use ($request): array {
            $started = $this->guests->start($request);

            return [$started, $this->ownership->createForGuest($started->id)];
        });

        return redirect('/p/'.$key)->withCookie($started->cookie);
    }
}
