import { Head, Link, router, usePage } from '@inertiajs/react';
import { notify } from '@/lib/notify';
import * as Menu from '@radix-ui/react-dropdown-menu';
import * as Tip from '@radix-ui/react-tooltip';
import type { LucideIcon } from 'lucide-react';
import {
    ChevronsLeft,
    ChevronsRight,
    Cpu,
    FolderGit2,
    Inbox,
    Play,
    Settings,
} from 'lucide-react';
import type { ReactElement, ReactNode } from 'react';
import { useEffect, useEffectEvent, useState } from 'react';
import { index as showProject } from '@/actions/App/Modules/Capture/Http/Controllers/CaptureStreamController';
import AgentPage from '@/actions/App/Modules/Agent/Http/Controllers/AgentPageController';
import RunPage from '@/actions/App/Modules/Proving/Http/Controllers/RunPageController';
import ProjectSettings from '@/actions/App/Modules/Workspace/Http/Controllers/ProjectSettingsController';
import { SupportLink } from '@/components/brand/support-link';
import { PairPanel } from '@/components/agent/pair-panel';
import { SecurityDialog } from '@/components/identity/dialog';
import AppLogoIcon from '@/components/app-logo-icon';
import AppLogoWordmark from '@/components/app-logo-wordmark';
import { Glyph } from '@/components/identity/glyph';
import { RecoveryBanner } from '@/components/identity/recovery-banner';
import { useLayout } from '@/hooks/use-layout';
import { meterLeft } from '@/lib/identity/format';
import { workspaceCopy, workspaceFlashCopy } from '@/lib/workspace/copy';
import { home, login, logout } from '@/routes';
import account from '@/routes/account';
import projects from '@/routes/projects';
import type { Flash } from '@/types/identity';
import type { AgentPill as AgentPillProp } from '@/types/run';
import type { ShellLayout } from '@/types/workspace';

/*
 * S-00 app shell, in the layout the person chose (decision 59):
 * - Top bar: mark · project switcher (guest: meter) · section tabs · URL
 *   chip · agent pill · ⋯ · account.
 * - Sidebar: the same items in a left sidebar, expanded (240 px) or
 *   collapsed to a 56 px icon rail with tooltips.
 * Below 768 px both use the one mobile shell: a top bar and a bottom nav.
 * The layout comes from the server-shared `layout` prop (cookies), so the
 * first paint is right. One `main`, the skip link first, and exactly one
 * visible `nav[aria-label="Main"]` at any width.
 */

const SOON = 'Soon';

type NavItem = {
    key: 'capture' | 'runs' | 'fixtures' | 'agent' | 'settings';
    label: string;
    icon: LucideIcon;
    href?: string;
    current: boolean;
};

/** Section nav for the current project; null when there is none. */
function useSectionNav(): NavItem[] | null {
    const page = usePage();
    const { currentProject } = page.props;
    const path = page.url.split('?')[0];

    if (!currentProject) {
        return null;
    }
    const base = `/p/${currentProject.key}`;

    return [
        {
            key: 'capture',
            label: 'Capture',
            icon: Inbox,
            href: showProject.url(currentProject.key),
            current: path === base,
        },
        // Runs, Fixtures and Agent have no screens yet: shown, not linked.
        {
            key: 'runs',
            label: 'Runs',
            icon: Play,
            href: RunPage.index.url(currentProject.key),
            current: path.startsWith(`${base}/runs`),
        },
        {
            key: 'fixtures',
            label: 'Fixtures',
            icon: FolderGit2,
            current: false,
        },
        {
            key: 'agent',
            label: 'Agent',
            icon: Cpu,
            href:
                page.props.principal.kind === 'user'
                    ? AgentPage.index.url()
                    : AgentPage.guest.url(currentProject.key),
            current: path.startsWith('/agents') || path === `${base}/agent`,
        },
        {
            key: 'settings',
            label: 'Settings',
            icon: Settings,
            href: ProjectSettings.index.url(currentProject.key),
            current: path.startsWith(`${base}/settings`),
        },
    ];
}

/** Tooltip for the collapsed rail; plain children otherwise. */
function RailTip({
    show,
    label,
    children,
}: {
    show: boolean;
    label: string;
    children: ReactElement;
}) {
    if (!show) {
        return children;
    }

    return (
        <Tip.Root>
            <Tip.Trigger asChild>{children}</Tip.Trigger>
            <Tip.Portal>
                <Tip.Content side="right" sideOffset={8} className="tt tt-tip">
                    {label}
                </Tip.Content>
            </Tip.Portal>
        </Tip.Root>
    );
}

