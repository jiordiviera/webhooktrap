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
        <link rel="manifest" href="/manifest.webmanifest">

        {{-- Link previews (Open Graph, X). SSR covers the public pages' body only (App\Http\Ssr\PublicPagesSsrGateway); these tags stay in Blade.
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
        {{-- Allow-list (App\Http\PublicPages): every page not named there is noindex. --}}
        <meta name="robots" content="{{ \App\Http\PublicPages::indexable($page) ? 'index,follow' : 'noindex,nofollow' }}">
        <link rel="canonical" href="{{ \App\Http\PublicPages::canonical($page, request()->path()) }}">
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

        @if ($page['component'] === 'welcome')
            {{-- Fonts of the landing's first paint (H1, terminal), latin only; font-display is swap. --}}
            @foreach (\App\Http\PublicPages::landingFontUrls() as $fontUrl)
                <link rel="preload" href="{{ $fontUrl }}" as="font" type="font/woff2" crossorigin>
            @endforeach
            @php
                $ldUrl = $ogBase.'/';
                $structuredData = [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'SoftwareApplication',
                            'name' => 'Webhook Trap',
                            'applicationCategory' => 'DeveloperApplication',
                            'operatingSystem' => 'Linux, macOS, Windows',
                            'url' => $ldUrl,
                            'description' => $ogDescription,
                            'license' => 'https://www.gnu.org/licenses/agpl-3.0.html',
                            'codeRepository' => 'https://github.com/jiordiviera/webhooktrap',
                            'image' => $ogBase.'/og/landing.png',
                            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                            'publisher' => ['@id' => $ldUrl.'#organization'],
                        ],
                        [
                            '@type' => 'Organization',
                            '@id' => $ldUrl.'#organization',
                            'name' => 'Webhook Trap',
                            'url' => $ldUrl,
                            'logo' => $ogBase.'/brand/png/webhooktrap-mark-light-512.png',
                            'sameAs' => ['https://github.com/jiordiviera/webhooktrap'],
                        ],
                        [
                            '@type' => 'WebSite',
                            'name' => 'Webhook Trap',
                            'url' => $ldUrl,
                            'publisher' => ['@id' => $ldUrl.'#organization'],
                        ],
                    ],
                ];
            @endphp
            <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) !!}</script>
        @endif

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
