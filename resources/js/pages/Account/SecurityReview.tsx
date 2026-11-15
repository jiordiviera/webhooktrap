import { Link } from '@inertiajs/react';
import { AccountShell } from '@/components/identity/account-shell';
import { PageHeading } from '@/components/identity/bare-shell';
import { buttonClass } from '@/components/identity/form';
import { logCopy } from '@/lib/identity/copy';
import { formatFull } from '@/lib/identity/format';
import account from '@/routes/account';
import type { SecurityLogEntry } from '@/types/identity';

type Review = {
    completedAt: string;
    lockUntil: string;
    agents: { name: string; needsReconfirm: boolean }[];
    ciTokens: {
        id: string;
        name: string;
        projectName: string;
        lastUsedAt: string | null;
    }[];
    log: SecurityLogEntry[];
};

/** S-30 · After recovery: security review. */
export default function SecurityReview({ review }: { review: Review }) {
    return (
        <AccountShell title="Your account was recovered" section="review">
            <PageHeading>Your account was recovered</PageHeading>
            <p className="tt-muted" style={{ fontSize: 14 }}>
                On {formatFull(review.completedAt)}. Here&apos;s what changed
                and what to check now.
            </p>

            <section className="tt-sec" aria-labelledby="h-2fa">
                <h2 id="h-2fa">Turn two-step sign-in back on</h2>
                <p style={{ fontSize: 14 }}>
                    Your passkeys, keys, authenticator app and recovery codes
                    were removed.
                </p>
                <div className="tt-actions" style={{ gap: 8 }}>
                    <Link className={buttonClass()} href={account.security()}>
                        Add a passkey
                    </Link>
                    <Link className={buttonClass()} href={account.security()}>
                        Set up an authenticator app
                    </Link>
                </div>
            </section>

            <section className="tt-sec" aria-labelledby="h-agents">
                <h2 id="h-agents">Check your agents</h2>
                {review.agents.length === 0 ? (
                    <p style={{ fontSize: 14 }}>No agents in your workspace.</p>
                ) : (
                    <>
                        <p style={{ fontSize: 14 }}>
                            These agents won&apos;t run anything until you
                            confirm them in their terminal. Approved targets
                            were cleared.
                        </p>
                        <ul className="tt-actlist">
                            {review.agents.map((agent) => (
                                <li key={agent.name}>
                                    <span className="l1">
                                        {agent.name}
                                        {agent.needsReconfirm
                                            ? ' · confirm in its terminal'
                                            : ''}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </section>

            <section className="tt-sec" aria-labelledby="h-ci">
                <h2 id="h-ci">Check your CI tokens</h2>
                {review.ciTokens.length === 0 ? (
                    <p style={{ fontSize: 14 }}>No CI tokens.</p>
                ) : (
                    <>
                        <p style={{ fontSize: 14 }}>
                            These tokens can still upload runs. Revoke any you
                            don&apos;t recognise.
                        </p>
                        <table className="tt-keytbl" aria-labelledby="h-ci">
                            <thead>
                                <tr>
                                    <th scope="col">Token</th>
                                    <th scope="col">Project</th>
                                    <th scope="col">Last used</th>
                                </tr>
                            </thead>
                            <tbody>
                                {review.ciTokens.map((token) => (
                                    <tr key={token.id}>
                                        <th scope="row">{token.name}</th>
                                        <td>{token.projectName}</td>
                                        <td>
                                            {token.lastUsedAt
                                                ? formatFull(token.lastUsedAt)
                                                : 'Never used'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </>
                )}
            </section>

            <section className="tt-sec" aria-labelledby="h-what">
                <h2 id="h-what">What happened</h2>
                {review.log.length === 0 ? (
                    <p style={{ fontSize: 14 }}>Nothing in the last 30 days.</p>
                ) : (
                    <>
                        <table
                            className="tt-keytbl hidden md:table"
                            aria-labelledby="h-what"
                        >
                            <thead>
                                <tr>
                                    <th scope="col">When</th>
                                    <th scope="col">What</th>
                                    <th scope="col">Browser</th>
                                    <th scope="col">Network</th>
                                </tr>
                            </thead>
                            <tbody>
                                {review.log.map((entry, i) => (
                                    <tr key={`${entry.at}-${i}`}>
                                        <td className="nw">
                                            {formatFull(entry.at)}
                                        </td>
                                        <td style={{ minWidth: 200 }}>
                                            {logCopy(entry)}
                                        </td>
                                        <td>{entry.browser ?? '—'}</td>
                                        <td className="net">
                                            {entry.network ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <ul
                            className="tt-actlist md:hidden"
                            aria-labelledby="h-what"
                        >
                            {review.log.map((entry, i) => (
                                <li key={`${entry.at}-${i}`}>
                                    <span className="l1">{logCopy(entry)}</span>
                                    <span className="m">
                                        {formatFull(entry.at)} ·{' '}
                                        {entry.browser ?? '—'} ·{' '}
                                        <span className="tt-mono">
                                            {entry.network ?? '—'}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
                <p className="tt-hint">
                    Until {formatFull(review.lockUntil)} you can&apos;t change
                    your email, disconnect GitHub or create tokens that start
                    runs.
                </p>
            </section>

            <div>
                <Link
                    className={buttonClass({ variant: 'primary' })}
                    href={account.security()}
                >
                    Done
                </Link>
            </div>
        </AccountShell>
    );
}
