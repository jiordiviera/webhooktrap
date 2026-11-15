import type { Flash, SecurityLogEntry } from '@/types/identity';
import { formatFull } from './format';

/*
 * Exact copy from the screen specifications (S-02, S-17A/B, S-24 to S-32).
 * The server answers with codes (contracts §3.4); the words live here.
 */

export const EMAIL_EXAMPLE = 'maya@brindlecoffee.example';

export const copy = {
    invalidCredentials: 'Email or password is incorrect.',
    throttled: (time: string) => `Too many attempts. Try again after ${time}.`,
    emailRequired: `Enter an email address, such as ${EMAIL_EXAMPLE}.`,
    passwordRequired: 'Enter your password.',
    nameRequired: 'Enter your name.',
    codeRequired: 'Enter the code.',
    passwordsDiffer: "The passwords don't match.",
    passwordHelper:
        '12 to 128 characters. A long phrase is easiest to remember.',
    passwordHelperBytes:
        'At least 12 characters and at most 72 bytes. Accented letters and symbols take more than one byte.',
    serverError: 'Something went wrong on our side. Try again in a moment.',
} as const;

/** Field errors for a password (S-17B `password`, S-24, S-26). */
export function passwordError(
    code: string | undefined,
    unit: 'code_points' | 'bytes' = 'code_points',
): string | undefined {
    switch (code) {
        case undefined:
            return undefined;
        case 'password_too_short':
            return 'Use at least 12 characters.';
        case 'password_too_long':
            return unit === 'bytes'
                ? 'Use 72 bytes or fewer. Accented letters and symbols count as more than one.'
                : 'Use 128 characters or fewer.';
        case 'password_common':
            return 'This password is too common. Choose another.';
        case 'password_is_email':
            return "Don't use your email address as your password.";
        default:
            return /confirm/i.test(code) ? copy.passwordsDiffer : code;
    }
}

/** Validation messages Laravel may send for a field that the client checks too. */
export function fieldError(
    field: 'email' | 'name' | 'password' | 'code',
    message: string | undefined,
): string | undefined {
    if (message === undefined) {
        return undefined;
    }
    if (field === 'email') {
        return copy.emailRequired;
    }
    if (field === 'name') {
        return copy.nameRequired;
    }
    if (field === 'password' && /required/i.test(message)) {
        return copy.passwordRequired;
    }

    return field === 'password' ? passwordError(message) : message;
}

/** Ruled flash on bare pages, toast on account pages. */
export function flashCopy(flash: Flash | undefined): string | null {
    if (!flash) {
        return null;
    }
    const provider = providerLabel(flash.message);

    switch (flash.code) {
        case 'verification_sent':
            return null; // S-25 shows it in its own words.
        case 'verification_required':
            return null;
        case 'email_verified':
            return 'Email confirmed.';
        case 'email_verified_set_password':
            return 'Email confirmed. Set a password with Forgot password to sign in.';
        case 'password_reset':
            return 'Password saved. Other browsers were signed out.';
        case 'password_changed':
            return 'Password changed. Other browsers were signed out.';
        case 'reset_link_sent':
            return null;
        case 'recovery_link_sent':
            return null;
        case 'recovery_started':
            return "Recovery started. We'll email you when your account becomes recoverable. If you find a passkey, key or code before then, just sign in: recovery stops by itself.";
        case 'recovery_declined':
            return 'Recovery not started. Nothing changed.';
        case 'recovery_cancelled':
            return 'Recovery cancelled. We emailed you.';
        case 'totp_enabled':
            return 'Authenticator app is on.';
        case 'totp_disabled':
            return 'Authenticator app turned off.';
        case 'key_removed':
            return 'Key removed.';
        case 'key_unblocked':
            return "Key unblocked. If it's blocked again, you'll need to remove it.";
        case 'identity_linked':
            return `${provider} connected.`;
        case 'identity_unlinked':
            return `${provider} disconnected.`;
        case 'session_revoked':
            return 'Signed out that browser.';
        case 'sessions_revoked':
            return 'Signed out other browsers.';
        case 'session_already_ended':
            return 'That browser was already signed out.';
        case 'no_other_sessions':
            return "You're not signed in anywhere else.";
        case 'email_change_sent':
            return null; // The dialog shows the uniform "Check the new inbox" step.
        case 'email_change_cancelled':
            return 'Email change cancelled. The link no longer works.';
        case 'email_change_waiting_old':
            return 'Confirmed. Waiting for the link sent to your current address.';
        case 'email_change_waiting_new':
            return 'Confirmed. Waiting for the link sent to the new address.';
        case 'email_changed':
            return 'Your email changed. Other browsers were signed out.';
        case 'deletion_link_sent':
            return null;
        case 'deletion_cancelled':
            return null;
        case 'account_deleted':
            return 'Your account was deleted. We sent a confirmation to your email.';
        case 'session_ended':
            return 'Session ended. Nothing was kept.';
        case 'nothing_changed':
            return 'Nothing changed.';
        default:
            return flash.message !== '' ? flash.message : null;
    }
}

