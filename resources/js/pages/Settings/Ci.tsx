import { PageHeading } from '@/components/identity/bare-shell';
import { AppShell, SettingsLayout } from '@/components/workspace/app-shell';
import { workspaceCopy } from '@/lib/workspace/copy';
import type { ProjectSettingsProps } from '@/types';

/** S-16 · CI tokens. Empty state until CI tokens ship (New token, Revoke). */
export default function Ci({
    project,
    sections,
    section,
}: ProjectSettingsProps) {
    return (
        <AppShell title={`${project.name} · Settings`}>
            <SettingsLayout
                projectName={project.name}
                projectKey={project.key}
                sections={sections}
                current={section}
            >
                <PageHeading>CI tokens</PageHeading>
                <section className="tt-sec" aria-labelledby="h-ci">
                    <h2 id="h-ci">Tokens</h2>
                    <p>{workspaceCopy.ciEmpty}</p>
                </section>
            </SettingsLayout>
        </AppShell>
    );
}
