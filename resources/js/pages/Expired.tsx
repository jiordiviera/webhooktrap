import { Link, useForm, usePage } from '@inertiajs/react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { Button } from '@/components/identity/form';
import { login } from '@/routes';
import guest from '@/routes/guest';

/** S-18 · Expired guest URL. Nothing from the session is in the props (R59). */
export default function Expired() {
    const { instance } = usePage().props;
    const form = useForm({});

    return (
        <BareShell title="This guest URL expired">
            <PageHeading>This guest URL expired</PageHeading>
            <p>
                Guest captures are kept for 24 hours. Get a new URL, or sign in
                so URLs don&apos;t expire.
            </p>
            <div className="tt-actions">
                {instance.guestMode && (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(guest.new.url());
                        }}
                    >
                        <Button
                            type="submit"
                            variant="primary"
                            size="lg"
                            disabled={form.processing}
                        >
                            Get a new URL
                        </Button>
                    </form>
                )}
                <Link href={login()}>Sign in</Link>
            </div>
        </BareShell>
    );
}