export function providerLabel(provider: string | undefined): string {
    if (provider === 'github') {
        return 'GitHub';
    }
    if (provider === 'google') {
        return 'Google';
    }

    return provider ?? '';
}

const FIRST: Record<string, string> = {
    password: 'Password',
    github: 'GitHub',
    google: 'Google',
    passkey: 'Passkey',
    webauthn: 'Passkey',
    reset_link: 'Password reset link',
    recovery_finish: 'Account recovery',
};

const SECOND: Record<string, string> = {
    security_key: 'security key',
    webauthn: 'security key',
    totp: 'authenticator app',
    recovery_code: 'recovery code',
};

/** "How" on S-17A sessions (RD `method_labels`). */
export function methodLabel(method: {
    first: string;
    second: string | null;
}): string {
    const first = FIRST[method.first] ?? method.first;

    if (method.first === 'passkey' || !method.second) {
        return first;
    }

    return `${first}, then ${SECOND[method.second] ?? method.second}`;
}

function how(params: Record<string, unknown> | undefined): string {
    const h = (params?.how ?? {}) as { first?: string; second?: string | null };
    const key = typeof params?.keyName === 'string' ? params.keyName : null;

    if (h.first === 'passkey' || h.first === 'webauthn') {
        return key ? `a passkey (${key})` : 'a passkey';
    }

    const first = (FIRST[h.first ?? ''] ?? h.first ?? 'password').replace(
        /^Password$/,
        'password',
    );

    if (!h.second) {
        return first;
    }

    const second = SECOND[h.second] ?? h.second;

    return key && h.second === 'security_key'
        ? `${first}, then ${second} (${key})`
        : `${first}, then ${second}`;
}

const str = (v: unknown): string => (typeof v === 'string' ? v : '');

const kindLabel = (v: unknown): string =>
    v === 'security_key' ? 'Security key' : 'Passkey';

/** "What" on the security log (RD `SECURITY_LOG_COPY`, schema §7 codes). */
export function logCopy(entry: SecurityLogEntry): string {
    const p = entry.params;

    switch (entry.action) {
        case 'signed_in':
            return `Signed in with ${how(p)}`;
        case 'sign_in_held':
            return 'Sign-in paused after too many wrong passwords';
        case 'second_factor_hold':
            return 'Two-step sign-in paused after 20 wrong codes or keys';
        case 'key_added':
            return `${kindLabel(p?.kind)} added: ${str(p?.keyName)}`;
        case 'key_removed':
            return `${kindLabel(p?.kind)} removed: ${str(p?.keyName)}`;
        case 'key_blocked':
            return `Key blocked: ${str(p?.keyName)}`;
        case 'key_unblocked':
            return `Key unblocked: ${str(p?.keyName)}`;
        case 'totp_enabled':
            return 'Authenticator app turned on';
        case 'totp_disabled':
            return 'Authenticator app turned off';
        case 'two_factor_disabled':
            return 'Two-step sign-in turned off';
        case 'recovery_codes_regenerated':
            return 'New recovery codes created';
        case 'password_set':
            return 'Password set';
        case 'password_changed':
            return 'Password changed; other browsers signed out';
        case 'password_reset':
            return 'Password reset by email; every browser signed out';
        case 'email_change_requested':
            return `Email change requested to ${str(p?.masked)}`;
        case 'email_change_cancelled':
            return `Email change to ${str(p?.masked)} cancelled`;
        case 'email_change_confirmed':
            return p?.side === 'old'
                ? `Email change to ${str(p?.masked)} confirmed from the current address`
                : `Email change to ${str(p?.masked)} confirmed from the new address`;
        case 'email_changed':
            return `Email changed to ${str(p?.masked)}; other browsers signed out`;
        case 'account_deletion_requested':
            return 'Account deletion requested';
        case 'account_deletion_cancelled':
            return 'Account deletion cancelled';
        case 'identity_linked':
            return `${providerLabel(str(p?.provider))} linked (${str(p?.label)})`;
        case 'identity_unlinked':
            return `${providerLabel(str(p?.provider))} disconnected (${str(p?.label)})`;
        case 'session_revoked':
            return `Signed out ${str(p?.browser) || 'a browser'}`;
        case 'recovery_requested':
            return 'Account recovery requested';
        case 'recovery_confirmed':
            return 'Recovery link confirmed; 72-hour wait started';
        case 'recovery_extended':
            return 'Recovery wait extended to 7 days';
        case 'recovery_cancelled':
            return 'Account recovery cancelled';
        case 'recovery_completed':
            return 'Account recovered: passkeys, security keys, authenticator app and recovery codes removed; every browser and personal token signed out';
        case 'run_token_created':
            return `Personal token that can start runs created: ${str(p?.tokenName)}`;
        default:
            return entry.action;
    }
}

/** S-17A lock line for `lock.reason` (RC-3). */
export function lockLine(lock: { until: string; reason: string }): string {
    return lock.reason === 'recovery_open'
        ? "You can't change your email or disconnect GitHub while account recovery is open."
        : `You can't change your email or disconnect GitHub until ${formatFull(lock.until)}, 7 days after the account recovery.`;
}
