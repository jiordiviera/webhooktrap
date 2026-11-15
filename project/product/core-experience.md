# Core Experience

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-11
Concepts → [concepts.md](concepts.md). Product names and CLI command names are placeholders (`trap …`) pending brand work. This document describes *experience*, not visual style. The art director owns the look.

---

## Part 1: Narratives per concept

Each narrative follows the same four beats: **first minute**, **aha**, **daily use**, **failure and edge cases**.

### A. Workbench (the debugger that explains)

- **First minute:**
  - The visitor lands and already has a URL and a "waiting for your first webhook" state, with copyable provider instructions.
  - They paste it into Stripe test mode. The event lands live, shown as provider, type and a verdict chip.
- **Aha:**
  - They paste their endpoint's signing secret into the local agent (never the browser).
  - The event shows *"Signature valid"*.
  - Their own app logs "mismatch", so they run `trap why`. The answer comes back: *"Your app verified 1,204 bytes. Stripe signed 1,198. Your framework re-serialised the JSON."*
- **Daily use:** keep the URL running during development, scrub through deliveries, edit a field, re-sign, resend to localhost, compare responses side by side.
- **Failure and edges:**
  - **Agent offline:** resend is queued with a clear "your machine isn't connected" state.
  - **Unknown provider:** "Unsigned or unrecognised scheme". Offer to define a scheme.
  - **Large binary body:** shown as hex with a download.

### C. Flight Recorder (production watch)

