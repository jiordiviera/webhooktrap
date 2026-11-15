import { Link } from '@inertiajs/react';
import { index as showProject } from '@/actions/App/Modules/Capture/Http/Controllers/CaptureStreamController';
import { PageHeading } from '@/components/identity/bare-shell';
import { buttonClass, StatusBadge } from '@/components/identity/form';
import { AppShell, CaptureUrlChip } from '@/components/workspace/app-shell';
import { formatLastUsed } from '@/lib/identity/format';
import projects from '@/routes/projects';
import type { ProjectRow } from '@/types';

const NONE = '—';

function RunStatus({ status }: { status: string | null }) {
    if (!status) {
        return <span className="tt-muted">{NONE}</span>;
    }
    const tone =
        status === 'passed' ? 'pass' : status === 'failed' ? 'fail' : 'off';

    return (
        <span className="st">
            <StatusBadge tone={tone}>{status.toUpperCase()}</StatusBadge>
            <span>{status}</span>
        </span>
    );
}

/**
 * S-15 · Projects. Shown with 2+ live projects (the server redirects
 * otherwise). Deliveries, activity and run status arrive with the Capture
 * and Proving slices; until then they read "—".
 */
export default function Index({ projects: rows }: { projects: ProjectRow[] }) {
    return (
        <AppShell title="Projects">
            <div className="tt-page">
                <div className="tt-pagehead">
                    <PageHeading>Projects</PageHeading>
                    <Link
                        className={buttonClass({ variant: 'primary' })}
                        href={projects.create()}
                    >
                        New project
                    </Link>
                </div>

                <section className="tt-sec" aria-labelledby="h-projects">
                    <h2 id="h-projects">{rows.length} projects</h2>
                    <table className="tt-keytbl tt-projtbl max-md:hidden">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Capture URL</th>
                                <th scope="col">Deliveries</th>
                                <th scope="col">Last activity</th>
                                <th scope="col">Last run</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((p) => (
                                <tr key={p.key}>
                                    <th scope="row">
                                        <Link href={showProject(p.key)}>
                                            {p.name}
                                        </Link>
                                    </th>
                                    <td>
                                        <CaptureUrlChip url={p.captureUrl} />
                                    </td>
                                    <td className="tt-num">
                                        {p.deliveries ?? NONE}
                                    </td>
                                    <td>
                                        {p.lastActivityAt
                                            ? formatLastUsed(p.lastActivityAt)
                                            : NONE}
                                    </td>
                                    <td>
                                        <RunStatus status={p.lastRunStatus} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <ul className="tt-actlist md:hidden">
                        {rows.map((p) => (
                            <li key={p.key}>
                                <Link className="l1" href={showProject(p.key)}>
                                    {p.name}
                                </Link>
                                <CaptureUrlChip url={p.captureUrl} />
                                <span className="m">
                                    {p.deliveries ?? NONE} deliveries · last
                                    activity{' '}
                                    {p.lastActivityAt
                                        ? formatLastUsed(p.lastActivityAt)
                                        : NONE}{' '}
                                    · last run {p.lastRunStatus ?? NONE}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </AppShell>
    );
}