function NavLinks({
    items,
    variant,
    rail = false,
}: {
    items: NavItem[];
    variant: 'tabs' | 'side' | 'bottom';
    rail?: boolean;
}) {
    const list =
        variant === 'bottom'
            ? items.filter((i) => i.key !== 'fixtures' && i.key !== 'settings')
            : items;

    return (
        <ul className={`tt-nav tt-nav-${variant}`}>
            {list.map((item) => {
                const Icon = item.icon;
                const body = (
                    <>
                        <Icon className="ic" aria-hidden="true" />
                        <span className="lbl">{item.label}</span>
                        {!item.href && <span className="soon">{SOON}</span>}
                    </>
                );

                return (
                    <li key={item.key}>
                        {item.href ? (
                            <RailTip show={rail} label={item.label}>
                                <Link
                                    className="tt-navitem"
                                    href={item.href}
                                    aria-current={
                                        item.current ? 'page' : undefined
                                    }
                                >
                                    {body}
                                </Link>
                            </RailTip>
                        ) : (
                            // Not focusable: no screen yet. Its name says so.
                            <span
                                className="tt-navitem is-soon"
                                aria-disabled="true"
                                title={`${item.label}: coming in a later slice`}
                            >
                                {body}
                            </span>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

function chipText(url: string): string {
    return url.replace(/^https?:\/\//, '');
}

/** The capture URL with its copy button, or the calm pending state. */
export function CaptureUrlChip({
    url,
    className = '',
    rail = false,
}: {
    url: string | null;
    className?: string;
    rail?: boolean;
}) {
    const [copied, setCopied] = useState(false);

    if (!url) {
        return (
            <RailTip show={rail} label={workspaceCopy.captureUrlPending}>
                <span
                    className={`tt-chip is-pending ${className}`}
                    tabIndex={rail ? 0 : undefined}
                >
                    <Glyph name="wait" />
                    <span className="lbl">
                        {workspaceCopy.captureUrlPending}
                    </span>
                </span>
            </RailTip>
        );
    }

    return (
        <span className={`tt-chip ${className}`}>
            <code className="lbl">{chipText(url)}</code>
            <RailTip show={rail} label="Copy capture URL">
                <button
                    type="button"
                    className="tt-chipbtn"
                    aria-label="Copy capture URL"
                    onClick={() =>
                        void navigator.clipboard
                            .writeText(url)
                            .then(() => {
                                setCopied(true);
                                window.setTimeout(() => setCopied(false), 1500);
                            })
                            .catch(() => setCopied(false))
                    }
                >
                    <Glyph name={copied ? 'check' : 'copy'} />
                </button>
            </RailTip>
            <span className="tt-sr" role="status">
                {copied ? 'Capture URL copied.' : ''}
            </span>
        </span>
    );
}

/** S-00 agent pill. The Agent slice adds pairing and the popover. */
const PILL_WORDS: Record<AgentPillProp['state'], string> = {
    none: 'No agent',
    awaiting_confirmation: 'confirm in terminal',
    connected: '',
    disconnected: 'offline',
    too_old: 'update needed',
};

/**
 * S-00 agent pill (shared `agentPill`): "No agent", "maya-mbp",
 * "maya-mbp · confirm in terminal", "maya-mbp · offline",
 * "maya-mbp · update needed". It opens the agent page (users: /agents;
 * guests: the project's agent page); with no agent and no page to go to,
 * it opens the pair panel.
 */
function AgentPill({ rail = false }: { rail?: boolean }) {
    const { agentPill, principal, currentProject } = usePage().props;
    const [open, setOpen] = useState(false);

    if (!agentPill) {
        return null;
    }
    const label =
        agentPill.state === 'none' || !agentPill.name
            ? PILL_WORDS.none
            : [agentPill.name, PILL_WORDS[agentPill.state]]
                  .filter(Boolean)
                  .join(' · ');
    const href =
        principal.kind === 'user'
            ? agentPill.state === 'none'
                ? AgentPage.pair.url()
                : AgentPage.index.url()
            : currentProject
              ? AgentPage.guest.url(currentProject.key)
              : null;
    const body = (
        <>
            <span className="dot" aria-hidden="true" />
            <span className="lbl">{label}</span>
        </>
    );
    const tip = `Agent: ${label}${agentPill.version ? ` · ${agentPill.version}` : ''}`;

    if (href) {
        return (
            <RailTip show={rail} label={tip}>
                <Link
                    href={href}
                    className="tt-pill"
                    data-state={agentPill.state}
                    aria-label={tip}
                >
                    {body}
                </Link>
            </RailTip>
        );
    }

    return (
        <>
            <RailTip show={rail} label={tip}>
                <button
                    type="button"
                    className="tt-pill"
                    data-state={agentPill.state}
                    aria-label={`${tip}. Pair an agent`}
                    aria-haspopup="dialog"
                    onClick={() => setOpen(true)}
                >
                    {body}
                </button>
            </RailTip>
            <SecurityDialog
                open={open}
                onOpenChange={setOpen}
                title="Pair an agent"
            >
                <PairPanel agent={null} />
            </SecurityDialog>
        </>
    );
}

function ProjectSwitcher({ rail = false }: { rail?: boolean }) {
    const { currentProject, projectSwitcher = [] } = usePage().props;
    const label = currentProject?.name ?? 'Projects';

    return (
        <Menu.Root modal={false}>
            <RailTip show={rail} label={`${label} · Switch project`}>
                <Menu.Trigger asChild>
                    <button
                        type="button"
                        className="tt-switcher"
                        aria-label={`Project: ${label}. Switch project`}
                    >
                        <span className="sq" aria-hidden="true">
                            {label.slice(0, 1).toUpperCase()}
                        </span>
                        <span className="nm lbl">{label}</span>
                        <span className="lbl" aria-hidden="true">
                            ▾
                        </span>
                    </button>
                </Menu.Trigger>
            </RailTip>
            <Menu.Portal>
                <Menu.Content
                    align="start"
                    side={rail ? 'right' : 'bottom'}
                    sideOffset={6}
                    className="tt tt-menu"
                >
                    <Menu.Label className="grp">Projects</Menu.Label>
                    {projectSwitcher.map((p) => (
                        <Menu.Item key={p.key} asChild>
                            <Link
                                className="tt-menu-item"
                                href={showProject(p.key)}
                                aria-current={
                                    p.key === currentProject?.key
                                        ? 'page'
                                        : undefined
                                }
                            >
                                <span className="tt-menu-mark">
                                    {p.key === currentProject?.key && (
                                        <Glyph name="check" />
                                    )}
                                </span>
                                {p.name}
                            </Link>
                        </Menu.Item>
                    ))}
                    <hr />
                    <Menu.Item asChild>
                        <Link className="tt-menu-item" href={projects.create()}>
                            <span className="tt-menu-mark" aria-hidden="true">
                                +
                            </span>
                            New project
                        </Link>
                    </Menu.Item>
                </Menu.Content>
            </Menu.Portal>
        </Menu.Root>
    );
}

function MoreMenu({ className = '' }: { className?: string }) {
    const { currentProject, principal } = usePage().props;
    const guest = principal.kind === 'guest';

    return (
        <Menu.Root modal={false}>
            <Menu.Trigger asChild>
                <button
                    type="button"
                    className={`tt-iconbtn max-sm:size-11 ${className}`}
                    aria-label="More"
                >
                    ⋯
                </button>
            </Menu.Trigger>
            <Menu.Portal>
                <Menu.Content align="end" sideOffset={6} className="tt tt-menu">
                    {currentProject && (
                        <Menu.Item asChild>
                            <Link
                                className="tt-menu-item"
                                href={ProjectSettings.index(currentProject.key)}
                            >
                                {guest
                                    ? 'Settings › End session now'
                                    : 'Settings'}
                            </Link>
                        </Menu.Item>
                    )}
                    <Menu.Item asChild>
                        <a className="tt-menu-item" href="#" data-todo="help">
                            Help
                        </a>
                    </Menu.Item>
                    <Menu.Item asChild>
                        <a
                            className="tt-menu-item"
                            href="https://docs.webhooktrap.dev"
                        >
                            Docs
                        </a>
                    </Menu.Item>
                    <hr />
                    <Menu.Item asChild>
                        <SupportLink className="tt-menu-item" />
                    </Menu.Item>
                </Menu.Content>
            </Menu.Portal>
        </Menu.Root>
    );
}

function initials(name: string): string {
    return (
        name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase())
            .join('') || '·'
    );
}

function AccountMenu({
    side = false,
    rail = false,
}: {
    side?: boolean;
    rail?: boolean;
}) {
    const { principal } = usePage().props;
    const { shell, setShell } = useLayout();

    if (principal.kind !== 'user') {
        return (
            <Link className="tt-btn tt-btn-sm" href={login()}>
                Sign in
            </Link>
        );
    }
    const name = principal.name || principal.email;

    return (
        <Menu.Root modal={false}>
            <RailTip show={rail} label={`Account: ${name}`}>
                <Menu.Trigger asChild>
                    <button
                        type="button"
                        className={
                            side
                                ? 'tt-sideaccount'
                                : 'tt-iconbtn tt-avatar max-sm:size-11'
                        }
                        aria-label={`Account: ${name}`}
                    >
                        <span className="av" aria-hidden="true">
                            {initials(name)}
                        </span>
                        {side && <span className="lbl">{name}</span>}
                    </button>
                </Menu.Trigger>
            </RailTip>
            <Menu.Portal>
                <Menu.Content
                    align="end"
                    side={side ? 'right' : 'bottom'}
                    sideOffset={6}
                    className="tt tt-menu"
                >
                    <Menu.Item asChild>
                        <Link
                            className="tt-menu-item"
                            href={account.security()}
                        >
                            Account
                        </Link>
                    </Menu.Item>
                    {!principal.twoFactor && (
                        <Menu.Item asChild>
                            <Link
                                className="tt-menu-item"
                                href={account.security()}
                            >
                                Turn on two-step sign-in
                            </Link>
                        </Menu.Item>
                    )}
                    <hr />
                    <Menu.Label className="grp">Layout</Menu.Label>
                    <Menu.RadioGroup
                        value={shell}
                        onValueChange={(v) => setShell(v as ShellLayout)}
                    >
                        {(
                            [
                                ['topbar', 'Top bar'],
                                ['sidebar', 'Sidebar'],
                            ] as const
                        ).map(([value, label]) => (
                            <Menu.RadioItem
                                key={value}
                                value={value}
                                className="tt-menu-item"
                            >
                                <span className="tt-menu-mark">
                                    <Menu.ItemIndicator>
                                        <Glyph name="check" />
                                    </Menu.ItemIndicator>
                                </span>
                                {label}
                            </Menu.RadioItem>
                        ))}
                    </Menu.RadioGroup>
                    <Menu.Item asChild>
                        <Link
                            className="tt-menu-item"
                            href={account.preferences()}
                        >
                            <span className="tt-menu-mark" />
                            Preferences
                        </Link>
                    </Menu.Item>
                    <hr />
                    <Menu.Item
                        className="tt-menu-item"
                        onSelect={() => router.post(logout.url())}
                    >
                        Sign out
                    </Menu.Item>
                </Menu.Content>
            </Menu.Portal>
        </Menu.Root>
    );
}

function GuestMeter({ compact = false }: { compact?: boolean }) {
    const { principal, meter } = usePage().props;

    if (principal.kind !== 'guest' || !meter) {
        return null;
    }
    const left = meterLeft(meter.expiresAt);
    const full = `Guest session · expires in ${left} · ${meter.captures.used}/${meter.captures.limit} deliveries · ${meter.runs.used}/${meter.runs.limit} runs`;
    // S-00 compact meter (390): "Guest · 23 h 49 m · 13/100 · 1/10 runs".
    const short = `Guest · ${left} · ${meter.captures.used}/${meter.captures.limit} · ${meter.runs.used}/${meter.runs.limit} runs`;

    return (
        <p className="tt-meter">
            {compact ? (
                short
            ) : (
                <>
                    {full} · <Link href={login()}>Sign in to keep</Link>
                </>
            )}
        </p>
    );
}

function Brand({ wordmark = false }: { wordmark?: boolean }) {
    return (
        <Link className="tt-brand" href={home()} aria-label="Webhook Trap home">
            <AppLogoIcon className="size-5" />
            {wordmark && <AppLogoWordmark className="lbl h-[13px] w-auto" />}
        </Link>
    );
}

function Sidebar({ nav }: { nav: NavItem[] | null }) {
    const { principal, currentProject } = usePage().props;
    const { sidebar, setSidebar } = useLayout();
    const rail = sidebar === 'collapsed';
    const guest = principal.kind === 'guest';

    return (
        <aside
            id="app-sidebar"
            className="tt-side"
            data-collapsed={rail || undefined}
            aria-label="Sidebar"
        >
            <div className="tt-side-top">
                <Brand wordmark />
                {guest ? (
                    <RailTip show={rail} label="Guest session">
                        <div
                            className="tt-side-meter"
                            tabIndex={rail ? 0 : undefined}
                        >
                            <GuestMeter compact />
                        </div>
                    </RailTip>
                ) : (
                    principal.kind === 'user' && <ProjectSwitcher rail={rail} />
                )}
            </div>
            {nav && (
                <nav aria-label="Main" className="tt-side-nav">
                    <NavLinks items={nav} variant="side" rail={rail} />
                </nav>
            )}
            {currentProject && (
                <div className="tt-side-chip">
                    <CaptureUrlChip
                        url={currentProject.captureUrl}
                        rail={rail}
                    />
                </div>
            )}
            <div className="tt-side-bottom">
                <AgentPill rail={rail} />
                <AccountMenu side rail={rail} />
                <button
                    type="button"
                    className="tt-collapse"
                    aria-expanded={!rail}
                    aria-controls="app-sidebar"
                    aria-label={rail ? 'Expand sidebar' : 'Collapse sidebar'}
                    onClick={() => setSidebar(rail ? 'expanded' : 'collapsed')}
                >
                    {rail ? (
                        <ChevronsRight className="ic" aria-hidden="true" />
                    ) : (
                        <ChevronsLeft className="ic" aria-hidden="true" />
                    )}
                    <span className="lbl">Collapse</span>
                </button>
            </div>
        </aside>
    );
}

export function AppShell({
    title,
    children,
    toastFor,
    wide = false,
}: {
    title: string;
    children: ReactNode;
    /** Full-bleed working screen (S-03): no centred column, no padding. */
    wide?: boolean;
    /** Page-specific toast copy for a flash; falls back to the shared copy. */
    toastFor?: (flash: Flash) => string | null | undefined;
}) {
    const { principal, currentProject, flash } = usePage().props;
    const { shell, sidebar } = useLayout();
    const nav = useSectionNav();
    const user = principal.kind === 'user';

    const announce = useEffectEvent((current: Flash) => {
        const text = toastFor?.(current) ?? workspaceFlashCopy(current);

        if (text) {
            notify.flash(current, text);
        }
    });

    useEffect(() => {
        if (flash) {
            announce(flash);
        }
    }, [flash]);

    return (
        <Tip.Provider delayDuration={200}>
            <div
                className="tt tt-shell"
                data-layout={shell}
                data-sidebar={sidebar}
            >
                <Head title={title} />
                <a href="#main" className="tt-skip">
                    Skip to content
                </a>
                {shell === 'sidebar' && <Sidebar nav={nav} />}
                <div className="tt-shell-body">
                    <header className="tt-topbar tt-appbar">
                        <Brand />
                        {user && <ProjectSwitcher />}
                        <div className="max-sm:hidden">
                            <GuestMeter />
                        </div>
                        <div className="sm:hidden">
                            <GuestMeter compact />
                        </div>
                        {nav && (
                            <nav aria-label="Main" className="tt-topnav">
                                <NavLinks items={nav} variant="tabs" />
                            </nav>
                        )}
                        <span className="tt-spacer" />
                        {currentProject && (
                            <CaptureUrlChip
                                url={currentProject.captureUrl}
                                className="max-lg:hidden"
                            />
                        )}
                        <span className="max-md:hidden">
                            <AgentPill />
                        </span>
                        <MoreMenu />
                        <AccountMenu />
                    </header>
                    <RecoveryBanner />
                    <main
                        id="main"
                        className={wide ? 'tt-appmain is-wide' : 'tt-appmain'}
                    >
                        {children}
                    </main>
                </div>
                {nav && (
                    <nav aria-label="Main" className="tt-bottomnav">
                        <NavLinks items={nav} variant="bottom" />
                        <MoreMenu className="tt-bottommore" />
                    </nav>
                )}
            </div>
        </Tip.Provider>
    );
}
