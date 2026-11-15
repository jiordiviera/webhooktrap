# Scenarios and Judge: authoritative rules

**Status:** REVIEW_REQUIRED. Nothing in this document is approved.
**Author:** A01 (Product Strategist) · **Level:** C3 · **Date:** 2026-11-14 · **Revision 2:** 2026-11-14, aligned with decisions 35 to 41 (§18 lists every change)
**Answers:** A11 testability review (`project/qa/testability-review.md`) §3, items assigned to A01: A-1 to A-6, A-8 to A-11, A-16, A-17, A-19 to A-22, A-24, A-25. A-7, A-13 and A-18 are written as recommendations for the PO (§14). Decision 31 proxy validation: §15.
**Context:** concept B "Proving Ground" (decision 23 and later), field validation waived (decision 31), local agent in the MVP and Redis required (decision 32), Gate 1 passed (decision 34).

---

## 0. How to read this document

- **Authority.** For anything the judge decides (what is sent, what counts as a finding, severity, merging, run status, exit code, machine output content), this document wins over `core-experience.md`, `validation/prototype-spec.md`, `ux-foundations.md`, `ux-timetable-v2.md` and `realistic-data.md`. Where they disagree, §16 lists the change each one needs. The Go prototype (`spikes/proving-cli`) is evidence of what is buildable, not the spec.
- **Rule IDs.** Every rule has an ID (`ONCE-2`, `SEV-1`, `RUN-4` …). Tests, golden files and copy reference rules by ID.
- **Words.** MUST and MUST NOT are binding. "Default" means a value the user can change and the place where they change it is named.
- **Constraint for A05.** Marked boxes are timing, mechanics or architecture that A05 owns in the "Local agent and client-side scenario engine" ADR. Each one gives the **product-side bound**: A05 may make it tighter, not looser.
- **PO decision.** Marked items are recommendations with options. They are not decided here.
- **Names.** Command, config file and header names follow §12 (A-20): `webhooktrap`, `webhooktrap.yml`, `Webhooktrap-*`. Examples use them. Copy in other documents still says `trap`; §16 lists the change.

### 0.1 Where each A11 item is answered

| A11 item | Section | Short answer |
| -------- | ------- | ------------ |
| A-1 | §2, §3.1–3.3 | Once sends every selected event, in capture order, and records two baselines. Twice: 500 ms after the first delivery completes. Shuffled: reverse capture order. |
| A-2 | §5 | One severity principle, one table. Late stays High (with the reason). Twice has no special Level 1 severity. |
| A-3 | §6 | Fixed finding types. Merge key = (type, event type). F-numbers by severity, then first offending delivery. |
| A-4 | §7 | One table: run status, label, exit code. Info findings don't count in "N findings". |
| A-5 | §3.1 (ONCE-4), §7.1 | Baseline rejected = only the first Once delivery. "Completed delivery" defined. |
| A-6 | §3.4, §3.6 | Outcome matrix for invalid deliveries. Tampered keeps a fresh timestamp. |
| A-8 | §3.5 | Retry after error always sends 2 attempts; decision table for the oracle. |
| A-9 | §3.7 | Burst: barrier release, 20 ms spread bound, final-state oracle. |
| A-10 | §8 | One probe syntax, failure rules, settle, async check, reset failure, expected state per delivery. What leaves the agent: PV-1 to PV-14 (§8.1.1). |
| A-11 | §4 | 10 000 ms for Standard Webhooks and unknown. 3× median dropped. Uniformly slow handlers are flagged. |
| A-16 | §11 | Percentiles, load and environment for each target. |
| A-17 | §10 | Pack-declared group keys plus linking; shape = typed path set, union baseline. |
| A-19 | §9 | Ordered diagnosis reasons and the exact 7 checks. |
| A-20 | §12 | `webhooktrap` (because `trap` is a shell builtin), `webhooktrap.yml`, `Webhooktrap-*` headers. |
| A-21 | §2.4 | 50 deliveries per run, 1–5 events, loopback-only by resolved address, pinned. |
| A-22 | §6.4 | Likely cause only from a fixed rule table. Express, Next.js, Laravel snippets. |
| A-24 | §13 | Field list for `--json`, MCP and "Copy as JSON". Two profiles: local (real values, prose) and hosted (display values, no prose). |
| A-25 | §10.3 | Pack rules plus value patterns, consistent replacement, always re-signed, residual match blocks the save. |
| A-7, A-13, A-18 | §14 | PO decisions, with options and A01's recommendation. |

---

## 1. Definitions

| Term | Definition |
| ---- | ---------- |
| **Source event** | A captured delivery (or a fixture) selected for a run. Has a provider, an event type and exact body bytes. |
| **Selection** | 1 to 5 source events from **one** provider (DEF-1). |
| **Capture order** | Source events sorted by `received_at` ascending, ties broken by capture id. Fixtures carry their capture order in the fixture file. |
| **Primary event** | The first source event in capture order. Twice, Late, Retry after error, Tampered, Unsigned and Slow budget use it. |
| **Delivery** | One HTTP request the agent sends to the target. Numbered D1…Dn in send order, across the whole run. |
| **Valid delivery** | The source event's exact body, with a signature that the provider's own verifier accepts at send time. Sent by Once, Twice, Shuffled, Retry after error, Burst, Slow budget. |
| **Invalid delivery** | A delivery built to fail verification: Late (stale timestamp), Tampered (changed body), Unsigned (no signature). |
| **Response class** | `2xx`, `3xx`, `4xx`, `5xx`, or a transport outcome (below). 1xx is never final and is ignored. |
| **Unreachable** | No connection could be established: connection refused, DNS failure, TLS handshake failure. Not the handler's fault; never a finding. |
| **Transport failure** | A connection was established and the request sent, but no response status line arrived: timeout (30 s), connection reset or closed early. The handler's fault. |
| **Latency** | Monotonic time from the first byte of the request written to the socket to the last byte of the response **headers** received. Not measured for unreachable deliveries. |
| **Completed delivery** | Its response status (or transport failure, or unreachable) is recorded **and**, when a probe is configured, its probe reading is recorded or marked `probe_failed`. Only completed deliveries are judged. A delivery that was sent but not completed shows `sent, no result` and is not judged (A-5). |
| **Probe reading** | `before` and `after` values of each named probe for a delivery (§8). |
| **Baselines** | `B_primary` = probe reading after Once's first delivery. `B_final` = probe reading after Once's last delivery. Both exist only when a reset ran before Once (§8.4). |
| **Pack** | Versioned provider data (budget, signing scheme, tolerance, duplicate and retry rules, tamper paths, group keys, scrub rules, citations). The judge reads packs only, never hard-coded provider names (A11 D-8). |

**DEF-1.** A selection contains 1 to 5 events from one provider. A draft with events from two providers MUST be refused before anything is sent ("Select events from one provider").

**DEF-2.** Every rule below that mentions a provider value (budget, tolerance, header) reads it from the pack. Values quoted in this document are the MVP pack values.

---

## 2. The run plan

### 2.1 Scenario order and delivery count

**PLAN-1.** Scenarios always run in this order. A run never reorders them.

| # | Scenario | Deliveries (n = number of selected events) | Concurrency | Needs |
| - | -------- | ------------------------------------------- | ----------- | ----- |
| 1 | Once | n | sequential | always runs (PLAN-2) |
| 2 | Twice | 2 | sequential | — |
| 3 | Shuffled | n | sequential | n ≥ 2, else `n/a (needs 2+ related events)` |
| 4 | Late | 1 | — | pack has a signed timestamp, else `n/a (provider signs no timestamp)` |
| 5 | Retry after error | 2 | sequential | pack has automatic retries, else `n/a ({provider} doesn't retry automatically)` |
| 6 | Tampered | 1 | — | JSON body |
| 6b | Unsigned *(only if the PO puts it in the MVP, §14.1)* | 1 | — | pack has a signature header |
| 7 | Burst | max(n, 2) | concurrent | — |
| 8 | Slow budget | 1 | — | — |

Example: 3 selected Stripe events, all 8 scenarios → 3 + 2 + 3 + 1 + 2 + 1 + 3 + 1 = **16 deliveries**. With Unsigned: 17.

**PLAN-2.** Once is mandatory whenever any other scenario is selected. It is the setup check (ONCE-4) and the source of the baselines. The draft shows it checked and locked.

**PLAN-3.** Inside a sequential scenario, a delivery is sent only after the previous one is **completed** (§1). Gaps are measured from that completion.

| Gap | Value |
| --- | ----- |
| Once and Shuffled, between deliveries | 150 ms |
| Twice, between D_a and D_b | 500 ms |
| Retry after error, between attempt 1 and attempt 2 | 2 000 ms |
| Between scenarios | reset (if any), then 150 ms |

> **Constraint for A05 (PLAN-3).** Each gap MUST be ≥ the stated value and ≤ the stated value + 100 ms, measured on the agent's monotonic clock. Waits go through the scheduler port so unit tests run them in virtual time (A11 D-4). Recorded offsets are the real ones.

**PLAN-4.** The plan is fixed before the first delivery: the ordered list of deliveries, their source event, signing instruction, mutation and expected-state rule. It is stored in the run file, and the judge works only from the plan plus the observations (A11 D-1).

### 2.2 Signing

**SIGN-1.** When the agent holds a secret for the provider, every valid delivery is **re-signed** at send time with the current wall clock. Late uses the timestamp in LATE-1. Tampered uses TAMP-2. Unsigned removes the signature (UNS-1).

**SIGN-2.** Without a secret:

| Pack signing | Valid deliveries | Late | Tampered | Unsigned |
| ------------ | ---------------- | ---- | -------- | -------- |
| Signed timestamp (Stripe, Standard Webhooks) | Can't be sent: a captured signature goes stale after the tolerance. The draft shows **Needs secret** and Run is disabled. | — | — | — |
| No timestamp (GitHub, Shopify) | Sent with the **original** signature header (the body is unchanged, so it still verifies). | n/a | Original signature header, changed body | Runs |
| Unsigned or unknown scheme | Sent as captured | n/a | n/a (nothing to forge) | n/a |

**SIGN-3.** Every delivery carries `Webhooktrap-Run: <run id>`, `Webhooktrap-Delivery: D<n>`, `Webhooktrap-Scenario: <scenario>` and `Webhooktrap-Dry-Run: 1` (§12).

