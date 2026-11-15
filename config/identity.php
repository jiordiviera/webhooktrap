<?php

declare(strict_types=1);

/*
 * Identity configuration (domain-model §2). Throttle values follow the
 * "Throttle values" table of domain §2.1 (revision 6); lifetimes follow §2.9.
 * These are configuration values, A09 to confirm; not secrets.
 */
return [

    // hosted | selfhost (domain §2.3: delayed recovery is hosted only).
    'instance' => env('WEBHOOKTRAP_INSTANCE', 'hosted'),

    // The image process (docker/entrypoint.sh, set by Compose): app | ingest |
    // worker. `ingest` only stores captures and never reads the Identity keys
    // or the password list, so the serve-time check skips it.
    'runtime_role' => env('WT_ROLE', 'app'),

    // Self-host may switch guest mode off (contracts §3.2 POST /new).
    'guest_mode' => (bool) env('GUEST_MODE', true),

    'guest' => [
        'lifetime_hours' => 24,
        'cookie' => '__Host-wt_guest',
        'claim_nonce_minutes' => 30,
        // A-13 quotas (ADR-003 amendment 1 §3).
        'quotas' => ['captures' => 100, 'runs' => 10, 'accepted_runs' => 20,
            // A09 S3-8: counted capture bytes per guest session. 50 MiB is a
            // proposal awaiting the PO's approval.
            'stored_bytes' => 50 * 1024 * 1024],
        'create_per_address_per_hour' => 10,
        'create_global_per_hour' => 1000,
    ],

    'password' => [
        'min' => 12,
        'max' => 128,
        'common_list' => resource_path('security/common-passwords.txt'),
    ],

    'sessions' => [
        'idle_days' => 14,
        'absolute_days' => 30,
        'last_seen_every_seconds' => 300,
        'liveness_cache_seconds' => 30,
    ],

    'recent_auth_seconds' => 600,
    'delete_confirmation_seconds' => 300,

    'links' => [
        'verify_minutes' => 60,
        'reset_minutes' => 60,
        'email_change_minutes' => 60,
        'deletion_minutes' => 60,
        'recovery_request_minutes' => 60,
        'recovery_finish_hours' => 24,
    ],

    'recovery' => [
        'wait_hours' => 72,
        'reset_extension_days' => 7,
        'lock_days' => 7,
        'cooldown_days' => 30,
    ],

    'throttle' => [
        'pair' => ['max' => 5, 'decay' => 900],
        'account' => ['max' => 20, 'decay' => 3600, 'hold' => 900],
        'address_failures' => ['max' => 30, 'decay' => 900],
        'address_requests' => ['max' => 100, 'decay' => 3600],
        'passkey_options' => ['max' => 30, 'decay' => 60],
        'challenge_attempts' => 5,
        // Per address across `/two-factor/*` and second-factor assertions (A09 F2).
        'two_factor_address' => ['max' => 30, 'decay' => 900],
        'second_factor' => ['max' => 20, 'window_hours' => 24, 'hold_minutes' => 60],
        'confirm_auth' => ['max' => 10, 'decay' => 900],
        'forgot_email' => ['max' => 3, 'decay' => 3600],
        'forgot_address' => ['max' => 10, 'decay' => 3600],
        'register_address' => ['max' => 10, 'decay' => 3600],
        'verify_resend_gap' => 60,
        'verify_resend_daily' => 10,
        'email_change' => ['max' => 5, 'decay' => 86400],
        'recovery_email' => ['max' => 3, 'decay' => 86400],
        'recovery_address' => ['max' => 10, 'decay' => 86400],
    ],

    // Dedicated keys, never APP_KEY, kept out of the database and backups.
    // Secrets: set as <NAME>_FILE in containers. The app refuses to serve
    // without them (A09 F8, F9; threat model §12.11 R-Y).
    'keys' => [
        // `email_hmac` of the audit log and deletion ledger. Must never change.
        'email_hmac' => env('IDENTITY_EMAIL_HMAC_KEY'),
        // Keyed hash of recovery codes. Rotating it invalidates every code.
        'recovery_codes' => env('IDENTITY_RECOVERY_CODE_KEY'),
    ],

    'webauthn' => [
        'rp_id' => env('WEBAUTHN_RP_ID'),
        'rp_name' => env('WEBAUTHN_RP_NAME'),
        'max_credentials' => 10,
    ],

];
