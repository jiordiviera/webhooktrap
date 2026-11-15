import { router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { Button, ErrorSummary } from '@/components/identity/form';
import { copy } from '@/lib/identity/copy';
import { retryLabel } from '@/lib/identity/format';
import { logout, register } from '@/routes';
import { send } from '@/routes/verification';

type Props = { email: string | null; retryAt?: string | null };

const COOLDOWN_S = 60;

/** S-25 · Verify email, "Check your inbox" */
export default function VerifyEmail({ email, retryAt }: Props) {
    const { flash, errors } = usePage<{ errors: Record<string, string> }>()
        .props;
    const address =
        email ??
        (flash?.code === 'verification_sent' && flash.message
            ? flash.message
            : null);
    const resend = useForm({});
    const [left, setLeft] = useState(0);

    useEffect(() => {
        if (left <= 0) {
            return;
        }
        const timer = window.setTimeout(() => setLeft((s) => s - 1), 1000);

        return () => window.clearTimeout(timer);
    }, [left]);

    const sendAgain = () => {
        if (left > 0 || resend.processing) {
            return;
        }
        resend.post(send.url(), {
            preserveScroll: true,
            onSuccess: () => setLeft(COOLDOWN_S),
        });
    };

    const startAgain = () => {
        router.post(
            logout.url(),
            {},
            { onFinish: () => router.visit(register()) },
        );
    };

    return (
        <BareShell title="Check your inbox">
            <PageHeading>Check your inbox</PageHeading>
            <ErrorSummary
                message={
                    errors.form === 'throttled' && retryAt
                        ? copy.throttled(retryLabel(retryAt))
                        : null
                }
            />
            <p>
                We sent a link to{' '}
                {address ? (
                    <strong className="tt-strong">{address}</strong>
                ) : (
                    'your address'
                )}
                . Open it on any device to confirm your address. It works for 60
                minutes.
            </p>
            <div className="tt-col" style={{ gap: 6 }}>
                <div>
                    <Button
                        size="lg"
                        aria-disabled={
                            left > 0 || resend.processing || undefined
                        }
                        aria-describedby={
                            left > 0 ? 'resend-status' : undefined
                        }
                        busy={resend.processing}
                        onClick={sendAgain}
                    >
                        Send it again
                    </Button>
                </div>
                <p className="tt-hint" id="resend-status" role="status">
                    {left > 0 ? `Sent. You can send another in ${left} s.` : ''}
                </p>
            </div>
            <div className="tt-footlinks">
                <p>
                    Wrong address?{' '}
                    <button
                        type="button"
                        className="tt-linkbtn"
                        onClick={startAgain}
                    >
                        Start again
                    </button>
                </p>
            </div>
        </BareShell>
    );
}
