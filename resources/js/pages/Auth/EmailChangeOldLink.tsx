import { Link, usePage } from '@inertiajs/react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { EmailChangeBroken } from '@/components/identity/broken-links';
import { LinkForm, SignInRequired } from '@/components/identity/link-page';
import { formatTime } from '@/lib/identity/format';
import account from '@/routes/account';
import type { LinkState } from '@/types/identity';

type Props = {
    link: LinkState<{
        newEmailMasked: string;
        expiresAt: string;
        newHalfDone: boolean;
    }>;
};

/** S-32 · Confirm email change, old address (EC-1, accounts without 2FA). */
export default function EmailChangeOldLink({ link }: Props) {
    const { principal } = usePage().props;

    if (link.state === 'invalid') {
        return <EmailChangeBroken />;
    }

    if (link.state === 'sign_in_required' || link.state === 'other_account') {
        return (
            <SignInRequired
                title="Sign in to confirm the email change"
                body="For your safety, your email changes only in a browser where you're signed in to the account that asked for it."
                signedInAs={
                    link.state === 'other_account' ? link.signedInAs : undefined
                }
                otherBody={(email) =>
                    `You're signed in as ${email}. Sign out, then sign in to the account that asked for this change.`
                }
            />
        );
    }

    const current =
        principal.kind === 'user' ? principal.email : 'your current address';

    return (
        <BareShell title="Confirm the email change">
            <PageHeading>Confirm the email change</PageHeading>
            <p>
                Change your Webhook Trap email from{' '}
                <strong className="tt-strong">{current}</strong> to{' '}
                <strong className="tt-strong">{link.newEmailMasked}</strong>?
                Both addresses must confirm. This one is the current address.
                Sign-in, recovery and security emails will go to the new
                address, and your other browsers will be signed out.
            </p>
            {link.newHalfDone && (
                <p>
                    The new address has already confirmed, so this finishes the
                    change.
                </p>
            )}
            <LinkForm
                label="Confirm email change"
                busyLabel="Confirming…"
                secondary={<Link href={account.security()}>Not now</Link>}
            />
            <p className="tt-hint">
                &ldquo;Not now&rdquo; changes nothing; the link works until{' '}
                {formatTime(link.expiresAt)}.
            </p>
        </BareShell>
    );
}
