import type { VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import type { Intent } from '@/lib/identity/intent';
import {
    clearIntent,
    rememberIntent,
    RESUME_PARAM,
} from '@/lib/identity/intent';
import password from '@/routes/password';

/*
 * Requests from the Security dialogs. Actions behind `recent-auth` may come
 * back as S-28; the intent is kept so the dialog reopens after (A11Y G13).
 */

type Method = 'post' | 'put' | 'patch' | 'delete';

export function protectedVisit(
    method: Method,
    url: string,
    data: Record<string, string | boolean>,
    intent: Intent | null,
    options: Omit<VisitOptions, 'method' | 'data'> = {},
): void {
    if (intent) {
        rememberIntent(intent);
    }

    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        ...options,
        onSuccess: (page) => {
            if (page.component !== 'Auth/ConfirmAuth') {
                clearIntent();
            }
            options.onSuccess?.(page);
        },
    });
}

/** Sends the person to S-28 for a JSON endpoint that answered 403 recent_auth_required. */
export function confirmThenResume(intent: Intent): void {
    rememberIntent(intent);
    router.visit(
        password.confirm.url({
            query: {
                next: `/account/security?${RESUME_PARAM}=1`,
                action: 'security',
            },
        }),
    );
}
