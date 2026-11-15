import { Link } from '@inertiajs/react';
import type { KeyboardEvent, ReactNode } from 'react';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { StripeLogo } from '@/components/brand/provider-logos';
import { EscText } from '@/components/capture/bits';
import {
    groupDigits,
    ms,
    provenanceHelper,
    provenanceLine,
    queuedCopy,
    reasonLine,
    runCopy,
    SEVERITY,
    statusWord,
} from '@/lib/run/format';
import type {
    FindingView,
    ProbeReading,
    RunDeliveryRow,
    RunHeader,
    ScenarioCell,
    Severity,
} from '@/types/run';

/*
 * S-11 run board pieces, shared by the example run (S-14) and, later, the
 * owner and share run pages. Every value from a delivery (headers, bodies)
 * is untrusted: text nodes only.
 */

export type Href = (ref: string | null) => string;

export function SeverityMark({
    severity,
    n,
    showNumber = true,
}: {
    severity: Severity;
    n?: number;
    showNumber?: boolean;
}) {
    const s = SEVERITY[severity];

    return (
        <span className={`tt-sev is-${s.tone}`}>
            <span aria-hidden="true">{s.glyph}</span> {s.word}
            {showNumber && n !== undefined && (
                <span className="tt-mono"> · F{n}</span>
            )}
        </span>
    );
}

/* ---------------------------------------------------------- verdict line */

export function VerdictLine({
    run,
    total,
    current,
    elapsedMs,
    replaying = false,
}: {
    run: RunHeader;
    total: number;
    current: RunDeliveryRow | undefined;
    elapsedMs: number;
    /** The example's replay is still playing (S-14). */
    replaying?: boolean;
}) {
    const counts = run.counts;
    const order: Severity[] = ['critical', 'high', 'medium', 'info'];
    const agent = run.agent?.name ?? run.provenance.agentName ?? '';
    const status = replaying ? 'running' : run.status;
    const exit = run.exitCode !== null && (
        <span className={`tt-exit${run.exitCode === 0 ? ' is-ok' : ''}`}>
            exit {run.exitCode}
        </span>
    );
    const sevs = (
        <p className="sevs">
            {order
                .filter((s) => counts[s] > 0)
                .map((s) => (
                    <span key={s}>
                        <SeverityMark severity={s} showNumber={false} />{' '}
                        <span className="tt-mono">{counts[s]}</span>
                    </span>
                ))}
        </p>
    );

    if (status === 'queued' || status === 'running') {
        const line =
            status === 'queued'
                ? run.reason === 'needs_confirmation'
                    ? queuedCopy.confirm(agent)
                    : run.reason === 'needs_reconfirm'
                      ? queuedCopy.reconfirm(agent)
                      : queuedCopy.waiting(agent)
                : current
                  ? runCopy.running(
                        current.d,
                        replaying ? total : (run.deliveryCount ?? null),
                        current.scenarioName ?? current.scenario,
                        `${(elapsedMs / 1000).toFixed(1)} s`,
                    )
                  : runCopy.preparing;

        return (
            <div className="tt-verdict" data-status={status}>
                <p className="word is-running">
                    <span className="tt-runpulse" aria-hidden="true" />
                    {line}
                </p>
                {status === 'running' && !replaying && sevs}
            </div>
        );
    }

    if (status === 'findings') {
        return (
            <div className="tt-verdict" data-status="findings" data-done>
                <p className="word">
                    {counts.findings}{' '}
                    {counts.findings === 1 ? 'finding' : 'findings'}
                </p>
                {sevs}
                {exit}
            </div>
        );
    }

    const reason = reasonLine(status, run.reason, agent);

    return (
        <div className="tt-verdict" data-status={status}>
            <p className="word">
                {status === 'couldnt_start'
                    ? "Couldn't start the run"
                    : (statusWord[status] ?? status)}
                {status === 'passed' && counts.info > 0 && (
                    <span className="tt-muted"> · {counts.info} Info</span>
                )}
            </p>
            {exit}
            {reason && status !== 'passed' && (
                <p className="reason">{reason}</p>
            )}
        </div>
    );
}

/* ---------------------------------------------------------- scenario strip */

