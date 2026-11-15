import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { formatFull } from '@/lib/identity/format';
import { cancel as cancelRecovery } from '@/routes/account-recovery';
import { DialogCancel, SecurityDialog } from './dialog';
import { Button } from './form';
import { Glyph } from './glyph';

/*
 * S-00 "Account recovery pending" (U-5): a ruled banner under the top bar on
 * every signed-in page, above any other banner, not dismissable. It is
 * `role="status"`, so it is announced once per page load; Cancel recovery
 * is its only control (S-29 #7 dialog).
 */
export function RecoveryBanner() {
    const { principal } = usePage().props;
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    if (principal.kind !== 'user' || !principal.recoveryPending) {
        return null;
    }

    const { requestedAt, completesAt, extended } = principal.recoveryPending;
    const requested = formatFull(requestedAt);
    const completes = formatFull(completesAt);

    return (
        <div className="tt-bannerx" role="status">
            <p className="txt">
                <Glyph name="lock" />
                <span>
                    Someone asked to recover this account on {requested}. If it
                    wasn&apos;t you, cancel it now. Otherwise your passkeys,
                    keys and authenticator app are removed on {completes}.
                    {extended &&
                        ' The wait is 7 days because the password was reset by email.'}
                </span>
            </p>
            <Button onClick={() => setOpen(true)}>Cancel recovery</Button>
            <SecurityDialog
                open={open}
                onOpenChange={setOpen}
                title="Cancel account recovery?"
                destructive
                footer={
                    <>
                        <DialogCancel asChild>
                            <Button>Keep it</Button>
                        </DialogCancel>
                        <Button
                            variant="primary"
                            busy={busy}
                            aria-disabled={busy || undefined}
                            onClick={() => {
                                setBusy(true);
                                router.delete(cancelRecovery.url(), {
                                    preserveScroll: true,
                                    onFinish: () => {
                                        setBusy(false);
                                        setOpen(false);
                                    },
                                });
                            }}
                        >
                            Cancel recovery
                        </Button>
                    </>
                }
            >
                <p>
                    If you didn&apos;t ask for this, cancel it and change your
                    password: someone may know it.
                </p>
            </SecurityDialog>
        </div>
    );
}
