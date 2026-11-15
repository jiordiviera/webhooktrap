import { Link, usePage } from '@inertiajs/react';
import { BareShell, PageHeading } from '@/components/identity/bare-shell';
import { Button, buttonClass } from '@/components/identity/form';
import { copy } from '@/lib/identity/copy';
import { home, login } from '@/routes';
import projects from '@/routes/projects';

/*
 * S-18 · System pages for HTTP errors (F-06): bare shell, focus to the h1,
 * one primary action. The server sends the status only (ErrorPages), so a
 * not-found page holds nothing about the resource asked for (IA5, R59).
 * Shared props are missing when the URL matched no route, hence the
 * optional reads.
 */

type Status = 403 | 404 | 419 | 429 | 500 | 503;

const PAGES: Record<Status, { title: string; body: string; code: string }> = {
    403: {
        title: 'Not allowed',
        body: "You don't have access to this page.",
        code: 'forbidden',
    },
    404: {
        title: 'Not found',
        body: "This page doesn't exist or isn't shared with you.",
        code: 'not found',
    },
    419: {
        title: 'Page expired',
        body: 'This page was open too long. Reload it and try again.',
        code: 'page expired',
    },
    429: {
        title: 'Too many requests',
        body: 'Wait a minute, then try again.',
        code: 'too many requests',
    },
    500: {
        title: 'Server error',
        body: copy.serverError,
        code: 'server error',
    },
    503: {
        title: 'Down for maintenance',
        body: "We're updating Webhook Trap. Try again in a few minutes.",
        code: 'unavailable',
    },
};

export default function ErrorPage({ status }: { status: Status }) {
    const { principal } = usePage().props as {
        principal?: { kind: string };
    };
    const page = PAGES[status] ?? PAGES[500];
    // Unknown when the URL matched no route (no session, no shared props).
    const kind = principal?.kind ?? 'unknown';
    const retry = status === 419 || status === 429 || status >= 500;

    const homeLink =
        kind === 'user' ? (
            <Link
                href={projects.index()}
                className={
                    retry
                        ? undefined
                        : buttonClass({ variant: 'primary', size: 'lg' })
                }
            >
                Go to projects
            </Link>
        ) : (
            <Link
                href={home()}
                className={
                    retry
                        ? undefined
                        : buttonClass({ variant: 'primary', size: 'lg' })
                }
            >
                Go home
            </Link>
        );

    return (
        <BareShell
            title={page.title}
            command={`exit ${status} · ${page.code}`}
            proof={false}
        >
            <PageHeading>{page.title}</PageHeading>
            <p>{page.body}</p>
            <div className="tt-actions">
                {retry && (
                    <Button
                        variant="primary"
                        size="lg"
                        onClick={() => window.location.reload()}
                    >
                        {status === 419 ? 'Reload' : 'Retry'}
                    </Button>
                )}
                {homeLink}
                {kind === 'anonymous' && status !== 503 && (
                    <Link href={login()}>Sign in</Link>
                )}
            </div>
        </BareShell>
    );
}
