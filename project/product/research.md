# Research: What Goes Wrong With Webhooks

**Status:** PROPOSED for PO review · **Author:** A01 (Product Strategist) · **Date:** 2026-11-11

## Method and limits

- Desk research on 2026-11-11: provider documentation, GitHub issues and discussions, Hacker News, developer write-ups (DEV, Medium), vendor guides, and launch listings. Both sides are covered: **consumers** (receiving) and **producers** (sending).
- 45 distinct sources are listed below. Vendor blogs (Hookdeck, Svix, ngrok, Convoy) have an interest in the problem being hard, so they are used for facts they can document and not for market-size claims.
- Searches did not surface Reddit or Stack Overflow threads directly. Developer pain is taken from first-hand DEV posts, HN comments, forum threads and one Stack Overflow mirror. Section 5 lists what could not be verified.
- Quotes are 15 words or fewer. Everything else is paraphrased.

## 1. Sources

| ID | Source | Type | Supports |
| -- | ------ | ---- | -------- |
| S1 | [Stripe: Receive Stripe events in your webhook endpoint](https://docs.stripe.com/webhooks) | Provider docs | Retries for up to 3 days in live mode with exponential backoff, and 3 times over a few hours in sandboxes. No ordering guarantee. Duplicate events can occur. The raw body is required for verification. "Thin" events are now recommended. The docs open by pointing to agent skills. |
| S2 | [Stripe: Verify webhook signatures](https://docs.stripe.com/webhooks/signature) | Provider docs | The body must be the unmodified UTF-8 string Stripe sent. A wrong endpoint secret is a common cause of failures. |
| S3 | [Stripe webhook signatures fail behind Caddy (DEV)](https://dev.to/aurelea_hammonds_18e3beea/stripe-webhook-signatures-fail-behind-caddy-1l2j) | First-hand post | Verification works locally and fails in production behind a reverse proxy. |
| S4 | [Next.js App Router Stripe signature verification (Medium)](https://kitson-broadhurst.medium.com/next-js-app-router-stripe-webhook-signature-verification-ea9d59f3593f) | Practitioner guide | The framework's body handling breaks verification, and the fix differs between frameworks and router versions. |
| S5 | [Stripe CLI: `listen`](https://docs.stripe.com/cli/listen) | Provider docs | Forwarding sandbox events to localhost from a CLI that only works for Stripe. |
| S6 | [Stripe CLI: fixtures](https://docs.stripe.com/cli/fixtures) | Provider docs | Building custom event sequences takes JSON fixture files describing API calls. |
| S7 | [Stripe webhook silently failing for 5 days (DEV)](https://dev.to/edhiblemeer/stripe-webhook-was-silently-failing-for-5-days-the-4xx-retry-trap-and-the-beginning-of-month-time-5d2o) | First-hand incident | A handler returned 400 on a data miss and 21 `invoice.paid` events failed over 5 days. Daily error alerts missed it. Stripe's warning email was how it was found. |
| S8 | [Ghost forum: Stripe webhook delivery issues](https://forum.ghost.org/t/stripe-webhook-delivery-issues/11993) | Forum | Users learn about failing endpoints from provider warning emails. |
| S9 | [Shopify: Troubleshoot webhooks](https://shopify.dev/docs/apps/build/webhooks/troubleshoot) | Provider docs | 5-second response window. 8 retries over 4 hours, then an Admin-API subscription is deleted. Data for the gap may need recovering. |
| S10 | [Shopify: Ignore duplicate webhooks](https://shopify.dev/docs/apps/build/webhooks/ignore-duplicates) | Provider docs | Duplicates happen. Each subscription gets its own delivery ID while sharing an event ID. |
| S11 | [How Shopify silently kills your webhook subscriptions (DEV)](https://dev.to/shophooks/how-shopify-silently-kills-your-webhook-subscriptions-and-how-to-prevent-it-1d3f) | Vendor post with an incident | Orders went missing for over 30 hours before anyone noticed. Uptime checks that send GET requests miss failing POST handlers. |
| S12 | [A field disappeared from Shopify webhooks for two days (DEV)](https://dev.to/balboubal/a-field-disappeared-from-shopify-webhooks-for-two-days-and-every-monitor-stayed-green-5b86) | First-hand incident | `id` vanished from payloads pinned to a version. Handlers returned 200 and monitoring stayed green. |
| S13 | [Shopify Next Generation Events: community take (Hookdeck)](https://hookdeck.com/blog/shopify-next-generation-events-community-take) | Interviews | One app team discards most webhooks and spends about half its AWS bill handling them. Developers still need reconciliation polling. |
| S14 | [Shopify dev community: webhook URLs not updating on restart](https://community.shopify.dev/t/webhooks-urls-are-not-updating-with-server-restarts/1977) | Forum | Tunnel URLs change on every restart and events go to dead URLs. |
| S15 | [GitHub: Handling failed webhook deliveries](https://docs.github.com/webhooks/using-webhooks/handling-failed-webhook-deliveries) | Provider docs | GitHub does not retry failed deliveries automatically. |
| S16 | [GitHub: Redelivering webhooks](https://docs.github.com/en/webhooks/testing-and-troubleshooting-webhooks/redelivering-webhooks) | Provider docs | Redelivery is only possible for the past 3 days. |
| S17 | [GitHub: Forward webhooks with the GitHub CLI](https://docs.github.com/en/webhooks/testing-and-troubleshooting-webhooks/using-the-github-cli-to-forward-webhooks-for-testing) | Provider docs | Forwarding is for testing only, and is a GitHub-only CLI. |
| S18 | [GitHub silently removed `payload.commits` from PushEvent (DEV)](https://dev.to/flarecanary/github-silently-removed-payloadcommits-from-pushevent-heres-what-broke-and-how-to-catch-the-2i33) | Incident analysis | Fields were removed without a version bump. Responses stayed 200, frozen fixtures and types passed, and pipelines produced wrong results. |
| S19 | [Twilio: Request validation 403s behind a proxy](https://support.twilio.com/hc/en-us/articles/50535649249307-Fixing-Twilio-Request-Validation-403-Errors-Behind-a-Proxy) | Provider support | Signatures are computed over the public URL, so a TLS-terminating proxy breaks validation. |
| S20 | [Twilio: Webhooks security](https://www.twilio.com/docs/usage/webhooks/webhooks-security) | Provider docs | Validation needs the exact URL, including encoding. Twilio advises using its SDK rather than writing your own. |
| S21 | [Stack Overflow (mirror): Slack Events API triggers multiple times](https://kiwix.lema.org/stackoverflow.com_en_all_2023-11/questions/50715387/slack-events-api-triggers-multiple-times-by-one-message) | Q&A | Slow handlers cause Slack to retry, which produces duplicate actions. |
| S22 | [Pipedream community: responding within Slack's 3-second limit](https://pipedream.com/community/t/how-do-i-issue-an-http-response-within-slacks-timeout-limit-of-3-seconds/322) | Forum | Developers struggle with provider timeout windows. |
| S23 | [ngrok: Get webhooks secure, it depends](https://ngrok.com/blog/get-webhooks-secure-it-depends-a-field-guide-to-webhook-security) | Study of 100 providers | Only 2 services verified signatures the same way. About 30% had replay protection. About 10% of docs omitted details needed to verify. 16% used no authentication. |
| S24 | [ngrok: Webhook integrations docs](https://ngrok.com/docs/integrations/webhooks/) | Vendor docs | Traffic Inspector can replay with modifications. Webhook verification is capped on the free tier. |
| S25 | [Svix: State of Webhooks](https://www.svix.com/state-of-webhooks/) | Industry survey | Among the original 100 providers: 77.5% retry automatically, 59% use HMAC-SHA256, 40% have replay protection, 41.2% offer delivery logs, 21.2% offer manual retries, and 8% use Standard Webhooks headers. |
| S26 | [Svix: Webhooks are harder than they seem](https://www.svix.com/blog/webhooks-are-harder-than-they-seem/) | Vendor essay | Producer-side problems: retries, SSRF, slow consumers, noisy neighbours, observability. GitHub's lack of retries means some CI runs never happen. |
| S27 | [Standard Webhooks specification](https://github.com/standard-webhooks/standard-webhooks) | Open specification | A common signing scheme over id, timestamp and body, with secret rotation. |
| S28 | [OpenAI: Webhooks guide](https://developers.openai.com/api/docs/guides/webhooks) | Provider docs | AI platforms send webhooks for background and batch work, following Standard Webhooks, with thin payloads that the receiver fetches. |
| S29 | [Hookdeck: Webhook ordering, why it's hard](https://hookdeck.com/webhooks/guides/webhook-ordering-why-its-hard-and-how-to-handle-it) | Vendor guide | Providers trade ordering for throughput. A 200 does not mean the event was processed in order. Consumers must converge on the right state. |
| S30 | [HN: Give me /events, not webhooks](https://news.ycombinator.com/item?id=27823109) | Discussion | Bad customer deploys are inevitable, so senders need bulk replay. Consumers carry the reliability tooling burden. Receivers behind firewalls cannot accept webhooks. |
| S31 | [Sequin: Give me /events, not webhooks (mirror)](https://freek.dev/2034-give-me-events-not-webhooks) | Essay | Webhooks are ephemeral and easy to lose, and polling is used for reconciliation. |
| S32 | [Hookdeck CLI v2: agents debug webhooks via MCP](https://hookdeck.com/blog/hookdeck-cli-v2) | Vendor launch | AI coding agents can query, inspect and replay webhooks. |
| S33 | [Hookdeck Console docs](https://hookdeck.com/docs/console) | Vendor docs | Capture URLs without an account, remembered by the browser. |
| S34 | [Webhook.site FAQ](https://docs.webhook.site/faq.html) | Vendor docs | Free URLs expire after 7 days and have a request cap. Data is reachable by anyone who knows the URL ID. |
| S35 | [Svix: Webhook DX to eliminate support tickets](https://www.svix.com/resources/faq/webhook-developer-experience-increase-adoption-eliminate-tickets/) | Vendor FAQ | The most common support tickets: "my endpoint stopped receiving", "resend last Thursday's", "change my URL". |
| S36 | [HighLevel changelog: webhook logs dashboard](https://highlevel.canny.io/changelog/webhook-logs-dashboard-in-developer-portal) | Producer changelog | Developers had to open tickets just to confirm a delivery. |
| S37 | [Hookdeck: Common outbound webhook mistakes](https://hookdeck.com/outpost/guides/common-outbound-webhook-mistakes) | Vendor guide | Producer mistakes: missing stable event IDs, missing delivery IDs, no versioning. |
| S38 | [Convoy: Webhook security for API providers](https://www.getconvoy.io/blog/webhook-security-for-api-providers) | Vendor guide | Producers need SSRF filtering, signing and secret rotation. |
| S39 | [HookChaos: webhook stress tester CLI (DEV)](https://dev.to/umutkeskin1001/hookchaos-webhook-stress-tester-cliopen-sourced-project-21ne) | OSS launch, 2026 | Early-stage CLI for duplicates, out-of-order, retries, bad signatures and clock skew. Its pass/fail criteria are not documented. |
| S40 | [Carbon Layer v0.6: webhook resilience testing (DEV)](https://dev.to/pritom14/carbon-layer-v06-webhook-resilience-testing-for-payment-handlers-idempotency-out-of-order-307d) | OSS launch, 2026 | Payment-only chaos CLI. CI fails only on 5xx or timeout, with no side-effect checks. |
| S41 | [Automating webhook tests in CI/CD (HelpMeTest)](https://helpmetest.com/blog/webhook-testing-ci-cd/) | Practitioner guide | CI needs deterministic signed mocks. Hand-written fixtures drift from what providers actually send. |
| S42 | [Show HN: WebhookHub, inspect and replay webhooks](https://news.ycombinator.com/item?id=45013754) | Launch | One of many 2025–2026 inspect-and-replay launches. Also listed: webhook.rodeo, Webhook Mirror, HookCap, HookLens. |
| S43 | [A local RequestBin in PHP: why self-hosting beats webhook.site (DEV)](https://dev.to/sendotltd/a-local-requestbin-in-200-lines-of-php-why-self-hosting-beats-webhooksite-for-production-5h31) | First-hand post | Teams don't want production payloads leaving their infrastructure. |
| S44 | [OWASP Webhook Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Webhook_Security_Cheat_Sheet.html) | Standard guidance | Pair signature, timestamp and event-ID deduplication. Verify the raw body in constant time. |
| S45 | [Pinggy: Provider CLIs vs tunnels vs replay tools](https://pinggy.io/blog/webhook_testing_provider_clis_vs_tunnels/) | Vendor guide | Local development is split across provider CLIs, tunnels and replay tools. |

## 2. Findings: consumers (receiving)

### 2.1 Signature verification fails for reasons the developer can't see
- Stripe's verification needs the exact raw body. Framework parsers that run before the handler break it (S2, S4). Reverse proxies break it in production only (S3).
- Twilio signs the **public URL** as well as the body, so TLS termination and URL re-encoding cause 403s that never show up locally (S19, S20).
- Schemes vary a lot. In a study of 100 providers, only 2 services verified signatures the same way, and about 10% of docs left out details needed to verify (S23). Adoption of the common standard is growing but still small: 8% of providers use Standard Webhooks headers (S25, S27).
- **Pattern:** the error message is always "signature mismatch". Nothing tells the developer *which* byte, URL or secret differs.

### 2.2 Delivery semantics break handlers that "work"
- At-least-once delivery means duplicates are normal (Stripe S1, Shopify S10, Slack S21). Stripe says it doesn't guarantee order and warns against using `created` for ordering (S1). Providers choose throughput over ordering on purpose (S29).
- Timeout windows are short and differ by provider: Shopify 5 s (S9), Slack about 3 s (S22). A slow handler causes retries, and retries cause duplicate side effects (S21).
- The "4xx trap": a 400 on a lookup miss looks deliberate, but the provider treats it as a failure and keeps retrying (S7).
- **Pattern:** these bugs need a *sequence* (twice, reordered, after a timeout) to show up. Normal manual testing sends each event once, in order, quickly.

### 2.3 Production failures are silent and are found by customers
- Shopify deletes Admin-API subscriptions after 8 consecutive failures over about 4 hours (S9). One team lost orders for more than 30 hours before noticing (S11).
- Stripe retries for up to 3 days and then sends warning emails. That email is often how teams find out (S1, S7, S8).
- GitHub doesn't retry, and redelivery only reaches back 3 days (S15, S16), so anything noticed later is lost.
- Error-count alerts miss failures tied to an event's schedule. Monthly `invoice.paid` failures were invisible to daily baselines (S7).
- **Pattern:** "delivered and returned 200" is not the same as "handled correctly", and "no errors" is not the same as "no events". Generic uptime monitoring checks neither (S11).

### 2.4 Payload contracts drift without a version change
- A field disappeared from version-pinned Shopify payloads for two days, and every monitor stayed green (S12).
- GitHub removed `payload.commits` without a version bump. Frozen fixtures and types kept passing while pipelines silently produced wrong results (S18).
- **Pattern:** the only defence is checking payload *shape* against a baseline taken from real traffic, and almost nobody does it.

### 2.5 Reproducing a real event is time-boxed and lossy
- Redelivery windows are short: GitHub 3 days (S16). Stripe sandboxes retry only 3 times over a few hours (S1).
- Building realistic sequences means writing fixture files by hand for each provider (S6). Hand-written fixtures drift from real payloads (S41).
- Captured payloads are the most faithful fixtures, but they hold production data (S41, S43).

### 2.6 The local loop is solved, but in pieces
- Per-provider CLIs (S5, S17), tunnels whose URLs change on restart (S14), relays that drop events when the client is offline (S45), and capture sites with expiring URLs and public-by-ID data (S34).
- New inspect-and-replay tools keep launching (S42). Hookdeck offers capture without an account and a forwarding CLI (S33). ngrok replays with modifications (S24).
- **Pattern:** seeing and replaying a payload is a commodity. Nobody wins on it alone.

### 2.7 Privacy pushes captures in-house
- Public capture sites expose data to anyone holding the URL ID (S34). Practitioners build self-hosted bins so production payloads never leave (S43).

### 2.8 AI agents are now writing and debugging handlers
- Stripe's webhook docs now start by pointing to agent skills (S1). Hookdeck ships an MCP server so coding agents can inspect and replay events (S32).
- AI platforms are themselves webhook producers. OpenAI sends Standard Webhooks with thin payloads that must be fetched (S28).
- **Implication:** handler code is cheaper to write and less reviewed. Checking it against real delivery semantics becomes worth more.

## 3. Findings: producers (sending)

- Hard parts: retries with backoff, SSRF via customer-chosen URLs, slow consumers backing up queues, noisy neighbours, signing and rotation, observability (S26, S38).
- Common design mistakes: no stable event ID, no per-attempt delivery ID, no versioning (S37). Customers inevitably ship bad deploys, so senders need bulk replay (S30).
- Support load: "did you send it?" tickets are frequent enough that producers build delivery-log dashboards (S35, S36). Only 41.2% of providers offer delivery logs and 21.2% offer manual retry (S25).
- This side is well served by funded infrastructure vendors (Svix, Hookdeck Outpost, Convoy). What's missing is a way for producers to **test their own sender** against a misbehaving receiver before shipping.

## 4. Market observations

| Job | Who serves it today | Gap |
| --- | ------------------- | --- |
| See what arrived | webhook.site, Hookdeck Console, ngrok inspector, many 2025–26 launches (S33, S34, S24, S42) | Crowded. Little to differentiate. |
| Get events to localhost | Stripe CLI, `gh webhook`, Hookdeck CLI, ngrok, smee (S5, S17, S45) | Fragmented by provider, but solved. |
| Prove a handler survives real delivery semantics | Early OSS CLIs: HookChaos, Carbon Layer (S39, S40) | **Nascent.** Synthetic payloads, pass/fail on HTTP status only, no side-effect checks, no path from real captures to CI. |
| Know production broke | Provider emails (S7, S8), niche monitors (S11, S12), Hookdeck Event Gateway (inline) | Consumers lack a cross-provider view of silence, error rate per event type and shape drift. |
| Send webhooks reliably | Svix, Hookdeck Outpost, Convoy (S26, S37, S38) | Well served on infrastructure. Weak on testing a sender. |

## 5. Patterns (synthesis)

1. **Most webhook bugs are bugs in sequences, not in payloads.** Duplicates, reordering, retries after timeouts and late arrivals all need several deliveries to reproduce (2.2).
2. **Status codes are false comfort.** A 200 can hide wrong state (S12, S18), a 4xx can trigger retry storms (S7), and accepting a tampered payload is invisible (S23).
3. **Real traffic is the only trustworthy fixture**, but it is perishable (redelivery windows) and sensitive (privacy) (2.5, 2.7).
4. **Production discovery is late and external**: emails, customers, missing orders (2.3).
5. **The commodity layer (capture, inspect, replay, tunnel) is saturated.** The value is in judging correctness, not in showing bytes.
6. **Code is getting cheaper** (2.8). Verification is the scarce step.

## 6. Unverified or conflicting

- **Shopify retry policy:** official docs say 8 retries over 4 hours (S9). A vendor post says 19 attempts over about 48 hours (S11). The official figure is used here.
- **Stripe auto-disable timing:** forums and blogs describe warning emails followed by disabling, but I found no official schedule. Only the 3-day retry window is confirmed (S1).
- **Webhook.site free request cap:** official docs and third-party sites disagree (100 vs 50). The 7-day expiry is consistent (S34).
- **Svix survey edition:** the page labels the figures with a later year than expected, so its edition naming could not be confirmed. The numbers are reported as shown (S25).
- **`gh webhook` maturity:** third-party sources disagree on whether it is beta or GA. The docs say testing only (S17).
- **Hookdeck free-tier retention:** sources disagree. Not used.
- **Reddit:** no threads surfaced. No claim relies on Reddit.
- **Market size and willingness to pay:** I found no reliable data. Figures like "X% of integration failures come from webhooks" from secondary blogs were left out.
