import { usePage } from '@inertiajs/react';
import { login } from '@/routes';
import account from '@/routes/account';
import { LinkBroken, PrimaryLink } from './link-page';

/* "This link doesn't work" variants that depend on who is signed in (S-32). */

export function EmailChangeBroken() {
    const { principal } = usePage().props;

    return (
        <LinkBroken
            body="It may have expired, been used or been replaced by a newer request. Links to confirm an email change work for 60 minutes. Ask again in Security."
            action={
                principal.kind === 'user' ? (
                    <PrimaryLink href={account.security()}>
                        Go to Security
                    </PrimaryLink>
                ) : (
                    <PrimaryLink href={login()}>Sign in</PrimaryLink>
                )
            }
        />
    );
}

export function DeletionBroken() {
    const { principal } = usePage().props;

    return (
        <LinkBroken
            body="It may have expired, been used or been replaced by a newer request. Links to delete an account work for 60 minutes. Nothing was deleted. Ask again from Delete account."
            action={
                principal.kind === 'user' ? (
                    <PrimaryLink href={account.delete()}>
                        Go to Delete account
                    </PrimaryLink>
                ) : (
                    <PrimaryLink href={login()}>Sign in</PrimaryLink>
                )
            }
        />
    );
}
