/** The 9 MVP scenarios, RD §5 order. Copy from the Gate 2 board and the A04 mockup. */
export type Scenario = {
    name: string;
    /** Delivery count as the catalogue states it. */
    deliveries: string;
    what: string;
    /** Extra context shown first when the row is open. */
    note?: string;
    why: string;
    cite?: string;
    needsSecret: boolean;
    /** 16 px glyph path, 1.5 px square-cap stroke. */
    glyph: string;
};

export const scenarios: Scenario[] = [
    {
        name: 'Once',
        deliveries: 'n deliveries',
        what: 'Every selected event once, in capture order. Sets the baseline.',
        why: 'Every provider',
        needsSecret: false,
        glyph: 'M3 3 13 13',
    },
    {
        name: 'Twice',
        deliveries: '2 deliveries',
        what: 'The first event again, 500 ms after the first delivery completes: same event id, new signature timestamp',
        why: 'Stripe may deliver the same event more than once.',
        cite: 'Stripe docs › Webhooks › Handle duplicate events',
        needsSecret: true,
        glyph: 'M1.5 3 11.5 13M5.5 3 15.5 13',
    },
    {
        name: 'Shuffled',
        deliveries: 'n deliveries',
        what: 'Related events in reverse order',
        why: "Stripe doesn't guarantee delivery order.",
        cite: 'Stripe docs › Webhooks › Event ordering',
        needsSecret: true,
        glyph: 'M3 3 13 13M13 3 3 13',
    },
    {
        name: 'Late',
        deliveries: '1 delivery',
        what: 'Valid signature, timestamp 360 s old (tolerance 300 s)',
        why: 'Replay protection relies on the timestamp tolerance.',
        cite: 'Stripe docs › Webhooks › Prevent replay attacks',
        needsSecret: true,
        glyph: 'M6.5 1.5V14.5M2 3 13 14',
    },
    {
        name: 'Retry after error',
        deliveries: '2 deliveries',
        what: "Sends the event, then the provider's retry 2 s after it completes.",
        note: "Providers retry when a delivery fails or takes too long; your handler can't tell which.",
        why: 'Stripe retries failed or timed-out deliveries with backoff for up to 3 days in live mode.',
        cite: 'Stripe docs › Webhooks › Retry behavior',
        needsSecret: true,
        glyph: 'M2 3 7 13M9 3 14 13M7 13H9',
    },
    {
        name: 'Tampered',
        deliveries: '1 delivery',
        what: 'amount_paid 2400 → 0 in the body, signed for the original body',
        why: 'A signature only protects you if a failed check stops the request.',
        cite: 'Stripe docs › Webhooks › Verify signatures',
        needsSecret: true,
        glyph: 'M2 2 6 6H10L14 10',
    },
    {
        name: 'Unsigned',
        deliveries: '1 delivery',
        what: 'The event with every signature header removed',
        why: 'A handler that checks the signature only when the header is present accepts anything.',
        cite: 'Stripe docs › Webhooks › Verify signatures',
        needsSecret: false,
        glyph: 'M2 2 14 14',
    },
    {
        name: 'Burst',
        deliveries: 'max(n,2) deliveries',
        what: 'Every selected event, released together (≤ 20 ms apart)',
        why: 'Stripe sends related events close together, often concurrently.',
        cite: 'Stripe docs › Webhooks › Event ordering',
        needsSecret: true,
        glyph: 'M3 3 13 13M3 3V13M3 3H13',
    },
    {
        name: 'Slow budget',
        deliveries: '1 delivery',
        what: 'One more delivery after the others, measured against the budget: 10 s budget · Webhook Trap default',
        why: "If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout.",
        cite: 'Stripe docs › Webhooks › Quickly return a 2xx response',
        needsSecret: false,
        glyph: 'M2 11.5H10V2M2 3.5 6 7.5H14',
    },
];
