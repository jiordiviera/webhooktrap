import { findings } from './findings';

/**
 * Hero terminal data (decision 57, A04 mockup "direction-2-terminal").
 * The animation plays these lines in order; the component holds no copy.
 */

export type Tone =
    | 'ink'
    | 'muted'
    | 'dim'
    | 'green'
    | 'red'
    | 'amber'
    | 'orange'
    | 'info'
    | 'mag';

export type Segment = {
    text: string;
    tone?: Tone;
    bold?: boolean;
    /** Renders as a PASS/FAIL badge instead of plain text. */
    badge?: 'pass' | 'fail';
    /** "a → b": in the playing hero the value rolls from a to b. */
    flip?: boolean;
};

/**
 * `cmd` is typed character by character after the prompt; `run` lines are
 * scenario results (slow cadence); `slow` waits a little longer; `out` is
 * plain output; `prompt` is the final prompt with the cursor.
 */
export type LineKind = 'cmd' | 'out' | 'run' | 'slow' | 'prompt';

export type TermLine = { kind: LineKind; segments: Segment[] };

export const PROMPT = '~/code/brindle-app $';
export const COMMAND = 'webhooktrap test --fail-on high';

/** Milliseconds to wait after a line of each kind is shown. */
export const LINE_DELAY: Record<LineKind, number> = {
    cmd: 450,
    out: 70,
    run: 520,
    slow: 700,
    prompt: 0,
};
export const START_DELAY = 500;
export const LOOP_DELAY = 7000;
/** Per-character typing delay: base plus a fixed jitter pattern. */
export const typeDelay = (index: number) => 38 + ((index * 17) % 40);

const blank: TermLine = { kind: 'out', segments: [] };
const s = (text: string, tone?: Tone, bold?: boolean): Segment => ({
    text,
    tone,
    bold,
});
const flip = (text: string): Segment => ({
    text,
    tone: 'red',
    bold: true,
    flip: true,
});
const run = (
    badge: 'pass' | 'fail',
    name: string,
    ids: string,
    rest: Segment[],
): TermLine => ({
    kind: 'run',
    segments: [
        s(' '),
        { text: badge.toUpperCase(), badge },
        s(` ${name}`),
        s(ids, 'dim'),
        s('  '),
        ...rest,
    ],
});

export const runLines: TermLine[] = [
    { kind: 'cmd', segments: [s(COMMAND)] },
    blank,
    {
        kind: 'out',
        segments: [
            s('Run #3', 'ink', true),
            s(' '),
            s('Prove invoice.paid · renewal', 'muted'),
        ],
    },
    {
        kind: 'out',
        segments: [
            s('target ', 'dim'),
            s(' http://localhost:3000/webhooks/stripe '),
            s('via', 'dim'),
            s(' maya-mbp '),
            s('(Express)', 'dim'),
        ],
    },
    {
        kind: 'out',
        segments: [
            s('probe  ', 'dim'),
            s(' orders.count   '),
            s('reset', 'dim'),
            s(' npm run db:reset:webhooks'),
        ],
    },
    {
        kind: 'out',
        segments: [s('budget ', 'dim'), s(' 10 s · Webhook Trap default')],
    },
    {
        kind: 'slow',
        segments: [s('preflight  reset 610 ms · probe stable', 'dim')],
    },
    blank,
    run('pass', 'Once               ', 'D1 D2 D3   ', [
        s('200 ×3 · orders.count 0 → 1'),
    ]),
    run('fail', 'Twice              ', 'D4 D5      ', [
        s('orders.count '),
        flip('1 → 2'),
        s(' on D5, expected 1'),
    ]),
    run('pass', 'Shuffled           ', 'D6 D7 D8   ', [
        s('reversed · orders.count 0 → 1'),
    ]),
    run('pass', 'Late               ', 'D9         ', [
        s('400 in 41 ms · rejected as expected'),
    ]),
    run('fail', 'Retry after error  ', 'D10 D11    ', [
        s('11 840 ms', 'amber'),
        s(' → retry → orders.count '),
        flip('1 → 2'),
    ]),
    run('fail', 'Tampered           ', 'D12        ', [
        s('expected 4xx · got '),
        s('200', 'red', true),
        s(' in 167 ms'),
    ]),
    run('pass', 'Burst              ', 'D13–D15    ', [
        s('200 ×3 · 312–601 ms · 0 → 1 (final)'),
    ]),
    run('fail', 'Slow budget        ', 'D16        ', [
        s('10 420 ms', 'amber'),
        s(' · 420 ms over budget'),
    ]),
    blank,
    {
        kind: 'out',
        segments: [
            s(' '),
            s('■ F1 Critical', 'red'),
            s('  Accepts tampered payload                   '),
            s('D12', 'dim'),
        ],
    },
    {
        kind: 'out',
        segments: [
            s(' '),
            s('▲ F2 High    ', 'orange'),
            s('  Duplicate delivery created a second order  '),
            s('D5 D11', 'dim'),
        ],
    },
    {
        kind: 'out',
        segments: [
            s(' '),
            s('△ F3 Medium  ', 'amber'),
            s('  Over the 10 s budget for Stripe            '),
            s('D10 D16', 'dim'),
        ],
    },
    {
        kind: 'out',
        segments: [
            s(' '),
            s('○ F4 Info    ', 'info'),
            s("  Probe doesn't see subscription state       "),
            s('D6 D15', 'dim'),
        ],
    },
    blank,
    {
        kind: 'out',
        segments: [
            s('3 findings', 'ink', true),
            s(' '),
            s(
                '(1 Critical · 1 High · 1 Medium) · 1 Info · 16 deliveries · 43.2 s',
                'muted',
            ),
        ],
    },
    {
        kind: 'out',
        segments: [
            s('report', 'dim'),
            s(' https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC'),
        ],
    },
    {
        kind: 'out',
        segments: [
            s('exit 1', 'red', true),
            s(' '),
            s('--fail-on high', 'dim'),
        ],
    },
    { kind: 'prompt', segments: [] },
];

