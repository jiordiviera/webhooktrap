import { Link } from '@inertiajs/react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { EmailChangeBroken } from '@/components/identity/broken-links';
import { LinkForm, SignInRequired } from '@/components/identity/link-page';
import { formatTime } from '@/lib/identity/format';
import account from '@/routes/account';
import type { LinkState } from '@/types/identity';

type Props = {
    link: LinkState<{ newEmail: string; expiresAt: string; needsOld: boolean }>;
};

/** S-32 · Confirm new email (`/email-change/{token}`). */
export default function EmailChangeLink({ link }: Props) {
    if (link.state === 'invalid') {
        return <EmailChangeBroken />;
    }

    if (link.state === 'sign_in_required' || link.state === 'other_account') {
        return (
            <SignInRequired
                title="Sign in to confirm your new email"
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

    return (
        <BareShell title="Confirm your new email">
            <PageHeading>Confirm your new email</PageHeading>
            <p>
                Make <strong className="tt-strong">{link.newEmail}</strong> the
                email for your Webhook Trap account? Sign-in, recovery and
                security emails will go there, and your other browsers will be
                signed out.
            </p>
            {link.needsOld && (
                <p>
                    The email changes once your current address has confirmed
                    too.
                </p>
            )}
            <LinkForm
                label="Confirm new email"
                busyLabel="Confirming…"
                secondary={<Link href={account.security()}>Not now</Link>}
            />
            <p className="tt-hint">
                &ldquo;Not now&rdquo; changes nothing; the link keeps working
                until {formatTime(link.expiresAt)}.
            </p>
        </BareShell>
    );
}
