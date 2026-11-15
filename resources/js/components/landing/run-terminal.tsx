import { useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import {
    agentLines,
    excerptLines,
    LINE_DELAY,
    LOOP_DELAY,
    PROMPT,
    RUN_SUMMARY,
    runLines,
    START_DELAY,
    typeDelay,
    type TermLine,
} from './run-demo';
import { useInView } from './motion';
import { Segments, useTabs } from './term-text';

function Prompt() {
    return <span className="text-lp-mag">{PROMPT} </span>;
}

function Cursor({ blink }: { blink: boolean }) {
    return <span className="lp-cursor" data-blink={blink} />;
}

function Line({ children }: { children?: ReactNode }) {
    return <span className="block min-h-[1.72em]">{children}</span>;
}

function StaticLine({
    line,
    blink,
    animate = false,
}: {
    line: TermLine;
    blink: boolean;
    animate?: boolean;
}) {
    if (line.kind === 'prompt') {
        return (
            <Line>
                <Prompt />
                <Cursor blink={blink} />
            </Line>
        );
    }
    return (
        <Line>
            {line.kind === 'cmd' && <Prompt />}
            <Segments segments={line.segments} animate={animate} />
        </Line>
    );
}

/** Text of a `cmd` line, for typing. */
const cmdText = (line: TermLine) => line.segments.map((s) => s.text).join('');

type Timing = {
    start: number;
    type: (index: number) => number;
    line: Record<TermLine['kind'], number>;
};

const LANDING_TIMING: Timing = {
    start: START_DELAY,
    type: typeDelay,
    line: LINE_DELAY,
};

/**
 * Plays terminal lines: types `cmd` lines, then shows one line at a time.
 * Loops after LOOP_DELAY when `loop` is set. Reduced motion jumps to the
 * final frame and never plays. `visible` false holds the playback where it
 * is (off screen), without changing the user's play/pause choice.
 */
function useRunPlayback(
    lines: TermLine[],
    {
        loop,
        visible = true,
        timing = LANDING_TIMING,
    }: { loop: boolean; visible?: boolean; timing?: Timing },
) {
    const last = lines.length;
    const [shown, setShown] = useState(0);
    const [chars, setChars] = useState(0);
    const [playing, setPlaying] = useState(true);
    const done = shown === last;

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setShown(last);
            setPlaying(false);
        }
    }, [last]);

    useEffect(() => {
        if (!playing || !visible || (shown === last && !loop)) return;

        let delay: number;
        let step: () => void;

        if (shown === last) {
            delay = LOOP_DELAY;
            step = () => {
                setShown(0);
                setChars(0);
            };
        } else {
            const next = lines[shown];
            const prev = lines[shown - 1];
            if (next.kind === 'cmd' && chars < cmdText(next).length) {
                delay =
                    shown === 0 && chars === 0
                        ? timing.start
                        : timing.type(chars);
                step = () => setChars((c) => c + 1);
            } else {
                delay =
                    next.kind === 'cmd'
                        ? timing.line.cmd
                        : prev && prev.kind !== 'cmd'
                          ? timing.line[prev.kind]
                          : 0;
                step = () => {
                    setShown((n) => n + 1);
                    setChars(0);
                };
            }
        }

        const t = window.setTimeout(step, delay);
        return () => window.clearTimeout(t);
    }, [playing, visible, shown, chars, last, lines, loop, timing]);

    function toggle() {
        if (playing) {
            setPlaying(false);
            return;
        }
        if (done) {
            setShown(0);
            setChars(0);
        }
        setPlaying(true);
    }

    return { shown, chars, playing: playing && (loop || !done), done, toggle };
}

/** The visible frame of a playback: shown lines plus the line being typed. */
function PlaybackLines({
    lines,
    shown,
    chars,
    playing,
}: {
    lines: TermLine[];
    shown: number;
    chars: number;
    playing: boolean;
}) {
    const typing = shown < lines.length && lines[shown].kind === 'cmd';

    return (
        <>
            {lines.slice(0, shown).map((line, i) => (
                <StaticLine key={i} line={line} blink={playing} animate />
            ))}
            {typing && (
                <Line>
                    <Prompt />
                    {cmdText(lines[shown]).slice(0, chars)}
                    <Cursor blink={playing} />
                </Line>
            )}
        </>
    );
}

/**
 * Hero terminal: `webhooktrap test --fail-on high` typed and run line by line,
 * looping. Reduced motion shows the final frame. The animated text is hidden
 * from assistive tech; the visible summary under the terminal is its text
 * alternative.
 */
