import { useCallback, useEffect, useState } from 'react';

/*
 * Example run replay (S-14, decision 63): deliveries appear in run order,
 * their real offsets compressed into about 8–9 s. Reduced motion and deep
 * links start on the final frame; Pause holds, Replay starts over.
 */

const LEAD_MS = 700;
const SPAN_MS = 8200;

export function useReplay(offsets: number[], autoplay: boolean) {
    const total = offsets.length;
    const max = Math.max(1, ...offsets);
    const schedule = offsets.map((o) => LEAD_MS + (o / max) * SPAN_MS);
    // Same first frame on the server and in the browser (SSR hydration):
    // an autoplaying run starts empty, and reduced motion jumps to the end.
    const [revealed, setRevealed] = useState(autoplay ? 0 : total);
    const [playing, setPlaying] = useState(autoplay);

    useEffect(() => {
        if (
            autoplay &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            setPlaying(false);
            setRevealed(total);
        }
    }, [autoplay, total]);

    const active = playing && revealed < total;

    useEffect(() => {
        if (!active) {
            return;
        }
        const previous = revealed === 0 ? 0 : schedule[revealed - 1];
        const t = window.setTimeout(
            () => setRevealed((r) => r + 1),
            Math.max(60, schedule[revealed] - previous),
        );

        return () => window.clearTimeout(t);
        // schedule is derived from offsets; revealed drives the chain.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [active, revealed]);

    const replay = useCallback(() => {
        setRevealed(0);
        setPlaying(true);
    }, []);
    const toggle = useCallback(() => {
        if (revealed >= total) {
            replay();
        } else {
            setPlaying(!active);
        }
    }, [revealed, total, replay, active]);
    const finish = useCallback(() => {
        setPlaying(false);
        setRevealed(total);
    }, [total]);

    return {
        revealed,
        playing: active,
        done: revealed >= total,
        replay,
        toggle,
        finish,
    };
}
