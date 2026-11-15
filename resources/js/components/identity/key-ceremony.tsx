import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { retryLabel } from '@/lib/identity/format';
import { postJson, retryAtFromSeconds } from '@/lib/identity/http';
import {
    conditionalMediationAvailable,
    getCredential,
    webauthnSupported,
} from '@/lib/identity/webauthn';
import { assert } from '@/routes/webauthn';
import { options as assertOptions } from '@/routes/webauthn/assert';
import { Button } from './form';
import { Glyph } from './glyph';

/*
 * Passkey and security-key assertions (S-02, S-27, S-28). A ceremony
 * starts only from a button press; the button keeps focus (aria-disabled,
 * aria-busy) and the outcome is announced once below it (A11Y §9).
 */

export type AssertionOutcome =
    | { status: 'ok'; credential: Record<string, unknown> }
    | { status: 'cancelled' | 'refused' | 'error' }
    | { status: 'throttled'; retryAt: string };

export async function runAssertion(
    purpose?: 'confirm',
    signal?: AbortSignal,
    mediation?: CredentialMediationRequirement,
): Promise<AssertionOutcome> {
    const options = await postJson<unknown>(
        assertOptions.url(),
        purpose ? { purpose } : {},
    );

    if (!options.ok) {
        return options.status === 429
            ? {
                  status: 'throttled',
                  retryAt: retryAtFromSeconds(options.retryAfter),
              }
            : { status: 'error' };
    }

    const result = await getCredential(options.data, signal, mediation);

    if (result.status === 'ok') {
        return result;
    }

    return { status: result.status === 'cancelled' ? 'cancelled' : 'refused' };
}

/** Sends a passkey assertion to `/webauthn/assert` and follows the redirect. */
export async function finishSignIn(
    credential: Record<string, unknown>,
): Promise<AssertionOutcome> {
    const answer = await postJson<{ redirect: string }>(assert.url(), {
        credential,
    });

    if (answer.ok) {
        router.visit(answer.data.redirect);

        return { status: 'ok', credential };
    }

    if (answer.status === 429) {
        return {
            status: 'throttled',
            retryAt: retryAtFromSeconds(answer.retryAfter),
        };
    }

    if (answer.error === 'challenge_expired' && answer.redirect) {
        router.visit(answer.redirect);
    }

    return { status: 'refused' };
}

type Messages = {
    cancelled: string;
    refused: string;
};

type Props = {
    label: string;
    waiting: string;
    messages: Messages;
    /** Turns a credential into an outcome (sign in, second step, confirm). */
    onCredential: (
        credential: Record<string, unknown>,
    ) => Promise<AssertionOutcome>;
    purpose?: 'confirm';
    variant?: 'outline' | 'primary';
    describedBy?: string;
    /** Shown while the browser sheet is open (S-27: "Touch your key…"). */
    waitingLine?: string;
    /** Called before the ceremony (stops conditional UI). */
    onStart?: () => void;
};

export function KeyCeremonyButton({
    label,
    waiting,
    messages,
    onCredential,
    purpose,
    variant = 'outline',
    describedBy,
    waitingLine,
    onStart,
}: Props) {
    const [busy, setBusy] = useState(false);
    const [result, setResult] = useState<{
        kind: 'status' | 'alert';
        text: string;
    } | null>(null);
    const button = useRef<HTMLButtonElement>(null);

    const start = async () => {
        if (busy) {
            return;
        }
        onStart?.();
        setBusy(true);
        setResult(null);
        let outcome = await runAssertion(purpose);

        if (outcome.status === 'ok') {
            outcome = await onCredential(outcome.credential);
        }
        setBusy(false);
        button.current?.focus();

        switch (outcome.status) {
            case 'ok':
                return;
            case 'cancelled':
                setResult({ kind: 'status', text: messages.cancelled });

                return;
            case 'throttled':
                setResult({
                    kind: 'alert',
                    text: `Too many attempts. Try again after ${retryLabel(outcome.retryAt)}.`,
                });

                return;
            default:
                setResult({ kind: 'alert', text: messages.refused });
        }
    };

    return (
        <div className="tt-col" style={{ gap: 8 }}>
            <Button
                buttonRef={button}
                variant={variant}
                size="lg"
                block
                className={variant === 'outline' ? 'tt-pbtn' : undefined}
                aria-disabled={busy || undefined}
                busy={busy}
                aria-describedby={describedBy}
                onClick={() => void start()}
            >
                <Glyph name={busy ? 'wait' : 'key'} large spin={busy} />
                {busy ? waiting : label}
            </Button>
            {busy && waitingLine && <p className="tt-wait">{waitingLine}</p>}
            <div role={result?.kind === 'alert' ? 'alert' : 'status'}>
                {result && (
                    <p
                        className={
                            result.kind === 'alert' ? 'tt-ferr' : 'tt-status'
                        }
                    >
                        {result.kind === 'alert' && <Glyph name="error" />}
                        <span>{result.text}</span>
                    </p>
                )}
            </div>
        </div>
    );
}

/**
 * Passkeys in the email autofill list (conditional UI, S-02 §6). Runs in
 * the background; the passkey button aborts it before its own ceremony.
 */
export function useConditionalPasskeys(enabled: boolean) {
    const controller = useRef<AbortController | null>(null);

    useEffect(() => {
        if (!enabled) {
            return;
        }
        let cancelled = false;

        void (async () => {
            if (!(await conditionalMediationAvailable()) || cancelled) {
                return;
            }
            controller.current = new AbortController();
            const outcome = await runAssertion(
                undefined,
                controller.current.signal,
                'conditional',
            );

            if (!cancelled && outcome.status === 'ok') {
                await finishSignIn(outcome.credential);
            }
        })();

        return () => {
            cancelled = true;
            controller.current?.abort();
        };
    }, [enabled]);

    return () => controller.current?.abort();
}

export { webauthnSupported };