/**
 * Excerpt for the auth screens' side panel, derived from runLines and the F2
 * finding (not copied): the command, each scenario as badge + name, then
 * "▲ F2 · Duplicate delivery created a second order".
 */
const f2 = findings.find((f) => f.id === 'f2')!;
export const excerptLines: TermLine[] = [
    { kind: 'out', segments: [s('$ ', 'mag'), s(COMMAND)] },
    blank,
    ...runLines
        .filter((l) => l.kind === 'run')
        .map((l): TermLine => ({
            kind: 'out',
            segments: l.segments.slice(0, 3),
        })),
    blank,
    {
        kind: 'out',
        segments: [
            s(`${f2.glyph} F2`, f2.tone, true),
            s(` · ${f2.title.split(' · ')[1]}`),
        ],
    },
];

/** Text alternative for the animated run, shown under the terminal. */
export const RUN_SUMMARY =
    'webhooktrap test --fail-on high runs Run #3 against localhost:3000/webhooks/stripe. Once, Shuffled, Late and Burst pass. Twice, Retry after error, Tampered and Slow budget fail. 3 findings: F1 Critical, accepts a tampered payload; F2 High, a duplicate delivery created a second order; F3 Medium, over the 10 s budget. Exit code 1.';

/**
 * The "agent" tab of the hero terminal: what the agent shows while it serves
 * the run. Illustrative output, not a final CLI format.
 */
export const agentLines: TermLine[] = [
    {
        kind: 'out',
        segments: [
            s('~/code/brindle-app $', 'mag'),
            s(' npx @webhooktrap/agent'),
        ],
    },
    blank,
    {
        kind: 'out',
        segments: [s('agent  ', 'dim'), s(' maya-mbp · v0.9.2')],
    },
    {
        kind: 'out',
        segments: [
            s('server ', 'dim'),
            s(' https://webhooktrap.dev '),
            s('connected', 'green'),
        ],
    },
    {
        kind: 'out',
        segments: [s('allow  ', 'dim'), s(' http://localhost:3000/webhooks/*')],
    },
    {
        kind: 'out',
        segments: [
            s('secrets', 'dim'),
            s(' STRIPE_WEBHOOK_SECRET '),
            s('set', 'green'),
            s('  stays on this machine', 'dim'),
        ],
    },
    blank,
    {
        kind: 'out',
        segments: [
            s('Run #3', 'ink', true),
            s('  16 deliveries → localhost:3000', 'muted'),
        ],
    },
    {
        kind: 'out',
        segments: [s('       probe orders.count after each delivery', 'dim')],
    },
    {
        kind: 'out',
        segments: [
            s(
                '       done · 43.2 s · sent counts and status codes only',
                'dim',
            ),
        ],
    },
    blank,
    { kind: 'out', segments: [s('waiting for runs…', 'muted')] },
];
