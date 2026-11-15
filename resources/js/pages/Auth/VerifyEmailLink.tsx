import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import {
    LinkBroken,
    LinkForm,
    PrimaryLink,
} from '@/components/identity/link-page';
import { login } from '@/routes';

type Props = {
    link:
        | { state: 'invalid' }
        | { state: 'confirm'; alreadyVerified: boolean; email?: string };
};

/** S-25 link page (S-32 rules): opening it changes nothing. */
export default function VerifyEmailLink({ link }: Props) {
    if (link.state !== 'confirm') {
        return (
            <LinkBroken
                body="It may have expired or been used. Confirmation links work for 60 minutes. Sign in to get a new one."
                action={<PrimaryLink href={login()}>Sign in</PrimaryLink>}
            />
        );
    }

    return (
        <BareShell title="Confirm your email">
            <PageHeading>Confirm your email</PageHeading>
            <p>
                Press the button to confirm{' '}
                {link.email ? (
                    <strong className="tt-strong">{link.email}</strong>
                ) : (
                    'your email'
                )}{' '}
                for your Webhook Trap account.
            </p>
            <LinkForm label="Confirm email" busyLabel="Confirming…" />
        </BareShell>
    );
}
