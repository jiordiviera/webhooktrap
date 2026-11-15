<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\AccountRecovery;
use App\Modules\Identity\Application\SecondFactorChallenge;
use App\Modules\Identity\Application\SignInSessions;
use App\Modules\Identity\Domain\PasswordPolicy;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/** Delayed email recovery, hosted only (PO-J, domain §2.3); 404 on self-host. */
final readonly class AccountRecoveryController
{
    public function __construct(private AccountRecovery $recovery)
    {
        abort_unless(AccountRecovery::hostedOnly(), 404);
    }

    /** `POST /account-recovery`: from the 2FA challenge, or with an email; uniform answer. */
    public function request(Request $request, SecondFactorChallenge $challenge): RedirectResponse
    {
        $pending = $challenge->pendingUser($request);
        $email = $pending->email ?? (string) $request->validate(['email' => ['required', 'string', 'max:254']])['email'];
        $this->recovery->request($email, RequestContext::from($request));

        return back()->with('flash', Answers::flash('info', 'recovery_link_sent'));
    }

    public function show(Request $request, string $token): Response
    {
        $pending = $this->recovery->pendingLink($token);

        return Answers::linkPage($request, 'Auth/AccountRecoveryLink', [
            'link' => $pending === null ? ['state' => 'invalid'] : ['state' => 'confirm', 'waitHours' => (int) config('identity.recovery.wait_hours')],
        ]);
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        $pending = $this->recovery->pendingLink($token);
        if ($pending === null) {
            return back()->withErrors(['form' => 'link_invalid']);
        }
        $this->recovery->confirm($pending, RequestContext::from($request));

        return redirect('/sign-in')->with('flash', Answers::flash('info', 'recovery_started'));
    }

    public function decline(string $token): RedirectResponse
    {
        if ($pending = $this->recovery->pendingLink($token)) {
            $this->recovery->decline($pending);
        }

        return redirect('/sign-in')->with('flash', Answers::flash('info', 'recovery_declined'));
    }

    /** `DELETE /account-recovery`: cancel from any signed-in session. */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);
        $this->recovery->cancelByUser($user, RequestContext::from($request));

        return redirect('/account/security')->with('flash', Answers::flash('info', 'recovery_cancelled'));
    }

    public function finishShow(Request $request, string $token): Response
    {
        $recovery = $this->recovery->finishLink($token);

        return Answers::linkPage($request, 'Auth/AccountRecoveryFinish', [
            'link' => $recovery === null ? ['state' => 'invalid'] : ['state' => 'valid'],
            'passwordPolicy' => PasswordPolicy::fromConfig()->toProp(),
        ]);
    }

    public function finish(Request $request, string $token, SignInSessions $sessions): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:4096', 'confirmed']]);
        $recovery = $this->recovery->finishLink($token);
        if ($recovery === null) {
            return back()->withErrors(['form' => 'link_invalid']);
        }
        $user = $recovery->user;
        if ($error = PasswordPolicy::fromConfig()->violation((string) $data['password'], $user->email)) {
            throw ValidationException::withMessages(['password' => $error]);
        }
        DB::transaction(function () use ($recovery, $user, $data): void {
            $this->recovery->consumeFinish($recovery);
            $user->forceFill(['password_hash' => Hash::make((string) $data['password'])])->save();
        });
        $sessions->complete($request, $user, 'recovery_finish', null, mayClaim: false);

        return redirect('/account/security/review');
    }
}
