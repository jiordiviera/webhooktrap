import { Link, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { CodeForm } from '@/components/identity/code-form';
import {
    Button,
    buttonClass,
    ErrorSummary,
    PasswordField,
} from '@/components/identity/form';
import { Glyph } from '@/components/identity/glyph';
import type { AssertionOutcome } from '@/components/identity/key-ceremony';
import { KeyCeremonyButton } from '@/components/identity/key-ceremony';
import { ProviderLogo } from '@/components/identity/providers';
import { useClientValidation } from '@/hooks/use-client-validation';
import { useWebAuthnSupport } from '@/hooks/use-webauthn-support';
import { copy, providerLabel } from '@/lib/identity/copy';
import { retryLabel } from '@/lib/identity/format';
import { readIntent, RESUME_PARAM } from '@/lib/identity/intent';
import confirmAuth from '@/routes/confirm-auth';

type Props = {
    methods: string[];
    secondFactorRequired: boolean;
    action: 'confirm' | 'change_email' | 'delete_account' | 'security';
    next: string;
    retryAt?: string | null;
    /** Allow-listed reason code of a generic recent-auth bounce. */
    reason?: string | null;
    /** S-28 wording for `reason`: "You're about to {verb}." */
    verb?: string | null;
    /** provider => linked account label, for `reauth_mismatch`. */
    providerLabels?: Record<string, string>;
};

const ACTION_VERBS: Record<Props['action'], string | null> = {
    confirm: null,
    security: null,
    change_email: 'change your email',
    delete_account: 'delete your account',
};

/** S-28 · Confirm it's you (recent authentication). */
export default function ConfirmAuth({
    methods,
    secondFactorRequired,
    action,
    next,
    retryAt,
    reason = null,
    verb: reasonVerb = null,
    providerLabels = {},
}: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const supported = useWebAuthnSupport();
    const [intent, setIntent] = useState<{ verb: string; next: string } | null>(
        null,
    );

    useEffect(() => {
        // Coming from a Security dialog: name the action and reopen the dialog after.
        const saved = readIntent();

        if (saved && next.startsWith('/account/security')) {
            // A POST bounced here comes back to the page itself: reopen the dialog.
            // A GET (Connect) comes back to its own URL and carries on.
            setIntent({
                verb: saved.verb,
                next:
                    next === '/account/security'
                        ? `/account/security?${RESUME_PARAM}=1`
                        : next,
            });
        }
    }, [next]);

    const verb =
        ACTION_VERBS[action] ??
        reasonVerb ??
        intent?.verb ??
        'change a security setting';
    const target = intent?.next ?? next;
    const providers = methods.filter((m) => m === 'github' || m === 'google');
    const hasKey = methods.includes('webauthn') && supported;

    const formError = errors.form;
    const summary =
        formError === 'throttled' && retryAt
            ? copy.throttled(retryLabel(retryAt))
            : formError === 'reauth_mismatch' ||
                formError === 'oauth_cancelled' ||
                formError === 'oauth_failed'
              ? null
              : formError === 'second_factor_required'
                ? 'Your email is how you’d recover this account, so confirm with a passkey, security key or authenticator code.'
                : null;

    const confirmWithKey = (credential: Record<string, unknown>) =>
        new Promise<AssertionOutcome>((resolve) => {
            router.post(
                confirmAuth.store.url(),
                {
                    credential: JSON.stringify(credential),
                    action,
                    next: target,
                },
                {
                    preserveScroll: true,
                    onSuccess: () => resolve({ status: 'ok', credential }),
                    onError: (errs) =>
                        resolve(
                            errs.form === 'throttled'
                                ? {
                                      status: 'throttled',
                                      retryAt:
                                          retryAt ?? new Date().toISOString(),
                                  }
                                : { status: 'refused' },
                        ),
                    onCancel: () => resolve({ status: 'error' }),
                },
            );
        });

    return (
        <BareShell title="Confirm it's you">
            <PageHeading>Confirm it&apos;s you</PageHeading>
            <p>
                You&apos;re about to {verb}. Confirm it&apos;s you first. You
                won&apos;t be asked again for 10 minutes.
            </p>
            {secondFactorRequired && (
                <p>
                    {action === 'delete_account'
                        ? "Deleting your account can't be undone, so confirm with a passkey, security key or authenticator code."
                        : 'Your email is how you’d recover this account, so confirm with a passkey, security key or authenticator code.'}
                </p>
            )}
            <ErrorSummary message={summary} />

            {hasKey && (
                <KeyCeremonyButton
                    label="Use passkey or security key"
                    waiting="Waiting…"
                    waitingLine="Touch your key or confirm on your device…"
                    purpose="confirm"
                    onCredential={confirmWithKey}
                    messages={{
                        cancelled:
                            'Nothing happened. Try again, or use another way below.',
                        refused:
                            "That key didn't work. If it keeps failing, check your email: we may have blocked it to protect your account. Use another way below.",
                    }}
                />
            )}
            {methods.includes('webauthn') && !supported && (
                <p className="tt-hint">
                    This browser can&apos;t use security keys. Use another way
                    below.
                </p>
            )}

            {methods.includes('password') && !secondFactorRequired && (
                <PasswordConfirm action={action} next={target} />
            )}

            {methods.includes('totp') && (
                <CodeForm
                    id="confirm-code"
                    label="Authenticator code"
                    kind="totp"
                    action={confirmAuth.store.url()}
                    submitLabel="Confirm"
                    busyLabel="Confirming…"
                    extra={{ action, next: target }}
                    errorFor={(c) =>
                        c === 'throttled'
                            ? undefined
                            : "That code didn't work. Enter the newest code from your app."
                    }
                />
            )}

            {providers.map((provider) => (
                <ProviderConfirm
                    key={provider}
                    provider={provider}
                    linkedLabel={providerLabels[provider]}
                    action={action}
                    next={target}
                    reason={reason}
                    mismatch={formError === 'reauth_mismatch'}
                />
            ))}
            {formError === 'oauth_cancelled' && providers.length > 0 && (
                <p className="tt-status" role="status">
                    Nothing changed. Try again when you&apos;re ready.
                </p>
            )}
            {formError === 'oauth_failed' && providers.length > 0 && (
                <p className="tt-ferr" role="alert">
                    <Glyph name="error" />
                    <span>
                        That didn&apos;t work. Nothing changed. Try again.
                    </span>
                </p>
            )}

            <div className="tt-footlinks">
                <p>
                    <Link href={next}>Cancel</Link>
                </p>
            </div>
        </BareShell>
    );
}

function PasswordConfirm({ action, next }: { action: string; next: string }) {
    const form = useForm({ password: '', action, next });
    const ref = useRef<HTMLInputElement>(null);
    const failed =
        (form.errors as Record<string, string | undefined>).form ===
        'confirmation_failed';

    const checks = useClientValidation({
        password: { id: 'confirm-password', label: 'Password' },
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        let typed = '';
        checks.check(event.currentTarget, (values) => {
            typed = values.password;
            form.setData('password', values.password);
        });

        if (typed === '') {
            ref.current?.focus();

            return;
        }
        form.transform((data) => ({ ...data, action, next }));
        form.post(confirmAuth.store.url(), {
            preserveScroll: true,
            onError: () => {
                form.setData('password', '');
                ref.current?.focus();
            },
        });
    };

    return (
        <form
            className="tt-col"
            style={{ gap: 12 }}
            onSubmit={submit}
            noValidate
        >
            <PasswordField
                id="confirm-password"
                name="password"
                label="Password"
                autoComplete="current-password"
                inputRef={ref}
                value={form.data.password}
                error={checks.error(
                    'password',
                    failed ? 'That password is incorrect.' : undefined,
                )}
                {...checks.bind('password', (v) => form.setData('password', v))}
            />
            <div>
                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    disabled={form.processing}
                >
                    {form.processing ? 'Confirming…' : 'Confirm'}
                </Button>
            </div>
        </form>
    );
}

function ProviderConfirm({
    provider,
    linkedLabel,
    action,
    next,
    reason,
    mismatch,
}: {
    provider: string;
    linkedLabel?: string;
    action: string;
    next: string;
    reason: string | null;
    mismatch: boolean;
}) {
    const label = providerLabel(provider);
    const [busy, setBusy] = useState(false);
    const ref = useRef<HTMLButtonElement>(null);
    const noteId = `${provider}-note`;

    useEffect(() => {
        if (mismatch) {
            ref.current?.focus();
        }
    }, [mismatch]);

    return (
        <div className="tt-col" style={{ gap: 6 }}>
            <button
                ref={ref}
                type="button"
                className={`${buttonClass({ size: 'lg', block: true })} tt-pbtn`}
                aria-describedby={noteId}
                aria-busy={busy || undefined}
                onClick={() => {
                    setBusy(true);
                    router.post(
                        confirmAuth.oauth.url(provider),
                        reason ? { action, next, reason } : { action, next },
                        { onFinish: () => setBusy(false) },
                    );
                }}
            >
                <ProviderLogo provider={provider} />
                Continue with {label}
            </button>
            <p className="tt-hint" id={noteId}>
                {provider === 'github'
                    ? "If you're signed in to GitHub in this browser, GitHub may confirm without asking."
                    : 'Google will ask you to sign in again.'}
            </p>
            {mismatch && (
                <p className="tt-ferr" role="alert">
                    <Glyph name="error" />
                    <span>
                        {provider === 'github'
                            ? `That isn't the GitHub account linked here. Sign in to GitHub as ${linkedLabel ?? 'the linked account'}, then try again.`
                            : `That isn't the Google account linked here. Choose ${linkedLabel ?? 'the linked account'} when Google asks.`}
                    </span>
                </p>
            )}
        </div>
    );
}
