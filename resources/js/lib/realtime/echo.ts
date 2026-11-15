import type { LiveOptions } from '@inertiajs/core';
import { echo as inertiaEcho } from '@inertiajs/react/echo';
import { configureEcho, echo } from '@laravel/echo-react';
import type { ChannelAuthorizationCallback } from 'pusher-js';
import { useSyncExternalStore } from 'react';
import { xsrfToken } from '@/lib/identity/http';

/*
 * Live updates (ADR-004 amendment 1, decision 62): Echo over Reverb feeds
 * Inertia v3 Live Props. The socket goes to this origin (`/app`, proxied to
 * Reverb), the channel auth posts to `/broadcasting/auth` with the
 * `__Host-XSRF-TOKEN` header, and client events are never sent. When the
 * key is missing or the socket can't connect, pages keep working and show
 * "Live updates paused".
 */

export type LiveStatus = 'off' | 'connecting' | 'live' | 'paused';

const listeners = new Set<() => void>();
let status: LiveStatus = 'off';

function setStatus(next: LiveStatus): void {
    if (status !== next) {
        status = next;
        listeners.forEach((listener) => listener());
    }
}

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

/** Configures Echo and returns Inertia's live option, or undefined (no live). */
export function setupLive(): LiveOptions | undefined {
    const key = typeof window === 'undefined' ? null : initialKey();

    if (!key) {
        return undefined;
    }
    const secure = window.location.protocol === 'https:';
    const port = Number(window.location.port) || (secure ? 443 : 80);

    configureEcho({
        broadcaster: 'reverb',
        key,
        wsHost: window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: secure,
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel: { name: string }) => ({
            authorize: (
                socketId: string,
                callback: ChannelAuthorizationCallback,
            ) => {
                fetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken() ?? '',
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then((response) =>
                        response.ok
                            ? response.json()
                            : Promise.reject(
                                  new Error(`auth ${response.status}`),
                              ),
                    )
                    .then((data) => callback(null, data))
                    .catch((error: unknown) =>
                        callback(
                            error instanceof Error
                                ? error
                                : new Error('auth failed'),
                            null,
                        ),
                    );
            },
        }),
    });

    setStatus('connecting');
    echo().connector.onConnectionChange((next) => {
        setStatus(
            next === 'connected'
                ? 'live'
                : next === 'connecting'
                  ? 'connecting'
                  : 'paused',
        );
    });

    return inertiaEcho({ throttle: 250 });
}

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

/** "live", "connecting", "paused" (lost) or "off" (not configured). */
export function useLiveStatus(): LiveStatus {
    return useSyncExternalStore(
        subscribe,
        () => status,
        () => 'off' as LiveStatus,
    );
}
