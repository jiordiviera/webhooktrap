import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import AppLogoWordmark from '@/components/app-logo-wordmark';
import { home, login } from '@/routes';
import { index as projects } from '@/routes/projects';
import { GetUrlButton, LandingButton } from './landing-button';

const links = [
    { label: 'Docs', href: 'https://docs.webhooktrap.dev' },
    {
        label: 'Self-host',
        href: 'https://docs.webhooktrap.dev/guides/self-hosting',
    },
    { label: 'GitHub', href: 'https://github.com/jiordiviera/webhooktrap' },
];

export function LandingHeader() {
    const { auth } = usePage<{ auth?: { user?: unknown } }>().props;
    const signedIn = Boolean(auth?.user);

    return (
        <header className="mx-auto flex h-[72px] w-full max-w-[1280px] items-center gap-6 border-b border-lp-rule-soft px-4 sm:px-7">
            <a
                href={home().url}
                className="flex items-center gap-2.5 text-lp-ink no-underline"
                aria-label="Webhook Trap home"
            >
                <AppLogoIcon className="size-6" />
                <AppLogoWordmark className="h-[14px] w-auto" />
            </a>
            <nav
                aria-label="Main"
                className="ml-auto flex items-center gap-[26px] text-[13px]"
            >
                {links.map((l) => (
                    <a
                        key={l.label}
                        href={l.href}
                        rel="noopener"
                        className="text-lp-muted no-underline hover:text-lp-ink max-sm:hidden"
                    >
                        {l.label}
                    </a>
                ))}
                {signedIn ? (
                    <LandingButton href={projects().url} size="sm">
                        Open app
                    </LandingButton>
                ) : (
                    <>
                        <a
                            href={login().url}
                            className="text-lp-muted no-underline hover:text-lp-ink max-sm:min-h-11 max-sm:content-center"
                        >
                            Sign in
                        </a>
                        <GetUrlButton short />
                    </>
                )}
            </nav>
        </header>
    );
}
