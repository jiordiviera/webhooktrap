import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AccountShell } from '@/components/identity/account-shell';
import { PageHeading } from '@/components/identity/bare-shell';
import { Button, buttonClass, ErrorSummary } from '@/components/identity/form';
import { copy } from '@/lib/identity/copy';
import { formatFull } from '@/lib/identity/format';
import account from '@/routes/account';
import password from '@/routes/password';

type Deletion = {
    mode: 'immediate' | 'email_link';
    pending?: { expiresAt: string };
    email: string;
    projects: { name: string }[];
    runs: number;
    agents: { name: string }[];
    ciTokens: number;
    personalTokens: number;
    activeRuns: number;
    pendingEmail: boolean;
    blocked?: { reason: string; until?: string };
};

const plural = (n: number, word: string) => `${n} ${word}${n === 1 ? '' : 's'}`;

/** S-17 `delete` · Delete account (DL-1, DL-4). */
export default function Delete({ deletion }: { deletion: Deletion }) {
    const ticked = usePage().url.includes('ticked=1');
    const [understood, setUnderstood] = useState(ticked);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [startOver, setStartOver] = useState(false);
    const twoFactor = deletion.mode === 'immediate';
    const d = deletion;

    const destroy = () => {
        if (!understood || busy) {
            return;
        }
        setBusy(true);
        setError(null);
        router.delete(account.destroy.url(), {
            data: { confirm: true },
            preserveScroll: true,
            onSuccess: () => setStartOver(false),
            onError: (errors) => {
                if (
                    errors.form === 'confirmation_stale' ||
                    errors.form === 'second_factor_required'
                ) {
                    router.visit(
                        password.confirm.url({
                            query: {
                                action: 'delete_account',
                                next: '/account/delete?ticked=1',
                            },
                        }),
                    );

                    return;
                }
                setError(copy.serverError);
            },
            onFinish: () => setBusy(false),
        });
    };

    if (d.blocked) {
        return (
            <AccountShell title="Delete account" section="delete">
                <PageHeading>Delete account</PageHeading>
                <p>
                    {d.blocked.reason === 'last_instance_admin'
                        ? "You're this instance's only administrator. Make someone else an administrator first."
                        : d.blocked.reason === 'recovery_open'
                          ? "You can't delete your account while account recovery is open."
                          : `You can't delete your account until ${d.blocked.until ? formatFull(d.blocked.until) : 'later'}, 7 days after the account recovery.`}
                </p>
            </AccountShell>
        );
    }

    if (!twoFactor && d.pending && !startOver) {
        return (
            <AccountShell title="Delete account" section="delete">
                <PageHeading>Delete account</PageHeading>
                <section className="tt-sec" aria-labelledby="h-check">
                    <h2 id="h-check">Check your email</h2>
                    <p style={{ fontSize: 14 }}>
                        We sent a link to{' '}
                        <strong className="tt-strong">{d.email}</strong>. Your
                        account is deleted when you open it and press Delete my
                        account. The link works for 60 minutes and once. Until
                        then, nothing changes.
                    </p>
                    <div className="tt-actions">
                        <Link
                            className={buttonClass({ variant: 'primary' })}
                            href={account.security()}
                        >
                            Back to Security
                        </Link>
                        <button
                            type="button"
                            className="tt-linkbtn"
                            onClick={() => setStartOver(true)}
                        >
                            Start over
                        </button>
                    </div>
                </section>
            </AccountShell>
        );
    }

    const items = [
        d.projects.length > 0 &&
            `${plural(d.projects.length, 'project')} (${d.projects.map((p) => p.name).join(', ')}), with their captures, runs, fixtures and share links`,
        d.runs > 0 && plural(d.runs, 'run'),
        d.agents.length > 0 &&
            `${plural(d.agents.length, 'agent')} (${d.agents.map((a) => a.name).join(', ')}). It's signed out and can't run anything until it's paired again`,
        d.ciTokens + d.personalTokens > 0 &&
            `${plural(d.ciTokens, 'CI token')} and ${plural(d.personalTokens, 'personal token')}`,
    ].filter((x): x is string => typeof x === 'string');

    return (
        <AccountShell title="Delete account" section="delete">
            <PageHeading>Delete account</PageHeading>
            <ErrorSummary message={error} />
            <div
                className="tt-col"
                style={{ gap: 8, fontSize: 14, lineHeight: 1.5 }}
            >
                <p>
                    This deletes your account and everything in it, now
                    {items.length > 0 ? ':' : '.'}
                </p>
                {items.length > 0 && (
                    <ul className="tt-ul">
                        {items.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                )}
                <p>
                    Capture URLs stop working: providers that send to them get
                    the same answer as an unknown URL. Share links stop working.
                    We email <strong className="tt-strong">{d.email}</strong>{' '}
                    when it&apos;s done.
                </p>
            </div>
            <div className="tt-col" style={{ gap: 12 }}>
                <label className="tt-checkrow">
                    <input
                        type="checkbox"
                        className="tt-check"
                        checked={understood}
                        onChange={(e) => setUnderstood(e.target.checked)}
                    />
                    I understand this can&apos;t be undone.
                </label>
                <div className="tt-actions">
                    <Button
                        variant="danger"
                        className="max-md:w-full"
                        aria-disabled={!understood || busy || undefined}
                        aria-describedby={
                            understood ? undefined : 'delete-reason'
                        }
                        busy={busy}
                        onClick={destroy}
                    >
                        {busy
                            ? twoFactor
                                ? 'Deleting your account…'
                                : 'Sending…'
                            : 'Delete account'}
                    </Button>
                    {!understood && (
                        <span className="tt-reason" id="delete-reason">
                            Tick the box to continue.
                        </span>
                    )}
                </div>
            </div>
        </AccountShell>
    );
}
