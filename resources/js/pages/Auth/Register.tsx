import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import {
    Button,
    ErrorSummary,
    Field,
    PasswordField,
} from '@/components/identity/form';
import { passwordLength } from '@/components/identity/new-password';
import { ProviderButtons } from '@/components/identity/providers';
import { useClientValidation } from '@/hooks/use-client-validation';
import { copy, passwordError } from '@/lib/identity/copy';
import { retryLabel } from '@/lib/identity/format';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { PasswordPolicy } from '@/types/identity';

type Props = { passwordPolicy: PasswordPolicy; retryAt?: string | null };

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** S-24 · Create account */
export default function Register({ passwordPolicy, retryAt }: Props) {
    const { principal, errors } = usePage<{ errors: Record<string, string> }>()
        .props;
    const form = useForm({ name: '', email: '', password: '' });
    const bytes = passwordPolicy.unit === 'bytes';
    const checks = useClientValidation({
        name: {
            id: 'name',
            label: 'Name',
            rule: (v) => (v.trim() === '' ? copy.nameRequired : undefined),
        },
        email: {
            id: 'email',
            label: 'Email',
            rule: (v) =>
                EMAIL_RE.test(v.trim()) ? undefined : copy.emailRequired,
        },
        password: {
            id: 'password',
            label: 'Password',
            rule: (v) => {
                const length = passwordLength(v, passwordPolicy);

                if (length < passwordPolicy.minLength) {
                    return passwordError(
                        'password_too_short',
                        passwordPolicy.unit,
                    );
                }

                return length > passwordPolicy.maxLength
                    ? passwordError('password_too_long', passwordPolicy.unit)
                    : undefined;
            },
        },
    });

    const shown = {
        name: checks.error('name', errors.name ? copy.nameRequired : undefined),
        email: checks.error(
            'email',
            errors.email ? copy.emailRequired : undefined,
        ),
        password: checks.error(
            'password',
            passwordError(errors.password, passwordPolicy.unit),
        ),
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const ok = checks.check(event.currentTarget, (values) =>
            form.setData((data) => ({ ...data, ...values })),
        );

        if (ok) {
            form.post(store.url());
        }
    };

    return (
        <BareShell title="Create your account">
            <PageHeading>Create your account</PageHeading>
            {principal.kind === 'guest' && (
                <p>
                    Your guest session and its deliveries move to your account.
                </p>
            )}
            <ProviderButtons />
            <p className="tt-or">or use your email</p>
            <form
                className="tt-col"
                style={{ gap: 16 }}
                aria-label="Create an account with email and password"
                onSubmit={submit}
                noValidate
            >
                <ErrorSummary
                    message={
                        errors.form === 'throttled' && retryAt
                            ? copy.throttled(retryLabel(retryAt))
                            : errors.form === 'signup_closed'
                              ? 'Sign-ups on this instance are closed.'
                              : null
                    }
                    fields={checks.summary(shown)}
                />
                <Field
                    id="name"
                    name="name"
                    label="Name"
                    autoComplete="name"
                    value={form.data.name}
                    error={shown.name}
                    {...checks.bind('name', (v) => form.setData('name', v))}
                />
                <Field
                    id="email"
                    name="email"
                    label="Email"
                    type="email"
                    autoComplete="email"
                    value={form.data.email}
                    error={shown.email}
                    {...checks.bind('email', (v) => form.setData('email', v))}
                />
                <PasswordField
                    id="password"
                    name="password"
                    label="Password"
                    autoComplete="new-password"
                    value={form.data.password}
                    helper={
                        bytes ? copy.passwordHelperBytes : copy.passwordHelper
                    }
                    error={shown.password}
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
                    {form.processing
                        ? 'Creating your account…'
                        : 'Create account'}
                </Button>
            </form>
            <div className="tt-footlinks">
                <p>
                    Already have an account? <Link href={login()}>Sign in</Link>
                </p>
            </div>
        </BareShell>
    );
}
