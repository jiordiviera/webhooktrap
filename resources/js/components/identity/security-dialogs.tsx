import { router, useForm } from '@inertiajs/react';
import type { FormEvent, RefObject } from 'react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { useClientValidation } from '@/hooks/use-client-validation';
import { copy as words, lockLine, providerLabel } from '@/lib/identity/copy';
import { formatDay, formatFull } from '@/lib/identity/format';
import { postJson } from '@/lib/identity/http';
import { createCredential } from '@/lib/identity/webauthn';
import {
    email as requestEmail,
    password as changePassword,
    recoveryCodes as regenerateCodes,
} from '@/routes/account';
import identities from '@/routes/account/identities';
import keys from '@/routes/account/keys';
import sessions from '@/routes/account/sessions';
import totp from '@/routes/account/totp';
import { register as registerKey } from '@/routes/webauthn';
import { options as registerOptions } from '@/routes/webauthn/register';
import type {
    SecurityKeyRow,
    SecurityView,
    SessionRow,
} from '@/types/identity';
import { DialogCancel, SecurityDialog } from './dialog';
import { Button, ErrorSummary, Field } from './form';
import { Glyph } from './glyph';
import { NewPasswordFields, useNewPassword } from './new-password';
import { QrCode } from './qr-code';
import { RecoveryCodes } from './recovery-codes';
import { rememberIntent } from '@/lib/identity/intent';
import { confirmThenResume, protectedVisit } from './security-actions';

/* S-17B · Security dialogs. Copy is exact from the screen specifications. */

type Base = {
    open: boolean;
    onClose: () => void;
    /** Shows a toast with exact copy when the matching flash arrives. */
    expect: (code: string, message: string) => void;
    returnFocus?: RefObject<HTMLElement | null>;
};

export const kindWord = (kind: SecurityKeyRow['kind']) =>
    kind === 'passkey' ? 'Passkey' : 'Security key';

// ---------------------------------------------------------------- add a key

const NAME_RE = /^[^\p{Cc}\p{Cf}]{1,48}$/u;

const KEY_NAME_ERROR = 'Use 1 to 48 letters, numbers, spaces or punctuation.';

export function validKeyName(name: string): boolean {
    return NAME_RE.test(name) && name.trim().length > 0;
}

type AddResult = SecurityKeyRow & { recoveryCodes?: string[] };

