# Product Concepts

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-11
Problems → [problems.md](problems.md) · Personas → [personas.md](personas.md) · Evidence S# → [research.md](research.md#1-sources)

Four concepts, each with a different **job**, **primary user** and **core loop**. Working names are placeholders for the brand work. ADR references are to `project/architecture/decisions/`.

---

## Concept A: Workbench, the webhook debugger that tells you *why*

**Pitch.** A stable URL and a local agent that show every delivery live, explain exactly why a signature fails, and let you edit, re-sign and resend to localhost.

- **Primary user and context:** the Integrator, on a laptop, in the first hours of an integration.
- **Problems attacked:** #3 signature diagnosis (primary), #10 localhost, #5 reproduce.
- **Core loop:** paste URL into provider → event arrives live → read the signature diagnosis → fix the handler → resend (edited and re-signed if needed) → read your app's response.
- **Key moments:**
  - The first event appears without signing up.
  - The *diagnosis*: "Your app verified against a parsed body. 14 bytes differ (key order, whitespace). Use the raw body." Or: "Twilio signed `https://…`, your app rebuilt `http://…`."
  - Edit a field, re-sign locally with your secret, and resend.
- **Deliberately NOT:** a monitor, a router, a no-code tool, or a test runner.
- **Differentiation:**
  - webhook.site and Hookdeck Console capture, but don't explain signature failures (S33, S34).
  - ngrok replays with modifications, but the edited body no longer matches its signature (S24).
  - Stripe CLI and `gh webhook` each cover one provider (S5, S17).
  - The edge is **diagnosis plus provider-faithful re-signing**.
- **Business / open-source fit:** easy to adopt and free to start, but the category is crowded (S42) and hard to monetise ($7.5/month reference prices). Good as a funnel, weak as a business.
- **Risks:**
  - It reads as "yet another webhook.site" unless the diagnosis is exceptional.
  - It needs a maintained per-provider catalogue of signature schemes (Standard Webhooks covers a growing share, S25, S27).
- **Technical implications:**
  - Fits ADR-003/004/005 best.
  - Needs a **local agent at MVP** for localhost, which challenges ADR-006, where the agent is a 2.1 item.
  - Re-signing needs the signing secret. It should never be uploaded to the hosted service, so signing happens in the agent.
  - Signature diagnosis needs the exact request target (path plus raw query) and, for URL-signing providers, the public URL as the provider saw it. Raw-body capture (ADR-005) is essential.

---

## Concept B: Proving Ground, the test harness for webhook handlers ★ recommended

**Pitch.** Capture real events once, then run them against your handler the way providers actually deliver them: twice, shuffled, late, after a timeout, tampered. Get a verdict on *state*, not just on status codes, locally and in CI.

- **Primary user and context:** the Integrator and the QA/CI owner, before production. The Agent-assisted developer through the CLI and MCP.
- **Problems attacked:** #1 delivery semantics (primary), #6 CI fixtures, #3 signature (rejecting tampered or unsigned input), #5 reproduce, #4 drift (shape baseline from fixtures).
- **Core loop:**
  1. Capture real events (hosted or self-hosted URL, or forwarded by the agent).
  2. Pick an event or a sequence.
  3. Choose scenarios.
  4. The agent runs them against localhost with provider-accurate signatures and timing.
  5. Read the verdict.
  6. Fix.
  7. Save as fixtures in the repo.
  8. `proving test` runs the same scenarios in CI.
- **Key moments:**
  - **Aha:** "Deliver twice" turns a green handler red: *"Second delivery created a second subscription. Your handler isn't idempotent."*
  - **Budget line:** response time is drawn against the provider's limit (Shopify 5 s, Slack 3 s) (S9, S22).
  - **Security finding:** *"Your handler accepted a tampered body with status 200."*
  - **The 4xx trap explained:** *"You returned 400 on a missing record. Stripe will retry this for 3 days."* (S1, S7)
  - **Fixtures become CI:** one command writes signed, scrubbed fixtures and a scenario file into the repo.
- **How the verdict is judged (three levels, opt-in):**
  1. **Transport (no setup):** status codes, latency against the provider's budget, consistency across duplicates, acceptance of bad signatures or stale timestamps, 4xx-on-retry.
  2. **State probe (one line of config):** a URL or command that returns a digest of relevant state. The digest is compared after 1× vs 2× vs reordered deliveries.
  3. **Test assertions (CI):** your own test command runs after each scenario.
