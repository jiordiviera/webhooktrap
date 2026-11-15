import { Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { CodeForm } from '@/components/identity/code-form';
import { Button, buttonClass, Field } from '@/components/identity/form';
import { Glyph } from '@/components/identity/glyph';
import {
    finishSignIn,
    KeyCeremonyButton,
} from '@/components/identity/key-ceremony';
import { useClientValidation } from '@/hooks/use-client-validation';
import { useWebAuthnSupport } from '@/hooks/use-webauthn-support';
import { copy } from '@/lib/identity/copy';
import { retryLabel } from '@/lib/identity/format';
import { login, logout } from '@/routes';
import { request as requestRecovery } from '@/routes/account-recovery';
import twoFactor from '@/routes/two-factor';

const EMAIL_RE = /^[^\s@]+@[^\s@]+$/;

type Method = 'security_key' | 'totp' | 'recovery_code';

type Props = {
    methods: Method[];
    recoveryAvailable: boolean;
    retryAt?: string | null;
    email?: string;
    /** Attempts left on this challenge; S-27 names it at 2 or fewer. */
    attemptsLeft?: number;
};

const TITLES: Record<Method, string> = {
    security_key: 'Security key or passkey',
    totp: 'Authenticator app',
    recovery_code: 'Recovery code',
};

const ORDER: Method[] = ['security_key', 'totp', 'recovery_code'];

/** S-27 · Two-step sign-in challenge, with the S-29 request page. */
export default function Challenge({
    methods,
    recoveryAvailable,
    retryAt,
    email,
    attemptsLeft,
}: Props) {
    const { errors, flash } = usePage<{ errors: Record<string, string> }>()
        .props;
    const supported = useWebAuthnSupport();
    const available = ORDER.filter(
        (m) => methods.includes(m) && (m !== 'security_key' || supported),
    );
    const [chosen, setChosen] = useState<Method | null>(null);
    const active =
        chosen && available.includes(chosen) ? chosen : (available[0] ?? null);
    const [recovering, setRecovering] = useState(false);
    const panelHeading = useRef<HTMLHeadingElement>(null);
    const switched = useRef(false);

    useEffect(() => {
        if (switched.current) {
            panelHeading.current?.focus();
        }
    }, [active]);

    const cancel = (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                router.post(logout.url());
            }}
        >
            <Button type="submit" variant="ghost" size="sm">
                Cancel and sign out
            </Button>
        </form>
    );

    if (errors.form === 'throttled') {
        return (
            <BareShell title="Two-step sign-in is paused" aside={cancel}>
                <PageHeading>Confirm it&apos;s you</PageHeading>
                <div className="tt-col" style={{ gap: 12 }} role="alert">
                    <h2 className="tt-mh">Two-step sign-in is paused</h2>
                    <p>
                        There were too many wrong codes or keys for this
                        account.
                        {retryAt
                            ? ` Try again after ${retryLabel(retryAt)}.`
                            : ''}{' '}
                        We emailed the account owner.
                    </p>
                </div>
                <div>
                    <Link
                        className={buttonClass({
                            variant: 'primary',
                            size: 'lg',
                        })}
                        href={login()}
                    >
                        Back to sign in
                    </Link>
                </div>
            </BareShell>
        );
    }

    if (recovering) {
        return (
            <RecoveryRequest
                email={email}
                sent={flash?.code === 'recovery_link_sent'}
                onBack={() => setRecovering(false)}
                aside={cancel}
            />
        );
    }

    const others = available.filter((m) => m !== active);
    const choose = (method: Method) => {
        switched.current = true;
        setChosen(method);
    };

    return (
        <BareShell title="Confirm it's you" aside={cancel}>
            <PageHeading>Confirm it&apos;s you</PageHeading>
            <p>
                {email ? (
                    <>
                        Signed in as{' '}
                        <strong className="tt-strong">{email}</strong>.
                    </>
                ) : (
                    'You signed in with your first step.'
                )}{' '}
                One more step.
            </p>

            {methods.includes('security_key') && !supported && (
                <p className="tt-hint">
                    This browser can&apos;t use security keys. Use your
                    authenticator app or a recovery code.
                </p>
            )}

            {active && (
                <section className="tt-method" aria-labelledby="method-h">
                    <h2
                        className="tt-mh"
                        id="method-h"
                        ref={panelHeading}
                        tabIndex={-1}
                    >
                        {TITLES[active]}
                    </h2>
                    {active === 'security_key' && (
                        <>
                            <p>Use your security key or passkey.</p>
                            <KeyCeremonyButton
                                label="Use security key"
                                waiting="Waiting…"
                                waitingLine="Touch your key or confirm on your device…"
                                variant="primary"
                                onCredential={finishSignIn}
                                messages={{
                                    cancelled:
                                        'Nothing happened. Try again, or use another way below.',
                                    refused:
                                        "That key didn't work. If it keeps failing, check your email: we may have blocked it to protect your account. Use another way below.",
                                }}
                            />
                        </>
                    )}
                    {active === 'totp' && (
                        <CodeForm
                            id="totp-code"
                            label="6-digit code"
                            kind="totp"
                            action={twoFactor.totp.url()}
                            submitLabel="Verify"
                            errorFor={(c) =>
                                c === 'throttled'
                                    ? undefined
                                    : `That code didn't work. Enter the newest code from your app.${
                                          attemptsLeft !== undefined &&
                                          attemptsLeft <= 2
                                              ? ` ${attemptsLeft} ${attemptsLeft === 1 ? 'attempt' : 'attempts'} left.`
                                              : ''
                                      }`
                            }
                        />
                    )}
                    {active === 'recovery_code' && (
                        <CodeForm
                            id="recovery-code"
                            label="Recovery code"
                            kind="recovery"
                            action={twoFactor.recovery.url()}
                            submitLabel="Verify"
                            helper="Like x7vc2-y4bnq. Each code works once."
                            errorFor={(c) =>
                                c === 'throttled'
                                    ? undefined
                                    : "That recovery code didn't work. Check it, or try another one."
                            }
                        />
                    )}
                </section>
            )}

            {others.length > 0 && (
                <nav
                    aria-labelledby="other-ways"
                    className="tt-col"
                    style={{ gap: 6 }}
                >
                    <h2 className="tt-label" id="other-ways">
                        Other ways
                    </h2>
                    <ul className="tt-linkrow">
                        {others.map((m) => (
                            <li key={m} className="tt-col">
                                <a
                                    href="#method-h"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        choose(m);
                                    }}
                                >
                                    {TITLES[m]}
                                    <Glyph name="right" />
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>
            )}

            <div className="tt-footlinks">
                <p>
                    Lost access to all of these?{' '}
                    {recoveryAvailable ? (
                        <button
                            type="button"
                            className="tt-linkbtn"
                            onClick={() => setRecovering(true)}
                        >
                            Recover your account
                        </button>
                    ) : (
                        'Ask your instance administrator.'
                    )}
                </p>
            </div>
        </BareShell>
    );
}

