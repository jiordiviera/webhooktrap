import { Link, router, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import CaptureStream from '@/actions/App/Modules/Capture/Http/Controllers/CaptureStreamController';
import RunPage from '@/actions/App/Modules/Proving/Http/Controllers/RunPageController';
import { PairPanel } from '@/components/agent/pair-panel';
import { ProviderMark } from '@/components/capture/bits';
import { PageHeading } from '@/components/identity/bare-shell';
import { Button, ErrorSummary } from '@/components/identity/form';
import { AppShell } from '@/components/shell/app-shell';
import { bytes, clockMs, seqLabel } from '@/lib/capture/format';
import { refusalCopy, seconds } from '@/lib/run/format';
import type { RunsDraftProps } from '@/types';

/*
 * S-10 · Run draft (`/p/{key}/runs/new?from=` or `?group=`): what will be
 * proved (the selection), how (scenarios, fail on), against what (the
 * agent's declared target plus a path), then Run. The plan preview comes
 * from the server-side planner (EngineProcess) over every scenario; the page
 * sums the chosen ones. Without it (engine down, rate limit) a calm line.
 * Refusals known up front disable Run with their reason; the server's
 * `errors.run` is shown above the form.
 */

const AGENT_PROPS = ['agent', 'agents', 'targets', 'refusals'];

const FAIL_ON = [
    ['critical', 'Critical'],
    ['high', 'High and above'],
    ['medium', 'Medium and above'],
    ['never', 'Never (report only)'],
] as const;

export default function Draft({
    project,
    selection,
    preview,
    refusals,
    targets,
    agent,
    scenarios,
    failOn: defaultFailOn,
}: RunsDraftProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [chosen, setChosen] = useState<Set<string>>(
        () => new Set(scenarios.map((s) => s.key)),
    );
    const [target, setTarget] = useState(0);
    const provider = selection[0]?.provider ?? null;
    const [path, setPath] = useState(`/webhooks/${provider ?? ''}`);
    const [failOn, setFailOn] = useState(defaultFailOn);
    const [busy, setBusy] = useState(false);
    const planned =
        preview?.scenarios.filter(
            // PLAN-2: the engine always plans `once` (the baseline).
            (s) => s.name === 'once' || chosen.has(s.name),
        ) ?? [];
    const scenarioName = (key: string) =>
        scenarios.find((s) => s.key === key)?.name ?? key;
    const deliveries = planned.reduce((n, s) => n + s.deliveries, 0);
    const blocked = [
        ...refusals,
        ...(targets.length === 0 && agent?.state === 'connected'
            ? ['undeclared_target']
            : []),
    ];
    const ready =
        blocked.length === 0 &&
        targets.length > 0 &&
        chosen.size > 0 &&
        agent?.state === 'connected';
    const serverError = errors.run
        ? (refusalCopy[errors.run] ??
          `The run was refused (${errors.run.replace(/_/g, ' ')}).`)
        : null;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!ready || busy) {
            return;
        }
        const t = targets[target];
        setBusy(true);
        router.post(
            RunPage.store.url(project.key),
            {
                from: selection.map((c) => c.seq),
                scenarios: [...chosen],
                target: {
                    kind: 'agent',
                    agentId: t.agentId,
                    targetRef: `${t.targetRef}${path.startsWith('/') ? path : `/${path}`}`,
                },
                failOn,
            },
            { onFinish: () => setBusy(false) },
        );
    };

    return (
        <AppShell title={`Prove · ${project.name}`}>
            <div className="tt-page tt-draft">
                <p className="tt-crumb">
                    <Link href={CaptureStream.index.url(project.key)}>
                        ‹ Arrivals
                    </Link>
                </p>
                <PageHeading>Prove</PageHeading>
                <ErrorSummary message={serverError} />

                <form className="tt-draftgrid" onSubmit={submit} noValidate>
                    <section className="tt-sec" aria-labelledby="h-sel">
                        <h2 id="h-sel">
                            Selection · {selection.length}{' '}
                            {selection.length === 1 ? 'event' : 'events'}
                        </h2>
                        {selection.length === 0 ? (
                            <p className="tt-muted">
                                {refusalCopy.empty_selection}
                            </p>
                        ) : (
                            <ul className="tt-actlist">
                                {selection.map((c) => (
                                    <li key={c.seq} className="tt-selrow">
                                        <span className="tt-mono tt-muted">
                                            {seqLabel(c.seq)}
                                        </span>
                                        <ProviderMark
                                            provider={c.provider}
                                            name={c.providerName}
                                            showName={false}
                                        />
                                        <span className="l1">
                                            {c.providerName ?? 'Unknown'} ·{' '}
                                            {c.eventType ?? 'request'}
                                        </span>
                                        <span className="tt-mono tt-muted">
                                            {clockMs(c.receivedAt)} ·{' '}
                                            {c.bodyKept
                                                ? bytes(c.bodySize)
                                                : 'body not kept'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <fieldset className="tt-sec tt-scnset">
                        <legend className="tt-sec-legend">
                            Scenarios · {chosen.size} of {scenarios.length}
                        </legend>
                        <ul className="tt-checkgrid">
                            {scenarios.map((s) => (
                                <li key={s.key}>
                                    <label className="tt-checkrow">
                                        <input
                                            type="checkbox"
                                            className="tt-check"
                                            checked={chosen.has(s.key)}
                                            onChange={(e) =>
                                                setChosen((prev) => {
                                                    const next = new Set(prev);

                                                    if (e.target.checked) {
                                                        next.add(s.key);
                                                    } else {
                                                        next.delete(s.key);
                                                    }

                                                    return next;
                                                })
                                            }
                                        />
                                        {s.name}
                                    </label>
                                </li>
                            ))}
                        </ul>
                    </fieldset>

                    <section className="tt-sec" aria-labelledby="h-agent">
                        <h2 id="h-agent">Agent and target</h2>
                        <PairPanel agent={agent} reloadOnly={AGENT_PROPS} />
                        {targets.length > 0 && (
                            <div className="tt-twocol">
                                <div className="tt-field">
                                    <label
                                        className="tt-label"
                                        htmlFor="target"
                                    >
                                        Target
                                    </label>
                                    <select
                                        id="target"
                                        className="tt-input tt-select"
                                        value={target}
                                        onChange={(e) =>
                                            setTarget(Number(e.target.value))
                                        }
                                    >
                                        {targets.map((t, i) => (
                                            <option
                                                key={`${t.agentId}-${t.targetRef}`}
                                                value={i}
                                            >
                                                {t.display}
                                                {t.approvedOnAgent
                                                    ? ''
                                                    : ' (approve in terminal)'}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="tt-field">
                                    <label
                                        className="tt-label"
                                        htmlFor="target-path"
                                    >
                                        Path
                                    </label>
                                    <input
                                        id="target-path"
                                        className="tt-input"
                                        value={path}
                                        maxLength={512}
                                        aria-describedby="target-path-help"
                                        onChange={(e) =>
                                            setPath(e.target.value)
                                        }
                                    />
                                    <p
                                        className="tt-hint"
                                        id="target-path-help"
                                    >
                                        The handler's path on{' '}
                                        {targets[target]?.targetRef}.
                                    </p>
                                </div>
                            </div>
                        )}
                    </section>

                    <section className="tt-sec" aria-labelledby="h-plan">
                        <h2 id="h-plan">Plan</h2>
                        {/* COPY PROPOSAL (A07): plan preview lines. */}
                        {preview ? (
                            <>
                                <p>
                                    {deliveries}{' '}
                                    {deliveries === 1
                                        ? 'delivery'
                                        : 'deliveries'}{' '}
                                    · {selection.length}{' '}
                                    {selection.length === 1
                                        ? 'event'
                                        : 'events'}{' '}
                                    · {chosen.size} scenarios
                                </p>
                                <ul className="tt-actlist">
                                    {planned.map((s) => (
                                        <li key={s.name} className="tt-selrow">
                                            <span className="l1">
                                                {scenarioName(s.name)}
                                            </span>
                                            <span className="tt-mono tt-muted">
                                                {s.na === null
                                                    ? `${s.deliveries} ${s.deliveries === 1 ? 'delivery' : 'deliveries'}`
                                                    : `n/a · ${s.na.replace(/_/g, ' ')}`}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <p className="tt-muted">
                                    Budget {seconds(preview.budget.ms)} (
                                    {preview.budget.source.replace(/_/g, ' ')})
                                    {preview.needsSecret &&
                                        ' · valid signatures need the signing secret on the agent'}
                                </p>
                            </>
                        ) : (
                            <>
                                <p>
                                    The agent plans the run on your machine and
                                    reports every delivery as it goes.
                                </p>
                                <p className="tt-muted">
                                    {selection.length}{' '}
                                    {selection.length === 1
                                        ? 'event'
                                        : 'events'}{' '}
                                    · {chosen.size} scenarios
                                </p>
                            </>
                        )}
                        <div className="tt-field" style={{ maxWidth: 320 }}>
                            <label className="tt-label" htmlFor="fail-on">
                                Fail the run on
                            </label>
                            <select
                                id="fail-on"
                                className="tt-input tt-select"
                                value={failOn}
                                onChange={(e) =>
                                    setFailOn(e.target.value as typeof failOn)
                                }
                            >
                                {FAIL_ON.map(([v, l]) => (
                                    <option key={v} value={v}>
                                        {l}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </section>

                    <div className="tt-draftsubmit">
                        {blocked.length > 0 && (
                            <ul className="tt-reasons" id="run-blocked">
                                {blocked.map((code) => (
                                    <li key={code}>
                                        {refusalCopy[code] ?? code}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {blocked.length === 0 &&
                            agent?.state !== 'connected' && (
                                <p className="tt-reason" id="run-blocked">
                                    {refusalCopy.no_agent}
                                </p>
                            )}
                        <Button
                            type="submit"
                            variant="primary"
                            size="lg"
                            busy={busy}
                            aria-disabled={!ready || busy || undefined}
                            aria-describedby={ready ? undefined : 'run-blocked'}
                        >
                            {busy ? 'Starting…' : 'Run'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppShell>
    );
}
