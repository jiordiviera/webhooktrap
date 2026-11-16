import { createInertiaApp, http } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { lazyLive } from '@/lib/realtime/live';

// The XSRF cookie carries the __Host- prefix (TM-T4).
http.setClient({ xsrfCookieName: '__Host-XSRF-TOKEN' });

const appName = import.meta.env.VITE_APP_NAME || 'Webhook Trap';

// Live props over Echo + Reverb, when the server shares a Reverb key. Echo and
// pusher-js load only when a page subscribes to a live prop (lib/realtime/live.ts).
const live = lazyLive();

void createInertiaApp({
    ...(live ? { live } : {}),
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Every page renders its own shell (components/shell, identity, landing):
    // no persistent Inertia layout.
    layout: () => null,
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
