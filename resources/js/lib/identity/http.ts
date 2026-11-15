/*
 * JSON calls to the WebAuthn endpoints (contracts §3.4): same-origin fetch,
 * session cookie, CSRF from the __Host- XSRF cookie (TM-T4). Inertia handles
 * every other request.
 */

const XSRF_COOKIE = '__Host-XSRF-TOKEN';

export function xsrfToken(): string | null {
    const match = document.cookie
        .split('; ')
        .find((part) => part.startsWith(`${XSRF_COOKIE}=`));

    return match
        ? decodeURIComponent(match.slice(XSRF_COOKIE.length + 1))
        : null;
}

export type JsonResult<T> =
    | { ok: true; status: number; data: T }
    | {
          ok: false;
          status: number;
          error: string;
          retryAfter: number | null;
          redirect?: string;
      };

export async function postJson<T>(
    url: string,
    body: unknown = {},
): Promise<JsonResult<T>> {
    const token = xsrfToken();
    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify(body),
        });
    } catch {
        return { ok: false, status: 0, error: 'network', retryAfter: null };
    }

    let data: unknown = null;

    try {
        data = await response.json();
    } catch {
        data = null;
    }

    if (response.ok) {
        return { ok: true, status: response.status, data: data as T };
    }

    const payload = (data ?? {}) as { error?: string; redirect?: string };
    const retry = response.headers.get('Retry-After');

    return {
        ok: false,
        status: response.status,
        error: payload.error ?? (response.status === 419 ? 'csrf' : 'server'),
        retryAfter: retry !== null ? Number(retry) : null,
        redirect: payload.redirect,
    };
}

/** "Try again after" time from a Retry-After header, as an ISO string. */
export function retryAtFromSeconds(seconds: number | null): string {
    return new Date(Date.now() + (seconds ?? 60) * 1000).toISOString();
}
