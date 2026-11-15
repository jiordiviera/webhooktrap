import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useRef } from 'react';
import {
    BareShell,
    PageFlash,
    PageHeading,
} from '@/components/identity/bare-shell';
import {
    Button,
    ErrorSummary,
    Field,
    PasswordField,
} from '@/components/identity/form';
import {
    finishSignIn,
    KeyCeremonyButton,
    useConditionalPasskeys,
} from '@/components/identity/key-ceremony';
import { ProviderButtons } from '@/components/identity/providers';
import { useClientValidation } from '@/hooks/use-client-validation';
import { useWebAuthnSupport } from '@/hooks/use-webauthn-support';
import { copy, providerLabel } from '@/lib/identity/copy';
import { retryLabel } from '@/lib/identity/format';
import { register } from '@/routes';
import password from '@/routes/password';
import { password as signInPassword } from '@/routes/sign-in';

type Props = {
    next: string;
    passkeysAvailable: boolean;
    retryAt?: string | null;
    /** Provider of a cancelled or failed OAuth sign-in. */
    oauthProvider?: string | null;
};

const EMAIL_RE = /^[^\s@]+@[^\s@]+$/;

/** S-02 · Sign in */
export default function SignIn({
    next,
    passkeysAvailable,
    retryAt,
    oauthProvider,
}: Props) {
    const { principal, instance, errors } = usePage<{
        errors: Record<string, string>;
    }>().props;
    const supported = useWebAuthnSupport();
    const form = useForm({ email: '', password: '', next });
    const checks = useClientValidation({
        email: {
            id: 'email',
            label: 'Email',
            rule: (v) =>
                EMAIL_RE.test(v.trim()) ? undefined : copy.emailRequired,
        },
        password: {
            id: 'password',
            label: 'Password',
            rule: (v) => (v === '' ? copy.passwordRequired : undefined),
        },
    });
    const passwordRef = useRef<HTMLInputElement>(null);
    const emailRef = useRef<HTMLInputElement>(null);
    const passkeysOn = passkeysAvailable && supported;
    const stopConditional = useConditionalPasskeys(passkeysOn);

    const formError = errors.form;
    const summary =
        formError === 'throttled' && retryAt
            ? copy.throttled(retryLabel(retryAt))
            : formError === 'invalid_credentials'
              ? copy.invalidCredentials
              : formError === 'challenge_expired'
                ? 'Too many attempts. Sign in again to try again.'
                : formError === 'oauth_cancelled' ||
                    formError === 'oauth_failed'
                  ? null
                  : formError
                    ? copy.invalidCredentials
                    : null;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const ok = checks.check(event.currentTarget, (values) =>
            form.setData((data) => ({ ...data, ...values })),
        );

        if (!ok) {
            return;
        }

        form.post(signInPassword.url(), {
            onError: () => {
                form.setData('password', '');
                passwordRef.current?.focus();
            },
        });
    };

    const shown = {
        email: checks.error(
            'email',
            errors.email ? copy.emailRequired : undefined,
        ),
        password: checks.error('password'),
    };

    return (
        <BareShell title="Sign in">
            <PageHeading>Sign in to Webhook Trap</PageHeading>
            <PageFlash />
            {principal.kind === 'guest' && (
                <p>
                    Your guest session and its deliveries move to your account.
                </p>
            )}

            <ProviderButtons />
            {formError === 'oauth_cancelled' && (
                <p className="tt-status" role="status">
                    Sign-in was cancelled. Nothing changed.
                </p>
            )}
            {formError === 'oauth_failed' && (
                <p className="tt-ferr" role="alert">
                    {oauthProvider === 'github' || oauthProvider === 'google'
                        ? providerLabel(oauthProvider)
                        : 'The provider'}{' '}
                    didn&apos;t let us sign you in. Try again, or use another
                    method.
                </p>
            )}

            {passkeysOn ? (
                <div className="tt-col" style={{ gap: 8 }}>
                    <KeyCeremonyButton
                        label="Sign in with a passkey"
                        waiting="Waiting for your passkey…"
                        describedBy="pk-help"
                        onStart={stopConditional}
                        onCredential={finishSignIn}
                        messages={{
                            cancelled:
                                'Passkey sign-in was cancelled. Nothing changed.',
                            refused:
                                "That passkey didn't work. Try another passkey or another way to sign in.",
                        }}
                    />
                    <p className="tt-hint" id="pk-help">
                        Use Touch ID, Windows Hello, your phone or a security
                        key that holds a passkey.
                    </p>
                </div>
            ) : (
                <p className="tt-hint">
                    {passkeysAvailable
                        ? "This browser can't use passkeys. Use your email, GitHub or Google."
                        : 'Passkeys need this instance to run on HTTPS with a domain name. Ask your administrator.'}
                </p>
            )}

            <p className="tt-or">or use your email</p>

            <form
                className="tt-col"
                style={{ gap: 16 }}
                aria-label="Sign in with email and password"
                onSubmit={submit}
                noValidate
            >
                <ErrorSummary
                    message={summary}
                    fields={checks.summary(shown)}
                />
                <Field
                    id="email"
                    name="email"
                    label="Email"
                    type="email"
                    autoComplete="username webauthn"
                    inputRef={emailRef}
                    value={form.data.email}
                    readOnly={form.processing}
                    error={shown.email}
                    {...checks.bind('email', (v) => form.setData('email', v))}
                />
                <PasswordField
                    id="password"
                    name="password"
                    label="Password"
                    autoComplete="current-password"
                    inputRef={passwordRef}
                    value={form.data.password}
                    readOnly={form.processing}
                    error={shown.password}
                    aside={
                        <Link href={password.request()}>Forgot password?</Link>
                    }
                    {...checks.bind('password', (v) =>
                        form.setData('password', v),
                    )}
                />
                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    block
                    disabled={form.processing}
                >
                    {form.processing ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>

            <div className="tt-footlinks">
                <p>
                    New to Webhook Trap?{' '}
                    <Link href={register()}>Create an account</Link>
                </p>
                {instance.kind === 'hosted' && (
                    <p>
                        Had an account before 2.0?{' '}
                        <Link href={password.request()}>
                            Set a password to sign in.
                        </Link>
                    </p>
                )}
            </div>
        </BareShell>
    );
}
