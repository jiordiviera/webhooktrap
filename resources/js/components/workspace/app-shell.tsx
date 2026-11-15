import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { index as showProject } from '@/actions/App/Modules/Capture/Http/Controllers/CaptureStreamController';
import ProjectSettings from '@/actions/App/Modules/Workspace/Http/Controllers/ProjectSettingsController';

/*
 * Workspace page frames. The app shell itself (S-00, both layouts of
 * decision 59) lives in components/shell/app-shell.tsx.
 */
export { AppShell, CaptureUrlChip } from '@/components/shell/app-shell';

export const SECTION_LABELS: Record<string, string> = {
    capture: 'Capture URL',
    ci: 'CI tokens',
    budgets: 'Budgets',
    danger: 'Danger',
};

/**
 * S-16 frame: "Brindle Coffee › Settings" heading, the section list (left on
 * desktop, a row of links on mobile), then the page.
 */
export function SettingsLayout({
    projectName,
    projectKey,
    sections,
    current,
    children,
}: {
    projectName: string;
    projectKey: string;
    sections: string[];
    current: string;
    children: ReactNode;
}) {
    return (
        <div className="tt-settings">
            <p className="tt-crumb">
                <Link href={showProject(projectKey)}>{projectName}</Link>
                <span aria-hidden="true"> › </span>Settings
            </p>
            <div className="tt-settings-grid">
                <nav className="tt-sidenav" aria-label="Project settings">
                    {sections.map((s) => (
                        <Link
                            key={s}
                            href={ProjectSettings.show({
                                projectKey,
                                section: s,
                            })}
                            aria-current={s === current ? 'page' : undefined}
                        >
                            {SECTION_LABELS[s] ?? s}
                        </Link>
                    ))}
                </nav>
                <div className="tt-acct tt-settings-body">{children}</div>
            </div>
        </div>
    );
}