> **Constraint for A05 (SIGN-4, from A11 A-6).** Before a run starts, the agent MUST check each planned request against the pack's own verifier: every valid delivery passes, Late fails on the timestamp only, Tampered fails on the signature only, Unsigned fails for a missing header. If a check fails, that scenario is `n/a (couldn't build a faithful request)` and is not sent. CI runs the official SDK cross-check (A11 D-9).

### 2.3 Duplicate and retry headers (pack data)

**DUP-1.** A duplicate (Twice D_b) and a retry (Retry after error attempt 2) keep the provider's **event id**. Other headers follow the pack:

| Pack | On a duplicate | On a retry | Verification status |
| ---- | -------------- | ---------- | ------------------- |
| Stripe | Same body (same `evt_` id). New `Stripe-Signature` timestamp. | Same as duplicate. Stripe sends no retry header. | To verify and cite before the pack ships |
| GitHub | Same body. `X-GitHub-Delivery` kept, as on a redelivery. | n/a (no automatic retries, `provider-budgets.md` §2) | **To verify.** The prototype generates a new `X-GitHub-Delivery` on a duplicate; this rule says keep it. A01 records the docs page before the pack ships. |
| Shopify | Same body. `X-Shopify-Webhook-Id` kept. | Same as duplicate. | To verify and cite |
| Standard Webhooks | Same body. `webhook-id` kept. New `webhook-timestamp` and `webhook-signature`. | Same as duplicate. | Spec says the id is stable across retries; cite the section |

**DUP-2.** A pack value marked "to verify" MUST NOT ship. Until it is verified, the scenario that needs it is `n/a (not verified for {provider})`. This is the same discipline as `provider-budgets.md`.

### 2.4 Caps and target validation (A-21)

**CAP-1.** A run sends at most **50 deliveries**. The draft computes the count from PLAN-1 and refuses more, showing the count ("This run would send 54 deliveries. The limit is 50."). Exit 3 in the CLI. With 5 events and all scenarios the count is 22 (23 with Unsigned), so 50 leaves room for sequence packs later.

**CAP-2.** A run lasts at most **15 minutes** of wall time. When reached, the run stops as Aborted, reason `time_limit`.

**TGT-1.** A target is **local** when every address its host resolves to is loopback: `127.0.0.0/8`, `::1`, or IPv4-mapped `::ffff:127.0.0.0/104`. `localhost` and `*.localhost` resolve to loopback inside the agent, with no DNS query (RFC 6761).

**TGT-2.** Refused: `0.0.0.0`, `::`, any host that resolves to a mix of loopback and other addresses, any non-local host not in the agent's allow-list. The allow-list lives on the agent (`webhooktrap targets add <host:port>`); the server can't add to it. Allow-list entries match host and port exactly. A non-local allowed host prints a warning on every run.

**TGT-3.** Scheme `http` or `https`. TLS errors make the target unreachable, unless `--insecure-localhost` is set and the target is local.

**TGT-4.** Redirects are never followed. A 3xx is a response.

> **Constraint for A05 (TGT-5).** The agent resolves the host once at run start and pins the addresses for every delivery of the run (no rebinding). Request timeout is 30 s, from the first byte written to the response headers. Before each delivery, if the connection is refused, the agent retries the connect only (no request bytes sent) every 500 ms for up to 5 s, so a dev server restarting after a file change isn't reported as unreachable. Only then is the delivery `unreachable`.

---

## 3. Scenarios

Each scenario has: what it sends, its expected state (§8.5), and its findings. The generic Level 1 rules in §3.0 apply to **every valid delivery in every scenario**; scenario sections add only what is specific.

### 3.0 Generic rules for valid deliveries (Level 1)

| ID | Observation on a valid delivery | Finding type | Severity |
| -- | ------------------------------- | ------------ | -------- |
| GEN-1 | 5xx, or transport failure (timeout, reset) | `server_error` | High |
| GEN-2 | 3xx or 4xx (except ONCE-4) | `non_2xx_on_valid` | Medium |
| GEN-3 | Latency > budget (§4), response received | `over_budget` | Medium |
| GEN-4 | Unreachable | none; the delivery result is `unreachable` (§7) | — |

**GEN-5.** A transport failure is not also `over_budget`: GEN-1 covers it.

### 3.1 Once (A-1, A-5)

**ONCE-1.** Sends every selected event once, in capture order, as valid deliveries, gaps per PLAN-3.

**ONCE-2.** Records `B_primary` (after D1) and `B_final` (after the last Once delivery), when a reset ran before Once (§8.4).

**ONCE-3.** Findings: GEN-1 to GEN-3. No state expectation (Once defines the baseline).

**ONCE-4 (baseline rejected).** If the **first** Once delivery (the primary event) gets a 3xx or 4xx, the run stops at once. Nothing else is sent. Status `Couldn't start`, reason `baseline_rejected`, exit 2. Copy: "Your handler rejected a correctly signed delivery (HTTP {status}). Check the secret on {agent} and the target path." This is not a finding.

**ONCE-5.** If a **later** Once delivery gets a 3xx or 4xx while the first got a 2xx, the setup is proven (the same secret verified D1), so this is GEN-2 `non_2xx_on_valid` (Medium) and the run continues. This is the common "400 for an event type I don't handle" case: the provider counts it as failed and retries it.

**ONCE-6.** If the first Once delivery is unreachable after TGT-5, the run stops: `Couldn't start`, reason `target_unreachable`, exit 2.

### 3.2 Twice (A-1, A-2)

**TWICE-1.** Sends the primary event (D_a), then the same event again (D_b) **500 ms after D_a completes**, with DUP-1 headers. Not 50 ms: near-simultaneous duplicates are Burst's job (§3.7).

**TWICE-2 (Level 1).** GEN-1 to GEN-3 on both deliveries. There is **no Twice-specific Level 1 severity**. A 409 or 400 on the duplicate is GEN-2 (Medium): the provider treats it as a failure and retries it.

**TWICE-3 (Level 1, nothing failed).** If both got a 2xx and no probe is configured, the scenario result is `inconclusive` (reason `needs_probe`) and the run gets the Info finding `needs_probe` (§6). Different response bodies are not a finding.

**TWICE-4 (Level 2).** If D_a got a 2xx: `after(D_b) ≠ after(D_a)` → `duplicate_changed_state` (High), evidence D_a (reference) and D_b (offending). If D_a did not get a 2xx, D_b has no state expectation (GEN already reported D_a).

### 3.3 Shuffled (A-1)

**SHUF-1.** Sends the selected events in the **reverse of capture order**, as valid deliveries. Not random: with at most 5 events, reverse order puts every dependent event before its dependency, it needs no seed, and it reproduces exactly. The name "Shuffled" stays as UI copy; the copy says "in reverse order". Seeded permutations are post-MVP.

**SHUF-2 (Level 1).** GEN-1 to GEN-3. With nothing failed and no probe: `inconclusive` (`needs_probe`).

**SHUF-3 (Level 2).** Needs a reset before Once and before Shuffled. `after(last Shuffled delivery) ≠ B_final` → `order_changed_state` (High), evidence = all Shuffled deliveries (offending: the last one; reference: the last Once delivery). Without a reset: Level 2 part `inconclusive` (reason `needs_reset`).

### 3.4 Late (A-6)

**LATE-1.** Sends the primary event with a signature that is valid for a timestamp of **now − (pack tolerance + 60 s)**. Stripe: 300 + 60 = 360 s. Standard Webhooks: 300 + 60 = 360 s. Body unchanged.

**LATE-2.** n/a when the pack has no signed timestamp (GitHub, Shopify): `n/a (provider signs no timestamp)`. Never a finding.

**LATE-3.** Outcome: the invalid-delivery matrix in §3.6 with finding type `accepts_stale` (High).

### 3.5 Retry after error (A-8)

**RETRY-1 (trigger).** Always sends two attempts of the primary event: attempt 1 (A1), then attempt 2 (A2) **2 000 ms after A1 completes**, with DUP-1 retry headers. A2 is always sent, even when A1 got a fast 2xx, because a provider can't see a response that arrived after its own timeout, and a handler can't tell a retry from a duplicate. This explains RD Run #4 (`expected 1 · got 1` on a fixed handler).

**RETRY-2.** The agent never cuts A1 short at the budget. It waits up to the 30 s request timeout and records the real latency.

**RETRY-3.** A1 is **failed in the provider's eyes** when it got a non-2xx, a transport failure, or a latency over the budget. The row shows this ("over budget, treated as failed"); it changes copy, not the oracle.

**RETRY-4 (Level 1).** GEN-1 to GEN-3 on A1 and A2. With both 2xx and no probe: `inconclusive` (`needs_probe`).

**RETRY-5 (Level 2 decision table).**

| A1 response | A1 state | Rule | Finding |
| ----------- | -------- | ---- | ------- |
| 2xx (fast or over budget) | any | `after(A2) ≠ after(A1)` | `duplicate_changed_state` (High), offending A2 |
| non-2xx or transport failure | changed (`after(A1) ≠ before(A1)`) | always | `partial_side_effect` (High), offending A1: the handler reported failure but kept a write, and the provider will retry |
| non-2xx or transport failure | unchanged | needs reset: `after(A2) ≠ B_primary` | `retry_diverged` (High), offending A2: the retry didn't leave the state one clean delivery leaves (e.g. the event id was recorded before the failure, so the retry was skipped) |
| non-2xx or transport failure | unchanged | no reset | Level 2 part `inconclusive` (`needs_reset`) |

**RETRY-6.** Scenario copy: "Sends the event, then the provider's retry 2 s after it completes. Providers retry when a delivery fails or takes too long; your handler can't tell which." Retry headers come from the pack; the copy never says "Stripe always retries".

### 3.6 Tampered (A-6) and the invalid-delivery matrix

**TAMP-1 (what changes).** The body is edited **in place in the raw bytes** (no re-serialisation), Content-Length recomputed. The edit is the first rule that applies:
1. The first path in the pack's `tamper_paths` for this event type that exists with a scalar value. Numbers: non-zero → `0`, zero → `1`. Strings and booleans: the pack gives the replacement value (e.g. Stripe `invoice.*`: `data.object.amount_paid` → 0, then `data.object.total` → 0).
2. Otherwise the first numeric field, in document order, whose name contains `amount`, `total`, `price` or `quantity`, by the number rule.
3. Otherwise insert `"_webhooktrap_tampered":true` as the last member of the top-level object. This changes the bytes without changing what the handler does, so an accepting handler is visible.
Fields named `id`, `type`, `object`, `created` or ending in `_id` are never changed: changing them would make the handler reject for a lookup reason, which would hide a missing signature check.

**TAMP-2 (signature).** The signature header is computed for the **original** body at a **fresh** timestamp (now), with the real secret. Only the body check can fail. It is not the capture's original header: for timestamped providers that header is also stale, so a handler that checks only the timestamp would pass Tampered by accident. Without a secret, SIGN-2 applies.

**TAMP-3.** Non-JSON bodies: `n/a (body isn't JSON)`. The diff (`path: from → to`) is shown on the row and in the finding.

**UNS-1 (Unsigned, if in MVP).** Removes every signature header of the pack (`Stripe-Signature`; `X-Hub-Signature-256` and `X-Hub-Signature`; `X-Shopify-Hmac-Sha256`; `webhook-signature`, keeping `webhook-id` and `webhook-timestamp`). Body unchanged. Finding type `accepts_unsigned` (Critical).

**INV-1 (outcome matrix for Late, Tampered, Unsigned).** "Changed" means the probe reading changed (`after ≠ before`). `T` is the scenario's type: `accepts_stale` (High), `accepts_tampered` (Critical), `accepts_unsigned` (Critical).

| Response | Level 1 only | Level 2, state unchanged | Level 2, state changed |
| -------- | ------------ | ------------------------ | ---------------------- |
| 2xx | `T` | `T` | `T` |
| 4xx | pass (rejected) | pass (rejected) | `T` ("rejected, but the write happened first") |
| 3xx | `inconclusive` (`redirected`) | pass (rejected) | `T` |
| 5xx or transport failure | `error_on_invalid` (Medium) | `error_on_invalid` (Medium) | `T` |
| Unreachable | `unreachable` | `unreachable` | `unreachable` |

**INV-2.** Invalid deliveries are excluded from GEN-3 (budget): providers never send them.

### 3.7 Burst (A-9)

**BURST-1.** Sends every selected event once, all released together from a barrier. With one selected event, it sends **two copies** of it (same event id, DUP-1 headers): concurrent duplicates are what Stripe's "may deliver more than once" looks like under load.

**BURST-1a (numbering).** Burst deliveries are released together, so "send order" doesn't apply. Their D-numbers are assigned in the plan in **capture order** (the one-event case: the original first, then the copy). In RD Run #3, D13 = #01, D14 = #03, D15 = #04.

**BURST-2 (simultaneity).** The dispatch spread is the time from the first to the last request's first byte written, on the agent's monotonic clock. If it is **> 20 ms**, the scenario result is `inconclusive` (reason `not_simultaneous`) and it is not judged at Level 2.

> **Constraint for A05 (BURST-2).** Product bound: spread ≤ 20 ms on loopback. Connections are opened before the barrier so the spread doesn't include connect time. Assert the spread the agent records, not arrival times at the handler (A11 D-5).

**BURST-3 (Level 1).** GEN-1 to GEN-3 on each delivery. With nothing failed and no probe: `inconclusive` (`needs_probe`).

