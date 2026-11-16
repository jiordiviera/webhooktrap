<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The admin host has no sign-in form of its own (decision 74): its login
 * route sends the browser to the app's handoff, which signs in, confirms and
 * comes back with a single-use token. The admin host's `/robots.txt`
 * disallows everything.
 */
final readonly class AdminEntryController
{
    public function login(): RedirectResponse
    {
        return redirect()->away(rtrim((string) config('app.url'), '/').'/operator/handoff');
    }

    public function robots(): Response
    {
        return new Response("User-agent: *\nDisallow: /\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
