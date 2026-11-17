import { login } from '@/routes';
import type { GuestMeter } from '@/types/identity';

/*
 * Guest session copy and thresholds (FL-11, IA §guest, RD `copy.guest.*`,
 * `copy.capture.quota`). One hour before the end the meter switches to its
 * warning style and one toast is shown, once per session.
 */

export const ENDING_SOON_SECONDS = 3600;

export const guestCopy = {
    endingSoon: {
        label: 'Ends in 1 h',
        helper: 'This session ends in 1 h. Save fixtures or sign in to keep it.',
    },
    limitReached: 'Limit reached',
    quota: {
        label: (limit: number) =>
            `Guest limit reached: ${limit} of ${limit} deliveries.`,
        helper: 'New deliveries are still answered (your provider sees 200) but not stored. Save the ones you need as fixtures, or sign in to keep capturing.',
    },
    /** COPY PROPOSAL (A07): next step after the first delivery (issue #18). */
    next: {
        unpaired: {
            label: 'Next: prove your handler.',
            helper: 'Pair the agent on your machine (one command), then open a delivery and press Prove. It replays the delivery and its edge cases against your local handler.',
        },
        paired: {
            label: 'Next: prove your handler.',
            helper: 'Your agent is paired. Open a delivery and press Prove to replay it and its edge cases against your local handler.',
        },
    },
};

/** Seconds left now, from the expiry time. */
export function guestSecondsLeft(meter: GuestMeter, now = Date.now()): number {
    return Math.max(
        0,
        Math.floor((new Date(meter.expiresAt).getTime() - now) / 1000),
    );
}

export function guestEndingSoon(meter: GuestMeter, now = Date.now()): boolean {
    return guestSecondsLeft(meter, now) <= ENDING_SOON_SECONDS;
}

/** FL-7 step 1: `/sign-in?next=/p/{projectKey}`, so the claim lands on the same project. */
export function guestSignInUrl(projectKey: string | undefined): string {
    return projectKey
        ? login.url({ query: { next: `/p/${projectKey}` } })
        : login.url();
}
