import { Link, router, usePage } from '@inertiajs/react';
import * as Tip from '@radix-ui/react-tooltip';
import { useEffect, useMemo, useRef, useState } from 'react';
import ExampleRun from '@/actions/App/Modules/Proving/Http/Controllers/ExampleRunController';
import RunPage from '@/actions/App/Modules/Proving/Http/Controllers/RunPageController';
import { store as startGuest } from '@/actions/App/Modules/Workspace/Http/Controllers/GuestWorkspaceController';
import { Glyph } from '@/components/identity/glyph';
import {
    DeliveriesTable,
    FindingEntry,
    Inspector,
    ProviderBadge,
    provenanceHelper,
    provenanceLine,
    ScenarioStrip,
    StateReadout,
    useScenarioNames,
    VerdictLine,
} from '@/components/run/run-board';
import { AppShell } from '@/components/shell/app-shell';
import { useReplay } from '@/hooks/use-replay';
import { useRunLive } from '@/hooks/use-run-live';
import { runCopy, seconds, statusWord } from '@/lib/run/format';
import projects from '@/routes/projects';
import type { RunShowProps } from '@/types';

/*
 * S-11 run board, served as S-14 · Example run (`/r/example`, `/D{n}`,
 * `/F{n}`). On first view the run replays: deliveries fill in over about
 * 9 s, strip cells light up, D5's orders.count flips 1 → 2, findings drop
 * in. Reduced motion and deep links show the final state. Selection lives
 * in the URL, so back works. The live region speaks twice: at the start
 * and the end of a replay.
 */
