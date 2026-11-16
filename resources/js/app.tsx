import { createInertiaApp, http } from '@inertiajs/react';
import { initializeTheme } from '@/hooks/use-appearance';
import { appTitle, withAppProviders } from '@/lib/app-options';
import { lazyLive } from '@/lib/realtime/live';

// The XSRF cookie carries the __Host- prefix (TM-T4).
http.setClient({ xsrfCookieName: '__Host-XSRF-TOKEN' });

// Live props over Echo + Reverb, when the server shares a Reverb key. Echo and
// pusher-js load only when a page subscribes to a live prop (lib/realtime/live.ts).
const live = lazyLive();

// Public pages (App\Http\PublicPages) arrive server-rendered and hydrate;
// every other page renders here (resources/js/ssr.tsx).
void createInertiaApp({
    ...(live ? { live } : {}),
    title: appTitle,
    // Every page renders its own shell (components/shell, identity, landing):
    // no persistent Inertia layout.
    layout: () => null,
    strictMode: true,
    withApp: withAppProviders,
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
