import { router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import CaptureUrl from '@/actions/App/Modules/Capture/Http/Controllers/CaptureUrlController';
import ProjectSettings from '@/actions/App/Modules/Workspace/Http/Controllers/ProjectSettingsController';
import { PageHeading } from '@/components/identity/bare-shell';
import { DialogCancel, SecurityDialog } from '@/components/identity/dialog';
import { Button, ErrorSummary, Field } from '@/components/identity/form';
import {
    AppShell,
    CaptureUrlChip,
    SettingsLayout,
} from '@/components/workspace/app-shell';
import { useClientValidation } from '@/hooks/use-client-validation';
import {
    projectNameRule,
    retentionLabel,
    workspaceCopy,
} from '@/lib/workspace/copy';
import type { ProjectSettingsProps, RetentionOptions } from '@/types';

function RetentionSelect({
    id,
    name,
    label,
    value,
    options,
    error,
    onChange,
}: {
    id: string;
    name: string;
    label: string;
    value: number;
    options: number[];
    error?: string;
    onChange: (value: number) => void;
}) {
    return (
        <div className="tt-field">
            <label className="tt-label" htmlFor={id}>
                {label}
            </label>
            <select
                id={id}
                name={name}
                className="tt-input tt-select"
                value={value}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? `${id}-err` : undefined}
                onChange={(e) => onChange(Number(e.target.value))}
            >
                {options.map((d) => (
                    <option key={d} value={d}>
                        {retentionLabel(d)}
                    </option>
                ))}
            </select>
            {error && (
                <p className="tt-ferr" id={`${id}-err`}>
                    {error}
                </p>
            )}
        </div>
    );
}

/**
 * S-16 · Capture URL page: the URL (calm pending state until the Capture
 * slice), the project name (rename lives here, slice 2 notes) and
 * retention. Rotate URL arrives with the Capture slice.
 */
