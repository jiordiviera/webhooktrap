import { Link } from '@inertiajs/react';
import {
    forwardRef,
    useEffect,
    useRef,
    useState,
    type CSSProperties,
    type HTMLAttributes,
    type ReactNode,
} from 'react';
import { SupportLink } from '@/components/brand/support-link';
import { cn } from '@/lib/utils';
import { privacy, terms } from '@/routes';
import { findings, type DiffLine, type Finding } from './findings';
import { CopyCommand, GetUrlButton } from './landing-button';
import {
    GitHubLogo,
    ShopifyLogo,
    StripeLogo,
} from '@/components/brand/provider-logos';
import { DeliveryLane } from './delivery-lane';
import { useReveal } from './motion';
import { scenarios } from './scenarios';
import { Badge, Segments, toneClass, useTabs } from './term-text';

const wrap = 'mx-auto w-full max-w-[1280px] px-4 sm:px-7';
const split =
    'grid grid-cols-1 gap-8 min-[1001px]:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] min-[1001px]:gap-14';
const h2 =
    'text-[clamp(28px,3.2vw,42px)] leading-[1.1] font-[750] tracking-[-0.03em] [font-stretch:80%]';
const lede = 'max-w-[56ch] text-[15px] text-lp-muted';

function Section({
    id,
    className,
    children,
}: {
    id: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={id}
            className={cn(wrap, 'pt-20 sm:pt-28', className)}
        >
            {children}
        </section>
    );
}

function SectionHead({
    id,
    title,
    children,
}: {
    id: string;
    title: ReactNode;
    children?: ReactNode;
}) {
    return (
        <div className={cn(split, 'mb-10 items-end')}>
            <h2 id={id} className={h2}>
                {title}
            </h2>
            {children && <p className={lede}>{children}</p>}
        </div>
    );
}

/** A code window: file bar and a wrapped `pre`. */
function Pane({
    label,
    children,
    preProps,
    preClassName,
}: {
    label: string;
    children: ReactNode;
    preProps?: HTMLAttributes<HTMLPreElement>;
    preClassName?: string;
}) {
    return (
        <div className="min-w-0 overflow-hidden rounded-md border border-lp-rule bg-lp-win">
            <div className="flex h-[38px] items-center border-b border-lp-rule-soft px-4 text-xs text-lp-dim">
                {label}
            </div>
            <pre
                {...preProps}
                className={cn(
                    'm-0 px-5 py-[18px] font-[inherit] text-[13px] leading-[1.85] [overflow-wrap:anywhere] whitespace-pre-wrap [font-stretch:80%]',
                    preClassName,
                )}
            >
                {children}
            </pre>
        </div>
    );
}

const Dim = ({ children }: { children: ReactNode }) => (
    <span className="text-lp-dim">{children}</span>
);
const Ps = () => <span className="text-lp-mag">$</span>;

/** Stagger index for CSS sequences (`--i`). */
const nth = (i: number) => ({ '--i': i }) as CSSProperties;

/* ---------------------------------------------------------------- findings */

function Diff({ line, i }: { line: DiffLine; i: number }) {
    const marker = line.kind === 'add' ? '+' : line.kind === 'del' ? '-' : null;
    return (
        <span
            data-n={line.n}
            data-kind={line.kind}
            style={nth(i)}
            className={cn(
                'lp-diff-line block pr-[18px] whitespace-pre',
                line.kind === 'hunk' && 'text-lp-info',
            )}
        >
            {marker && (
                <span
                    className={
                        line.kind === 'add' ? 'text-lp-green' : 'text-lp-red'
                    }
                >
                    {marker}
                </span>
            )}
            {line.text}
        </span>
    );
}