- **Deliberately NOT:**
  - a production gateway or monitor (not in the live path);
  - a load tester;
  - a mock of the provider's API (only its webhooks);
  - a payload editor first.
- **Differentiation:**
  - HookChaos and Carbon Layer (S39, S40) use synthetic payloads, judge only 5xx and timeouts, and cover few providers.
  - Stripe CLI triggers happy paths for Stripe only (S5, S6).
  - Hookdeck, ngrok and webhook.site replay one event at a time (S24, S33, S34).
  - The edge is **real captured traffic → adversarial sequences → state-aware verdict → CI**, across providers, in the open.
- **Business / open-source fit:**
  - Open-source core: agent, scenario engine, provider packs.
  - Hosted service: capture URLs, run history, team sharing, CI result history, private scenario packs.
  - Community packs ("Stripe subscription lifecycle", "Shopify order flow") are the distribution moat.
  - Strong self-host story: CI can run fully offline.
- **Risks:**
  - The state probe is setup cost. If level 1 alone doesn't produce findings that *feel* important, adoption stalls.
  - Scenarios need a reset between runs: a reset hook, or test databases in CI.
  - "Testing" sells less urgently than "debugging". It must be marketed through the pain ("your handler works. Once.").
  - The OSS competitors are early but exist.
- **Technical implications:**
  - The **local agent is the product's hands at MVP**, which challenges ADR-006's timeline.
  - The **scenario engine must live in the agent/CLI**: for millisecond timing control (two deliveries 50 ms apart), for localhost, and for offline CI. That challenges ADR-006's model of server-side queued replay jobs and ADR-002's single-runtime assumption.
  - Secrets stay local.
  - Run timelines stream many fine-grained updates. ADR-004 live props ("latest N", one reload per viewer per event) may need direct Echo events for runs.
  - Retention needs drop, because fixtures live in the user's repo, not in our database (good for ADR-003 guest retention).
  - New domains: Scenario, Run/Verdict, Agent, Fixture (ADR-001).

---

## Concept C: Flight Recorder, know when production webhooks break

**Pitch.** One place that watches every provider's webhooks into your production app. It alerts on silence, per-event-type failure, deleted subscriptions and payload shape drift, then helps you recover what you missed.

- **Primary user and context:** the Owner / on-call, in production.
- **Problems attacked:** #2 silent failure (primary), #4 drift, #5 reproduce, #8 team handoff.
- **Core loop:** connect sources → baseline (cadence per event type, payload shape) → alert → diagnose (delivery plus your response) → recover (replay the missed window).
- **Two ways in:**
  - **C1 Inline relay:** the provider sends to us and we forward to the app. Full fidelity, but we're in the critical path.
  - **C2 Sidecar watch:** read-only provider API access (Stripe events, GitHub deliveries API, Shopify subscription list) plus an optional mirror. We're never in the critical path, but each provider is a separate integration.
- **Key moments:**
  - *"No `invoice.paid` in 26 h; normally 140 on the 1st."* (S7)
  - *"Shopify subscription `orders/create` was removed 2 h ago."* (S9)
  - *"`id` missing in 12% of `orders/updated` since 14:02."* (S12)
- **Deliberately NOT:** a general APM, an event router with transformations, or a sender platform.
- **Differentiation:**
  - Hookdeck Event Gateway covers inline routing but needs all traffic through it.
  - ShopHooks and FlareCanary are single-purpose (S11, S18).
  - Provider emails are late and come one provider at a time (S8).
  - The edge is **cross-provider "expected but missing" plus shape drift**, self-hostable.
- **Business / open-source fit:** the highest willingness to pay (money is at stake), but it needs trust, uptime commitments and sales to teams. Self-hosting suits regulated teams. It is the hardest for a small team to run credibly as SaaS.
- **Risks:**
  - C1 means running a production SLA on PHP-FPM ingest.
  - C2 means maintaining provider integrations and OAuth apps.
  - Alert fatigue.
  - Head-on competition with funded infrastructure.
- **Technical implications:**
  - Long retention, multi-tenant teams and roles (ADR-003 defers collaborators), notification channels, scheduled baseline jobs.
  - C1 needs high-availability ingest and forwarding with retries and an outbox (ADR-007 "later ADR").
  - Redis becomes mandatory, and the minimal self-host profile (ADR-008 amendment) becomes harder to keep.

