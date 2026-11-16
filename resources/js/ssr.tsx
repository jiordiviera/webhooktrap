import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { appTitle, withAppProviders } from '@/lib/app-options';

/*
 * SSR entry (PO decision 2026-11-16). The server only sends the public pages
 * of App\Http\PublicPages here, for anonymous visitors
 * (App\Http\Ssr\PublicPagesSsrGateway); every other page renders in the
 * browser. @inertiajs/vite wraps this call with the render server
 * (bootstrap/ssr/ssr.js, port 13714). No theme script, no realtime: both are
 * browser-only and live in app.tsx.
 *
 * Second fence: the bundle only contains the public page components, so a
 * private page can never render here, whatever the gateway sends.
 */
const pages = import.meta.glob<{ default: ComponentType }>([
    './pages/welcome.tsx',
    './pages/Legal/Privacy.tsx',
    './pages/Legal/Terms.tsx',
    './pages/Run/Show.tsx',
]);

void createInertiaApp({
    resolve: (name: string) => {
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`SSR is off for page ${name}`);
        }

        return page().then((module) => module.default);
    },
    title: appTitle,
    layout: () => null,
    strictMode: true,
    withApp: withAppProviders,
});