export default function Capture({
    project,
    sections,
    section,
    retentionOptions,
}: ProjectSettingsProps & { retentionOptions: RetentionOptions }) {
    const name = useForm({ name: project.name });
    const nameChecks = useClientValidation({
        name: {
            id: 'project-name',
            label: 'Project name',
            rule: projectNameRule,
        },
    });
    const retention = useForm({
        captureRetentionDays: project.captureRetentionDays,
        runRetentionDays: project.runRetentionDays,
    });
    // No client rules (both are fixed choices); the hook gives the server
    // errors the same focus and summary as the identity forms (F-12).
    const retentionChecks = useClientValidation({
        captureRetentionDays: {
            id: 'capture-retention',
            label: 'Keep deliveries for',
        },
        runRetentionDays: { id: 'run-retention', label: 'Keep runs for' },
    });
    const retentionShown = {
        captureRetentionDays: retention.errors.captureRetentionDays,
        runRetentionDays: retention.errors.runRetentionDays,
    };

    const rename = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (name.processing) {
            return;
        }

        if (
            nameChecks.check(event.currentTarget, (values) =>
                name.setData('name', values.name),
            )
        ) {
            name.patch(ProjectSettings.rename.url(project.key), {
                preserveScroll: true,
                onError: (errors) => nameChecks.focusFirst(errors),
            });
        }
    };

    const saveRetention = (event: FormEvent) => {
        event.preventDefault();

        if (retention.processing) {
            return;
        }
        retention.patch(ProjectSettings.retention.url(project.key), {
            preserveScroll: true,
            onError: (errors) => retentionChecks.focusFirst(errors),
        });
    };

    return (
        <AppShell title={`${project.name} · Settings`}>
            <SettingsLayout
                projectName={project.name}
                projectKey={project.key}
                sections={sections}
                current={section}
            >
                <PageHeading>Capture URL</PageHeading>

                <section className="tt-sec" aria-labelledby="h-url">
                    <h2 id="h-url">Capture URL</h2>
                    <div className="tt-lr">
                        <span className="k">URL</span>
                        <CaptureUrlChip url={project.captureUrl} />
                        <span />
                    </div>
                    {!project.captureUrl && (
                        <p className="tt-hint">
                            {workspaceCopy.captureUrlPendingLong}
                        </p>
                    )}
                    {project.captureUrl && !project.isGuest && (
                        <RotateUrl projectKey={project.key} />
                    )}
                </section>

                <form
                    className="tt-sec"
                    aria-labelledby="h-name"
                    onSubmit={rename}
                    noValidate
                >
                    <h2 id="h-name">Name</h2>
                    <Field
                        id="project-name"
                        name="name"
                        label="Project name"
                        autoComplete="off"
                        maxLength={120}
                        value={name.data.name}
                        error={nameChecks.error('name', name.errors.name)}
                        {...nameChecks.bind('name', (v) =>
                            name.setData('name', v),
                        )}
                    />
                    <div>
                        <Button
                            type="submit"
                            busy={name.processing}
                            aria-disabled={name.processing || undefined}
                        >
                            Save name
                        </Button>
                    </div>
                </form>

                <form
                    className="tt-sec"
                    aria-labelledby="h-retention"
                    onSubmit={saveRetention}
                    noValidate
                >
                    <h2 id="h-retention">Retention</h2>
                    <ErrorSummary
                        fields={retentionChecks.summary(retentionShown)}
                    />
                    <div className="tt-twocol">
                        <RetentionSelect
                            id="capture-retention"
                            name="captureRetentionDays"
                            label="Keep deliveries for"
                            value={retention.data.captureRetentionDays}
                            options={retentionOptions.captureDays}
                            error={retention.errors.captureRetentionDays}
                            onChange={(v) => {
                                retention.setData('captureRetentionDays', v);
                                retention.clearErrors('captureRetentionDays');
                            }}
                        />
                        <RetentionSelect
                            id="run-retention"
                            name="runRetentionDays"
                            label="Keep runs for"
                            value={retention.data.runRetentionDays}
                            options={retentionOptions.runDays}
                            error={retention.errors.runRetentionDays}
                            onChange={(v) => {
                                retention.setData('runRetentionDays', v);
                                retention.clearErrors('runRetentionDays');
                            }}
                        />
                    </div>
                    <div>
                        <Button
                            type="submit"
                            busy={retention.processing}
                            aria-disabled={retention.processing || undefined}
                        >
                            Save retention
                        </Button>
                    </div>
                </form>
            </SettingsLayout>
        </AppShell>
    );
}

/**
 * S-16 Rotate URL… with its confirm. Throttled per user, 10 an hour (A09
 * S3-12): the server answers `errors.form = 'throttled'`, shown as the
 * section's form-level alert.
 */
function RotateUrl({ projectKey }: { projectKey: string }) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const throttled = errors.form === 'throttled';

    const rotate = () => {
        if (busy) {
            return;
        }
        setBusy(true);
        router.post(
            CaptureUrl.rotate.url(projectKey),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setOpen(false);
                },
            },
        );
    };

    return (
        <>
            <ErrorSummary
                message={throttled ? workspaceCopy.rotateThrottled : null}
            />
            <div>
                <Button onClick={() => setOpen(true)}>Rotate URL…</Button>
            </div>
            <SecurityDialog
                open={open}
                onOpenChange={setOpen}
                title="Rotate the capture URL?"
                destructive
                footer={
                    <>
                        <DialogCancel asChild>
                            <Button>Cancel</Button>
                        </DialogCancel>
                        <Button
                            variant="danger"
                            busy={busy}
                            aria-disabled={busy || undefined}
                            onClick={rotate}
                        >
                            Rotate URL
                        </Button>
                    </>
                }
            >
                <p>
                    Providers that use the old URL stop reaching this project.
                    Update them first.
                </p>
            </SecurityDialog>
        </>
    );
}
