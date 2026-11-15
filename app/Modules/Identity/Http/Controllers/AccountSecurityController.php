<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\AccountSecurity;
use App\Modules\Identity\Application\SecurityViews;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** `/account/security` and its actions (contracts §3.4). */
final readonly class AccountSecurityController
{
    public function __construct(private AccountSecurity $security, private SecurityViews $views) {}

    public function show(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Account/Security', [
            'security' => $this->views->security($user, $this->sessionId($request)),
            // Shown once: TOTP setup secret, recovery codes.
            'totpSetup' => $request->session()->get('identity.totp_setup'),
            'recoveryCodes' => $request->session()->get('identity.recovery_codes'),
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:4096', 'confirmed']]);
        $this->security->changePassword($this->user($request), (string) $data['password'], $this->sessionId($request), RequestContext::from($request));
        $request->session()->migrate(true);

        return redirect('/account/security')->with('flash', Answers::flash('success', 'password_changed'));
    }

    public function startTotp(Request $request): RedirectResponse
    {
        $setup = $this->security->startTotp($this->user($request));

        return redirect('/account/security')->with('identity.totp_setup', $setup);
    }

    public function confirmTotp(Request $request): RedirectResponse
    {
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:16']])['code'];
        $codes = $this->security->confirmTotp($this->user($request), $code, RequestContext::from($request));

        return redirect('/account/security')
            ->with('flash', Answers::flash('success', 'totp_enabled'))
            ->with('identity.recovery_codes', $codes);
    }

    public function disableTotp(Request $request): RedirectResponse
    {
        $this->security->disableTotp($this->user($request), RequestContext::from($request));

        return redirect('/account/security')->with('flash', Answers::flash('success', 'totp_disabled'));
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        $codes = $this->security->regenerateRecoveryCodes($this->user($request), RequestContext::from($request));

        return redirect('/account/security')->with('identity.recovery_codes', $codes);
    }

    public function renameKey(Request $request, string $keyId): JsonResponse|RedirectResponse
    {
        $name = (string) $request->validate(['name' => ['required', 'string', 'max:200']])['name'];
        $key = $this->security->renameKey($this->user($request), $keyId, $name);

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? new JsonResponse($this->views->keyRow($key))
            : redirect('/account/security');
    }

    public function removeKey(Request $request, string $keyId): RedirectResponse
    {
        $this->security->removeKey($this->user($request), $keyId, RequestContext::from($request));

        return redirect('/account/security')->with('flash', Answers::flash('success', 'key_removed'));
    }

    public function unblockKey(Request $request, string $keyId): JsonResponse|RedirectResponse
    {
        $key = $this->security->unblockKey($this->user($request), $keyId, RequestContext::from($request));

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? new JsonResponse($this->views->keyRow($key))
            : redirect('/account/security')->with('flash', Answers::flash('success', 'key_unblocked'));
    }

    public function disconnect(Request $request, string $provider): RedirectResponse
    {
        $this->security->disconnectIdentity($this->user($request), $provider, RequestContext::from($request));

        return redirect('/account/security')->with('flash', Answers::flash('success', 'identity_unlinked', $provider));
    }

    public function revokeSession(Request $request, string $sessionId): RedirectResponse
    {
        $result = $this->security->revokeSession($this->user($request), $sessionId, $this->sessionId($request), RequestContext::from($request));

        return redirect('/account/security')->with('flash', $result === 'revoked'
            ? Answers::flash('success', 'session_revoked')
            : Answers::flash('info', 'session_already_ended'));
    }

    public function revokeOthers(Request $request): RedirectResponse
    {
        $count = $this->security->revokeOtherSessions($this->user($request), $this->sessionId($request), RequestContext::from($request));

        return redirect('/account/security')->with('flash', $count > 0
            ? Answers::flash('success', 'sessions_revoked')
            : Answers::flash('info', 'no_other_sessions'));
    }

    public function review(Request $request): Response|RedirectResponse
    {
        $review = $this->views->review($this->user($request));
        if ($review === null) {
            return redirect('/account/security');
        }

        return Inertia::render('Account/SecurityReview', ['review' => $review]);
    }

    private function sessionId(Request $request): ?string
    {
        $id = $request->session()->get(SessionKeys::USER_SESSION);

        return is_string($id) ? $id : null;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}
