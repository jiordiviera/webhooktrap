<?php

declare(strict_types=1);

/*
 * Agent ↔ server protocol v1 (contracts.md §5; domain §5; threat model S-1,
 * S-3, TM-35, TM-37, TM-40, RE-1). Configuration values; the one secret is
 * the pairing HMAC key, which reaches containers as AGENT_PAIRING_HMAC_KEY_FILE.
 */
return [

    // S-1: pairing codes are stored as HMAC-SHA-256 under this key (32+
    // bytes, or base64:...). Never APP_KEY. Missing or short: pairing fails closed.
    'pairing_hmac_key' => env('AGENT_PAIRING_HMAC_KEY'),

    // The canonical origin agents pin at pairing (S-4) and sign into every
    // challenge (S-3): scheme + host + port, lowercase, default port omitted.
    'origin' => env('AGENT_SERVER_ORIGIN', env('APP_URL', 'http://localhost')),

    // R55: older agents get 426 at pair and hello.
    'min_agent_version' => '0.9.0',

    // A09 decision 3: `0.0.0-dev` builds are refused (426) unless this is on
    // AND APP_ENV is local or testing; each accepted handshake logs a warning.
    'allow_dev_builds' => (bool) env('AGENT_ALLOW_DEV_BUILDS', false),

    // PV-12 as applied on this instance (hosted: strict).
    'probe_display_policy' => env('AGENT_PROBE_DISPLAY_POLICY', 'strict'),

    // The `pair` command shown with a code (PairingCodeView.command).
    // AC-14: the CLI is `webhooktrap pair`, not `webhooktrap agent pair`.
    'pair_command' => env('AGENT_PAIR_COMMAND', 'webhooktrap pair {code} --server {origin}'),

    'pairing' => [
        'code_ttl_seconds' => 600,
        // S-1: per source address, then a 1 h block; a global breaker.
        'failures_per_address' => 10,
        'failure_window_seconds' => 600,
        'block_seconds' => 3600,
        'global_failures' => 300,
        'global_block_seconds' => 600,
        // Secondary control: a code is void after this many bad uses.
        'code_max_failures' => 5,
        // `POST /pairing-codes` per principal.
        'issues_per_window' => 10,
        'issue_window_seconds' => 600,
    ],

    // RE-1: an agent not confirmed this long after `pair` is revoked.
    'confirm_within_seconds' => 600,

    // TM-43: active agents per account workspace.
    'max_agents_per_workspace' => 20,

    'auth' => [
        'nonce_ttl_seconds' => 60,
        'token_ttl_seconds' => 900,
        'challenges_per_minute' => 60,
    ],

    // An agent whose last hello is older than this shows as disconnected.
    'connected_within_seconds' => 60,

    // TM-40 / decision 50: long-polls hold a PHP worker each, so they take a
    // cross-process slot (a PostgreSQL session advisory lock, freed when the
    // holding process dies). Agents get at most half of the HTTP workers and
    // leave at least 2 free (compose-profile §2.5). AC-5: one slot per agent
    // (a second concurrent poll answers 204 at once) and at most
    // `per_workspace` slots per workspace.
    'longpoll' => [
        // Names from .env.example (A10). Default: half of 8 HTTP workers.
        'slots' => (int) env('LONGPOLL_AGENT_SLOTS', 4),
        // FrankenPHP `num_threads` of the app container; caps `slots`.
        'http_workers' => (int) env('LONGPOLL_HTTP_WORKERS', 8),
        'per_workspace' => 2,
        'max_wait_seconds' => (int) env('LONGPOLL_MAX_WAIT_SECONDS', 25),
        'poll_interval_ms' => 1000,
        'retry_after_seconds' => (int) env('LONGPOLL_RETRY_AFTER_SECONDS', 5),
    ],

    'jobs' => [
        // A job not accepted in time leaves its run Aborted (`agent_lost`).
        'accept_within_seconds' => 600,
        // After accept, the job (and its capture fetches) stays valid this long.
        'run_within_seconds' => 1800,
        // AC-8: a job declined with `needs_confirmation` / `needs_reconfirm`
        // is not offered to `jobs/next` again before this.
        'decline_hold_seconds' => 30,
    ],

    // Request body limits per endpoint (contracts §5: every endpoint has one).
    'limits' => [
        'small' => 4096,
        'hello' => 65536,
        'observations' => 1048576,
        'final' => 2097152 + 4096,
        'capture_body' => 5242880,
    ],

    // TM-37, AC-12: observation volume per run. Batches: 4 per planned
    // delivery (50 while unplanned) plus `extra_batches`, never more than
    // `max_batches`; bytes: never more than `max_bytes_per_run` in total;
    // provisional findings: at most `max_provisional` keys.
    'observations' => [
        'extra_batches' => 20,
        'max_batches' => 220,
        'max_bytes_per_run' => 8388608,
        'max_provisional' => 50,
        'max_plan_bytes' => 524288,
    ],

    // AC-6: final document against the stored observations. Latency of a
    // completed delivery may differ by at most this much.
    'final' => [
        'latency_tolerance_ms' => 5,
    ],
];