export function ScenarioStrip({
    cells,
    revealed,
    href,
}: {
    cells: ScenarioCell[];
    revealed: number;
    href: Href;
}) {
    const [stop, setStop] = useState(0);
    const refs = useRef<(HTMLAnchorElement | null)[]>([]);

    const onKeyDown = (event: KeyboardEvent<HTMLUListElement>) => {
        const to =
            event.key === 'ArrowRight'
                ? stop + 1
                : event.key === 'ArrowLeft'
                  ? stop - 1
                  : event.key === 'Home'
                    ? 0
                    : event.key === 'End'
                      ? cells.length - 1
                      : null;

        if (to === null) {
            return;
        }
        event.preventDefault();
        const next = Math.max(0, Math.min(cells.length - 1, to));
        setStop(next);
        refs.current[next]?.focus();
    };

    return (
        <ul className="tt-strip" aria-label="Scenarios" onKeyDown={onKeyDown}>
            {cells.map((cell, i) => {
                const has = cell.deliveries.length > 0;
                const last = has ? Math.max(...cell.deliveries) : 0;
                const first = has ? Math.min(...cell.deliveries) : 0;
                const state =
                    cell.result !== null && revealed >= last
                        ? 'final'
                        : has && revealed >= first
                          ? 'running'
                          : 'waiting';
                const other =
                    cell.result === 'inconclusive'
                        ? '— Inconclusive'
                        : cell.result === 'unreachable'
                          ? 'Unreachable'
                          : cell.result === 'n/a'
                            ? 'n/a'
                            : cell.result === 'not_run'
                              ? 'Not run'
                              : null;
                const worst = cell.findings[0];
                const target = worst ? `F${worst.n}` : has ? `D${first}` : null;
                const resultWord =
                    state === 'waiting'
                        ? 'Waiting'
                        : state === 'running'
                          ? 'Running'
                          : worst && worst.severity !== 'info'
                            ? `F${worst.n} ${SEVERITY[worst.severity].word}`
                            : (other ?? 'Pass');
                const label = `${cell.name}: ${resultWord.toLowerCase()}.${cell.line ? ` ${cell.line}.` : ''}${cell.reason ? ` ${cell.reason}` : ''}`;

                return (
                    <li
                        key={cell.key}
                        className="tt-cell"
                        data-state={state}
                        data-tone={
                            state === 'final' &&
                            worst &&
                            worst.severity !== 'info'
                                ? SEVERITY[worst.severity].tone
                                : 'pass'
                        }
                    >
                        <Link
                            ref={(el: unknown) => {
                                refs.current[i] =
                                    el instanceof HTMLAnchorElement ? el : null;
                            }}
                            href={href(target)}
                            preserveScroll
                            preserveState
                            tabIndex={i === stop ? 0 : -1}
                            aria-label={label}
                            onFocus={() => setStop(i)}
                        >
                            <span className="l1">{cell.name}</span>
                            <span className="l2">
                                {state === 'waiting' && 'Waiting'}
                                {state === 'running' && '┃ Running'}
                                {state === 'final' &&
                                    (worst && worst.severity !== 'info' ? (
                                        <SeverityMark
                                            severity={worst.severity}
                                            n={worst.n}
                                        />
                                    ) : other ? (
                                        <span className="tt-muted">
                                            {other}
                                        </span>
                                    ) : (
                                        <span className="tt-pass">▬ Pass</span>
                                    ))}
                            </span>
                            <span className="l3 tt-mono">
                                {state === 'final'
                                    ? cell.reading || cell.reason || ' '
                                    : ' '}
                            </span>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

/* ---------------------------------------------------------- state reading */

export function StateReadout({
    delivery,
    probeName,
    note,
}: {
    delivery: RunDeliveryRow | undefined;
    probeName: string;
    note?: ReactNode;
}) {
    const reading: ProbeReading | undefined = delivery?.state?.readings[0];
    const value = reading?.display.after ?? '—';
    const flagged = reading?.flagged ?? false;

    return (
        <section
            className="tt-readout"
            aria-labelledby="h-readout"
            data-flag={flagged || undefined}
        >
            <h2 id="h-readout" className="cap">
                {delivery ? `State after D${delivery.d}` : 'State'} ·{' '}
                <span className="tt-mono">{probeName}</span>
            </h2>
            <p className="big tt-mono" key={`${delivery?.d}-${value}`}>
                {reading ? (
                    <>
                        <span className="before">
                            {reading.display.before ?? '—'}
                        </span>
                        <span className="arrow" aria-hidden="true">
                            →
                        </span>
                        <span className="tt-sr"> to </span>
                        <span className="after">{value}</span>
                    </>
                ) : (
                    '—'
                )}
            </p>
            <p className="line">
                {reading?.display.expected !== null &&
                reading?.display.expected !== undefined
                    ? `Expected ${reading.display.expected} · got ${value}`
                    : reading
                      ? `before ${reading.display.before} → after ${value}`
                      : delivery
                        ? runCopy.stateNotMeasured
                        : ' '}
            </p>
            {note}
        </section>
    );
}

/* ---------------------------------------------------------- deliveries table */

function LatencyBar({
    latency,
    budget,
    judged,
}: {
    latency: number | null;
    budget: number;
    judged: boolean;
}) {
    if (latency === null) {
        return <span className="tt-muted">—</span>;
    }
    const scale = budget * 1.25;
    const width = Math.min(100, (latency / scale) * 100);
    const over = judged && latency > budget;

    return (
        <span className="tt-lat" data-over={over || undefined}>
            <span className="bar" aria-hidden="true">
                <span
                    className="fill"
                    style={{ width: `${Math.max(1.5, width)}%` }}
                />
                <span
                    className="budget"
                    style={{ left: `${(budget / scale) * 100}%` }}
                />
            </span>
            <span className="tt-mono v">
                {ms(latency)}
                {over && (
                    <span className="overtxt">
                        {' '}
                        +{groupDigits(latency - budget)} over
                    </span>
                )}
            </span>
        </span>
    );
}

const resultWords: Record<string, string> = {
    inconclusive: '— Inconclusive',
    unreachable: 'Unreachable',
    sent_no_result: 'sent, no result',
    not_run: 'not run',
};

export function DeliveriesTable({
    rows,
    cells,
    findings,
    revealed,
    budget,
    selected,
    href,
    done,
}: {
    rows: RunDeliveryRow[];
    cells: ScenarioCell[];
    findings: FindingView[];
    revealed: number;
    budget: number;
    selected: number | null;
    href: Href;
    done: boolean;
}) {
    const severityOf = (n: number | null | undefined) =>
        findings.find((f) => f.n === n)?.severity;

    return (
        <div className="tt-scroll" tabIndex={0} aria-labelledby="h-deliveries">
            <table className="tt-runtbl">
                <caption className="tt-sr">
                    Deliveries of this run, grouped by scenario
                </caption>
                <thead>
                    <tr>
                        <th scope="col">D</th>
                        <th scope="col" className="num">
                            +ms
                        </th>
                        <th scope="col">Event</th>
                        <th scope="col">Status</th>
                        <th scope="col">
                            Latency ({Math.round(budget / 1000)} s budget)
                        </th>
                        <th scope="col">orders.count</th>
                        <th scope="col">Result</th>
                    </tr>
                </thead>
                {cells.map((cell) => (
                    <tbody key={cell.key}>
                        <tr className="grp">
                            <th scope="rowgroup" colSpan={7}>
                                {cell.name}
                                <span className="tt-muted tt-mono">
                                    {' '}
                                    · {cell.line}
                                </span>
                            </th>
                        </tr>
                        {rows
                            .filter((r) => r.scenario === cell.key)
                            .map((r) => {
                                const shown = r.d <= revealed;
                                const reading = r.state?.readings[0];
                                const sev = severityOf(r.finding);
                                const isNew =
                                    shown && r.d === revealed && !done;

                                return (
                                    <tr
                                        key={r.d}
                                        data-shown={shown || undefined}
                                        data-new={isNew || undefined}
                                        aria-selected={
                                            selected === r.d || undefined
                                        }
                                        aria-hidden={!shown || undefined}
                                    >
                                        <th scope="row" className="tt-mono">
                                            {shown ? (
                                                <Link
                                                    href={href(`D${r.d}`)}
                                                    preserveScroll
                                                    preserveState
                                                    aria-current={
                                                        selected === r.d
                                                            ? 'true'
                                                            : undefined
                                                    }
                                                >
                                                    D{r.d}
                                                </Link>
                                            ) : (
                                                `D${r.d}`
                                            )}
                                        </th>
                                        <td className="num tt-mono tt-muted">
                                            +{groupDigits(r.offsetMs)}
                                        </td>
                                        <td>
                                            <span className="ev">
                                                {r.eventType}
                                            </span>
                                            {r.mode === 'duplicate' && (
                                                <span className="tt-tag">
                                                    = D{r.d - 1}′
                                                </span>
                                            )}
                                            {r.mode === 'late' && (
                                                <span className="tt-tag">
                                                    t − 360 s
                                                </span>
                                            )}
                                            {r.tamper && (
                                                <span className="tt-tag is-warn">
                                                    body changed
                                                </span>
                                            )}
                                            {r.mode === 'retry' && (
                                                <span className="tt-tag">
                                                    retry
                                                </span>
                                            )}
                                        </td>
                                        <td className="tt-mono">
                                            {shown ? (r.status ?? '—') : ''}
                                        </td>
                                        <td>
                                            {shown && (
                                                <LatencyBar
                                                    latency={r.latencyMs}
                                                    budget={budget}
                                                    judged={
                                                        r.budgetJudged ?? true
                                                    }
                                                />
                                            )}
                                        </td>
                                        <td className="tt-mono">
                                            {shown &&
                                                (reading ? (
                                                    <span
                                                        className={
                                                            reading.flagged
                                                                ? 'tt-flagged'
                                                                : ''
                                                        }
                                                    >
                                                        {reading.display.before}{' '}
                                                        →{' '}
                                                        {reading.display.after}
                                                        {reading.flagged &&
                                                            reading.display
                                                                .expected !==
                                                                null &&
                                                            ` · expected ${reading.display.expected}`}
                                                    </span>
                                                ) : (
                                                    '—'
                                                ))}
                                        </td>
                                        <td className="res">
                                            {shown &&
                                                ((r.result === 'finding' ||
                                                    (r.result === null &&
                                                        r.finding)) &&
                                                sev ? (
                                                    <SeverityMark
                                                        severity={sev}
                                                        n={
                                                            r.finding ??
                                                            undefined
                                                        }
                                                        showNumber={done}
                                                    />
                                                ) : r.result === 'finding' ? (
                                                    <span className="tt-sev">
                                                        Finding
                                                    </span>
                                                ) : r.result === 'pass' ? (
                                                    <span className="tt-pass">
                                                        ▬ pass
                                                        {r.mode === 'late'
                                                            ? ' (rejected)'
                                                            : ''}
                                                    </span>
                                                ) : (
                                                    <span className="tt-muted">
                                                        {resultWords[
                                                            r.result ?? ''
                                                        ] ?? '—'}
                                                    </span>
                                                ))}
                                        </td>
                                    </tr>
                                );
                            })}
                    </tbody>
                ))}
            </table>
        </div>
    );
}

/* ---------------------------------------------------------- findings */

async function copyText(text: string, done: string) {
    try {
        await navigator.clipboard.writeText(text);
        toast(done);
    } catch {
        // Clipboard unavailable: the text stays selectable.
    }
}

export function FindingEntry({
    finding,
    open,
    onToggle,
    numbered,
    href,
    fresh,
}: {
    finding: FindingView;
    open: boolean;
    onToggle: () => void;
    numbered: boolean;
    href: Href;
    fresh: boolean;
}) {
    const f = finding;
    const info = f.severity === 'info';
    const titleId = `finding-${f.n}-title`;

    return (
        <li
            className="tt-finding"
            data-sev={SEVERITY[f.severity].tone}
            data-open={open || undefined}
            data-fresh={fresh || undefined}
        >
            <h3 className="head">
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={`finding-${f.n}-body`}
                    onClick={onToggle}
                    id={titleId}
                    tabIndex={-1}
                    className="toggle"
                >
                    <SeverityMark
                        severity={f.severity}
                        n={f.n}
                        showNumber={numbered}
                    />
                    <span className="t">{f.title}</span>
                </button>
            </h3>
            <p className="meta tt-mono">
                {f.levelLabel ?? `Level ${f.level}`} · evidence{' '}
                {f.evidence.map((e, i) => (
                    <span key={e.d}>
                        {i > 0 && ' '}
                        <Link
                            href={href(`D${e.d}`)}
                            preserveScroll
                            preserveState
                            className={`tt-chipref${e.role === 'offending' ? ' is-off' : ''}`}
                        >
                            D{e.d}
                        </Link>
                    </span>
                ))}
                {info && (
                    <span className="tt-muted">
                        {' '}
                        · {runCopy.infoNotCounted}
                    </span>
                )}
            </p>
            <div id={`finding-${f.n}-body`} hidden={!open} className="body">
                {f.caption && <p className="caption">{f.caption}</p>}
                <h4>What happened</h4>
                <p>{f.what}</p>
                <h4>Why it matters</h4>
                <p>{f.why}</p>
                {f.citation && (
                    <p className="cite">
                        <a
                            href={f.citation.url}
                            target="_blank"
                            rel="noreferrer noopener"
                        >
                            {f.citation.label}
                        </a>
                    </p>
                )}
                {f.likelyCause && (
                    <p className="tt-muted">
                        Likely cause: {f.likelyCause.text}
                    </p>
                )}
                <h4>Fix</h4>
                <p>{f.fix.text}</p>
                {f.fix.snippet && (
                    <div className="tt-fix">
                        {f.fix.framework && (
                            <p className="fw tt-mono">{f.fix.framework}</p>
                        )}
                        <pre tabIndex={0} aria-label={`Fix for F${f.n}`}>
                            {f.fix.snippet}
                        </pre>
                        <div className="tt-row">
                            <button
                                type="button"
                                className="tt-btn tt-btn-sm"
                                onClick={() =>
                                    void copyText(
                                        f.fix.snippet ?? '',
                                        'Fix copied.',
                                    )
                                }
                            >
                                Copy fix
                            </button>
                            <button
                                type="button"
                                className="tt-btn tt-btn-sm"
                                onClick={() =>
                                    void copyText(
                                        JSON.stringify(f, null, 2),
                                        'Finding copied as JSON.',
                                    )
                                }
                            >
                                Copy finding as JSON
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </li>
    );
}

/* ---------------------------------------------------------- inspector */

export function Inspector({
    row,
    budget,
    closeHref,
}: {
    row: RunDeliveryRow;
    budget: { ms: number; label: string };
    closeHref: string;
}) {
    const ins = row.inspector;

    return (
        <section className="tt-inspector" aria-labelledby="h-inspector">
            <header className="tt-rowhead">
                <h2 id="h-inspector" tabIndex={-1}>
                    Inspector · D{row.d}
                    <span className="tt-muted">
                        {' '}
                        · {row.scenarioName} · {row.eventType}
                    </span>
                </h2>
                <Link
                    href={closeHref}
                    preserveScroll
                    preserveState
                    className="tt-btn tt-btn-sm"
                >
                    Close
                </Link>
            </header>
            {ins && (
                <div className="tt-insgrid">
                    <div>
                        <h3>Request</h3>
                        <p className="tt-mono tt-break">
                            {ins.request.method} {ins.request.url}
                        </p>
                        <p className="tt-muted">
                            sent +{groupDigits(row.offsetMs)} ms ·{' '}
                            {row.signing.kind}
                            {ins.request.sizeBytes !== null &&
                                ` · ${groupDigits(ins.request.sizeBytes)} B`}
                        </p>
                        <table className="tt-keytbl tt-hdrtbl">
                            <tbody>
                                {ins.request.headers.map(([k, v], i) => (
                                    <tr key={i}>
                                        <th scope="row" className="tt-mono">
                                            <EscText text={k} />
                                        </th>
                                        <td className="tt-mono tt-break">
                                            {k.toLowerCase() ===
                                            'stripe-signature' ? (
                                                'signed on maya-mbp · value not stored'
                                            ) : (
                                                <EscText text={v} />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="tt-muted">
                            Body:{' '}
                            {row.tamper ? (
                                <span className="tt-mono">
                                    {row.tamper.path} {row.tamper.from} →{' '}
                                    {row.tamper.to}
                                </span>
                            ) : (
                                (ins.request.bodyNote ?? '—')
                            )}
                        </p>
                    </div>
                    <div>
                        <h3>Response</h3>
                        <p className="tt-mono">
                            {row.status ?? '—'} ·{' '}
                            {row.latencyMs !== null ? ms(row.latencyMs) : '—'}
                        </p>
                        <p className="tt-muted">
                            {row.latencyMs !== null &&
                                `${ms(row.latencyMs)} of the ${Math.round(budget.ms / 1000)} s budget · ${budget.label.split(' · ')[1] ?? ''}`}
                        </p>
                        <table className="tt-keytbl tt-hdrtbl">
                            <tbody>
                                {ins.response.headers.map(([k, v], i) => (
                                    <tr key={i}>
                                        <th scope="row" className="tt-mono">
                                            <EscText text={k} />
                                        </th>
                                        <td className="tt-mono tt-break">
                                            <EscText text={v} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <pre
                            className="tt-code"
                            data-wrap="on"
                            tabIndex={0}
                            aria-label="Response body"
                        >
                            <span className="ln">
                                <span className="t">{ins.response.body}</span>
                            </span>
                        </pre>
                    </div>
                </div>
            )}
        </section>
    );
}

export function ProviderBadge({ provider }: { provider: string }) {
    return (
        <span className="tt-prov">
            {provider === 'stripe' && <StripeLogo className="tt-g tt-g-16" />}
            <span>{provider === 'stripe' ? 'Stripe' : provider}</span>
        </span>
    );
}

export { provenanceHelper, provenanceLine };

export function useScenarioNames(
    rows: RunDeliveryRow[],
    cells: ScenarioCell[],
) {
    return useMemo(
        () =>
            rows.map((r) => ({
                ...r,
                scenarioName:
                    cells.find((c) => c.key === r.scenario)?.name ?? r.scenario,
            })),
        [rows, cells],
    );
}