**BURST-4 (Level 2).** The probe is **not** read between Burst deliveries (readings can't be attributed). It is read once before the barrier and once after all Burst deliveries completed (with the settle rule, §8.3). Needs a reset. Final ≠ `B_final` (or ≠ `B_primary` for the one-event case) → `concurrent_changed_state` (High), evidence = all Burst deliveries. Rows show the state as `—`; the scenario reading shows `before → final`.

### 3.8 Slow budget (A-11)

**SLOW-1.** Sends one more valid delivery of the primary event, after the reset. It measures the handler warm, after the other scenarios ran, and gives a run that only cares about latency something to measure.

**SLOW-2.** Its findings are GEN-1 to GEN-3. `over_budget` from any scenario merges with it (§6.2), so the Slow budget strip cell shows the run's `over_budget` finding even when the evidence came from elsewhere.

---

## 4. Budgets (A-11)

### 4.1 Values

**BUD-1.** Budgets come from `provider-budgets.md` (verified 2026-11-14). The MVP packs use:

| Pack | Budget | Source tag | Label |
| ---- | ------ | ---------- | ----- |
| Stripe | 10 000 ms | `default` | `10 s budget · Webhook Trap default` |
| GitHub | 10 000 ms | `GitHub docs` | `10 s · GitHub limit` |
| Shopify | 5 000 ms | `Shopify docs` | `5 s · Shopify limit` |
| **Standard Webhooks** | **10 000 ms** | `default` | `10 s budget · Webhook Trap default` |
| Unknown provider | 10 000 ms | `default` | `10 s budget · Webhook Trap default` |

**Conflict resolved.** `realistic-data.md` gives Parcelbird (Standard Webhooks) 15 000 ms. That is wrong: the Standard Webhooks spec's 15–30 s is guidance for **senders**, not a consumer limit (`provider-budgets.md` §1). Standard Webhooks and every provider without a pack use the generic 10 000 ms default. Twilio (15 000 ms) and Slack (3 000 ms) apply only if their packs ship (§14.3).

**BUD-2 (project setting).** `budget:` in `webhooktrap.yml` is in the MVP, with source tag `webhooktrap.yml`. Allowed range 100–30 000 ms. A value above a **provider-documented** limit (GitHub 10 000, Shopify 5 000) is refused as a config error (exit 3): it would hide a failure the provider documents. A value above a `default` is allowed.

**BUD-3.** The budget number never appears without its source tag, and possessives ("Stripe's budget") only when the source is the provider's docs (`ux-timetable-v2.md` §4 rules 1–3 stand).

### 4.2 The over-budget rule

**BUD-4.** A delivery is over budget when **latency > budget** (equal is within), latency as defined in §1.

**BUD-5.** `over_budget` (Medium) applies to every valid delivery in every scenario that got a response (GEN-3). The PS rule "and ≥ 3× the run median" is **dropped**: it hid a handler that is slow on every delivery. A handler slow on every delivery gets one `over_budget` finding per event type with every slow delivery as evidence, and the likely cause LC-5 (§6.4).

**BUD-6 (cold start).** If D1 is over budget **and** every other valid delivery of the run is within budget, D1 is not `over_budget`. It gets the Info finding `cold_start` ("D1 took {latency_ms} ms, later deliveries ≤ {max_other_ms} ms. Likely a cold start, such as a dev server compiling the route. Not counted."; params per FND-11). This is the only exemption, and only for D1, because development servers (Next.js dev, Laravel with cold caches) compile on the first request.

**BUD-7.** Severity of `over_budget` is Medium whatever the budget source (`ux-timetable-v2.md` §7 open point 3 closed). CI's default `--fail-on high` doesn't fail on it, because CI runner latency is not the provider's network.

---

## 5. Severity model (A-2)

### 5.1 The principle

**SEV-1.** Severity is the **worst consequence the run proved**, in this order:

| Severity | Consequence class | Plain meaning |
| -------- | ----------------- | ------------- |
| Critical | **Forged input had an effect.** Anyone who can reach the URL can make the handler act on content the provider never sent. | Authentication bypass |
| High | **A real provider behaviour corrupts state or loses the delivery.** Replayed genuine content was accepted, state diverged, or a valid delivery failed. | Data integrity or lost event |
| Medium | **Operational risk, no proven harm.** The provider will see failures and retry, alert or disable the endpoint, but no wrong state was shown. | Retries, noise, disabling |
| Info | **Something we couldn't check, or a suggestion.** Never counts as a finding in the run status (RUN-3). | Honesty about limits |

### 5.2 The table (no exceptions)

| Type | Severity | Where it comes from |
| ---- | -------- | ------------------- |
| `accepts_tampered` | Critical | INV-1 Tampered |
| `accepts_unsigned` | Critical | INV-1 Unsigned (if in MVP) |
| `accepts_stale` | High | INV-1 Late |
| `duplicate_changed_state` | High | TWICE-4, RETRY-5 |
| `order_changed_state` | High | SHUF-3 |
| `concurrent_changed_state` | High | BURST-4 |
| `partial_side_effect` | High | RETRY-5 |
| `retry_diverged` | High | RETRY-5 |
| `server_error` | High | GEN-1 |
| `non_2xx_on_valid` | Medium | GEN-2, ONCE-5 |
| `error_on_invalid` | Medium | INV-1 |
| `over_budget` | Medium | GEN-3, BUD-5 |
| `needs_probe` | Info | TWICE-3, SHUF-2, RETRY-4, BURST-3 |
| `order_dependent` | Info | PRB-9 (no reset) |
| `probe_gap` | Info | PRB-12 |
| `cold_start` | Info | BUD-6 |
| `async_write` | Info | PRB-6 |

### 5.3 Why Late is High, not Critical

Late can only replay a request the provider really signed: an attacker needs a captured genuine request and can't change its content. The worst outcome is that a genuine event is processed again, which is the same consequence class as a duplicate (`duplicate_changed_state`, High). Tampered and Unsigned let anyone send content of their choosing, which is a different class. A11's proposal "a probe change on Late raises it to Critical" is **rejected**: a state change after a replayed genuine event is still a duplicate effect. The PS line "Late with probe: raise to High" is removed because it was a no-op.

### 5.4 Why Twice has no special Level 1 severity

At Level 1 the judge sees only status and latency, so Twice follows the generic rules: a 5xx is a failed valid delivery (High), a 4xx or 3xx is the "4xx trap" (Medium: the provider retries, and Stripe disables endpoints that keep failing), and two 2xx are Inconclusive with `needs_probe`. The PS rule "response class differs → Medium, duplicate not handled cleanly" is replaced by these rules. It gave the same result for 4xx, and a smaller severity than the generic rule for 5xx.

### 5.5 Why the 4xx trap is Medium, not High

A 4xx on a valid delivery proves the provider will count the delivery as failed. It doesn't prove lost state: the provider retries (Stripe, Shopify, Standard Webhooks), and the retry may succeed once the missing record exists. Losing the event entirely is proven only by state (`retry_diverged`, High).

---

## 6. Findings: identity, merging, numbering, content (A-3, A-22)

### 6.1 Identity

**FND-1.** A finding has a **type** from §5.2. No other type exists in the MVP. Adding one is a change to this document.

**FND-2.** Each finding has a `fingerprint` = SHA-256 of `type | provider | event type | target path` (first 12 hex characters). It is stable across runs, so the PR comment can say "new" or "still there" and the hosted history can link runs.

### 6.2 Merging

**FND-3.** All observations in one run with the same **(type, event type)** merge into one finding. Its evidence is the union of their deliveries; its scenarios are the union of their scenarios. This reproduces RD F2 (Twice D3 + Retry D9, both `invoice.paid`) and F3 (Retry D8 + Slow budget D14), and keeps different event types apart (a 5xx on `invoice.paid` and a 5xx on `customer.subscription.updated` are two findings with probably two causes).

**FND-4.** Run-level Info types (`needs_probe`, `order_dependent`, `probe_gap`, `cold_start`, `async_write`) merge on **type only**: at most one of each per run.

**FND-5.** Each evidence item has a role: `offending` (the delivery that broke the rule) or `reference` (the delivery it is compared with). RD F2: D3 and D9 offending, D2 and D8 reference.

### 6.3 Numbering

**FND-6.** Findings, Info included, are numbered F1…Fn by: severity (Critical first), then the smallest offending D-number, then type name alphabetically. Numbers are assigned when the run ends. While running, findings appear sorted by the same key, and the list re-sorts when a more severe one arrives.

**FND-6a (no F-number while running, every surface).** Until the run reaches a final status, **no surface shows an F-number**: the CLI live lines, the run page, the shared view (`/s/{token}`), MCP `get_run` and the timeline events all identify a provisional finding by its title (from its type, FND-7) and severity only. F-numbers appear with the final status, and they are then stable (`webhooktrap show F1`, `/r/{runId}/F1`). A03's choice (user flows AC-3.7, live CLI rows without the F-number) is **confirmed** and extended to every surface, because a number that can change under the reader is worse than no number, and the agent's provisional findings (contracts §5.5 `findings_provisional`) carry no number.

> **Note for A03.** TT §1.4.1: live `new finding` lines show the title without the F-number. The summary shows final numbers.

### 6.4 Content of a finding

**FND-7.** Every finding has: title (from the type, with the event type), level (1 or 2: Level 2 if any offending evidence was judged on a probe reading), evidence, *What happened* (measurements first), *Why it matters* (provider behaviour + citation from the pack; consequence in this run when there is one), *Fix* (one imperative sentence + what the run should show after the fix), optional *Likely cause* (FND-8), optional snippet (FND-10). Voice rules in `ux-timetable-v2.md` §1.3 stand.

**FND-7a (text is derived, never written by the agent; ADR-010 §3, TM-16).** Every piece of finding text (title, *What happened*, *Why it matters*, *Fix*, *Likely cause*, snippet, citation) is a **template in the pack**, keyed by finding type (and likely-cause rule, framework), filled only from:
1. the finding's **type**, severity, level, scenarios and evidence D-numbers;
2. **provider data** from the pack version named in the run (provider name, budget and its source label, tolerance, citation label and URL);
3. the **params** of FND-11, whose probe-related values are **display values** (PV-2, PV-5), never raw values.

There is no free-text field. The agent sends `type`, `likely_cause.rule`, `framework` and `params`; the hosted server renders the text from the same templates (the pack version in the run) and picks the citation from the pack's list for that type, ignoring any URL in the document. The local CLI renders the same templates from the same params, so local and hosted text differ only where a local value is real and the hosted one is a display value (`2 → 3` locally can be `#a1f3c0 → #9c0254` hosted; small counts read the same). A finding that needs a sentence no template can produce needs a new template in the pack, which is a change to this document (FND-1).

**FND-8 (likely cause, A-22).** A *Likely cause* line comes **only** from this rule table, evaluated on the run's own findings and deliveries. Nothing is inferred from other captures, commit messages, provider APIs or repository history. If no rule matches, there is no Likely cause line. The agent sends the rule id (`LC-n`); the text is the pack template below, filled from FND-11 params.

| ID | Condition | Attached to | Text |
| -- | --------- | ----------- | ---- |
| LC-1 | `accepts_tampered` and Late was rejected (4xx, state unchanged) | `accepts_tampered` | "Late (D{n}) was rejected, so the timestamp check runs; the signature check probably fails into a log line instead of stopping the request." |
| LC-2 | `accepts_tampered` and `accepts_stale` | both | "Neither the signature nor the timestamp stops the request: verification is probably missing, or its result is ignored." |
| LC-3 | `accepts_stale` and Tampered rejected | `accepts_stale` | "The signature is checked but the timestamp isn't. This happens when the HMAC is verified by hand, or the tolerance is set very high." |
| LC-4 | `over_budget` on fewer than half of the valid deliveries of that event type | `over_budget` | "Only some deliveries of {event type} were slow, so the slow step is intermittent: usually an external call or a lock wait before the response." |
| LC-5 | `over_budget` on at least half of the valid deliveries of that event type | `over_budget` | "Most deliveries of {event type} were slow, so the slow work runs before the response on every request." |
| LC-6 | `non_2xx_on_valid` in Shuffled on an event that arrived before an event it follows in capture order | `non_2xx_on_valid` | "The handler rejects an event that arrives before the record it updates." |
| LC-7 | `server_error` only in Shuffled or Burst, and the same event type got 2xx in Once | `server_error` | "The same event succeeded in order, so the crash depends on arrival order or concurrency, not on the payload." |
| LC-8 | `duplicate_changed_state` and the two compared deliveries got the same status and identical response bytes | `duplicate_changed_state` | "The handler doesn't record the event id before it writes." |
| LC-9 | `concurrent_changed_state` and Shuffled passed at Level 2 | `concurrent_changed_state` | "Order alone is fine, so concurrent requests race: a check-then-insert without a unique constraint or a lock." |
| LC-10 | `partial_side_effect` | itself | "The handler writes before the step that fails, and doesn't roll back." |

**FND-9 (framework detection).** Only from the target's response headers, in this order: `X-Powered-By: Express` → Express; `X-Powered-By: Next.js` → Next.js; a `laravel_session` cookie or `X-Powered-By` containing `Laravel` → Laravel. `framework:` in `webhooktrap.yml` overrides detection. The row says how it was found ("Express (detected from X-Powered-By)" or "Laravel (set in webhooktrap.yml)").

**FND-10 (snippets, MVP).** Framework-specific fix snippets exist for **Express, Next.js route handlers and Laravel**, for these types: `accepts_tampered`, `accepts_unsigned`, `accepts_stale`, `duplicate_changed_state`, `over_budget`, `non_2xx_on_valid`. Every other (type × framework) pair, and every undetected framework, gets the generic Fix text and no snippet. A probe snippet (`webhooktrap.yml`) exists for `needs_probe`, `order_dependent` and `probe_gap`. **Each snippet must pass A11 U10**: applied to the matching buggy reference handler, the finding disappears on rerun. A snippet that fails U10 doesn't ship.

**FND-11 (params, the only variable parts of finding text).** Params are typed and closed: integers, D-numbers, pack identifiers, grammar-checked names and display values. Nothing else can fill a template.

| Param | Type | Used by |
| ----- | ---- | ------- |
| `event_type` | pack event type string | all titles; LC-4, LC-5 |
| `status`, `statuses[]` | integer HTTP status | `server_error`, `non_2xx_on_valid`, `error_on_invalid`, INV-1 types |
| `transport` | `timeout` · `reset` | `server_error` |
| `latency_ms`, `max_other_ms`, `over_by_ms` | integer | `over_budget`, `cold_start` |
| `budget_ms`, `budget_label` | from the pack or `webhooktrap.yml` (BUD-1, BUD-2) | `over_budget` |
| `slow_count`, `valid_count` | integer | `over_budget` (LC-4, LC-5) |
| `d`, `d_ref` | D-numbers | every type; LC-1 |
| `timestamp_offset_s` | integer | `accepts_stale` |
| `tamper {path, from, to}` | JSON path from the pack's `tamper_paths` or TAMP-1 rule 2/3; `from`, `to` are the edited scalar of the capture body, which the server already holds | `accepts_tampered` |
| `probe` | probe name (`as:`, PV-9 grammar) | Level 2 types, `async_write`, `probe_gap` |
| `before`, `after`, `expected` | **display values** (PV-2, PV-5) | Level 2 types |
| `object_type` | pack object type | `probe_gap` |
| `framework` | `express` · `nextjs` · `laravel` · null (FND-9) | snippets |

Probe stderr, probe definitions and raw values have no param and therefore can't appear in any finding text, local or hosted (PV-6, PV-8). Locally, `before`/`after`/`expected` hold real values (sanitised, 200 characters); the hosted profile accepts only display values and the server validates them (PV-11).

---

## 7. Run status, scenario results and exit codes (A-4, A-5)

### 7.1 Delivery results

| Result | When |
| ------ | ---- |
| `pass` | Completed, no rule broken |
| `finding` (with severity) | Completed, offending evidence of a finding |
| `inconclusive` | Completed, but its judgement needed something missing (probe, reset, settle, simultaneity) |
| `unreachable` | No connection after TGT-5 |
| `sent, no result` | Sent, not completed when the run stopped. Not judged. |
| `not run` | Planned, never sent |

### 7.2 Scenario results

**SCN-1.** One of: `pass`, `finding`, `inconclusive`, `unreachable`, `n/a`, `not run`. Precedence inside a scenario: `finding` (any finding ≥ Medium with evidence in it) > `unreachable` > `inconclusive` > `pass`. A scenario whose only findings are Info is `pass` and shows the Info ("pass · F4 Info"). `n/a` carries its reason (PLAN-1, LATE-2, TAMP-3, SIGN-2, SIGN-4, DUP-2). The strip cell for `inconclusive` shows `— Inconclusive` (TT Run #1), never "pass + Info".

### 7.3 Run status (one table for CLI, CI, PR comment, UI, JSON and MCP)

**RUN-1.** Evaluate top to bottom; the first match is the status.

| # | Status | Condition | Label (UI, CLI, PR) | Reason codes |
| - | ------ | --------- | ------------------- | ------------ |
| 1 | **Couldn't start** | Nothing beyond Once was judged: a preflight failed, or ONCE-4, or ONCE-6 | "Couldn't start the run" + reason | `agent_too_old`, `target_not_allowed`, `target_unreachable`, `baseline_rejected`, `reset_failed`, `probe_unstable`, `needs_secret` |
| 2 | **Aborted** | Started, but the plan didn't finish | "Aborted" + reason | `agent_lost`, `user_abort`, `target_lost` (RUN-5), `reset_failed` (later reset), `time_limit` |
| 3 | **N findings** | At least one finding ≥ Medium | "{N} findings" where **N counts Critical + High + Medium only**; the counts line adds Info: "1 Critical · 1 High · 1 Medium · 1 Info" | — |
| 4 | **Inconclusive** | No finding ≥ Medium, and at least one scenario is `inconclusive` or `unreachable`, or every scenario is `n/a` | "Inconclusive" (CLI and CI: "Inconclusive (not passed)") | `needs_probe`, `needs_reset`, `probe_failed`, `not_simultaneous`, `redirected`, `unsettled`, `unreachable`, `nothing_judged` |
| 5 | **Passed** | Otherwise | "Passed" (+ "· {k} Info" when Info findings exist) | — |

**RUN-2.** Draft, Queued and Running are pre-final states and have no exit code.

**RUN-3 (Info doesn't count).** Info findings never make a run "N findings", and never change Passed into anything else. This matches `validation-plan.md` §3.1 ("Finding: anything flagged at Medium or above"). RD Run #3 becomes "3 findings" with the counts line "1 Critical · 1 High · 1 Medium · 1 Info".

**RUN-4.** The `order_dependent` flag (no reset hook) shows with any status, under the verdict, and adds the Info finding `order_dependent`.

**RUN-5 (target lost).** Three consecutive `unreachable` deliveries stop the run: Aborted, reason `target_lost`; the rest is `not run`. A single unreachable delivery (or two) doesn't stop the run: that delivery is `unreachable`, its scenario is `unreachable` unless it has a finding, and the run continues (UF's behaviour is kept for the isolated case).

**RUN-6 (completed work is kept).** In Aborted and Couldn't start, completed deliveries and findings already proven are kept and shown ("Partial findings"). They are numbered by FND-6.

### 7.4 Exit codes

**EXIT-1.** The exit code is a function of the run definition and the status, evaluated top to bottom:

| Exit | Condition |
| ---- | --------- |
| **3** | The run **definition** is invalid, detectable without touching the target or running any command: unknown flag, `webhooktrap.yml` syntax or schema error, BUD-2 violation, DEF-1, CAP-1, unresolvable probe template (PRB-4). Nothing is sent. |
| **2** | Status is Couldn't start or Aborted, **or** any scenario result is `unreachable`. The verdict is incomplete; proven findings are still printed. |
| **1** | Any finding with severity ≥ `--fail-on` (default `high`; values `critical`, `high`, `medium`, `never`). |
| **1** | Status is Inconclusive and `--fail-on-inconclusive` is set. |
| **0** | Otherwise (Passed, Inconclusive without the flag, findings below `--fail-on`). |

**EXIT-2.** The UI, the hosted run history and the PR comment use the same function: the PR comment title says "check failed" exactly when the exit code is not 0. A run started from the UI stores the exit code it would have had with the defaults.

**EXIT-3.** Ctrl-C (user abort) is Aborted, `user_abort`, exit 2. The CLI prints which deliveries completed.

**Why 2 wins over 1.** Exit 2 means "don't read this as a full verdict, rerun it". A run with a Critical finding that also lost the agent still fails CI either way (non-zero), and the log shows both.

### 7.5 The Passed line

**RUN-7.** Passed claims only what was checked: "{k} of {k} scenarios passed at Level 1{ + Level 2 ({probe names})}." Then "Not checked:" followed by every item that applies, in this order: "state, because there is no probe" (no probe) · "{object types} state, which no probe reads" (PRB-12 uncovered types) · "state between scenarios, because no reset ran" (no reset) · "side effects outside your database, such as emails". The last item is always present.

### 7.6 Provenance, re-check and visibility (decision 39, ADR-010 §3 and §9)

**RUN-8 (provenance on every run).** Every run view, owner and shared, and every machine output, says who executed the run and from where: executor `agent` (with the agent name), `ci` (with the CI token **name**), `server_public` or `server_allowlist` ("Webhook Trap server runner"); source `web`, `cli` or `ci`. Copy: "Run by maya-mbp from the web app", "Uploaded from CI (token github-actions)", "Run by the Webhook Trap server runner". No surface ever says "verified" about a run or a finding.

**RUN-9 (what the server re-checks).** The judge runs where the run executed. When a run document reaches the hosted server (agent final, CI upload), the server checks the schema, recomputes `counts` from `findings[]`, checks `exit_code` against status and `fail_on` (EXIT-1), and re-checks **Level 1 only**: status classes, latency against the budget, `over_budget` flags and scenario results that depend only on Level 1 observations. What the server does on a mismatch is A05's (contracts §5.7); the product never shows a document that failed these checks as a normal run. **Level 2 can't be re-checked on the server**, because it holds no raw probe values (PV-1, PV-8). Level 2 findings therefore show as the executor's result: the finding header reads "Level 2 · judged on {agent name}" (or "judged in CI"), and the run's provenance line (RUN-8) carries the rest. `webhooktrap rejudge` on the local run file stays the full re-check (JSON-4).

**RUN-10 (visibility and sharing).**
- A run is **private** by default: `/r/{runId}` is owner-only. Printing the run URL in a terminal, a CI log or a PR comment grants nobody access.
- Sharing creates a **share token**, separate from the run id: `/s/{token}`. Sharing again **rotates** the token; old links stop working. Unsharing deletes it.
- **CI uploads are private** whatever the project default, unless the command passes `--share`, which creates a share token and prints the share URL. The PR comment links to `/s/{token}` with `--share`, else to `/r/{runId}` (owner sign-in).
- What a shared view shows about probes follows PV-7 (§8.1.1).

**RUN-11 (server runs).** On the hosted service, a run executed by the server runner (no agent; public targets only) is available to **accounts only**, behind a kill switch; when the switch is off the run form doesn't offer the server target. Guests always run through the agent. On self-host, server runs to private targets need the per-user B′ permission granted by the admin. The judge rules are the same for every executor. Product reading, for A05 to confirm: a server run is **Level 1 only** (the runner has no access to the user's database, so no probe or reset runs), and on hosted it holds no signing secret, so SIGN-2 applies (timestamped providers show **Needs secret**).

---

## 8. Probe and reset (A-10)

### 8.1 One syntax

**PRB-1.** In `webhooktrap.yml`:

```yaml
target: http://localhost:3000/webhooks/stripe
database: env:DATABASE_URL        # only for sql: probes; an env var name, never a literal
probe:
  - as: orders.count
    sql: select count(*) from orders where stripe_invoice_id = '{{invoice.id}}'
  - as: subscriptions.status
    cmd: ./scripts/sub-status.sh {{subscription.id}}
  - as: ledger.total
    url: http://localhost:3000/_debug/ledger?invoice={{invoice.id}}
settle: 0ms                        # default 0ms; see PRB-5
probe_timeout: 5s                  # default 5s, max 30s
reset: npm run db:reset:webhooks   # optional; timeout 60s
```

**PRB-2 (value).** Each entry has a name (`as:`, required, unique, `[a-z0-9_.]+`, at most 48 characters, PV-9) and optionally `show:` (PV-5, §8.1.1), and exactly one of:
- `sql:` run by the agent against `database:` (Postgres, MySQL/MariaDB, SQLite). The value is every returned row, columns joined by a tab, rows joined by a newline.
- `cmd:` run through the shell. The value is stdout.
- `url:` a GET to a host that passes TGT-1/TGT-2. The value is the body of a 2xx response, at most 64 KiB.
The value is compared as a string after trimming trailing whitespace. A probe's reading is the tuple of all entries' values.

> **Constraint for A05 (PRB-2).** `sql:` needs built-in drivers for Postgres, MySQL/MariaDB and SQLite in the agent. If that isn't feasible for the MVP, `sql:` is dropped and only `cmd:` and `url:` remain; the RD/TT examples then become `cmd: psql "$DATABASE_URL" -Atc "…"`. A05 decides; A01 accepts either.

**PRB-3 (privacy). Replaced by PV-1 to PV-14** (A09's C-3 review, adopted by the PO in decision 38). The binding text is A09's; §8.1.1 restates the product-facing parts. Where they differ, PV wins.

#### 8.1.1 What leaves the agent (PV-1 to PV-14, product view)

**Judging.** Every Level 2 comparison runs on the agent from real values (PV-1). The server receives only outcomes per reading and entry (`changed`, `matches_expected`, `flagged`, `failed`, `unsettled`) and **display values** for the UI. It never derives a verdict from a display value.

**By default (no opt-in), a display value is one of (PV-2, PV-3, PV-4):**
- a **small count**, −9 999 to 9 999, plain ASCII digits, no leading zeros, no sign other than `-`, no decimals or separators: sent as is (`0`, `1`, `2`);
- a **code**: `#` + 6 lowercase hex characters, keyed by the probe name and the value with a random salt that is new for every run and never leaves the agent. Same code = same value **within one run**; codes don't link across runs and don't reveal length. If two different values of a run get the same code, the whole run switches to 12-hex codes;
- everything else (status strings, larger numbers, multi-row output) is a code.

**Opt-in, per entry, owner runs only (PV-5).** `show: number` lets numbers up to 12 digits with up to 4 decimals through; `show: value` lets short tokens (`[A-Za-z0-9_.:-]`, up to 32 characters, e.g. `past_due`) through. Both pass the scrub detector (§10.3 patterns, plus phone numbers without `+` and any configured signing secret); a hit falls back to a code with a local note that names the entry, never the value. `show:` is **ignored for guest sessions** (local note: "show: is ignored for guest sessions; sign in to use it"). Opted-in values appear on the owner's private run view only.

**Never leaves the agent (PV-8).** Raw values; probe definitions (`sql:`, `cmd:`, `url:` text); `database:`; resolved template text; stderr and error text; reset command and output; the salt; the settle, stability and async intermediate readings. Only the outcomes `probe_failed`, `unsettled`, `async_write`, `probe_unstable` and `reset_failed` go up. Probe **names** do go up and are visible (PV-9).

**One renderer (PV-6).** Finding text (FND-7a, FND-11), scenario readings (§7.2 strip line 3), delivery state cells, live timeline lines, PR comments, the shared view and hosted MCP are all rendered from structured fields that already hold display values. No string bound for the server is ever formatted from a raw value.

**Shared views (PV-7, RUN-10).** A shared run (`/s/{token}`) shows probe names, small counts and codes. Opted-in values show as codes unless the owner ticks **Include probe values** in the share dialog, which lists the entries concerned. Guest runs have no such tick.

**Guests.** Strict defaults with no opt-outs: `show:` ignored, no Include-probe-values tick, readings deleted at the guest session's 24 h expiry (A-13).

**Retention (PV-13).** Readings are part of the run record: deleted with the run (not hidden), guest runs at the 24 h expiry, never copied into analytics, telemetry, error reporting or info-level logs.

**Self-host (PV-12).** Admin setting `probe_display`: `strict` (default) · `opt_in_allowed` · `opt_in_disabled`. No setting allows unconstrained raw strings. The agent applies the stricter of the server's advertised policy and its local config. The hosted service is compiled to strict with owner opt-in and reads no configuration for it.

**Server enforcement (PV-11).** The hosted API validates every display value against these grammars; anything else is stored as `#invalid`, counted, and never logged.

**Lints (PV-9, PV-10), warnings only.** A probe name with 6 or more consecutive digits, or matching a pack id pattern: "Probe names are visible in the web app and on shared links." A `sql:` probe with no `{{…}}` template and no `WHERE`: "This probe reads the whole table. Its value can reveal your volume over time. Scope it with {{invoice.id}}."

**Local.** The CLI, the local run file and local `--json` always show real values (sanitised, first 200 characters).

#### 8.1.2 Disclosure wording (PV-14, exact strings)

| Where | Copy |
| ----- | ---- |
| Config confirmation (PRB-11) in the CLI, and the Level 2 setup screen | "Probe values stay on your machine. The web app shows counts up to 9 999 as numbers and other values as short codes such as #a1f3c0, which only show whether a value changed." Each entry is tagged `count`, `code` or `shown (show: value)` / `shown (show: number)`. |
| First hosted run with a probe (one CLI line) | Same sentence. |
| Share dialog | "People with the link see probe names and counts." Plus the Include probe values tick (PV-7) when an entry is opted in. |
| Tooltip on any `#…` code in a hosted view | "Code for a value that stays on your machine. Same code = same value within this run." |
| CE §2.7, PS, landing and docs (replaces "never leaves the machine") | "Probe values stay on your machine. Only small counts and codes reach the server." |

**PRB-4 (templates).** `{{T.id}}` resolves **once, at plan time, from the selection**, not from the current delivery, so every probe entry runs for every delivery. Resolution: the `id` of the first object of type `T` among the selected events (pack `objects` paths: for Stripe, `data.object` where `object == T`), else the first field named `T` holding an id (Stripe `payment_intent.invoice`). An unresolvable template is a definition error: exit 3, "Probe template {{x}} has no value in the selected events."

### 8.2 Probe failures

**PRB-5.** A reading is `probe_failed` when any entry exits non-zero, times out (`probe_timeout`), returns a non-2xx (`url:`), errors (`sql:`) or produces an empty value. The first 200 bytes of stderr or the error are shown locally only; the server receives `probe_failed` and nothing else (PV-8). A Level 2 judgement that needs a failed reading makes that part of the scenario `inconclusive` (`probe_failed`); Level 1 still applies. After **3 consecutive** failed readings, the probe is turned off for the rest of the run (note "Probe failed 3 times in a row; continuing at Level 1"), and every later Level 2 judgement is `inconclusive` (`probe_failed`).

### 8.3 Settle and async handlers

**PRB-6.** With `settle: 0ms` (default) the probe is read once, right after the response. With `settle: N` (N ≤ 30 s), the probe is read every 250 ms from the response until its value has been unchanged for N, up to N + 5 s; if it never settles, the reading is `unsettled` and Level 2 judgements on it are `inconclusive` (`unsettled`).

**PRB-7 (async check).** At the end of every scenario, 1 000 ms after its last reading and before the next reset, the probe is read once more. If that value differs from the last reading, the handler probably writes after it responds: the scenario's Level 2 part is `inconclusive` (`unsettled`), and the run gets the Info finding `async_write` ("{probe} changed 1 s after the last response. Your handler probably writes in a background job. Set settle: 2s in webhooktrap.yml."). This prevents a silent false pass on queue-based handlers. The settle polls (PRB-6) and the async re-read are intermediate readings: they stay on the agent, and only `unsettled` and the `async_write` finding go up (PV-8).

> **Constraint for A05 (PRB-6, PRB-7).** Polling every 250 ms ± 25 ms; settle and async waits through the scheduler port (virtual time in unit tests, A11 D-7).

### 8.4 Reset and stability

**PRB-8 (order of preflight).** Before the first delivery: reset (if configured) → stability check → Once.

**PRB-9 (reset).** Reset runs before **every** scenario, Once included. Timeout 60 s. Non-zero exit or timeout: before Once → Couldn't start, `reset_failed`; later → Aborted, `reset_failed`, completed work kept. Exit 2 in both. Without a reset: the `order_dependent` flag and Info finding; judgements that compare against `B_primary` or `B_final` (SHUF-3, BURST-4, RETRY-5 row 3) are `inconclusive` (`needs_reset`); relative judgements (TWICE-4, RETRY-5 rows 1–2, INV-1) still run.

**PRB-10 (stability check).** The probe is read twice, 500 ms apart, with nothing sent. Different values → Couldn't start, `probe_unstable`, exit 2 (the two readings stay on the agent, PV-8): "Your probe isn't stable: its value changed with no delivery. Remove time-based values such as updated_at or now()." (CE's own example `max(updated_at)` fails this check on purpose, see §16.)

**PRB-11.** Probe and reset commands that come from `webhooktrap.yml` are shown and confirmed once per (agent, file content hash) before they run (the prototype's `confirmConfigCommands` behaviour). Non-interactive runs need `--yes`. The confirmation also shows the PV-14 disclosure sentence and tags each entry `count`, `code` or `shown` (§8.1.2).

### 8.5 Expected state per delivery (closes A11 R38)

| Delivery | Expected reading | Flagged when |
| -------- | ---------------- | ------------ |
| Once (any) | none (records baselines) | never |
| Twice D_a | none | never |
| Twice D_b | `after(D_a)`, if D_a got 2xx | differs |
| Shuffled, each but last | none | never |
| Shuffled, last | `B_final` (needs reset) | differs |
| Late, Tampered, Unsigned | `before` (no change) | differs |
| Retry A1 | none; a change after a non-2xx is `partial_side_effect` | RETRY-5 |
| Retry A2 | RETRY-5 table | RETRY-5 |
| Burst | final only: `B_final` (or `B_primary` for one event) | differs |
| Slow budget | none | never |

The **State reading** component (`ux-timetable-v2.md` §1.2) shows `Expected {x} · got {y}` exactly on the flagged rows of this table. `{x}` and `{y}` are display values on every hosted surface (counts or codes, e.g. `Expected #a1f3c0 · got #9c0254`, with the PV-14 tooltip) and real values in the CLI. The flag comes from the agent's `flagged` outcome, never from comparing display values.

### 8.6 Probe gap (F4)

**PRB-12.** Each pack lists `status_events`: event types whose main effect is a status change, with their object type (Stripe: `customer.subscription.updated` → `subscription`, `invoice.payment_failed` → `invoice`, …). A probe entry **covers** an object type if it declares `covers: [subscription]`, or if its `as:` name's first segment, singularised, equals the object type (`subscriptions.status` covers `subscription`). The Info finding `probe_gap` is raised when Level 2 is on, Shuffled or Burst ran, and a selected event is in `status_events` with an uncovered object type. Evidence: the deliveries of that event in Shuffled and Burst, all `offending`. The same uncovered types feed the Passed line (RUN-7).

**PRB-12a (RD Run #3, settles A03's O-FL-3).** The uncovered event is #04 `customer.subscription.updated`. Shuffled sends it first (D6 = #04, D7 = #03, D8 = #01) and Burst numbers it last (BURST-1a: D15 = #04). So **F4 evidence = D6 · D15**, scenarios Shuffled and Burst. The rule is right; §16 change 2 ("F4 = D6–D8") was wrong and is corrected there. D7 and D8 are `invoice.paid` and `payment_intent.succeeded`, which aren't status events.

---

## 9. Signature diagnosis on captures (A-19)

This applies to the signature chip on captured deliveries (checked in the agent), not to scenario deliveries.

**SIG-1 (order).** Checks run in this order; the first that decides gives the state and reason.

| # | Check | Result |
| - | ----- | ------ |
| 1 | No pack recognises a signature header, and no header name matches `(^|-)(signature|sig|hmac)(-|$)` (case-insensitive) | **Unsigned** |
| 2 | A signature-like header exists but no pack claims it | **Unknown scheme** ("signature-like header ({name})") |
| 3 | Pack header present but malformed (missing `t=`/`v1=`, bad base64, wrong length) | **Invalid** · `header_malformed` |
| 4 | No agent connected | **Not checked** |
| 5 | Agent has no secret for the provider | **No secret** |
| 6 | Any signature in the header matches the stored body with any secret held for the provider | **Valid** (then check 7) |
| 7 | (Valid only) Signed timestamp vs **`received_at`** (not now) is outside the tolerance | **Invalid** · `timestamp_outside_tolerance` |
| 8 | Integrity checks fail (Content-Length ≠ stored length, invalid UTF-8, BOM present) | **Invalid** · `body_altered_before_capture` |
| 9 | A body variant matches with a held secret (variants: JSON re-serialised compact; with sorted keys; pretty-printed 2-space; `\uXXXX` escapes decoded; non-ASCII escaped; CRLF ↔ LF) | **Invalid** · `body_re_encoded` (names the variant) |
| 10 | None of the above | **Invalid** · `different_secret` |

**SIG-2 (the 7 checks).** The "N of 7 checks" list is exactly, in this order: Content-Length matches · valid UTF-8 · no BOM · no re-encoding detected (check 9 found no variant) · timestamp within tolerance (or "no signed timestamp" for GitHub/Shopify, counted as passed) · header well-formed · scheme known. RD #09's "7 of 7" uses this list.

**SIG-3.** The detail names each secret checked by its **env var name** and the agent, never the value and **never any part of it**: no last-4 hint reaches the server, the web app, shared views, machine output or MCP (PO-D, decision 39). Only the agent may print the last 4 characters, in its own terminal (proposed command `webhooktrap secrets list`, name for A05 to confirm under NAME-1), to help the person tell two secrets apart. The detail also never carries the expected signature or any prefix of it (it is computed with the secret). It never states facts the agent can't observe: RD #09's "Hook ID 512839104 was edited at 13:31 today" is removed (§16).

Replacement wording (hosted and CLI run views):

| Case | Copy |
| ---- | ---- |
| Valid | "Checked on maya-mbp with STRIPE_WEBHOOK_SECRET." |
| Valid, detail line | "v1 matched with STRIPE_WEBHOOK_SECRET on maya-mbp. Timestamp 0.2 s old, tolerance 300 s." |
| Invalid, one secret | "X-Hub-Signature-256 did not match. Checked 1 secret: GITHUB_WEBHOOK_SECRET on maya-mbp." |
| Invalid, several | "Checked 2 secrets on maya-mbp: STRIPE_WEBHOOK_SECRET, STRIPE_WEBHOOK_SECRET_OLD. None matched." |
| Two secrets look alike | "To see which secret this is, run webhooktrap secrets list on maya-mbp." |

**SIG-4.** Each reason has one fixture in the test suite.

---

## 10. Grouping, shape and fixtures (A-17, A-25)

### 10.1 Grouping

**GRP-1.** Each pack declares, per event type, the object id paths it yields: its own object id and referenced ids (`link_paths`). Stripe `invoice.paid` yields `data.object.id`, `data.object.subscription`, `data.object.payment_intent`, `data.object.customer`; `payment_intent.succeeded` yields `data.object.id`, `data.object.invoice`.

**GRP-2.** Deliveries of one provider that share any yielded id (excluding `customer`-type ids, which would merge everything a customer ever did) are in the same group, transitively. RD's #01–#04 group because #03's `invoice` is #01's id.

**GRP-3.** Group label: the pack's anchor priority (Stripe: subscription > invoice > payment_intent > checkout session; Shopify: order; GitHub: repository). The label is the anchor object's id or display name (`Order #1042`).

**GRP-4 (no pack).** For providers without a pack (including generic Standard Webhooks), the group key is the first field in `data` named `id` or ending in `_id`, in document order. If none, the delivery is Ungrouped.

**GRP-5 (cross-provider).** Not in the MVP. RD's "Order #1042" cross-provider group (Shopify + Parcelbird via `order_ref`) can't be produced by these rules: Parcelbird #08 and #12 form their own group `shp_1c263…` (GRP-4 on `shipment_id`). §16 lists the change.

### 10.2 Shape

**SHP-1.** A shape is the set of `(path, JSON type)` pairs of a JSON body. Types: object, array, string, number, boolean, null. Arrays are written `path[]`, and the element shape is the union of every element's shape. An empty array adds `path[]` with no element type.

**SHP-2.** The baseline for `(provider, event type, API version)` is the saved fixture's shape if one exists, else the **union** of the shapes of all earlier captures in the workspace. Stripe's `api_version` is part of the key.

**SHP-3.** Verdicts: **New shape (first capture)** when no baseline exists · **Shape differs** when the capture adds a path, or adds a type at an existing path, not in the baseline; or removes a path present in **every** one of at least 5 baseline captures · **Shape matches** otherwise. The verdict lists the paths. Optional fields that come and go don't trigger "differs".

**SHP-4.** "Drifted" on fixtures = a new capture of that event type has "Shape differs" against the fixture.

### 10.3 Scrubbing (A-25)

**SCR-1.** Scrubbing runs on "Save as fixtures", in the agent, before anything is written. Rules come from two sources: the pack's field paths (Stripe: `customer_email`, `customer_name`, `customer_address.*`, `hosted_invoice_url`, `invoice_pdf`, `receipt_url`, `billing_details.*`, `card.fingerprint`, `card.last4`, …) and generic **value patterns** applied to every string in the body:

| Pattern | Replacement |
| ------- | ----------- |
| Email address | `customer{n}@example.test` |
| Phone (`+` and 8–15 digits) | `+1555555{nnnn}` |
| Person-name fields (`name`, `first_name`, `last_name`, `*_name` under customer/billing/shipping) | `Test Customer {n}` |
| URL with a path segment of ≥ 16 random-looking characters | same scheme, host's last label → `.test`, path → `/redacted` |
| Known secret prefixes: `sk_live_`, `sk_test_`, `rk_`, `whsec_`, `ghp_`, `gho_`, `github_pat_`, `shpat_`, `shpss_`, `xox[abpr]-`, `AKIA`, JWT (`eyJ….eyJ…`), `-----BEGIN` | **block** (never auto-replaced; SCR-4) |
| IPv4/IPv6 addresses | `192.0.2.{n}` / `2001:db8::{n}` |
| Card fingerprint / last4 | `fp_redacted{n}` / `4242` |

**SCR-2.** Ids that match the pack's id patterns (Stripe `^(evt|in|pi|sub|cus|ch|pm|price|il|si|prod|cs)_[A-Za-z0-9]+$`) are kept: scenarios, templates and grouping need them.

**SCR-3.** Replacement is **consistent across all files of one save**: the same original value always gets the same replacement, so relations between events survive. Headers are dropped except an allow-list (`Content-Type`, `User-Agent`, the pack's event-id and topic headers); signature headers are never stored.

**SCR-4.** After scrubbing, the detector runs again on the output. Any residual match of a value pattern, any blocked secret prefix, or any configured signing secret in any encoding (raw, base64, hex, URL-encoded; only for secrets ≥ 16 characters) **blocks the save** and lists the JSON paths. The user can keep a path with `scrub: keep: [path]` in `webhooktrap.yml`; the preview then shows it as kept, with a warning.

**SCR-5.** Fixtures are **always re-signed at replay** (scrubbing breaks the original signature; the fixture file records `signature: resign`). A fixture can't be replayed without a secret for timestamped providers (SIGN-2).

**SCR-6.** The preview lists every file and every replaced path with before → after (before values local only).

---

## 11. Performance targets (A-16)

All targets are product bounds. **Reference environment** (A05/A10 define it exactly): the self-host reference Compose stack on a 2 vCPU / 4 GB VM, Redis required (decision 32); the viewer's browser on a separate machine with 50 ms RTT to the server; the agent on a machine with 50 ms RTT to the server and the target on loopback.

| ID | Target | Measured from → to | Bound | Load |
| -- | ------ | ------------------ | ----- | ---- |
| PERF-1 | Arrival on screen | Ingest request fully received (server clock) → row in the viewer's DOM (client clock, offset-corrected) | p95 < 1 s, p99 < 2 s | 10 deliveries/s sustained for 60 s on one capture URL, 50 concurrent viewers of that URL, 200 other active capture URLs at 1 delivery/s |
| PERF-2 | Signature verdict on a new row | Row in DOM → verdict chip final (agent connected) | p95 < 1 s; "Checking…" only after 300 ms (UF) | Same as PERF-1 |
| PERF-3 | Run start | Run click → agent `accepted` event in the UI | p95 < 1 s; "Queued" only after 300 ms (UF) | 20 runs starting per minute across the instance |
| PERF-4 | Timeline row update | Agent records a completed delivery → row updated in the UI | p95 < 500 ms | 1 run, 16 deliveries, 10 viewers |
| PERF-5 | Engine overhead | Per delivery, excluding handler latency, scripted gaps, probe and reset time | p95 ≤ 50 ms | Agent on a 4-core laptop-class machine |
| PERF-6 | Default first run duration | Run start → verdict, 3 Stripe events, 8 scenarios, handler at 200 ms, probe at 50 ms, reset at 1 s | ≤ 60 s | — |
| PERF-7 | First event / first verdict | Landing → first delivery visible / → first run verdict, scripted journey on a clean VM (macOS, Ubuntu, WSL) | < 60 s / < 5 min | Scripted, not user-measured (A11 U5) |
| PERF-8 | Agent install and pair | Command pasted (10-symbol code, `XXXXX-XXXXX`) → "Connected", **excluding** the time the person takes to answer the `[y/N]` join confirmation | < 30 s on a 50 Mbit/s link | — |

**PERF-9.** Performance tests run against the reference environment only; a target missed elsewhere is reported, not failed.

---

## 12. Naming (A-20)

**NAME-1 (command).** The CLI and agent command is **`webhooktrap`**, one binary with subcommands (`webhooktrap pair`, `webhooktrap prove`, `webhooktrap test`, `webhooktrap show F1`, `webhooktrap targets add`). **Not `trap`:** `trap` is a POSIX shell builtin in bash, zsh, dash and sh, and builtins run before anything on `PATH`, so `trap prove …` would call the shell's signal handler and fail. No short alias ships (`wt` is Windows Terminal). The npm package stays `@webhooktrap/agent` with bin `webhooktrap`.

**NAME-2 (config).** `webhooktrap.yml` at the repository root. Local state in `.webhooktrap/` (git-ignored with a `*` `.gitignore`, as in the prototype). Fixtures in `webhooktrap/fixtures/<provider>/<event type>.json` (committed), so they don't collide with an existing `fixtures/` directory.

**NAME-3 (headers).** `Webhooktrap-Run`, `Webhooktrap-Delivery`, `Webhooktrap-Scenario`, `Webhooktrap-Dry-Run: 1`. No `X-` prefix (RFC 6648). Environment variables `WEBHOOKTRAP_*`.

**NAME-4 (side-effect warning).** Shown before the first run on a target whose plan sends any event id more than once in the run, which is every run with more than Once, because every scenario re-sends the primary event. It is acknowledged once per (agent, target) and remembered on the agent. Non-interactive runs print it as one header line and need `--yes` the first time. Copy: "This run sends the same events several times. If your handler sends emails or charges cards, honour the Webhooktrap-Dry-Run header first."

**NAME-5 (brand).** Q12 is pending. Every product name derives from the single token `webhooktrap`. If the brand changes, renaming is a mechanical substitution of that token, and the golden files are regenerated. The brand name in copy stays "Webhook Trap".

---

## 13. Machine output content (A-24)

The schema itself (`run.schema.json`, versioned) is A05's. This section fixes the **content**. `--json`, the MCP tools and "Copy as JSON" return the same objects; "Copy finding as JSON" is one `findings[]` element.

**Two profiles (ADR-010 §11, contracts §5.7).** The document carries `profile: local | hosted`.
- **local**: what the agent writes to its run file and prints with `--json`, and what local MCP returns. Real probe values (sanitised, 200 characters) and rendered prose.
- **hosted**: what the agent or CI uploads, and what the hosted API, hosted MCP, "Copy as JSON" in the web app and `/s/{token}.json` serve. Display values only (PV-2, PV-5) and **no prose field**: the server renders text from type + pack version + params (FND-7a) and adds it to what it serves, never to what it accepts.

| Object | Fields (both profiles unless marked) |
| ------ | ------------------------------------ |
| document | `schema_version`, `profile`, `run`, `events[]`, `scenarios[]`, `deliveries[]`, `findings[]`, `counts`, `not_checked[]`, `warnings[]` |
| `run` | `id`, `status` (RUN-1), `status_reason`, `exit_code`, `fail_on`, `fail_on_inconclusive`, `started_at`, `finished_at`, `duration_ms`, `seed`, `plan_hash`, `provider`, `pack {id, version}`, `budget {ms, source, label}`, `target {scheme, host, port, path}`, `provenance {executor, source, agent_name or ci_token_name or runner}` (RUN-8), `visibility` (`private` · `link`, RUN-10; served only), `agent {name, version}`, `framework {name, detected_from}`, `levels` (`[1]` or `[1, 2]`), `probe_entries[{name, display_mode: count · code · opted_number · opted_value}]`, `probe_display_policy` (as applied), `label_bits` (24 or 48, PV-4), `reset_configured`, `order_dependent`, `side_effects_acknowledged` |
| `events[]` | `ref` (capture id or fixture path), `provider_event_id`, `event_type`, `capture_order`, `body_sha256`, `primary` |
| `scenarios[]` | `name`, `result` (SCN-1), `reason`, `deliveries[]` (D-numbers), `findings[]` (F-ids, final only, FND-6a), `reading {kind, probe, before, after}` with display values in hosted (the strip's line 3 is rendered from it, PV-6); local also adds `reading_text` |
| `deliveries[]` | `n`, `scenario`, `event_ref`, `mode` (`normal`, `duplicate`, `retry`, `late`, `tampered`, `unsigned`), `offset_ms`, `signing {kind: resigned, original, original_body_fresh_ts, none; timestamp_offset_s}`, `tamper {path, from, to}` or null, `status` or null, `transport` (`timeout`, `reset`, `refused`, `dns`, `tls`) or null, `latency_ms` or null, `over_budget`, `response {content_type, body_sha256, body_bytes}`, `state {readings[{name, changed, matches_expected, flagged, display {before, after, expected}}], failed, unsettled}`; local also adds raw `before`, `after`, `expected` per reading; `result` (§7.1), `completed` |
| `findings[]` | `id` (final only), `type`, `fingerprint`, `severity`, `level`, `event_type`, `scenarios[]`, `evidence[{d, role}]`, `likely_cause_rule` or null, `framework` or null, `params` (FND-11), `measured` (typed: `{status, latency_ms, budget_ms, over_by_ms, before, after, expected}` as applicable; probe fields are display values in hosted). **Local and served only, never uploaded:** `title`, `what`, `why`, `citation {label, url}` (served: chosen by the server from the pack), `fix {text, snippet}`, `likely_cause_text` |
| `counts` | `critical`, `high`, `medium`, `info`, `findings` (= critical + high + medium); recomputed by the server (RUN-9) |

**JSON-1.** Integers are raw (no separators). Times are RFC 3339 UTC with milliseconds. Probe values follow PV-1 to PV-14 (§8.1.1): local has real values; the hosted profile has display values only, and every string derived from a probe value is rendered from display values (PV-6).

**JSON-2.** No machine output, in either profile, contains: a secret or any part of one (including a last-4 hint, SIG-3); a signature header value or an expected signature; body content (bodies are referenced by SHA-256); the probe label salt. The hosted profile also never contains probe definitions, `database:`, resolved template text, stderr, reset output, intermediate readings (PV-8) or finding prose.

**JSON-3 (MCP, MVP).** `list_events`, `get_event`, `plan_run` (returns the plan and delivery count without sending), `run_scenarios`, `get_run`, `get_finding`. Each returns the fragments above, in the local profile from a local MCP server and in the hosted profile (with server-rendered text) from the hosted one. `run_scenarios` from MCP obeys NAME-4: it refuses until the side-effect warning has been acknowledged for that target by a person.

**JSON-4 (rejudge).** `webhooktrap rejudge <run.json>` recomputes the verdict from the stored plan and observations; the output must be byte-identical to the original `findings`, `scenarios` and `run.status` (A11 D-1, test 40). Rejudge needs the **local** profile: a hosted document has no raw probe values, so only its Level 1 part can be recomputed (RUN-9).

---

## 14. PO decisions (A-7, A-13, A-18)

**Decided (decision 35): A-7, A-13 and A-18 were approved as recommended (★).** The options stay below as the record. §14.2 adds what later decisions (38, 39) changed for guests.

### 14.1 A-7 · MVP scenarios and levels

| Option | Scenarios | For | Against |
| ------ | --------- | --- | ------- |
| A | The 8 in RD §5 | Matches every Phase 2 frame (8-cell strip) | Misses "verification only when the header is present", a common pattern in hand-written GitHub and custom HMAC handlers |
| **B ★** | The 8 + **Unsigned** | Unsigned costs one delivery and reuses Tampered's oracle (INV-1); Critical-class bug; no new mechanics | The strip becomes 9 cells (≈ 111 px each at 1 000 px): A03/A04 rework |
| C | The prototype's 6 (Once, Twice, Shuffled, Late, Tampered, Slow budget) | Smallest engine | Drops Retry after error and Burst, which are the "providers really do this" story (CE §2.3) |

**Recommendation: B.** Also, for every option: **Level 3 (test command) and sequence packs are post-MVP** and are not drawn in Phase 2. Default selection: all MVP scenarios checked (a first run of about 45–60 s, PERF-6); revisit with proxy-validation run durations (A11 U9).

### 14.2 A-13 · Guest quotas and what counts

| Item | Options | Recommendation and reason |
| ---- | ------- | ------------------------- |
| Retention | (a) 24 h from URL creation · (b) 24 h sliding from last activity, max 72 h | **(a)**: one clock, simple copy ("expires in 23 h 49 m"), easy to test with a fake clock |
| Stored deliveries | 50 · **100** · 500 | **100**, checked against 1.x usage data (A11 U8: p90 deliveries per anonymous inbox in 24 h). Delivery 101 onward is answered exactly like a stored one, not stored. |
| What counts as a run | (a) accepted by the agent · **(b) produced a verdict**: got past Once (not Couldn't start, including baseline rejected) · (c) completed | **(b)**: a wrong secret is the most likely guest mistake and must not burn the quota. Aborted runs count if at least one scenario after Once completed. |
| Runs per guest session | 3 · 5 · **10** | **10**: runs execute on the user's machine, so server cost is small (timeline events only). 3 leaves no room for run → fix → rerun → rerun. |
| Agents | **1 at a time; pairing a second replaces the first** · 1, refuse a second | Replace: a guest who re-pairs from a new terminal shouldn't be stuck. Since decision 39 (PO-E): the replacement happens only after the person answers `y` to the join prompt in the new agent's terminal; a guest workspace always prompts, and a non-interactive agent can't pair into one |
| Probes (decision 38) | — | Strict PV defaults: `show:` ignored, no "Include probe values" when sharing (PV-5, PV-7). Readings go with the session at 24 h (PV-13) |
| Server runs (decision 39) | — | None: guests run through the agent (PO-B, RUN-11) |
| Claim on sign-in (P11, decision 39) | — | The run records move to the account unchanged (readings are already display values). The paired agent becomes `needs_reconfirm` and asks `[y/N]` again in its terminal before it takes another job |

### 14.3 A-18 · MVP providers

| Option | Providers with signing and packs | For | Against |
| ------ | -------------------------------- | --- | ------- |
| 1 | Stripe only | One pack to verify | Weak story; proxy validation would have one provider |
| **2 ★** | Stripe, GitHub, Shopify, Standard Webhooks (scheme pack, generic budget) | Q7 leaning; the prototype already signs all four; Standard Webhooks covers AI platforms | Four packs to verify (DUP-2 citations) |
| 3 | Option 2 + Twilio and Slack | Matches the landing strip | Twilio needs "public URL as seen" (ADR-005 amendment); Slack uses form bodies (Tampered n/a by TAMP-3) |

**Recommendation: 2.** Twilio and Slack appear as "coming", with **no budget line anywhere in the product** until their pack ships. Providers without a pack are supported for capture and replay as "Unknown" (SIGN-2: sent as captured; Late, Tampered and Unsigned n/a; generic budget).

---

## 15. Proxy validation protocol (decision 31)

**Ask to the PO: pre-register this section (copy it into the decisions log, with the salt and snapshot date filled in) before any repository is downloaded.** Changing anything afterwards needs a PO note with the reason, before results are discussed (the same freezing rule as `validation-plan.md` §3).

### 15.1 What it measures

H1 (finding rate) and H2 (Level 1 share, false-positive share) from `validation-plan.md` §3.2, on real open-source handlers instead of participants. It cannot measure H3 (install), H4 (CI habit) or the user's own judgement; those stay unvalidated (decision 31 accepted that risk).

### 15.2 Repository selection rule (criteria, not a list)

1. **Source.** GitHub code search, run once on a pre-registered **snapshot date**, with pre-registered queries per provider that find handler code using the official verifier or the documented header: Stripe (`constructEvent`, `construct_event`, `Webhook::constructEvent`), GitHub (`X-Hub-Signature-256`, `@octokit/webhooks`), Shopify (`X-Shopify-Hmac-Sha256`), Standard Webhooks (`standardwebhooks`, `svix` verify). The raw result lists are saved.
2. **Eligibility.** Public; OSI-approved licence; not a fork; not archived; a commit in the 18 months before the snapshot; at least 50 stars; the handler is part of an application (it writes state or calls services), not a library.
3. **Two strata, reported separately.** *Main*: applications meeting rule 2. *Samples*: official provider sample repos and tutorials. Only the main stratum decides.
4. **Order.** Candidates are sorted by SHA-256 of `owner/repo` + a pre-registered random salt, and taken in that order. Nobody chooses.
5. **Quota per provider in the main stratum:** Stripe 6, GitHub 4, Shopify 3, Standard Webhooks 2 (15 in total). If a provider runs out of candidates, its remaining slots go to Stripe.
6. **Runnable within the timebox.** A candidate is run if it starts locally within **60 minutes** of setup by one person, using only test-mode or sandbox credentials and no paid service. Otherwise it is skipped, with the reason logged. Skipped candidates are never revisited.
7. **PO approval of the list** (decision 31: downloading third-party code needs it). The rule produces the list; the PO approves it before download.
8. **Safety.** Each repository runs in a disposable VM or container, network limited to package installation and loopback, test keys only, destroyed after the run.

### 15.3 Procedure per repository

1. Run the default MVP scenario set at **Level 1** first (no probe). Save the run file.
2. Write a probe (timebox **20 minutes**; record the minutes and whether one entry was enough, A11 U4) and a reset if feasible. Run again at Level 1 + Level 2.
3. Rerun any run with a finding once, to check it reproduces.
4. Findings are counted under **this document's rules**. The prototype's run files are re-judged against them (it still uses the 3× median rule and the old Twice Level 1 rule). Where the prototype can't run a scenario (Retry after error, Burst, Unsigned), the report says so per repository.

### 15.4 What counts as a real finding

Only findings ≥ Medium are classified (`validation-plan.md` §3.1). **A01 and A09 classify each one independently, blind to each other**, with this rubric:

| Criterion | Question | All must be yes for **Real** |
| --------- | -------- | --------------------------- |
| Reproducible | Did the rerun show the same finding? | yes |
| Production path | Does the delivery hit the code that runs in production, with the repository's default production configuration (not a test stub, not a dev-only bypass)? | yes |
| Provider-reachable | Would a documented provider behaviour (cited in the pack) trigger it in production? | yes |
| Not an artefact | Is it independent of the harness (cold start, local DB latency, missing local service)? | yes |

Other labels: **Known and accepted** (documented as intentional, e.g. a README says signature checks are off in dev) · **False positive** (the judge is wrong) · **Unsure**. Only **Real by both classifiers** counts as strict-real. Disagreements count as not real, are reported, and agreement (Cohen's κ) is reported. Each label records one line of reasoning per criterion.

**Secondary signal (reported, not decisive):** for each strict-real finding, an issue or PR is offered to the maintainers; acceptance is recorded at 2 and 4 weeks (A11 U1). **Critical findings (`accepts_tampered`, `accepts_unsigned`) are reported privately** (GitHub private vulnerability reporting or the project's security contact), never as a public issue.

### 15.5 Metrics and thresholds

- **Finding rate** = main-stratum repositories with ≥ 1 strict-real finding ÷ main-stratum repositories run. Reported as a count ("6 of 15") and a percentage.
- Also reported: Level 1-only rate, false-positive share of all classified findings, findings per scenario, probe minutes, median run duration, sample-stratum results.

| Outcome | Condition | What happens |
| ------- | --------- | ------------ |
| **Go** | Finding rate ≥ 40 % | Continue B as planned. |
| **Extend** | 20–39 % | Run the next **10** repositories from the ordered list (same rules). If still 20–39 %, the PO decides between "Go, probe-first" and pivot, using the false-positive share and the Level 1 share (`validation-plan.md` §4). |
| **Fall back to A** | < 20 % | Positioning A (the explaining debugger); proving scenarios become a feature inside A. Build order from decision 31 means capture, inspect and signature work is not wasted. |

Also: if the false-positive share is **> 35 %**, the judge rules are reviewed before any decision, whatever the rate.

### 15.6 Minimum sample

**15 main-stratum repositories run.** No decision on fewer than **12** (if the ordered list is exhausted first, the PO decides whether to widen rule 2 to ≥ 20 stars, pre-registered as a fallback now). With 15 repositories, one repository moves the rate by about 7 points; the 95 % interval around 40 % is roughly ± 25 points, so the result is a screen, not a measurement, and is reported that way.

---

## 16. Changes needed in other documents

For A03 (copy and screens) and each document's owner. This document does not edit them.

| # | File | Section | Change |
| - | ---- | ------- | ------ |
| 1 | `p1b-design/project/design/realistic-data.md` + `.json` | §1 budget table | Parcelbird (Standard Webhooks): 15 000 ms → **10 000 ms · default** (BUD-1). |
| 2 | same | §4 Run #3 | **Regenerate the run** under PLAN-1: Once sends #01, #03, #04 (D1–D3), so the run has **16 deliveries**: Once D1–D3, Twice D4–D5, Shuffled D6–D8, Late D9, Retry D10–D11, Tampered D12, Burst D13–D15, Slow budget D16. Findings keep their content: F1 = D12, F2 = D4 D5 · D10 D11, F3 = D10 · D16, **F4 = D6 · D15** (PRB-12a; revision 1 said D6–D8, which was wrong). Draft copy "14 deliveries" → "16 deliveries". Run #2 "during D3 of 14" → "of 16". |
| 3 | same | §4.1, §4.2 Twice and Retry offsets | D_b is sent 500 ms after D_a **completes** (TWICE-1), A2 2 000 ms after A1 completes (RETRY-1). Recompute `+ms`. |
| 4 | same | §4.1 Tampered row, §4.2 D10, §4.3 D10 | "original signature kept" → "signed for the original body"; the `Stripe-Signature` shown has a **fresh** timestamp (TAMP-2), not the capture's `t=1794405484`. |
| 5 | same | §4.2 Burst rows | Per-row state `—`; the scenario reading shows `0 → 1 (final)` (BURST-4). |
| 6 | same | §4 header, §4.6, §6 Run `findings` | "4 findings" → **"3 findings"**, counts line "1 Critical · 1 High · 1 Medium · 1 Info" (RUN-3). |
| 7 | same | §4.4 F3 | Likely cause (commit message) → LC-4 text. Measured line counts only valid `invoice.paid` deliveries (INV-2). |
| 8 | same | §2 #09 detail | Remove "Hook ID 512839104 was edited at 13:31 today" (SIG-3). Keep "7 of 7 checks" with the SIG-2 list. Also remove "Expected sha256=5c1e09…, received sha256=4d2f4b…" (SIG-3, JSON-2). |
| 9 | same | §2 groups | Order #1042 becomes Shopify only (#05, #06); Parcelbird #08, #12 form group `shp_1c263…` (GRP-5). |
| 10 | same | §6 Run states | Add `couldnt_start` reasons `baseline_rejected`, `probe_unstable`, `reset_failed`, `needs_secret`, `target_unreachable` (RUN-1) with copy from ONCE-4, PRB-9, PRB-10. Add Aborted `target_lost`, `reset_failed`, `time_limit`. |
| 11 | same | §6 Run `needs_secret` | Stripe can't run "only Once and Slow budget" without a secret (SIGN-2). New helper: "Stripe signs a timestamp, so nothing can be replayed without STRIPE_WEBHOOK_SECRET on maya-mbp." |
| 12 | same | §6 Run `side_effects` | NAME-4 copy; it applies to every run beyond Once, not only "Twice and Burst". |
| 13 | same | everywhere | `trap` → `webhooktrap`, `trap.yml` → `webhooktrap.yml`, `X-Trap-*` → `Webhooktrap-*` (NAME-1 to 3). Fixture paths → `webhooktrap/fixtures/…`. |
| 14 | same | §5 catalogue | Shuffled "Related events in reverse order" stays. Retry after error "what" → RETRY-6 copy. Add Unsigned if the PO picks A-7 option B. |
| 15 | `p1b-design/project/design/ux-timetable-v2.md` | §1.1 strip | Run #3 readings follow change 2. Add cell states `n/a` (with reason), `unreachable`, `— Inconclusive` reasons. 9 cells if A-7 option B. |
| 16 | same | §1.2 State reading | Flagged rows = §8.5 table. Burst shows only a final reading. Add "Not measured: probe failed" and "unsettled" cases. |
| 17 | same | §1.4 rules table | Exit codes → EXIT-1 (adds: unreachable scenario → 2; config definition → 3). `--fail-on` values `critical`, `high`, `medium`, `never`. Names → NAME-1. |
| 18 | same | §1.4.1 live | `new finding` lines print the title without the F-number (FND-6). |
| 19 | same | §1.4.2–1.4.6 | Regenerate golden samples from change 2 and RUN-3 ("3 findings · 1 Critical · 1 High · 1 Medium · 1 Info"; PR comment title "3 findings · check failed"). |
| 20 | same | §7 open points | Close 1 (BUD-2: `budget:` is MVP), 3 (BUD-7: stays Medium). Point 5 stays `--fail-on high`. |
| 21 | `p1b-design/project/design/ux-foundations.md` | §5.1 | Add status **Couldn't start** (RUN-1 row 1) to the fixed vocabulary. |
| 22 | same | §5.2 | Meanings → SEV-1 consequence classes. Medium example title "Exceeded Stripe's response budget" → "Over the 10 s budget for Stripe" (PB §4). |
| 23 | same | Screen 4 states, agent table | "Target unreachable mid-run … ends Inconclusive" → RUN-5 (isolated: delivery and scenario `unreachable`, exit 2; 3 in a row: Aborted `target_lost`). Add Couldn't start variants. Headers row `X-Trap-*` → `Webhooktrap-*`. |
| 24 | same | Screen 4 draft step 4 | Once is locked on (PLAN-2). Flag "May repeat side effects" → NAME-4 rule. |
| 25 | `p1b-product/project/product/core-experience.md` | §2.3 catalogue | Once "every selected event"; Twice "500 ms after the first completes"; Shuffled "reverse order"; Burst "released together (≤ 20 ms)"; Retry after error per RETRY-1. |
| 26 | same | §2.4 Level 2 | Probe syntax → PRB-1; replace the `max(updated_at)` example (it fails PRB-10). Level 3 marked post-MVP (pending A-7). |
| 27 | same | §2.6 Finding row, §2.7 "never on the hosted server" | Severity meanings → SEV-1. §2.7 rewritten in revision 2 with the PV-14 sentence (done). |
| 28 | `p1b-product/project/product/validation/prototype-spec.md` | §3.3, §3.4, §5 | Mark as superseded for judging by this document: 3× median rule (BUD-5), Twice Level 1 Medium (§5.4), Late "raise to High" (§5.3), `proving.yml`/`X-Proving-Dry-Run` (NAME), reset failure (PRB-9). |
| 29 | `p1b-design/project/design/provider-budgets.md` | §1 | Add a "Tolerance" column (Stripe 300 s, Standard Webhooks 300 s, GitHub/Shopify none) and a row note: Standard Webhooks pack uses the generic 10 000 ms default. Add the DUP-1 verification rows (§2.3). |
| 30 | `p1b-product/project/product/core-experience.md` §2.8, landing frames | Providers strip | Twilio and Slack shown as "coming", no budget (§14.3, A-18 decided in decision 35). |
| 31 | `spikes/proving-cli` (A06, only if the PO wants it before proxy validation) | `runner.go` `slowBudget`, `eval.go` Twice | Drop the 3× median condition; apply GEN-2 on the duplicate; keep `X-GitHub-Delivery` on duplicates (DUP-1, once verified). Otherwise re-judge offline (§15.3 step 4). |
| 32 | `p1b-design/project/design/realistic-data.md` + `.json` | §1 examples, §2 every signature detail, §3 table `valid` | Remove every secret last-4 hint (`…3f9a`, `…7c4d`, `…b21e`) from anything shown in the web app: SIG-3 wording. The agent terminal frames may keep them. |
| 33 | same | §4.4 F4 | Evidence D6 (offending) · D15 (offending), scenarios Shuffled · Burst (PRB-12a). Shuffled strip cell keeps "+ Info F4"; Burst strip cell gains "· F4 Info". |
| 34 | same | Run #3 state readings, findings | Every probe value shown in a hosted frame is a PV-2 display value. `orders.count` 0/1/2 stays as is. Any non-count value (a status) is a code (`#a1f3c0`) unless the frame states `show: value` and is the owner's private view. Shared and guest frames: codes. |
| 35 | same | §1 examples, §6 Run states | Add provenance lines (RUN-8) to every run frame; CI-uploaded run is private unless `--share` (RUN-10); share URL `/s/{token}`, not the run URL. CI token shown by **name** only ("github-actions"), no `…9f2c`. |
| 36 | `p1b-design/project/design/phase2/*` (A03) and `ux-timetable-v2.md` | config confirmation, Level 2 setup, share dialog, State reading tooltip, finding header | PV-14 copy (§8.1.2), exactly. Level 2 finding header "Level 2 · judged on {agent}" (RUN-9). No "verified" anywhere. Live findings without F-numbers on every surface (FND-6a). |
| 37 | same | pairing frames | 10-symbol code `XXXXX-XXXXX`; the agent prints the server origin, the workspace label and "Join? Only continue if you created this code in your own browser just now. [y/N]"; claim triggers the same prompt again (`needs_reconfirm`). Web copy while waiting: "Confirm in your terminal". |
| 38 | `p1b-design/project/design/ux-timetable-v2.md` | §1.4.2–1.4.6 golden samples | Text derived only from type + pack + params (FND-7a, FND-11). The golden test runs with canary probe values (A09 T6). |

---

## 17. Conflicts not resolved here

| # | Conflict | Why it stays open | Who |
| - | -------- | ----------------- | --- |
| ~~C-1~~ | ~~Probe values on the hosted server.~~ **Closed** by decision 38: PV-1 to PV-14 replace PRB-3 (§8.1.1); CE §2.7 rewritten (PV-14). | — | — |
| C-2 | Command name. `trap` can't work (shell builtin); `webhooktrap` is chosen provisionally. | Q12 (brand) is pending. | A02 / PO |
| C-3 | Duplicate headers for GitHub and Shopify (DUP-1). The prototype makes a new `X-GitHub-Delivery`; this spec says keep it. | Needs docs verification with citations before the packs ship. | A01, before Phase 4 |
| C-4 | `sql:` probes need DB drivers in the agent (PRB-2). | Engine feasibility. | A05 |
| C-5 | Exit 2 over exit 1 when a run is incomplete but has proven findings (EXIT-1). TT and A11 agree; some CI users may prefer 1. | Low risk; flagged for the PO. | PO |
| C-6 | Once sending every event changes RD Run #3 to 16 deliveries, which moves every D-number in frames and golden files (change 2). | Necessary for the Shuffled and Burst baselines; cost is A03/A04 regeneration. | A03 |
| C-7 | Probe name grammar. PV-9 and PRB-2 say `[a-z0-9_.]`, max 48; contracts §5.11 accepts `[A-Za-z0-9_.:-]`, max 48. | The stricter one should win; A01 keeps PV-9. | A05 |
| C-8 | Server runs (RUN-11) read as Level 1 only, with no signing secret on hosted. ADR-010 §12 doesn't state it. | Product reading, needs the architecture to confirm. | A05 |

---

---

## 18. Change table (revision 2, 2026-11-14)

| # | Section | Change | Source |
| - | ------- | ------ | ------ |
| 1 | Header, §0.1 | Revision 2 line; A-10 and A-24 rows mention PV and the two profiles | decisions 35–41 |
| 2 | §3.7 BURST-1a | New: Burst D-numbers follow capture order | A03 O-FL-3 |
| 3 | §4.2 BUD-6 | `cold_start` text uses named params | ADR-010 §3 |
| 4 | §6.3 FND-6, FND-6a | No F-number on any surface until the run ends; A03's live-row choice confirmed and extended | A03 AC-3.7, contracts §5.5 |
| 5 | §6.4 FND-7a, FND-8, FND-11 | Finding text = pack template filled from type, pack data and typed params (display values); agent sends rule ids, no prose | ADR-010 §3, TM-16, PV-6 |
| 6 | §7.6 RUN-8 to RUN-11 | New: provenance on every run; server re-checks Level 1 only, Level 2 shown as "judged on {agent}"; private by default, share token `/s/{token}` rotating on reshare, CI private unless `--share`; server runs accounts only behind a kill switch, B′ on self-host | decision 39 (PO-A, PO-B, PO-C, PO-G), ADR-010 §3, §9, §12 |
| 7 | §8.1 PRB-2 | `as:` max 48; `show:` key | PV-5, PV-9 |
| 8 | §8.1 PRB-3, §8.1.1, §8.1.2 | PRB-3 replaced by PV-1 to PV-14; product restatement (default display, opt-in, never-uploaded set, shared views, guests, retention, self-host, lints); exact PV-14 disclosure strings | decision 38 |
| 9 | §8.2 PRB-5, §8.3 PRB-7, §8.4 PRB-10, PRB-11 | stderr, intermediate and stability readings stay local; confirmation shows the disclosure | PV-8, PV-14 |
| 10 | §8.5 | State reading uses display values on hosted surfaces; flag from the agent's outcome | PV-1, PV-6 |
| 11 | §8.6 PRB-12a | RD Run #3 F4 evidence = D6 · D15; the rule stands, the old data line was wrong | A03 O-FL-3 |
| 12 | §9 SIG-3 | No last-4 hint or expected signature outside the agent's terminal; replacement wording | decision 39 (PO-D), S-8 |
| 13 | §11 PERF-8 | 10-symbol code; excludes the `[y/N]` answer time | decision 39 (PO-E, PO-F) |
| 14 | §13, JSON-1 to JSON-4 | Local and hosted profiles; hosted has no prose and display values only; `provenance`, `visibility`, `probe_entries`, `probe_display_policy`, `label_bits`; structured `scenarios[].reading`; `findings[].params`; JSON-2 adds last-4, expected signature, salt; rejudge needs the local profile | ADR-010 §11, contracts §3.3, §5.5–5.9 |
| 15 | §14 | A-7, A-13, A-18 marked decided; guest rows for pairing confirmation, probes, server runs, claim reconfirmation | decisions 35, 38, 39 |
| 16 | §16 | Change 2 corrected (F4); 8 extended (expected signature); 27 done; 32–38 added for A03/A04 | this revision |
| 17 | §17 | C-1 closed; C-7 (probe name grammar) and C-8 (server runs Level 1 only) opened | decisions 38, 39 |
| 18 | `core-experience.md` §2.7 | "Never on the hosted server" rewritten so it is true under PV | PV-14 |

Not affected by decisions 40 and 41 (1.x retired, accounts kept): nothing in this document depends on 1.x.

*End. Status REVIEW_REQUIRED. Nothing here is approved or decided except what §14 records as decided by the PO.*
