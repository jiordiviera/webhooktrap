import { Link } from '@inertiajs/react';
import AgentPage from '@/actions/App/Modules/Agent/Http/Controllers/AgentPageController';
import { AgentState } from '@/components/agent/agent-detail';
import { PageHeading } from '@/components/identity/bare-shell';
import { buttonClass } from '@/components/identity/form';
import { AppShell } from '@/components/shell/app-shell';
import { formatFull } from '@/lib/identity/format';
import type { AgentsIndexProps } from '@/types';

/** S-08 · Agents (`/agents`): the workspace's agents, live. */
export default function Index({ agents }: AgentsIndexProps) {
    return (
        <AppShell title="Agents">
            <div className="tt-page">
                <div className="tt-pagehead">
                    <PageHeading>Agents</PageHeading>
                    <Link
                        className={buttonClass({ variant: 'primary' })}
                        href={AgentPage.pair.url()}
                    >
                        Pair a new agent
                    </Link>
                </div>
                <section className="tt-sec" aria-labelledby="h-agents">
                    <h2 id="h-agents">
                        {agents.length}{' '}
                        {agents.length === 1 ? 'agent' : 'agents'}
                    </h2>
                    {agents.length === 0 ? (
                        <p>
                            No agent connected. The agent runs on your machine,
                            sends deliveries to localhost and keeps your signing
                            secrets there.
                        </p>
                    ) : (
                        <div
                            className="tt-scroll"
                            tabIndex={0}
                            aria-labelledby="h-agents"
                        >
                            <table className="tt-keytbl tt-runstbl">
                                <thead>
                                    <tr>
                                        <th scope="col">Agent</th>
                                        <th scope="col">State</th>
                                        <th scope="col">Version</th>
                                        <th scope="col">Machine</th>
                                        <th scope="col">Last seen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {agents.map((a) => (
                                        <tr key={a.id}>
                                            <th scope="row">
                                                <Link
                                                    href={AgentPage.show.url(
                                                        a.id,
                                                    )}
                                                >
                                                    {a.name}
                                                </Link>
                                            </th>
                                            <td>
                                                <AgentState agent={a} />
                                            </td>
                                            <td className="tt-mono">
                                                {a.version}
                                            </td>
                                            <td className="tt-mono">{a.os}</td>
                                            <td className="tt-mono">
                                                {a.lastSeenAt
                                                    ? formatFull(a.lastSeenAt)
                                                    : '—'}
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
