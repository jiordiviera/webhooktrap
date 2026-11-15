/** Workspace props (A05 contracts §3.1 `currentProject`, §3.2; slice 2 notes). */

/** Shared on `/p/{projectKey}/…` for the owner. `captureUrl` is null until the Capture slice. */
export type CurrentProject = {
    key: string;
    name: string;
    captureUrl: string | null;
};

/** Shared for signed-in users: the top-bar project switcher (S-00). */
export type ProjectSwitcherItem = { key: string; name: string };

/** S-15 row. Counts and activity arrive with the Capture and Proving slices. */
export type ProjectRow = {
    key: string;
    name: string;
    captureUrl: string | null;
    deliveries: number | null;
    lastActivityAt: string | null;
    lastRunStatus: string | null;
    createdAt: string;
};

export type SettingsSection = 'capture' | 'ci' | 'budgets' | 'danger';

/** S-16, every section. */
export type ProjectSettingsProps = {
    section: SettingsSection;
    sections: SettingsSection[];
    project: {
        key: string;
        name: string;
        isGuest: boolean;
        captureUrl: string | null;
        captureRetentionDays: number;
        runRetentionDays: number;
        defaultVisibility: 'private' | 'link';
    };
};

export type RetentionOptions = { captureDays: number[]; runDays: number[] };

/** Decision 59: per-device app layout, shared from the `layout` cookies. */
export type ShellLayout = 'topbar' | 'sidebar';
export type SidebarState = 'expanded' | 'collapsed';
export type LayoutPreference = { shell: ShellLayout; sidebar: SidebarState };
