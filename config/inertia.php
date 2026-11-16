<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    /*
     * PO decision 2026-11-16: SSR for the public pages only, for anonymous
     * visitors (App\Http\Ssr\PublicPagesSsrGateway decides per page). The
     * render server is the `ssr` Compose service (node bootstrap/ssr/ssr.js).
     * A dead or slow server never fails a page: the request gives up after
     * `connect_timeout` / `timeout` seconds and the page renders in the
     * browser, as every private page always does. `throw_on_error` stays
     * false outside tests (fixed here, not read from the environment).
     */
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'timeout' => (float) env('INERTIA_SSR_TIMEOUT', 1.0),
        'connect_timeout' => (float) env('INERTIA_SSR_CONNECT_TIMEOUT', 0.25),
        'throw_on_error' => false,
        'ensure_bundle_exists' => true,
        'bundle' => base_path('bootstrap/ssr/ssr.js'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

    /*
     * History encryption (A09 delta review D1): page props in the tab's
     * history state are encrypted with a per-session key, so one-time secrets
     * and account details are unreadable after sign-out. Fixed here, not
     * overridable by the environment.
     */
    'history' => [
        'encrypt' => true,
    ],

];
