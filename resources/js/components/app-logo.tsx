import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';
import AppLogoWordmark from '@/components/app-logo-wordmark';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-8 shrink-0 text-sidebar-foreground" />
            <div className="ml-2 grid flex-1 text-left">
                <span className="sr-only">{name}</span>
                <AppLogoWordmark className="h-4 w-auto text-sidebar-foreground" />
            </div>
        </>
    );
}
