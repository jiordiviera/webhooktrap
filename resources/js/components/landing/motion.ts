import { useEffect, useLayoutEffect, useState, type RefObject } from 'react';

/**
 * Landing motion helpers (decision 59). No library: IntersectionObserver,
 * CSS keyframes and the Web Animations API only.
 */

export const reducedMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** True while the element is on screen; used to pause loops off screen. */
export function useInView<T extends Element>(
    ref: RefObject<T | null>,
    rootMargin = '0px',
) {
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el || typeof IntersectionObserver === 'undefined') {
            setInView(true);
            return;
        }
        const io = new IntersectionObserver(
            ([entry]) => setInView(entry.isIntersecting),
            { rootMargin },
        );
        io.observe(el);
        return () => io.disconnect();
    }, [ref, rootMargin]);

    return inView;
}

/**
 * One-shot reveal state for `data-motion`:
 * - undefined: static, fully visible (first render, reduced motion, no IO);
 * - 'armed': motion will play, CSS holds the start state;
 * - 'play': the element reached the viewport, CSS plays the sequence once.
 * Arming happens before paint, so content never flashes in and out.
 */
export function useReveal<T extends Element>(
    ref: RefObject<T | null>,
    threshold = 0.25,
) {
    const [state, setState] = useState<'armed' | 'play' | undefined>();

    useLayoutEffect(() => {
        const el = ref.current;
        if (
            !el ||
            reducedMotion() ||
            typeof IntersectionObserver === 'undefined'
        )
            return;
        setState('armed');
        const io = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setState('play');
                    io.disconnect();
                }
            },
            { threshold },
        );
        io.observe(el);
        return () => io.disconnect();
    }, [ref, threshold]);

    return state;
}
