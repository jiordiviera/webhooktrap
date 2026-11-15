import { Link } from '@inertiajs/react';
import AgentPage from '@/actions/App/Modules/Agent/Http/Controllers/AgentPageController';
import { AgentState } from '@/components/agent/agent-detail';
import { PairPanel } from '@/components/agent/pair-panel';
import { PageHeading } from '@/components/identity/bare-shell';
import { AppShell } from '@/components/shell/app-shell';
import type { AgentsPairProps } from '@/types';

const LIVE = ['agents'];

/**
 * S-08 · Pair an agent (`/agents/pair[?replace=agt_…]`). With `replace`,
 * the agent being replaced and its key fingerprint show next to the new
 * one's, so you can tell them apart before you confirm in the terminal.
 */
export default function Pair({ agents, replace }: AgentsPairProps) {
    const old = replace ? agents.find((a) => a.id === replace) : undefined;
    const incoming = agents
        .filter((a) => a.id !== replace && a.state !== 'connected')
        .sort((a, b) => b.pairedAt.localeCompare(a.pairedAt))[0];

    return (
        <AppShell title="Pair an agent">
            <div className="tt-page tt-page-mid">
                <p className="tt-crumb">
                    <Link href={AgentPage.index.url()}>‹ Agents</Link>
                </p>
                <PageHeading>
                    {old ? `Replace ${old.name}` : 'Pair an agent'}
                </PageHeading>
                {old && (
                    <section className="tt-sec" aria-labelledby="h-replace">
                        <h2 id="h-replace">Replacing</h2>
                        <dl className="tt-facts">
                            <div className="tt-lr">
                                <dt className="k">{old.name}</dt>
                                <dd>
                                    <AgentState agent={old} />
                                </dd>
                            </div>
                            <div className="tt-lr">
                                <dt className="k">Old key</dt>
                                <dd className="tt-mono tt-break">
                                    {old.keyFingerprint}
                                </dd>
                            </div>
                            <div className="tt-lr">
                                <dt className="k">New key</dt>
                                <dd className="tt-mono tt-break">
                                    {incoming
                                        ? `${incoming.keyFingerprint} (${incoming.name})`
                                        : 'Appears when the new agent sends the code.'}
                                </dd>
                            </div>
                        </dl>
                        <p className="tt-hint">
                            {/* COPY PROPOSAL (A07) */}
                            The old agent stops taking runs once the new one is
                            confirmed in its terminal.
                        </p>
                    </section>
                )}
                <section className="tt-sec" aria-labelledby="h-pair">
                    <h2 id="h-pair">Pairing code</h2>
                    <PairPanel
                        agent={null}
                        agents={agents}
                        replace={replace}
                        reloadOnly={LIVE}
                    />
                </section>
            </div>
        </AppShell>
    );
}