---

## Concept D: Sender Lab, a conformance and chaos receiver for webhook producers

**Pitch.** Point your outbound webhooks at a receiver that misbehaves on purpose (slow, 5xx, redirects, TLS errors, DNS rebinding bait), then get a conformance report: retries, backoff, signatures (Standard Webhooks), IDs, timeouts and SSRF refusal.

- **Primary user and context:** the Platform engineer, before a release of their webhook feature.
- **Problems attacked:** #9 (primary).
- **Core loop:** pick a receiver profile → trigger events from your product → the receiver misbehaves on schedule → report card → fix → rerun in CI.
- **Key moments:**
  - *"You retried 3 times in 4 s. No backoff."*
  - *"Your delivery followed a redirect to 169.254.169.254."*
  - *"Your `webhook-id` changed between retries."* (S37)
  - *"Signature valid per Standard Webhooks v1."* (S27)
- **Deliberately NOT:** a sending platform (Svix, Outpost and Convoy do that), and not for consumers.
- **Differentiation:** sending platforms are infrastructure. Nobody offers a **neutral test receiver and grader**. It could double as the reference conformance tool for Standard Webhooks.
- **Business / open-source fit:** a niche B2B audience, strong developer-marketing value as a free public grader, and modest direct revenue.
- **Risks:** a small market. Hosting deliberately malicious receivers (DNS rebinding) needs careful abuse and security review.
- **Technical implications:**
  - Holding connections open for 30 s+ to simulate slowness exhausts PHP-FPM workers. It needs a non-blocking runtime (Go, Node, or Octane/FrankenPHP), which challenges ADR-002.
  - It also needs custom DNS for rebinding tests.
  - Fewer anonymous users, so ADR-003 matters less.

---

## Variants considered and rejected

- **Event router / transformation gateway:** Hookdeck's core business, and the persona is out of focus.
- **No-code webhook automations:** a different job (personas.md).
- **"/events pull adapter"** (turn webhooks into a cursor API, S30, S31): intellectually appealing, but niche demand. Could be a later Flight Recorder feature.

---

## Comparison

| | A Workbench | **B Proving Ground** ★ | C Flight Recorder | D Sender Lab |
| - | ----------- | ---------------------- | ----------------- | ------------ |
| Job | See why a delivery fails | Prove a handler survives real delivery | Know production broke and recover | Prove a sender is correct |
| Primary persona | Integrator | Integrator, QA/CI, Agent-assisted | Owner / on-call | Platform engineer |
| Lifecycle stage | Build | Build → CI | Run | Build (producer) |
| Top problems | #3, #10 | #1, #6, #3 | #2, #4 | #9 |
| Problem score covered | 45 + 10 | 80 + 36 + 45 | 60 + 40 | 16 |
| Market crowding | Very high (S42) | Low, early OSS only (S39, S40) | Medium (Hookdeck inline, niche monitors) | Low |
| Differentiation strength | Medium (diagnosis) | **High** | High, but costly | Medium |
| Time to first value | < 1 min | < 2 min (capture), < 5 min (first verdict) | Hours to days (baseline) | ~15 min |
| Monetisation | Weak | Medium (teams, CI, history) | **Strong** | Weak to medium |
| Open-source / self-host fit | Good | **Excellent** (offline CI) | Good (regulated teams) | Good |
| Operational burden for us | Low | Low (not in the production path) | **High** (SLA or integrations) | Medium (abuse surface) |
| Needs local agent / CLI | Yes | **Yes, core** | No (C2) / No (C1) | No |
| Non-PHP runtime needed | Agent only | Agent/engine | No | Yes (receiver) |
| Long retention | Low | Low (fixtures in repo) | High | Low |
| Teams / multi-tenant | Nice | Nice (shared runs) | Required | Nice |
| Outbound delivery infrastructure | No | No (agent sends locally) | C1 yes | No |
| ADR tension | 006 timing | 006, 002, 004 | 003, 007, 008 | 002 |

**Reading the table:**
- **B** covers the highest-scoring problem cluster with the least operational risk and the clearest gap.
- **A** is the necessary first minute of B, not a product by itself.
- **C** is the natural second act, once B has built trust and a user base.
- **D** is an optional marketing asset that reuses B's scenario engine in reverse.

See [vision.md](vision.md).
