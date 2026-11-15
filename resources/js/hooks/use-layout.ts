import { usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import type { ShellLayout, SidebarState } from '@/types/workspace';

/*
 * Decision 59: the app layout is a per-device preference stored in plain
 * cookies, like `appearance`. The server reads them and shares the `layout`
 * prop, so the first paint is right; a change here writes the cookie and
 * updates every mounted shell at once, without a reload.
 */

type Override = { shell?: ShellLayout; sidebar?: SidebarState };

const listeners = new Set<() => void>();
let override: Override = {};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const snapshot = () => override;

function setCookie(name: string, value: string): void {
    const maxAge = 365 * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

function set(next: Override): void {
    override = { ...override, ...next };
    listeners.forEach((listener) => listener());
}

export function useLayout() {
    const { layout } = usePage().props;
    const local = useSyncExternalStore(subscribe, snapshot, snapshot);
    const shell = local.shell ?? layout?.shell ?? 'topbar';
    const sidebar = local.sidebar ?? layout?.sidebar ?? 'expanded';

    return {
        shell,
        sidebar,
        setShell: (value: ShellLayout) => {
            setCookie('layout', value);
            set({ shell: value });
        },
        setSidebar: (value: SidebarState) => {
            setCookie('layout_sidebar', value);
            set({ sidebar: value });
        },
    };
}
