<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "dark" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f7f7f4;
            }

            html.dark {
                background-color: #141411;
            }
        </style>

        <meta name="application-name" content="{{ config('app.name', 'Webhook Trap') }}">
        <meta name="theme-color" content="#f7f7f4" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#141411" media="(prefers-color-scheme: dark)">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- Link previews (Open Graph, X). No SSR, so crawlers only see these server-rendered tags.
             Images are exports of the "Webhook Trap — OG images" Claude Design canvas. --}}
        @php
            $ogPages = [
                'welcome' => ['Your webhook handler works. Once.', 'Webhook Trap replays your real webhook events twice, out of order and late against your handler, and fails the build when your database ends up wrong.', 'og/landing.png'],
                'Legal/Privacy' => ['Privacy policy · Webhook Trap', 'What Webhook Trap collects, how long it keeps it, and who processes it.', 'og/privacy.png'],
                'Legal/Terms' => ['Terms of service · Webhook Trap', 'The terms for using Webhook Trap.', 'og/terms.png'],
            ];
            [$ogTitle, $ogDescription, $ogImage] = $ogPages[$page['component']] ?? ['Webhook Trap', 'Capture webhooks, prove your handler against real provider scenarios, and replay them.', 'og/default.png'];
            $ogBase = rtrim((string) config('app.url'), '/');
        @endphp
        <meta name="description" content="{{ $ogDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Webhook Trap">
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDescription }}">
        <meta property="og:url" content="{{ $ogBase.'/'.ltrim(request()->path(), '/') }}">
        <meta property="og:image" content="{{ $ogBase.'/'.$ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $ogTitle }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $ogTitle }}">
        <meta name="twitter:description" content="{{ $ogDescription }}">
        <meta name="twitter:image" content="{{ $ogBase.'/'.$ogImage }}">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Webhook Trap') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
