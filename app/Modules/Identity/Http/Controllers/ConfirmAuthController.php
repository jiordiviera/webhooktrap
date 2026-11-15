<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Application\AccountRecovery;
use App\Modules\Identity\Application\OAuthFlows;
use App\Modules\Identity\Application\RecentAuthentication;
use App\Modules\Identity\Application\SecondFactorFailures;
use App\Modules\Identity\Application\SignInThrottle;
use App\Modules\Identity\Application\TotpVerifier;
use App\Modules\Identity\Application\WebAuthnCeremonies;
use App\Modules\Identity\Domain\DummyHash;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Answers;
use App\Modules\Identity\Http\ConfirmReasons;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * `/confirm-auth` (S-28, contracts §3.4): recent authentication and the
 * purpose-bound `delete_account` confirmation (DL-1). Failures count toward
 * the per-account sign-in key and the WA-3 counter (TH-1b); during a hold
 * the password is not verified (TH-1a).
 */
final readonly class ConfirmAuthController
{
    public const array ACTIONS = ['confirm', 'change_email', 'delete_account', 'security'];

    public function __construct(private Clock $clock, private Throttle $throttle) {}

    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $action = $this->action($request->query('action'));

        $reason = ConfirmReasons::valid($request->query('reason'));

        return Inertia::render('Auth/ConfirmAuth', [
            'methods' => $this->methods($user, $action),
            'secondFactorRequired' => $this->secondFactorRequired($user, $action),
            'action' => $action,
            // Generic recent auth: "You're about to {verb}." from the allow-list only.
            'reason' => $reason,
            'verb' => ConfirmReasons::verb($reason),
            // `reauth_mismatch` names the linked account ("Sign in to GitHub as maya-l").
            'providerLabels' => $this->providerLabels($user),
            'next' => Answers::safeNext(is_string($request->query('next')) ? $request->query('next') : null, '/account/security'),
            'retryAt' => $request->session()->get(SessionKeys::RETRY_AT) ?? $this->heldUntil($user)?->toIso8601ZuluString('millisecond'),
        ]);
    }

    public function store(
        Request $request,
        RecentAuthentication $recent,
        SignInThrottle $signInThrottle,
        SecondFactorFailures $failures,
        TotpVerifier $totp,
        AccountRecovery $recovery,
        WebAuthnCeremonies $webauthn,
    ): RedirectResponse|JsonResponse {
        $user = $this->user($request);
        $data = $request->validate([
            'action' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'max:4096'],
            'code' => ['nullable', 'string', 'max:32'],
            'credential' => ['nullable', 'string', 'max:16384'],
            'next' => ['nullable', 'string', 'max:2000'],
        ]);
        $action = $this->action($data['action'] ?? null);
        $context = RequestContext::from($request);
        $key = "confirm:{$user->id}";

        if ($until = $this->heldUntil($user)) {
            return Answers::throttled($request, $until, $this->clock->now());
        }
        // F2: this attempt is counted before anything is verified.
        $c = config('identity.throttle.confirm_auth');
        if (! $this->throttle->reserve($key, $c['max'], $c['decay'])) {
            return Answers::throttled($request, $this->holdConfirm($key), $this->clock->now());
        }

        $method = null;
        if (isset($data['credential']) && $data['credential'] !== '') {
            $result = $webauthn->confirm($request, $user, (string) $data['credential']);
            if ($result instanceof CarbonImmutable) {
                return Answers::throttled($request, $result, $this->clock->now());
            }
            $method = $result ? 'webauthn' : null;
        } elseif (isset($data['code']) && $data['code'] !== '') {
            // F2: WA-3 counter under a row lock; during its hold nothing is verified.
            $result = $failures->guarded($user, 'totp', $context, fn (User $locked): bool => $totp->verify($locked, (string) $data['code']));
            if ($result instanceof CarbonImmutable) {
                return Answers::throttled($request, $result, $this->clock->now());
            }
            $method = $result ? 'totp' : null;
        } elseif (isset($data['password']) && ! $this->secondFactorRequired($user, $action)) {
            $emailKey = $signInThrottle->emailKey($user->email);
            // F7 (TH-1a): during the account sign-in hold no password is verified.
            if ($held = $signInThrottle->accountHeldUntil($emailKey)) {
                return Answers::throttled($request, $held, $this->clock->now());
            }
            if (! $signInThrottle->reserveAccount($emailKey)) {
                return Answers::throttled($request, $signInThrottle->accountHeldUntil($emailKey) ?? $this->clock->now()->addMinutes(15), $this->clock->now());
            }
            if (DummyHash::verify((string) $data['password'], $user->password_hash)) {
                $method = 'password';
                $signInThrottle->refundAccount($emailKey);
            } else {
                $signInThrottle->accountFailed($emailKey, $user, $context);
            }
        }

        if ($method === null) {
            if ($this->throttle->attempts($key) >= $c['max']) {
                $this->holdConfirm($key);
            }

            return back()->withErrors(['form' => $this->secondFactorRequired($user, $action) && isset($data['password']) ? 'second_factor_required' : 'confirmation_failed']);
        }
        $this->throttle->refund($key, $c['decay']);

        $recent->recordConfirmation($request, $action, $method);
        if ($method === 'totp' || $method === 'webauthn') {
            $recovery->cancelOnSecondFactor($user, $context); // RC-1
        }

        return redirect(Answers::safeNext($data['next'] ?? null, '/account/security'));
    }

    /**
     * `POST /confirm-auth/{provider}`: OAuth round trip, OAuth-only accounts
     * only. Inertia posts this with XHR, which cannot follow a cross-origin
     * redirect, so an Inertia request gets `409 X-Inertia-Location` and the
     * browser goes to the provider itself; a plain form post gets the redirect.
     */
    public function oauth(Request $request, string $provider, OAuthFlows $oauth): SymfonyResponse
    {
        $user = $this->user($request);
        if (! $user->isOAuthOnly() || ! $user->identities()->where('provider', $provider)->exists()) {
            return back()->withErrors(['form' => 'reauth_mismatch']);
        }
        $next = Answers::safeNext(is_string($request->input('next')) ? $request->input('next') : null, '/account/security');
        $redirect = $oauth->redirect($request, $provider, 'confirm', $user, $next, $this->action($request->input('action')), ConfirmReasons::valid($request->input('reason')));

        return Inertia::location($redirect->getTargetUrl());
    }

    /**
     * Where a failed or cancelled OAuth confirmation lands: S-28 again, with
     * the same action, target and reason.
     */
    public static function retryUrl(?string $action, ?string $next, ?string $reason): string
    {
        $query = array_filter([
            'next' => Answers::safeNext($next, '/account/security'),
            'action' => is_string($action) && in_array($action, self::ACTIONS, true) ? $action : 'confirm',
            'reason' => ConfirmReasons::valid($reason),
        ], fn ($v) => $v !== null);

        return '/confirm-auth?'.http_build_query($query);
    }

    /** @return array<string, string> provider => label, for an OAuth-only account */
    private function providerLabels(User $user): array
    {
        if (! $user->isOAuthOnly()) {
            return [];
        }
        $labels = [];
        foreach ($user->identities()->orderBy('provider')->get(['provider', 'provider_label']) as $identity) {
            $labels[(string) $identity->provider] = (string) $identity->provider_label;
        }

        return $labels;
    }

    private function holdConfirm(string $key): CarbonImmutable
    {
        $until = $this->throttle->hold($key, (int) config('identity.throttle.confirm_auth.decay'));
        $this->throttle->clear($key);

        return $until;
    }

    /** @return list<string> */
    private function methods(User $user, string $action): array
    {
        $methods = [];
        if ($user->webauthnCredentials()->whereNull('flagged_at')->exists()) {
            $methods[] = 'webauthn';
        }
        if ($user->hasPassword() && ! $this->secondFactorRequired($user, $action)) {
            $methods[] = 'password';
        }
        if ($user->hasConfirmedTotp()) {
            $methods[] = 'totp';
        }
        if ($user->isOAuthOnly()) {
            foreach ($user->identities()->orderBy('provider')->pluck('provider') as $provider) {
                $methods[] = (string) $provider;
            }
        }

        return $methods;
    }

    private function secondFactorRequired(User $user, string $action): bool
    {
        return in_array($action, ['change_email', 'delete_account'], true) && $user->hasSecondFactor();
    }

    private function heldUntil(User $user): ?CarbonImmutable
    {
        return $this->throttle->heldUntil("confirm:{$user->id}");
    }

    private function action(mixed $action): string
    {
        return is_string($action) && in_array($action, self::ACTIONS, true) ? $action : 'confirm';
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}
