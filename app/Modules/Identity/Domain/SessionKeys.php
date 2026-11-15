<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Keys Identity keeps in the server-side browser session. WebAuthn
 * challenges and the recent-authentication time live here, never in a
 * shared cache or process memory (domain §2.2, schema §2).
 */
final class SessionKeys
{
    public const string USER_SESSION = 'identity.user_session_id';

    /** ['at' => int, 'method' => string, 'second_factor' => bool] */
    public const string RECENT_AUTH = 'identity.recent_auth';

    /** ['purpose' => string, 'method' => string, 'second_factor' => bool, 'at' => int, 'consumed' => bool] */
    public const string CONFIRMATION = 'identity.confirmation';

    /** Half-signed-in state: ['id', 'user_id', 'first_factor', 'started_at', 'pending_identity']; attempts are counted in the cache */
    public const string CHALLENGE = 'identity.challenge';

    /** ['guest_session_id' => string, 'nonce' => string] (TM-12) */
    public const string CLAIM = 'identity.claim';

    /** User id registered from this browser, until its email is verified. */
    public const string PENDING_REGISTRATION = 'identity.pending_registration';

    /** ['purpose' => 'sign_in'|'link'|'confirm', 'provider' => string, 'user_id' => ?string, 'next' => ?string, 'action' => ?string, 'reason' => ?string] */
    public const string OAUTH = 'identity.oauth';

    /** Flash: the provider of a cancelled or failed OAuth sign-in, for S-02's wording. */
    public const string OAUTH_PROVIDER = 'identity.oauth_provider';

    /** One WebAuthn ceremony: ['type' => register|assert, 'mode', 'user_id', 'kind', 'options' (JSON), 'at'] (domain §2.2) */
    public const string WEBAUTHN = 'identity.webauthn';

    public const string INTENDED = 'url.intended';

    public const string RETRY_AT = 'identity.retry_at';
}
