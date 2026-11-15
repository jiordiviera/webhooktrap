<?php

declare(strict_types=1);

/*
 * Packs (domain-model §8): versioned provider data in `packs/`, the same
 * files the engine embeds. Derivation limits keep a hostile body from
 * costing more than a bounded amount of time and memory in a queue worker.
 */
return [
    'path' => base_path('packs'),

    'derive' => [
        // JSON bodies above this size are not parsed: header-based facts
        // (provider, Shopify and GitHub event types and ids) still derive.
        'max_json_bytes' => 1024 * 1024,
        // json_decode nesting limit; deeper documents are treated as non-JSON.
        'max_depth' => 64,
        // SHP-1: a shape with more (path, type) pairs, or a body with more
        // values than `max_nodes`, is not recorded (shape null).
        'max_shape_paths' => 2000,
        'max_nodes' => 100000,
        // Derived strings (event type, ids, labels) longer than this are dropped.
        'max_value_chars' => 200,
        // Object ids kept per capture (GRP-1).
        'max_object_ids' => 32,
    ],
];
