import type { LiveOptions, LiveTransport } from '@inertiajs/core';
import { onLiveWake, setLiveStatus } from '@/lib/realtime/status';

/*
 * Inertia's live option with Echo loaded on demand. The transport is handed
 * to `createInertiaApp` up front (a later visit may land on a page with live
 * props), but `echo.ts`, laravel-echo and pusher-js are only imported when a
 * page subscribes to a live prop or shows the live status. The landing, the
 * legal pages and the identity pages never load them.
 */

/** The Reverb app key, from the first page's shared `realtime` prop. */
function initialKey(): string | null {
    try {
        const script = document.querySelector('script[data-page="app"]');
        const page = JSON.parse(script?.textContent ?? '{}') as {
            props?: { realtime?: { key?: string | null } };
        };

        return page.props?.realtime?.key ?? null;
    } catch {
        return null;
    }
}

/** Inertia's live option, or undefined when the server shares no Reverb key. */
export function lazyLive(): LiveOptions | undefined {
    const key = typeof window === 'undefined' ? null : initialKey();

    if (!key) {
        return undefined;
    }

    let loading: Promise<LiveTransport> | null = null;
    let inner: LiveTransport | null = null;
    let statusCallback: ((connected: boolean) => void) | null = null;
    let stopStatus: VoidFunction | null = null;

    const load = (): Promise<LiveTransport> => {
        if (!loading) {
            setLiveStatus('connecting');
            loading = import('@/lib/realtime/echo').then(
                ({ createLiveTransport }) => {
                    inner = createLiveTransport(key);
                    if (statusCallback) {
                        stopStatus =
                            inner.onStatusChange?.(statusCallback) ?? null;
                    }

                    return inner;
                },
            );
            loading.catch(() => setLiveStatus('paused'));
        }

        return loading;
    };

    onLiveWake(() => void load().catch(() => undefined));

    const transport: LiveTransport = {
        subscribe(channel, event, handler) {
            if (inner) {
                return inner.subscribe(channel, event, handler);
            }
            let stop: VoidFunction | null = null;
            let cancelled = false;
            load()
                .then((loaded) => {
                    if (!cancelled) {
                        stop = loaded.subscribe(channel, event, handler);
                    }
                })
                .catch(() => undefined);

            return () => {
                cancelled = true;
                stop?.();
            };
        },
        socketId: () => inner?.socketId?.() ?? null,
        onStatusChange(callback) {
            statusCallback = callback;
            if (inner) {
                stopStatus = inner.onStatusChange?.(callback) ?? null;
            }

            return () => {
                statusCallback = null;
                stopStatus?.();
                stopStatus = null;
            };
        },
    };

    return { transport, throttle: 250 };
}
