import type { ReactElement } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';

/*
 * Options shared by the browser entry (app.tsx) and the SSR entry (ssr.tsx),
 * so a server-rendered page and its hydration build the same tree.
 */

const appName = import.meta.env.VITE_APP_NAME || 'Webhook Trap';

export const appTitle = (title: string): string =>
    title ? `${title} - ${appName}` : appName;

export function withAppProviders(app: ReactElement): ReactElement {
    return (
        <TooltipProvider delayDuration={0}>
            {app}
            <Toaster />
        </TooltipProvider>
    );
}
