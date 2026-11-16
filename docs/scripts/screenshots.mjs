// Regenerates every screenshot in docs/images. See README.md in this folder.
//
// Public pages come from production, read-only. Everything signed in comes from a LOCAL
// development site with a demo account, demo captures and a real run of the agent against
// demo-handler.mjs. Nothing is created on production.
import { spawn } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { mkdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';
import sharp from 'sharp';

const here = dirname(fileURLToPath(import.meta.url));
const repo = resolve(here, '../..');
const images = join(repo, 'docs/images');
const work = join(here, '.out/run');

const PROD = process.env.WT_PROD ?? 'https://app.webhooktrap.dev';
const LOCAL = process.env.WT_LOCAL ?? 'https://webhooktrap.test';
const EMAIL = process.env.WT_DEMO_EMAIL ?? 'maya@example.com';
const PASSWORD = process.env.WT_DEMO_PASSWORD;
const BIN = process.env.WEBHOOKTRAP_BIN;
const ONLY = (process.env.ONLY ?? 'prod,guest,account').split(',');

// Demo values only: this secret signs the demo captures and the demo handler checks it.
const DEMO_SECRET = 'whsec_demo_docs_only_not_a_real_secret';
const MAGENTA = '#e977bb';

if (ONLY.some((s) => s !== 'prod') && (!PASSWORD || !BIN)) {
    console.error('Set WT_DEMO_PASSWORD and WEBHOOKTRAP_BIN (see README.md).');
    process.exit(2);
}
if (!/\.test(:\d+)?$|localhost/.test(new URL(LOCAL).host)) {
    console.error(`WT_LOCAL must be a local development host, got ${LOCAL}`);
    process.exit(2);
}

mkdirSync(images, { recursive: true });
rmSync(work, { recursive: true, force: true });
mkdirSync(work, { recursive: true });

const written = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------- page helpers

/** Local host names and addresses shown as the hosted ones, so no shot shows a dev name. */
async function sanitize(page) {
    const host = new URL(LOCAL).host;
    await page.evaluate((host) => {
        const swap = (s) =>
            s
                .replaceAll(`https://${host}`, 'https://app.webhooktrap.dev')
                .replaceAll(`${host}/c/`, 'in.webhooktrap.dev/c/')
                .replaceAll(host, 'app.webhooktrap.dev')
                .replace(/\b10\.\d+\.\d+\.\d+\b/g, '203.0.113.24')
                .replace(/\b10\.\d+\.\d+\.0\/24\b/g, '203.0.113.0/24');
        const walk = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        for (let n = walk.nextNode(); n; n = walk.nextNode()) {
            const next = swap(n.nodeValue);
            if (next !== n.nodeValue) n.nodeValue = next;
        }
        for (const el of document.querySelectorAll('input')) {
            if (el.value) el.value = swap(el.value);
        }
    }, host);
}

/** Draws magenta frames (and optional numbers) around elements, as a page overlay. */
async function highlight(page, targets) {
    const boxes = [];
    for (const [i, t] of targets.entries()) {
        const loc = typeof t.boundingBox === 'function' ? t : t.locator;
        await loc.scrollIntoViewIfNeeded();
        const b = await loc.boundingBox();
        if (b) boxes.push({ ...b, label: t.label ?? (targets.length > 1 ? String(i + 1) : '') });
    }
    await page.evaluate(
        ({ boxes, color }) => {
            for (const b of boxes) {
                const pad = 6;
                const d = document.createElement('div');
                d.dataset.wtShot = '';
                Object.assign(d.style, {
                    position: 'absolute',
                    left: `${b.x + scrollX - pad}px`,
                    top: `${b.y + scrollY - pad}px`,
                    width: `${b.width + pad * 2}px`,
                    height: `${b.height + pad * 2}px`,
                    border: `2.5px solid ${color}`,
                    borderRadius: '8px',
                    boxShadow: `0 0 0 4px ${color}33`,
                    pointerEvents: 'none',
                    zIndex: 2147483647,
                });
                if (b.label) {
                    const n = document.createElement('span');
                    n.textContent = b.label;
                    Object.assign(n.style, {
                        position: 'absolute',
                        left: '-14px',
                        top: '-14px',
                        width: '24px',
                        height: '24px',
                        borderRadius: '12px',
                        background: color,
                        color: '#141411',
                        font: '700 13px/24px Archivo, system-ui, sans-serif',
                        textAlign: 'center',
                    });
                    d.append(n);
                }
                document.body.append(d);
            }
        },
        { boxes, color: MAGENTA },
    );
}

async function clearHighlights(page) {
    await page.evaluate(() => document.querySelectorAll('[data-wt-shot]').forEach((e) => e.remove()));
}

/**
 * Takes one screenshot and writes docs/images/<name>.webp.
 *   region: a locator to crop around (with padding), else the viewport
 *   marks:  locators (or {locator, label}) to frame in magenta
 */
async function shot(page, name, { region, marks = [], pad = 24, fullPage = false } = {}) {
    await sanitize(page);
    if (marks.length) await highlight(page, marks);
    let png;
    if (region) {
        // Document coordinates, so a region taller than the viewport still fits.
        const b = await region.evaluate((el) => {
            const r = el.getBoundingClientRect();
            return { x: r.x + scrollX, y: r.y + scrollY, width: r.width, height: r.height };
        });
        const doc = await page.evaluate(() => ({ w: document.documentElement.scrollWidth, h: document.documentElement.scrollHeight }));
        const x = Math.max(0, b.x - pad);
        const y = Math.max(0, b.y - pad);
        const clip = {
            x,
            y,
            width: Math.min(doc.w - x, b.width + pad * 2),
            height: Math.min(doc.h - y, b.height + pad * 2),
        };
        png = await page.screenshot({ clip, fullPage: true });
    } else {
        png = await page.screenshot({ fullPage });
    }
    await clearHighlights(page);
    const file = join(images, `${name}.webp`);
    await sharp(png).webp({ quality: 88, effort: 6, smartSubsample: true }).toFile(file);
    written.push({ name: `${name}.webp`, bytes: statSync(file).size });
    console.log(`  ${name}.webp  ${(statSync(file).size / 1024).toFixed(0)} KB`);
}

async function open(page, url) {
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await sleep(400);
}

async function newContext(browser, extra = {}) {
    return browser.newContext({
        viewport: { width: 1440, height: 900 },
        deviceScaleFactor: 2,
        colorScheme: 'dark',
        reducedMotion: 'reduce',
        locale: 'en-GB',
        timezoneId: 'UTC',
        ...extra,
    });
}

// ---------------------------------------------------------------- demo data

/** Sends three signed Stripe events (the agent's synthetic test fixtures) to a capture URL. */
async function sendStripeEvents(captureUrl) {
    for (const type of ['invoice.paid', 'payment_intent.succeeded', 'customer.subscription.updated']) {
        const fx = JSON.parse(readFileSync(join(repo, `agent/testdata/fixtures/stripe/${type}.json`), 'utf8'));
        const t = Math.floor(Date.now() / 1000);
        const v1 = createHmac('sha256', DEMO_SECRET).update(`${t}.${fx.body}`).digest('hex');
        const res = await fetch(captureUrl, {
            method: 'POST',
            body: fx.body,
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                'User-Agent': 'Stripe/1.0 (+https://stripe.com/docs/webhooks)',
                'Stripe-Signature': `t=${t},v1=${v1}`,
            },
        });
        if (res.status !== 200) throw new Error(`capture answered ${res.status}`);
        await sleep(450);
    }
}

