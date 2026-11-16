import { useEffect, useSyncExternalStore } from 'react';

/*
 * The live-updates status store, kept apart from Echo and pusher-js so the
 * pages that only show it (and every page that shows nothing live) don't pull
 * the socket client into their chunks. `live.ts` loads Echo on demand.
 */

export type LiveStatus = 'off' | 'connecting' | 'live' | 'paused';

const listeners = new Set<() => void>();
let status: LiveStatus = 'off';
let wake: (() => void) | null = null;

export function setLiveStatus(next: LiveStatus): void {
    if (status !== next) {
        status = next;
        listeners.forEach((listener) => listener());
    }
}

/** Set by `lazyLive()`: starts the socket when a page shows the status first. */
export function onLiveWake(callback: () => void): void {
    wake = callback;
}

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

/** "live", "connecting", "paused" (lost) or "off" (not configured). */
export function useLiveStatus(): LiveStatus {
    useEffect(() => {
        wake?.();
    }, []);

    return useSyncExternalStore(
        subscribe,
        () => status,
        () => 'off' as LiveStatus,
    );
}
