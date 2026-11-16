<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Infrastructure\Clock\Clock;
use App\Infrastructure\Persistence\Bytea;
use App\Infrastructure\RateLimit\Throttle;
use App\Modules\Identity\Domain\Audit;
use App\Modules\Identity\Domain\IdentityRefusal;
use App\Modules\Identity\Domain\Notifier;
use App\Modules\Identity\Domain\RequestContext;
use App\Modules\Identity\Domain\SessionKeys;
use App\Modules\Identity\Domain\SignInOutcome;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\WebAuthn\AssertionCheck;
use App\Modules\Identity\Domain\WebAuthn\AssertionPolicy;
use App\Modules\Identity\Domain\WebAuthn\RelyingParty;
use App\Modules\Identity\Domain\WebAuthnCredential;
use App\Modules\Identity\Events\WebAuthnCounterAnomaly;
use App\Modules\Identity\Events\WebAuthnCredentialAdded;
use Carbon\CarbonImmutable;
use CBOR\Decoder;
use CBOR\Normalizable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Uuid;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\StringStream;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * WebAuthn registration and assertion ceremonies (contracts §3.4, domain
 * §2.2) on `web-auth/webauthn-lib` 5.x. Challenges are 32 random bytes kept
 * in the (encrypted) server session with the ceremony type and user, single
 * use (pulled before anything is checked) and valid for 5 minutes.
 */
