<?php

declare(strict_types=1);

/*
 * Capture configuration (domain-model §4, ADR-005 amendment 1, contracts §2).
 * Configuration values, not secrets. Hosted: compiled here and set through
 * the Compose environment; self-host: instance settings will override the
 * kept-body limit and the guest budget (Instance slice).
 */
return [

    // HD-7: the capture host serves only `/c/…`; app routes are not served
    // there. Null (local development, tests): `/c/…` is served on any host.
    'host' => env('CAPTURE_HOST') ?: null,

    // `public_origin` comes from configuration, never from request headers
    // (ADR-005 am. §1). Null: the request's own origin, source `direct`.
    // Capture URLs shown to the owner use this origin, else APP_URL.
    'public_origin' => env('CAPTURE_PUBLIC_ORIGIN') ?: null,

    // With a trusted proxy, take the scheme from `X-Forwarded-Proto`
    // (source `forwarded_trusted`); host and port stay configured (§9.5).
    'trust_forwarded_origin' => (bool) env('CAPTURE_TRUST_FORWARDED_ORIGIN', false),

    // Who may set forwarded headers (SV-2, compose-profile §2.1): `secret`
    // (the image Caddyfile sets `X-WT-Verified: 1` only for the host proxy),
    // `ip` (peer address in TRUSTED_PROXIES; weaker), `none` (never).
    // Default `none` (A09 S3-5): only the image Caddyfile strips a
    // client-sent `X-WT-Verified`, so `secret` is set explicitly where that
    // Caddyfile runs (the image ENV, compose.production.yaml).
    'proxy_trust_mode' => env('PROXY_TRUST_MODE', 'none'),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

    // Headers our own proxies add; removed from the stored list when the
    // request comes through a trusted proxy (§9.4). X-WT-* are never stored.
    'proxy_headers' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CAPTURE_PROXY_HEADERS', 'X-Forwarded-For,X-Forwarded-Proto,X-Real-IP,X-WT-Edge,X-WT-Verified',
    ))))),

    // Bodies up to and including this size are kept (§5, 5 MiB). The
    // database CHECK on capture_bodies holds the same limit.
    'body_keep_max_bytes' => (int) env('CAPTURE_BODY_KEEP_MAX_BYTES', 5 * 1024 * 1024),

    // Hard request limit; the proxy answers 413 first, this is the backstop.
    'request_max_bytes' => (int) env('CAPTURE_REQUEST_MAX_BYTES', 25 * 1024 * 1024),

    // Edge rate limit per client address (IPv4 /32, IPv6 /64), 600 / min
    // (A09 decision 1). It never depends on whether a capture key exists or
    // on its state (ADR-005 am. §4).
    'edge_rate' => ['max' => 600, 'decay' => 60],

    // A09 S3-1: providers deliver from small shared address pools, so one
    // per-address bucket would let one tenant get every other tenant's
    // deliveries from that provider answered 429. From these ranges the
    // bucket is (address, SHA-256 of the key segment as sent), counted the
    // same way for unknown and malformed keys, still 600 / min.
    //
    // A small documented list, not fetched at runtime.
    // TODO(A10): refresh from the published lists before hosted go-live and
    // on a schedule: Stripe https://stripe.com/files/ips/ips_webhooks.json,
    // GitHub https://api.github.com/meta (`hooks`). Shopify publishes no
    // webhook egress list (deliveries come from shared cloud ranges), so it
    // has no entry until it does; its tenants share per-address buckets.
    'edge_provider_ranges' => [
        'stripe' => [
            '3.18.12.63/32', '3.130.192.231/32', '13.235.14.237/32', '13.235.122.149/32',
            '18.211.135.69/32', '35.154.171.200/32', '52.15.183.38/32', '54.88.130.119/32',
            '54.88.130.237/32', '54.187.174.169/32', '54.187.205.235/32', '54.187.216.72/32',
        ],
        'github' => [
            '192.30.252.0/22', '185.199.108.0/22', '140.82.112.0/20', '143.55.64.0/20',
            '2a0a:a440::/29', '2606:50c0::/32',
        ],
        'shopify' => [],
    ],

    // A09 S3-10: lock waits in the ingest transaction (project row, guest
    // session row, the global guest budget row, capture URL row) give up
    // after this long; the request is dropped as `store_error`, same answer.
    'store_lock_timeout_ms' => 2000,

    // A09 S3-12: rotations per user.
    'rotate_rate' => ['max' => 10, 'decay' => 3600],

    // Key resolution cache: owner liveness is held at most this long and
    // evicted on account deletion, project deletion and claim (DL-2).
    'resolve_cache_seconds' => 3,

    // Global guest storage budget (TM-02; open-questions §4 proposal, 5 GiB).
    'guest_storage_budget_bytes' => 5 * 1024 * 1024 * 1024,

    // Retention purge: rows deleted per statement.
    'retention_batch' => 1000,

    // S-03 stream (slice 4): rows per page (`captures.latest`, `captures.older`,
    // contracts §3.2 "latest 50") and the most groups summarised.
    'stream' => [
        'page_size' => 50,
        'groups_max' => 200,
    ],

    // S-04 inspector (slice 4): body bytes sent inline. Larger bodies are
    // truncated (`body.truncated`); the raw download always has every byte.
    'inspector' => [
        'text_max_bytes' => 256 * 1024,
        'binary_max_bytes' => 64 * 1024,
    ],

    // Live Prop trigger throttle on the client, in ms (event-catalogue §3.1:
    // `project.captures` coalesced 250 ms per project).
    'live_throttle_ms' => 250,
];
