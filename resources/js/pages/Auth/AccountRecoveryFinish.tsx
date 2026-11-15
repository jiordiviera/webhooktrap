import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { Button, ErrorSummary } from '@/components/identity/form';
import {
    CsrfField,
    LinkBroken,
    PrimaryLink,
    useLinkUrl,
} from '@/components/identity/link-page';
import {
    NewPasswordFields,
    useNewPassword,
} from '@/components/identity/new-password';
import password from '@/routes/password';
import type { PasswordPolicy } from '@/types/identity';

type Props = {
    link: { state: 'invalid' } | { state: 'valid' };
    passwordPolicy: PasswordPolicy;
};

/** S-29 #9 · Set a new password to finish the recovery. */
export default function AccountRecoveryFinish({ link, passwordPolicy }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const url = useLinkUrl();
    const form = useForm({ password: '', password_confirmation: '' });
    const fields = useNewPassword(passwordPolicy);

    if (link.state !== 'valid' || errors.form === 'link_invalid') {
        return (
            <LinkBroken
                body="It may have expired or been used. Finish links work for 24 hours. Your account no longer has two-step sign-in, so you can set a password with Forgot password."
                action={
                    <PrimaryLink href={password.request()}>
                        Forgot password
                    </PrimaryLink>
                }
            />
        );
    }

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const ok = fields.check(event.currentTarget, (values) =>
            form.setData((data) => ({ ...data, ...values })),
        );

        if (ok) {
            form.post(url, { onError: () => form.reset() });
        }
    };

    return (
        <BareShell title="Set a new password">
            <PageHeading>Set a new password</PageHeading>
            <p>
                The wait is over. Set a password to finish recovering your
                account.
            </p>
            <form
                method="post"
                action={url}
                className="tt-col"
                style={{ gap: 16 }}
                onSubmit={submit}
                noValidate
            >
                <CsrfField />
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
                    {form.processing ? 'Finishing…' : 'Finish recovery'}
                </Button>
            </form>
        </BareShell>
    );
}
