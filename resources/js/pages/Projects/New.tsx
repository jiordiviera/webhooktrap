import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { PageHeading } from '@/components/identity/bare-shell';
import { Button, Field } from '@/components/identity/form';
import { AppShell } from '@/components/workspace/app-shell';
import { useClientValidation } from '@/hooks/use-client-validation';
import { projectNameRule } from '@/lib/workspace/copy';
import projects from '@/routes/projects';

/** S-15 · New project: "Project name" (default "My project"), Create → S-03. */
export default function New({ defaultName }: { defaultName: string }) {
    const form = useForm({ name: defaultName });
    const checks = useClientValidation({
        name: { id: 'name', label: 'Project name', rule: projectNameRule },
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        if (
            checks.check(event.currentTarget, (values) =>
                form.setData('name', values.name),
            )
        ) {
            // A server refusal focuses the field, as on the identity forms (F-12).
            form.post(projects.store.url(), {
                onError: (errors) => checks.focusFirst(errors),
            });
        }
    };

    return (
        <AppShell title="New project">
            <div className="tt-page tt-page-narrow">
                <PageHeading>New project</PageHeading>
                <form
                    className="tt-sec tt-sec-plain"
                    aria-label="New project"
                    onSubmit={submit}
                    noValidate
                >
                    <Field
                        id="name"
                        name="name"
                        label="Project name"
                        autoComplete="off"
                        maxLength={120}
                        value={form.data.name}
                        error={checks.error('name', form.errors.name)}
                        {...checks.bind('name', (v) => form.setData('name', v))}
                    />
                    <div>
                        <Button
                            type="submit"
                            variant="primary"
                            busy={form.processing}
                            aria-disabled={form.processing || undefined}
                        >
                            {form.processing ? 'Creating…' : 'Create'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppShell>
    );
}
