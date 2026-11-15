import { router } from '@inertiajs/react';
import { useState } from 'react';
import ProjectSettings from '@/actions/App/Modules/Workspace/Http/Controllers/ProjectSettingsController';
import { PageHeading } from '@/components/identity/bare-shell';
import { DialogCancel, SecurityDialog } from '@/components/identity/dialog';
import { Button } from '@/components/identity/form';
import { AppShell, SettingsLayout } from '@/components/workspace/app-shell';
import { workspaceCopy } from '@/lib/workspace/copy';
import guest from '@/routes/guest';
import type { ProjectSettingsProps } from '@/types';

/*
 * S-16 · Danger. Account: Delete project, with a confirm dialog that names
 * the project (S-12 Confirm dialog pattern: Esc cancels, focus returns to
 * the trigger). Guest: only End session now.
 */
export default function Danger({
    project,
    sections,
    section,
}: ProjectSettingsProps) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const confirm = () => {
        setBusy(true);

        if (project.isGuest) {
            router.post(
                guest.end.url(),
                {},
                { onFinish: () => setBusy(false) },
            );

            return;
        }
        router.delete(ProjectSettings.destroy.url(project.key), {
            onFinish: () => setBusy(false),
        });
    };

    const title = project.isGuest
        ? workspaceCopy.endSessionTitle
        : `Delete ${project.name}?`;

    return (
        <AppShell title={`${project.name} · Settings`}>
            <SettingsLayout
                projectName={project.name}
                projectKey={project.key}
                sections={sections}
                current={section}
            >
                <PageHeading>Danger</PageHeading>
                <section
                    className="tt-sec tt-danger"
                    aria-labelledby="h-danger"
                >
                    <h2 id="h-danger">
                        {project.isGuest ? 'End session' : 'Delete project'}
                    </h2>
                    <div className="tt-lr">
                        <span className="k">
                            {project.isGuest ? 'Guest session' : project.name}
                        </span>
                        <span>
                            {project.isGuest
                                ? workspaceCopy.endSessionBody
                                : workspaceCopy.deleteBody}
                        </span>
                        <span className="acts">
                            <Button
                                variant="danger"
                                onClick={() => setOpen(true)}
                            >
                                {project.isGuest
                                    ? 'End session now'
                                    : 'Delete project'}
                            </Button>
                        </span>
                    </div>
                </section>
                <SecurityDialog
                    open={open}
                    onOpenChange={setOpen}
                    title={title}
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
                                onClick={() => !busy && confirm()}
                            >
                                {project.isGuest
                                    ? 'End session now'
                                    : 'Delete project'}
                            </Button>
                        </>
                    }
                >
                    <p>
                        {project.isGuest
                            ? workspaceCopy.endSessionBody
                            : workspaceCopy.deleteBody}
                    </p>
                </SecurityDialog>
            </SettingsLayout>
        </AppShell>
    );
}