/** S-29 #1 and #2: request a recovery link from the challenge. */
function RecoveryRequest({
    email,
    sent,
    onBack,
    aside,
}: {
    email?: string;
    sent: boolean;
    onBack: () => void;
    aside: ReactNode;
}) {
    const form = useForm({ email: email ?? '' });
    const [done, setDone] = useState(false);
    const checks = useClientValidation({
        email: {
            id: 'recovery-email',
            label: 'Email',
            rule: (v) =>
                EMAIL_RE.test(v.trim()) ? undefined : copy.emailRequired,
        },
    });

    if (done && sent) {
        return (
            <BareShell title="Check your inbox" aside={aside}>
                <PageHeading>Check your inbox</PageHeading>
                <p>
                    If this address belongs to an account that can be recovered,
                    we sent it a link. It works for 60 minutes. Open it and
                    press Start to begin the 72-hour wait.
                </p>
            </BareShell>
        );
    }

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (
            !checks.check(event.currentTarget, (values) =>
                form.setData('email', values.email),
            )
        ) {
            return;
        }
        form.post(requestRecovery.url(), {
            preserveState: true,
            onSuccess: () => setDone(true),
        });
    };

    return (
        <BareShell title="Recover your account" aside={aside}>
            <PageHeading>Recover your account</PageHeading>
            <p>
                Use this only if you&apos;ve lost every passkey, security key,
                authenticator app and recovery code. Here&apos;s what happens:
            </p>
            <ol className="tt-ol">
                <li>We email a link to your account&apos;s address.</li>
                <li>
                    After you open it and press Start, there&apos;s a 72-hour
                    wait. We email you again after 24 hours.
                </li>
                <li>
                    If you sign in with a second step during the wait, recovery
                    stops.
                </li>
                <li>
                    After the wait, your passkeys, security keys, authenticator
                    app, recovery codes and password are removed, every browser
                    and personal token is signed out, and we email you a link to
                    set a new password.
                </li>
            </ol>
            <form
                className="tt-col"
                style={{ gap: 16 }}
                onSubmit={submit}
                noValidate
            >
                <Field
                    id="recovery-email"
                    name="email"
                    label="Email"
                    type="email"
                    autoComplete="username"
                    value={form.data.email}
                    readOnly={email !== undefined}
                    error={checks.error(
                        'email',
                        form.errors.email ? copy.emailRequired : undefined,
                    )}
                    {...checks.bind('email', (v) => form.setData('email', v))}
                />
                <div className="tt-actions">
                    <Button
                        type="submit"
                        variant="primary"
                        size="lg"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Sending…' : 'Send recovery link'}
                    </Button>
                    <button
                        type="button"
                        className="tt-linkbtn"
                        onClick={onBack}
                    >
                        Back
                    </button>
                </div>
            </form>
        </BareShell>
    );
}
