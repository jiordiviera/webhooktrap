<?php

declare(strict_types=1);

/*
 * Operator admin panel (decision 74): Filament v5, read-only, served on its
 * own host only. Without ADMIN_HOST the panel is not registered at all, so
 * self-host and local setups have no admin routes unless they opt in.
 */
return [

    // e.g. admin.webhooktrap.dev; no scheme, no port. Empty: no panel.
    'host' => env('ADMIN_HOST') ?: null,

    // Idle limit of an admin-host session: past it, the next request signs
    // the admin session out and goes back through the app's sign-in.
    'idle_seconds' => (int) env('ADMIN_IDLE_SECONDS', 900),

    // The app → admin handoff token (single use, bound to the user and to
    // the app session that asked for it).
    'handoff_seconds' => 60,

];
