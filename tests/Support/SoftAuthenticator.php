<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * A software WebAuthn authenticator for tests: generates a P-256 (ES256)
 * credential and produces `none` attestations and signed assertions exactly
 * as a browser would serialise them (RegistrationResponseJSON,
 * AuthenticationResponseJSON). No real authenticator is needed, and every
 * field can be bent to exercise one server rule at a time.
 */
final class SoftAuthenticator
{
    public const int FLAG_UP = 0x01;

    public const int FLAG_UV = 0x04;

    public const int FLAG_BE = 0x08;

    public const int FLAG_BS = 0x10;

    public const int FLAG_AT = 0x40;

    public string $credentialId;

    public int $signCount = 0;

    private \OpenSSLAsymmetricKey $key;

    public function __construct(
        public string $rpId = 'webhooktrap.test',
        public string $origin = 'https://webhooktrap.test',
        public bool $userVerifying = true,
        public bool $backupEligible = false,
    ) {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($key === false) {
            throw new RuntimeException('openssl could not create a P-256 key');
        }
        $this->key = $key;
        $this->credentialId = random_bytes(32);
    }

    /**
     * Answers creation options (as returned by /webauthn/register/options).
     *
     * @param  array<string, mixed>  $options
     * @param  array{origin?: string, rpId?: string, challenge?: string, type?: string, flags?: int, fmt?: string}  $bend
     * @return array<string, mixed>
     */
    public function register(array $options, array $bend = []): array
    {
        $clientData = $this->clientData($bend['type'] ?? 'webauthn.create', $bend['challenge'] ?? (string) $options['challenge'], $bend['origin'] ?? $this->origin);
        $attested = str_repeat("\0", 16).pack('n', strlen($this->credentialId)).$this->credentialId.$this->coseKey();
        $authData = $this->authData($bend['rpId'] ?? $this->rpId, ($bend['flags'] ?? $this->flags()) | self::FLAG_AT, $this->signCount).$attested;
        $attestation = Cbor::map([
            [Cbor::text('fmt'), Cbor::text($bend['fmt'] ?? 'none')],
            [Cbor::text('attStmt'), Cbor::map([])],
            [Cbor::text('authData'), Cbor::bytes($authData)],
        ]);

        return [
            'id' => self::b64($this->credentialId),
            'rawId' => self::b64($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::b64($clientData),
                'attestationObject' => self::b64($attestation),
                'transports' => ['internal'],
            ],
            'clientExtensionResults' => new \stdClass,
        ];
    }

    /**
     * Answers request options (as returned by /webauthn/assert/options).
     *
     * @param  array<string, mixed>  $options
     * @param  array{origin?: string, rpId?: string, challenge?: string, type?: string, flags?: int, counter?: int, userHandle?: string|null, signature?: string}  $bend
     * @return array<string, mixed>
     */
    public function assert(array $options, ?string $userHandle, array $bend = []): array
    {
        $counter = $bend['counter'] ?? ++$this->signCount;
        $clientData = $this->clientData($bend['type'] ?? 'webauthn.get', $bend['challenge'] ?? (string) $options['challenge'], $bend['origin'] ?? $this->origin);
        $authData = $this->authData($bend['rpId'] ?? $this->rpId, $bend['flags'] ?? $this->flags(), $counter);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);
        $handle = array_key_exists('userHandle', $bend) ? $bend['userHandle'] : $userHandle;

        return [
            'id' => self::b64($this->credentialId),
            'rawId' => self::b64($this->credentialId),
            'type' => 'public-key',
            'response' => array_filter([
                'clientDataJSON' => self::b64($clientData),
                'authenticatorData' => self::b64($authData),
                'signature' => self::b64($bend['signature'] ?? $signature),
                'userHandle' => $handle === null ? null : self::b64($handle),
            ], fn ($v): bool => $v !== null),
            'clientExtensionResults' => new \stdClass,
        ];
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function unb64(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/'), true);
    }

    private function flags(): int
    {
        return self::FLAG_UP | ($this->userVerifying ? self::FLAG_UV : 0) | ($this->backupEligible ? self::FLAG_BE | self::FLAG_BS : 0);
    }

    private function authData(string $rpId, int $flags, int $counter): string
    {
        return hash('sha256', $rpId, true).chr($flags).pack('N', $counter);
    }

    private function clientData(string $type, string $challenge, string $origin): string
    {
        return (string) json_encode(['type' => $type, 'challenge' => $challenge, 'origin' => $origin, 'crossOrigin' => false], JSON_UNESCAPED_SLASHES);
    }

    private function coseKey(): string
    {
        $details = openssl_pkey_get_details($this->key);
        if ($details === false) {
            throw new RuntimeException('openssl key details');
        }
        $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        return Cbor::map([
            [Cbor::int(1), Cbor::int(2)],     // kty: EC2
            [Cbor::int(3), Cbor::int(-7)],    // alg: ES256
            [Cbor::int(-1), Cbor::int(1)],    // crv: P-256
            [Cbor::int(-2), Cbor::bytes($x)],
            [Cbor::int(-3), Cbor::bytes($y)],
        ]);
    }
}
