import { Link, router } from '@inertiajs/react';
import * as Menu from '@radix-ui/react-dropdown-menu';
import type { ReactNode } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import CaptureStream from '@/actions/App/Modules/Capture/Http/Controllers/CaptureStreamController';
import RunPage from '@/actions/App/Modules/Proving/Http/Controllers/RunPageController';
import { Note } from '@/components/identity/form';
import {
    bytes,
    captureCopy,
    clockMs,
    seqLabel,
    shapeCopy,
    signatureCopy,
} from '@/lib/capture/format';
import type { CaptureDetail, DecodedFacts } from '@/types/capture';
import { EscText, ProviderMark, SignatureWord } from './bits';

/*
 * S-04 · Delivery detail, as the S-03 pane or the full page. Every capture
 * value is untrusted: rendered as text nodes only, with the server's
 * `\u{XXXX}` escapes styled apart when the detail says something was
 * escaped (A09 S3-16). Prove arrives with Runs; Decoded with Packs.
 */

export type DetailTab = 'verdict' | 'raw' | 'decoded';

const TABS: { id: DetailTab; label: string }[] = [
    { id: 'verdict', label: 'Verdict' },
    { id: 'raw', label: 'Raw' },
    { id: 'decoded', label: 'Decoded' },
];

function decodeBase64(text: string): Uint8Array {
    const binary = atob(text);
    const out = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        out[i] = binary.charCodeAt(i);
    }

    return out;
}

/** 16 bytes per line: offset, hex, ASCII (`.` for non-printables). */
function hexLines(data: Uint8Array): string[] {
    const lines: string[] = [];

    for (let off = 0; off < data.length; off += 16) {
        const chunk = data.slice(off, off + 16);
        const hex = Array.from(chunk, (b) => b.toString(16).padStart(2, '0'))
            .join(' ')
            .padEnd(47, ' ');
        const ascii = Array.from(chunk, (b) =>
            b >= 0x20 && b < 0x7f ? String.fromCharCode(b) : '.',
        ).join('');
        lines.push(`${off.toString(16).padStart(8, '0')}  ${hex}  ${ascii}`);
    }

    return lines;
}