function FindingPanel({ f }: { f: Finding }) {
    return (
        <>
            <div className="min-w-0 border-b border-lp-rule px-4 py-5 text-[13px] leading-[1.75] [font-stretch:80%] min-[1001px]:border-r min-[1001px]:border-b-0 sm:px-7 sm:py-[26px]">
                <Badge kind="fail" />{' '}
                <span className="text-lp-muted">{f.scenarios}</span>
                <h3 className="lp-type mt-2.5 mb-[18px] text-[17px] leading-[1.35] font-[750] [font-stretch:87.5%]">
                    {f.title}
                </h3>
                <dl
                    style={nth(0)}
                    className="lp-rv mb-[18px] grid grid-cols-[90px_minmax(0,1fr)] rounded-[3px] bg-lp-raise px-4 py-3.5 sm:grid-cols-[110px_minmax(0,1fr)]"
                >
                    {f.expectation.map((row) => (
                        <div key={row.term} className="contents">
                            <dt className="text-lp-dim">{row.term}</dt>
                            <dd
                                className={cn(
                                    row.tone && toneClass[row.tone],
                                    row.bold && 'font-bold',
                                )}
                            >
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
                {f.evidence && (
                    <ul
                        tabIndex={0}
                        aria-label="Deliveries"
                        className="mb-[18px] overflow-x-auto whitespace-pre"
                    >
                        {f.evidence.map((segs, i) => (
                            <li key={i} className="lp-rv" style={nth(i + 1)}>
                                <Segments segments={segs} />
                            </li>
                        ))}
                    </ul>
                )}
                <p
                    className="lp-rv text-lp-muted"
                    style={nth((f.evidence?.length ?? 0) + 1)}
                >
                    {f.why}
                    <cite className="block text-lp-dim not-italic">
                        {f.cite}
                    </cite>
                </p>
            </div>
            <div className="min-w-0 text-[12.5px] leading-[1.75] [font-stretch:78%]">
                <div className="flex flex-wrap justify-between gap-3 border-b border-lp-rule-soft px-[18px] py-3 text-lp-muted">
                    <span>{f.file}</span>
                    <span>
                        <Segments segments={f.stat} />
                    </span>
                </div>
                {/* Illustrative fix diff (see findings.ts). */}
                <pre
                    tabIndex={0}
                    aria-label={`Illustrative fix for ${f.tab}, ${f.file}`}
                    className="m-0 overflow-x-auto py-2.5 font-[inherit]"
                >
                    {f.diff.map((line, i) => (
                        <Diff key={i} line={line} i={i} />
                    ))}
                </pre>
                <div className="border-t border-lp-rule-soft px-[18px] py-3.5 text-lp-muted">
                    <Segments segments={f.after} />
                </div>
            </div>
        </>
    );
}

/**
 * Motion: when the findings reach the viewport the FAIL badge pulses, the
 * title types in, the facts rise and the diff slides in line by line.
 * Switching tabs replays it (a hidden panel restarts its CSS animations).
 */
export function FailingTests() {
    const { selected, tabProps } = useTabs(findings.length);
    const motionRef = useRef<HTMLDivElement>(null);
    const motion = useReveal(motionRef, 0.2);

    return (
        <Section id="lp-s-fail">
            <SectionHead
                id="lp-s-fail"
                title="A finding is a failing test, with the diff that fixes it."
            >
                Each one names the deliveries that prove it, what was expected,
                what your handler did, and the provider doc behind it. Then the
                change. Run #3 found three.
            </SectionHead>
            <div ref={motionRef} data-motion={motion} className="lp-finding">
                <div
                    role="tablist"
                    aria-label="Findings from Run #3"
                    className="flex overflow-x-auto border-b border-lp-rule"
                >
                    {findings.map((f, i) => (
                        <button
                            key={f.id}
                            {...tabProps(i, `lp-t-${f.id}`, `lp-p-${f.id}`)}
                            className={cn(
                                'flex h-12 items-center gap-2.5 border-b-[3px] px-5 text-[13px] font-semibold whitespace-nowrap hover:text-lp-ink focus-visible:outline-offset-[-3px]',
                                selected === i
                                    ? 'border-lp-ink text-lp-ink'
                                    : 'border-transparent text-lp-muted',
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className={toneClass[f.tone]}
                            >
                                {f.glyph}
                            </span>
                            {f.tab}
                        </button>
                    ))}
                </div>
                {findings.map((f, i) => (
                    <div
                        key={f.id}
                        role="tabpanel"
                        id={`lp-p-${f.id}`}
                        aria-labelledby={`lp-t-${f.id}`}
                        hidden={selected !== i}
                        className="grid grid-cols-1 border border-t-0 border-lp-rule min-[1001px]:grid-cols-2"
                    >
                        <FindingPanel f={f} />
                    </div>
                ))}
            </div>
        </Section>
    );
}

/* --------------------------------------------------------------- scenarios */

/**
 * Motion: the delivery lane plays the scenario of the row under the pointer
 * or keyboard focus, else (wide screens, where the lane is sticky) the row
 * at the middle of the viewport, else Twice. Glyphs come in one after the
 * other when the list first shows; an opened row fades its text in.
 */
export function Scenarios() {
    const listRef = useRef<HTMLDivElement>(null);
    const motion = useReveal(listRef, 0.15);
    const [scrolled, setScrolled] = useState<number | null>(null);
    const [pointed, setPointed] = useState<number | null>(null);
    const active = pointed ?? scrolled ?? 1;

    useEffect(() => {
        const list = listRef.current;
        if (!list || typeof IntersectionObserver === 'undefined') return;
        const wide = window.matchMedia(
            '(min-width: 1001px) and (min-height: 760px)',
        );
        let io: IntersectionObserver | null = null;
        const sync = () => {
            io?.disconnect();
            io = null;
            setScrolled(null);
            if (!wide.matches) return;
            const rows = Array.from(list.querySelectorAll('details'));
            io = new IntersectionObserver(
                (entries) => {
                    for (const e of entries)
                        if (e.isIntersecting)
                            setScrolled(
                                rows.indexOf(e.target as HTMLDetailsElement),
                            );
                },
                { rootMargin: '-55% 0px -40% 0px' },
            );
            rows.forEach((r) => io!.observe(r));
        };
        sync();
        wide.addEventListener('change', sync);
        return () => {
            wide.removeEventListener('change', sync);
            io?.disconnect();
        };
    }, []);

    return (
        <Section id="lp-s-scn">
            <SectionHead
                id="lp-s-scn"
                title={
                    <>
                        <span
                            aria-hidden="true"
                            className="font-normal text-lp-mag"
                        >
                            ${' '}
                        </span>
                        webhooktrap scenarios
                    </>
                }
            >
                Every way a webhook can arrive. Nine scenarios, always run in
                this order, each one taken from what the provider's own docs say
                it does.
            </SectionHead>
            <div className="lp-scn-stage">
                <DeliveryLane
                    name={scenarios[active].name}
                    className="lp-lane-sticky mb-6"
                />
                <div
                    ref={listRef}
                    data-motion={motion}
                    onMouseLeave={() => setPointed(null)}
                    className="lp-scn border-t border-lp-rule"
                >
                    {scenarios.map((s, i) => (
                        <details
                            key={s.name}
                            open={i === 1}
                            data-active={i === active || undefined}
                            onPointerEnter={() => setPointed(i)}
                            onFocus={() => setPointed(i)}
                            onClick={() => setPointed(i)}
                            className="relative border-b border-lp-rule-soft"
                        >
                            <summary className="grid cursor-pointer grid-cols-[22px_minmax(0,1fr)_20px] items-baseline gap-x-2.5 gap-y-1 px-1 py-4 text-sm hover:bg-lp-raise focus-visible:outline-offset-[-2px] sm:grid-cols-[28px_200px_160px_minmax(0,1fr)_24px] sm:gap-4">
                                <svg
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    aria-hidden="true"
                                    style={nth(i)}
                                    className="lp-glyph translate-y-0.5 text-lp-mag"
                                >
                                    <path
                                        d={s.glyph}
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeLinecap="square"
                                    />
                                </svg>
                                <span className="font-bold">{s.name}</span>
                                <span className="col-start-2 text-[12.5px] whitespace-nowrap text-lp-dim sm:col-start-auto">
                                    {s.deliveries}
                                </span>
                                <span className="col-start-2 text-lp-muted sm:col-start-auto">
                                    {s.what}
                                </span>
                                <span
                                    aria-hidden="true"
                                    className="lp-chevron col-start-3 row-start-1 text-right text-lp-dim sm:col-start-auto sm:row-start-auto"
                                >
                                    ›
                                </span>
                            </summary>
                            <div className="lp-scn-body pr-1 pb-[22px] pl-9 text-[13.5px] sm:pl-[274px]">
                                {s.note && <p className="mb-1.5">{s.note}</p>}
                                <p className="mb-1.5">{s.why}</p>
                                {s.cite && (
                                    <cite className="block text-lp-dim not-italic">
                                        {s.cite}
                                    </cite>
                                )}
                                <p className="text-[12.5px] text-lp-dim">
                                    Needs the signing secret:{' '}
                                    {s.needsSecret ? 'yes' : 'no'}
                                </p>
                            </div>
                        </details>
                    ))}
                </div>
            </div>
            <p className="mt-5 text-[13px] text-lp-dim">
                Sequence packs and test commands: later.
            </p>
        </Section>
    );
}

/* --------------------------------------------------- traffic, secrets, JSON */

/**
 * One pipeline step. Decorative spans draw its node on the rail, the rail
 * segment down to the next step and the dot that travels it.
 */
function Step({
    i,
    last = false,
    children,
}: {
    i: number;
    last?: boolean;
    children: ReactNode;
}) {
    return (
        <span className="lp-step relative block" style={nth(i)}>
            <span aria-hidden="true" className="lp-node" />
            {!last && (
                <>
                    <span aria-hidden="true" className="lp-seg" />
                    <span aria-hidden="true" className="lp-seg-dot" />
                </>
            )}
            {children}
        </span>
    );
}

/**
 * Motion: when the pane reaches the viewport, Capture → Scrub → Commit → CI
 * light up in order and a magenta dot runs the rail between them.
 */
export function RealTraffic() {
    const pipeRef = useRef<HTMLDivElement>(null);
    const motion = useReveal(pipeRef, 0.35);

    return (
        <Section id="lp-s-traffic" className={cn(split, 'items-start')}>
            <div>
                <h2 id="lp-s-traffic" className={h2}>
                    Real traffic, not fixtures you wrote
                </h2>
                <p className={cn(lede, 'mt-5')}>
                    Capture what Stripe actually sent, scrub the personal data,
                    commit it, and let CI replay it on every push.
                </p>
            </div>
            <div ref={pipeRef} data-motion={motion} className="lp-pipe min-w-0">
                <Pane
                    label="capture → scrub → commit → CI"
                    preClassName="pl-10 sm:pl-11"
                >
                    <Step i={0}>
                        <Dim>
                            # 1 Capture: point Stripe test mode at your URL
                        </Dim>
                        {'\nhttps://webhooktrap.dev/c/Br7nDLk2Qm9x'}
                    </Step>
                    <Step i={1}>
                        <Dim>
                            # 2 Scrub: personal data is replaced before it is
                            saved
                        </Dim>
                        {'\ncustomer_email      '}
                        <span className="text-lp-red">
                            jonah.reyes@example.com
                        </span>
                        {' → '}
                        <span className="text-lp-green">
                            customer1@example.test
                        </span>
                        {'\ncustomer_name       '}
                        <span className="text-lp-red">Jonah Reyes</span>
                        {' → '}
                        <span className="text-lp-green">Test Customer 1</span>
                        {'\nhosted_invoice_url  → '}
                        <span className="text-lp-green">
                            https://invoice.stripe.test/redacted
                        </span>
                    </Step>
                    <Step i={2}>
                        <Dim># 3 Commit</Dim>
                        {'\n'}
                        <Ps />
                        {
                            ' git add webhooktrap/fixtures/stripe/invoice.paid.json'
                        }
                    </Step>
                    <Step i={3} last>
                        <Dim># 4 CI: fail the build on High and above</Dim>
                        {'\n'}
                        <Ps />
                        {' webhooktrap test --fail-on high'}
                    </Step>
                </Pane>
            </div>
        </Section>
    );
}

const secretFacts = [
    'Sends deliveries only to targets you allow',
    'Keeps signing secrets on your machine (we see names, never values)',
    'Probe values stay on your machine. Only small counts and codes reach the server.',
    'Open source (AGPL-3.0). Run your own with docker compose up',
];

export function Secrets() {
    return (
        <Section id="lp-s-sec" className={cn(split, 'items-start')}>
            <div>
                <h2 id="lp-s-sec" className={h2}>
                    Secrets stay on your machine
                </h2>
                <ul className="mt-6 text-[14.5px]">
                    {secretFacts.map((t) => (
                        <li
                            key={t}
                            className="grid grid-cols-[24px_minmax(0,1fr)] gap-2 border-t border-lp-rule-soft py-3"
                        >
                            <span
                                aria-hidden="true"
                                className="font-bold text-lp-green"
                            >
                                ✓
                            </span>
                            <span>{t}</span>
                        </li>
                    ))}
                </ul>
                <p className="border-t border-lp-rule-soft pt-3 pl-8 text-[13px] text-lp-muted">
                    Free to run yourself. If it saves you a night,{' '}
                    <SupportLink className="text-lp-muted underline-offset-4 hover:text-lp-ink hover:underline" />
                </p>
            </div>
            {/*
             * ILLUSTRATIVE: `secrets list` output shape is a CLI proposal (A04);
             * only `secrets set` exists in the RD.
             */}
            <Pane label="agent · maya-mbp">
                <Ps />
                {' webhooktrap secrets list\n'}
                <Dim>{'provider    name                       value'}</Dim>
                {'\nStripe      STRIPE_WEBHOOK_SECRET      '}
                <span className="text-lp-green">set</span>
                {'      '}
                <Dim>stays on this machine</Dim>
                {'\nGitHub      GITHUB_WEBHOOK_SECRET      '}
                <span className="text-lp-green">set</span>
                {'\nShopify     SHOPIFY_API_SECRET         '}
                <span className="text-lp-green">set</span>
                {'\nParcelbird  PARCELBIRD_WEBHOOK_SECRET  '}
                <span className="text-lp-amber">not set</span>
                {'\n            '}
                <Dim>→ webhooktrap secrets set parcelbird</Dim>
                {'\n\n'}
                <Ps />
                {' docker compose up '}
                <Dim># or run your own instance</Dim>
            </Pane>
        </Section>
    );
}

const Key = ({ k }: { k: string }) => (
    <span className="text-lp-muted">"{k}":</span>
);

export function CodingAgent() {
    return (
        <Section id="lp-s-agent" className={cn(split, 'items-start')}>
            <div>
                <h2 id="lp-s-agent" className={h2}>
                    Works with your coding agent
                </h2>
                <p className={cn(lede, 'mt-5')}>
                    Findings come out as JSON: the type, the deliveries that
                    prove it, and the fix in words your agent can apply and
                    rerun.
                </p>
            </div>
            <Pane
                label="findings[] · Run #3"
                preProps={{
                    tabIndex: 0,
                    'aria-label': 'Example finding as JSON',
                }}
            >
                {'{\n  '}
                <Key k="id" />
                {' "F2",\n  '}
                <Key k="type" />
                {' "duplicate_changed_state",\n  '}
                <Key k="severity" />{' '}
                <span className="text-lp-orange">"high"</span>
                {',\n  '}
                <Key k="title" />
                {' "Duplicate delivery created a second order",\n  '}
                <Key k="evidence" />
                {' ["D4", "D5", "D10", "D11"],\n  '}
                <Key k="fix" />
                {' { '}
                <Key k="text" />{' '}
                <span className="text-lp-green">
                    "Record event.id before creating the order and return 200
                    early when it is already recorded. After the fix, D5 and D11
                    should leave orders.count at 1."
                </span>
                {' }\n}'}
            </Pane>
        </Section>
    );
}

/* --------------------------------------------------------------- providers */

const providers = [
    { name: 'Stripe', Logo: StripeLogo },
    { name: 'GitHub', Logo: GitHubLogo },
    { name: 'Shopify', Logo: ShopifyLogo },
    // No mark for Standard Webhooks in svgl: name only.
    { name: 'Standard Webhooks', Logo: null },
];

export function Providers() {
    return (
        <Section id="lp-s-prov">
            <SectionHead id="lp-s-prov" title="Providers" />
            <ul className="flex flex-wrap gap-x-11 gap-y-4 text-[22px] font-bold tracking-[-0.02em]">
                {providers.map(({ name, Logo }) => (
                    <li key={name} className="flex items-center gap-3">
                        {Logo && <Logo className="size-6 shrink-0" />}
                        {name}
                    </li>
                ))}
            </ul>
            <p className="mt-[18px] text-[13.5px] text-lp-dim">
                Coming: Twilio, Slack
            </p>
        </Section>
    );
}

/* ------------------------------------------------------------ CTA, footer */

export const FooterCta = forwardRef<HTMLElement>(function FooterCta(_, ref) {
    return (
        <section
            ref={ref}
            aria-labelledby="lp-s-final"
            className={cn(
                wrap,
                'mt-32 border-t border-lp-rule pt-20 pb-[72px]',
            )}
        >
            <h2
                id="lp-s-final"
                className={cn(
                    h2,
                    'mb-9 max-w-[18ch] text-[clamp(34px,5vw,68px)] leading-[1.1]',
                )}
            >
                Prove your first handler in 5 minutes.
            </h2>
            <div className="flex flex-wrap items-center gap-4">
                <GetUrlButton className="max-sm:w-full" />
                <CopyCommand className="flex-[0_1_420px] max-sm:flex-auto" />
            </div>
        </section>
    );
});

const footerLinks = [
    ['Docs', 'https://docs.webhooktrap.dev'],
    ['GitHub', 'https://github.com/jiordiviera/webhooktrap'],
    ['Self-host', 'https://github.com/jiordiviera/webhooktrap#self-hosting'],
    // Hosted on OpenStatus, reached through a Cloudflare redirect: it stays up when the VPS is down.
    ['Status', 'https://status.webhooktrap.dev'],
] as const;

const legalLinks = [
    { label: 'Privacy', href: privacy().url },
    { label: 'Terms', href: terms().url },
];

export function LandingFooter() {
    return (
        <footer className={wrap}>
            <nav
                aria-label="Footer"
                className="flex flex-wrap gap-6 border-t border-lp-rule-soft pt-6 pb-24 text-[13px] sm:pb-16"
            >
                {footerLinks.map(([label, href]) => (
                    <a
                        key={label}
                        href={href}
                        rel="noopener"
                        className="text-lp-muted no-underline hover:text-lp-ink"
                    >
                        {label}
                    </a>
                ))}
                {legalLinks.map(({ label, href }) => (
                    <Link
                        key={label}
                        href={href}
                        className="text-lp-muted no-underline hover:text-lp-ink"
                    >
                        {label}
                    </Link>
                ))}
                <SupportLink className="text-lp-muted no-underline hover:text-lp-ink sm:ml-auto" />
            </nav>
        </footer>
    );
}