export default function Show({
    run,
    scenarios,
    timeline,
    findings,
    focus,
    access,
    example,
    live,
    project,
}: RunShowProps) {
    const { principal } = usePage().props;
    const isExample = access === 'example';
    // Real runs go live instead of replaying (agent connection notes).
    useRunLive(live?.channel ?? null);
    const rows = useScenarioNames(timeline.deliveries, scenarios);
    const total = rows.length;
    const replay = useReplay(
        rows.map((r) => r.offsetMs),
        isExample && focus === null,
    );
    // Real runs show every delivery they have (no replay): the replay only
    // drives the example.
    const revealed = isExample ? replay.revealed : total;
    const done = isExample ? replay.done : true;
    const href = (ref: string | null) =>
        isExample
            ? ref
                ? ExampleRun.show.url({ focus: ref })
                : ExampleRun.show.url()
            : ref
              ? RunPage.show.url({ runId: run.id, focus: ref })
              : RunPage.show.url({ runId: run.id });
    // Owner runs carry their project (users and guests): Runs, Run again.
    const projectKey = project?.key;
    const active = run.status === 'queued' || run.status === 'running';

    const focusedRow =
        focus?.kind === 'delivery' && !focus.missing
            ? rows.find((r) => r.d === focus.n)
            : undefined;
    const current = revealed > 0 ? rows[revealed - 1] : undefined;
    const flagged = rows.find((r) => r.state?.readings[0]?.flagged);
    const readoutRow =
        focusedRow ?? (done && !active ? (flagged ?? current) : current);

    // Findings drop in when their first offending delivery has run.
    const firstOffending = (n: number) =>
        Math.min(
            ...(findings
                .find((f) => f.n === n)
                ?.evidence.filter((e) => e.role === 'offending')
                .map((e) => e.d) ?? [0]),
        );
    const visibleFindings = findings.filter(
        (f) => !isExample || done || firstOffending(f.n) <= revealed,
    );
    const [open, setOpen] = useState<Set<number>>(
        () =>
            new Set([
                focus?.kind === 'finding' && !focus.missing ? focus.n : 1,
            ]),
    );

    // Deep links and in-page selection: final state, open, focus.
    const lastFocus = useRef<string | null>(null);

    useEffect(() => {
        const key = focus ? `${focus.kind}${focus.n}` : null;

        if (key === lastFocus.current) {
            return;
        }
        lastFocus.current = key;

        if (!focus || focus.missing) {
            return;
        }
        replay.finish();

        if (focus.kind === 'finding') {
            setOpen((o) => new Set(o).add(focus.n));
        }
        window.requestAnimationFrame(() => {
            const target =
                focus.kind === 'delivery'
                    ? document.getElementById('h-inspector')
                    : document.getElementById(`finding-${focus.n}-title`);
            target?.focus();
            target?.scrollIntoView({ block: 'nearest' });
        });
    }, [focus, replay]);

    // The live region: once at the start, once at the end.
    const [said, setSaid] = useState('');
    const wasDone = useRef(done);

    useEffect(() => {
        if (!done && wasDone.current) {
            setSaid(`Replaying Run #${run.number}.`);
        }

        if (done && !wasDone.current) {
            setSaid(
                runCopy.replayDone(
                    `${run.counts.findings} findings, exit ${run.exitCode}`,
                ),
            );
        }
        wasDone.current = done;
    }, [done, run]);

    // Owner: announce the status once when it changes (not per delivery).
    const lastStatus = useRef(run.status);

    useEffect(() => {
        if (isExample || lastStatus.current === run.status) {
            return;
        }
        lastStatus.current = run.status;
        setSaid(
            run.status === 'findings'
                ? `Run #${run.number} finished: ${run.counts.findings} findings, exit ${run.exitCode}.`
                : `Run #${run.number}: ${statusWord[run.status] ?? run.status}.`,
        );
    }, [isExample, run]);

    const fresh = useMemo(() => {
        const set = new Set<number>();

        if (!done) {
            visibleFindings.forEach((f) => {
                if (firstOffending(f.n) === revealed) {
                    set.add(f.n);
                }
            });
        }

        return set;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [revealed, done]);

    const cta =
        principal.kind === 'user' ? (
            <Link className="tt-btn tt-btn-primary" href={projects.index()}>
                Prove your own
            </Link>
        ) : (
            <button
                type="button"
                className="tt-btn tt-btn-primary"
                onClick={() => router.post(startGuest.url())}
            >
                {principal.kind === 'guest' ? 'Prove your own' : 'Get a URL'}
            </button>
        );

    return (
        <AppShell
            title={`${isExample ? 'Example run · ' : ''}Run #${run.number}`}
            wide
        >
            <Tip.Provider delayDuration={200}>
                <div
                    className="tt-run"
                    data-inspector={focusedRow ? 'on' : undefined}
                >
                    <header className="tt-run-head">
                        <p className="crumb">
                            {isExample && (
                                <>
                                    <span className="tt-badge is-off">
                                        {example?.label ?? 'Example run'}
                                    </span>{' '}
                                </>
                            )}
                            {run.project} ›{' '}
                            {projectKey ? (
                                <Link href={RunPage.index.url(projectKey)}>
                                    Runs
                                </Link>
                            ) : (
                                'Runs'
                            )}{' '}
                            › Run #{run.number}
                        </p>
                        <h1 tabIndex={-1}>
                            {run.title ?? `Run #${run.number}`}
                        </h1>
                        <p className="from tt-mono">
                            <ProviderBadge provider={run.provider} /> · From{' '}
                            {run.from.groupKey ? `${run.from.groupKey} · ` : ''}
                            {run.from.seqs
                                .map((s) => `#${String(s).padStart(2, '0')}`)
                                .join(' ')}{' '}
                            · {run.target.host}:{run.target.port}
                            {run.target.path}
                        </p>
                        <p className="prov">
                            {provenanceLine(run.provenance)}{' '}
                            <Tip.Root>
                                <Tip.Trigger asChild>
                                    <button
                                        type="button"
                                        className="tt-iconbtn tt-infobtn"
                                        aria-label={provenanceHelper}
                                    >
                                        <Glyph name="info" />
                                    </button>
                                </Tip.Trigger>
                                <Tip.Portal>
                                    <Tip.Content
                                        className="tt tt-tip"
                                        sideOffset={6}
                                    >
                                        {provenanceHelper}
                                    </Tip.Content>
                                </Tip.Portal>
                            </Tip.Root>
                        </p>
                        {example && <p className="helper">{example.helper}</p>}
                        <div className="actions">
                            {isExample ? (
                                <>
                                    <button
                                        type="button"
                                        className="tt-btn"
                                        onClick={replay.toggle}
                                        aria-pressed={
                                            replay.playing || undefined
                                        }
                                    >
                                        {done
                                            ? runCopy.replay
                                            : replay.playing
                                              ? runCopy.pause
                                              : runCopy.resume}
                                    </button>
                                    {!done && (
                                        <button
                                            type="button"
                                            className="tt-btn tt-btn-ghost"
                                            onClick={replay.finish}
                                        >
                                            {/* COPY PROPOSAL (A07) */}
                                            Skip to the end
                                        </button>
                                    )}
                                    {cta}
                                </>
                            ) : (
                                projectKey &&
                                !active && (
                                    <Link
                                        className="tt-btn tt-btn-primary"
                                        href={RunPage.draft.url(projectKey, {
                                            query: {
                                                from: run.from.seqs
                                                    .map((n) =>
                                                        String(n).padStart(
                                                            2,
                                                            '0',
                                                        ),
                                                    )
                                                    .join(','),
                                            },
                                        })}
                                    >
                                        Run again
                                    </Link>
                                )
                            )}
                        </div>
                    </header>

                    <section className="tt-run-verdict" aria-label="Verdict">
                        <VerdictLine
                            run={run}
                            total={total}
                            current={current}
                            elapsedMs={current?.offsetMs ?? 0}
                            replaying={isExample && !done}
                        />
                        <p className="meta tt-mono">
                            {scenarios.length} scenarios ·{' '}
                            {run.deliveryCount ?? total} deliveries ·{' '}
                            {run.durationMs !== null
                                ? seconds(run.durationMs)
                                : active
                                  ? 'in progress'
                                  : '—'}
                            {run.exitCode !== null &&
                                ` · CI exit ${run.exitCode}`}
                            {run.failOn && ` (--fail-on ${run.failOn})`}
                        </p>
                    </section>

                    <div className="tt-run-strip">
                        <ScenarioStrip
                            cells={scenarios}
                            revealed={revealed}
                            href={href}
                        />
                    </div>

                    <div className="tt-run-readout">
                        <StateReadout
                            delivery={readoutRow}
                            probeName={run.probeEntries[0]?.name ?? 'probe'}
                            note={
                                readoutRow?.d === 5 && done ? (
                                    <p className="caption">
                                        {runCopy.sameRequest}
                                    </p>
                                ) : undefined
                            }
                        />
                    </div>

                    <section
                        className="tt-run-findings"
                        aria-labelledby="h-findings"
                    >
                        <h2 id="h-findings">Findings</h2>
                        {focus?.kind === 'finding' && focus.missing && (
                            <p className="tt-note">
                                {runCopy.notInRun(
                                    'F',
                                    focus.n,
                                    findings.length,
                                )}
                            </p>
                        )}
                        {visibleFindings.length === 0 ? (
                            <p className="tt-muted">
                                {active || (isExample && !done)
                                    ? 'Findings appear here as the run goes.'
                                    : 'No findings.'}
                            </p>
                        ) : (
                            <ol className="tt-findings">
                                {visibleFindings.map((f) => (
                                    <FindingEntry
                                        key={f.n}
                                        finding={f}
                                        numbered={
                                            (isExample ? done : true) &&
                                            !f.provisional
                                        }
                                        open={open.has(f.n)}
                                        fresh={fresh.has(f.n)}
                                        href={href}
                                        onToggle={() =>
                                            setOpen((o) => {
                                                const next = new Set(o);

                                                if (next.has(f.n)) {
                                                    next.delete(f.n);
                                                } else {
                                                    next.add(f.n);
                                                }

                                                return next;
                                            })
                                        }
                                    />
                                ))}
                            </ol>
                        )}
                    </section>

                    {focusedRow && (
                        <div className="tt-run-inspector">
                            <Inspector
                                row={focusedRow}
                                budget={run.budget}
                                closeHref={href(null)}
                            />
                        </div>
                    )}

                    <section
                        className="tt-run-deliveries"
                        aria-labelledby="h-deliveries"
                    >
                        <h2 id="h-deliveries">Deliveries</h2>
                        {focus?.kind === 'delivery' && focus.missing && (
                            <p className="tt-note">
                                {runCopy.notInRun('D', focus.n, total)}
                            </p>
                        )}
                        <DeliveriesTable
                            rows={rows}
                            cells={scenarios}
                            findings={findings}
                            revealed={revealed}
                            budget={run.budget.ms}
                            selected={focusedRow?.d ?? null}
                            href={href}
                            done={done}
                        />
                    </section>

                    <p className="tt-sr" aria-live="polite">
                        {said}
                    </p>
                </div>
            </Tip.Provider>
        </AppShell>
    );
}
