<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\WebAuthn;

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\EdDSA\EdDSA;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithms;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManager;
use Webauthn\CeremonyStep\CheckAlgorithm;
use Webauthn\CeremonyStep\CheckAllowedCredentialList;
use Webauthn\CeremonyStep\CheckAllowedOrigins;
use Webauthn\CeremonyStep\CheckAttestationFormatIsKnownAndValid;
use Webauthn\CeremonyStep\CheckBackupBitsAreConsistent;
use Webauthn\CeremonyStep\CheckBackupEligibilityIsUnchanged;
use Webauthn\CeremonyStep\CheckChallenge;
use Webauthn\CeremonyStep\CheckClientDataCollectorType;
use Webauthn\CeremonyStep\CheckCredentialId;
use Webauthn\CeremonyStep\CheckExtensions;
use Webauthn\CeremonyStep\CheckHasAttestedCredentialData;
use Webauthn\CeremonyStep\CheckRelyingPartyIdIdHash;
use Webauthn\CeremonyStep\CheckSignature;
use Webauthn\CeremonyStep\CheckTopOrigin;
use Webauthn\CeremonyStep\CheckUserVerification;
use Webauthn\CeremonyStep\CheckUserWasPresent;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRpEntity;

/**
 * The relying party for `web-auth/webauthn-lib` 5.x (domain §2.2, A09's
 * configuration): RP ID and the single allowed origin come from
 * configuration only (`WEBAUTHN_RP_ID`, `APP_URL`), never from the request;
 * only the `none` attestation format is supported; algorithms ES256, EdDSA,
 * RS256. The library verifies origin, RP ID hash, challenge, UP/UV, BE and
 * the signature. The counter rule (WA-1) and the user-handle check (WA-2)
 * are applied by AssertionPolicy after the signature is verified, so a
 * forged assertion can never flag somebody's credential.
 */
final readonly class RelyingParty
{
    public const int TIMEOUT_MS = 300_000;

    public const array ALGORITHMS = [Algorithms::COSE_ALGORITHM_ES256, Algorithms::COSE_ALGORITHM_EDDSA, Algorithms::COSE_ALGORITHM_RS256];

    public function __construct(
        public string $id,
        public string $name,
        public string $origin,
    ) {}

    public static function fromConfig(): self
    {
        $url = (string) config('app.url');
        $parts = parse_url($url);
        $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? 'https') : 'https';
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        $port = is_array($parts) && isset($parts['port']) ? ':'.$parts['port'] : '';
        $rpId = config('identity.webauthn.rp_id');
        $name = config('identity.webauthn.rp_name');

        return new self(
            is_string($rpId) && $rpId !== '' ? $rpId : $host,
            is_string($name) && $name !== '' ? $name : (string) config('app.name'),
            "{$scheme}://{$host}{$port}",
        );
    }

    /**
     * WebAuthn needs a secure context and a domain RP ID: plain HTTP (other
     * than localhost) or an IP address cannot use it (contracts §3.4).
     */
    public function available(): bool
    {
        $parts = parse_url($this->origin);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        if ($this->id === '' || filter_var(trim($this->id, '[]'), FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return $scheme === 'https' || ($scheme === 'http' && ($host === 'localhost' || str_ends_with($host, '.localhost')));
    }

    public function entity(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create($this->name, $this->id);
    }

    /** @return list<PublicKeyCredentialParameters> */
    public function parameters(): array
    {
        return array_map(fn (int $alg): PublicKeyCredentialParameters => PublicKeyCredentialParameters::createPk($alg), self::ALGORITHMS);
    }

    public function serializer(): SerializerInterface
    {
        return (new WebauthnSerializerFactory($this->attestationSupport()))->create();
    }

    public function toJson(object $options): string
    {
        return $this->serializer()->serialize($options, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS => JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ]);
    }

    public function attestationValidator(): AuthenticatorAttestationResponseValidator
    {
        return AuthenticatorAttestationResponseValidator::create(new CeremonyStepManager([
            new CheckClientDataCollectorType,
            new CheckChallenge,
            $this->origins(),
            new CheckTopOrigin(null),
            new CheckRelyingPartyIdIdHash,
            new CheckUserWasPresent,
            new CheckUserVerification,
            new CheckBackupBitsAreConsistent,
            new CheckAlgorithm,
            new CheckExtensions(new ExtensionOutputCheckerHandler),
            new CheckAttestationFormatIsKnownAndValid($this->attestationSupport()),
            new CheckHasAttestedCredentialData,
            new CheckCredentialId,
        ]));
    }

    /**
     * The library's request ceremony without its user-handle and counter
     * steps: AssertionPolicy applies WA-1 and WA-2 after this succeeds.
     */
    public function assertionValidator(): AuthenticatorAssertionResponseValidator
    {
        return AuthenticatorAssertionResponseValidator::create(new CeremonyStepManager([
            new CheckAllowedCredentialList,
            new CheckClientDataCollectorType,
            new CheckChallenge,
            $this->origins(),
            new CheckTopOrigin(null),
            new CheckRelyingPartyIdIdHash,
            new CheckUserWasPresent,
            new CheckUserVerification,
            new CheckBackupBitsAreConsistent,
            new CheckExtensions(new ExtensionOutputCheckerHandler),
            new CheckSignature(Manager::create()->add(ES256::create(), new EdDSA, RS256::create())),
            new CheckBackupEligibilityIsUnchanged,
        ]));
    }

    private function origins(): CheckAllowedOrigins
    {
        return new CheckAllowedOrigins([$this->origin], false);
    }

    private function attestationSupport(): AttestationStatementSupportManager
    {
        return new AttestationStatementSupportManager([new NoneAttestationStatementSupport]);
    }
}
