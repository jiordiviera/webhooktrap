import { flashCopy } from '@/lib/identity/copy';
import type { Flash } from '@/types/identity';

/*
 * Workspace copy (S-00, S-15, S-16). Lines marked COPY PROPOSAL (A07) are
 * not in the specs yet; A02/A03 may reword them.
 */
export const workspaceCopy = {
    /** COPY PROPOSAL (A07): the URL chip before the Capture slice. */
    captureUrlPending: 'Capture URL arrives with capture',
    /** COPY PROPOSAL (A07): settings line before the Capture slice. */
    captureUrlPendingLong:
        "This project gets its capture URL when capture ships. You'll copy and rotate it here.",
    nameRequired: 'Enter a name.',
    nameTooLong: 'Use 80 characters or fewer.',
    /** COPY PROPOSAL (A07): delete confirm body (S-16 only says it names the project). */
    deleteBody:
        'Its deliveries, runs and CI tokens are deleted with it. This can’t be undone.',
    /** S-16 guest confirm, split into title and body. */
    endSessionTitle: 'End this session?',
    endSessionBody: 'Every delivery, run and share link is deleted now.',
    /** COPY PROPOSAL (A07): rotate refused by the 10-an-hour throttle (A09 S3-12). */
    rotateThrottled:
        "You've rotated this URL many times in the last hour. Try again later.",
    ciEmpty: 'No CI tokens. Create one to upload runs from CI.',
};

/** Client rule for a project name (same limits as the server: 1 to 80). */
export function projectNameRule(value: string): string | undefined {
    const name = value.trim();

    if (name === '') {
        return workspaceCopy.nameRequired;
    }

    // Code points, as the server counts (mb_strlen).
    let length = 0;

    for (const _ of name) {
        length++;
    }

    return length > 80 ? workspaceCopy.nameTooLong : undefined;
}

export function retentionLabel(days: number): string {
    return days === 1 ? '1 day' : `${days} days`;
}

/** Toast copy for workspace flashes, then the shared identity copy. */
export function workspaceFlashCopy(flash: Flash | undefined): string | null {
    if (!flash) {
        return null;
    }

    switch (flash.code) {
        case 'project_renamed':
            // COPY PROPOSAL (A07)
            return `Project renamed to ${flash.message}.`;
        case 'capture_url_rotated':
            return 'New capture URL ready. The old one now answers like an unknown URL.';
        case 'agent_revoked':
            // COPY PROPOSAL (A07)
            return 'Agent revoked. It can no longer take runs.';
        case 'retention_saved':
            // COPY PROPOSAL (A07)
            return 'Retention saved.';
        case 'project_deleted':
            // COPY PROPOSAL (A07)
            return `${flash.message} deleted.`;
        default:
            return flashCopy(flash);
    }
}