final readonly class WebAuthnCeremonies
{
    public const array KINDS = ['passkey', 'security_key'];

    public function __construct(
        private Clock $clock,
        private Audit $audit,
        private Notifier $notifier,
        private Throttle $throttle,
        private SignInThrottle $signInThrottle,
        private SecondFactorChallenge $challenge,
        private SecondFactorFailures $failures,
        private SignInSessions $sessions,
        private TwoFactorState $twoFactor,
    ) {}

    public function relyingParty(): RelyingParty
    {
        return RelyingParty::fromConfig();
    }

    // ---------------------------------------------------------------- registration

    /** `POST /webauthn/register/options`: creation options JSON. */
    public function registrationOptions(Request $request, User $user, string $kind): string
    {
        $rp = $this->available();
        AccountSecurity::requireVerified($user);
        if (! in_array($kind, self::KINDS, true)) {
            throw new IdentityRefusal('kind_invalid');
        }
        if ($user->webauthnCredentials()->count() >= (int) config('identity.webauthn.max_credentials')) {
            throw new IdentityRefusal('too_many_keys');
        }
        $passkey = $kind === 'passkey';
        $options = PublicKeyCredentialCreationOptions::create(
            $rp->entity(),
            PublicKeyCredentialUserEntity::create($user->email, $user->webauthn_user_handle, $user->name ?? $user->email),
            random_bytes(32),
            $rp->parameters(),
            AuthenticatorSelectionCriteria::create(
                null,
                $passkey ? AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED : AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_DISCOURAGED,
                $passkey ? AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED : AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_DISCOURAGED,
            ),
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            $user->webauthnCredentials()->get()->map(fn (WebAuthnCredential $c): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create('public-key', $c->credential_id))->all(),
            RelyingParty::TIMEOUT_MS,
        );
        $json = $rp->toJson($options);
        $this->store($request, ['type' => 'register', 'mode' => $kind, 'user_id' => $user->id, 'options' => $json]);

        return $json;
    }

    /**
     * `POST /webauthn/register`: verifies the attestation (format `none`
     * only) and stores the credential.
     *
     * @return array{key: WebAuthnCredential, recoveryCodes: list<string>|null}
     */
    public function register(Request $request, User $user, string $credentialJson, ?string $name): array
    {
        $rp = $this->available();
        AccountSecurity::requireVerified($user);
        $state = $this->pull($request, 'register', $user->id);
        $kind = (string) $state['mode'];
        try {
            /** @var PublicKeyCredentialCreationOptions $options */
            $options = $rp->serializer()->deserialize((string) $state['options'], PublicKeyCredentialCreationOptions::class, 'json');
            $credential = $this->parse($rp, $credentialJson);
            if (! $credential->response instanceof AuthenticatorAttestationResponse) {
                throw new IdentityRefusal('registration_failed');
            }
            $record = $rp->attestationValidator()->check($credential->response, $options, $rp->id);
        } catch (Throwable) {
            throw new IdentityRefusal('registration_failed');
        }
        $alg = self::algorithmOf($record->credentialPublicKey);
        if (! in_array($alg, RelyingParty::ALGORITHMS, true)) {
            throw new IdentityRefusal('registration_failed');
        }
        if ($kind === 'passkey' && $record->uvInitialized !== true) {
            throw new IdentityRefusal('registration_failed');
        }
        $context = RequestContext::from($request);

        return DB::transaction(function () use ($user, $record, $alg, $kind, $name, $context): array {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->webauthnCredentials()->count() >= (int) config('identity.webauthn.max_credentials')) {
                throw new IdentityRefusal('too_many_keys');
            }
            if (WebAuthnCredential::query()->where('credential_id', Bytea::bind($record->publicKeyCredentialId))->exists()) {
                throw new IdentityRefusal('registration_failed');
            }
            $aaguid = $record->aaguid->toRfc4122();
            $key = WebAuthnCredential::query()->create([
                'user_id' => $locked->id,
                'credential_id' => $record->publicKeyCredentialId,
                'public_key' => $record->credentialPublicKey,
                'alg' => $alg,
                'sign_count' => $record->counter,
                'aaguid' => $aaguid === '00000000-0000-0000-0000-000000000000' ? null : $aaguid,
                'discoverable' => $kind === 'passkey',
                'uv_at_registration' => $record->uvInitialized === true,
                'backup_eligible' => $record->backupEligible === true,
                'backup_state' => $record->backupStatus === true,
                'name' => $name !== null && trim($name) !== ''
                    ? AccountSecurity::cleanName($name)
                    : ($kind === 'passkey' ? 'Passkey' : 'Security key').' '.$this->clock->now()->format('Y-m-d'),
            ]);
            $codes = $this->twoFactor->refresh($locked, $context);
            $this->audit->record('key_added', $locked, $context, ['kind' => $kind, 'keyName' => $key->name]);
            $this->notifier->send($locked->email, 'key-added', ['keyName' => $key->name, 'kind' => $kind]);
            WebAuthnCredentialAdded::dispatch($locked->id, $key->id, $kind);

            return ['key' => $key, 'recoveryCodes' => $codes];
        });
    }

    // ---------------------------------------------------------------- assertion

    /**
     * `POST /webauthn/assert/options`. The mode is decided on the server:
     * `confirm` for a signed-in user asking for it, `second_factor` while a
     * 2FA challenge is pending, else discoverable passkey sign-in.
     */
    public function assertionOptions(Request $request, ?User $signedIn, bool $confirm): string|CarbonImmutable
    {
        $rp = $this->available();
        $pending = $this->challenge->pendingUser($request);
        if ($confirm && $signedIn !== null) {
            [$mode, $user] = ['confirm', $signedIn];
        } elseif ($signedIn === null && $pending !== null) {
            [$mode, $user] = ['second_factor', $pending];
        } elseif ($signedIn === null) {
            [$mode, $user] = ['passkey', null];
            $context = RequestContext::from($request);
            $c = config('identity.throttle.passkey_options');
            $key = "webauthn:options:{$context->addressKey}";
            if (! $this->throttle->reserve($key, $c['max'], $c['decay'])) {
                return $this->throttle->availableAt($key);
            }
        } else {
            throw new IdentityRefusal('not_applicable');
        }

        $allow = [];
        if ($user !== null) {
            $allow = $user->webauthnCredentials()->whereNull('flagged_at')->get()
                ->map(fn (WebAuthnCredential $c): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create('public-key', $c->credential_id))->all();
            if ($allow === []) {
                throw new IdentityRefusal('no_credentials');
            }
        }
        $options = PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            $rp->id,
            $allow,
            $mode === 'passkey' ? PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED : PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_DISCOURAGED,
            RelyingParty::TIMEOUT_MS,
        );
        $json = $rp->toJson($options);
        $this->store($request, ['type' => 'assert', 'mode' => $mode, 'user_id' => $user?->id, 'options' => $json]);

        return $json;
    }

    /**
     * `POST /webauthn/assert`: passkey sign-in (completes sign-in and 2FA)
     * or the second factor of a pending challenge. Completes the sign-in on
     * success.
     */
    public function assert(Request $request, string $credentialJson): SignInOutcome
    {
        $rp = $this->available();
        $state = $request->session()->pull(SessionKeys::WEBAUTHN);
        if (! $this->fresh($state, 'assert') || ! in_array($state['mode'], ['passkey', 'second_factor'], true)) {
            return new SignInOutcome(SignInOutcome::RESTART);
        }
        $context = RequestContext::from($request);

        if ($state['mode'] === 'second_factor') {
            $pending = $this->challenge->pendingUser($request);
            if ($pending === null || $pending->id !== $state['user_id']) {
                return new SignInOutcome(SignInOutcome::RESTART);
            }
            $firstFactor = $this->challenge->firstFactor($request);
            $used = null;
            $outcome = $this->challenge->attempt($request, 'webauthn', function (User $locked) use ($rp, $state, $credentialJson, $context, &$used): bool {
                $used = $this->verify($rp, (string) $state['options'], $credentialJson, $locked, false, $context);

                return $used !== null;
            });
            if ($outcome->status === SignInOutcome::SIGNED_IN && $outcome->user !== null && $used instanceof WebAuthnCredential) {
                $second = AssertionPolicy::isPasskey($used) ? 'passkey' : 'security_key';
                $this->sessions->complete($request, $outcome->user, $firstFactor, $second, mayClaim: $firstFactor !== 'reset_link');
            }

            return $outcome;
        }

        if ($retryAt = $this->signInThrottle->reserveAddressFailure($context->addressKey)) {
            return new SignInOutcome(SignInOutcome::THROTTLED, $retryAt);
        }
        $used = $this->verify($rp, (string) $state['options'], $credentialJson, null, true, $context);
        $user = $used?->user;
        // DL-2: a tombstoned or disabled account answers as unknown.
        if ($used === null || $user === null || ! $user->isLive() || ! $user->isVerified()) {
            $this->signInThrottle->passkeyFailed();

            return new SignInOutcome(SignInOutcome::FAILED);
        }
        $this->signInThrottle->refundAddressFailure($context->addressKey);
        $this->sessions->complete($request, $user, 'passkey', null);

        return new SignInOutcome(SignInOutcome::SIGNED_IN, null, $user);
    }

    /**
     * `POST /confirm-auth` with `{credential}` from the `purpose=confirm`
     * options. Counts toward WA-3 like any second-factor failure.
     */
    public function confirm(Request $request, User $user, string $credentialJson): bool|CarbonImmutable
    {
        $rp = $this->available();
        $state = $request->session()->pull(SessionKeys::WEBAUTHN);
        if (! $this->fresh($state, 'assert') || $state['mode'] !== 'confirm' || $state['user_id'] !== $user->id) {
            return false;
        }
        $context = RequestContext::from($request);

        return $this->failures->guarded($user, 'webauthn', $context,
            fn (User $locked): bool => $this->verify($rp, (string) $state['options'], $credentialJson, $locked, false, $context) !== null);
    }

    /**
     * Library verification first (origin, RP ID hash, challenge, UP, UV as
     * requested, BE, signature), then AssertionPolicy: WA-1 counter rule and
     * WA-2 user handle, which flag the credential. Returns the credential on
     * success, null on any refusal.
     */
    private function verify(RelyingParty $rp, string $optionsJson, string $credentialJson, ?User $owner, bool $discoverable, RequestContext $context): ?WebAuthnCredential
    {
        try {
            /** @var PublicKeyCredentialRequestOptions $options */
            $options = $rp->serializer()->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
            $credential = $this->parse($rp, $credentialJson);
        } catch (Throwable) {
            return null;
        }
        $response = $credential->response;
        if (! $response instanceof AuthenticatorAssertionResponse) {
            return null;
        }
        $key = WebAuthnCredential::query()->where('credential_id', Bytea::bind($credential->rawId))
            ->when($owner !== null, fn ($q) => $q->where('user_id', $owner?->id))
            ->first();
        if ($key === null || ($discoverable && ! AssertionPolicy::isPasskey($key))) {
            return null;
        }
        $ownerHandle = $key->user->webauthn_user_handle;

        try {
            $rp->assertionValidator()->check($this->record($key, $ownerHandle), $response, $options, $rp->id, null);
        } catch (Throwable) {
            return null;
        }

        $authData = $response->authenticatorData;
        $check = AssertionPolicy::check(
            $key,
            $ownerHandle,
            $authData->signCount,
            $authData->isBackupEligible(),
            $authData->isUserVerified(),
            $discoverable,
            $response->userHandle === '' ? null : $response->userHandle,
        );
        if ($check === AssertionCheck::CounterAnomaly || $check === AssertionCheck::UserHandleMismatch) {
            $this->flag($key, $check->value, $context);

            return null;
        }
        if ($check === AssertionCheck::Flagged) {
            $this->audit->record('key_refused', $key->user, $context, ['keyName' => $key->name, 'cause' => $key->flag_cause]);

            return null;
        }
        if ($check !== AssertionCheck::Accepted) {
            return null;
        }

        $key->forceFill([
            'sign_count' => $authData->signCount,
            'backup_state' => $authData->isBackedUp(),
            'last_used_at' => $this->clock->now(),
        ])->save();

        return $key;
    }

    private function flag(WebAuthnCredential $key, string $cause, RequestContext $context): void
    {
        $key->forceFill([
            'flagged_at' => $this->clock->now(),
            'flag_cause' => $cause,
            'flag_count' => $key->flag_count + 1,
        ])->save();
        $user = $key->user;
        $kind = AssertionPolicy::isPasskey($key) ? 'passkey' : 'security_key';
        $this->audit->record('key_blocked', $user, $context, ['keyName' => $key->name, 'kind' => $kind, 'cause' => $cause]);
        $this->notifier->send($user->email, 'key-blocked', ['keyName' => $key->name, 'cause' => $cause]);
        WebAuthnCounterAnomaly::dispatch($user->id, $key->id, $cause);
        $this->twoFactor->refresh($user, $context);
    }

    private function record(WebAuthnCredential $key, string $ownerHandle): CredentialRecord
    {
        return CredentialRecord::create(
            $key->credential_id,
            'public-key',
            [],
            'none',
            EmptyTrustPath::create(),
            Uuid::fromString($key->aaguid ?? '00000000-0000-0000-0000-000000000000'),
            $key->public_key,
            $ownerHandle,
            $key->sign_count,
            null,
            $key->backup_eligible,
            $key->backup_state,
            $key->uv_at_registration,
        );
    }

    private function parse(RelyingParty $rp, string $json): PublicKeyCredential
    {
        return $rp->serializer()->deserialize($json, PublicKeyCredential::class, 'json');
    }

    private static function algorithmOf(string $coseKey): int
    {
        $stream = new StringStream($coseKey);
        $decoded = Decoder::create()->decode($stream);
        $data = $decoded instanceof Normalizable ? $decoded->normalize() : null;

        return is_array($data) && isset($data[3]) ? (int) $data[3] : 0;
    }

    private function available(): RelyingParty
    {
        $rp = $this->relyingParty();
        if (! $rp->available()) {
            throw new IdentityRefusal('webauthn_unavailable');
        }

        return $rp;
    }

    /** @param  array<string, mixed>  $state */
    private function store(Request $request, array $state): void
    {
        $request->session()->put(SessionKeys::WEBAUTHN, $state + ['at' => $this->clock->now()->getTimestamp()]);
    }

    /** @return array<string, mixed> */
    private function pull(Request $request, string $type, string $userId): array
    {
        $state = $request->session()->pull(SessionKeys::WEBAUTHN);
        if (! $this->fresh($state, $type) || $state['user_id'] !== $userId) {
            throw new IdentityRefusal('challenge_expired');
        }

        return $state;
    }

    /** @phpstan-assert-if-true array<string, mixed> $state */
    private function fresh(mixed $state, string $type): bool
    {
        return is_array($state)
            && ($state['type'] ?? null) === $type
            && is_string($state['options'] ?? null)
            && $this->clock->now()->getTimestamp() - (int) ($state['at'] ?? 0) <= intdiv(RelyingParty::TIMEOUT_MS, 1000);
    }
}
