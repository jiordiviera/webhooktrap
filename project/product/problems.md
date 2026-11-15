# Ranked Problems

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-11
Evidence IDs (S#) refer to [research.md § Sources](research.md#1-sources).

## Scoring

Each problem is scored 1–5 on three axes. **Score = Frequency × Pain × Poorly served** (maximum 125).

- **Frequency:** how many developers hit it, and how often.
- **Pain:** cost when it happens (time, money, data, trust).
- **Poorly served:** 5 means nothing good exists, 1 means it's a commodity.

These scores are a strategist's judgment based on the evidence, not measurements. Interviews should test them (see [open-questions.md](open-questions.md)).

| Rank | Problem | F | P | U | Score | Side |
| ---- | ------- | - | - | - | ----- | ---- |
| 1 | Handlers that pass manual tests break under real delivery semantics | 4 | 5 | 4 | **80** | Consumer |
| 2 | Production webhook failures are silent and found by customers | 3 | 5 | 4 | **60** | Consumer |
| 3 | Signature failures can't be diagnosed | 5 | 3 | 3 | **45** | Consumer |
| 4 | Payload contracts drift without notice | 2 | 4 | 5 | **40** | Consumer |
| 5 | A real event can't be reproduced after the fact | 4 | 3 | 3 | **36** | Consumer |
| 6 | CI tests rely on fake or unsigned fixtures | 3 | 3 | 4 | **36** | Consumer / QA |
| 7 | Captured payloads leak to third parties | 2 | 3 | 3 | **18** | Consumer |
| 8 | Delivery context can't be handed off within a team | 3 | 2 | 3 | **18** | Consumer |
| 9 | Producers can't test their own sender or show customers what happened | 2 | 4 | 2 | **16** | Producer |
| 10 | Getting events to localhost | 5 | 2 | 1 | **10** | Consumer |

## 1. Handlers that pass manual tests break under real delivery semantics (80)

**What happens.** The developer triggers an event once, sees a 200, and ships. In production the provider delivers the same event twice, out of order relative to related events, after a timeout, or as a retry following a 4xx. The handler double-charges, creates duplicate records, lets state go backwards, or sets off a retry storm.

**Evidence.**
- Providers document at-least-once delivery and no ordering: Stripe (S1), Shopify (S10), Slack (S21). Ordering is traded away on purpose (S29).
- Timeout windows are tight and differ by provider: Shopify 5 s (S9), Slack about 3 s (S22). Slow handlers turn into duplicates (S21).
- The "4xx trap": returning 400 on a lookup miss causes repeated retries (S7).
- Early OSS tools built only for this appeared in 2026 (S39, S40). That shows demand. They judge results by HTTP status only, which is the gap.

**Why poorly served.** Capture-and-replay tools send one event at a time. Provider CLIs trigger the happy path. Nobody connects *real captured* events to *adversarial sequences* to a *verdict about state*.

## 2. Production webhook failures are silent and found by customers (60)

**What happens.** An endpoint keeps failing, or a subscription is deleted, or events simply stop arriving. Nothing pages anyone. The team learns from a provider warning email, a missing order, or a customer ticket, sometimes days later.

**Evidence.**
- Shopify deletes Admin-API subscriptions after 8 consecutive failures over about 4 hours (S9). Orders were missing for more than 30 hours in one case (S11).
- Stripe: 5 days of silent `invoice.paid` failures, found through the warning email (S7, S8).
- GitHub doesn't retry, and redelivery only reaches back 3 days (S15, S16).
- Uptime checks don't hit the POST path. Daily error baselines miss failures tied to an event's schedule (S7, S11).

**Why poorly served.** Provider dashboards each show one provider. Inline gateways (Hookdeck Event Gateway) solve it only if all production traffic goes through them. Consumers have no cross-provider view of "expected but missing".

## 3. Signature failures can't be diagnosed (45)

**What happens.** Every new integration hits "signature mismatch" at least once. The cause could be a body parser, a proxy, the URL scheme, an encoding change, a test secret used against live, a CLI secret used against a dashboard endpoint, or clock skew. The error never says which.

**Evidence.** Raw-body problems (S2, S4); proxy-only failures (S3, S19); exact-URL rules (S20); only 2 of 100 services verified signatures the same way, and docs often missing details (S23); OWASP recommends combining signature, timestamp and ID checks (S44).

**Why poorly served.** Every provider documents its own scheme. No tool compares the bytes the provider signed with the bytes the handler saw and names the difference.

## 4. Payload contracts drift without notice (40)

**What happens.** A field disappears or changes type, even under a pinned version. Handlers read `undefined`, return 200, and corrupt data downstream.

**Evidence.** A Shopify `id` vanished for two days (S12). GitHub `payload.commits` was removed without a version bump (S18). In both cases tests built on frozen fixtures kept passing.

**Why poorly served.** Delivery monitoring checks arrival and status, not shape (S12). Only niche monitors exist.

## 5. A real event can't be reproduced after the fact (36)

**What happens.** A bug report comes in about "the webhook from Tuesday". The provider only lets you redeliver for 3 days (GitHub S16). Test mode behaves differently from live (Stripe sandbox retries S1). The exact bytes and headers are gone.

**Evidence.** S1, S6, S16, S41.

**Why poorly served.** Capture tools store payloads but keep them only briefly (S34) and aren't connected to how the handler behaved.

## 6. CI tests rely on fake or unsigned fixtures (36)

**What happens.** Teams write fixtures by hand, skip signature checks in tests, or mock the provider SDK. Tests drift from reality, and provider CLIs aren't available in CI.

**Evidence.** S41 (deterministic signed mocks needed; hand-written fixtures drift), S6 (custom sequences need hand-written fixture files), S18 (frozen fixtures miss removed fields).

## 7. Captured payloads leak to third parties (18)

Payloads contain personal and payment data. Public capture sites expose data to anyone who has the URL ID (S34). Teams build self-hosted bins to avoid this (S43). Moderate pain, regulated teams only, partly served by self-hostable inspectors.

## 8. Delivery context can't be handed off within a team (18)

"Look at this delivery" means screenshots or pasted JSON. HN commenters want clear event logs and individual or bulk resend (S30). This is real, but it supports problems 2 and 5 rather than standing alone.

## 9. Producers can't test their own sender or show customers what happened (16)

Producers must handle retries, SSRF, signing, versioning and logs (S26, S37, S38). Customer tickets cluster around "did you send it?" (S35, S36). The infrastructure is well served (Svix, Hookdeck Outpost, Convoy). **Testing a sender against a misbehaving receiver** is not, but the market is small.

## 10. Getting events to localhost (10)

Very frequent, but well served: provider CLIs (S5, S17), tunnels, relays, consoles (S33, S45). The remaining friction is URLs changing on restart (S14). This must be good in any concept but **cannot be the differentiator**.

## Takeaways

- The top three problems share a root: **developers lack an independent, provider-faithful view of what was delivered versus what their code did with it.**
- Problem 1 has the best mix of pain, frequency and unmet need, and it can be tackled *before* production, where a small open-source team can win without running a production SLA.
- Problems 2 and 4 are the biggest willingness-to-pay opportunity later, but they need either an inline production path or provider API integrations (see [concepts.md](concepts.md)).
