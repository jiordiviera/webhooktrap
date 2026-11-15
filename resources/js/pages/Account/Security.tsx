import { Link, router } from '@inertiajs/react';
import * as Menu from '@radix-ui/react-dropdown-menu';
import type { RefObject } from 'react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { toast } from 'sonner';
import { AccountShell } from '@/components/identity/account-shell';
import { PageHeading } from '@/components/identity/bare-shell';
import { Button, Note, StatusBadge } from '@/components/identity/form';
import { Glyph } from '@/components/identity/glyph';
import { ProviderLogo } from '@/components/identity/providers';
import { protectedVisit } from '@/components/identity/security-actions';
import {
    AddKeyDialog,
    BlockedKeyDialog,
    DisconnectDialog,
    EmailDialog,
    kindWord,
    LastMethodDialog,
    PasswordDialog,
    RegenerateCodesDialog,
    RemoveKeyDialog,
    RenameDialog,
    ShowCodesDialog,
    SignOutDialog,
    TotpOffDialog,
    TotpSetupDialog,
} from '@/components/identity/security-dialogs';
import { useWebAuthnSupport } from '@/hooks/use-webauthn-support';
import {
    flashCopy,
    lockLine,
    logCopy,
    methodLabel,
    providerLabel,
} from '@/lib/identity/copy';
import {
    formatDay,
    formatFull,
    formatLastUsed,
    formatTime,
} from '@/lib/identity/format';
import {
    clearIntent,
    readIntent,
    rememberIntent,
    RESUME_PARAM,
} from '@/lib/identity/intent';
import account from '@/routes/account';
import { cancel as cancelEmail } from '@/routes/account/email';
import identities from '@/routes/account/identities';
import totp from '@/routes/account/totp';
import type {
    Flash,
    SecurityKeyRow,
    SecurityView,
    SessionRow,
} from '@/types/identity';

type Props = {
    security: SecurityView;
    totpSetup?: { secret: string; uri: string } | null;
    recoveryCodes?: string[] | null;
};

type Dialog =
    | { kind: 'add'; keyKind: 'passkey' | 'security_key' }
    | { kind: 'rename' | 'remove' | 'blocked'; key: SecurityKeyRow }
    | { kind: 'last-method' }
    | { kind: 'totp'; setup: { secret: string; uri: string } }
    | { kind: 'totp-off' }
    | { kind: 'regenerate' }
    | { kind: 'codes'; codes: string[]; firstFactor: boolean }
    | { kind: 'password' }
    | { kind: 'disconnect'; provider: string }
    | { kind: 'email'; email?: string }
    | { kind: 'signout'; session: SessionRow | null };

const PROVIDERS = ['github', 'google'] as const;

function joinWords(items: string[]): string {
    if (items.length <= 1) {
        return items.join('');
    }

    return `${items.slice(0, -1).join(', ')} and ${items[items.length - 1]}`;
}

function summaryLine(s: SecurityView): string {
    const ways: string[] = [];

    if (s.hasPassword) {
        ways.push('a password');
    }
    s.identities.forEach((i) => ways.push(providerLabel(i.provider)));
    const passkeys = s.keys.filter(
        (k) => k.kind === 'passkey' && !k.flagged,
    ).length;

    if (passkeys > 0) {
        ways.push(`${passkeys} passkey${passkeys === 1 ? '' : 's'}`);
    }
    const state = s.twoFactor
        ? 'Two-step sign-in is on.'
        : 'Two-step sign-in is off.';

    return ways.length > 0
        ? `${state} You can sign in with ${joinWords(ways)}.`
        : state;
}

function flagNote(key: SecurityKeyRow): string {
    const when = key.flaggedAt ? formatFull(key.flaggedAt) : '';

    return key.flagCause === 'user_handle_mismatch'
        ? `Blocked on ${when}: it sent sign-in details that don't belong to your account, which can mean it was tampered with. It can't be used until you review it.`
        : `Blocked on ${when}: its use counter went backwards, which can mean the key was copied. It can't be used until you review it.`;
}

const syncWord = (synced: boolean | null) =>
    synced === null ? '—' : synced ? 'Synced' : 'This device only';

