<?php

declare(strict_types=1);

/*
 * Workspace configuration (domain-model §3, §12; schema.md §3). Configuration
 * values, not secrets. Hosted: compiled here; self-host: instance settings
 * will override the quota and retention ceilings (Instance slice).
 */
return [

    // Retention choices offered in S-16 (PO-4 defaults: captures 30 days,
    // runs 90 days). The database accepts 1..365; these lists are the UI set.
    'retention' => [
        'capture_days' => [1, 7, 14, 30],
        'run_days' => [7, 30, 90],
        'default_capture_days' => 30,
        'default_run_days' => 90,
    ],

    // Account capture quota per project (TM-02, ADR-005 amendment 1 §5);
    // over quota, ingest answers identically and stores nothing.
    'quota' => [
        'captures_per_day' => 5000,
        'stored_body_bytes' => 512 * 1024 * 1024,
    ],

    // Soft-deleted projects are hard-deleted after this (schema.md §8).
    'deleted_project_grace_hours' => 24,

    'guest_project_name' => 'Guest session',
    'default_project_name' => 'My project',
];
