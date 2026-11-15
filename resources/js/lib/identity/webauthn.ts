/*
 * Native WebAuthn (navigator.credentials) with the JSON options the server
 * returns (webauthn-lib, base64url fields). No library: the two option
 * shapes are decoded here and the credential is sent back as the standard
 * JSON form (PublicKeyCredential.toJSON()).
 */

export function base64urlToBuffer(value: string): ArrayBuffer {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(
        base64.length + ((4 - (base64.length % 4)) % 4),
        '=',
    );
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes.buffer;
}

export function bufferToBase64url(
    buffer: ArrayBuffer | ArrayBufferView,
): string {
    const bytes =
        buffer instanceof ArrayBuffer
            ? new Uint8Array(buffer)
            : new Uint8Array(
                  buffer.buffer,
                  buffer.byteOffset,
                  buffer.byteLength,
              );
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return btoa(binary)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

/** True when this browser has WebAuthn at all (S-02, S-17A, S-27 states). */
export function webauthnSupported(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.PublicKeyCredential === 'function' &&
        typeof navigator.credentials?.create === 'function'
    );
}

type Descriptor = { type: 'public-key'; id: string; transports?: string[] };

type CreationJson = Omit<
    PublicKeyCredentialCreationOptions,
    'challenge' | 'user' | 'excludeCredentials'
> & {
    challenge: string;
    user: { id: string; name: string; displayName: string };
    excludeCredentials?: Descriptor[];
};

type RequestJson = Omit<
    PublicKeyCredentialRequestOptions,
    'challenge' | 'allowCredentials'
> & {
    challenge: string;
    allowCredentials?: Descriptor[];
};

function descriptors(list: Descriptor[] | undefined) {
    return list?.map((d) => ({
        type: d.type,
        id: base64urlToBuffer(d.id),
        transports: d.transports as AuthenticatorTransport[] | undefined,
    }));
}

export function creationOptions(
    json: CreationJson,
): PublicKeyCredentialCreationOptions {
    return {
        ...json,
        challenge: base64urlToBuffer(json.challenge),
        user: { ...json.user, id: base64urlToBuffer(json.user.id) },
        excludeCredentials: descriptors(json.excludeCredentials),
    };
}

export function requestOptions(
    json: RequestJson,
): PublicKeyCredentialRequestOptions {
    return {
        ...json,
        challenge: base64urlToBuffer(json.challenge),
        allowCredentials: descriptors(json.allowCredentials),
    };
}

/** The standard JSON form of a credential (create or get). */
export function credentialToJson(
    credential: PublicKeyCredential,
): Record<string, unknown> {
    const response = credential.response;
    const base = {
        id: credential.id,
        rawId: bufferToBase64url(credential.rawId),
        type: credential.type,
        authenticatorAttachment: credential.authenticatorAttachment ?? null,
        clientExtensionResults: credential.getClientExtensionResults(),
    };

    if ('attestationObject' in response) {
        const attestation = response as AuthenticatorAttestationResponse;

        return {
            ...base,
            response: {
                clientDataJSON: bufferToBase64url(attestation.clientDataJSON),
                attestationObject: bufferToBase64url(
                    attestation.attestationObject,
                ),
                transports:
                    typeof attestation.getTransports === 'function'
                        ? attestation.getTransports()
                        : [],
            },
        };
    }

    const assertion = response as AuthenticatorAssertionResponse;

    return {
        ...base,
        response: {
            clientDataJSON: bufferToBase64url(assertion.clientDataJSON),
            authenticatorData: bufferToBase64url(assertion.authenticatorData),
            signature: bufferToBase64url(assertion.signature),
            userHandle: assertion.userHandle
                ? bufferToBase64url(assertion.userHandle)
                : null,
        },
    };
}

/**
 * Outcome of a browser ceremony. `cancelled` covers the user closing the
 * sheet and the 5-minute timeout (NotAllowedError); `exists` is an
 * excludeCredentials match (InvalidStateError); `refused` is anything the
 * browser would not do here (wrong origin, no authenticator, security).
 */
export type CeremonyResult =
    | { status: 'ok'; credential: Record<string, unknown> }
    | { status: 'cancelled' | 'exists' | 'refused' };

function classify(error: unknown): CeremonyResult {
    const name = error instanceof DOMException ? error.name : '';

    if (name === 'NotAllowedError' || name === 'AbortError') {
        return { status: 'cancelled' };
    }
    if (name === 'InvalidStateError') {
        return { status: 'exists' };
    }

    return { status: 'refused' };
}

export async function createCredential(json: unknown): Promise<CeremonyResult> {
    try {
        const credential = (await navigator.credentials.create({
            publicKey: creationOptions(json as CreationJson),
        })) as PublicKeyCredential | null;

        return credential
            ? { status: 'ok', credential: credentialToJson(credential) }
            : { status: 'cancelled' };
    } catch (error) {
        return classify(error);
    }
}

export async function getCredential(
    json: unknown,
    signal?: AbortSignal,
    mediation?: CredentialMediationRequirement,
): Promise<CeremonyResult> {
    try {
        const credential = (await navigator.credentials.get({
            publicKey: requestOptions(json as RequestJson),
            signal,
            mediation,
        })) as PublicKeyCredential | null;

        return credential
            ? { status: 'ok', credential: credentialToJson(credential) }
            : { status: 'cancelled' };
    } catch (error) {
        return classify(error);
    }
}

/** Conditional UI (passkeys in the email autofill list, S-02 §6). */
export async function conditionalMediationAvailable(): Promise<boolean> {
    if (!webauthnSupported()) {
        return false;
    }
    const pkc = window.PublicKeyCredential as unknown as {
        isConditionalMediationAvailable?: () => Promise<boolean>;
    };

    try {
        return (await pkc.isConditionalMediationAvailable?.()) ?? false;
    } catch {
        return false;
    }
}
