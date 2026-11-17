import type { CaptureRow, SignatureState } from '@/types/capture';

/*
 * Capture formatting and copy (S-03, S-04; RD §6 "Capture" and "Signature").
 * Times are UTC text, like the rest of the app. Lines marked COPY PROPOSAL
 * (A07) are not in the copy deck yet.
 */

const pad = (n: number, w = 2) => String(n).padStart(w, '0');

/** "#01" (at least two digits, IA rule 1). */
export const seqLabel = (seq: number): string => `#${pad(seq)}`;

/** "13:58:04.212" (UTC). */
export function clockMs(iso: string): string {
    const d = new Date(iso);

    return `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:${pad(d.getUTCSeconds())}.${pad(d.getUTCMilliseconds(), 3)}`;
}

/** "14:06:39" (UTC). */
export function clock(iso: string): string {
    return clockMs(iso).slice(0, 8);
}

/** "2 294 B", "48 KB", "12.4 MB" (RD style: thin groups, no decimals under 10 KB). */
export function bytes(n: number): string {
    if (n < 10_000) {
        return `${String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')} B`;
    }

    if (n < 1_000_000) {
        return `${Math.round(n / 1000)} KB`;
    }

    return `${(n / 1_000_000).toFixed(1)} MB`;
}

export const signatureCopy: Record<
    SignatureState,
    { label: string; helper: string }
> = {
    valid: { label: 'Signature valid', helper: '' },
    invalid: {
        label: 'Signature invalid',
        helper: 'Signed with a different secret. See the reason and fix.',
    },
    no_secret: {
        label: 'No secret',
        helper: "Signed, but no secret is set on your agent, so we can't check it.",
    },
    unknown_scheme: {
        label: 'Unknown scheme',
        helper: "There is a signature-like header but we don't recognise the scheme. Define it to check it.",
    },
    unsigned: {
        label: 'Unsigned',
        helper: 'No signature header. Nothing to check.',
    },
    not_checked: {
        label: 'Not checked',
        helper: 'Secrets live on your machine. Connect the agent to check signatures.',
    },
    checking: { label: 'Checking…', helper: '' },
};

export const shapeCopy = {
    matches: 'Shape matches baseline',
    new: 'New shape (first capture)',
    differs: 'Shape differs from baseline',
    none: 'No baseline',
};

export const captureCopy = {
    waiting: 'Waiting for the first delivery',
    waitingHelper:
        'Send any webhook to this URL. It appears here within a second.',
    live: 'Live',
    liveHelper: 'New deliveries appear at the top.',
    /** COPY PROPOSAL (A07): the socket can't connect at all. */
    paused: 'Live updates paused',
    pausedHelper:
        'Deliveries are still captured. Reload the page to see new ones.',
    streamLost: 'Live updates interrupted',
    streamLostHelper:
        'Reconnecting… Deliveries are still being captured on our side and will appear when the connection returns.',
    connecting: 'Connecting…',
    newPill: (n: number) => `${n} new ${n === 1 ? 'delivery' : 'deliveries'}`,
    noResults: (q: string, total: number) => ({
        label: `No deliveries match “${q}”`,
        helper: `Clear the filter to see all ${total}.`,
    }),
    bodyNotKept: (size: number) => ({
        label: `Body not kept (${bytes(size)})`,
        helper: 'Headers and a SHA-256 of the body are stored. The body itself was not.',
    }),
    binary: (type: string | null, size: number) => ({
        label: `Binary body (${type ?? 'unknown type'}, ${bytes(size)})`,
        helper: 'Shown as hex. Download the exact bytes.',
    }),
    /** COPY PROPOSAL (A07): truncation line under a long body. */
    truncated: (shown: number, size: number) =>
        `Showing the first ${bytes(shown)} of ${bytes(size)}. Download raw for the rest.`,
    /** COPY PROPOSAL (A07): A09 S3-16 hidden-characters warning. */
    hidden: 'This delivery contains hidden characters (controls, bidi or zero-width). They are shown as \\u{…} in a different style. Download raw for the exact bytes.',
    /** COPY PROPOSAL (A07): Decoded tab before Packs. */
    decodedLater: 'Decoded views arrive with provider packs. Use Raw for now.',
    sendTest: 'Send a test request',
    sendTestHelper: 'Sends a small JSON body to your URL from this browser.',
    /** COPY PROPOSAL (A07): announcer line, at most one per 5 s. */
    arrivals: (n: number) =>
        `${n} new ${n === 1 ? 'delivery' : 'deliveries'} arrived.`,
};

/**
 * A synthetic event for curl and Send a test request (issue #18): shaped like
 * a real webhook, with made-up ids and no personal data.
 */
export const SAMPLE_BODY = JSON.stringify({
    id: 'evt_test_0001',
    type: 'order.paid',
    created: '2026-11-18T09:30:00Z',
    data: { order_id: 'ord_1042', amount: 2500, currency: 'eur' },
});

/** COPY PROPOSAL (A07): how to send from your own app, and where to look (issue #18). */
export const fromYourApp =
    'From your own app: set its webhook or callback URL (often an environment variable such as WEBHOOK_URL) to this address and trigger the event. Each request appears here within a second; open it to see the headers, the exact body and the signature check.';

/** Waiting-panel provider instructions (RD `copy.capture.waiting`). */
export function providerInstructions(id: string, url: string): string {
    switch (id) {
        case 'stripe':
            return 'Test mode › Developers › Webhooks › Add destination. Paste the URL, pick the events you handle, save, then trigger a test checkout. Stripe sends straight here, no CLI needed.';
        case 'github':
            return 'Repository › Settings › Webhooks › Add webhook. Payload URL = this URL, Content type = application/json, set a secret.';
        case 'shopify':
            return 'Settings › Notifications › Webhooks › Create webhook. Format JSON, URL = this URL, API version 2025-10.';
        case 'standard_webhooks':
            return "Paste the URL as the endpoint in your provider's webhook settings. We detect webhook-id / webhook-signature headers automatically.";
        default:
            return `curl -X POST ${url} \\\n  -H 'Content-Type: application/json' \\\n  -H 'X-Event-Type: order.paid' \\\n  -d '${SAMPLE_BODY}'`;
    }
}

/** What a row says when there is no event type. */
export function rowTitle(row: CaptureRow): string {
    return row.eventType ?? `${row.method} ${row.path}`;
}

/** Split text on the server's `\u{XXXX}` escapes, for distinct styling. */
export function splitEscapes(
    text: string,
): { text: string; escape: boolean }[] {
    const parts: { text: string; escape: boolean }[] = [];
    const re = /\\u\{[0-9A-Fa-f]{1,6}\}/g;
    let last = 0;

    for (const match of text.matchAll(re)) {
        const at = match.index ?? 0;

        if (at > last) {
            parts.push({ text: text.slice(last, at), escape: false });
        }
        parts.push({ text: match[0], escape: true });
        last = at + match[0].length;
    }

    if (last < text.length) {
        parts.push({ text: text.slice(last), escape: false });
    }

    return parts;
}
