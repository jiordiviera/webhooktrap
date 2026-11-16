import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AgentPage from '@/actions/App/Modules/Agent/Http/Controllers/AgentPageController';
import { DialogCancel, SecurityDialog } from '@/components/identity/dialog';
import { Button, StatusBadge } from '@/components/identity/form';
import { formatFull } from '@/lib/identity/format';
import type { AgentDetail, AgentRow, AgentSummary } from '@/types/run';

/*
 * Agent state in words (S-00 pill, S-08 Agents): never a colour alone.
 * Secret names only, never values (S-8); targets as the agent declared
 * them, with the terminal approval.
 */

export const agentStateWord: Record<AgentSummary['state'], string> = {
    connected: 'Connected',
    awaiting_confirmation: 'Confirm in your terminal',
    needs_reconfirm: 'Confirm in your terminal (moved workspace)',
    disconnected: 'Offline',
};

export function AgentState({ agent }: { agent: AgentRow }) {
    const tone =
        agent.state === 'connected'
            ? 'pass'
            : agent.state === 'disconnected'
              ? 'off'
              : 'warn';

    return (
        <span className="st">
            <StatusBadge tone={tone}>
                {agent.state === 'connected'
                    ? 'ON'
                    : agent.state === 'disconnected'
                      ? 'OFF'
                      : 'WAIT'}
            </StatusBadge>
            <span>{agentStateWord[agent.state]}</span>
            {agent.tooOld && (
                <span className="tt-tag is-warn">update needed</span>
            )}
        </span>
    );
}

export function AgentDetailView({
    agent,
    canReplace,
}: {
    agent: AgentDetail;
    canReplace: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const revoke = () => {
        if (busy) {
            return;
        }
        setBusy(true);
        router.delete(agent.revokeUrl, {
            onFinish: () => {
                setBusy(false);
                setOpen(false);
            },
        });
    };

    return (
        <>
            <section className="tt-sec" aria-labelledby="h-agent-facts">
                <h2 id="h-agent-facts">{agent.name}</h2>
                <dl className="tt-facts">
                    <div className="tt-lr">
                        <dt className="k">State</dt>
                        <dd>
                            <AgentState agent={agent} />
                        </dd>
                    </div>
                    <div className="tt-lr">
                        <dt className="k">Version</dt>
                        <dd className="tt-mono">
                            {agent.version}
                            {agent.tooOld && (
                                <span className="tt-muted">
                                    {' '}
                                    · update: npm i -g @webhooktrap/agent@latest
                                    (or rerun install.sh)
                                </span>
                            )}
                        </dd>
                    </div>
                    <div className="tt-lr">
                        <dt className="k">Machine</dt>
                        <dd className="tt-mono">
                            {agent.os}
                            {agent.arch ? `/${agent.arch}` : ''}
                        </dd>
                    </div>
                    <div className="tt-lr">
                        <dt className="k">Key fingerprint</dt>
                        <dd className="tt-mono tt-break">
                            {agent.keyFingerprint}
                        </dd>
                    </div>
                    <div className="tt-lr">
                        <dt className="k">Paired</dt>
                        <dd className="tt-mono">
                            {formatFull(agent.pairedAt)}
                        </dd>
                    </div>
                    <div className="tt-lr">
                        <dt className="k">Last seen</dt>
                        <dd className="tt-mono">
                            {agent.lastSeenAt
                                ? formatFull(agent.lastSeenAt)
                                : '—'}
                        </dd>
                    </div>
                </dl>
            </section>

            <section className="tt-sec" aria-labelledby="h-agent-targets">
                <h2 id="h-agent-targets">Targets</h2>
                {agent.targets.length === 0 ? (
                    <p className="tt-muted">
                        No target declared. Add one to webhooktrap.yml, then
                        restart the agent.
                    </p>
                ) : (
                    <table className="tt-keytbl">
                        <thead>
                            <tr>
                                <th scope="col">Target</th>
                                <th scope="col">Kind</th>
                                <th scope="col">Approved in the terminal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {agent.targets.map((t) => (
                                <tr key={`${t.scheme}${t.host}${t.port}`}>
                                    <th scope="row" className="tt-mono">
                                        {t.scheme}://{t.host}:{t.port}
                                    </th>
                                    <td>
                                        {t.kind === 'local'
                                            ? 'local'
                                            : 'allow-list'}
                                        {t.declared ? ' · declared' : ''}
                                    </td>
                                    <td>{t.approved ? 'yes' : 'not yet'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>

            <section className="tt-sec" aria-labelledby="h-agent-secrets">
                <h2 id="h-agent-secrets">Secrets (names only, never values)</h2>
                {agent.secretNames.length === 0 ? (
                    <p className="tt-muted">
                        No signing secret set on this agent.
                    </p>
                ) : (
                    <ul className="tt-actlist">
                        {agent.secretNames.map((s) => (
                            <li key={`${s.provider}-${s.envName}`}>
                                <span className="l1 tt-mono">{s.envName}</span>
                                <span className="m">{s.provider}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <div className="tt-row" style={{ gap: 12, flexWrap: 'wrap' }}>
                {canReplace && (
                    <Link
                        className="tt-btn"
                        href={AgentPage.pair.url({
                            query: { replace: agent.id },
                        })}
                    >
                        Replace this agent…
                    </Link>
                )}
                <Button variant="danger" onClick={() => setOpen(true)}>
                    Revoke…
                </Button>
            </div>

            <SecurityDialog
                open={open}
                onOpenChange={setOpen}
                title={`Revoke ${agent.name}?`}
                destructive
                footer={
                    <>
                        <DialogCancel asChild>
                            <Button>Cancel</Button>
                        </DialogCancel>
                        <Button
                            variant="danger"
                            busy={busy}
                            aria-disabled={busy || undefined}
                            onClick={revoke}
                        >
                            Revoke
                        </Button>
                    </>
                }
            >
                {/* COPY PROPOSAL (A07) */}
                <p>
                    {agent.name} stops taking runs at once and its key no longer
                    works. Runs it already finished are kept. To use this
                    machine again, pair it with a new code.
                </p>
            </SecurityDialog>
        </>
    );
}
