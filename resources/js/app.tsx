import { createInertiaApp, http } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import { setupLive } from '@/lib/realtime/echo';
import SettingsLayout from '@/layouts/settings/layout';

// The XSRF cookie carries the __Host- prefix (TM-T4).
http.setClient({ xsrfCookieName: '__Host-XSRF-TOKEN' });

const appName = import.meta.env.VITE_APP_NAME || 'Webhook Trap';

// Live props over Echo + Reverb, when the server shares a Reverb key.
const live = setupLive();

void createInertiaApp({
    ...(live ? { live } : {}),
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'Expired':
            case name === 'Error':
            case name.startsWith('Legal/'):
            case name.startsWith('Auth/'):
            case name.startsWith('Account/'):
            case name.startsWith('TwoFactor/'):
            case name.startsWith('Capture/'):
            case name.startsWith('Projects/'):
            case name.startsWith('Settings/'):
            case name.startsWith('Run/'):
            case name.startsWith('Runs/'):
            case name.startsWith('Agents/'):
            case name.startsWith('Agent/'):
                return null;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
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
