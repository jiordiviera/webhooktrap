import { router } from '@inertiajs/react';
import { useState } from 'react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import {
    LinkBroken,
    LinkForm,
    PrimaryLink,
    useLinkUrl,
} from '@/components/identity/link-page';
import { formatFull } from '@/lib/identity/format';
import { login } from '@/routes';

type Props = {
    link: { state: 'invalid' } | { state: 'confirm'; waitHours: number };
};

/** S-29 #3 · Start account recovery? (link page: opening it changes nothing). */
export default function AccountRecoveryLink({ link }: Props) {
    const url = useLinkUrl();
    const [declining, setDeclining] = useState(false);
    // Rendered once; the time is "72 hours after you press Start" from now.
    const [completes] = useState(() =>
        link.state === 'confirm'
            ? formatFull(new Date(Date.now() + link.waitHours * 3_600_000))
            : '',
    );

    if (link.state !== 'confirm') {
        return (
            <LinkBroken
                body="It may have expired, been used, or the recovery was cancelled. Recovery links work for 60 minutes. Sign in, or start again."
                action={<PrimaryLink href={login()}>Sign in</PrimaryLink>}
            />
        );
    }

    return (
        <BareShell title="Start account recovery?">
            <PageHeading>Start account recovery?</PageHeading>
            <p>
                Your account will be recoverable on{' '}
                <strong className="tt-strong">{completes}</strong>,{' '}
                {link.waitHours} hours after you press Start. Until then you
                can&apos;t sign in without a second step.
            </p>
            <LinkForm
                label={`Start the ${link.waitHours}-hour wait`}
                busyLabel="Starting…"
                secondary={
                    <button
                        type="button"
                        className="tt-linkbtn"
                        aria-busy={declining || undefined}
                        onClick={() => {
                            setDeclining(true);
                            router.delete(url, {
                                onFinish: () => setDeclining(false),
                            });
                        }}
                    >
                        Cancel, I found my key
                    </button>
                }
            />
        </BareShell>
    );
}