async function captureUrlOf(page) {
    const text = await page.locator('body').innerText();
    const path = /\/c\/[A-Za-z0-9]{12}/.exec(text)?.[0];
    if (!path) throw new Error('no capture URL on the page');
    return LOCAL + path;
}

function startHandler() {
    return spawn(process.execPath, [join(here, 'demo-handler.mjs')], {
        env: { ...process.env, STRIPE_WEBHOOK_SECRET: DEMO_SECRET, PORT: '4242' },
        stdio: 'ignore',
    });
}

/** The demo project the agent runs in: webhooktrap.yml and the same events as fixtures. */
function writeDemoApp() {
    const app = join(work, 'demo-app');
    mkdirSync(join(app, 'webhooktrap/fixtures/stripe'), { recursive: true });
    writeFileSync(
        join(app, 'webhooktrap.yml'),
        [
            'target: http://127.0.0.1:4242/webhooks/stripe',
            'provider: stripe',
            'secret: env:STRIPE_WEBHOOK_SECRET',
            'probe:',
            '  - as: orders.count',
            '    url: http://127.0.0.1:4242/_debug/orders',
            'reset: curl -fsS -X POST http://127.0.0.1:4242/_debug/reset',
            '',
        ].join('\n'),
    );
    return app;
}

const agentEnv = () => ({
    ...process.env,
    WEBHOOKTRAP_CONFIG_DIR: join(work, 'agent-config'),
    STRIPE_WEBHOOK_SECRET: DEMO_SECRET,
});