export function RunTerminal() {
    const frameRef = useRef<HTMLDivElement>(null);
    const visible = useInView(frameRef);
    const { shown, chars, playing, done, toggle } = useRunPlayback(runLines, {
        loop: true,
        visible,
    });
    const { selected, tabProps } = useTabs(2, 1);
    const uid = useId();
    const summaryId = `${uid}-summary`;

    const tabs = [
        { label: 'agent', id: `${uid}-t-agent`, panel: `${uid}-p-agent` },
        { label: 'test', id: `${uid}-t-test`, panel: `${uid}-p-test` },
    ];
    const screen =
        'lp-screen h-[520px] overflow-auto px-3.5 py-3.5 text-[11px] leading-[1.72] whitespace-pre [font-stretch:78%] sm:px-5 sm:py-[18px] sm:text-[12.5px] min-[641px]:h-[560px] min-[1001px]:h-[600px]';

    return (
        <div>
            {/*
             * data-state drives the end of run (frame jolt, FAIL pulse, exit
             * chip); data-paused freezes every CSS animation inside when the
             * user pauses or the terminal is off screen.
             */}
            <div
                ref={frameRef}
                data-state={done ? 'exit' : 'run'}
                data-paused={!playing || !visible || undefined}
                className="lp-term overflow-hidden rounded-md border border-lp-rule bg-lp-win shadow-[0_40px_80px_-40px_#000]"
            >
                <div className="flex h-[38px] items-center border-b border-lp-rule-soft text-xs text-lp-dim">
                    <div
                        role="tablist"
                        aria-label="Terminal"
                        className="flex h-full"
                    >
                        {tabs.map((t, i) => (
                            <button
                                key={t.label}
                                {...tabProps(i, t.id, t.panel)}
                                className={cn(
                                    'flex h-full items-center border-r border-lp-rule-soft px-4 focus-visible:outline-offset-[-3px]',
                                    selected === i
                                        ? 'bg-lp-bg text-lp-ink'
                                        : 'text-lp-dim hover:text-lp-ink',
                                )}
                            >
                                {t.label}
                            </button>
                        ))}
                    </div>
                    <span className="ml-auto truncate pr-3.5 pl-3">
                        ~/code/brindle-app
                    </span>
                </div>

                <div
                    role="tabpanel"
                    id={tabs[0].panel}
                    aria-labelledby={tabs[0].id}
                    hidden={selected !== 0}
                    tabIndex={0}
                    className={screen}
                >
                    {/* Illustrative agent output: not a final CLI format. */}
                    {agentLines.map((line, i) => (
                        <StaticLine key={i} line={line} blink={false} />
                    ))}
                </div>

                <div
                    role="tabpanel"
                    id={tabs[1].panel}
                    aria-labelledby={tabs[1].id}
                    aria-describedby={summaryId}
                    hidden={selected !== 1}
                    tabIndex={0}
                    className={screen}
                >
                    <span className="sr-only">
                        Animated terminal run. Its text version follows the
                        terminal.
                    </span>
                    <div aria-hidden="true">
                        <PlaybackLines
                            lines={runLines}
                            shown={shown}
                            chars={chars}
                            playing={playing}
                        />
                    </div>
                </div>

                <div className="flex h-7 items-center bg-lp-mag text-[11.5px] font-bold text-lp-mag-ink">
                    <span className="flex h-full items-center bg-lp-mag-ink px-3 text-lp-mag">
                        maya-mbp
                    </span>
                    <span className="flex h-full items-center px-3 max-sm:hidden">
                        connected · v0.9.2
                    </span>
                    <span className="flex h-full items-center px-3 max-sm:hidden">
                        Run #3
                    </span>
                    {done ? (
                        <span className="lp-exit ml-auto flex h-full items-center bg-lp-red px-3 text-lp-bg">
                            exit 1
                        </span>
                    ) : (
                        <span className="ml-auto flex h-full items-center gap-2 px-3">
                            <span
                                aria-hidden="true"
                                className="lp-live size-[7px] bg-lp-mag-ink"
                            />
                            running
                        </span>
                    )}
                    <button
                        type="button"
                        onClick={toggle}
                        aria-label={
                            playing
                                ? 'Pause the terminal animation'
                                : done
                                  ? 'Replay the terminal animation'
                                  : 'Play the terminal animation'
                        }
                        className="h-full border-l border-lp-mag-ink/35 px-3 font-bold focus-visible:outline-offset-[-3px] focus-visible:outline-lp-ink"
                    >
                        {playing ? 'Pause' : done ? 'Replay' : 'Play'}
                    </button>
                </div>
            </div>
            <p id={summaryId} className="mt-3 text-xs text-lp-dim">
                {RUN_SUMMARY}
            </p>
        </div>
    );
}

/**
 * Static run excerpt (scenario results, then the F2 finding) for the auth
 * screens' side panel. No motion, no controls; the caller hides it from
 * assistive tech. Needs the `lp-tokens` scope.
 */
export function RunExcerpt({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-md border border-lp-rule bg-lp-win',
                className,
            )}
        >
            <div className="flex h-8 items-center border-b border-lp-rule-soft px-3 text-[11px] text-lp-dim">
                test
                <span className="ml-auto">Run #3</span>
            </div>
            <div className="px-3 py-3 font-mono text-[11px] leading-[1.72] whitespace-pre-wrap [font-stretch:78%]">
                {excerptLines.map((line, i) => (
                    <StaticLine key={i} line={line} blink={false} />
                ))}
            </div>
        </div>
    );
}