export function AddKeyDialog({
    open,
    onClose,
    kind,
    onAdded,
    returnFocus,
}: Omit<Base, 'expect'> & {
    kind: 'passkey' | 'security_key';
    onAdded: (row: AddResult) => void;
}) {
    const [step, setStep] = useState<'explain' | 'waiting' | 'name'>('explain');
    const [error, setError] = useState<string | null>(null);
    const [retry, setRetry] = useState(false);
    const [credential, setCredential] = useState<Record<
        string,
        unknown
    > | null>(null);
    const [name, setName] = useState(
        () =>
            `${kind === 'passkey' ? 'Passkey' : 'Security key'} ${formatDay(new Date())}`,
    );
    const nameChecks = useClientValidation({
        name: {
            id: 'key-name',
            label: 'Name',
            rule: (v) => (validKeyName(v) ? undefined : KEY_NAME_ERROR),
        },
    });
    const [saving, setSaving] = useState(false);
    const title = kind === 'passkey' ? 'Add a passkey' : 'Add a security key';
    const verb = kind === 'passkey' ? 'add a passkey' : 'add a security key';

    const start = async () => {
        setError(null);
        setRetry(false);
        setStep('waiting');
        const options = await postJson<unknown>(registerOptions.url(), {
            kind,
        });

        if (!options.ok) {
            setStep('explain');

            if (options.error === 'recent_auth_required') {
                confirmThenResume({ dialog: 'add', verb, params: { kind } });

                return;
            }
            setError(
                options.error === 'too_many_keys'
                    ? 'You already have 10 passkeys and security keys. Remove one first.'
                    : words.serverError,
            );

            return;
        }

        const result = await createCredential(options.data);
        setStep(result.status === 'ok' ? 'name' : 'explain');

        if (result.status === 'ok') {
            setCredential(result.credential);
        } else if (result.status === 'cancelled') {
            setError('Nothing was added. You can try again.');
            setRetry(true);
        } else if (result.status === 'exists') {
            setError('This passkey or key is already on your account.');
        } else {
            setError(
                "Your browser couldn't create a passkey here. Try another browser or device.",
            );
        }
    };

    const save = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        let typed = name;

        if (
            !nameChecks.check(event.currentTarget, (values) => {
                typed = values.name;
                setName(values.name);
            })
        ) {
            return;
        }
        setSaving(true);
        const answer = await postJson<AddResult>(registerKey.url(), {
            credential,
            name: typed.trim(),
        });
        setSaving(false);

        if (answer.ok) {
            onAdded(answer.data);

            return;
        }

        if (answer.error === 'recent_auth_required') {
            confirmThenResume({ dialog: 'add', verb, params: { kind } });

            return;
        }
        setStep('explain');
        setCredential(null);
        setError(
            answer.error === 'too_many_keys'
                ? 'You already have 10 passkeys and security keys. Remove one first.'
                : "Your browser couldn't create a passkey here. Try another browser or device.",
        );
    };

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={title}
            returnFocus={returnFocus}
            footer={
                step === 'name' ? undefined : (
                    <>
                        <DialogCancel asChild>
                            <Button>Cancel</Button>
                        </DialogCancel>
                        <Button
                            variant="primary"
                            aria-disabled={step === 'waiting' || undefined}
                            busy={step === 'waiting'}
                            onClick={() => step === 'explain' && void start()}
                        >
                            {step === 'waiting'
                                ? 'Waiting…'
                                : retry
                                  ? 'Try again'
                                  : 'Continue'}
                        </Button>
                    </>
                )
            }
        >
            {step !== 'name' && (
                <>
                    <p>
                        {kind === 'passkey'
                            ? 'Your browser or phone will ask you to confirm with your fingerprint, face, PIN or screen lock. The passkey is saved on that device or in your password manager.'
                            : 'Insert your key or hold it near your phone, then touch it when it blinks.'}
                    </p>
                    {step === 'waiting' && (
                        <p className="tt-wait" role="status">
                            <Glyph name="wait" spin />
                            Follow the steps from your browser.
                        </p>
                    )}
                    {error && (
                        <p
                            className="tt-ferr"
                            role={retry ? 'status' : 'alert'}
                        >
                            <Glyph name="error" />
                            <span>{error}</span>
                        </p>
                    )}
                </>
            )}
            {step === 'name' && (
                <form
                    className="tt-col"
                    style={{ gap: 16 }}
                    onSubmit={(e) => void save(e)}
                    noValidate
                >
                    <Field
                        id="key-name"
                        name="name"
                        label="Name"
                        value={name}
                        maxLength={64}
                        autoComplete="off"
                        helper="1 to 48 characters. Only you see it, and it appears in emails about this key."
                        error={nameChecks.error('name')}
                        {...nameChecks.bind('name', setName)}
                    />
                    <footer
                        className="tt-actions"
                        style={{ justifyContent: 'flex-end' }}
                    >
                        <Button
                            type="submit"
                            variant="primary"
                            busy={saving}
                            aria-disabled={saving || undefined}
                        >
                            Save
                        </Button>
                    </footer>
                </form>
            )}
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- rename

