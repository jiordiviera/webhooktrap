<?php

declare(strict_types=1);

/*
 * The engine sandbox (ADR-010 §1): `webhooktrap serve-sandbox` on a Unix
 * socket shared with the app container. The app calls it for `plan` only
 * (EngineProcess adapter); its output is untrusted (S-11, TM-18, TM-74).
 */
return [

    // Path of the sandbox socket (both Compose files mount it here).
    'socket' => env('ENGINE_SOCKET') ?: '/run/engine/engine.sock',

    // Whole call (connect, write, read). The engine's own wall clock is 5 s.
    'timeout_ms' => 8000,

    // Connecting to a local socket is immediate or it is down.
    'connect_timeout_ms' => 1000,

    // TM-18: engine calls per principal per minute (cache hits do not count).
    'plan_per_minute' => 20,

    // Plan previews are cached by the content they were planned from.
    'plan_cache_seconds' => 600,
];
