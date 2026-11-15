import { Link } from '@inertiajs/react';
import AgentPage from '@/actions/App/Modules/Agent/Http/Controllers/AgentPageController';
import { AgentDetailView } from '@/components/agent/agent-detail';
import { PageHeading } from '@/components/identity/bare-shell';
import { AppShell } from '@/components/shell/app-shell';
import type { AgentsShowProps } from '@/types';

/** S-08 · Agent detail (`/agents/{id}`): facts, targets, secret names, revoke. */
export default function Show({ agent }: AgentsShowProps) {
    return (
        <AppShell title={`${agent.name} · Agents`}>
            <div className="tt-page tt-page-mid">
                <p className="tt-crumb">
                    <Link href={AgentPage.index.url()}>‹ Agents</Link>
                </p>
                <PageHeading>{agent.name}</PageHeading>
                <AgentDetailView agent={agent} canReplace />
            </div>
        </AppShell>
    );
}