function curlFor(capture: CaptureDetail, url: string | null): string {
    const skip = new Set(['host', 'content-length', 'connection']);
    const quote = (s: string) => `'${s.replace(/'/g, `'\\''`)}'`;
    const parts = [
        `curl -X ${capture.method}`,
        quote(url ?? capture.rawTarget),
    ];

    for (const [name, value] of capture.headers) {
        const lower = name.toLowerCase();

        if (
            skip.has(lower) ||
            capture.redactedHeaders.some((r) => r.toLowerCase() === lower)
        ) {
            continue;
        }
        parts.push(`-H ${quote(`${name}: ${value}`)}`);
    }

    if (capture.body?.encoding === 'utf8') {
        parts.push(`--data-binary ${quote(capture.body.text)}`);
    }

    return parts.join(' \\\n  ');
}

async function copy(text: string, done: string) {
    try {
        await navigator.clipboard.writeText(text);
        toast(done);
    } catch {
        // Clipboard unavailable: the values stay selectable text.
    }
}

function Body({
    capture,
    wrap,
    rawHref,
}: {
    capture: CaptureDetail;
    wrap: boolean;
    rawHref: string;
}) {
    const body = capture.body;

    if (!capture.bodyKept || !body) {
        const c = captureCopy.bodyNotKept(capture.bodySize);

        return (
            <Note>
                <p>
                    <strong className="tt-strong">{c.label}</strong>
                </p>
                <p>{c.helper}</p>
                <p className="tt-mono tt-break">SHA-256 {capture.bodySha256}</p>
            </Note>
        );
    }

    if (capture.bodySize === 0) {
        // COPY PROPOSAL (A07)
        return <p className="tt-muted">Empty body.</p>;
    }

    const lines =
        body.encoding === 'base64'
            ? hexLines(decodeBase64(body.text))
            : body.text.split('\n');
    const label =
        body.encoding === 'base64'
            ? captureCopy.binary(capture.contentType, capture.bodySize)
            : null;

    return (
        <div className="tt-col" style={{ gap: 8 }}>
            {label && (
                <p className="tt-hint">
                    {label.label}. {label.helper}
                </p>
            )}
            <pre
                className="tt-code"
                data-wrap={wrap && body.encoding === 'utf8' ? 'on' : 'off'}
                tabIndex={0}
                aria-label={`Raw body, ${capture.bodySize} bytes`}
            >
                {lines.map((line, i) => (
                    <span key={i} className="ln">
                        <span className="g" aria-hidden="true">
                            {i + 1}
                        </span>
                        <span className="t">
                            <EscText text={line} escaped={body.escaped} />
                        </span>
                    </span>
                ))}
            </pre>
            {body.truncated && (
                <p className="tt-hint">
                    {captureCopy.truncated(body.shownBytes, capture.bodySize)}{' '}
                    <a href={rawHref} download>
                        Download raw
                    </a>
                </p>
            )}
        </div>
    );
}

/** S-04 Decoded tab: the pack's facts, then the shape as typed paths. */
function Decoded({
    decoded,
    shape,
    escaped,
    groupHref,
}: {
    decoded: DecodedFacts;
    shape?: [string, string][];
    escaped: boolean;
    groupHref: (key: string) => string;
}) {
    const facts: [string, ReactNode][] = [
        [
            'Provider',
            <ProviderMark
                key="p"
                provider={decoded.provider}
                name={decoded.providerName}
            />,
        ],
        [
            'Event',
            decoded.eventType ? (
                <EscText text={decoded.eventType} escaped={escaped} />
            ) : (
                '—'
            ),
        ],
        [
            'Event id',
            decoded.providerEventId ? (
                <IdChip id={decoded.providerEventId} />
            ) : (
                '—'
            ),
        ],
        ['API version', decoded.apiVersion ?? '—'],
        [
            'Group',
            decoded.group ? (
                <Link href={groupHref(decoded.group.key)}>
                    {decoded.group.kind && (
                        <span className="tt-muted">{decoded.group.kind} </span>
                    )}
                    <EscText text={decoded.group.label} escaped={escaped} />
                </Link>
            ) : (
                'Ungrouped'
            ),
        ],
        ['Pack', decoded.pack],
    ];

    return (
        <>
            <section className="tt-sec" aria-labelledby="d-facts">
                <h3 id="d-facts">Decoded</h3>
                <dl className="tt-facts">
                    {facts.map(([k, v]) => (
                        <div key={k} className="tt-lr">
                            <dt className="k">{k}</dt>
                            <dd className="tt-mono tt-break">{v}</dd>
                        </div>
                    ))}
                    <div className="tt-lr">
                        <dt className="k">Object ids</dt>
                        <dd className="tt-idlist">
                            {decoded.objectIds.length === 0
                                ? '—'
                                : decoded.objectIds.map((id, i) => (
                                      <IdChip key={`${i}-${id}`} id={id} />
                                  ))}
                        </dd>
                    </div>
                </dl>
            </section>
            {shape && shape.length > 0 && (
                <section className="tt-sec" aria-labelledby="d-shape">
                    <h3 id="d-shape">Shape ({shape.length} paths)</h3>
                    <div
                        className="tt-scroll tt-shape"
                        tabIndex={0}
                        aria-labelledby="d-shape"
                    >
                        <table className="tt-keytbl">
                            <thead>
                                <tr>
                                    <th scope="col">Path</th>
                                    <th scope="col">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                {shape.map(([path, type], i) => (
                                    <tr key={`${i}-${path}`}>
                                        <th
                                            scope="row"
                                            className="tt-mono tt-break"
                                        >
                                            <EscText
                                                text={path}
                                                escaped={escaped}
                                            />
                                        </th>
                                        <td className="tt-mono">
                                            <span
                                                className={`tt-type is-${type}`}
                                            >
                                                {type}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}
        </>
    );
}

/** An id as a copyable chip (S-04 Decoded: "ids as copyable chips"). */
function IdChip({ id }: { id: string }) {
    return (
        <button
            type="button"
            className="tt-idchip"
            onClick={() => void copy(id, 'Id copied.')}
            aria-label={`Copy ${id}`}
        >
            <EscText text={id} escaped />
        </button>
    );
}

export function DeliveryDetail({
    capture,
    projectKey,
    captureUrl = null,
    tab,
    onTab,
    prev,
    next,
    prevHref,
    nextHref,
    headingLevel = 2,
}: {
    capture: CaptureDetail;
    projectKey: string;
    captureUrl?: string | null;
    tab: DetailTab;
    onTab: (tab: DetailTab) => void;
    prev: number | null;
    next: number | null;
    prevHref?: (seq: number) => string;
    nextHref?: (seq: number) => string;
    headingLevel?: 1 | 2;
}) {
    const [wrap, setWrap] = useState(false);
    const rawHref = CaptureStream.raw.url({ projectKey, n: capture.seq });
    const pageHref = (seq: number) =>
        CaptureStream.show.url({ projectKey, n: seq });
    // Group keys can contain `/` (grp_github_brindle/brindle-app): always a
    // query value, encoded by the URL builder, never a path segment.
    // S-03/S-04 Prove: the run draft for this delivery or its group.
    const proveHref = (query: Record<string, string>) =>
        RunPage.draft.url(projectKey, { query });
    const groupHref = (key: string) =>
        CaptureStream.index.url(projectKey, { query: { group: key } });
    const escaped = capture.hiddenCharacters;
    const Heading = headingLevel === 1 ? 'h1' : 'h2';
    const sig = signatureCopy[capture.signature.state];
    const facts = useMemo(
        () =>
            [
                ['Received', `${clockMs(capture.receivedAt)} UTC`],
                ['Size', bytes(capture.bodySize)],
                ['Content type', capture.contentType ?? '—'],
                ['Source IP', capture.sourceIp],
                [
                    'Public origin',
                    `${capture.publicOrigin} (${capture.publicOriginSource})`,
                ],
                [
                    'Header fidelity',
                    capture.headerFidelity === 'exact'
                        ? 'exact (as received)'
                        : 'reconstructed by PHP',
                ],
                ['Delivery id', capture.id],
                ['SHA-256', capture.bodySha256],
            ] as const,
        [capture],
    );

    // Keys (S-04): 1 2 3 tabs · [ ] group navigation · y copy body · w wrap.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                target?.closest('input, textarea, select, [role="menu"]')
            ) {
                return;
            }
            const byKey: Record<string, () => void> = {
                '1': () => onTab('verdict'),
                '2': () => onTab('raw'),
                '3': () => onTab('decoded'),
                p: () =>
                    capture.bodyKept &&
                    router.visit(
                        proveHref({
                            from: String(capture.seq).padStart(2, '0'),
                        }),
                    ),
                P: () =>
                    capture.groupKey &&
                    router.visit(proveHref({ group: capture.groupKey })),
                w: () => setWrap((v) => !v),
                y: () => {
                    if (capture.body?.encoding === 'utf8') {
                        void copy(capture.body.text, 'Body copied.');
                    }
                },
            };
            const run = byKey[event.key];

            if (run) {
                event.preventDefault();
                run();
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [capture, onTab]);

    return (
        <article
            className="tt-detail"
            aria-labelledby={`dlv-${capture.seq}-title`}
        >
            <header className="tt-detail-head">
                <p className="tt-detail-kicker">
                    <ProviderMark
                        provider={capture.provider}
                        name={capture.providerName}
                    />
                    <span className="tt-mono">{seqLabel(capture.seq)}</span>
                </p>
                <Heading
                    id={`dlv-${capture.seq}-title`}
                    className="tt-detail-title"
                    tabIndex={-1}
                >
                    <EscText
                        text={
                            capture.eventType ??
                            `${capture.method} ${capture.path}`
                        }
                        escaped={escaped}
                    />
                </Heading>
                <p className="tt-detail-meta tt-mono">
                    {seqLabel(capture.seq)} · {clockMs(capture.receivedAt)} UTC
                    · {bytes(capture.bodySize)} ·{' '}
                    <EscText
                        text={`${capture.method} ${capture.path}`}
                        escaped={escaped}
                    />
                </p>
                <div className="tt-detail-actions">
                    {capture.bodyKept && (
                        <Link
                            className="tt-btn tt-btn-sm tt-btn-primary"
                            href={proveHref({
                                from: String(capture.seq).padStart(2, '0'),
                            })}
                            aria-keyshortcuts="p"
                        >
                            Prove
                        </Link>
                    )}
                    {capture.groupKey && (
                        <Link
                            className="tt-btn tt-btn-sm"
                            href={proveHref({ group: capture.groupKey })}
                            aria-keyshortcuts="Shift+P"
                        >
                            {/* COPY PROPOSAL (A07) */}
                            Prove group
                        </Link>
                    )}
                    <Menu.Root modal={false}>
                        <Menu.Trigger asChild>
                            <button
                                type="button"
                                className="tt-btn tt-btn-sm"
                                aria-label="Delivery actions"
                            >
                                ⋯
                            </button>
                        </Menu.Trigger>
                        <Menu.Portal>
                            <Menu.Content
                                align="end"
                                sideOffset={6}
                                className="tt tt-menu"
                            >
                                {capture.body?.encoding === 'utf8' && (
                                    <Menu.Item
                                        className="tt-menu-item"
                                        onSelect={() =>
                                            void copy(
                                                capture.body?.text ?? '',
                                                'Body copied.',
                                            )
                                        }
                                    >
                                        Copy body
                                    </Menu.Item>
                                )}
                                <Menu.Item
                                    className="tt-menu-item"
                                    onSelect={() =>
                                        void copy(
                                            curlFor(capture, captureUrl),
                                            'curl command copied.',
                                        )
                                    }
                                >
                                    Copy as curl
                                </Menu.Item>
                                {capture.bodyKept && (
                                    <Menu.Item asChild>
                                        <a
                                            className="tt-menu-item"
                                            href={rawHref}
                                            download
                                        >
                                            Download raw
                                        </a>
                                    </Menu.Item>
                                )}
                                <Menu.Item
                                    className="tt-menu-item"
                                    onSelect={() =>
                                        void copy(
                                            capture.id,
                                            'Delivery id copied.',
                                        )
                                    }
                                >
                                    Copy delivery id
                                </Menu.Item>
                            </Menu.Content>
                        </Menu.Portal>
                    </Menu.Root>
                </div>
            </header>

            <div className="tt-verdictlines">
                <SignatureWord signature={capture.signature} />
                <span className="tt-muted">
                    {capture.shapeVerdict
                        ? shapeCopy[capture.shapeVerdict]
                        : shapeCopy.none}
                </span>
                <span className="tt-muted">
                    {capture.groupKey ? (
                        <>
                            in{' '}
                            <Link
                                href={groupHref(capture.groupKey)}
                                className="tt-mono"
                            >
                                <EscText
                                    text={
                                        capture.groupLabel ?? capture.groupKey
                                    }
                                    escaped={escaped}
                                />
                            </Link>
                        </>
                    ) : (
                        'Ungrouped'
                    )}
                </span>
            </div>

            {capture.hiddenCharacters && (
                <Note glyph="info" className="tt-hiddenwarn" role="status">
                    <p>{captureCopy.hidden}</p>
                </Note>
            )}

            <div role="tablist" aria-label="Delivery views" className="tt-tabs">
                {TABS.map((t) => (
                    <button
                        key={t.id}
                        type="button"
                        role="tab"
                        id={`dtab-${t.id}`}
                        aria-controls={`dpanel-${t.id}`}
                        aria-selected={tab === t.id}
                        tabIndex={tab === t.id ? 0 : -1}
                        className="tt-tab"
                        onClick={() => onTab(t.id)}
                        onKeyDown={(event) => {
                            const i = TABS.findIndex((x) => x.id === tab);
                            const step =
                                event.key === 'ArrowRight'
                                    ? 1
                                    : event.key === 'ArrowLeft'
                                      ? -1
                                      : 0;

                            if (step) {
                                event.preventDefault();
                                const nextTab =
                                    TABS[
                                        (i + step + TABS.length) % TABS.length
                                    ];
                                onTab(nextTab.id);
                                window.requestAnimationFrame(() =>
                                    document
                                        .getElementById(`dtab-${nextTab.id}`)
                                        ?.focus(),
                                );
                            }
                        }}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <div
                role="tabpanel"
                id={`dpanel-${tab}`}
                aria-labelledby={`dtab-${tab}`}
                className="tt-tabpanel"
            >
                {tab === 'verdict' && (
                    <>
                        <section className="tt-sec" aria-labelledby="v-sig">
                            <h3 id="v-sig">Signature</h3>
                            <div className="tt-lr">
                                <span className="k">State</span>
                                <SignatureWord signature={capture.signature} />
                                <span />
                            </div>
                            {sig.helper && <p>{sig.helper}</p>}
                            {capture.signatureChecks && (
                                <ul className="tt-checks">
                                    {capture.signatureChecks.map((c) => (
                                        <li key={c.check}>
                                            <span aria-hidden="true">
                                                {c.passed ? '✓' : '✗'}
                                            </span>{' '}
                                            {c.check}:{' '}
                                            {c.passed ? 'passed' : 'failed'}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        <section className="tt-sec" aria-labelledby="v-facts">
                            <h3 id="v-facts">Request</h3>
                            <dl className="tt-facts">
                                {facts.map(([k, v]) => (
                                    <div key={k} className="tt-lr">
                                        <dt className="k">{k}</dt>
                                        <dd className="tt-mono tt-break">
                                            <EscText
                                                text={v}
                                                escaped={escaped}
                                            />
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    </>
                )}

                {tab === 'raw' && (
                    <>
                        <section className="tt-sec" aria-labelledby="r-line">
                            <h3 id="r-line">Request line</h3>
                            <p className="tt-mono tt-break tt-reqline">
                                <EscText
                                    text={`${capture.method} ${capture.rawTarget}`}
                                    escaped={escaped}
                                />
                            </p>
                        </section>
                        <section className="tt-sec" aria-labelledby="r-headers">
                            <h3 id="r-headers">
                                Headers ({capture.headers.length})
                            </h3>
                            <div
                                className="tt-scroll"
                                tabIndex={0}
                                aria-labelledby="r-headers"
                            >
                                <table className="tt-keytbl tt-hdrtbl">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Name</th>
                                            <th scope="col">Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {capture.headers.map(
                                            ([name, value], i) => (
                                                <tr key={i}>
                                                    <td className="tt-num">
                                                        {i + 1}
                                                    </td>
                                                    <th
                                                        scope="row"
                                                        className="tt-mono"
                                                    >
                                                        <EscText
                                                            text={name}
                                                            escaped={escaped}
                                                        />
                                                    </th>
                                                    <td className="tt-mono tt-break">
                                                        <EscText
                                                            text={value}
                                                            escaped={escaped}
                                                        />
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        <section className="tt-sec" aria-labelledby="r-body">
                            <h3 id="r-body" className="tt-rowhead">
                                <span>Body</span>
                                <span className="tt-row">
                                    {capture.body?.encoding === 'utf8' && (
                                        <button
                                            type="button"
                                            className="tt-btn tt-btn-sm"
                                            aria-pressed={wrap}
                                            onClick={() => setWrap((v) => !v)}
                                        >
                                            Wrap
                                        </button>
                                    )}
                                    {capture.bodyKept && (
                                        <a
                                            className="tt-btn tt-btn-sm"
                                            href={rawHref}
                                            download
                                        >
                                            Download raw
                                        </a>
                                    )}
                                </span>
                            </h3>
                            <Body
                                capture={capture}
                                wrap={wrap}
                                rawHref={rawHref}
                            />
                        </section>
                    </>
                )}

                {tab === 'decoded' &&
                    (capture.decoded ? (
                        <Decoded
                            decoded={capture.decoded}
                            shape={capture.shape}
                            escaped={escaped}
                            groupHref={groupHref}
                        />
                    ) : (
                        <section className="tt-sec" aria-labelledby="d-later">
                            <h3 id="d-later">Decoded</h3>
                            <p>{captureCopy.decodedLater}</p>
                        </section>
                    ))}
            </div>

            <nav className="tt-prevnext" aria-label="Deliveries in this group">
                {prev !== null ? (
                    <Link href={(prevHref ?? pageHref)(prev)} preserveScroll>
                        ‹ {seqLabel(prev)} previous in group
                    </Link>
                ) : (
                    <span className="tt-muted">‹ previous in group</span>
                )}
                {next !== null ? (
                    <Link href={(nextHref ?? pageHref)(next)} preserveScroll>
                        next in group {seqLabel(next)} ›
                    </Link>
                ) : (
                    <span className="tt-muted">next in group ›</span>
                )}
            </nav>
        </article>
    );
}