- **First minute:** connect a provider read-only (sidecar), or add a relay URL in front of one endpoint. See the last 24 h of deliveries and your app's responses.
- **Aha (often days later):** an alert lands in Slack or email. *"No `invoice.paid` since 00:00. Expected about 140 on the 1st. Your endpoint returned 400 to 21 deliveries."* One click opens the failed window, and another replays it once the fix is deployed.
- **Daily use:** a quiet health board per provider and per event type (rate, error share, latency against the provider's budget, shape stability). Weekly digest.
- **Failure and edges:**
  - Provider API rate limits: show how stale the data is.
  - Relay downtime (C1): the provider retries, and we must show our own uptime honestly.
  - Alert fatigue: per-event-type schedules and snooze.

### D. Sender Lab (producer conformance)

- **First minute:** create a receiver, choose the profile "Grumpy consumer", and paste its URL into your product's webhook settings.
- **Aha:** trigger 10 events. The receiver answers 503, then sleeps 35 s, then redirects to a private IP. The report reads: *"No backoff (3 retries in 4 s). Followed redirect to 10.0.0.5. `webhook-id` changed between retries. Signature: Standard Webhooks v1 ✓."*
- **Daily use:** a CI job runs the grader against staging before each release, and a badge appears in the docs.
- **Failure and edges:** the sender never calls (show the expected URL and a test checklist); sender IPs are blocked by our abuse rules (explain them).

---

## Part 2: Proving Ground in depth ★ recommended concept

### 2.1 The story in one paragraph

Maya is integrating Stripe subscriptions, with help from a coding agent.

1. She opens Webhook Trap, gets a URL, and triggers a test checkout. Four events land live, each signed and decoded.
2. She clicks **Prove this handler**. A one-line install starts the agent pointed at `localhost:3000/webhooks/stripe`.
3. She picks **Twice** and **Shuffled** and runs them. The timeline fills in real time. The first pass is green. On the second delivery of `customer.subscription.created`, the state probe changes: a second subscription row.
4. The verdict reads: *"Not idempotent: duplicate delivery created new state."* It shows exactly which delivery did it.
5. Her agent reads the same verdict through MCP, adds an event-ID guard, and reruns. Green.
6. She runs `trap save`. The four events become scrubbed, signed fixtures and a `trap.yml` scenario file in her repo. CI now fails if anyone breaks idempotency.

### 2.2 Key moments

| # | Moment | What the user experiences | Must feel |
| - | ------ | ------------------------- | --------- |
| M1 | **Arrival** | A personal capture URL exists on landing, with no sign-up (ADR-003 guest). | Instant |
| M2 | **First event** | The event appears live with provider, event type, size, signature verdict, and "related events" grouping (same object ID). | Instant (< 1 s from provider POST to screen) |
| M3 | **Signature verdict** | ✓ valid / ✗ invalid with reason / ? no secret configured. The secret is configured in the agent or the self-hosted instance. | Instant on arrival |
| M4 | **Connect the agent** | One command (`npx …` or a binary) with a pairing code. Status turns to "Connected · localhost:3000". | < 30 s |
| M5 | **Choose scenarios** | A scenario sheet with plain-language names and a one-line "why providers do this" plus provider citation. Sensible defaults pre-selected per provider. | Obvious |
| M6 | **Run (the aha)** | A timeline of deliveries streams live. Each row shows scenario, sent-at, re-signed ✓, status, latency against the provider budget line, and state digest. | Live, row by row |
| M7 | **Verdict** | A top banner: Passed / N findings. Each finding has a title, the evidence (which delivery), why it matters (provider behaviour), and how to fix it (framework-aware snippet). | Calm, specific |
| M8 | **Save as fixtures** | A preview of files to write, with scrubbing rules (emails, names, tokens, card fingerprints). Writes into the repo through the agent. | Trustworthy |
| M9 | **CI** | `trap test` locally or in CI, offline. PR annotation with findings. Optional upload of results to the hosted history. | Boring, reliable |
| M10 | **Agent loop** | An MCP and JSON interface: list events, run scenarios, read verdicts. | Machine-clean |

### 2.3 Scenario catalogue (language for UI and docs)

| Name | What it does | Finds | Provider basis |
| ---- | ------------ | ----- | -------------- |
| **Once** | Baseline single delivery | Crashes, slow handlers | All |
| **Twice** | The same event again (same event ID, new delivery ID where the provider does that) | Non-idempotent side effects | Stripe S1, Shopify S10, Slack S21 |
| **Shuffled** | Related events in a different order | State regression, missing-dependency crashes | Stripe S1, S29 |
| **Late** | A valid signature with an old timestamp beyond tolerance | Missing replay protection | S23, S27, S44 |
| **Tampered** | Body changed, signature kept | Accepting forged input | S44 |
| **Unsigned** | Signature header removed | Verification skipped or optional | S23 |
| **Slow budget** | Measures response time against the provider limit | Retries caused by slowness | Shopify S9, Slack S22 |
| **Retry after error** | Replays the provider's retry pattern, compressed in time, after a non-2xx | 4xx trap, partial side effects | S1, S7 |
| **Burst** | N related events at the same time | Race conditions, lock contention | S13 |
| **Sequence pack** | A curated, provider-real flow (e.g., Stripe subscription lifecycle) shuffled and duplicated | Domain-level ordering bugs | Community packs |

### 2.4 How the verdict is judged

- **Level 1, transport (default, zero setup):** status, latency against the budget, response consistency across duplicates, acceptance of Tampered, Unsigned or Late input, 4xx followed by a retry.
- **Level 2, state probe (opt-in, one line in `trap.yml`):** a URL or shell command that returns a digest of the relevant state (e.g., `SELECT count(*), max(updated_at) FROM subscriptions WHERE customer=…`). The digest is compared across scenarios. An optional **reset hook** runs between scenarios.
- **Level 3, assertions (CI):** your test command runs after each scenario and its exit code becomes the result.

The UI always says which level produced a finding, and suggests the next level when level 1 is inconclusive: *"Responses are identical, but we can't see your database. Add a probe to check for duplicate side effects."*

### 2.5 Screens (for UX)

1. **Landing:** see 2.8.
2. **Capture stream (home of a project or guest session):**
   - Left: a live list of deliveries, grouped by related object.
   - Right: the selected delivery with tabs **Verdict** (signature, shape, provider metadata), **Raw** (exact bytes, ordered headers, raw query string), **Decoded**.
   - The primary action, on every event and every group: **Prove**.
3. **Run (the main screen of the product):**
   - Top: the target (`localhost:3000/webhooks/stripe` via agent "maya-mbp"), scenarios, run status.
   - Center: the timeline, with one row per delivery and the provider budget as a vertical line on the latency bars.
   - Right or bottom: findings, ordered by severity.
   - Each finding links to the delivery that triggered it, with request and response side by side.
4. **Fixtures and suite:** the files in the repo (read through the agent), scenario file, last CI results, and shape baseline per event type (the seed of Horizon 2's drift detection).
5. **Agent and secrets:** connected agents, their targets, which providers have secrets configured locally (names only, never values).
6. **Packs:** browse and install provider sequence packs, with contribution guidance.

### 2.6 States the design must cover

| Surface | States |
| ------- | ------ |
| Capture stream | Waiting for first event (with provider instructions) · receiving · paused (tab hidden, catching up) · quota reached (guest) · expired (guest retention) |
| Signature chip | Valid · invalid (reason) · no secret · scheme unknown · unsigned by provider |
| Agent | Not installed · pairing · connected · target unreachable (connection refused, TLS error) · disconnected mid-run · version too old |
| Run | Draft · queued · running (live) · passed · findings · aborted (agent lost) · inconclusive (needs probe) |
| Finding | Critical (security, e.g. accepts tampered) · High (state divergence) · Medium (budget exceeded, 4xx trap) · Info (suggestion) |
| Fixtures | Not saved · saved (in sync) · drifted (new captures differ in shape from the saved baseline) |

### 2.7 What must be instant and what is live

- **Instant (< 1 s perceived):** event arrival on screen; signature verdict; opening any delivery; starting a run.
- **Live (streamed):** the capture stream; the run timeline row by row; agent connection status; the CI run status when uploaded.
- **Not live (refreshed on demand):** history, packs, settings, fixture diffs.
- **Never on the hosted server:** signing secrets, in any form or part (not even the last 4 characters); probe definitions, raw probe values, probe errors and reset output; and fixture contents unless the user explicitly uploads them.
- **Probe values stay on your machine. Only small counts and codes reach the server.** The agent judges Level 2 itself and sends the outcome (changed or not, as expected or not) with a display value: a count from −9 999 to 9 999 as is, anything else as a short code such as `#a1f3c0` that only shows whether a value changed within the run. A signed-in owner can opt an entry in to show a number or a short status (`show:`), on their private view only; guests can't. Rules: `spec/scenarios-and-judge.md` §8.1.1 (PV-1 to PV-14, decision 38).

### 2.8 Landing page story (for the art director)

1. **Hero:**
   - Line: *"Your webhook handler works. Once."*
   - Subline: *"Providers deliver twice, out of order, late, and retry. Webhook Trap replays real events the way they do, and tells you what your code did."*
   - Primary call to action: **Get a URL, no sign-up.**
   - Secondary: `npx …` copy chip.
2. **Demo strip:** one real event is delivered once (green), then twice. A state counter goes from 1 to 2 and the finding appears. This is the single image the brand should be able to tell.
3. **"Every way a webhook can arrive":** the scenario catalogue as a compact grid, each with its provider citation.
4. **"Real traffic, not fixtures you wrote"**: capture → scrub → commit → CI.
5. **"Secrets stay on your machine"** plus open source and self-hosted (Docker).
6. **"Works with your coding agent":** a short MCP and JSON example.
7. **Providers strip:** Stripe, Shopify, GitHub, Twilio, Slack, Standard Webhooks (OpenAI and others).
8. **Footer CTA:** "Prove your first handler in 5 minutes."

Tone: precise, calm, a little dry. The product judges code, not people. Avoid fear-mongering. Use the evidence (provider docs) as the authority.

### 2.9 Failure and edge cases (product behaviour)

- **No signing secret configured:** scenarios that need re-signing (Twice with a new timestamp, Late, Tampered) are marked "needs secret". Pure replays still run. Explain where to put the secret locally.
- **Provider signs the URL (Twilio):** re-signing must use the public URL the provider saw. If the local target differs, rewrite the signature for the configured "as-seen" URL and say so.
- **Handler has side effects outside the app** (emails, charges): warn before Twice or Burst, and offer a "dry-run header" convention so handlers can skip external calls.
- **Mutable state between scenarios:** without a reset hook, results show "order-dependent" and suggest one.
- **Huge or binary bodies:** stored raw within limits. Beyond the limit, capture metadata plus a hash, and say the body was not kept.
- **Guest limits:** quotas and short retention are visible up front. "Save as fixtures" is the way to keep things, not an account upsell wall.
- **Agent lost mid-run:** the run is marked aborted, with the deliveries already completed and their results kept.
- **Unknown provider:** treat as unsigned or generic. Offer a scheme definition (header, algorithm, signed-string template) that can later become a pack.
