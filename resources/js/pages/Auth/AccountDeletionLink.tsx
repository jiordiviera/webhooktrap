import { Link, usePage } from '@inertiajs/react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { DeletionBroken } from '@/components/identity/broken-links';
import { LinkForm, SignInRequired } from '@/components/identity/link-page';
import { formatTime } from '@/lib/identity/format';
import account from '@/routes/account';
import type { LinkState } from '@/types/identity';

type Props = { link: LinkState<{ expiresAt: string; blocked?: string }> };

/** S-32 · Delete my account (`/account-deletion/{token}`, DL-4). */
export default function AccountDeletionLink({ link }: Props) {
    const { principal } = usePage().props;

    if (
        link.state === 'invalid' ||
        (link.state === 'confirm' && link.blocked)
    ) {
        return <DeletionBroken />;
    }

    if (link.state === 'sign_in_required' || link.state === 'other_account') {
        return (
            <SignInRequired
                title="Sign in to delete your account"
                body="For your safety, an account is deleted only in a browser where you're signed in to it."
                signedInAs={
                    link.state === 'other_account' ? link.signedInAs : undefined
                }
                otherBody={(email) =>
                    `You're signed in as ${email}. Sign out, then sign in to the account that asked for this.`
                }
            />
        );
    }

    const email = principal.kind === 'user' ? principal.email : '';

    return (
        <BareShell title="Delete your account?">
            <PageHeading>Delete your account?</PageHeading>
            <p>
                This deletes the Webhook Trap account for{' '}
                <strong className="tt-strong">{email}</strong> and everything in
                it, now. It can&apos;t be undone.
            </p>
            <LinkForm
                label="Delete my account"
                busyLabel="Deleting your account…"
                destructive
                secondary={<Link href={account.delete()}>Not now</Link>}
            />
            <p className="tt-hint">
                &ldquo;Not now&rdquo; changes nothing; the link keeps working
                until {formatTime(link.expiresAt)}.
            </p>
        </BareShell>
    );
}
