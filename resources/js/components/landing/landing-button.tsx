import { router, usePage } from '@inertiajs/react';
import { useState, type AnchorHTMLAttributes } from 'react';
import { store as startGuest } from '@/actions/App/Modules/Workspace/Http/Controllers/GuestWorkspaceController';
import { cn } from '@/lib/utils';
import projects from '@/routes/projects';

type Props = AnchorHTMLAttributes<HTMLAnchorElement> & {
    size?: 'sm' | 'lg';
};

const buttonClass = (size: 'sm' | 'lg', className?: string) =>
    cn(
        'inline-flex items-center justify-center gap-2.5 rounded-[3px] border border-lp-ink bg-lp-ink font-bold whitespace-nowrap text-lp-bg no-underline hover:border-lp-mag hover:bg-lp-mag hover:text-lp-mag-ink',
        size === 'lg' ? 'h-12 px-5 text-sm' : 'h-9 px-3.5 text-[12.5px]',
        className,
    );

/** Terminal-direction button, rendered as a link: ink block, magenta on hover. */
export function LandingButton({ size = 'lg', className, ...props }: Props) {
    return <a className={buttonClass(size, className)} {...props} />;
}

/**
 * The one CTA of S-01. A visitor starts a guest workspace (POST /new, which
 * also sends a live guest back to their project); a signed-in user goes to
 * their projects.
 */
export function GetUrlButton({
    className,
    short = false,
}: {
    className?: string;
    short?: boolean;
}) {
    const { principal } = usePage().props;
    const [busy, setBusy] = useState(false);
    const size = short ? 'sm' : 'lg';
    const label = short ? 'Get a URL' : 'Get a URL, no sign-up';

    if (principal.kind === 'user') {
        return (
            <LandingButton
                href={projects.index().url}
                size={size}
                className={className}
            >
                {short ? 'Open app' : 'Open your projects'}
            </LandingButton>
        );
    }

    return (
        <button
            type="button"
            disabled={busy}
            aria-busy={busy || undefined}
            className={buttonClass(size, cn('cursor-pointer', className))}
            onClick={() =>
                router.post(
                    startGuest.url(),
                    {},
                    {
                        onStart: () => setBusy(true),
                        onFinish: () => setBusy(false),
                    },
                )
            }
        >
            {label}
        </button>
    );
}

export const AGENT_COMMAND = 'npx @webhooktrap/agent';

/** `$ npx @webhooktrap/agent` with a Copy button. */
export function CopyCommand({ className }: { className?: string }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(AGENT_COMMAND);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard unavailable: the command stays selectable text.
        }
    }

    return (
        <div
            className={cn(
                'flex h-[52px] min-w-0 items-center rounded-[3px] border border-lp-rule bg-lp-win pl-4 text-sm',
                className,
            )}
        >
            <span aria-hidden="true" className="mr-2.5 text-lp-mag">
                $
            </span>
            <code className="min-w-0 flex-1 truncate font-[inherit] select-all">
                {AGENT_COMMAND}
            </code>
            <button
                type="button"
                onClick={copy}
                aria-label={`Copy ${AGENT_COMMAND}`}
                className="h-full border-l border-lp-rule px-4 text-[12.5px] font-semibold text-lp-ink hover:bg-lp-raise focus-visible:outline-offset-[-3px]"
            >
                {copied ? 'Copied' : 'Copy'}
            </button>
            <span role="status" className="sr-only">
                {copied ? 'Copied' : ''}
            </span>
        </div>
    );
}
