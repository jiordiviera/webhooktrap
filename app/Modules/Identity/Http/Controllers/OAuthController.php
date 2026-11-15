<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\OAuthFlows;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\SignInOutcome;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Answers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class OAuthController
{
    public function __construct(private OAuthFlows $oauth) {}

    /** `GET /sign-in/{provider}` */
    public function redirect(Request $request, string $provider): SymfonyRedirect|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/');
        }

        return $this->oauth->redirect($request, $provider, 'sign_in');
    }

    /**
     * `POST /account/security/identities/{provider}/connect` (recent auth).
     * A POST, so a sign-in `next` can never land on it (A09 delta review D2);
     * answered with `Inertia::location` to the provider, like `/confirm-auth/{provider}`.
     */
    public function connect(Request $request, string $provider): SymfonyResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return Inertia::location($this->oauth->redirect($request, $provider, 'link', $user)->getTargetUrl());
    }

    /**
     * `GET /auth/{provider}/callback`: one callback for every purpose. A
     * cancel or a provider error returns to where the flow started: S-02 for
     * sign-in, S-28 for a confirmation, Security for a connect.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $result = $this->oauth->callback($request, $provider);
        $purpose = $result['purpose'];
        $error = $result['error'] ?? null;

        if ($purpose === 'link') {
            return match ($error) {
                null => redirect('/account/security')->with('flash', Answers::flash('success', 'identity_linked', $provider)),
                'oauth_cancelled' => redirect('/account/security')->with('flash', Answers::flash('info', 'nothing_changed')),
                default => redirect('/account/security')->withErrors(['form' => $error]),
            };
        }
        if ($purpose === 'confirm') {
            if ($error !== null) {
                return redirect(ConfirmAuthController::retryUrl($result['action'] ?? null, $result['next'] ?? null, $result['reason'] ?? null))
                    ->withErrors(['form' => $error]);
            }

            return redirect(Answers::safeNext($result['next'] ?? null, '/account/security'));
        }
        if ($error !== null) {
            return redirect('/sign-in')->withErrors(['form' => $error])->with(SessionKeys::OAUTH_PROVIDER, $provider);
        }

        $outcome = $result['outcome'] ?? new SignInOutcome(SignInOutcome::FAILED);

        return match ($outcome->status) {
            SignInOutcome::THROTTLED => redirect('/sign-in')->withErrors(['form' => 'throttled'])
                ->with('identity.retry_at', $outcome->retryAt?->toIso8601ZuluString('millisecond')),
            SignInOutcome::CHALLENGE => redirect('/two-factor'),
            SignInOutcome::SIGNED_IN => SignInController::afterSignIn($outcome->user),
            default => redirect('/sign-in')->withErrors(['form' => 'invalid_credentials']),
        };
    }
}
