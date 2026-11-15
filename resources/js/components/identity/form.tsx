import type {
    ButtonHTMLAttributes,
    InputHTMLAttributes,
    ReactNode,
    Ref,
} from 'react';
import { useId, useState } from 'react';
import type { SummaryField } from '@/hooks/use-client-validation';
import { cn } from '@/lib/utils';
import { Glyph } from './glyph';

/*
 * Form pieces of the Identity screens (DS FormControls, CodeInput). Every
 * field has a visible label; helper and error are tied with
 * aria-describedby; an invalid field has a 1 px danger border and a quiet
 * message line under it.
 */

type FieldProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'id'> & {
    id: string;
    label: string;
    helper?: ReactNode;
    error?: string;
    /** Content on the label row's right (e.g. "Forgot password?"). */
    aside?: ReactNode;
    code?: boolean;
    inputRef?: Ref<HTMLInputElement>;
    after?: ReactNode;
};

export function fieldIds(id: string, helper: boolean, error: boolean) {
    return (
        [helper ? `${id}-help` : null, error ? `${id}-err` : null]
            .filter(Boolean)
            .join(' ') || undefined
    );
}

export function FieldError({
    id,
    children,
}: {
    id: string;
    children: ReactNode;
}) {
    return (
        <p className="tt-ferr" id={id}>
            <Glyph name="error" />
            <span>{children}</span>
        </p>
    );
}

export function Field({
    id,
    label,
    helper,
    error,
    aside,
    code = false,
    inputRef,
    after,
    className,
    ...input
}: FieldProps) {
    return (
        <div className="tt-field">
            {aside ? (
                <span className="tt-labelrow">
                    <label className="tt-label" htmlFor={id}>
                        {label}
                    </label>
                    {aside}
                </span>
            ) : (
                <label className="tt-label" htmlFor={id}>
                    {label}
                </label>
            )}
            <input
                ref={inputRef}
                id={id}
                className={cn('tt-input', code && 'is-code', className)}
                aria-invalid={error ? true : undefined}
                aria-describedby={fieldIds(id, Boolean(helper), Boolean(error))}
                {...input}
            />
            {helper && (
                <p className="tt-hint" id={`${id}-help`}>
                    {helper}
                </p>
            )}
            {error && <FieldError id={`${id}-err`}>{error}</FieldError>}
            {after}
        </div>
    );
}

export function PasswordField({
    id,
    label,
    helper,
    error,
    aside,
    inputRef,
    ...input
}: Omit<FieldProps, 'type' | 'code'>) {
    const [shown, setShown] = useState(false);

    return (
        <div className="tt-field">
            {aside ? (
                <span className="tt-labelrow">
                    <label className="tt-label" htmlFor={id}>
                        {label}
                    </label>
                    {aside}
                </span>
            ) : (
                <label className="tt-label" htmlFor={id}>
                    {label}
                </label>
            )}
            <div className="tt-pw">
                <input
                    ref={inputRef}
                    id={id}
                    type={shown ? 'text' : 'password'}
                    className="tt-input"
                    aria-invalid={error ? true : undefined}
                    aria-describedby={fieldIds(
                        id,
                        Boolean(helper),
                        Boolean(error),
                    )}
                    {...input}
                />
                <button
                    className="tt-showbtn"
                    type="button"
                    aria-pressed={shown}
                    aria-controls={id}
                    aria-label="Show password"
                    onClick={() => setShown((v) => !v)}
                >
                    {shown ? 'Hide' : 'Show'}
                </button>
            </div>
            {helper && (
                <p className="tt-hint" id={`${id}-help`}>
                    {helper}
                </p>
            )}
            {error && <FieldError id={`${id}-err`}>{error}</FieldError>}
        </div>
    );
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'outline' | 'ghost' | 'danger';
    size?: 'sm' | 'md' | 'lg';
    block?: boolean;
    busy?: boolean;
    buttonRef?: Ref<HTMLButtonElement>;
};

export function buttonClass({
    variant = 'outline',
    size = 'md',
    block = false,
}: Pick<ButtonProps, 'variant' | 'size' | 'block'> = {}) {
    return cn(
        'tt-btn',
        variant === 'primary' && 'tt-btn-primary',
        variant === 'ghost' && 'tt-btn-ghost',
        variant === 'danger' && 'tt-btn-danger',
        size === 'sm' && 'tt-btn-sm',
        size === 'lg' && 'tt-btn-lg',
        block && 'tt-btn-block',
    );
}

export function Button({
    variant,
    size,
    block,
    busy,
    buttonRef,
    className,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button
            ref={buttonRef}
            type={type}
            aria-busy={busy || undefined}
            className={cn(buttonClass({ variant, size, block }), className)}
            {...props}
        />
    );
}

/** A disabled control that stays focusable, with its reason adjacent (A11Y §9). */
export function useReasonId(prefix: string) {
    const id = useId();

    return `${prefix}-${id.replace(/:/g, '')}`;
}

/**
 * Form-level alert above a form (`role="alert"`, once): invalid credentials,
 * throttled, expired challenge, OAuth. Field errors stay inline under their
 * field and are never repeated here; when two or more fields are invalid,
 * one short line links to them.
 */
export function ErrorSummary({
    message,
    fields = [],
}: {
    message?: string | null;
    fields?: SummaryField[];
}) {
    if (!message && fields.length < 2) {
        return null;
    }

    return (
        <div className="tt-errsum" role="alert">
            <Glyph name="error" />
            <div>
                {message && <p>{message}</p>}
                {fields.length >= 2 && (
                    <p>
                        Check the {fields.length} fields below:{' '}
                        {fields.map((f, i) => (
                            <span key={f.id}>
                                {i > 0 && ', '}
                                <a href={`#${f.id}`}>{f.label}</a>
                            </span>
                        ))}
                        .
                    </p>
                )}
            </div>
        </div>
    );
}

export function Note({
    children,
    role,
    glyph = 'info',
    className,
}: {
    children: ReactNode;
    role?: 'status' | 'alert';
    glyph?: 'info' | 'lock' | 'check' | 'blocked';
    className?: string;
}) {
    return (
        <div className={cn('tt-note', className)} role={role}>
            <Glyph name={glyph} />
            <div className="tt-col" style={{ gap: 6 }}>
                {children}
            </div>
        </div>
    );
}

export function FlashNote({ children }: { children: ReactNode }) {
    return (
        <div className="tt-flash" role="status">
            <Glyph name="check" />
            <p>{children}</p>
        </div>
    );
}

/**
 * PASS/FAIL-style status badge for a security state (decision 57). The state
 * is always written next to it, so the badge is hidden from assistive tech.
 */
export function StatusBadge({
    tone,
    children,
}: {
    tone: 'pass' | 'fail' | 'warn' | 'off';
    children: ReactNode;
}) {
    return (
        <span className={cn('tt-badge', `is-${tone}`)} aria-hidden="true">
            {children}
        </span>
    );
}
