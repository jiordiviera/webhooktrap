import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { AppShell } from '@/components/shell/app-shell';
import { flashCopy } from '@/lib/identity/copy';
import account from '@/routes/account';
import type { Flash } from '@/types/identity';

/*
 * Signed-in frame for the account pages (S-17): the app shell in the
 * person's layout (decision 59, no section tabs here), then the account
 * list (left on desktop) and the page. The recovery banner and the toasts
 * come from the app shell.
 */

type Section = 'security' | 'delete' | 'review' | 'preferences';

export function AccountShell({
    title,
    section,
    children,
    toastFor,
}: {
    title: string;
    section: Section;
    children: ReactNode;
    /** Page-specific toast copy for a flash; falls back to the shared copy. */
    toastFor?: (flash: Flash) => string | null | undefined;
}) {
    const { principal } = usePage().props;
    const name =
        principal.kind === 'user' ? principal.name || principal.email : '';

    return (
        <AppShell
            title={title}
            toastFor={(flash) => toastFor?.(flash) ?? flashCopy(flash)}
        >
            <div className="flex w-full max-w-[1040px] flex-1 flex-wrap gap-8 max-md:flex-col max-md:gap-6">
                <nav
                    className="tt-sidenav md:flex-[0_0_200px]"
                    aria-label="Account"
                >
                    <p
                        className="tt-muted"
                        style={{
                            padding: '0 12px 8px',
                            fontSize: 11,
                            fontWeight: 600,
                        }}
                    >
                        Account · {name}
                    </p>
                    <Link
                        href={account.security()}
                        aria-current={
                            section === 'security' || section === 'review'
                                ? 'page'
                                : undefined
                        }
                    >
                        Security
                    </Link>
                    <span className="later">Personal tokens</span>
                    <Link
                        href={account.preferences()}
                        aria-current={
                            section === 'preferences' ? 'page' : undefined
                        }
                    >
                        Preferences
                    </Link>
                    <Link
                        href={account.delete()}
                        aria-current={section === 'delete' ? 'page' : undefined}
                    >
                        Delete account
                    </Link>
                </nav>
                <div className="tt-acct flex max-w-[760px] min-w-0 flex-[999_1_560px] flex-col gap-6">
                    {children}
                </div>
            </div>
        </AppShell>
    );
}
