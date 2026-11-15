import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { useRef } from 'react';
import { useClientValidation } from '@/hooks/use-client-validation';
import { copy } from '@/lib/identity/copy';
import { Button, Field } from './form';

/*
 * One field for a one-time or recovery code (DS CodeInput): never six
 * boxes, paste allowed, spaces ignored, helper and error tied to it.
 */
export function CodeForm({
    id,
    label,
    kind,
    action,
    submitLabel,
    busyLabel = 'Verifying…',
    helper,
    errorFor,
    extra = {},
    after,
}: {
    id: string;
    label: string;
    kind: 'totp' | 'recovery';
    action: string;
    submitLabel: string;
    busyLabel?: string;
    helper?: ReactNode;
    /** Copy for the server's `errors.code` or `errors.form` (undefined: not a field error). */
    errorFor: (code: string) => string | undefined;
    extra?: Record<string, string>;
    after?: ReactNode;
}) {
    const form = useForm({ code: '', ...extra });
    const ref = useRef<HTMLInputElement>(null);
    const inFlight = useRef(false);
    const errors = form.errors as Record<string, string | undefined>;
    // An empty submit shows a message as well as moving focus (F-07).
    const checks = useClientValidation({
        code: {
            id,
            label,
            rule: (v) =>
                v.replace(/\s+/g, '') === '' ? copy.codeRequired : undefined,
        },
    });
    const raw = errors.code ?? errors.form;
    // The caller maps the server's code or form error to the code field's
    // message (undefined: not a field error, shown by the page instead).
    const error = checks.error('code', raw ? errorFor(raw) : undefined);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        let cleaned = '';
        const ok = checks.check(event.currentTarget, (values) => {
            cleaned = values.code.replace(/\s+/g, '');
            form.setData('code', values.code);
        });

        if (!ok || cleaned === '' || inFlight.current) {
            return;
        }
        inFlight.current = true;
        form.transform((data) => ({ ...data, ...extra, code: cleaned }));
        form.post(action, {
            preserveScroll: true,
            onFinish: () => {
                inFlight.current = false;
            },
            onError: () => {
                form.setData('code', '');
                ref.current?.focus();
            },
        });
    };

    return (
        <form
            className="tt-col"
            style={{ gap: 12 }}
            onSubmit={submit}
            noValidate
        >
            <Field
                id={id}
                name="code"
                label={label}
                code
                inputRef={ref}
                inputMode={kind === 'totp' ? 'numeric' : 'text'}
                autoComplete={kind === 'totp' ? 'one-time-code' : 'off'}
                autoCapitalize="none"
                spellCheck={false}
                maxLength={kind === 'totp' ? 12 : 32}
                value={form.data.code}
                helper={helper}
                error={error}
                after={after}
                {...checks.bind('code', (v) => form.setData('code', v))}
            />
            <div>
                <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    disabled={form.processing}
                >
                    {form.processing ? busyLabel : submitLabel}
                </Button>
            </div>
        </form>
    );
}
