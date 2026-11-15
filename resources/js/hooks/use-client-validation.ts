import type { ChangeEvent, FocusEvent } from 'react';
import { useState } from 'react';

/*
 * Client-side checks for the Identity forms, shared so every form behaves
 * the same way:
 * - submit reads the values from the form element (FormData), so Chrome
 *   autofill and the conditional passkey UI, which can fill an input
 *   without React's onChange, never fail a valid field; `sync` hands them
 *   to the caller (e.g. useForm.setData) before the post;
 * - editing a field clears its error, the client one and the server one;
 * - once the form was submitted, leaving a field re-checks it, so a fixed
 *   field turns clean at once;
 * - the first invalid field takes focus after a failed submit, and after
 *   the server refused one (`focusFirst` from the visit's onError).
 */

export type FieldSpec<K extends string> = {
    /** The input's id (focus, summary links). Its `name` is the spec key. */
    id: string;
    /** Visible label, for the summary links. */
    label: string;
    /** Client rule: the message, or undefined when the value is fine. */
    rule?: (value: string, values: Record<K, string>) => string | undefined;
};

export type SummaryField = { id: string; label: string };

function readValues<K extends string>(
    form: HTMLFormElement,
    keys: K[],
): Record<K, string> {
    const data = new FormData(form);

    return Object.fromEntries(
        keys.map((key) => {
            const value = data.get(key);

            return [key, typeof value === 'string' ? value : ''];
        }),
    ) as Record<K, string>;
}

export function useClientValidation<K extends string>(
    fields: Record<K, FieldSpec<K>>,
) {
    const keys = Object.keys(fields) as K[];
    const [client, setClient] = useState<Partial<Record<K, string>>>({});
    const [submitted, setSubmitted] = useState(false);
    /** Fields edited since the last submit: their server error is stale. */
    const [edited, setEdited] = useState<ReadonlySet<K>>(new Set());

    const ruleFor = (key: K, values: Record<K, string>) =>
        fields[key].rule?.(values[key] ?? '', values);

    /**
     * On submit: read the form, sync the values, run every rule. Returns
     * true when the form can be posted.
     */
    function check(
        form: HTMLFormElement,
        sync?: (values: Record<K, string>) => void,
    ): boolean {
        const values = readValues(form, keys);
        sync?.(values);
        const found: Partial<Record<K, string>> = {};

        for (const key of keys) {
            const message = ruleFor(key, values);

            if (message) {
                found[key] = message;
            }
        }
        setClient(found);
        setSubmitted(true);
        setEdited(new Set());
        const first = keys.find((key) => found[key]);

        if (first) {
            document.getElementById(fields[first].id)?.focus();

            return false;
        }

        return true;
    }

    function changed(key: K) {
        setClient((prev) => {
            if (!prev[key]) {
                return prev;
            }
            const next = { ...prev };
            delete next[key];

            return next;
        });
        setEdited((prev) => (prev.has(key) ? prev : new Set(prev).add(key)));
    }

    function revalidate(key: K, form: HTMLFormElement | null) {
        if (!submitted || !form) {
            return;
        }
        const message = ruleFor(key, readValues(form, keys));
        setClient((prev) => {
            if (prev[key] === message) {
                return prev;
            }
            const next = { ...prev };

            if (message) {
                next[key] = message;
            } else {
                delete next[key];
            }

            return next;
        });
    }

    /** onChange/onBlur for a field; `onValue` keeps the caller's state. */
    function bind(key: K, onValue?: (value: string) => void) {
        return {
            onChange: (event: ChangeEvent<HTMLInputElement>) => {
                onValue?.(event.target.value);
                changed(key);
            },
            onBlur: (event: FocusEvent<HTMLInputElement>) =>
                revalidate(key, event.currentTarget.form),
        };
    }

    /** The message to show under a field: client first, then server. */
    function error(key: K, server?: string): string | undefined {
        return client[key] ?? (edited.has(key) ? undefined : server);
    }

    /** Links for the summary when two or more fields are invalid. */
    function summary(shown: Partial<Record<K, string | undefined>>) {
        const invalid = keys.filter((key) => shown[key]);

        return invalid.length >= 2
            ? invalid.map((key) => ({
                  id: fields[key].id,
                  label: fields[key].label,
              }))
            : [];
    }

    /**
     * After the server refused the form: focus the first field it named, in
     * the spec's order. Returns false when no field matched (a form-level
     * error), so the caller can focus its summary instead.
     */
    function focusFirst(errors: Partial<Record<string, string>>): boolean {
        const first = keys.find((key) => errors[key]);

        if (first) {
            document.getElementById(fields[first].id)?.focus();
        }

        return first !== undefined;
    }

    return { check, bind, error, summary, changed, focusFirst };
}