function pair(code) {
    return new Promise((ok, fail) => {
        const p = spawn(BIN, ['pair', code, '--server', LOCAL, '--name', 'maya-mbp', '--yes'], {
            env: agentEnv(),
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        let err = '';
        p.stderr.on('data', (d) => (err += d));
        p.on('exit', (c) => (c === 0 ? ok() : fail(new Error(`pair exited ${c}: ${err}`))));
    });
}

/** Removes the demo agent from the server and deletes its key, so the next run starts clean. */
function unpair() {
    return new Promise((ok) => {
        const p = spawn(BIN, ['agent', 'unpair', '--server', LOCAL], { env: agentEnv(), stdio: 'ignore' });
        p.on('exit', () => ok());
        p.on('error', () => ok());
    });
}

/** Runs `webhooktrap agent` under a pseudo-terminal and answers its [y/N] questions. */
function startAgent(app) {
    const log = join(work, 'agent.log');
    writeFileSync(log, '');
    const p = spawn('script', ['-qfc', `${BIN} agent`, log], {
        cwd: app,
        env: agentEnv(),
        stdio: ['pipe', 'ignore', 'ignore'],
    });
    let answered = 0;
    const timer = setInterval(() => {
        const asked = (readFileSync(log, 'utf8').match(/\[y\/N\]/g) ?? []).length;
        for (; answered < asked; answered++) p.stdin.write('y\n');
    }, 250);
    return () => {
        clearInterval(timer);
        p.kill('SIGINT');
    };
}

// ---------------------------------------------------------------- flows

async function prodPages(browser) {
    console.log('production (read-only)');
    const ctx = await newContext(browser);
    const page = await ctx.newPage();

    await open(page, `${PROD}/`);
    await sleep(9000); // the hero terminal plays once
    await shot(page, 'landing');

    await open(page, `${PROD}/r/example`);
    await shot(page, 'example-run-scenarios', { region: page.locator('.tt-run-strip').first(), pad: 14 });

    await ctx.close();
}

async function guestFlow(browser) {
    console.log('guest session (local)');
    const ctx = await newContext(browser);
    const page = await ctx.newPage();

    await open(page, `${LOCAL}/r/example`);
    await shot(page, 'quickstart-example-get-url', {
        region: page.locator('.tt-run-head').first(),
        marks: [page.getByRole('button', { name: /^Get a URL$/ }).first()],
    });
    await page.getByRole('button', { name: /^Get a URL$/ }).first().click();
    await page.waitForURL(/\/p\/[A-Za-z0-9]{10}/);
    await page.waitForLoadState('networkidle');
    await page.getByText('Live', { exact: true }).first().waitFor();
    const captureUrl = await captureUrlOf(page);
    const card = page.locator('.tt-waiting').first();
    await shot(page, 'quickstart-capture-url', {
        marks: [page.locator('.tt-waiting-url').first()],
    });
    await shot(page, 'quickstart-provider-tabs', { region: card });

    await sendStripeEvents(captureUrl);
    await sleep(1500);
    await open(page, page.url());
    await shot(page, 'quickstart-captures-arrived', {
        marks: [page.locator('.tt-cap-list').first()],
    });
    await shot(page, 'guest-session-bar', { region: page.getByText(/Guest session · expires/).first(), pad: 12 });

    const key = new URL(page.url()).pathname.split('/')[2];
    await open(page, `${LOCAL}/p/${key}/agent`);
    await page.getByRole('button', { name: 'Pair an agent' }).click();
    await page.getByText(/expires in/).first().waitFor();
    await sleep(500);
    await shot(page, 'guest-pairing-code', {
        marks: [page.locator('main').getByText(/npx @webhooktrap\/agent@1 pair/).first()],
    });
    await ctx.close();
}

async function accountFlow(browser) {
    console.log('account (local)');
    const handler = startHandler();
    const app = writeDemoApp();
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    let stopAgent = () => {};
    try {
        await open(page, `${LOCAL}/sign-in`);
        await page.locator('#email').click();
        await page.keyboard.type(EMAIL);
        await page.locator('#password').click();
        await page.keyboard.type(PASSWORD);
        await page.locator('form').filter({ has: page.locator('#password') }).getByRole('button', { name: /Sign in/ }).click();
        await page.waitForURL((u) => !u.pathname.startsWith('/sign-in'));

        await open(page, `${LOCAL}/projects/new`);
        await page.getByLabel('Project name').fill('brindle-app');
        await shot(page, 'project-new', { region: page.locator('main form').first(), pad: 32 });
        await page.getByRole('button', { name: 'Create' }).click();
        await page.waitForURL(/\/p\/[A-Za-z0-9]{10}/);
        await page.waitForLoadState('networkidle');
        const key = new URL(page.url()).pathname.split('/')[2];
        const captureUrl = await captureUrlOf(page);

        await sendStripeEvents(captureUrl);
        await sleep(1500);
        await open(page, `${LOCAL}/p/${key}`);
        await shot(page, 'captures-stream');
        await shot(page, 'captures-stream-group', {
            marks: [page.locator('.tt-grow').first()],
        });

        await open(page, `${LOCAL}/p/${key}/d/1`);
        await shot(page, 'delivery-detail');
        await shot(page, 'delivery-signature-not-checked', {
            region: page.locator('#dpanel-verdict section.tt-sec').first(),
        });

        // Pairing: the browser shows a code; the agent joins with it.
        await open(page, `${LOCAL}/agents/pair`);
        await page.getByRole('button', { name: 'Pair an agent' }).click();
        await page.getByText(/expires in/).first().waitFor();
        await sleep(500);
        const code = /\b[0-9A-HJKMNP-TV-Z]{5}-[0-9A-HJKMNP-TV-Z]{5}\b/.exec(await page.locator('main').innerText())[0];
        await shot(page, 'pairing-code', {
            marks: [page.locator('main').getByText(/npx @webhooktrap\/agent@1 pair/).first()],
        });
        await pair(code);
        stopAgent = startAgent(app);
        await sleep(3000);

        await open(page, `${LOCAL}/agents`);
        await shot(page, 'agents-list');

        // A run from the browser, executed by the agent against demo-handler.mjs.
        await open(page, `${LOCAL}/p/${key}`);
        await shot(page, 'prove-group-button', {
            region: page.locator('.tt-detail-actions').first(),
            pad: 28,
            marks: [page.getByRole('link', { name: 'Prove group' })],
        });
        await page.getByRole('link', { name: 'Prove group' }).click();
        await page.waitForURL(/runs\/new/);
        await page.waitForLoadState('networkidle');
        await page.getByText(/Connected · maya-mbp/).waitFor();
        await sleep(500);
        await shot(page, 'run-draft', {
            marks: [
                { locator: page.locator('fieldset.tt-scnset'), label: '1' },
                { locator: page.locator('section.tt-sec').filter({ has: page.locator('#h-agent') }), label: '2' },
                { locator: page.getByRole('button', { name: 'Run', exact: true }), label: '3' },
            ],
        });
        await page.getByRole('button', { name: 'Run', exact: true }).click();
        await page.waitForURL(/\/r\/run_/);
        const runUrl = page.url();
        await page.getByText(/Run #\d+ finished/).waitFor({ timeout: 120_000 });
        await open(page, runUrl);

        await shot(page, 'run-result');
        await shot(page, 'run-verdict', {
            region: page.locator('.tt-run-verdict'),
        });
        const f1 = page.locator('li.tt-finding').first();
        await shot(page, 'finding-detail', { region: f1, pad: 12 });
        await shot(page, 'run-deliveries', {
            region: page.locator('.tt-run-deliveries'),
            marks: [page.getByRole('row').filter({ hasText: 'body changed' }).first()],
        });

        await open(page, `${LOCAL}/p/${key}/runs`);
        await shot(page, 'runs-list');

        await open(page, `${LOCAL}/p/${key}/settings/capture`);
        await shot(page, 'project-settings-capture');
        await shot(page, 'project-retention', {
            region: page.locator('form.tt-sec').filter({ hasText: 'Keep deliveries for' }),
        });

        await open(page, `${LOCAL}/account/security`);
        await shot(page, 'account-security');
    } finally {
        stopAgent();
        await sleep(500);
        await unpair();
        handler.kill();
        await ctx.close();
    }
}

// ---------------------------------------------------------------- main

const browser = await chromium.launch({ channel: process.env.PW_CHANNEL ?? 'chrome' });
try {
    if (ONLY.includes('prod')) await prodPages(browser);
    if (ONLY.includes('guest')) await guestFlow(browser);
    if (ONLY.includes('account')) await accountFlow(browser);
} finally {
    await browser.close();
}
const total = written.reduce((s, w) => s + w.bytes, 0);
console.log(`${written.length} images, ${(total / 1024).toFixed(0)} KB`);
