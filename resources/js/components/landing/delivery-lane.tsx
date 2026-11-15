import { useEffect, useRef, type CSSProperties } from 'react';
import { StripeLogo } from '@/components/brand/provider-logos';
import { cn } from '@/lib/utils';
import { reducedMotion, useInView } from './motion';
import { runLines, type Segment } from './run-demo';
import { Badge, Segments } from './term-text';

/**
 * Deliveries made visible: Stripe → your handler, one scenario at a time.
 * Purely decorative (aria-hidden); the scenario list below it is the content.
 * Every scenario is one Web Animations timeline of length T (ms): packets
 * fly with transform, the handler's counter rolls with transform, status
 * chips and the verdict switch with opacity. It loops only while on screen;
 * reduced motion renders the finished timeline, static.
 */

type Status = 'ok' | 'bad' | 'rejected' | 'timeout' | 'slow';

type Packet = {
    id: string;
    /** Departure and flight time, ms from the start of the timeline. */
    at: number;
    fly: number;
    /** Vertical offset at departure and arrival (px), for crossing paths. */
    y?: [number, number];
    /** amber: late or slow; unsigned: no signature mark. */
    look?: 'amber' | 'unsigned';
    /** Turns red half way: the body is changed in flight. */
    tamper?: boolean;
};

type Beat = { at: number; count?: number; status?: Status };

type Lane = {
    packets: Packet[];
    beats: Beat[];
    verdict: 'pass' | 'fail' | null;
    /** A clock that runs out before departure (Late). */
    clock?: boolean;
    /** The 10 s budget bar fills past its mark (Slow budget). */
    budget?: boolean;
};

const FLY = 900;

const lanes: Record<string, Lane> = {
    Once: {
        packets: [
            { id: 'D1', at: 0, fly: FLY },
            { id: 'D2', at: 700, fly: FLY },
            { id: 'D3', at: 1400, fly: FLY },
        ],
        beats: [
            { at: 900, count: 1, status: 'ok' },
            { at: 1600, status: 'ok' },
            { at: 2300, status: 'ok' },
        ],
        verdict: 'pass',
    },
    Twice: {
        packets: [
            { id: 'D4', at: 0, fly: FLY },
            { id: 'D5', at: 1400, fly: FLY },
        ],
        beats: [
            { at: 900, count: 1, status: 'ok' },
            { at: 2300, count: 2, status: 'ok' },
        ],
        verdict: 'fail',
    },
    Shuffled: {
        packets: [
            { id: 'D6', at: 0, fly: 1500, y: [-14, 14] },
            { id: 'D7', at: 150, fly: 1100, y: [0, 0] },
            { id: 'D8', at: 300, fly: 700, y: [14, -14] },
        ],
        beats: [
            { at: 1000, count: 1, status: 'ok' },
            { at: 1250, status: 'ok' },
            { at: 1500, status: 'ok' },
        ],
        verdict: 'pass',
    },
    Late: {
        packets: [{ id: 'D9', at: 900, fly: FLY, look: 'amber' }],
        beats: [{ at: 1800, status: 'rejected' }],
        verdict: 'pass',
        clock: true,
    },
    'Retry after error': {
        packets: [
            { id: 'D10', at: 0, fly: 1500, look: 'amber' },
            { id: 'D11', at: 2100, fly: FLY },
        ],
        beats: [
            { at: 1500, count: 1, status: 'timeout' },
            { at: 3000, count: 2, status: 'ok' },
        ],
        verdict: 'fail',
    },
    Tampered: {
        packets: [{ id: 'D12', at: 0, fly: 1200, tamper: true }],
        beats: [{ at: 1200, status: 'bad' }],
        verdict: 'fail',
    },
    Unsigned: {
        packets: [{ id: 'D–', at: 0, fly: FLY, look: 'unsigned' }],
        beats: [],
        verdict: null,
    },
    Burst: {
        packets: [
            { id: 'D13', at: 0, fly: FLY, y: [-14, -14] },
            { id: 'D14', at: 20, fly: FLY + 120, y: [0, 0] },
            { id: 'D15', at: 40, fly: FLY + 260, y: [14, 14] },
        ],
        beats: [
            { at: 900, count: 1, status: 'ok' },
            { at: 1040, status: 'ok' },
            { at: 1200, status: 'ok' },
        ],
        verdict: 'pass',
    },
    'Slow budget': {
        packets: [{ id: 'D16', at: 0, fly: FLY }],
        beats: [{ at: 2500, status: 'slow' }],
        verdict: 'fail',
        budget: true,
    },
};

