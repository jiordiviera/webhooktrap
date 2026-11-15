<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\AccountDeletion;
use App\Modules\Identity\Application\RecentAuthentication;
use App\Modules\Identity\Application\SecurityViews;
use App\Modules\Identity\Application\SignInSessions;
use App\Modules\Identity\Domain\AccountDeletionRequest;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Account deletion (domain §2.10; DL-1, DL-4; contracts §3.4). */
final readonly class AccountDeletionController
{
    public function __construct(private AccountDeletion $deletion) {}

    public function show(Request $request, SecurityViews $views): Response
    {
        return Inertia::render('Account/Delete', ['deletion' => $views->deletion($this->user($request))]);
    }

    /** `DELETE /account` */
    public function destroy(Request $request, RecentAuthentication $recent, SignInSessions $sessions): RedirectResponse
    {
        $user = $this->user($request);
        if (($blocked = $this->deletion->blocked($user)) !== null) {
            throw new IdentityRefusal(AccountDeletion::refusalCode($blocked));
        }
        if ($request->input('confirm') !== true && $request->input('confirm') !== 'true') {
            throw new IdentityRefusal('confirmation_required');
        }
        $twoFactor = $user->hasSecondFactor();
        $state = $recent->deleteConfirmationState($request, $twoFactor);
        if ($state !== 'ok') {
            throw new IdentityRefusal($state);
        }
        $recent->consumeConfirmation($request);
        $context = RequestContext::from($request);

        if (! $twoFactor) {
            $this->deletion->requestLink($user, $context);

            return redirect('/account/delete')->with('flash', Answers::flash('info', 'deletion_link_sent'));
        }

        $this->deletion->tombstone($user, 'user', $context);
        $sessions->endBrowserSession($request);

        return redirect('/')->with('flash', Answers::flash('success', 'account_deleted'));
    }

    /** `DELETE /account/deletion`: cancels the pending link (idempotent). */
    public function cancel(Request $request): RedirectResponse
    {
        $this->deletion->cancelPending($this->user($request), RequestContext::from($request));

        return redirect('/account/delete')->with('flash', Answers::flash('info', 'deletion_cancelled'));
    }

    public function showLink(Request $request, string $token): Response
    {
        return Answers::linkPage($request, 'Auth/AccountDeletionLink', ['link' => $this->state($request, $this->deletion->byToken($token))]);
    }

    public function confirmLink(Request $request, string $token, SignInSessions $sessions): RedirectResponse
    {
        $pending = $this->deletion->byToken($token);
        $viewer = $request->user();
        if ($pending === null || ! $viewer instanceof User || $viewer->id !== $pending->user_id) {
            return back()->withErrors(['form' => 'link_invalid']);
        }
        if ($this->deletion->blocked($viewer) !== null) {
            return back()->withErrors(['form' => 'blocked']);
        }
        $this->deletion->tombstone($viewer, 'user', RequestContext::from($request), $pending);
        $sessions->endBrowserSession($request);

        return redirect('/')->with('flash', Answers::flash('success', 'account_deleted'));
    }

    /** @return array<string, mixed> */
    private function state(Request $request, ?AccountDeletionRequest $pending): array
    {
        $viewer = $request->user();
        if ($pending === null) {
            return ['state' => 'invalid'];
        }
        if (! $viewer instanceof User) {
            $request->session()->put(SessionKeys::INTENDED, $request->getRequestUri());

            return ['state' => 'sign_in_required'];
        }
        if ($viewer->id !== $pending->user_id) {
            $request->session()->put(SessionKeys::INTENDED, $request->getRequestUri());

            return ['state' => 'other_account', 'signedInAs' => $viewer->email];
        }

        return array_filter([
            'state' => 'confirm',
            'expiresAt' => $pending->expires_at->toIso8601ZuluString('millisecond'),
            'blocked' => $this->deletion->blocked($viewer),
        ], fn ($v) => $v !== null);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}
