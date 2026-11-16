import type { LiveTransport } from '@inertiajs/core';
import { echo as inertiaEcho } from '@inertiajs/react/echo';
import { configureEcho, echo } from '@laravel/echo-react';
import type { ChannelAuthorizationCallback } from 'pusher-js';
import { xsrfToken } from '@/lib/identity/http';
import { setLiveStatus } from '@/lib/realtime/status';

/*
 * Live updates (ADR-004 amendment 1, decision 62): Echo over Reverb feeds
 * Inertia v3 Live Props. The socket goes to this origin (`/app`, proxied to
 * Reverb), the channel auth posts to `/broadcasting/auth` with the
 * `__Host-XSRF-TOKEN` header, and client events are never sent. When the
 * key is missing or the socket can't connect, pages keep working and show
 * "Live updates paused". Loaded on demand by `live.ts`, never statically.
 */

/** Configures Echo for this Reverb key and returns Inertia's Echo transport. */
export function createLiveTransport(key: string): LiveTransport {
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

    setLiveStatus('connecting');
    echo().connector.onConnectionChange((next) => {
        setLiveStatus(
            next === 'connected'
                ? 'live'
                : next === 'connecting'
                  ? 'connecting'
                  : 'paused',
        );
    });

    // The Echo adapter only ever builds an event-name transport.
    return inertiaEcho().transport as LiveTransport;
}
