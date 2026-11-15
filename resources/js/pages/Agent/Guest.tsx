import { PairPanel } from '@/components/agent/pair-panel';
import { AgentDetailView } from '@/components/agent/agent-detail';
import { PageHeading } from '@/components/identity/bare-shell';
import { AppShell } from '@/components/shell/app-shell';
import type { AgentGuestProps } from '@/types';

const LIVE = ['agent'];

/** S-06 · Agent for a guest project (`/p/{key}/agent`): pair, or the agent. */
export default function Guest({ project, agent }: AgentGuestProps) {
    return (
        <AppShell title={`Agent · ${project.name}`}>
            <div className="tt-page tt-page-mid">
                <PageHeading>Agent</PageHeading>
                {agent && agent.state === 'connected' ? (
                    <AgentDetailView agent={agent} canReplace={false} />
                ) : (
                    <section className="tt-sec" aria-labelledby="h-pair">
                        <h2 id="h-pair">Pair an agent</h2>
                        <PairPanel agent={agent} reloadOnly={LIVE} />
                    </section>
                )}
            </div>
        </AppShell>
    );
}