export function RenameDialog({
    open,
    onClose,
    expect,
    keyRow,
    returnFocus,
}: Base & { keyRow: SecurityKeyRow }) {
    const form = useForm({ name: keyRow.name });
    const checks = useClientValidation({
        name: {
            id: 'rename-key',
            label: 'Name',
            rule: (v) => (validKeyName(v) ? undefined : KEY_NAME_ERROR),
        },
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        let name = '';

        if (
            !checks.check(event.currentTarget, (values) => {
                name = values.name.trim();
                form.setData('name', values.name);
            })
        ) {
            return;
        }
        expect('renamed', `Renamed to ${name}.`);
        form.transform(() => ({ name }));
        form.patch(keys.rename.url(keyRow.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: () => form.setError('name', KEY_NAME_ERROR),
        });
    };

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={`Rename ${keyRow.name}`}
            returnFocus={returnFocus}
        >
            <form
                className="tt-col"
                style={{ gap: 16 }}
                onSubmit={submit}
                noValidate
            >
                <Field
                    id="rename-key"
                    name="name"
                    label="Name"
                    value={form.data.name}
                    autoComplete="off"
                    onFocus={(e) => e.currentTarget.select()}
                    helper="1 to 48 characters. Only you see it, and it appears in emails about this key."
                    error={checks.error('name', form.errors.name)}
                    {...checks.bind('name', (v) => form.setData('name', v))}
                />
                <footer
                    className="tt-actions"
                    style={{ justifyContent: 'flex-end' }}
                >
                    <DialogCancel asChild>
                        <Button>Cancel</Button>
                    </DialogCancel>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={form.processing}
                    >
                        Save
                    </Button>
                </footer>
            </form>
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- remove / last method

export function LastMethodDialog({
    open,
    onClose,
    onAddPasskey,
    canAddPasskey,
}: Pick<Base, 'open' | 'onClose'> & {
    onAddPasskey: () => void;
    canAddPasskey: boolean;
}) {
    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title="You can't remove your only way to sign in"
            footer={
                <>
                    <DialogCancel asChild>
                        <Button>Close</Button>
                    </DialogCancel>
                    {canAddPasskey && (
                        <Button variant="primary" onClick={onAddPasskey}>
                            Add a passkey
                        </Button>
                    )}
                </>
            }
        >
            <p>Add a password, a passkey or a linked account first.</p>
        </SecurityDialog>
    );
}

export function RemoveKeyDialog({
    open,
    onClose,
    expect,
    keyRow,
    lastSecondFactor,
    onLastMethod,
    returnFocus,
}: Base & {
    keyRow: SecurityKeyRow;
    lastSecondFactor: boolean;
    onLastMethod: () => void;
}) {
    const [busy, setBusy] = useState(false);
    const title = lastSecondFactor
        ? `Remove ${keyRow.name} and turn off two-step sign-in?`
        : `Remove ${keyRow.name}?`;

    const remove = () => {
        setBusy(true);
        expect('key_removed', `${keyRow.name} removed.`);
        protectedVisit(
            'delete',
            keys.remove.url(keyRow.id),
            {},
            {
                dialog: 'remove',
                verb: 'remove a key',
                params: { key: keyRow.id },
            },
            {
                onSuccess: () => onClose(),
                onError: (errors) => {
                    if (errors.form === 'last_sign_in_method') {
                        onLastMethod();
                    }
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={title}
            destructive
            returnFocus={returnFocus}
            footer={
                <>
                    <DialogCancel asChild>
                        <Button>Cancel</Button>
                    </DialogCancel>
                    <Button
                        variant="danger"
                        busy={busy}
                        aria-disabled={busy || undefined}
                        onClick={remove}
                    >
                        {lastSecondFactor ? 'Remove and turn off' : 'Remove'}
                    </Button>
                </>
            }
        >
            <p>
                {lastSecondFactor
                    ? "It's your last second step. Removing it turns off two-step sign-in and deletes your recovery codes."
                    : "You can't use it to sign in to Webhook Trap any more. We'll email you about it."}
            </p>
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- blocked key

export function BlockedKeyDialog({
    open,
    onClose,
    expect,
    keyRow,
    onRemove,
    returnFocus,
}: Base & { keyRow: SecurityKeyRow; onRemove: () => void }) {
    const [busy, setBusy] = useState(false);
    const [refused, setRefused] = useState(false);
    const when = keyRow.flaggedAt ? formatFull(keyRow.flaggedAt) : '';
    const canUnblock = keyRow.canUnblock !== false && !refused;

    const unblock = () => {
        setBusy(true);
        expect(
            'key_unblocked',
            `${keyRow.name} unblocked. If it's blocked again, you'll need to remove it.`,
        );
        protectedVisit(
            'post',
            keys.unblock.url(keyRow.id),
            {},
            {
                dialog: 'blocked',
                verb: 'unblock a key',
                params: { key: keyRow.id },
            },
            {
                onSuccess: () => onClose(),
                onError: (errors) =>
                    setRefused(errors.form === 'unblock_not_allowed'),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={`Review ${keyRow.name}`}
            returnFocus={returnFocus}
            footer={
                <>
                    <DialogCancel asChild>
                        <Button>Cancel</Button>
                    </DialogCancel>
                    {canUnblock && (
                        <Button
                            busy={busy}
                            aria-disabled={busy || undefined}
                            aria-describedby="unblock-help"
                            onClick={unblock}
                        >
                            Unblock
                        </Button>
                    )}
                    <Button variant="primary" onClick={onRemove}>
                        Remove key
                    </Button>
                </>
            }
        >
            <p>
                {keyRow.flagCause === 'user_handle_mismatch'
                    ? `On ${when} this key was refused because it sent sign-in details that don't belong to your account. That can happen when a key is tampered with. Remove it and add it again unless you're sure what happened.`
                    : `On ${when} this key was refused because its use counter went backwards. That can happen when a key is copied. If you still have the key and nobody else could have used it, you can unblock it. If you're not sure, remove it and add it again.`}
            </p>
            {keyRow.canUnblock === false && (
                <p>This key was blocked twice. Remove it and add it again.</p>
            )}
            {refused && (
                <p className="tt-ferr" role="alert">
                    <Glyph name="error" />
                    <span>
                        This key can&apos;t be unblocked any more. Remove it and
                        add it again.
                    </span>
                </p>
            )}
            {canUnblock && (
                <p className="tt-hint" id="unblock-help">
                    You&apos;ll confirm it&apos;s you with another passkey, key,
                    authenticator code or your password first. If this key is
                    blocked again, it can&apos;t be unblocked.
                </p>
            )}
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- authenticator app

function groupKey(secret: string): string {
    return secret
        .replace(/\s+/g, '')
        .replace(/(.{4})/g, '$1 ')
        .trim();
}

export function TotpSetupDialog({
    open,
    onClose,
    setup,
    returnFocus,
}: Omit<Base, 'expect'> & { setup: { secret: string; uri: string } }) {
    const [step, setStep] = useState<'scan' | 'confirm'>('scan');
    const [copied, setCopied] = useState(false);
    const [code, setCode] = useState('');
    // protectedVisit goes through the router, not useForm: the server's
    // error and the in-flight state are kept here (F-01, F-11).
    const [serverError, setServerError] = useState<string | undefined>();
    const [busy, setBusy] = useState(false);
    const inFlight = useRef(false);
    const codeRef = useRef<HTMLInputElement>(null);
    const saved = useRef(false);
    const checks = useClientValidation({
        code: {
            id: 'totp-setup-code',
            label: '6-digit code from the app',
            rule: (v) =>
                v.replace(/\s+/g, '') === '' ? words.codeRequired : undefined,
        },
    });
    const error = checks.error(
        'code',
        serverError
            ? "That code didn't match. Check the time on your phone and enter the newest code."
            : undefined,
    );

    const close = () => {
        if (!saved.current) {
            // The secret is shown once; closing discards it.
            toast('Nothing was saved.');
        }
        onClose();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        // One confirm per code: a double click sends one POST (F-11).
        if (inFlight.current) {
            return;
        }
        let value = '';
        const ok = checks.check(event.currentTarget, (values) => {
            value = values.code.replace(/\s+/g, '');
        });

        if (!ok || value === '') {
            return;
        }
        inFlight.current = true;
        setBusy(true);
        setServerError(undefined);
        protectedVisit('post', totp.confirm.url(), { code: value }, null, {
            // A refused code keeps the page, so the dialog and its secret
            // stay (F-01); the success path remounts as before.
            preserveState: 'errors',
            onSuccess: (page) => {
                const errors = page.props.errors as
                    | Record<string, string>
                    | undefined;

                if (errors?.code) {
                    return;
                }
                saved.current = true;
                onClose();
            },
            onError: (errors) => {
                // Stays open with the same QR and key; the field is cleared
                // and focused with the error under it.
                setServerError(errors.code ?? errors.form ?? 'code_invalid');
                setCode('');
                window.setTimeout(() => codeRef.current?.focus(), 0);
            },
            onFinish: () => {
                inFlight.current = false;
                setBusy(false);
            },
        });
    };

    const grouped = groupKey(setup.secret);

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && close()}
            title="Set up an authenticator app"
            returnFocus={returnFocus}
            footer={
                step === 'scan' ? (
                    <>
                        <DialogCancel asChild>
                            <Button>Cancel</Button>
                        </DialogCancel>
                        <Button
                            variant="primary"
                            onClick={() => {
                                setStep('confirm');
                                window.setTimeout(
                                    () => codeRef.current?.focus(),
                                    0,
                                );
                            }}
                        >
                            Continue
                        </Button>
                    </>
                ) : undefined
            }
        >
            {step === 'scan' ? (
                <>
                    <p>
                        Scan this code with an authenticator app, such as
                        1Password, Google Authenticator or Authy.
                    </p>
                    <QrCode
                        value={setup.uri}
                        alt="QR code for your authenticator app. Use the setup key below if you can't scan it."
                    />
                    <p>Can&apos;t scan? Enter this setup key:</p>
                    <div className="tt-setupkey">
                        <code aria-label={grouped.split('').join(' ')}>
                            {grouped}
                        </code>
                        <Button
                            size="sm"
                            onClick={() =>
                                void navigator.clipboard
                                    .writeText(setup.secret)
                                    .then(() => setCopied(true))
                                    .catch(() => setCopied(false))
                            }
                        >
                            <Glyph name="copy" />
                            Copy
                        </Button>
                        <span className="tt-hint" role="status">
                            {copied ? 'Copied.' : ''}
                        </span>
                    </div>
                </>
            ) : (
                <form
                    className="tt-col"
                    style={{ gap: 16 }}
                    onSubmit={submit}
                    noValidate
                >
                    <Field
                        id="totp-setup-code"
                        name="code"
                        label="6-digit code from the app"
                        code
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        inputRef={codeRef}
                        value={code}
                        error={error}
                        {...checks.bind('code', (v) => {
                            setCode(v);
                            setServerError(undefined);
                        })}
                    />
                    <footer
                        className="tt-actions"
                        style={{ justifyContent: 'flex-end' }}
                    >
                        <Button onClick={() => setStep('scan')}>Back</Button>
                        <Button
                            type="submit"
                            variant="primary"
                            busy={busy}
                            aria-disabled={busy || undefined}
                        >
                            {busy ? 'Turning on…' : 'Turn on'}
                        </Button>
                    </footer>
                </form>
            )}
        </SecurityDialog>
    );
}

export function TotpOffDialog({
    open,
    onClose,
    expect,
    last,
    returnFocus,
}: Base & { last: boolean }) {
    const [busy, setBusy] = useState(false);

    const off = () => {
        setBusy(true);
        expect(
            'totp_disabled',
            last ? 'Two-step sign-in is off.' : 'Authenticator app turned off.',
        );
        protectedVisit(
            'delete',
            totp.disable.url(),
            {},
            {
                dialog: 'totp-off',
                verb: last
                    ? 'turn off two-step sign-in'
                    : 'turn off the authenticator app',
            },
            { onSuccess: () => onClose(), onFinish: () => setBusy(false) },
        );
    };

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={
                last
                    ? 'Turn off the authenticator app and two-step sign-in?'
                    : 'Turn off the authenticator app?'
            }
            destructive={last}
            returnFocus={returnFocus}
            footer={
                <>
                    <DialogCancel asChild>
                        <Button>Cancel</Button>
                    </DialogCancel>
                    <Button
                        variant={last ? 'danger' : 'primary'}
                        busy={busy}
                        aria-disabled={busy || undefined}
                        onClick={off}
                    >
                        {last ? 'Turn off two-step sign-in' : 'Turn off'}
                    </Button>
                </>
            }
        >
            <p>
                {last
                    ? "It's your last second step. Turning it off also turns off two-step sign-in and deletes your recovery codes."
                    : "You can set it up again in Security. We'll email you about it."}
            </p>
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- recovery codes

export function RegenerateCodesDialog({
    open,
    onClose,
    left,
    returnFocus,
}: Omit<Base, 'expect'> & { left: number }) {
    const [busy, setBusy] = useState(false);

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title="Generate new recovery codes?"
            returnFocus={returnFocus}
            footer={
                <>
                    <DialogCancel asChild>
                        <Button>Cancel</Button>
                    </DialogCancel>
                    <Button
                        variant="primary"
                        busy={busy}
                        aria-disabled={busy || undefined}
                        onClick={() => {
                            setBusy(true);
                            protectedVisit(
                                'post',
                                regenerateCodes.url(),
                                {},
                                {
                                    dialog: 'codes',
                                    verb: 'generate new recovery codes',
                                },
                                {
                                    onSuccess: () => onClose(),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        Generate
                    </Button>
                </>
            }
        >
            <p>Your {left} unused codes stop working now.</p>
        </SecurityDialog>
    );
}

export function ShowCodesDialog({
    open,
    onClose,
    codes,
    firstFactor,
    returnFocus,
}: Omit<Base, 'expect'> & { codes: string[]; firstFactor: boolean }) {
    const [saved, setSaved] = useState(false);
    const [asking, setAsking] = useState(false);

    const attemptClose = () => {
        if (saved) {
            onClose();
        } else {
            setAsking(true);
        }
    };

    if (asking) {
        return (
            <SecurityDialog
                open={open}
                onOpenChange={(o) => !o && setAsking(false)}
                title="Close without saving the codes?"
                destructive
                footer={
                    <>
                        <Button onClick={() => setAsking(false)}>
                            Go back
                        </Button>
                        <Button variant="primary" onClick={onClose}>
                            Close anyway
                        </Button>
                    </>
                }
            >
                <p>
                    You can generate new ones later, but these won&apos;t be
                    shown again.
                </p>
            </SecurityDialog>
        );
    }

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && attemptClose()}
            title={
                firstFactor
                    ? 'Two-step sign-in is on. Save your recovery codes.'
                    : 'Your recovery codes'
            }
            returnFocus={returnFocus}
            footer={
                <>
                    {!saved && (
                        <span className="tt-reason" id="codes-done-reason">
                            Tick the box to continue.
                        </span>
                    )}
                    <Button
                        variant="primary"
                        aria-disabled={!saved || undefined}
                        aria-describedby={
                            saved ? undefined : 'codes-done-reason'
                        }
                        onClick={() => saved && onClose()}
                    >
                        Done
                    </Button>
                </>
            }
        >
            <p>
                Each code works once. Keep them somewhere other than your phone
                or password manager, such as a printout. They&apos;re shown only
                now.
            </p>
            <RecoveryCodes
                codes={codes}
                saved={saved}
                onSavedChange={setSaved}
            />
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- password

export function PasswordDialog({
    open,
    onClose,
    expect,
    hasPassword,
    policy,
    returnFocus,
}: Base & { hasPassword: boolean; policy: SecurityView['passwordPolicy'] }) {
    const form = useForm({ password: '', password_confirmation: '' });
    const fields = useNewPassword(policy, 'dlg');

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (
            !fields.check(event.currentTarget, (values) =>
                form.setData((data) => ({ ...data, ...values })),
            )
        ) {
            return;
        }
        expect(
            'password_changed',
            hasPassword
                ? 'Password changed. Other browsers were signed out.'
                : 'Password set. Other browsers were signed out.',
        );
        protectedVisit(
            'put',
            changePassword.url(),
            form.data,
            { dialog: 'password', verb: 'change your password' },
            {
                onSuccess: () => onClose(),
                onError: (errors) => {
                    form.setError(errors as Record<string, string>);
                    form.reset();
                },
            },
        );
    };

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={hasPassword ? 'Change password' : 'Set a password'}
            returnFocus={returnFocus}
        >
            <form
                className="tt-col"
                style={{ gap: 16 }}
                onSubmit={submit}
                noValidate
            >
                <ErrorSummary
                    fields={fields.summary(fields.shown(form.errors.password))}
                />
                <NewPasswordFields
                    policy={policy}
                    data={form.data}
                    setData={(k, v) => form.setData(k, v)}
                    checks={fields}
                    serverError={form.errors.password}
                />
                <footer
                    className="tt-actions"
                    style={{ justifyContent: 'flex-end' }}
                >
                    <DialogCancel asChild>
                        <Button>Cancel</Button>
                    </DialogCancel>
                    <Button type="submit" variant="primary">
                        Save
                    </Button>
                </footer>
            </form>
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- linked accounts

export function DisconnectDialog({
    open,
    onClose,
    expect,
    provider,
    onLastMethod,
    returnFocus,
}: Base & { provider: string; onLastMethod: () => void }) {
    const [busy, setBusy] = useState(false);
    const label = providerLabel(provider);

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={`Disconnect ${label}?`}
            destructive
            returnFocus={returnFocus}
            footer={
                <>
                    <DialogCancel asChild>
                        <Button>Cancel</Button>
                    </DialogCancel>
                    <Button
                        variant="danger"
                        busy={busy}
                        aria-disabled={busy || undefined}
                        onClick={() => {
                            setBusy(true);
                            expect(
                                'identity_unlinked',
                                `${label} disconnected.`,
                            );
                            protectedVisit(
                                'delete',
                                identities.disconnect.url(provider),
                                {},
                                {
                                    dialog: 'disconnect',
                                    verb: `disconnect ${label}`,
                                    params: { provider },
                                },
                                {
                                    onSuccess: () => onClose(),
                                    onError: (errors) => {
                                        if (
                                            errors.form ===
                                            'last_sign_in_method'
                                        ) {
                                            onLastMethod();
                                        }
                                    },
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        Disconnect
                    </Button>
                </>
            }
        >
            <p>
                You won&apos;t be able to sign in with {label} any more. Your
                projects and runs stay. Browsers already signed in with {label}{' '}
                stay signed in; you can sign them out under Where you&apos;re
                signed in.
            </p>
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- email

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function EmailDialog({
    open,
    onClose,
    security,
    initialEmail = '',
    returnFocus,
}: Omit<Base, 'expect'> & { security: SecurityView; initialEmail?: string }) {
    const [value, setValue] = useState(initialEmail);
    const [serverError, setServerError] = useState<string | undefined>();
    const [refusal, setRefusal] = useState<string | null>(null);
    const [sentTo, setSentTo] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const twoFactor = security.twoFactor;
    const checks = useClientValidation({
        email: {
            id: 'new-email',
            label: 'New email',
            rule: (v) => {
                const address = v.trim();

                if (!EMAIL_RE.test(address)) {
                    return words.emailRequired;
                }

                return address.toLowerCase() === security.email.toLowerCase()
                    ? "That's already your email."
                    : undefined;
            },
        },
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        let address = '';

        if (
            !checks.check(event.currentTarget, (values) => {
                address = values.email.trim();
                setValue(values.email);
            })
        ) {
            return;
        }
        setServerError(undefined);
        setBusy(true);
        protectedVisit(
            'post',
            requestEmail.url(),
            { email: address },
            {
                dialog: 'email',
                verb: 'change your email',
                params: { email: address },
            },
            {
                onSuccess: (page) => {
                    if (page.component === 'Account/Security') {
                        setSentTo(address);
                    }
                },
                onError: (errors) => {
                    if (errors.form) {
                        setRefusal(errors.form);
                    } else if (errors.email) {
                        setServerError(words.emailRequired);
                    }
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    let body;
    let footer;
    let title = 'Change your email';

    if (refusal === 'second_factor_required') {
        body = (
            <p role="alert">
                To change your email, confirm it&apos;s you with a passkey,
                security key or authenticator code. Your password alone
                isn&apos;t enough, because your email is how you&apos;d recover
                this account.
            </p>
        );
        footer = (
            <>
                <DialogCancel asChild>
                    <Button>Cancel</Button>
                </DialogCancel>
                <Button
                    variant="primary"
                    onClick={() => {
                        rememberIntent({
                            dialog: 'email',
                            verb: 'change your email',
                            params: { email: value.trim() },
                        });
                        const params = new URLSearchParams({
                            next: '/account/security?resume=1',
                            action: 'change_email',
                        });
                        router.visit(`/confirm-auth?${params.toString()}`);
                    }}
                >
                    Confirm it&apos;s you
                </Button>
            </>
        );
    } else if (refusal === 'recovery_lock' && security.lock) {
        body = <p role="alert">{lockLine(security.lock)}</p>;
        footer = (
            <DialogCancel asChild>
                <Button>Close</Button>
            </DialogCancel>
        );
    } else if (sentTo) {
        title = twoFactor ? 'Check the new inbox' : 'Check both inboxes';
        body = twoFactor ? (
            <p>
                We sent a link to{' '}
                <strong className="tt-strong">{sentTo}</strong>. Your email
                changes when you open it and press Confirm new email. We&apos;ve
                also told{' '}
                <strong className="tt-strong">{security.email}</strong> about
                this.
            </p>
        ) : (
            <p>
                We sent a link to{' '}
                <strong className="tt-strong">{sentTo}</strong> and one to{' '}
                <strong className="tt-strong">{security.email}</strong>. Your
                email changes when you&apos;ve opened both and pressed the
                button on each. Nothing changes until then.
            </p>
        );
        footer = (
            <Button variant="primary" onClick={onClose}>
                Done
            </Button>
        );
    } else {
        body = (
            <form
                id="email-form"
                className="tt-col"
                style={{ gap: 16 }}
                onSubmit={submit}
                noValidate
            >
                <p>
                    Now: <span className="tt-mono">{security.email}</span>
                </p>
                {security.pendingEmail && (
                    <p>
                        This replaces your pending change to{' '}
                        {security.pendingEmail.masked}.
                    </p>
                )}
                <ErrorSummary
                    message={
                        refusal && refusal !== 'second_factor_required'
                            ? words.serverError
                            : null
                    }
                />
                <Field
                    id="new-email"
                    name="email"
                    label="New email"
                    type="email"
                    autoComplete="email"
                    value={value}
                    error={checks.error('email', serverError)}
                    helper={
                        twoFactor
                            ? "We'll send a link to the new address. Open it within 60 minutes in a browser where you're signed in to this account."
                            : "We'll send a link to the new address and one to your current address. Open both within 60 minutes in a browser where you're signed in to this account. Your email changes after both are confirmed."
                    }
                    {...checks.bind('email', setValue)}
                />
            </form>
        );
        footer = (
            <>
                <DialogCancel asChild>
                    <Button>Cancel</Button>
                </DialogCancel>
                <Button
                    type="submit"
                    form="email-form"
                    variant="primary"
                    disabled={busy}
                >
                    {busy ? 'Sending…' : 'Send link'}
                </Button>
            </>
        );
    }

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={title}
            returnFocus={returnFocus}
            footer={footer}
        >
            {body}
        </SecurityDialog>
    );
}

// ---------------------------------------------------------------- sign out browsers

export function SignOutDialog({
    open,
    onClose,
    expect,
    session,
    others,
    returnFocus,
}: Base & { session: SessionRow | null; others: number }) {
    const [busy, setBusy] = useState(false);
    const browser = session?.browser ?? 'this browser';
    const title = session
        ? `Sign out ${browser}?`
        : `Sign out ${others} other browser${others === 1 ? '' : 's'}?`;

    const confirm = () => {
        setBusy(true);

        if (session) {
            expect('session_revoked', `Signed out ${browser}.`);
        } else {
            expect(
                'sessions_revoked',
                `Signed out ${others} other browser${others === 1 ? '' : 's'}.`,
            );
        }
        protectedVisit(
            'delete',
            session
                ? sessions.revoke.url(session.id)
                : sessions.revokeOthers.url(),
            {},
            session
                ? {
                      dialog: 'signout',
                      verb: 'sign out a browser',
                      params: { session: session.id },
                  }
                : { dialog: 'signout-all', verb: 'sign out other browsers' },
            { onSuccess: () => onClose(), onFinish: () => setBusy(false) },
        );
    };

    return (
        <SecurityDialog
            open={open}
            onOpenChange={(o) => !o && onClose()}
            title={title}
            returnFocus={returnFocus}
            footer={
                <>
                    <DialogCancel asChild>
                        <Button>Cancel</Button>
                    </DialogCancel>
                    <Button
                        variant="primary"
                        busy={busy}
                        aria-disabled={busy || undefined}
                        onClick={confirm}
                    >
                        {session ? 'Sign out' : 'Sign out other browsers'}
                    </Button>
                </>
            }
        >
            <p>
                {session
                    ? 'That browser is signed out the next time it loads a page. Nothing else changes.'
                    : 'Every browser except this one is signed out the next time it loads a page. If you think someone else signed in, change your password too.'}
            </p>
        </SecurityDialog>
    );
}
