<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Application\GuestSessions;
use App\Modules\Identity\Application\Registration;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class RegisterController
{
    public function show(Request $request, GuestSessions $guests): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/');
        }
        $guests->beginClaim($request);

        return Inertia::render('Auth/Register', [
            'passwordPolicy' => PasswordPolicy::fromConfig()->toProp(),
            'retryAt' => $request->session()->get(SessionKeys::RETRY_AT),
        ]);
    }

    /** `POST /register`: same answer whether or not the address is taken. */
    public function store(Request $request, Registration $registration, Clock $clock): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:4096'],
        ]);
        $retryAt = $registration->register($request, (string) $data['name'], (string) $data['email'], (string) $data['password']);
        if ($retryAt !== null) {
            return Answers::throttled($request, $retryAt, $clock->now());
        }

        return redirect('/verify-email')->with('flash', Answers::flash('info', 'verification_sent', (string) $data['email']));
    }
}
