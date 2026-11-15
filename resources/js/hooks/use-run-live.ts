import { router } from '@inertiajs/react';
import { echo, echoIsConfigured } from '@laravel/echo-react';
import { useEffect } from 'react';

/*
 * Owner run page live updates (event-catalogue §3.2, ADR-004 am. 1): while
 * the run is not final the server sends `live.channel` (`run.<uuid>`); the
 * timeline events on `private-run.<uuid>` carry ids and codes only, so each
 * one triggers a throttled partial reload of the run props (values load
 * with the reload, PV-6). Without a socket the page polls every 3 s.
 */

const EVENTS = [
    'run.accepted',
    'run.delivery',
    'run.scenario',
    'run.finding',
    'run.finished',
];
const PROPS = ['run', 'scenarios', 'timeline', 'findings', 'live'];
const THROTTLE_MS = 250;
const POLL_MS = 3000;

export function useRunLive(channel: string | null) {
    useEffect(() => {
        if (!channel) {
            return;
        }
        let pending: number | null = null;
        let inFlight = false;
        const reload = () => {
            if (pending !== null) {
                return;
            }
            pending = window.setTimeout(() => {
                pending = null;

                if (inFlight) {
                    reload();

                    return;
                }
                inFlight = true;
                router.reload({
                    only: PROPS,
                    onFinish: () => {
                        inFlight = false;
                    },
                });
            }, THROTTLE_MS);
        };

        let stopSocket: (() => void) | null = null;

        if (echoIsConfigured()) {
            const ch = echo().private(channel);
            EVENTS.forEach((e) => ch.listen(`.${e}`, reload));
            stopSocket = () => {
                EVENTS.forEach((e) => ch.stopListening(`.${e}`, reload));
                echo().leave(channel);
            };
        }
        // A safety net: socket down, no key, or an event lost on the way.
        const poll = window.setInterval(reload, POLL_MS);

        return () => {
            window.clearInterval(poll);

            if (pending !== null) {
                window.clearTimeout(pending);
            }
            stopSocket?.();
        };
    }, [channel]);
}
