import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { Button, ErrorSummary } from '@/components/identity/form';
import {
    CsrfField,
    LinkBroken,
    PrimaryLink,
} from '@/components/identity/link-page';
import {
    NewPasswordFields,
    useNewPassword,
} from '@/components/identity/new-password';
import password from '@/routes/password';
import type { PasswordPolicy } from '@/types/identity';

type Props = {
    link:
        | { state: 'invalid' }
        | { state: 'valid'; token: string; email?: string; legacy?: boolean };
    passwordPolicy: PasswordPolicy;
};

/** S-26 · Reset form (a link page: the token is used only by the POST). */
export default function ResetPassword({ link, passwordPolicy }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const form = useForm({
        token: link.state === 'valid' ? link.token : '',
        password: '',
        password_confirmation: '',
    });
    const fields = useNewPassword(passwordPolicy);

    if (
        link.state !== 'valid' ||
        errors.form === 'link_invalid' ||
        errors.token
    ) {
        return (
            <LinkBroken
                body="It may have expired or been used. Reset links work for 60 minutes and once."
                action={
                    <PrimaryLink href={password.request()}>
                        Send a new link
                    </PrimaryLink>
                }
            />
        );
    }

    const legacy = link.legacy === true;
    const title = legacy ? 'Set a password' : 'Choose a new password';

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const ok = fields.check(event.currentTarget, (values) =>
            form.setData((data) => ({ ...data, ...values })),
        );

        if (ok) {
            form.post(password.update.url(), {
                onError: () => form.reset('password', 'password_confirmation'),
            });
        }
    };

    return (
        <BareShell title={title}>
            <PageHeading>{title}</PageHeading>
            {legacy && (
                <p>
                    Welcome back. Webhook Trap 2.0 is a new app: your account
                    came across, your 1.x inboxes and events didn&apos;t.
                </p>
            )}
            {link.email && (
                <p>
                    <span className="tt-muted">Account </span>
                    <strong className="tt-strong">{link.email}</strong>
                </p>
            )}
            <form
                method="post"
                action={password.update.url()}
                className="tt-col"
                style={{ gap: 16 }}
                onSubmit={submit}
                noValidate
            >
                <CsrfField />
                <input type="hidden" name="token" value={link.token} />
                <ErrorSummary
                    fields={fields.summary(fields.shown(errors.password))}
                />
                <NewPasswordFields
                    policy={passwordPolicy}
                    data={form.data}
                    setData={(k, v) => form.setData(k, v)}
                    checks={fields}
                    serverError={errors.password}
                />
                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    block
                    disabled={form.processing}
                >
                    {form.processing
                        ? 'Saving…'
                        : legacy
                          ? 'Set password'
                          : 'Save password'}
                </Button>
            </form>
        </BareShell>
    );
}