const statusChip: Record<Status, { text: string; className: string }> = {
    ok: { text: '200', className: 'text-lp-green' },
    bad: { text: '200', className: 'text-lp-red' },
    rejected: { text: '400', className: 'text-lp-green' },
    timeout: { text: '11 840 ms', className: 'text-lp-amber' },
    slow: { text: '10 420 ms', className: 'text-lp-amber' },
};

/** The Run #3 result of a scenario, from the hero run (no copy of its own). */
function resultOf(name: string): Segment[] | null {
    const line = runLines.find(
        (l) => l.kind === 'run' && l.segments[2]?.text.trim() === name,
    );
    return line ? line.segments.slice(5) : null;
}

const end = (lane: Lane) =>
    Math.max(
        ...lane.packets.map((p) => p.at + p.fly),
        ...lane.beats.map((b) => b.at),
        lane.budget ? 2500 : 0,
    );

/** Keyframe offsets must stay in [0, 1] and never decrease. */
function monotonic(frames: Keyframe[]): Keyframe[] {
    let last = 0;
    return frames.map((k) => {
        last = Math.min(1, Math.max(last, k.offset as number));
        return { ...k, offset: last };
    });
}

/** Keyframes that hold `frames[k]` from `times[k]` on, with a short ease. */
function steps(
    total: number,
    times: number[],
    frames: Keyframe[],
    initial: Keyframe,
    ease = 160,
): Keyframe[] {
    const out: Keyframe[] = [{ ...initial, offset: 0 }];
    times.forEach((t, i) => {
        const prev = i === 0 ? initial : frames[i - 1];
        out.push({ ...prev, offset: t / total });
        out.push({
            ...frames[i],
            offset: (t + ease) / total,
            easing: 'cubic-bezier(.2,.7,.2,1)',
        });
    });
    out.push({ ...(frames.at(-1) ?? initial), offset: 1 });
    return monotonic(out);
}

