<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\SignInOutcome;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserIdentity;
use App\Modules\Identity\Events\UserRegistered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

/**
 * GitHub and Google through Socialite (domain §2.1, §2.5). The OAuth
 * `state` is bound to the browser session (Socialite) and PKCE is on; the
 * purpose (`sign_in`, `link`, `confirm`) lives in the session, never in a URL.
 */
final readonly class OAuthFlows
{
    public const array PROVIDERS = ['github', 'google'];

    public function __construct(
        private Clock $clock,
        private IdentityLinks $links,
        private SignInThrottle $throttle,
        private SecondFactorChallenge $challenge,
        private SignInSessions $sessions,
        private EmailOwnership $ownership,
        private GuestSessions $guests,
        private RecentAuthentication $recent,
    ) {}

    public function redirect(Request $request, string $provider, string $purpose, ?User $user = null, ?string $next = null, ?string $action = null, ?string $reason = null): RedirectResponse
    {
        $request->session()->put(SessionKeys::OAUTH, [
            'purpose' => $purpose,
            'provider' => $provider,
            'user_id' => $user?->id,
            'next' => $next,
            'action' => $action,
            'reason' => $reason,
        ]);
        if ($purpose === 'sign_in') {
            $this->guests->beginClaim($request);
        }
        $driver = Socialite::driver($provider);
        if ($driver instanceof AbstractProvider) {
            $driver->enablePKCE();
            if ($provider === 'google' && $purpose === 'confirm') {
                $driver->with(['prompt' => 'login', 'max_age' => '0']);
            }
        }

        return $driver->redirect();
    }

    /**
     * The provider's answer, for any purpose. A refusal or a provider-side
     * problem comes back as `error` (a contract code), never as an
     * exception: `oauth_cancelled` when the person declined at the provider
     * (`error=access_denied`), `oauth_failed` for anything else the provider
     * or the exchange got wrong. Neither changes anything.
     *
     * @return array{purpose: string, outcome?: SignInOutcome, error?: string, next?: string|null, action?: string|null, reason?: string|null}
     */
    public function callback(Request $request, string $provider): array
    {
        $flow = $request->session()->pull(SessionKeys::OAUTH);
        if (! is_array($flow) || ($flow['provider'] ?? null) !== $provider) {
            return ['purpose' => 'sign_in', 'error' => 'oauth_failed'];
        }
        $purpose = (string) $flow['purpose'];
        $context = [
            'purpose' => $purpose,
            'next' => is_string($flow['next'] ?? null) ? $flow['next'] : null,
            'action' => is_string($flow['action'] ?? null) ? $flow['action'] : null,
            'reason' => is_string($flow['reason'] ?? null) ? $flow['reason'] : null,
        ];

        // RFC 6749 §4.1.2.1: the provider sends the person back with `error`
        // and no code. `access_denied` is the person saying no.
        $error = $request->query('error');
        if ($error !== null) {
            // The provider's own error code only (RFC 6749 §4.1.2.1), never the query string.
            Log::warning('oauth.provider_error', ['provider' => $provider, 'error' => is_string($error) ? mb_substr($error, 0, 64) : 'invalid']);

            return $context + ['error' => $error === 'access_denied' ? 'oauth_cancelled' : 'oauth_failed'];
        }
        try {
            $driver = Socialite::driver($provider);
            // The exchange must send the code_verifier the redirect's challenge was made from;
            // a fresh driver has PKCE off, and the provider then refuses the code.
            if ($driver instanceof AbstractProvider) {
                $driver->enablePKCE();
            }
            $profile = $driver->user();
        } catch (Throwable $exception) {
            // Class and a short message: enough to tell state, PKCE and token-exchange failures
            // apart. The code, the state and any token never reach the log.
            Log::warning('oauth.exchange_failed', [
                'provider' => $provider,
                'exception' => $exception::class,
                'message' => mb_substr(preg_replace('/(code|state|token|secret)=[^&\s"]+/i', '$1=[redacted]', $exception->getMessage()) ?? '', 0, 300),
            ]);

            return $context + ['error' => 'oauth_failed'];
        }

        try {
            $user = isset($flow['user_id']) ? User::query()->find((string) $flow['user_id']) : null;
            // `link` and `confirm` belong to the account signed in when the flow started.
            if ($purpose !== 'sign_in' && ($user === null || $request->user()?->getAuthIdentifier() !== $user->id)) {
                throw new IdentityRefusal('reauth_mismatch');
            }

            if ($purpose === 'link') {
                $this->link($request, $this->requireUser($user), $provider, $profile);

                return $context;
            }
            if ($purpose === 'confirm') {
                $this->confirm($request, $this->requireUser($user), $provider, $profile, $context['action'] ?? 'confirm');

                return $context;
            }

            return ['purpose' => 'sign_in', 'outcome' => $this->signIn($request, $provider, $profile)];
        } catch (IdentityRefusal $refusal) {
            return $context + ['error' => $refusal->errorCode];
        }
    }

    public function signIn(Request $request, string $provider, SocialiteUser $profile): SignInOutcome
    {
        $context = RequestContext::from($request);
        if ($retryAt = $this->throttle->addressBlockedUntil($context->addressKey)) {
            return new SignInOutcome(SignInOutcome::THROTTLED, $retryAt);
        }
        [$email, $verified] = $this->emailOf($provider, $profile);
        $subject = (string) $profile->getId();

        $identity = UserIdentity::query()->where('provider', $provider)->where('provider_subject', $subject)->first();
        if ($identity !== null) {
            $user = $identity->user;
            if (! $user->isLive()) {
                return new SignInOutcome(SignInOutcome::FAILED);
            }
            $identity->forceFill(['provider_label' => $this->labelOf($provider, $profile)])->save();
        } elseif ($email !== null && $verified) {
            $user = User::query()->where('email', $email)->first();
            if ($user !== null) {
                // DL-2: a tombstoned account answers as unknown: no link, no new account.
                if (! $user->isLive() || $user->identities()->where('provider', $provider)->exists()) {
                    return new SignInOutcome(SignInOutcome::FAILED);
                }
                // F1: on a never-verified account this resets it to a clean
                // state (credentials, identities, sessions) before linking.
                $this->ownership->proven($user, 'oauth');
                $user->refresh();
                $pending = $this->identityOf($provider, $profile, true);
                if ($user->hasSecondFactor()) {
                    // F3: the identity is linked only after the challenge succeeds.
                    $this->challenge->begin($request, $user, $provider, $pending);

                    return new SignInOutcome(SignInOutcome::CHALLENGE, null, $user);
                }
                $this->links->create($user, $pending, 'oauth_email_match', $context);
            } else {
                $user = User::query()->create([
                    'email' => $email,
                    'name' => $profile->getName() ?? $profile->getNickname(),
                    'email_verified_at' => $this->clock->now(),
                    'webauthn_user_handle' => random_bytes(32),
                ]);
                $this->links->create($user, $this->identityOf($provider, $profile, true), 'signup', null);
                UserRegistered::dispatch($user->id, $provider);
            }
        } else {
            // Pre-hijack rule: an unverified provider email never links or creates.
            return new SignInOutcome(SignInOutcome::FAILED);
        }

        if ($user->hasSecondFactor()) {
            $this->challenge->begin($request, $user, $provider);

            return new SignInOutcome(SignInOutcome::CHALLENGE, null, $user);
        }
        $this->sessions->complete($request, $user, $provider, null);

        return new SignInOutcome(SignInOutcome::SIGNED_IN, null, $user);
    }

    /** @return array{linked: bool} */
    public function link(Request $request, User $user, string $provider, SocialiteUser $profile): array
    {
        $subject = (string) $profile->getId();
        $other = UserIdentity::query()->where('provider', $provider)->where('provider_subject', $subject)->first();
        if ($other !== null && $other->user_id !== $user->id) {
            throw new IdentityRefusal('identity_in_use');
        }
        if ($other !== null || $user->identities()->where('provider', $provider)->exists()) {
            throw new IdentityRefusal('identity_already_linked');
        }
        // F1: an unverified account links nothing.
        if (! $user->isVerified()) {
            throw new IdentityRefusal('email_unverified');
        }
        [, $verified] = $this->emailOf($provider, $profile);
        $this->links->create($user, $this->identityOf($provider, $profile, $verified), 'settings', RequestContext::from($request));

        return ['linked' => true];
    }

    /** @return array{confirmed: bool} */
    public function confirm(Request $request, User $user, string $provider, SocialiteUser $profile, string $action): array
    {
        $identity = $user->identities()->where('provider', $provider)->first();
        if (! $user->isOAuthOnly() || $identity === null || $identity->provider_subject !== (string) $profile->getId()) {
            throw new IdentityRefusal('reauth_mismatch');
        }
        $this->recent->recordConfirmation($request, $action, $provider);

        return ['confirmed' => true];
    }

    /** @return array{provider: string, subject: string, label: string, verified: bool} */
    private function identityOf(string $provider, SocialiteUser $profile, bool $verified): array
    {
        return ['provider' => $provider, 'subject' => (string) $profile->getId(), 'label' => $this->labelOf($provider, $profile), 'verified' => $verified];
    }

    /** @return array{0: string|null, 1: bool} */
    private function emailOf(string $provider, SocialiteUser $profile): array
    {
        $email = $profile->getEmail();
        if ($email === null || $email === '') {
            return [null, false];
        }
        $raw = method_exists($profile, 'getRaw') ? (array) $profile->getRaw() : [];
        // GitHub: with the `user:email` scope Socialite returns the primary
        // *verified* address, or none. Google: the `email_verified` claim.
        $verified = $provider === 'github' ? true : (($raw['email_verified'] ?? false) === true);

        return [trim($email), $verified];
    }

    private function labelOf(string $provider, SocialiteUser $profile): string
    {
        return mb_substr((string) ($provider === 'github' ? ($profile->getNickname() ?? $profile->getEmail()) : $profile->getEmail()), 0, 100) ?: $provider;
    }

    private function requireUser(?User $user): User
    {
        if ($user === null || ! $user->isLive()) {
            throw new IdentityRefusal('reauth_mismatch');
        }

        return $user;
    }
}
