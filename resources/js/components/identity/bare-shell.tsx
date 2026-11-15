import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect, useRef } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import AppLogoWordmark from '@/components/app-logo-wordmark';
import { flashCopy } from '@/lib/identity/copy';
import { home } from '@/routes';
import { AuthProof } from './auth-proof';
import { FlashNote } from './form';

/*
 * Bare shell (NAV §1.2) for sign-in, account creation, the two-step
 * challenge, confirm-auth, recovery and every emailed-link page: skip link,
 * the mark, one narrow centred column. Layout (PO direction after decision
 * 57): a calm main column (~75%) with the form centred in it, and a quiet
 * side panel (~25%, hidden below 1024 px) with our own proof: a static run
 * excerpt and the providers we work with. Focus goes to the h1 on load.
 */

/**
 * `~/webhooktrap $ sign-in` from the page path. Only plain word segments are
 * kept, so tokens in emailed-link paths never show.
 */
function promptCommand(url: string): string {
    const words = url
        .split('?')[0]
        .split('/')
        .filter((part) => /^[a-z][a-z-]*$/.test(part));

    return words.join(' ') || 'webhooktrap';
}

export function BareShell({
    title,
    aside,
    command,
    proof = true,
    children,
}: {
    title: string;
    aside?: ReactNode;
    /** Prompt line after `~/webhooktrap $`; defaults to the page path. */
    command?: string;
    /** The side panel with our run excerpt (off on the system pages). */
    proof?: boolean;
    children: ReactNode;
}) {
    const { url } = usePage();

    return (
        <div className={proof ? 'tt tt-auth' : 'tt tt-auth tt-auth-solo'}>
            <Head title={title} />
            <a href="#main" className="tt-skip">
                Skip to content
            </a>
            <div className="tt-authmain">
                {aside && <header className="tt-authtop">{aside}</header>}
                <main id="main" className="tt-authpage">
                    <div className="tt-authcol">
                        <div className="tt-authbrand">
                            <Link
                                className={
                                    proof
                                        ? 'tt-authmark'
                                        : 'tt-authmark tt-authmark-wide'
                                }
                                href={home()}
                                aria-label="Webhook Trap home"
                            >
                                <AppLogoIcon className="size-5" />
                                {!proof && (
                                    <AppLogoWordmark className="h-[13px] w-auto" />
                                )}
                            </Link>
                            <p className="tt-eyebrow" aria-hidden="true">
                                <span className="ps">~/webhooktrap $</span>{' '}
                                {command ?? promptCommand(url)}
                            </p>
                        </div>
                        {children}
                    </div>
                </main>
            </div>
            {proof && <AuthProof />}
        </div>
    );
}

/** The page heading; takes focus on load so screen readers start here. */
export function PageHeading({ children }: { children: ReactNode }) {
    const ref = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        ref.current?.focus();
    }, []);

    return (
        <h1 ref={ref} tabIndex={-1}>
            {children}
        </h1>
    );
}

/** Ruled flash from the server, shown once at the top of a bare page. */
export function PageFlash() {
    const { flash } = usePage().props;
    const text = flashCopy(flash);

    return text ? <FlashNote>{text}</FlashNote> : null;
}
