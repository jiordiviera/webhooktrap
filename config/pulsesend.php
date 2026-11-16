<?php

declare(strict_types=1);

/*
 * PulseSend (decision 72): the mail transport only. The package's webhook route stays off; it is
 * not used here and must not be reachable, so `routes` is false unless set otherwise.
 */
return [

    // The key used by the mail transport. A file secret in containers (pulsesend_api_key,
    // read by the entrypoint as PULSESEND_API_KEY); empty means mail is not sent through PulseSend.
    'api_key' => env('PULSESEND_API_KEY'),

    // Empty: the public PulseSend API.
    'base_url' => env('PULSESEND_BASE_URL'),

    // The package's webhook handler (POST /pulsesend/webhook). Off.
    'routes' => (bool) env('PULSESEND_ROUTES', false),

    'domain' => env('PULSESEND_DOMAIN'),

    'path' => env('PULSESEND_PATH', 'pulsesend'),

    'webhook' => [
        'secret' => env('PULSESEND_WEBHOOK_SECRET'),
        'tolerance' => env('PULSESEND_WEBHOOK_TOLERANCE', 300),
    ],

    'middleware' => [],

];