/** S-17A · Account · Security */
export default function Security({
    security,
    totpSetup,
    recoveryCodes,
}: Props) {
    const supported = useWebAuthnSupport();
    const [dialog, setDialog] = useState<Dialog | null>(null);
    const expected = useRef<Record<string, string>>({});
    const headings = {
        keys: useRef<HTMLHeadingElement>(null),
        linked: useRef<HTMLHeadingElement>(null),
        sessions: useRef<HTMLHeadingElement>(null),
        totp: useRef<HTMLHeadingElement>(null),
    };
    const [returnTo, setReturnTo] = useState<
        RefObject<HTMLElement | null> | undefined
    >();

    const s = security;
    const activeKeys = s.keys.filter((k) => !k.flagged);
    const secondFactors = activeKeys.length + (s.totp.enabled ? 1 : 0);
    const atLimit = s.keys.length >= s.maxKeys;
    const otherSessions = s.sessions.filter((x) => !x.current);
    const lock = s.lock;

    const open = (next: Dialog, focusAfter?: RefObject<HTMLElement | null>) => {
        setReturnTo(focusAfter);
        setDialog(next);
    };
    const close = () => {
        clearIntent();
        setDialog(null);
    };
    const expect = (code: string, message: string) => {
        expected.current[code] = message;
    };

    // One-time values from the server open their dialog (TOTP secret, codes).
    useEffect(() => {
        if (totpSetup) {
            setDialog({ kind: 'totp', setup: totpSetup });
        }
    }, [totpSetup]);

    useEffect(() => {
        if (recoveryCodes && recoveryCodes.length > 0) {
            setDialog({
                kind: 'codes',
                codes: recoveryCodes,
                firstFactor: false,
            });
        }
    }, [recoveryCodes]);

    // Back from S-28: reopen the dialog the person had open, choices kept.
    const resume = useEffectEvent(() => {
        if (!new URLSearchParams(window.location.search).has(RESUME_PARAM)) {
            return;
        }
        const intent = readIntent();
        clearIntent();
        window.history.replaceState(
            window.history.state,
            '',
            window.location.pathname,
        );

        if (!intent) {
            return;
        }
        const keyOf = (id?: string) => security.keys.find((k) => k.id === id);
        const p = intent.params ?? {};

        switch (intent.dialog) {
            case 'add':
                setDialog({
                    kind: 'add',
                    keyKind:
                        p.kind === 'security_key' ? 'security_key' : 'passkey',
                });
                break;
            case 'remove':
            case 'blocked': {
                const key = keyOf(p.key);

                if (key) {
                    setDialog({ kind: intent.dialog, key });
                }
                break;
            }
            case 'totp-start':
                router.post(totp.start.url(), {}, { preserveScroll: true });
                break;
            case 'totp-off':
            case 'regenerate':
            case 'password':
                setDialog({ kind: intent.dialog } as Dialog);
                break;
            case 'codes':
                setDialog({ kind: 'regenerate' });
                break;
            case 'disconnect':
                if (p.provider) {
                    setDialog({ kind: 'disconnect', provider: p.provider });
                }
                break;
            case 'email':
                setDialog({ kind: 'email', email: p.email });
                break;
            case 'signout':
                setDialog({
                    kind: 'signout',
                    session:
                        security.sessions.find((x) => x.id === p.session) ??
                        null,
                });
                break;
            case 'signout-all':
                setDialog({ kind: 'signout', session: null });
                break;
        }
    });

    useEffect(() => resume(), []);

    const toastFor = (flash: Flash): string | null | undefined => {
        const exact = expected.current[flash.code];
        delete expected.current[flash.code];

        if (exact) {
            return exact;
        }

        if (flash.code === 'identity_linked') {
            const linked = s.identities.find(
                (i) => i.provider === flash.message,
            );

            return linked
                ? `${providerLabel(flash.message)} connected as ${linked.label}.`
                : flashCopy(flash);
        }

        if (
            flash.code === 'totp_enabled' &&
            recoveryCodes &&
            recoveryCodes.length > 0
        ) {
            // First second factor: the codes dialog carries the news.
            return 'Authenticator app is on.';
        }

        return undefined;
    };

    const addDisabledReason = atLimit
        ? `You have ${s.maxKeys} passkeys and security keys, the most an account can hold. Remove one to add another.`
        : !supported
          ? "This browser can't create passkeys or use security keys. Try a current Chrome, Edge, Firefox or Safari."
          : null;

    return (
        <AccountShell title="Security" section="security" toastFor={toastFor}>
            <PageHeading>Security</PageHeading>
            <p className="tt-muted" style={{ fontSize: 14 }}>
                {summaryLine(s)}
            </p>

            {s.recoveryReview && (
                <Note>
                    <p>
                        Your account was recovered. Check what changed:{' '}
                        <Link href={account.security.review()}>
                            Review your account
                        </Link>
                        . Available until {formatFull(s.recoveryReview.until)}.
                    </p>
                </Note>
            )}

            {/* 1 · Password and email */}
            <section className="tt-sec" aria-labelledby="h-pw">
                <h2 id="h-pw" tabIndex={-1}>
                    Password and email
                </h2>
                <div className="tt-lr">
                    <span className="k">Password</span>
                    <span className="st">
                        {s.hasPassword && (
                            <StatusBadge tone="pass">SET</StatusBadge>
                        )}
                        <span>
                            {s.hasPassword
                                ? 'Set'
                                : 'No password. You sign in with GitHub or a passkey.'}
                        </span>
                    </span>
                    <span className="acts">
                        <Button
                            size="sm"
                            onClick={() => open({ kind: 'password' })}
                        >
                            {s.hasPassword
                                ? 'Change password'
                                : 'Set a password'}
                        </Button>
                    </span>
                </div>
                <div className="tt-lr">
                    <span className="k">Email</span>
                    <span className="tt-col" style={{ gap: 4 }}>
                        <span className="st flex-wrap">
                            <StatusBadge
                                tone={s.emailVerified ? 'pass' : 'warn'}
                            >
                                {s.emailVerified ? 'OK' : 'UNVERIFIED'}
                            </StatusBadge>
                            <span
                                className="tt-mono"
                                style={{ fontSize: 12.5 }}
                            >
                                {s.email}
                            </span>{' '}
                            · {s.emailVerified ? 'Verified' : 'Not verified'}
                        </span>
                        {s.pendingEmail && <PendingEmail security={s} />}
                    </span>
                    <span className="acts">
                        <Button
                            size="sm"
                            aria-disabled={lock ? true : undefined}
                            aria-describedby={lock ? 'lock-reason' : undefined}
                            onClick={() => !lock && open({ kind: 'email' })}
                        >
                            Change email
                        </Button>
                        {s.pendingEmail && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.delete(cancelEmail.url(), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Cancel change
                            </Button>
                        )}
                    </span>
                </div>
                {lock && (
                    <p className="tt-reason" id="lock-reason">
                        {lockLine(lock)}
                    </p>
                )}
            </section>

            {/* 2 · Passkeys and security keys */}
            <section className="tt-sec" aria-labelledby="h-keys">
                <h2 id="h-keys" tabIndex={-1} ref={headings.keys}>
                    Passkeys and security keys
                </h2>
                {!s.passkeysAvailable ? (
                    <p>
                        Passkeys and security keys need this instance to run on
                        HTTPS with a domain name, such as
                        hooks.ferrymarket.example. Ask your administrator. The
                        authenticator app works without it.
                    </p>
                ) : (
                    <>
                        {!s.twoFactor && (
                            <Note>
                                <p>
                                    Add a passkey, a security key or an
                                    authenticator app to turn on two-step
                                    sign-in.
                                </p>
                            </Note>
                        )}
                        {s.keys.length === 0 ? (
                            <p>No passkeys or security keys yet.</p>
                        ) : (
                            <KeyTable
                                keys={s.keys}
                                onRename={(key) =>
                                    open({ kind: 'rename', key })
                                }
                                onRemove={(key) =>
                                    open({ kind: 'remove', key }, headings.keys)
                                }
                                onReview={(key) =>
                                    open({ kind: 'blocked', key })
                                }
                            />
                        )}
                        <p className="tt-hint">
                            Passkeys sign you in without a password and count as
                            two-step sign-in. Security keys are a second step
                            after your password, GitHub or Google.
                        </p>
                        <div className="flex flex-wrap gap-2 max-md:flex-col">
                            <Button
                                className="max-md:w-full"
                                aria-disabled={
                                    addDisabledReason ? true : undefined
                                }
                                aria-describedby={
                                    addDisabledReason ? 'add-reason' : undefined
                                }
                                onClick={() =>
                                    !addDisabledReason &&
                                    open({ kind: 'add', keyKind: 'passkey' })
                                }
                            >
                                Add a passkey
                            </Button>
                            <Button
                                className="max-md:w-full"
                                aria-disabled={
                                    addDisabledReason ? true : undefined
                                }
                                aria-describedby={
                                    addDisabledReason ? 'add-reason' : undefined
                                }
                                onClick={() =>
                                    !addDisabledReason &&
                                    open({
                                        kind: 'add',
                                        keyKind: 'security_key',
                                    })
                                }
                            >
                                Add a security key
                            </Button>
                        </div>
                        {addDisabledReason && (
                            <p className="tt-reason" id="add-reason">
                                {addDisabledReason}
                            </p>
                        )}
                    </>
                )}
            </section>

            {/* 3 · Authenticator app */}
            <section className="tt-sec" aria-labelledby="h-totp">
                <h2 id="h-totp" tabIndex={-1} ref={headings.totp}>
                    Authenticator app
                </h2>
                <div className="tt-lr">
                    <span className="k">Authenticator app</span>
                    <span className="st">
                        <StatusBadge
                            tone={
                                s.totp.enabled
                                    ? 'pass'
                                    : s.twoFactor
                                      ? 'off'
                                      : 'fail'
                            }
                        >
                            {s.totp.enabled ? 'ON' : 'OFF'}
                        </StatusBadge>
                        <span>
                            {s.totp.enabled && s.totp.since
                                ? `On since ${formatDay(s.totp.since)}`
                                : 'Off'}
                        </span>
                    </span>
                    <span className="acts">
                        {s.totp.enabled ? (
                            <Button
                                size="sm"
                                onClick={() =>
                                    open({ kind: 'totp-off' }, headings.totp)
                                }
                            >
                                Turn off
                            </Button>
                        ) : (
                            <Button
                                size="sm"
                                onClick={() =>
                                    protectedVisit(
                                        'post',
                                        totp.start.url(),
                                        {},
                                        {
                                            dialog: 'totp-start',
                                            verb: 'set up an authenticator app',
                                        },
                                        // Keeps focus on this button, so
                                        // the dialog returns it here (F-02).
                                        { preserveState: true },
                                    )
                                }
                            >
                                Set up
                            </Button>
                        )}
                    </span>
                </div>
            </section>

            {/* 4 · Recovery codes */}
            <section className="tt-sec" aria-labelledby="h-codes">
                <h2 id="h-codes" tabIndex={-1}>
                    Recovery codes
                </h2>
                {s.twoFactor && s.recoveryCodesLeft !== null ? (
                    <>
                        <div className="tt-lr">
                            <span className="k">Recovery codes</span>
                            <span className="st">
                                <StatusBadge
                                    tone={
                                        s.recoveryCodesLeft === 0
                                            ? 'fail'
                                            : s.recoveryCodesLeft <= 2
                                              ? 'warn'
                                              : 'pass'
                                    }
                                >
                                    {s.recoveryCodesLeft === 0
                                        ? 'NONE'
                                        : s.recoveryCodesLeft <= 2
                                          ? 'LOW'
                                          : 'OK'}
                                </StatusBadge>
                                <span>
                                    {s.recoveryCodesLeft} of 10 left. Each code
                                    works once, if you lose your passkeys, keys
                                    and phone.
                                </span>
                            </span>
                            <span className="acts">
                                <Button
                                    size="sm"
                                    onClick={() => open({ kind: 'regenerate' })}
                                >
                                    Generate new codes
                                </Button>
                            </span>
                        </div>
                        {s.recoveryCodesLeft <= 2 && (
                            <Note>
                                <p>
                                    {s.recoveryCodesLeft === 0
                                        ? 'No recovery codes left. Generate a new set now.'
                                        : `Only ${s.recoveryCodesLeft} recovery codes left. Generate a new set and store it somewhere safe.`}
                                </p>
                            </Note>
                        )}
                    </>
                ) : (
                    <div className="tt-lr">
                        <span className="k">Recovery codes</span>
                        <span>
                            You get recovery codes when you turn on two-step
                            sign-in.
                        </span>
                        <span />
                    </div>
                )}
            </section>

            {/* 5 · Linked accounts */}
            <section className="tt-sec" aria-labelledby="h-linked">
                <h2 id="h-linked" tabIndex={-1} ref={headings.linked}>
                    Linked accounts
                </h2>
                {PROVIDERS.map((provider) => {
                    const linked = s.identities.find(
                        (i) => i.provider === provider,
                    );
                    const label = providerLabel(provider);

                    return (
                        <div className="tt-lr" key={provider}>
                            <span className="k inline-flex items-center gap-2">
                                <ProviderLogo provider={provider} />
                                {label}
                            </span>
                            <span>
                                {linked
                                    ? `${linked.label} · linked ${formatDay(linked.linkedAt)}`
                                    : 'Not linked'}
                            </span>
                            <span className="acts">
                                {linked ? (
                                    <Button
                                        size="sm"
                                        aria-label={`Disconnect ${label}`}
                                        aria-disabled={lock ? true : undefined}
                                        aria-describedby={
                                            lock
                                                ? 'lock-reason-linked'
                                                : undefined
                                        }
                                        onClick={() =>
                                            !lock &&
                                            open(
                                                {
                                                    kind: 'disconnect',
                                                    provider,
                                                },
                                                headings.linked,
                                            )
                                        }
                                    >
                                        Disconnect
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        aria-label={`Connect ${label}`}
                                        onClick={() => {
                                            rememberIntent({
                                                dialog: 'connect',
                                                verb: `connect ${label}`,
                                            });
                                            router.post(
                                                identities.connect.url(
                                                    provider,
                                                ),
                                            );
                                        }}
                                    >
                                        Connect
                                    </Button>
                                )}
                            </span>
                        </div>
                    );
                })}
                {lock && (
                    <p className="tt-reason" id="lock-reason-linked">
                        {lockLine(lock)}
                    </p>
                )}
            </section>

            {/* 6 · Where you're signed in */}
            <section className="tt-sec" aria-labelledby="h-sess">
                <h2 id="h-sess" tabIndex={-1} ref={headings.sessions}>
                    Where you&apos;re signed in
                </h2>
                <SessionTable
                    sessions={s.sessions}
                    onSignOut={(session) =>
                        open({ kind: 'signout', session }, headings.sessions)
                    }
                />
                {otherSessions.length === 0 && (
                    <p>You&apos;re not signed in anywhere else.</p>
                )}
                <p className="tt-hint">
                    Networks are shown approximately. Personal tokens, CI tokens
                    and agents aren&apos;t listed here: they have their own
                    pages.
                </p>
                {otherSessions.length > 0 && (
                    <div>
                        <Button
                            size="sm"
                            className="max-md:w-full"
                            onClick={() =>
                                open(
                                    { kind: 'signout', session: null },
                                    headings.sessions,
                                )
                            }
                        >
                            Sign out all other browsers
                        </Button>
                    </div>
                )}
            </section>

            {/* 7 · Recent security activity */}
            <section className="tt-sec" aria-labelledby="h-act">
                <h2 id="h-act" tabIndex={-1}>
                    Recent security activity
                </h2>
                {s.securityLog.length === 0 ? (
                    <p>Nothing in the last 30 days.</p>
                ) : (
                    <>
                        <table
                            className="tt-keytbl hidden md:table"
                            aria-labelledby="h-act"
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
                                {s.securityLog.map((entry, i) => (
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
                            aria-labelledby="h-act"
                        >
                            {s.securityLog.map((entry, i) => (
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
                <p className="tt-hint">Showing the last 30 days.</p>
            </section>

            <Dialogs
                dialog={dialog}
                security={s}
                secondFactors={secondFactors}
                otherSessions={otherSessions.length}
                supported={supported}
                close={close}
                setDialog={setDialog}
                expect={expect}
                returnTo={returnTo}
            />
        </AccountShell>
    );
}

function PendingEmail({ security }: { security: SecurityView }) {
    const pending = security.pendingEmail;

    if (!pending) {
        return null;
    }
    const until = formatTime(pending.expiresAt);

    if (security.twoFactor) {
        return (
            <span>
                Changing to {pending.masked}. We sent a link there; it works
                until {until}.
            </span>
        );
    }

    return (
        <span>
            Changing to {pending.masked}.{' '}
            {pending.awaiting === 'both' && (
                <>
                    <strong className="tt-strong">
                        Waiting for both addresses:
                    </strong>{' '}
                    the new one and {security.email}.
                </>
            )}
            {pending.awaiting === 'old' && (
                <>
                    <strong className="tt-strong">
                        Waiting for your current address
                    </strong>
                    , {security.email}. <Glyph name="check" /> The new address
                    is confirmed.
                </>
            )}
            {pending.awaiting === 'new' && (
                <>
                    <strong className="tt-strong">
                        Waiting for the new address.
                    </strong>{' '}
                    <Glyph name="check" /> Your current address is confirmed.
                </>
            )}{' '}
            Links work until {until}.
        </span>
    );
}

function KeyTable({
    keys,
    onRename,
    onRemove,
    onReview,
}: {
    keys: SecurityKeyRow[];
    onRename: (key: SecurityKeyRow) => void;
    onRemove: (key: SecurityKeyRow) => void;
    onReview: (key: SecurityKeyRow) => void;
}) {
    const lastUsed = (k: SecurityKeyRow) =>
        k.lastUsedAt ? formatLastUsed(k.lastUsedAt) : 'Never used';

    return (
        <>
            <table
                className="tt-keytbl hidden md:table"
                aria-labelledby="h-keys"
            >
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Sync</th>
                        <th scope="col">Added</th>
                        <th scope="col">Last used</th>
                        <th scope="col">
                            <span className="tt-sr">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {keys.map((k) => (
                        <tr key={k.id}>
                            <th scope="row" style={{ minWidth: 150 }}>
                                {k.name}
                                {k.flagged && (
                                    <span className="tt-blk">Blocked</span>
                                )}
                                {k.flagged && (
                                    <span className="tt-flagnote">
                                        {flagNote(k)}
                                    </span>
                                )}
                                {k.flagged && k.canUnblock === false && (
                                    <span className="tt-flagnote">
                                        This key was blocked twice. Remove it
                                        and add it again.
                                    </span>
                                )}
                            </th>
                            <td>{kindWord(k.kind)}</td>
                            <td>{syncWord(k.synced)}</td>
                            <td>{formatDay(k.createdAt)}</td>
                            <td>{lastUsed(k)}</td>
                            <td className="ac">
                                {k.flagged ? (
                                    <Button
                                        size="sm"
                                        aria-label={`Review ${k.name}`}
                                        onClick={() => onReview(k)}
                                    >
                                        Review
                                    </Button>
                                ) : (
                                    <Button
                                        size="sm"
                                        aria-label={`Rename ${k.name}`}
                                        onClick={() => onRename(k)}
                                    >
                                        Rename
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    aria-label={`Remove ${k.name}`}
                                    onClick={() => onRemove(k)}
                                >
                                    Remove
                                </Button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <ul className="tt-keylist md:hidden" aria-labelledby="h-keys">
                {keys.map((k) => (
                    <li key={k.id}>
                        <span className="l1">
                            {k.name}
                            {k.flagged && (
                                <span className="tt-blk">Blocked</span>
                            )}
                        </span>
                        <span>
                            {kindWord(k.kind)} · {syncWord(k.synced)}
                        </span>
                        <span className="m">
                            Added {formatDay(k.createdAt)} ·{' '}
                            {k.lastUsedAt
                                ? `Last used ${lastUsed(k).replace(/^Today/, 'today')}`
                                : 'Never used'}
                        </span>
                        {k.flagged && (
                            <span className="tt-flagnote">{flagNote(k)}</span>
                        )}
                        <span className="act">
                            <Menu.Root modal={false}>
                                <Menu.Trigger asChild>
                                    <button
                                        type="button"
                                        className="tt-iconbtn tt-iconbtn-touch"
                                        aria-label={`Actions for ${k.name}`}
                                    >
                                        <Glyph name="more" large />
                                    </button>
                                </Menu.Trigger>
                                <Menu.Portal>
                                    <Menu.Content
                                        align="end"
                                        sideOffset={4}
                                        className="tt tt-menu"
                                    >
                                        <Menu.Item
                                            className="tt-menu-item"
                                            onSelect={() =>
                                                k.flagged
                                                    ? onReview(k)
                                                    : onRename(k)
                                            }
                                        >
                                            {k.flagged ? 'Review' : 'Rename'}
                                        </Menu.Item>
                                        <Menu.Item
                                            className="tt-menu-item"
                                            onSelect={() => onRemove(k)}
                                        >
                                            Remove
                                        </Menu.Item>
                                    </Menu.Content>
                                </Menu.Portal>
                            </Menu.Root>
                        </span>
                    </li>
                ))}
            </ul>
        </>
    );
}

function SessionTable({
    sessions,
    onSignOut,
}: {
    sessions: SessionRow[];
    onSignOut: (session: SessionRow) => void;
}) {
    const browser = (x: SessionRow) => x.browser ?? 'Unknown browser';
    const seen = (x: SessionRow) =>
        x.current
            ? 'Active now'
            : x.lastSeenAt
              ? formatFull(x.lastSeenAt)
              : '—';

    return (
        <>
            <table
                className="tt-keytbl hidden md:table"
                aria-labelledby="h-sess"
            >
                <thead>
                    <tr>
                        <th scope="col">Browser</th>
                        <th scope="col">Network</th>
                        <th scope="col">Signed in</th>
                        <th scope="col">Last seen</th>
                        <th scope="col">How</th>
                        <th scope="col">
                            <span className="tt-sr">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {sessions.map((x) => (
                        <tr key={x.id}>
                            <th scope="row" style={{ minWidth: 120 }}>
                                {browser(x)}
                                {x.current && (
                                    <span className="tt-tb">This browser</span>
                                )}
                            </th>
                            <td className="net">{x.network ?? '—'}</td>
                            <td>{formatFull(x.signedInAt)}</td>
                            <td>{seen(x)}</td>
                            <td>{methodLabel(x.method)}</td>
                            <td className="ac">
                                {x.current ? (
                                    <span
                                        className="tt-muted"
                                        style={{
                                            display: 'inline-block',
                                            maxWidth: 120,
                                            fontSize: 12,
                                            whiteSpace: 'normal',
                                            textAlign: 'right',
                                        }}
                                    >
                                        Use Sign out in the account menu
                                    </span>
                                ) : (
                                    <Button
                                        size="sm"
                                        aria-label={`Sign out ${browser(x)}`}
                                        onClick={() => onSignOut(x)}
                                    >
                                        Sign out
                                    </Button>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <ul className="tt-sesslist md:hidden" aria-labelledby="h-sess">
                {sessions.map((x) => (
                    <li key={x.id}>
                        <span className="l1">
                            {browser(x)}
                            {x.current && (
                                <span className="tt-tb">This browser</span>
                            )}
                        </span>
                        <span className="tt-mono">{x.network ?? '—'}</span>
                        <span className="m">
                            Signed in {formatFull(x.signedInAt)} ·{' '}
                            {x.current ? 'Active now' : `Last seen ${seen(x)}`}
                        </span>
                        <span className="m">How: {methodLabel(x.method)}</span>
                        {x.current ? (
                            <span className="m">
                                Use Sign out in the account menu
                            </span>
                        ) : (
                            <Button
                                aria-label={`Sign out ${browser(x)}`}
                                onClick={() => onSignOut(x)}
                            >
                                Sign out
                            </Button>
                        )}
                    </li>
                ))}
            </ul>
        </>
    );
}

function Dialogs({
    dialog,
    security,
    secondFactors,
    otherSessions,
    supported,
    close,
    setDialog,
    expect,
    returnTo,
}: {
    dialog: Dialog | null;
    security: SecurityView;
    secondFactors: number;
    otherSessions: number;
    supported: boolean;
    close: () => void;
    setDialog: (d: Dialog | null) => void;
    expect: (code: string, message: string) => void;
    returnTo?: RefObject<HTMLElement | null>;
}) {
    if (!dialog) {
        return null;
    }
    const common = {
        open: true,
        onClose: close,
        expect,
        returnFocus: returnTo,
    };
    const lastMethod = () => setDialog({ kind: 'last-method' });

    switch (dialog.kind) {
        case 'add':
            return (
                <AddKeyDialog
                    key={dialog.keyKind}
                    open
                    onClose={close}
                    kind={dialog.keyKind}
                    returnFocus={returnTo}
                    onAdded={(row) => {
                        toast(
                            `${row.kind === 'passkey' ? 'Passkey' : 'Security key'} added: ${row.name}.`,
                        );
                        router.reload({ only: ['security'] });

                        if (row.recoveryCodes && row.recoveryCodes.length > 0) {
                            setDialog({
                                kind: 'codes',
                                codes: row.recoveryCodes,
                                firstFactor: true,
                            });
                        } else {
                            close();
                        }
                    }}
                />
            );
        case 'rename':
            return <RenameDialog {...common} keyRow={dialog.key} />;
        case 'remove':
            return (
                <RemoveKeyDialog
                    {...common}
                    keyRow={dialog.key}
                    lastSecondFactor={
                        security.twoFactor &&
                        !dialog.key.flagged &&
                        secondFactors === 1
                    }
                    onLastMethod={lastMethod}
                />
            );
        case 'blocked':
            return (
                <BlockedKeyDialog
                    {...common}
                    keyRow={dialog.key}
                    onRemove={() =>
                        setDialog({ kind: 'remove', key: dialog.key })
                    }
                />
            );
        case 'last-method':
            return (
                <LastMethodDialog
                    open
                    onClose={close}
                    canAddPasskey={security.passkeysAvailable && supported}
                    onAddPasskey={() =>
                        setDialog({ kind: 'add', keyKind: 'passkey' })
                    }
                />
            );
        case 'totp':
            // The secret is shown once and is gone from the props after a
            // wrong code; the dialog keeps the copy it opened with (F-01).
            return (
                <TotpSetupDialog
                    open
                    onClose={close}
                    setup={dialog.setup}
                    returnFocus={returnTo}
                />
            );
        case 'totp-off':
            return <TotpOffDialog {...common} last={secondFactors === 1} />;
        case 'regenerate':
            return (
                <RegenerateCodesDialog
                    open
                    onClose={close}
                    left={security.recoveryCodesLeft ?? 0}
                    returnFocus={returnTo}
                />
            );
        case 'codes':
            return (
                <ShowCodesDialog
                    open
                    onClose={close}
                    codes={dialog.codes}
                    firstFactor={dialog.firstFactor}
                    returnFocus={returnTo}
                />
            );
        case 'password':
            return (
                <PasswordDialog
                    {...common}
                    hasPassword={security.hasPassword}
                    policy={security.passwordPolicy}
                />
            );
        case 'disconnect':
            return (
                <DisconnectDialog
                    {...common}
                    provider={dialog.provider}
                    onLastMethod={lastMethod}
                />
            );
        case 'email':
            return (
                <EmailDialog
                    open
                    onClose={close}
                    security={security}
                    initialEmail={dialog.email}
                    returnFocus={returnTo}
                />
            );
        case 'signout':
            return (
                <SignOutDialog
                    {...common}
                    session={dialog.session}
                    others={otherSessions}
                />
            );
    }
}
