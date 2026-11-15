import { useRef, useState, type KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';
import type { Segment, Tone } from './run-demo';

export const toneClass: Record<Tone, string> = {
    ink: 'text-lp-ink',
    muted: 'text-lp-muted',
    dim: 'text-lp-dim',
    green: 'text-lp-green',
    red: 'text-lp-red',
    amber: 'text-lp-amber',
    orange: 'text-lp-orange',
    info: 'text-lp-info',
    mag: 'text-lp-mag',
};

export function Badge({ kind }: { kind: 'pass' | 'fail' }) {
    return (
        <span
            data-badge={kind}
            className={cn(
                'lp-badge relative inline-block px-1.5 font-extrabold text-lp-bg',
                kind === 'pass' ? 'bg-lp-green' : 'bg-lp-red',
            )}
        >
            {kind.toUpperCase()}
        </span>
    );
}

/**
 * "a → b" as a counter: `a → ` then a one-line window that rolls from a to
 * b (CSS `lp-flip`). Only used inside the aria-hidden hero playback.
 */
function FlipValue({ seg }: { seg: Segment }) {
    const [from, to] = seg.text.split(' → ');
    return (
        <span
            className={cn(
                seg.tone && toneClass[seg.tone],
                seg.bold && 'font-bold',
            )}
        >
            {from} →{' '}
            <span className="lp-flip">
                <span className="lp-flip-col">
                    <span>{from}</span>
                    <span>{to}</span>
                </span>
            </span>
        </span>
    );
}

/**
 * Renders coloured terminal segments. `animate` (the playing hero only)
 * turns `flip` segments into rolling counters.
 */
export function Segments({
    segments,
    animate = false,
}: {
    segments: Segment[];
    animate?: boolean;
}) {
    return (
        <>
            {segments.map((seg, i) =>
                seg.badge ? (
                    <Badge key={i} kind={seg.badge} />
                ) : animate && seg.flip ? (
                    <FlipValue key={i} seg={seg} />
                ) : (
                    <span
                        key={i}
                        className={cn(
                            seg.tone && toneClass[seg.tone],
                            seg.bold && 'font-bold',
                        )}
                    >
                        {seg.text}
                    </span>
                ),
            )}
        </>
    );
}

/**
 * Roving-tabindex tabs (WAI-ARIA APG, automatic activation): arrow keys move
 * and select, Home/End jump to the ends.
 */
export function useTabs(count: number, initial = 0) {
    const [selected, setSelected] = useState(initial);
    const refs = useRef<(HTMLButtonElement | null)[]>([]);

    function select(i: number) {
        setSelected(i);
        refs.current[i]?.focus();
    }

    function onKeyDown(e: KeyboardEvent<HTMLButtonElement>) {
        const keys: Record<string, number> = {
            ArrowRight: (selected + 1) % count,
            ArrowLeft: (selected + count - 1) % count,
            Home: 0,
            End: count - 1,
        };
        if (e.key in keys) {
            e.preventDefault();
            select(keys[e.key]);
        }
    }

    function tabProps(i: number, id: string, panelId: string) {
        return {
            role: 'tab' as const,
            id,
            type: 'button' as const,
            'aria-controls': panelId,
            'aria-selected': i === selected,
            tabIndex: i === selected ? 0 : -1,
            ref: (el: HTMLButtonElement | null) => {
                refs.current[i] = el;
            },
            onClick: () => setSelected(i),
            onKeyDown,
        };
    }

    return { selected, tabProps };
}