function play(root: HTMLElement, lane: Lane, T: number): Animation[] {
    const opts = { duration: T, fill: 'both' } as const;
    const anims: Animation[] = [];
    const q = <E extends Element = HTMLElement>(sel: string) =>
        Array.from(root.querySelectorAll<E & HTMLElement>(sel));

    q('[data-flight]').forEach((el, i) => {
        const p = lane.packets[i];
        const [y0, y1] = p.y ?? [0, 0];
        const a = p.at / T;
        const b = (p.at + p.fly) / T;
        const gone = Math.min(1, (p.at + p.fly + 140) / T);
        anims.push(
            el.animate(
                [
                    {
                        transform: `translate(0%, ${y0}px)`,
                        opacity: 0,
                        offset: 0,
                    },
                    {
                        transform: `translate(0%, ${y0}px)`,
                        opacity: 0,
                        offset: a,
                    },
                    {
                        transform: `translate(0%, ${y0}px)`,
                        opacity: 1,
                        offset: Math.min(b, a + 60 / T),
                        easing: 'cubic-bezier(.45,.05,.35,1)',
                    },
                    {
                        transform: `translate(100%, ${y1}px)`,
                        opacity: 1,
                        offset: b,
                    },
                    {
                        transform: `translate(100%, ${y1}px)`,
                        opacity: 0,
                        offset: gone,
                    },
                    {
                        transform: `translate(100%, ${y1}px)`,
                        opacity: 0,
                        offset: 1,
                    },
                ],
                opts,
            ),
        );
        if (p.tamper) {
            const red = el.querySelector('[data-tamper]');
            const mid = (p.at + p.fly / 2) / T;
            if (red)
                anims.push(
                    red.animate(
                        [
                            { opacity: 0, offset: 0 },
                            { opacity: 0, offset: mid },
                            { opacity: 1, offset: mid + 120 / T },
                            { opacity: 1, offset: 1 },
                        ],
                        opts,
                    ),
                );
        }
    });

    // Handler: counter rolls, the inbox ring blinks once per arrival.
    const counts = lane.beats.filter((b) => b.count !== undefined);
    q('[data-count]').forEach((el) =>
        anims.push(
            el.animate(
                steps(
                    T,
                    counts.map((b) => b.at),
                    counts.map((b) => ({
                        transform: `translateY(${-(b.count ?? 0)}lh)`,
                    })),
                    { transform: 'translateY(0)' },
                ),
                opts,
            ),
        ),
    );

    q('[data-status]').forEach((el) => {
        const key = el.dataset.status as Status;
        const beats = lane.beats.filter((b) => b.status);
        anims.push(
            el.animate(
                steps(
                    T,
                    beats.map((b) => b.at),
                    beats.map((b) => ({ opacity: b.status === key ? 1 : 0 })),
                    { opacity: 0 },
                    90,
                ),
                opts,
            ),
        );
    });

    q('[data-ring]').forEach((el) => {
        const arrivals = lane.packets
            .map((p) => p.at + p.fly)
            .sort((a, b) => a - b);
        const frames: Keyframe[] = [{ opacity: 0, offset: 0 }];
        arrivals.forEach((t) => {
            frames.push({ opacity: 0, offset: t / T });
            frames.push({ opacity: 1, offset: (t + 60) / T });
            frames.push({ opacity: 0, offset: (t + 420) / T });
        });
        frames.push({ opacity: 0, offset: 1 });
        anims.push(el.animate(monotonic(frames), opts));
    });

    q('[data-clock]').forEach((el) =>
        anims.push(
            el.animate(
                [
                    { transform: 'rotate(0deg)', offset: 0 },
                    { transform: 'rotate(360deg)', offset: 850 / T },
                    { transform: 'rotate(360deg)', offset: 1 },
                ],
                opts,
            ),
        ),
    );

    q('[data-budget]').forEach((el) =>
        anims.push(
            el.animate(
                [
                    { transform: 'scaleX(0)', offset: 0 },
                    { transform: 'scaleX(0)', offset: 900 / T },
                    { transform: 'scaleX(1.04)', offset: 2500 / T },
                    { transform: 'scaleX(1.04)', offset: 1 },
                ],
                opts,
            ),
        ),
    );

    q('[data-verdict]').forEach((el) =>
        anims.push(
            el.animate(
                [
                    { opacity: 0, transform: 'translateY(4px)', offset: 0 },
                    {
                        opacity: 0,
                        transform: 'translateY(4px)',
                        offset: (T - 260) / T,
                    },
                    { opacity: 1, transform: 'none', offset: 1 },
                ],
                { ...opts, easing: 'cubic-bezier(.2,.7,.2,1)' },
            ),
        ),
    );

    return anims;
}

const HOLD = 2200;

