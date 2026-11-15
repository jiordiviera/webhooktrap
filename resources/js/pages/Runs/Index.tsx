import { Link, router } from '@inertiajs/react';
import CaptureStream from '@/actions/App/Modules/Capture/Http/Controllers/CaptureStreamController';
import RunPage from '@/actions/App/Modules/Proving/Http/Controllers/RunPageController';
import { ProviderMark } from '@/components/capture/bits';
import { PageHeading } from '@/components/identity/bare-shell';
import { SeverityMark } from '@/components/run/run-board';
import { AppShell } from '@/components/shell/app-shell';
import { clock } from '@/lib/capture/format';
import { formatDay } from '@/lib/identity/format';
import { provenanceLine, seconds, statusWord } from '@/lib/run/format';
import type { RunRow, RunsIndexProps, Severity } from '@/types';

/*
 * S-05 · Runs (`/p/{key}/runs?source=&status=`): every run of the project,
 * newest first, live on `project.runs`. Status in words (glyph + word),
 * never colour alone.
 */

const SEVERITIES: Severity[] = ['critical', 'high', 'medium', 'info'];

function StatusCell({ run }: { run: RunRow }) {
    if (run.status === 'findings') {
        const worst = SEVERITIES.find((s) => s !== 'info' && run.counts[s] > 0);

        return (
            <span className="st">
                {worst && <SeverityMark severity={worst} showNumber={false} />}
                <span>
                    {run.counts.findings}{' '}
                    {run.counts.findings === 1 ? 'finding' : 'findings'}
                </span>
            </span>
        );
    }

    if (run.status === 'passed') {
        return (
            <span className="tt-pass">
                ▬ Passed
                {run.counts.info > 0 ? ` · ${run.counts.info} Info` : ''}
            </span>
        );
    }

    const live = run.status === 'queued' || run.status === 'running';

    return (
        <span
            className={live ? 'tt-live' : 'tt-muted'}
            data-status={live ? 'live' : undefined}
        >
            {live && <span className="dot" aria-hidden="true" />}
            {statusWord[run.status] ?? run.status}
            {run.reason && !live ? ` · ${run.reason.replace(/_/g, ' ')}` : ''}
        </span>
    );
}

function length(run: RunRow): string {
    if (!run.startedAt || !run.finishedAt) {
        return '—';
    }

    return seconds(
        new Date(run.finishedAt).getTime() - new Date(run.startedAt).getTime(),
    );
}

export default function Index({ project, runs, filters }: RunsIndexProps) {
    const filter = (patch: Partial<RunsIndexProps['filters']>) => {
        const next = { ...filters, ...patch };
        const query: Record<string, string> = {};

        if (next.source) {
            query.source = next.source;
        }

        if (next.status) {
            query.status = next.status;
        }
        router.get(
            RunPage.index.url(project.key, { query }),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppShell title={`Runs · ${project.name}`}>
            <div className="tt-page">
                <div className="tt-pagehead">
                    <PageHeading>Runs</PageHeading>
                    <div
                        className="tt-row"
                        style={{ gap: 12, flexWrap: 'wrap' }}
                    >
                        <label className="tt-label" htmlFor="f-source">
                            Source
                        </label>
                        <select
                            id="f-source"
                            className="tt-input tt-select tt-select-sm"
                            value={filters.source ?? ''}
                            onChange={(e) =>
                                filter({
                                    source: (e.target.value ||
                                        null) as RunsIndexProps['filters']['source'],
                                })
                            }
                        >
                            <option value="">All</option>
                            <option value="web">Web</option>
                            <option value="cli">CLI</option>
                            <option value="ci">CI</option>
                        </select>
                        <label className="tt-label" htmlFor="f-status">
                            Status
                        </label>
                        <select
                            id="f-status"
                            className="tt-input tt-select tt-select-sm"
                            value={filters.status ?? ''}
                            onChange={(e) =>
                                filter({
                                    status: (e.target.value ||
                                        null) as RunsIndexProps['filters']['status'],
                                })
                            }
                        >
                            <option value="">All</option>
                            {[
                                'couldnt_start',
                                'aborted',
                                'findings',
                                'inconclusive',
                                'passed',
                                'running',
                                'queued',
                            ].map((s) => (
                                <option key={s} value={s}>
                                    {statusWord[s]}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <section className="tt-sec" aria-labelledby="h-runs">
                    <h2 id="h-runs">
                        {runs.length} {runs.length === 1 ? 'run' : 'runs'}
                    </h2>
                    {runs.length === 0 ? (
                        <p>
                            {/* COPY PROPOSAL (A07) */}
                            No runs yet. Select deliveries in{' '}
                            <Link href={CaptureStream.index.url(project.key)}>
                                Arrivals
                            </Link>{' '}
                            and press Prove.
                        </p>
                    ) : (
                        <div
                            className="tt-scroll"
                            tabIndex={0}
                            aria-labelledby="h-runs"
                        >
                            <table className="tt-keytbl tt-runstbl">
                                <thead>
                                    <tr>
                                        <th scope="col">Run</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Source</th>
                                        <th scope="col">From</th>
                                        <th scope="col">Started</th>
                                        <th scope="col">Length</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {runs.map((run) => (
                                        <tr key={run.id}>
                                            <th scope="row">
                                                <Link
                                                    href={RunPage.show.url({
                                                        runId: run.id,
                                                    })}
                                                >
                                                    #{run.number}
                                                </Link>
                                            </th>
                                            <td>
                                                <StatusCell run={run} />
                                            </td>
                                            <td className="tt-muted">
                                                {provenanceLine(run.provenance)}
                                            </td>
                                            <td>
                                                <span
                                                    className="tt-row"
                                                    style={{ gap: 6 }}
                                                >
                                                    <ProviderMark
                                                        provider={run.provider}
                                                        name={run.providerName}
                                                        showName={false}
                                                    />
                                                    <span className="tt-mono">
                                                        {run.from.seqs
                                                            .map(
                                                                (s) =>
                                                                    `#${String(s).padStart(2, '0')}`,
                                                            )
                                                            .join(' ')}
                                                    </span>
                                                </span>
                                            </td>
                                            <td className="tt-mono">
                                                {run.startedAt
                                                    ? `${formatDay(run.startedAt)} ${clock(run.startedAt)}`
                                                    : run.queuedAt
                                                      ? `queued ${clock(run.queuedAt)}`
                                                      : '—'}
                                            </td>
                                            <td className="tt-mono">
                                                {length(run)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AppShell>
    );
}
