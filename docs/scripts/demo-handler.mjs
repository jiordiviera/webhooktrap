// A deliberately buggy Stripe webhook handler, used only to produce a real run
// with findings for the documentation screenshots. Do not copy it.
//
// Bugs, on purpose:
//   - it checks the timestamp (Late is rejected), but a wrong signature is only
//     logged, so a tampered body is accepted;
//   - it never records the event id, so a duplicate creates a second order.
//
// Endpoints (127.0.0.1:4242):
//   POST /webhooks/stripe   the handler
//   GET  /_debug/orders     the orders count (the run's probe)
//   POST /_debug/reset      empties the tables (the run's reset)
import { createHmac, timingSafeEqual } from 'node:crypto';
import { createServer } from 'node:http';

const secret = process.env.STRIPE_WEBHOOK_SECRET ?? '';
const port = Number(process.env.PORT ?? 4242);
let orders = 0;

function signatureOk(header, body) {
    const parts = Object.fromEntries(
        String(header)
            .split(',')
            .map((kv) => kv.split('=')),
    );
    const expected = createHmac('sha256', secret).update(`${parts.t}.${body}`).digest('hex');
    const got = Buffer.from(parts.v1 ?? '', 'utf8');

    return got.length === expected.length && timingSafeEqual(got, Buffer.from(expected));
}

createServer((req, res) => {
    let body = '';
    req.on('data', (c) => (body += c));
    req.on('end', () => {
        res.setHeader('X-Powered-By', 'Express');
        if (req.method === 'GET' && req.url === '/_debug/orders') {
            return res.end(String(orders));
        }
        if (req.method === 'POST' && req.url === '/_debug/reset') {
            orders = 0;
            return res.end('ok');
        }
        if (req.method === 'POST' && req.url === '/webhooks/stripe') {
            const header = req.headers['stripe-signature'];
            if (!header) {
                res.statusCode = 400;
                return res.end('missing signature');
            }
            const t = Number(/t=(\d+)/.exec(header)?.[1] ?? 0);
            if (Math.abs(Date.now() / 1000 - t) > 300) {
                res.statusCode = 400;
                return res.end('stale');
            }
            if (!signatureOk(header, body)) {
                console.warn('signature mismatch'); // bug: logged, not refused
            }
            const event = JSON.parse(body);
            if (event.type === 'invoice.paid') {
                orders += 1; // bug: no check on event.id
            }
            return res.end('{"received":true}');
        }
        res.statusCode = 404;
        res.end();
    });
}).listen(port, '127.0.0.1', () => console.log(`demo handler on http://127.0.0.1:${port}`));
