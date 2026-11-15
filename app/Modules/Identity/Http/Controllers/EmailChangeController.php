<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Persistence\EmailMask;
use App\Modules\Identity\Application\EmailChanges;
use App\Modules\Identity\Application\RecentAuthentication;
use App\Modules\Identity\Domain\EmailChangeRequest;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Email change (domain §2.6, EC-1). Link pages have four states; while the
 * person signs in, the link is the session's intended URL, never a `next`
 * parameter.
 */
final readonly class EmailChangeController
{
    public function __construct(private EmailChanges $changes) {}

    public function request(Request $request, RecentAuthentication $recent): RedirectResponse
    {
        $user = $this->user($request);
        if ($user->hasSecondFactor() && ! $recent->isRecent($request, requireSecondFactor: true)) {
            throw new IdentityRefusal('second_factor_required');
        }
        $email = (string) $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:254']])['email'];
        $this->changes->request($user, $email, RequestContext::from($request));

        return redirect('/account/security')->with('flash', Answers::flash('info', 'email_change_sent', $email, ['sentTo' => $email]));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->changes->cancel($this->user($request), RequestContext::from($request));

        return redirect('/account/security')->with('flash', Answers::flash('info', 'email_change_cancelled'));
    }

    public function showNew(Request $request, string $token): Response
    {
        return $this->page($request, $token, 'new');
    }

    public function showOld(Request $request, string $token): Response
    {
        return $this->page($request, $token, 'old');
    }

    public function confirmNew(Request $request, string $token): RedirectResponse
    {
        return $this->confirm($request, $token, 'new');
    }

    public function confirmOld(Request $request, string $token): RedirectResponse
    {
        return $this->confirm($request, $token, 'old');
    }

    private function page(Request $request, string $token, string $side): Response
    {
        $component = $side === 'new' ? 'Auth/EmailChangeLink' : 'Auth/EmailChangeOldLink';
        $change = $this->changes->byToken($token, $side);
        $viewer = $request->user();

        return Answers::linkPage($request, $component, ['link' => $this->state($request, $change, $viewer instanceof User ? $viewer : null, $side)]);
    }

    /** @return array<string, mixed> */
    private function state(Request $request, ?EmailChangeRequest $change, ?User $viewer, string $side): array
    {
        if ($change === null) {
            return ['state' => 'invalid'];
        }
        if ($viewer === null) {
            $request->session()->put(SessionKeys::INTENDED, $request->getRequestUri());

            return ['state' => 'sign_in_required'];
        }
        if ($viewer->id !== $change->user_id) {
            $request->session()->put(SessionKeys::INTENDED, $request->getRequestUri());

            return ['state' => 'other_account', 'signedInAs' => $viewer->email];
        }
        if ($this->changes->blockedFor($change)) {
            return ['state' => 'invalid'];
        }

        return $side === 'new'
            ? ['state' => 'confirm', 'newEmail' => $change->new_email, 'expiresAt' => $change->expires_at->toIso8601ZuluString('millisecond'), 'needsOld' => $change->old_required && $change->old_confirmed_at === null]
            : ['state' => 'confirm', 'newEmailMasked' => EmailMask::mask($change->new_email), 'expiresAt' => $change->expires_at->toIso8601ZuluString('millisecond'), 'newHalfDone' => $change->new_confirmed_at !== null];
    }

    private function confirm(Request $request, string $token, string $side): RedirectResponse
    {
        $change = $this->changes->byToken($token, $side);
        $viewer = $request->user();
        if ($change === null || ! $viewer instanceof User || $viewer->id !== $change->user_id) {
            return back()->withErrors(['form' => 'link_invalid']);
        }
        $sessionId = $request->session()->get(SessionKeys::USER_SESSION);
        $result = $this->changes->confirm($change, $side, is_string($sessionId) ? $sessionId : null, RequestContext::from($request));

        return redirect('/account/security')->with('flash', match ($result) {
            'waiting_old' => Answers::flash('info', 'email_change_waiting_old'),
            'waiting_new' => Answers::flash('info', 'email_change_waiting_new'),
            default => Answers::flash('success', 'email_changed'),
        });
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}
