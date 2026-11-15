import {
    GitHubLogo,
    ShopifyLogo,
    StripeLogo,
} from '@/components/brand/provider-logos';
import { PageHeading } from '@/components/identity/bare-shell';
import { AppShell, SettingsLayout } from '@/components/workspace/app-shell';
import { comingProviders, providerBudgets } from '@/lib/workspace/budgets';
import type { ProjectSettingsProps } from '@/types';

const logos = {
    stripe: StripeLogo,
    shopify: ShopifyLogo,
    github: GitHubLogo,
    standard: null,
} as const;

/** S-16 · Budgets: read-only provider table (RD `providers`). */
export default function Budgets({
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
                <PageHeading>Budgets</PageHeading>
                <section className="tt-sec" aria-labelledby="h-budgets">
                    <h2 id="h-budgets">Response-time budgets</h2>
                    <table className="tt-keytbl">
                        <thead>
                            <tr>
                                <th scope="col">Provider</th>
                                <th scope="col">Budget</th>
                                <th scope="col">Source</th>
                                <th scope="col">Tolerance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {providerBudgets.map((b) => {
                                const Logo = logos[b.provider];

                                return (
                                    <tr key={b.provider}>
                                        <th scope="row">
                                            <span className="tt-row">
                                                {Logo && (
                                                    <Logo className="tt-g tt-g-16" />
                                                )}
                                                {b.name}
                                            </span>
                                        </th>
                                        <td>{b.label}</td>
                                        <td>{b.source}</td>
                                        <td>{b.tolerance}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                    <p className="tt-hint">
                        Coming: {comingProviders.join(', ')}
                    </p>
                </section>
            </SettingsLayout>
        </AppShell>
    );
}
