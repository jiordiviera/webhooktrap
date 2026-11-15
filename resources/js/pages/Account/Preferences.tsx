import { useState } from 'react';
import { AccountShell } from '@/components/identity/account-shell';
import { PageHeading } from '@/components/identity/bare-shell';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useLayout } from '@/hooks/use-layout';
import type { ShellLayout, SidebarState } from '@/types/workspace';

/*
 * S-17 · Preferences (decision 59): layout, sidebar default and appearance.
 * Every choice applies at once and is kept on this device (a cookie, like
 * appearance); syncing across devices comes later. Native radios inside
 * fieldsets: arrow keys move, labels name each card.
 */

/** Small drawing of each layout, for the radio cards. Decorative. */
function Preview({ kind }: { kind: ShellLayout }) {
    return (
        <svg
            className="tt-preview"
            viewBox="0 0 120 72"
            aria-hidden="true"
            focusable="false"
        >
            <rect
                x="0.5"
                y="0.5"
                width="119"
                height="71"
                rx="3"
                className="frame"
            />
            {kind === 'topbar' ? (
                <>
                    <rect
                        x="0.5"
                        y="0.5"
                        width="119"
                        height="12"
                        className="bar"
                    />
                    <rect x="6" y="4.5" width="5" height="4" className="mark" />
                    <rect x="16" y="5" width="16" height="3" className="ink" />
                    <rect x="38" y="5" width="10" height="3" className="dim" />
                    <rect x="52" y="5" width="10" height="3" className="dim" />
                    <rect x="66" y="5" width="10" height="3" className="dim" />
                    <rect x="12" y="22" width="60" height="5" className="ink" />
                    <rect
                        x="12"
                        y="32"
                        width="96"
                        height="30"
                        rx="2"
                        className="panel"
                    />
                </>
            ) : (
                <>
                    <rect
                        x="0.5"
                        y="0.5"
                        width="30"
                        height="71"
                        className="bar"
                    />
                    <rect x="5" y="5" width="5" height="4" className="mark" />
                    <rect x="5" y="15" width="20" height="3" className="ink" />
                    <rect x="5" y="24" width="16" height="3" className="dim" />
                    <rect x="5" y="31" width="16" height="3" className="dim" />
                    <rect x="5" y="38" width="16" height="3" className="dim" />
                    <rect x="38" y="10" width="44" height="5" className="ink" />
                    <rect
                        x="38"
                        y="22"
                        width="74"
                        height="40"
                        rx="2"
                        className="panel"
                    />
                </>
            )}
        </svg>
    );
}

function RadioCards<T extends string>({
    legend,
    name,
    value,
    options,
    onChange,
}: {
    legend: string;
    name: string;
    value: T;
    options: {
        value: T;
        label: string;
        hint?: string;
        preview?: ShellLayout;
    }[];
    onChange: (value: T) => void;
}) {
    return (
        <fieldset className="tt-radiocards">
            <legend className="tt-label">{legend}</legend>
            <div className="cards">
                {options.map((o) => (
                    <label key={o.value} className="tt-radiocard">
                        <input
                            type="radio"
                            name={name}
                            value={o.value}
                            checked={value === o.value}
                            onChange={() => onChange(o.value)}
                        />
                        {o.preview && <Preview kind={o.preview} />}
                        <span className="t">{o.label}</span>
                        {o.hint && <span className="h">{o.hint}</span>}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

export default function Preferences() {
    const { shell, sidebar, setShell, setSidebar } = useLayout();
    const { appearance, updateAppearance } = useAppearance();
    const [saved, setSaved] = useState('');
    const confirm = () => setSaved('Saved on this device.');

    return (
        <AccountShell title="Preferences" section="preferences">
            <PageHeading>Preferences</PageHeading>
            {/* COPY PROPOSAL (A07): the per-device line. */}
            <p className="tt-muted">These choices are kept on this device.</p>

            <section className="tt-sec" aria-labelledby="h-layout">
                <h2 id="h-layout">Layout</h2>
                <RadioCards<ShellLayout>
                    legend="App layout"
                    name="layout"
                    value={shell}
                    options={[
                        {
                            value: 'topbar',
                            label: 'Top bar',
                            hint: 'Project, sections and URL in one bar.',
                            preview: 'topbar',
                        },
                        {
                            value: 'sidebar',
                            label: 'Sidebar',
                            hint: 'The same items in a left sidebar.',
                            preview: 'sidebar',
                        },
                    ]}
                    onChange={(v) => {
                        setShell(v);
                        confirm();
                    }}
                />
                <RadioCards<SidebarState>
                    legend="Sidebar"
                    name="sidebar"
                    value={sidebar}
                    options={[
                        {
                            value: 'expanded',
                            label: 'Expanded',
                            hint: 'Icons and names.',
                        },
                        {
                            value: 'collapsed',
                            label: 'Collapsed',
                            hint: 'Icons only, names on hover and focus.',
                        },
                    ]}
                    onChange={(v) => {
                        setSidebar(v);
                        confirm();
                    }}
                />
            </section>

            <section className="tt-sec" aria-labelledby="h-appearance">
                <h2 id="h-appearance">Appearance</h2>
                <RadioCards<Appearance>
                    legend="Theme"
                    name="appearance"
                    value={appearance}
                    options={[
                        { value: 'dark', label: 'Dark' },
                        { value: 'light', label: 'Light' },
                        {
                            value: 'system',
                            label: 'System',
                            hint: 'Follows this device.',
                        },
                    ]}
                    onChange={(v) => {
                        updateAppearance(v);
                        confirm();
                    }}
                />
            </section>
            <p className="tt-sr" role="status">
                {saved}
            </p>
        </AccountShell>
    );
}
