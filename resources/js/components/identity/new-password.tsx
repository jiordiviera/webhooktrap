import { useClientValidation } from '@/hooks/use-client-validation';
import { copy, passwordError } from '@/lib/identity/copy';
import type { PasswordPolicy } from '@/types/identity';
import { PasswordField } from './form';

/*
 * "New password" + "Confirm new password" (S-17B `password`, S-26, S-29 #9).
 * Client checks length and match; the server adds `password_common` and
 * `password_is_email`.
 */

export type NewPassword = { password: string; password_confirmation: string };

/** Length the policy counts: code points, or UTF-8 bytes on bcrypt images. */
export function passwordLength(value: string, policy: PasswordPolicy): number {
    if (policy.unit === 'bytes') {
        return new TextEncoder().encode(value).length;
    }
    let count = 0;

    for (const _ of value) {
        count++;
    }

    return count;
}

/**
 * Client checks for the pair (shared useClientValidation): length on the
 * new password, then the match on the confirmation.
 */
export function useNewPassword(policy: PasswordPolicy, idPrefix = 'new') {
    const lengthError = (value: string) => {
        const length = passwordLength(value, policy);

        if (length < policy.minLength) {
            return passwordError('password_too_short', policy.unit);
        }

        return length > policy.maxLength
            ? passwordError('password_too_long', policy.unit)
            : undefined;
    };
    const checks = useClientValidation<keyof NewPassword>({
        password: {
            id: `${idPrefix}-password`,
            label: 'New password',
            rule: lengthError,
        },
        password_confirmation: {
            id: `${idPrefix}-confirm`,
            label: 'Confirm new password',
            rule: (value, values) =>
                !lengthError(values.password) && value !== values.password
                    ? copy.passwordsDiffer
                    : undefined,
        },
    });

    /** Messages under the two fields, with the server's mapped in. */
    const shown = (serverError?: string) => {
        const server = passwordError(serverError, policy.unit);
        const confirmServer =
            server === copy.passwordsDiffer ? server : undefined;

        return {
            password: checks.error(
                'password',
                confirmServer ? undefined : server,
            ),
            password_confirmation: checks.error(
                'password_confirmation',
                confirmServer,
            ),
        };
    };

    return { ...checks, shown, idPrefix };
}

export type NewPasswordChecks = ReturnType<typeof useNewPassword>;

export function NewPasswordFields({
    policy,
    data,
    setData,
    checks,
    serverError,
}: {
    policy: PasswordPolicy;
    data: NewPassword;
    setData: (key: keyof NewPassword, value: string) => void;
    checks: NewPasswordChecks;
    serverError?: string;
}) {
    const shown = checks.shown(serverError);

    return (
        <>
            <PasswordField
                id={`${checks.idPrefix}-password`}
                name="password"
                label="New password"
                autoComplete="new-password"
                value={data.password}
                helper={
                    policy.unit === 'bytes'
                        ? copy.passwordHelperBytes
                        : copy.passwordHelper
                }
                error={shown.password}
                {...checks.bind('password', (v) => setData('password', v))}
            />
            <PasswordField
                id={`${checks.idPrefix}-confirm`}
                name="password_confirmation"
                label="Confirm new password"
                autoComplete="new-password"
                value={data.password_confirmation}
                error={shown.password_confirmation}
                {...checks.bind('password_confirmation', (v) =>
                    setData('password_confirmation', v),
                )}
            />
        </>
    );
}
