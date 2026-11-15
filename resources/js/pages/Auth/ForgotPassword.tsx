import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import {
    Button,
    buttonClass,
    ErrorSummary,
    Field,
} from '@/components/identity/form';
import { useClientValidation } from '@/hooks/use-client-validation';
import { copy } from '@/lib/identity/copy';
import { retryLabel } from '@/lib/identity/format';
import { login } from '@/routes';
import { email as sendLink } from '@/routes/password';

const EMAIL_RE = /^[^\s@]+@[^\s@]+$/;

/** S-26 · Forgot password (also the 1.x "Set a password" entry). */
export default function ForgotPassword({
    retryAt,
}: {
    retryAt?: string | null;
}) {
    const { flash, errors } = usePage<{ errors: Record<string, string> }>()
        .props;
    const form = useForm({ email: '' });
    const checks = useClientValidation({
        email: {
            id: 'email',
            label: 'Email',
            rule: (v) =>
                EMAIL_RE.test(v.trim()) ? undefined : copy.emailRequired,
        },
    });
    const [sentTo, setSentTo] = useState<string | null>(null);
    const sent = sentTo !== null && flash?.code === 'reset_link_sent';

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        let address = '';
        const ok = checks.check(event.currentTarget, (values) => {
            address = values.email.trim();
            form.setData('email', values.email);
        });

        if (!ok) {
            return;
        }
        form.post(sendLink.url(), {
            preserveState: true,
            onSuccess: () => setSentTo(address),
        });
    };

    if (sent) {
        return (
            <BareShell title="Check your inbox">
                <PageHeading>Check your inbox</PageHeading>
                <p>
                    If an account uses{' '}
                    <strong className="tt-strong">{sentTo}</strong>, we sent it
                    a link to set a new password. The link works for 60 minutes.
                </p>
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

    return (
        <BareShell title="Reset your password">
            <PageHeading>Reset your password</PageHeading>
            <p>
                Enter your account&apos;s email. We&apos;ll send a link to set a
                new password.
            </p>
            <form
                className="tt-col"
                style={{ gap: 16 }}
                onSubmit={submit}
                noValidate
            >
                <ErrorSummary
                    message={
                        errors.form === 'throttled' && retryAt
                            ? copy.throttled(retryLabel(retryAt))
                            : null
                    }
                />
                <Field
                    id="email"
                    name="email"
                    label="Email"
                    type="email"
                    autoComplete="username"
                    value={form.data.email}
                    error={checks.error(
                        'email',
                        errors.email ? copy.emailRequired : undefined,
                    )}
                    {...checks.bind('email', (v) => form.setData('email', v))}
                />
                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    block
                    disabled={form.processing}
                >
                    {form.processing ? 'Sending…' : 'Send link'}
                </Button>
            </form>
            <div className="tt-footlinks">
                <p>
                    <Link href={login()}>Back to sign in</Link>
                </p>
            </div>
        </BareShell>
    );
}
