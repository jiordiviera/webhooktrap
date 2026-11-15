/*
 * "Confirm it's you" round trip (S-17B intro, A11Y G13): an action that
 * needs recent authentication goes to S-28 and comes back to the same
 * dialog with the person's choices kept. The choice is kept in this tab's
 * sessionStorage for 10 minutes; nothing secret is stored (no codes, no
 * passwords, no credentials).
 */

const KEY = 'wt.identity.intent';
const TTL_MS = 10 * 60 * 1000;

export type Intent = {
    /** Which S-17B dialog to reopen. */
    dialog: string;
    /** S-28 wording: "You're about to {verb}." */
    verb: string;
    /** Non-secret choices to restore (a key id, a provider, an address). */
    params?: Record<string, string>;
    at?: number;
};

export function rememberIntent(intent: Intent): void {
    try {
        sessionStorage.setItem(
            KEY,
            JSON.stringify({ ...intent, at: Date.now() }),
        );
    } catch {
        // Storage can be off (private mode); the person then reopens the dialog.
    }
}

export function readIntent(): Intent | null {
    try {
        const raw = sessionStorage.getItem(KEY);

        if (!raw) {
            return null;
        }
        const intent = JSON.parse(raw) as Intent;

        if (!intent.at || Date.now() - intent.at > TTL_MS) {
            sessionStorage.removeItem(KEY);

            return null;
        }

        return intent;
    } catch {
        return null;
    }
}

export function clearIntent(): void {
    try {
        sessionStorage.removeItem(KEY);
    } catch {
        // ignore
    }
}

/** The `next` S-28 sends the person back to, so the dialog reopens. */
export const RESUME_PARAM = 'resume';
