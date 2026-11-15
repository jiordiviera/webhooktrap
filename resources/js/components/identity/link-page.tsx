import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { login, logout } from '@/routes';
import { BareShell, PageHeading } from './bare-shell';
import { Button, buttonClass, ErrorSummary } from './form';

/*
 * Emailed-link pages (S-32): opening one changes nothing; one primary
 * button in a real form (POST, CSRF) makes the change. The form posts to
 * the page's own URL, which is the link itself.
 */

export function useLinkUrl(): string {
    return usePage().url;
}

/** The session's CSRF token, given to link pages so their forms post without JavaScript. */
export function CsrfField() {
    const token = usePage().props.csrfToken;

    return typeof token === 'string' ? (
        <input type="hidden" name="_token" value={token} />
    ) : null;
}

export function LinkForm({
    label,
    busyLabel,
    destructive = false,
    data = {},
    secondary,
}: {
    label: string;
    busyLabel: string;
    destructive?: boolean;
    data?: Record<string, string>;
    secondary?: ReactNode;
}) {
    const url = useLinkUrl();
    const form = useForm(data);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true });
    };

    return (
        <form
            method="post"
            action={url}
            onSubmit={submit}
            className="tt-col"
            style={{ gap: 12 }}
        >
            <CsrfField />
            <ErrorSummary
                message={
                    form.errors && Object.keys(form.errors).length > 0
                        ? "This link doesn't work any more. Ask for a new one."
                        : null
                }
            />
            <div className="tt-actions">
                <Button
                    type="submit"
                    variant={destructive ? 'danger' : 'primary'}
                    size="lg"
                    aria-disabled={form.processing || undefined}
                    busy={form.processing}
                >
                    {form.processing ? busyLabel : label}
                </Button>
                {secondary}
            </div>
        </form>
    );
}

/** "Not signed in" and "Signed in to another account" states (S-32). */
export function SignInRequired({
    title,
    body,
    signedInAs,
    otherBody,
}: {
    title: string;
    body: string;
    signedInAs?: string;
    otherBody?: (email: string) => string;
}) {
    const signOut = useForm({ continue: true });

    return (
        <BareShell title={title}>
            <PageHeading>{title}</PageHeading>
            {signedInAs && otherBody ? (
                <>
                    <p>{otherBody(signedInAs)}</p>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            signOut.post(logout.url());
                        }}
                    >
                        <Button
                            type="submit"
                            variant="primary"
                            size="lg"
                            busy={signOut.processing}
                            aria-disabled={signOut.processing || undefined}
                        >
                            Sign out and continue
                        </Button>
                    </form>
                </>
            ) : (
                <>
                    <p>{body}</p>
                    <div>
                        <Link
                            className={buttonClass({
                                variant: 'primary',
                                size: 'lg',
                            })}
                            href={login()}
                        >
                            Sign in
                        </Link>
                    </div>
                </>
            )}
        </BareShell>
    );
}

/** "This link doesn't work": one page for every cause, one next step. */
export function LinkBroken({
    body,
    action,
}: {
    body: string;
    action: ReactNode;
}) {
    return (
        <BareShell title="This link doesn't work">
            <PageHeading>This link doesn&apos;t work</PageHeading>
            <p>{body}</p>
            <div className="tt-actions">{action}</div>
        </BareShell>
    );
}

export function PrimaryLink({
    href,
    children,
}: {
    href: Parameters<typeof Link>[0]['href'];
    children: ReactNode;
}) {
    return (
        <Link
            className={buttonClass({ variant: 'primary', size: 'lg' })}
            href={href}
        >
            {children}
        </Link>
    );
}