export function DeliveryLane({
    name,
    className,
    style,
}: {
    name: string;
    className?: string;
    style?: CSSProperties;
}) {
    const rootRef = useRef<HTMLDivElement>(null);
    const visible = useInView(rootRef, '0px 0px -10% 0px');
    const lane = lanes[name] ?? lanes.Twice;
    const T = end(lane) + 500;
    const result = resultOf(name);

    useEffect(() => {
        const root = rootRef.current;
        if (!root) return;
        if (reducedMotion()) {
            play(root, lane, T).forEach((a) => a.finish());
            return;
        }
        if (!visible) return;

        let anims: Animation[] = [];
        let timer = 0;
        let alive = true;
        const loop = () => {
            anims.forEach((a) => a.cancel());
            anims = play(root, lane, T);
            Promise.all(anims.map((a) => a.finished))
                .then(() => {
                    if (alive) timer = window.setTimeout(loop, HOLD);
                })
                .catch(() => {});
        };
        // A short beat before the first flight lets the label settle.
        timer = window.setTimeout(loop, 180);

        return () => {
            alive = false;
            window.clearTimeout(timer);
            anims.forEach((a) => a.cancel());
        };
    }, [lane, T, visible]);

    return (
        <div
            ref={rootRef}
            aria-hidden="true"
            className={cn(
                'lp-lane overflow-hidden rounded-md border border-lp-rule bg-lp-win text-[12px] [font-stretch:80%]',
                className,
            )}
            style={style}
        >
            <div className="flex h-[34px] items-center gap-3 border-b border-lp-rule-soft px-4 text-lp-dim">
                <span>deliveries</span>
                <span key={name} className="lp-lane-name font-bold text-lp-mag">
                    {name}
                </span>
                <span className="ml-auto truncate max-sm:hidden">
                    Stripe → localhost:3000/webhooks/stripe
                </span>
            </div>

            <div
                key={name}
                className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 px-3 py-4 sm:gap-4 sm:px-4"
            >
                {/* Source */}
                <div className="flex items-center gap-2 text-lp-ink">
                    <span className="relative grid size-8 place-items-center rounded-[3px] border border-lp-rule bg-lp-raise">
                        <StripeLogo className="size-4" />
                        {lane.clock && (
                            <span className="absolute -top-2 -right-2 grid size-4 place-items-center rounded-full border border-lp-amber bg-lp-win">
                                <span
                                    data-clock
                                    className="block h-[6px] w-px origin-bottom -translate-y-[3px] bg-lp-amber"
                                />
                            </span>
                        )}
                    </span>
                    <span className="max-sm:hidden">Stripe</span>
                </div>

                {/* Track */}
                <div className="relative h-[60px]">
                    <span className="absolute inset-x-0 top-1/2 border-t border-dashed border-lp-rule" />
                    {lane.packets.map((p) => (
                        <span
                            key={p.id}
                            data-flight
                            className="absolute inset-y-0 right-[38px] left-0 flex items-center opacity-0"
                        >
                            <span
                                className={cn(
                                    'relative flex h-[16px] w-[38px] items-center justify-center gap-1 rounded-[2px] border text-[10px] leading-none font-bold',
                                    p.look === 'amber'
                                        ? 'border-lp-amber bg-lp-raise text-lp-amber'
                                        : p.look === 'unsigned'
                                          ? 'border-dashed border-lp-muted bg-lp-win text-lp-muted'
                                          : 'border-lp-ink bg-lp-ink text-lp-bg',
                                )}
                            >
                                {p.id}
                                {p.tamper && (
                                    <span
                                        data-tamper
                                        className="absolute -inset-px flex items-center justify-center rounded-[2px] bg-lp-red text-lp-bg opacity-0"
                                    >
                                        {p.id}
                                    </span>
                                )}
                            </span>
                        </span>
                    ))}
                </div>

                {/* Handler */}
                <div className="relative min-w-[118px] rounded-[3px] border border-lp-rule bg-lp-raise px-3 py-2 sm:min-w-[164px]">
                    <span
                        data-ring
                        className="pointer-events-none absolute -inset-px rounded-[3px] border border-lp-mag opacity-0"
                    />
                    <span className="block text-lp-dim">your handler</span>
                    <span className="flex items-center gap-1.5">
                        <span className="text-lp-muted max-sm:hidden">
                            orders.count
                        </span>
                        <span className="text-lp-muted sm:hidden">orders</span>
                        <span className="lp-flip font-bold">
                            <span data-count className="lp-flip-col">
                                <span>0</span>
                                <span>1</span>
                                <span className="text-lp-red">2</span>
                            </span>
                        </span>
                        <span className="relative ml-auto inline-grid font-bold">
                            {(Object.keys(statusChip) as Status[]).map((k) => (
                                <span
                                    key={k}
                                    data-status={k}
                                    className={cn(
                                        'col-start-1 row-start-1 text-right whitespace-nowrap opacity-0',
                                        statusChip[k].className,
                                    )}
                                >
                                    {statusChip[k].text}
                                </span>
                            ))}
                        </span>
                    </span>
                    {/* Always laid out, so switching scenarios shifts nothing. */}
                    <span
                        className={cn(
                            'relative mt-1.5 block h-[3px] bg-lp-rule-soft',
                            !lane.budget && 'invisible',
                        )}
                    >
                        {lane.budget && (
                            <>
                                <span
                                    data-budget
                                    className="absolute inset-y-0 left-0 w-[92%] origin-left scale-x-0 bg-lp-amber"
                                />
                                <span className="absolute -top-[2px] left-[92%] h-[7px] w-px bg-lp-ink" />
                            </>
                        )}
                    </span>
                </div>
            </div>

            <div className="flex min-h-[38px] items-center gap-3 border-t border-lp-rule-soft px-4 py-2 text-lp-muted">
                <span data-verdict className="shrink-0 opacity-0">
                    {lane.verdict ? (
                        <Badge kind={lane.verdict} />
                    ) : (
                        <span className="text-lp-dim">expect 4xx</span>
                    )}
                </span>
                <span className="min-w-0 truncate">
                    {result ? <Segments segments={result} /> : null}
                </span>
            </div>
        </div>
    );
}

export const laneScenarioNames = Object.keys(lanes);
