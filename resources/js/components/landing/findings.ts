import type { Segment, Tone } from './run-demo';

/** "A finding is a failing test" section: the three findings of Run #3. */

export type DiffLine = {
    /** Line number shown in the gutter; empty for the hunk header. */
    n: string;
    kind: 'ctx' | 'add' | 'del' | 'hunk';
    text: string;
};

export type Finding = {
    id: 'f2' | 'f1' | 'f3';
    tab: string;
    glyph: string;
    tone: Tone;
    scenarios: string;
    title: string;
    expectation: { term: string; value: string; tone?: Tone; bold?: boolean }[];
    evidence?: Segment[][];
    why: string;
    cite: string;
    file: string;
    stat: Segment[];
    diff: DiffLine[];
    after: Segment[];
};

const ctx = (n: string, text: string): DiffLine => ({ n, kind: 'ctx', text });
const add = (n: string, text: string): DiffLine => ({ n, kind: 'add', text });
const del = (n: string, text: string): DiffLine => ({ n, kind: 'del', text });
const hunk = (text: string): DiffLine => ({ n: '', kind: 'hunk', text });

/*
 * ILLUSTRATIVE: the fix diffs below are written for the landing (decision 57).
 * The product does not read the user's code; in the app a finding carries the
 * fix as text only.
 */
export const findings: Finding[] = [
    {
        id: 'f2',
        tab: 'F2 High',
        glyph: '▲',
        tone: 'orange',
        scenarios: 'Twice · Retry after error',
        title: 'F2 High · Duplicate delivery created a second order',
        expectation: [
            { term: 'orders.count', value: '' },
            { term: 'Expected', value: '1', tone: 'green' },
            { term: 'Received', value: '2', tone: 'red', bold: true },
        ],
        evidence: [
            [
                { text: 'D4 ', tone: 'dim' },
                { text: '  invoice.paid  evt_1QK9…pjPU  ' },
                { text: '200', tone: 'green' },
                { text: '  ' },
                { text: '0 → 1', tone: 'muted' },
            ],
            [
                { text: 'D5 ', tone: 'dim' },
                { text: '  invoice.paid  evt_1QK9…pjPU  ' },
                { text: '200', tone: 'green' },
                { text: '  ' },
                { text: '1 → 2', tone: 'red' },
                { text: '  ' },
                { text: 'same event id', tone: 'dim' },
            ],
            [
                { text: 'D11', tone: 'dim' },
                { text: '  invoice.paid  retry          ' },
                { text: '200', tone: 'green' },
                { text: '  ' },
                { text: '1 → 2', tone: 'red' },
            ],
        ],
        why: 'Stripe may deliver the same event more than once, and retries failed or timed-out deliveries with backoff for up to 3 days in live mode. Each duplicate creates another order.',
        cite: 'Stripe docs › Webhooks › Handle duplicate events · Retry behavior',
        file: 'src/webhooks/stripe.js',
        stat: [{ text: '+5', tone: 'green' }],
        diff: [
            hunk("@@ app.post('/webhooks/stripe') @@"),
            ctx(
                '14',
                '    event = stripe.webhooks.constructEvent(req.body, sig, endpointSecret);',
            ),
            ctx('15', '  ...'),
            add('21', ' // before creating the order'),
            add('22', ' const { rowCount } = await db.query('),
            add(
                '23',
                "   'INSERT INTO processed_events (id) VALUES ($1) ON CONFLICT DO NOTHING',",
            ),
            add('24', '   [event.id]'),
            add('25', ' );'),
            add(
                '26',
                ' if (rowCount === 0) return res.sendStatus(200); // already handled',
            ),
            ctx('27', '  await createOrder(event.data.object);'),
            ctx('28', '  res.json({ received: true });'),
        ],
        after: [
            {
                text: 'After the fix, D5 and D11 should leave orders.count at 1. Run #4: ',
            },
            { text: 'Passed · 1 Info · exit 0', tone: 'green', bold: true },
        ],
    },
    {
        id: 'f1',
        tab: 'F1 Critical',
        glyph: '■',
        tone: 'red',
        scenarios: 'Tampered',
        title: 'F1 Critical · Accepts tampered payload',
        expectation: [
            { term: 'D12 body', value: 'amount_paid 2400 → 0' },
            { term: 'Expected', value: 'a 4xx and no change', tone: 'green' },
            {
                term: 'Received',
                value: '200 in 167 ms · orders.count 0 → 1',
                tone: 'red',
                bold: true,
            },
        ],
        why: "Stripe signs the exact bytes it sends. If a failed check doesn't stop the request, anyone who can reach this URL can create paid orders. Late (D9) was rejected, so the timestamp check runs; the signature check probably fails into a log line.",
        cite: 'Stripe docs › Webhooks › Verify signatures',
        file: 'src/webhooks/stripe.js',
        stat: [
            { text: '−1', tone: 'red' },
            { text: ' ' },
            { text: '+1', tone: 'green' },
        ],
        diff: [
            hunk("@@ app.post('/webhooks/stripe') @@"),
            ctx('12', '  try {'),
            ctx(
                '13',
                "    event = stripe.webhooks.constructEvent(req.body, req.get('stripe-signature'), endpointSecret);",
            ),
            ctx('14', '  } catch (err) {'),
            del(
                '15',
                "   console.warn('signature check failed', err.message);",
            ),
            add(
                '15',
                "   return res.status(400).send('Invalid signature'); // stop here, don't log and continue",
            ),
            ctx('16', '  }'),
        ],
        after: [
            {
                text: 'After the fix, D12 should get 400 and leave orders.count at 0.',
            },
        ],
    },
    {
        id: 'f3',
        tab: 'F3 Medium',
        glyph: '△',
        tone: 'amber',
        scenarios: 'Retry after error · Slow budget',
        title: 'F3 Medium · Over the 10 s budget for Stripe',
        expectation: [
            { term: 'Budget', value: '10 000 ms · Webhook Trap default' },
            {
                term: 'D10',
                value: '11 840 ms (+1 840 over)',
                tone: 'amber',
                bold: true,
            },
            {
                term: 'D16',
                value: '10 420 ms (+420 over)',
                tone: 'amber',
                bold: true,
            },
            { term: 'Others', value: 'median 202 ms' },
        ],
        why: "If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout, so these are over our default budget, not proven failures at Stripe. In this run the retry after D10 created the duplicate order in F2.",
        cite: 'Stripe docs › Webhooks › Quickly return a 2xx response',
        file: 'src/webhooks/stripe.js',
        stat: [
            { text: '−2', tone: 'red' },
            { text: ' ' },
            { text: '+2', tone: 'green' },
        ],
        diff: [
            hunk("@@ case 'invoice.paid': @@"),
            ctx('31', '  await createOrder(invoice);'),
            del('32', ' await createShippingLabel(invoice);'),
            del('33', ' res.json({ received: true });'),
            add('32', ' res.sendStatus(200);            // acknowledge first'),
            add(
                '33',
                " await queue.add('create-label', { invoiceId: event.data.object.id });",
            ),
        ],
        after: [
            {
                text: 'After the fix, D10 and D16 should respond within the budget.',
            },
        ],
    },
];
