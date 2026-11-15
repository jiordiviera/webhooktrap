/** Identity props (A05 contracts §3.1 shared props, §3.4 page props). */

export type Principal =
    | { kind: 'anonymous' }
    | { kind: 'guest'; expiresAt: string; ended: boolean }
    | {
          kind: 'user';
          id: string;
          name: string;
          email: string;
          emailVerified: boolean;
          isInstanceAdmin: boolean;
          twoFactor: boolean;
          recoveryPending?: RecoveryPending;
      };

/** A confirmed, open account recovery (S-00 banner, U-5); `extended` is RC-2. */
export type RecoveryPending = {
    requestedAt: string;
    completesAt: string;
    extended: boolean;
};

export type GuestMeter = {
    expiresAt: string;
    /** Seconds left at render time, from the server clock (slice 4). */
    secondsLeft: number;
    captures: { used: number; limit: number };
    runs: { used: number; limit: number };
};

export type Instance = {
    kind: 'hosted' | 'selfhost';
    name: string;
    guestMode: boolean;
    serverRuns: boolean;
};

export type Flash = {
    kind: 'info' | 'success' | 'warning' | 'error';
    code: string;
    message: string;
    [key: string]: string;
};

export type PasswordPolicy = {
    minLength: number;
    maxLength: number;
    unit: 'code_points' | 'bytes';
};

export type SecurityKeyRow = {
    id: string;
    name: string;
    kind: 'passkey' | 'security_key';
    synced: boolean | null;
    createdAt: string;
    lastUsedAt: string | null;
    flagged: boolean;
    flaggedAt?: string;
    flagCause?: 'counter' | 'user_handle_mismatch';
    canUnblock?: boolean;
};

export type SessionRow = {
    id: string;
    current: boolean;
    browser: string | null;
    network: string | null;
    signedInAt: string;
    lastSeenAt: string | null;
    method: { first: string; second: string | null };
};

export type SecurityLogEntry = {
    at: string;
    action: string;
    browser: string | null;
    network: string | null;
    params?: Record<string, unknown>;
};

export type SecurityView = {
    hasPassword: boolean;
    email: string;
    emailVerified: boolean;
    pendingEmail?: {
        masked: string;
        expiresAt: string;
        awaiting: 'both' | 'new' | 'old';
    };
    identities: { provider: string; label: string; linkedAt: string }[];
    keys: SecurityKeyRow[];
    totp: { enabled: boolean; since: string | null };
    recoveryCodesLeft: number | null;
    twoFactor: boolean;
    recovery?: RecoveryPending;
    lock?: { until: string; reason: 'recovery_open' | 'recovery_completed' };
    recoveryReview?: { until: string };
    sessions: SessionRow[];
    securityLog: SecurityLogEntry[];
    passkeysAvailable: boolean;
    maxKeys: number;
    passwordPolicy: PasswordPolicy;
};

/** State of an emailed-link page (S-32). */
export type LinkState<T = Record<string, unknown>> =
    | { state: 'invalid' }
    | { state: 'sign_in_required' }
    | { state: 'other_account'; signedInAs: string }
    | ({ state: 'confirm' } & T);
