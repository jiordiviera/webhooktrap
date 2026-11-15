# Gate 1 · Requirements testability review

**Author:** A11 (QA and verification) · **Date:** 2026-11-14 · **Status:** PROPOSED. This review is not an approval of anything.
**Scope:** Webhook Trap 2.0, concept B "Proving Ground", Timetable direction. Read-only review. No code was run.

**Documents reviewed (abbreviations used below)**

| Abbr. | File |
| ----- | ---- |
| VI | `.worktrees/p1b-product/project/product/vision.md` |
| CE | `.worktrees/p1b-product/project/product/core-experience.md` |
| CO | `.worktrees/p1b-product/project/product/concepts.md` (concept B) |
| POS | `.worktrees/p1b-product/project/product/positioning.md` |
| OQ | `.worktrees/p1b-product/project/product/open-questions.md` |
| PS | `.worktrees/p1b-product/project/product/validation/prototype-spec.md` |
| VP | `.worktrees/p1b-product/project/product/validation/validation-plan.md` (read only for the definition of a "real" finding) |
| UF | `.worktrees/p1b-design/project/design/ux-foundations.md` |
| TT | `.worktrees/p1b-design/project/design/ux-timetable-v2.md` |
| PB | `.worktrees/p1b-design/project/design/provider-budgets.md` |
| RD | `.worktrees/p1b-design/project/design/realistic-data.md` |
| CIMP | `project/architecture/concept-impact.md` |
| ADR-00x | `project/architecture/decisions/000x-*.md` (001, 003, 004, 005, 006, 007, 008 read) |

---

## 1. Summary verdict

### READY WITH CLARIFICATIONS for Phase 2 (UX and design system)

**Why it is not NOT READY.** For a pre-build product, the documents are unusually testable:

- RD fixes one complete dataset: 13 captures, a 14-delivery run, every status, latency and probe value, and every state label.
- TT §1.4 gives byte-level CLI, CI and PR outputs, with exit codes, an ASCII map and width rules. These can serve as golden files almost as they stand.
- PB gives sourced budgets and exact wording that is safe to show.
- UF lists every state per screen and gives concrete accessibility numbers.
- PS §9 already contains a buggy/fixed handler acceptance matrix and an SDK cross-verification criterion.

Phase 2 is UX work, and the UX work can start on this basis.

**Why it is not READY.** The *judge*, which is the product's core promise, is only partly specified:

- **Oracles are missing.** Three of the eight scenarios have no defined finding rule at all: Burst, Retry after error, and the "4xx trap".
- **Oracles are contradictory.** Shuffled, Slow budget, Late and Twice at Level 1 are defined differently in PS and RD/TT.
- **The severity model is internally inconsistent** (§3, A-2).
- **Run-level status and exit codes conflict** for "target unreachable" and "baseline rejected".
- **Probe semantics are unspecified**: digest comparison, flaky probes, async handlers, probe failure.
- **No document defines the determinism hooks** that make the engine testable: seed, clock and scheduler. The ADR that would hold them, "Local agent and client-side scenario engine", does not exist yet.

**Gating proposal (for the PO; A11 does not approve).**

- **During Phase 2:** close clarifications A-1 to A-9 and A-13. They change copy, states and screens that A03/A04 are drawing now.
- **Before Phase 4 engine work starts:** close the rest, plus the §4 determinism constraints, recorded in the new agent ADR (A05).
- **Without these closures,** A11 cannot write pass/fail acceptance tests for the judge, and Gate 3 ("A11 verifies states") would verify drawings against copy whose underlying rule is undefined.

---

## 2. Requirement table

**Testable:** **Y** = a pass/fail test can be written now · **P** = partly (some inputs, thresholds or outcomes missing) · **N** = no oracle.
**Levels:** U unit · C contract · I integration · E2E end-to-end (browser) · G CLI golden output · V visual regression · A accessibility (automated + manual) · P perf.
"→ A-n" points to §3.

### 2.1 Capture and inspect (shared by A and B)

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R1 | Public capture URL accepts any method and stores the raw body bytes, method, path, **raw query string**, ordered headers "where available", source IP, content type, timestamp and size (ADR-005; CE M2; UF Screen 3 Raw) | P | Byte-for-byte equality of the stored body with the sent body (SHA-256). Query string equality, including encoding and repeated keys. | I, C | Header order and casing are only "where the SAPI provides" (ADR-005). The oracle must say which header facts are guaranteed under PHP-FPM, and how the "flagged in capture metadata" flag is shown in the UI (UF Raw says "headers in received order, original casing"). → A-14 |
| R2 | Capture also stores "public URL as seen" and the raw request target (OQ ADR-005 ask; CIMP §3) | N | — | — | Proposed amendment only, not accepted. No definition of how scheme/host are derived behind proxies (trusted `X-Forwarded-*`?). Needed for Twilio re-signing (CE §2.9). |
| R3 | Body over the limit: metadata plus SHA-256 kept, body not kept; Prove disabled (UF Screen 2 "> 5 MB"; RD §6 `body_not_kept`; CE §2.9) | Y | 5 MB boundary. Test 5 MB − 1, exactly 5 MB and 5 MB + 1. The response to the provider is still 200. | I, E2E | Is "limit 5 MB" inclusive? → A-15. Guest and signed-in limits are the same? Unstated. |
| R4 | Arrival on screen < 1 s from provider POST (CE M2, §2.7; UF live behaviour) | P | Latency from ingest 2xx to row visible in the DOM. | P, E2E | No percentile, load or environment stated → A-16 |
| R5 | Signature verdict arrives with the row; if the agent check takes > 300 ms, show "Checking…" and update in place with no reflow (UF Screen 2) | Y | Fake agent with an injected delay of 299 ms and 301 ms. Assert the label sequence and no layout shift (CLS = 0 on the row). | E2E, V | — |
| R6 | Grouping by related object (UF §2.1; RD groups, incl. cross-provider Order #1042 via `order_ref`) | P | RD group membership as the expected output. | U | The grouping rules per provider (which field is the "object id") are not written down. The Parcelbird cross-provider link via `order_ref` is described for this example only. → A-17 |
| R7 | Shape verdict: matches / new (first capture) / differs from baseline (RD #11) | P | RD #11 field diff `cancellation_details.feedback`. | U | "Shape" is not defined: keys only? types? array element shapes? optional vs missing? Not testable beyond the single example. → A-17 |
| R8 | Live list never moves the selected row; "N new" pill when scrolled or hovered; hidden tab → "Catching up", insert without motion, clear after 4 s; one polite announcement at most every 5 s (UF Screen 2) | Y | Simulated arrivals, with assertions on scroll anchor, pill text and the aria-live message cadence. | E2E, A | — |
| R9 | Every capture-stream state is designed: waiting, loading, receiving, paused, catching up, stream lost, quota, expired, no results, body not kept, server error, offline (UF Screen 2; RD §6) | Y | RD §6 labels and helpers are exact strings. | E2E, V | RD `paused` copy is the "Catching up" text, while UF has separate Paused (manual) and Catching up states. → A-13 |
| R10 | Public ingest never leaks inbox existence (CLAUDE.md 1.x rule; RD `quota`: "New deliveries are still answered (your provider sees 200) but not stored") | P | Same status and body for unknown, expired and over-quota URLs. | I | The 2.0 docs do not restate this rule for expired guest URLs, or for the response body and status. → A-15 |

### 2.2 Signature verification

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R11 | Signature chip states: Valid · Invalid (reason) · No secret · Unknown scheme · Unsigned · Not checked · Checking (UF Screen 3; RD §6) | Y | RD §6 strings, one fixture per state. | U, E2E | — |
| R12 | Verification is performed **in the agent**. Secrets never reach the browser or the hosted server (VI principle 4; UF U6; CIMP §4; OQ Q4) | P | Network capture: no secret value in any agent→server message or server log. Hosted DB scan for known test secrets. | C, I, security | The agent protocol is not specified (no ADR yet), so the contract test has no schema → A-12 |
| R13 | Stripe (`Stripe-Signature` t/v1), GitHub (`X-Hub-Signature-256`), Shopify (`X-Shopify-Hmac-Sha256`, base64), Standard Webhooks (`webhook-id`/`-timestamp`/`-signature`) (PS §3.2; OQ Q7) | Y | **Cross-verification with the official libraries**: `stripe` `constructEvent`, `@octokit/webhooks` `verify`, Shopify HMAC as in Shopify's docs/library, `standardwebhooks` `verify`. Also known-answer vectors where providers publish them. | C | MVP provider list = Q7 leaning only (Stripe, GitHub, Shopify, Standard Webhooks). Twilio and Slack appear in budgets and landing copy but have no signing scope → A-18 |
| R14 | Invalid-signature diagnosis: reason + evidence + fix, e.g. "Signed with a different secret", body intact "7 of 7 checks" (RD #09; CO A "14 bytes differ… use the raw body") | N | — | — | The diagnosis rules are not enumerated: which reasons exist, which checks make up "7 of 7", and how "different secret" is distinguished from "re-serialised body". Only one example exists. → A-19 |
| R15 | Unknown scheme detection ("signature-like header (X-Signature)") (RD §6) | N | — | — | The heuristic for "signature-like" is undefined. |
| R16 | Twilio: re-sign using the public URL "as seen" (CE §2.9) | N | — | — | Depends on R2; Twilio is not in the MVP list. |

### 2.3 Scenario engine (agent)

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R17 | **Once:** each event once, freshly signed (PS §3.3; RD §4.1) | P | PS: 5xx / connection error / timeout → High. 401/403/400 → "Setup", and the run stops. | U, I, G | RD Run #3 sends Once for **1 of 3** source events (D1 only). PS says "each event". The UI has no "Setup / baseline rejected" state. What about other 4xx (404, 422) and 3xx? → A-3, A-5 |
| R18 | **Twice:** same event again, 500 ms later; same event id; new delivery id and timestamp "where the provider does that" (PS; CE §2.3; RD D2/D3 = +500 ms) | P | L2: digest after the 2nd delivery ≠ digest after the 1st → High. L1: 2nd response 5xx, or class differs → Medium; both 2xx and identical → pass + Info "Inconclusive". | U, I, G | CO/CIMP say "two deliveries 50 ms apart". Delivery-id behaviour per provider is not tabulated. L1 "Medium" does not fit the severity table. Is a 2xx→409 on the duplicate a finding? Scenario cell shows "pass+Info" (PS) or "— Inconclusive" (TT). → A-1, A-2, A-4 |
| R19 | **Shuffled:** related events "in a different order" (CE) = "reverse order" (PS, RD); reset first if configured; needs ≥ 2 events | P | PS: any 5xx → High. L2: final digest ≠ "in-order final digest" → High. | U, I | Which permutation (reverse vs seeded random)? Where does the "in-order final digest" come from, given that Once sends only one event in RD? → A-1, A-10 |
| R20 | **Late:** valid signature, timestamp 360 s old; n/a for GitHub/Shopify (PS; RD) | P | 2xx → High "Accepts old signed request". 4xx → pass. GitHub/Shopify print `n/a (provider signs no timestamp)`. | U, I, G | 5xx and 3xx outcomes undefined. Fixed 360 s vs "tolerance + margin" per provider. Late+probe "raise to High" is a no-op. Severity High vs Critical for a security failure. → A-2, A-6 |
| R21 | **Tampered:** one body field changed (amount, id or status), original signature kept (PS; RD D10 `amount_paid 2400 → 0`) | P | 2xx → Critical. 4xx → pass. | U, C, I | The field-selection rule is undefined. A 5xx (signature lib throws, uncaught) is not covered. The tampered request must be **proven to fail official SDK verification**, or the scenario itself is invalid. → A-6 |
| R22 | **Unsigned:** signature header removed (CE §2.3; RD §5 lists it as "more"; UF Screen 1 "+ Unsigned") | N | — | — | No oracle, no severity, not in the 8 and not in PS. Is it in the MVP? → A-7 |
| R23 | **Retry after error:** replays the provider's retry pattern, compressed, "after a non-2xx" (CE); "first delivery exceeds the budget (treated as failed), then Stripe's retry compressed to +2 s" (RD) | N | — | — | No finding rule. RD derives F2 from it (retry changes state) and F3 (slow). What if the first delivery is fast and 2xx (RD Run #4 still shows `expected 1 · got 1` on the retry)? How is the failure induced? Client-side abort at the budget, or wait? The "4xx trap" oracle (CO: "You returned 400 on a missing record") is nowhere specified. → A-8 |
| R24 | **Burst:** N related events at the same instant (CE; RD: 3, "same instant", D11–D13 all +27 480) | N | — | — | No N rule, no "same instant" tolerance, no finding rule (what fails Burst: 5xx? lock timeout? final digest ≠ sequential final digest?). Excluded from PS, so never prototyped. → A-9 |
| R25 | **Slow budget:** "No extra delivery: evaluates every delivery's latency" (PS) vs a dedicated D14 (RD/TT); Medium if latency > budget **and** ≥ 3× run median (PS) | P | Latency > budget → Medium (RD/TT); plus 3× median (PS). | U, I | Two contradictions. The 3× median rule means **a uniformly slow handler (every delivery at 12 s) is never flagged**. Latency start/stop points undefined. `>` vs `≥`. → A-11 |
| R26 | Sequence packs (CE §2.3; UF "more") | N | — | — | No pack format. Out of MVP? → A-7 |
| R27 | Side-effect warning before Twice/Shuffled (PS) or Twice/Burst (RD `side_effects`); every delivery carries a dry-run header (PS `X-Proving-Dry-Run: 1`; RD/UF `X-Trap-Dry-Run: 1`) | P | Header present on 100% of deliveries; warning shown once before the first run. | I, E2E, G | Scenario list and header name differ → A-20 |
| R28 | Localhost only by default; `--allow-host` exact match; no redirects; 30 s per-request timeout; ≤ 25 deliveries per run; Ctrl-C aborts (PS §5); agent allow-list (RD `start_failed`) | Y | Refusal matrix (`localhost`, `127.0.0.1`, `::1`, `*.localhost`, `127.0.0.2`, `0.0.0.0`, DNS name resolving to 127.0.0.1, IPv4-mapped IPv6); 3xx reported, never followed; timeout at 30 s. | U, I, security | 25-delivery cap (PS) vs the Stripe default 14, plus packs. Is the cap a product rule or prototype-only? A DNS name resolving to loopback is not covered. → A-21 |

### 2.4 Judge, findings, run status

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R29 | Severity: Critical = security; High = state divergence; Medium = budget exceeded, 4xx trap; Info = suggestion (CE §2.6; UF §5.2) | P | Table mapping (scenario, observation) → severity. | U | Inconsistent with the scenario rules (Late accepted = security but High; Twice L1 Medium = not budget and not a 4xx trap). → A-2 |
| R30 | Every finding names the level that produced it and the evidence deliveries (CE §2.4; UF U2) | Y | Finding JSON has `level` and `evidence[]`; each evidence id exists in the run. | U, C | — |
| R31 | Findings across scenarios with the same cause are merged (RD F2 = Twice D3 + Retry D9; F3 = Retry D8 + Slow budget D14) | N | — | — | The merge key is undefined (finding type? root cause?). F-numbering order is undefined (severity? first occurrence?). → A-3 |
| R32 | "Likely cause" line (RD F1 inferred from D7; F3 inferred from capture #07 commit message) | N | — | — | No inference rules. The F3 cause reads a GitHub push commit, which is not something a rule can produce reliably. → A-22 |
| R33 | Framework detection ("Express (detected from X-Powered-By)") and framework-aware fix snippets (CE M7; RD F1–F3) | P | `X-Powered-By: Express` → Express snippet. | U | The MVP framework list and the fallback when undetected are undefined. PS uses generic fix text. → A-22 |
| R34 | Info F4 "Probe doesn't see subscription state" (RD F4) | N | — | — | No trigger rule (how does the engine know `subscriptions.status` exists or matters?). → A-10 |
| R35 | Run status vocabulary: Draft · Queued · Running · Passed · N findings · Aborted · Inconclusive (UF §5.1) | P | State machine. | U, E2E | No precedence rule (findings + inconclusive scenario?). Target unreachable mid-run → "Inconclusive for affected scenarios" (UF) vs exit 2 "run incomplete" (TT). Baseline rejected (PS "Setup") has no status. → A-4, A-5 |
| R36 | Passed claims only what was checked: "8 of 8 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: …" (TT D9, §3.3) | P | Exact string for the dataset. | G, E2E | The "Not checked" content is derived from F4's undefined rule → A-10 |
| R37 | Order-dependent flag when no reset hook ran (CE §2.9; UF; RD `order_dependent`) | Y | Absence of `reset:` → Info + flag. | U, G | Behaviour when the reset hook fails (non-zero exit) is undefined → A-10 |
| R38 | State reading flagged when "the probe digest after a delivery differs from what that delivery should have left" (TT §1.2) | P | Expected values: D3/D9 → value after the previous same-event delivery; D10 → before-value. | U | "Should have left" is undefined for Once, Shuffled and Burst rows. → A-10 |
| R39 | Abort keeps completed deliveries and results (CE §2.9; UF; RD Run #2) | Y | Kill the agent after D2 → run Aborted, D1–D2 persisted, D3 "not run". | I, E2E | "During D3": was D3 sent? If the request was sent but no response arrived, D3 counts as completed or not? → A-5 |

### 2.5 Probe and reset (Level 2) and assertions (Level 3)

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R40 | Probe = shell command (PS) or "URL or shell command" (CE) or `sql:` entries with `as:` names and `{{invoice.id}}` templates (RD, TT); run after each delivery; digest value never leaves the machine (PS; CE §2.7) | P | Digest equality by string. Hosted server never receives the digest value. | U, C, I | Three different probe syntaxes. Templating across event types (D12 `payment_intent` with `{{invoice.id}}`) undefined. Who runs `sql:` and with which connection string? Probe failure, timeout, non-zero exit and empty stdout undefined. CE's own example uses `max(updated_at)`, which changes every run and would produce false findings. Async handlers (queue) need a settle rule. → A-10 |
| R41 | Reset hook between scenarios (PS `reset:`; RD `npm run db:reset:webhooks`) | P | Reset runs before scenarios 2..n; its exit code is checked. | I | Failure behaviour, timeout, and whether it runs before scenario 1 are undefined → A-10 |
| R42 | Level 3: "your test command runs after each scenario and its exit code becomes the result" (CE §2.4; CO) | N | — | — | No config key, no severity mapping, not in any UI state or dataset. MVP or not? → A-7 |

### 2.6 Budgets

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R43 | Budgets: Stripe 10 000 ms default · Shopify 5 000 ms docs · GitHub 10 000 ms docs · Twilio 15 000 ms default · Slack 3 000 ms docs · generic 10 000 ms default (PB §1) | Y | Lookup table → value + source tag. | U | RD gives **Parcelbird (Standard Webhooks) 15 000 ms default**. PB says Standard Webhooks is "not a budget" and generic = 10 000 ms → A-11 |
| R44 | A budget number never appears without its source tag; possessive ("Stripe's budget") only with source = provider docs (TT §4 rules 1–3; PB §3 "Do NOT say") | Y | Lint over rendered copy: regex for a budget number not followed by its tag; forbidden-phrase list from PB §3. | U, G, V | Good oracle. Turn PB §3 "Do NOT say" into an automated copy check. |
| R45 | `budget:` in `trap.yml` overrides (TT §4) | N | — | — | PROPOSED, not decided → A-11 |

### 2.7 CLI, CI, PR comment, JSON, MCP

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R46 | Run record structure, 100-column max, wrap at 96, 18-column hanging indent, glyph set, ASCII map, 16-colour ANSI, `NO_COLOR`, non-TTY append-only, status line redraw ≤ 4/s (TT §1.4) | Y | **Golden files**: TT §1.4.2, §1.4.3, §1.4.5, §1.4.6 for Run #3 with an injected clock and ids. | G | The ASCII sample's state column is one space wider (`state   result`) than the Unicode one. Is that intended? Golden files will lock whichever is chosen. → A-23 |
| R47 | Exit codes: 0 passed or below `--fail-on`; 1 at/above `--fail-on` (default high); 2 run incomplete (aborted, start failed, target unreachable); 3 usage/config; Inconclusive exits 0, prints "(not passed)", `--fail-on-inconclusive` → 1 (TT §1.4; PS §3.4) | P | Exit-code matrix test. | G, I | "Target unreachable" → 2 conflicts with UF (Inconclusive). Once 4xx "Setup" → 2 or 3? Precedence when a run has a Critical finding **and** aborts. → A-4, A-5 |
| R48 | CI: `::group::` rows byte-identical to the TTY summary; workflow commands only when `--ci github` or `GITHUB_ACTIONS=true` (TT §1.4.5) | Y | Byte diff against the golden file. | G | — |
| R49 | PR comment: one comment per PR, edited in place (hidden marker on line 1); Critical/High open, Medium/Info collapsed (TT §1.4.6) | Y | Fake GitHub API: second push → PATCH the same comment id, never POST. | C, I | Needs a GitHub API test double. The integration mechanism (App vs token) is undefined (Phase 4). |
| R50 | Every verdict available as JSON and MCP (VI principle 8; UF U8; OQ Q11) | N | — | — | No JSON schema, no MCP tool list. Copy-as-JSON samples exist only for F2 → A-24 |
| R51 | `trap test` runs offline in CI from fixtures, no agent (CE M9; TT §1.4.5) | P | Run with network namespace = loopback only. Assert zero non-loopback connections. | I | Fixture file format undefined (RD lists file names only). |

### 2.8 Fixtures and scrubbing

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R52 | Save as fixtures: preview of files plus scrub rules (emails, names, tokens, card fingerprints); written through the agent (CE M8; RD §4.5 three scrub rules) | P | RD §4.5 replacements. | U, I | The scrub rule set is not enumerated (CE lists 4 categories, RD shows 3 field rules). After scrubbing, are fixtures **re-signed** at replay time? (They must be: scrubbing breaks the original signature.) Is the scrubber's detection field-name based or value-pattern based? → A-25 |
| R53 | Fixture states: Not saved · Saved (in sync) · Drifted (RD §6) | P | RD strings. | U, E2E | Drift relies on "shape" (R7) → A-17 |

### 2.9 Agent and pairing

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R54 | Pairing with a one-time code; connected in < 30 s; code expiry shown (`9:41`); guest pairing bound to the session (CE M4; UF; ADR-003 via OQ) | P | Code lifetime, single use, binding. | C, I, E2E | Code TTL value, single-use and brute-force limits undefined (A09 threat model). No agent ADR. → A-12 |
| R55 | Agent states: not installed · pairing · connected · target unreachable (refused/TLS) · disconnected mid-run · version too old (CE §2.6; UF §5.3; RD §6) | Y | RD §6 strings. Fake agent drives each state. | E2E, V | "Version too old" needs a protocol version rule ("needs 0.9 or later"): semver range? |
| R56 | Run starts < 1 s from pressing Run; "Queued" visible only if not accepted within 300 ms (UF Screen 4) | Y | Fake agent acceptance delay at 299/301 ms. | E2E | — |
| R57 | Run timeline streams row by row; findings appear as soon as known, sorted by severity, timeline not reordered; auto-follow only if the user hasn't scrolled or selected (UF Screen 4) | Y | Scripted fake agent event stream. | E2E, A | Transport (Echo vs Live Props) is a proposed ADR-004 amendment; the event payload contract is undefined → A-12 |

### 2.10 Guest access and quotas

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R58 | Guest: 24 h retention, 100 deliveries, 3 runs, 1 agent (RD §1; UF §10 "placeholders"; OQ Q9) | P | Boundary tests at 99/100/101 and 24 h ± 1 s with an injected clock. | I, E2E | **Values are placeholders.** What counts as a run (started? completed? aborted? start failed?) is undefined. Is "24 h" from the first capture or from URL creation? Does a 101st delivery still return 200? (RD says yes.) → A-13 |
| R59 | Expired guest URL: whole screen replaced; nothing from the session shown (UF; RD `expired`) | Y | After expiry, no delivery data in the HTML or props. | E2E, security | — |
| R60 | Sign-in claims the guest session: deliveries, runs and pairing move; the URL stays (UF §2.3) | P | Ownership transferred in one transaction (ADR-003 conversion steps); old guest token rejected. | I | UF §10 says it "needs an ADR-003 note". The ADR's "eligible resources" are not listed. |
| R61 | Workspace belongs to exactly one principal (DB CHECK) (ADR-003) | Y | Insert with both or neither of `user_id` / `guest_session_id` → constraint violation. | I | — |

### 2.11 UI, accessibility, comprehension

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R62 | Run screen states: draft, queued, running, passed, findings, aborted, inconclusive, order-dependent, needs secret, side-effects warning, start failed, read-only shared (UF Screen 4; RD §6) | Y | RD §6 strings per state. | E2E, V | Missing: "baseline rejected / Setup" (PS) → A-5 |
| R63 | Scenario strip cell content and states, accessible names (TT §1.1) | Y | TT table as expected text, incl. accessible names. | U, A, V | — |
| R64 | Contrast ≥ 4.5:1 text, ≥ 3:1 large text and meaningful non-text; 2 px focus ring + 2 px offset ≥ 3:1; targets ≥ 24 px desktop and ≥ 44 px touch; reflow at 320 px and 200 % zoom; reduced motion; no flash > 3/s; greyscale test (UF §7) | Y | axe-core plus manual checks per rule; contrast computed from tokens in light and dark. | A, V | The target level is not named. Phase 2 plan says "WCAG 2.2 AA", and the ≥ 24 px rule matches 2.2 SC 2.5.8. State it once. → A-26 |
| R65 | Every visual timeline has a synced table that is the accessible source of truth (UF §0.5, §7.9; TT §3.4) | Y | Selecting in one selects in the other; table has every value the graph shows. | E2E, A | — |
| R66 | Keyboard maps per screen; single-key shortcuts can be switched off (UF §3.1 and per screen) | P | Each key → action. | E2E, A | Conflicts: Run screen `j`/`k` **and** `→`/`←` both mean next/previous delivery, while the strip toolbar uses `←`/`→` (TT §1.1) and the capture list uses `←`/`→` to collapse/expand. Scope rules are needed. `r` "Run again" is a single key with a side effect. → A-27 |
| R67 | Live regions polite and batched; assertive only for "Run aborted: agent disconnected" (UF §7.6) | Y | Screen-reader event log. | A | — |
| R68 | 10-second comprehension test, 5 participants, pass marks per question, fallback rules (TT §5) | Y | TT §5 table (pass ≥ 4/5 or ≥ 3/5). | Manual (research) | Recruiting is the only remaining user contact (field validation waived). See §6. |
| R69 | Landing: demo final frame under reduced motion; text alternative tells the whole story; no-JS CTA works (UF Screen 1) | Y | Exact alt paragraph (UF). JS disabled → CTA creates a session. | E2E, A | — |
| R70 | Copy and voice rules: thin-space thousands in UI, plain space in CLI; ≤ 3 sentences per beat, ≤ 25 words per sentence; no exclamation marks; forbidden-terms sweep (`allowance`, `VERIFY`, `Jonah another`, `always retries`…) (UF §6; TT §1.3, §3) | Y | Copy linter over rendered strings and the RD JSON. | U | Good candidate for a CI copy check. |

### 2.12 Product-level success measures

| # | Requirement (source) | T | Oracle | Level | Issue |
| - | -------------------- | - | ------ | ----- | ----- |
| R71 | First event < 60 s, first verdict < 5 min from landing (VI) | P | Scripted journey timing. | E2E, P | Measurable on a scripted path only. Real users are needed for the true value. |
| R72 | > 40% of first runs surface a "real" finding; kill if < 20% (VI; VP §3) | N (now) | VP defines "strict real" = Real + fix-before-release yes. | — | No participants (field validation waived) → §6 |

---

## 3. Ambiguities and contradictions

Each item gives a proposed wording, chosen to be directly testable, and the owner of the decision. Wording is a proposal, not a decision.

**A-1 · Scenario definitions: Once, Twice and Shuffled contradict each other** (owner **A01**)
- Once: PS "each event once" vs RD Run #3 sends only `invoice.paid` (D1) for 3 source events.
- Twice: PS "500 ms later" vs CO and CIMP "two deliveries 50 ms apart".
- Shuffled: CE "a different order" vs PS and RD "reverse order"; brief name "Shuffled" suggests random. The L2 oracle needs an "in-order final digest" that no scenario in RD produces.
- *Proposed wording:*
  - "**Once** sends every selected event once, in capture order, each freshly signed. Its final digest is the *in-order baseline*."
  - "**Twice** sends the first selected event, then the same event again 500 ms after the first response (configurable per pack). It keeps the provider event id. Delivery id and signature timestamp follow the provider pack's `duplicate` rule."
  - "**Shuffled** sends the selected events in the reverse of capture order if there are 2. If there are ≥ 3, it uses a permutation chosen by the run seed, never the identity. The reset hook runs first. Finding (L2): final digest ≠ *in-order baseline*."
- Also needed: a per-provider table (pack data) of what changes on a duplicate (event id, delivery id header, timestamp).

**A-2 · The severity model is not consistent with the scenario rules** (owner **A01**)
- Critical is "security", but a Late acceptance (a replay-protection failure, so security) is High (PS).
- Twice L1 "Medium · duplicate not handled cleanly" fits neither "budget exceeded" nor "4xx trap".
- PS Late "with probe: digest changes → raise to High" is a no-op, because it is already High.
- UF §0.8 says making the 1 → 2 finding red needs a re-rank by A01.
- *Proposed wording (one table, no exceptions):*

  | Severity | Rule |
  | -------- | ---- |
  | Critical | The handler accepted input that fails authentication: Tampered or Unsigned answered 2xx, **or** any state change after such input. |
  | High | Replay protection missing: Late answered 2xx. State divergence at L2. A 5xx or crash on a valid delivery in any scenario. |
  | Medium | Over the budget. A non-2xx on a valid duplicate or retry (the "4xx trap": the provider will keep retrying). |
  | Info | Suggestions and inconclusive notes. |

  Then add: "A probe change on Late raises it to Critical."
- If A01 prefers Late to stay High, state the exception explicitly in the table.

**A-3 · Finding identity, merging and numbering** (owner **A01**)
- RD merges Twice D3 and Retry D9 into one F2, and Retry D8 and Slow budget D14 into F3. No rule says when observations merge.
- *Proposed wording:* "A finding is keyed by its **finding type** (fixed list: `accepts_tampered`, `accepts_unsigned`, `accepts_stale`, `duplicate_changed_state`, `order_changed_state`, `concurrent_changed_state`, `server_error`, `over_budget`, `non_2xx_on_retry`, `probe_gap`, …). All observations of the same type in one run merge into one finding whose evidence lists every delivery. Findings are numbered F1…Fn by severity, then by first evidence D-number."
- This also gives JSON/MCP a stable `type` field (see A-24).

**A-4 · Run-level status precedence, Inconclusive and exit codes** (owner **A01** with **A03**)
- UF: target unreachable mid-run → "the run continues and ends Inconclusive for affected scenarios".
- TT: target unreachable → exit 2 "run incomplete".
- Scenario-level Inconclusive is drawn as "pass + Info" (PS) or "— Inconclusive" (TT strip, Run #1).
- *Proposed wording:*
  1. Scenario result ∈ {pass, finding, inconclusive, not run, unreachable}.
  2. Run status = **Aborted** if the agent was lost or the user aborted; else **N findings** if any finding ≥ Medium (Info findings alone do **not** make "N findings"; they are listed under Passed or Inconclusive); else **Inconclusive** if any scenario is inconclusive or unreachable; else **Passed**.
  3. Exit code = 2 if Aborted, start failed, or any delivery unreachable; else 1 if any finding ≥ `--fail-on`; else 1 if Inconclusive and `--fail-on-inconclusive`; else 0. Exit code 3 is reserved for usage/config errors before any delivery is sent.
- Also state whether Info findings appear in the "N findings" count: RD Run #3 counts F4 in "4 findings", which contradicts point 2. **A01 decides which.**

**A-5 · Baseline rejected, and partial deliveries** (owner **A01** with **A03**)
- PS: Once answered 401/403/400 → "Setup (not a finding)" and the run stops. No UI state, no exit code, no RD copy.
- Aborted "during D3" doesn't say whether D3 was sent.
- *Proposed wording:*
  - "If any Once delivery gets 400, 401 or 403, the run stops with status **Couldn't start: baseline rejected**, exit 2, and the copy 'Your handler rejected a correctly signed delivery (HTTP {status}). Check the secret on {agent}.' Other 4xx on Once (404, 405, 422) do the same, with the status in the copy."
  - "A delivery is *completed* when its response (or a timeout/connection error) and its probe digest are recorded. A delivery that was sent but not completed shows `sent, no result` and is not judged."
- A03 adds the state to the UF Screen 4 table and RD §6.

**A-6 · Late and Tampered: outcome matrix and attack construction** (owner **A01**; construction **A05**)
- Only 2xx and 4xx are covered.
- *Proposed wording:*
  - "Late and Tampered pass on any 4xx. 2xx is a finding (A-2). 5xx is a High `server_error` finding ('the handler crashed on an invalid signature instead of rejecting it'). 3xx is reported as Inconclusive."
  - "Late uses timestamp = now − (pack tolerance + 60 s): Stripe 300 + 60 = 360 s; Standard Webhooks 300 + 60 = 360 s. It is n/a when the pack has no signed timestamp."
  - "Tampered changes the first field in the pack's `tamper_paths` list that exists in the body (Stripe `invoice.paid`: `data.object.amount_paid` → 0). Otherwise it changes the last byte of the first string value. The diff is shown."
- **Engine invariant (A05):** "Before sending, the agent verifies that the Late and Tampered requests **fail** the pack's own verifier and that every re-signed request **passes** it. Otherwise the scenario is marked invalid and not run."

**A-7 · MVP scope of scenarios and levels** (owner **PO** on advice from **A01**)
- CE §2.3 lists 10 scenarios.
- The brief and RD list 8 (no Unsigned, no Sequence pack).
- PS covers 6.
- Level 3 (test command) is in CE and CO, but in no state, dataset or CLI sample.
- *Proposed wording:* "MVP scenarios are the 8 in RD §5. Unsigned is MVP / not MVP: **PO to choose**. If MVP, its oracle is the same as Tampered's. Sequence packs and Level 3 assertions are post-MVP and are not drawn in Phase 2."

**A-8 · Retry after error has no oracle and no trigger rule** (owner **A01**; mechanics **A05**)
- *Proposed wording:*
  - "Retry after error sends the event (attempt 1). If attempt 1 gets a non-2xx, a timeout, or a latency over the budget, the provider would retry. Even if attempt 1 succeeds, the scenario sends attempt 2, because the provider cannot see a response that arrived after its own timeout. It is sent 2 000 ms after attempt 1 completes, with the same event id and a pack-specific retry header (Slack `x-slack-retry-num`, etc.)."
  - "Findings: attempt 2's digest ≠ attempt 1's digest → `duplicate_changed_state` (High). Attempt 1 non-2xx with no state change, then attempt 2 2xx → none. Attempt 1 non-2xx **and** a state change → `partial_side_effect` (High). Attempt 1 4xx on a valid delivery → `non_2xx_on_retry` (Medium, 'Stripe will retry this…')."
  - "The agent never aborts a request at the budget. It waits up to the 30 s request timeout and records the real latency."
- This also explains RD Run #4 (`expected 1 · got 1` on a fast handler).

**A-9 · Burst has no oracle** (owner **A01**; tolerance **A05**)
- *Proposed wording:*
  - "Burst sends all selected events (minimum 2, maximum 5) released together from a barrier. The dispatch spread (first to last request write) must be ≤ 20 ms on loopback; otherwise the scenario is marked 'not simultaneous' (Info) and not judged."
  - "Findings: any 5xx or timeout → `server_error` (High). Final digest ≠ *in-order baseline* → `concurrent_changed_state` (High)."

**A-10 · Probe and reset semantics** (owner **A01** for syntax and rules; **A05** for execution)
- Three syntaxes exist: a shell command (PS), a URL or command (CE), and `sql:` + `as:` + templates (RD/TT).
- *Proposed wording:*
  - "A probe is a list of named checks. Each is `cmd:` (stdout is the value) or `sql:` (run through `cmd` with a configured client) or `url:` (response body is the value). The value is compared as a string after trimming trailing whitespace."
  - "Templates (`{{invoice.id}}`) resolve from the **group's** related events, not from the current delivery, so every probe runs for every delivery."
  - "A probe that exits non-zero, times out (default 5 s) or prints nothing makes the delivery's state `probe failed`. Scenarios that need it become Inconclusive with the probe's stderr (first 200 bytes, local only)."
  - "**Stability check:** before the run, the probe runs twice with no delivery in between. If the values differ, the run stops: 'Your probe isn't stable (value changed with no delivery). Remove time-based columns like updated_at.'" (CE's own example `max(updated_at)` would fail this.)
  - "**Settle rule:** after each response, the probe runs at +0 ms and is repeated every 250 ms until two consecutive values match, up to `settle:` (default 2 s), so async handlers are measured after their job runs."
  - "Reset runs before every scenario, including the first. A non-zero exit stops the run (exit 2, 'Reset failed')."
  - "*Expected state* per delivery: Once → any (records the baseline); a duplicate or retry → equal to the value after the previous delivery of the same event; Tampered, Late, Unsigned → equal to the value before; Shuffled and Burst → only the final value is compared, against the baseline."
  - "F4 (`probe_gap`) is emitted when the run includes an event type listed in the pack's `status_events` and no probe name matches the pack's `status_fields`." Or A01 drops F4 from the MVP.

**A-11 · Budget values and Slow-budget rule** (owner **A01**)
- RD: Parcelbird (Standard Webhooks) 15 000 ms default vs PB: Standard Webhooks "not a budget", generic 10 000 ms default.
- PS: Slow budget adds "no extra delivery" vs RD/TT: dedicated D14.
- PS's "≥ 3× the run's median" condition hides uniformly slow handlers.
- Latency is undefined.
- *Proposed wording:*
  - "Standard Webhooks and unknown providers use the generic 10 000 ms default." (Fix RD.)
  - "Latency = time from the first byte of the request written to the last byte of the response headers received, on a monotonic clock."
  - "A delivery is over budget when latency **>** budget (equal is within)."
  - "`over_budget` (Medium) is raised for any valid delivery over budget, in any scenario. The Slow budget scenario adds one extra delivery of the first event, so a run that selects only Slow budget still measures something."
  - "The 3× median condition is dropped." Or A01 keeps it only to suppress single outliers, and states that a handler slower than budget on ≥ 50 % of deliveries is always flagged.
- Also decide whether `budget:` in `trap.yml` is MVP (TT §7.1).

**A-12 · No contract exists for the agent ↔ server protocol or the run event stream** (owner **A05**, with A09)
- Pairing, job integrity, version negotiation, secret-free payloads and the timeline events (CIMP §2: "run id, row index, status") are proposed but unwritten.
- *Proposed wording for the ADR:* "The agent protocol is versioned (`proto: 1`). Every message has a JSON Schema in the repo. Server and agent each run the other's schema tests in CI (consumer-driven contract). Timeline events carry `run_id`, `seq` (monotonic per run), `delivery` (D-number), `phase` (sent | responded | probed | judged), and never bodies, headers, digest values or secrets."
- Without this, R12, R54 and R57 cannot have contract tests.

**A-13 · Guest quotas and what counts** (owner **PO**; copy **A03**)
- UF §10 calls 24 h / 100 / 3 / 1 placeholders, yet they are drawn on screens and in copy.
- *Proposed wording:*
  - "Guest session lives 24 h from URL creation."
  - "It stores at most 100 deliveries. Delivery 101 onward is answered exactly like a stored one (same status and body) but not stored."
  - "A run counts toward the 3-run quota when the agent accepts it. Start-failed runs don't count; aborted runs do."
  - "One paired agent at a time; pairing a second replaces the first."
- Also: RD `paused` uses the "Catching up" label while UF has separate Paused (manual) and Catching up states. A03 to add a distinct `paused` row and rename the current one `catching_up`.

**A-14 · Header fidelity under PHP-FPM** (owner **A05**)
- UF promises "headers in received order, original casing". ADR-005 allows lossy order and casing, flagged.
- *Proposed wording:* "Capture stores the `header_fidelity` flag (`exact` | `sapi`). When `sapi`, the Raw tab shows 'Order and casing as reported by the server, may differ from the wire'."
- The test asserts the flag and the copy, not wire order.

**A-15 · Ingest response invariants** (owner **A05**)
- *Proposed wording:* "Every request to a capture URL gets the same status code and body: valid, unknown, expired, over quota, body over limit, rate-limited for storage. Only the network-level rate limit may differ (429), and it applies to all URLs equally. The body limit is 5 MiB inclusive (5 242 880 bytes kept, 5 242 881 not kept)."
- MB vs MiB must be decided.

**A-16 · Performance targets lack percentile and environment** (owner **A01**)
- *Proposed wording:* "Arrival on screen: p95 < 1 s, p99 < 2 s, measured from ingest response sent to row visible, at 10 deliveries/s per capture URL and 50 concurrent viewers, on the reference Compose stack. Run start: p95 < 1 s from Run click to the agent's `accepted` event, with the agent on the same LAN."

**A-17 · Grouping and shape rules** (owner **A01**)
- *Proposed wording:*
  - "Each provider pack declares `group_key` JSONPaths per event type, plus cross-provider `link_keys`."
  - "Shape = the set of JSON paths with their JSON types; arrays are represented by the union of their element shapes; null is its own type."
  - "Differs = any added or removed path, or a type change."

**A-18 · MVP provider list** (owner **PO**)
- Q7 is still a leaning. Budgets cover Twilio and Slack, the landing strip lists 6 providers, and signing covers 4.
- *Proposed wording:* "MVP providers with signing and packs: Stripe, GitHub, Shopify, Standard Webhooks. Twilio and Slack appear as 'coming' and get no budget line in the product until their pack ships."

**A-19 · Signature diagnosis rules** (owner **A01**)
- *Proposed wording:* "Diagnosis reasons, in order: header missing · scheme unknown · timestamp outside tolerance · body re-encoded (check the stored body against the signature with these variants: JSON re-serialised with sorted keys, whitespace normalised, UTF-8 re-encoded) · different secret (none of the variants match). The 'N of N checks' list is exactly: Content-Length matches, valid UTF-8, no BOM, no re-encoding detected, timestamp within tolerance, header well-formed, scheme known."
- Each reason gets a fixture.

**A-20 · Naming drift across documents** (owner **A01**, brand pending)
- `proto` (PS) / `trap` (TT, RD) / `proving test` (CO).
- `proving.yml` (PS) / `trap.yml` (RD, TT).
- `X-Proving-Dry-Run` (PS) / `X-Trap-Dry-Run` (RD, UF).
- Side-effect warning before Twice + Shuffled (PS) / Twice + Burst (RD).
- *Proposed wording:* "Until the brand decision, the product spec uses `trap`, `trap.yml` and `X-Trap-*`. The side-effect warning applies to every scenario that sends the same event id more than once: Twice, Retry after error, and Burst when an event repeats."
- Golden files will pin whichever names are chosen, so this must close before Phase 4.

**A-21 · Delivery cap and target validation** (owner **A01**; **A05** for the resolver rule)
- PS's 25-delivery cap is not restated for the product.
- *Proposed wording:* "A run sends at most 50 deliveries (packs included); the draft refuses more with a count. A target is local if every address it resolves to is loopback. Names resolving to a mix of addresses are refused. `0.0.0.0` is refused."

**A-22 · "Likely cause" and framework-aware fixes** (owner **A01**)
- *Proposed wording:*
  - "MVP 'Likely cause' lines come only from a fixed rule table (e.g. Tampered accepted **and** Late rejected → 'the timestamp check runs; the signature check probably doesn't stop the request'). No cause is inferred from other captures or commit messages."
  - "Frameworks with snippets in MVP: Express, Laravel, Next.js route handlers. Otherwise the fix is generic text."
- RD F3's commit-based cause is then dataset flavour only, and must be marked as such or removed.

**A-23 · Golden-file details** (owner **A03**)
- The ASCII sample's column header has an extra space after `state`.
- TT §1.4.2 uses the Unicode prime `′` in `D2′`.
- The 100-column rule is not met by the long `::error` lines (stated as exempt).
- *Proposed wording:* "The ASCII rendering is the Unicode rendering after the TT map, with column widths computed from display width. The state column width is the same in both."

**A-24 · Machine output contract** (owner **A01** for content, **A05** for the schema)
- *Proposed wording:* "`--json` emits one document per run, validated by `run.schema.json` (versioned). Fields: run, scenarios[], deliveries[], findings[] with the A-3 `type`, severity, level, evidence[], measured{}, and exit_code. MCP tools in MVP: `list_events`, `run_scenarios`, `get_run`, `get_finding`, all returning the same schema fragments."

**A-25 · Scrubbing** (owner **A01**)
- *Proposed wording:* "Scrub rules are pack data: field paths plus value patterns (email, phone, card fingerprint, bearer-like tokens ≥ 20 chars). Scrubbed fixtures are always re-signed at replay. A fixture that still matches any pattern after scrubbing blocks the save, with the path shown."

**A-26 · Accessibility target statement** (owner **A03**)
- *Proposed wording:* "Conformance target: WCAG 2.2 Level AA for all app screens and the landing page. UF §7 rules are additional product rules on top of it."

**A-27 · Keyboard scope conflicts** (owner **A03**)
- *Proposed wording:* "Arrow keys act only inside the focused composite widget (treegrid, toolbar, table). Letter shortcuts act at screen scope. `r`, `Shift X` and `s` require confirmation or are disabled when the 'single-key shortcuts' switch is off." Then define the Run screen's `j`/`k` as delivery navigation and drop `→`/`←` from screen scope.

---

## 4. Determinism strategy: constraints for A05 (Phase 4)

These are **requirements on the engine's design**, so the acceptance tests in §5 are possible. A05 should record them in the "Local agent and client-side scenario engine" ADR.

**D-1 · Three pure stages with a recorded plan.**
- `plan(selection, scenarios, pack, seed, config) → Plan` is a pure function. The Plan holds the exact ordered list of deliveries, offsets, signing instructions and expected-state rules.
- `execute(Plan, ports) → Observations` is the only impure stage.
- `judge(Plan, Observations) → Verdict` is a pure function: findings, severity, run status and exit code.
- Plan and Observations are serialised into the run file, so any verdict can be recomputed offline. `trap rejudge run.json` should exist for tests and bug reports.

**D-2 · Injected clock.**
- Two clocks behind one interface: wall (signature timestamps, `t − 360 s`) and monotonic (latency, offsets).
- Tests use a fake clock. Golden output must be produced with a fixed wall time and fixed ids.
- No `time.Now()` outside the clock adapter. Enforce with a lint rule or arch test.

**D-3 · Seeded randomness.**
- Every random choice (Shuffled permutation, Tampered fallback byte, ids, jitter) comes from one PRNG seeded per run.
- The seed is printed in `--verbose`, stored in the run file, and accepted as `--seed`. Same seed + same fixtures + same handler responses ⇒ the same Plan, byte for byte.

**D-4 · Virtual scheduler.**
- Waits (500 ms Twice gap, 2 s retry gap, Burst barrier) go through a scheduler port.
- Unit tests use a virtual scheduler that advances instantly, so a full 8-scenario plan is judged in milliseconds.
- Only a small, tagged real-time integration suite uses the wall scheduler.

**D-5 · Timing assertions use margins, never boundaries.**
- Real-time tests use test-only budgets (a "test" pack: budget 200 ms) and handlers that sleep 0.25× or 4× the budget, never near it.
- Boundary behaviour (`>` vs `≥`, A-11) is tested only in `judge` with synthetic observations.
- Burst simultaneity is asserted as the dispatch spread recorded by the agent (≤ 20 ms on loopback), not as arrival times measured by the handler.

**D-6 · Transport port with fault injection.**
- The HTTP sender is an interface. The test double can return a scripted status, latency, connection refused, TLS error, reset mid-body, 3xx with Location, or a hang until timeout.
- The real sender is tested once against a loopback server for each fault.

**D-7 · Probe port.**
- Probe and reset go through a port. The test double returns scripted values per call, including unstable and failing values, to test A-10's stability and settle rules deterministically.

**D-8 · Provider packs are data.**
- Budgets, tolerances, signing scheme, duplicate rule, retry headers, `tamper_paths`, `group_key`, scrub rules and citations live in versioned pack files with a JSON Schema.
- The judge reads only the pack, never hard-coded provider names. Tests then use a synthetic "test" pack to cover every branch without a real provider.

**D-9 · Signing is verified against official SDKs in CI.**
- A contract job (Node, plus PHP where a PHP SDK is the reference) feeds the agent's re-signed, Late and Tampered requests to each provider's official verifier.
- Expected: re-signed passes, Late fails on the timestamp, Tampered fails on the signature.
- This is the strongest oracle available for "provider-faithful" (VI principle 3).

**D-10 · Server side.**
- Laravel/Pest with a fake clock (`Carbon::setTestNow`) for guest expiry and quotas.
- Fake broadcaster for Echo events.
- Contract tests for the agent protocol (A-12).
- Ingest tested with raw socket requests, so body bytes and query encoding are not normalised by the HTTP client.

**D-11 · Output.**
- The text renderer is a pure function of the Verdict plus a terminal profile (width, UTF-8 or ASCII, colour or none, TTY or not).
- Golden files per profile are generated from the RD Run #3 observations. Golden updates need a reviewer.

**D-12 · Reference handlers** (the fixtures most tests depend on). One small app, in each of two stacks (Express, Laravel), with feature flags:
- `skip_signature_check`
- `log_but_continue_on_bad_signature` (the F1 case)
- `no_idempotency`
- `async_write` (queue with delay)
- `sleep_ms` on a path
- `crash_on_out_of_order`
- `return_4xx_on_missing_record`
- `non_atomic_insert` (races under Burst)
- `unstable_probe`

Each flag has a "fixed" counterpart. The PS §9 matrix (buggy → expected findings, fixed → none) becomes the core regression suite. It should run against the dogfooded open-source handlers too (see §6).

---

## 5. Proposed acceptance-test skeleton (MVP)

Titles only. Each title is meant to become one or more tests. `[G]` = golden, `[C]` = contract, `[E2E]`, `[A]` = accessibility, `[P]` = performance.

**Capture**
1. Stores exact body bytes for JSON, form-encoded, binary and empty bodies (SHA-256 match).
2. Stores the raw query string unchanged, including repeated keys and percent-encoding.
3. Records `header_fidelity` and shows the matching Raw-tab note.
4. Body at 5 MiB is kept; at 5 MiB + 1 byte, only metadata and SHA-256 are kept, and Prove is disabled with its reason.
5. Unknown, expired and over-quota capture URLs return the same status and body as a valid one.
6. A delivery appears in an open capture stream within the arrival target [P].
7. Selected row never moves when deliveries arrive; the "N new" pill counts correctly [E2E].
8. Hidden tab returns to "Catching up" with the right count, cleared after 4 s [E2E].
9. Stream lost shows the banner and recovers missed deliveries after reconnect [E2E].
10. Groups match RD §2 for the 13 RD deliveries (incl. the cross-provider Order #1042).
11. Shape: RD #11 is "Shape differs" with `cancellation_details.feedback`; first capture is "New shape".

**Signature**
12. Valid / invalid / no secret / unknown scheme / unsigned / not checked / checking states render RD §6 copy [E2E].
13. Agent-signed requests pass the official Stripe, GitHub, Shopify and Standard Webhooks verifiers [C].
14. Late requests fail Stripe and Standard Webhooks verifiers on the timestamp only [C].
15. Tampered requests fail every verifier on the signature [C].
16. Diagnosis: wrong secret → "Signed with a different secret" with the 7 checks listed.
17. Diagnosis: re-serialised JSON body → "body re-encoded" reason.
18. No secret value appears in any agent→server message, server log, run file or exported summary [C].

**Scenario engine and judge** (against the reference handlers, plus pure judge tests)
19. Buggy handler, L1 + probe: findings exactly Tampered Critical, Late High, Twice High, Shuffled High, Slow budget Medium (PS §9.1).
20. Fixed handler, L1 + probe: no findings; run Passed with the "Not checked" line.
21. Buggy handler, L1 only: Twice is Inconclusive (Info), the others as in test 19 (PS §9.2).
22. Once 401 → run stops "baseline rejected", exit 2, no other scenario sent.
23. Twice keeps the event id, changes timestamp/delivery id per pack, and waits 500 ms after the first response.
24. Shuffled with 2 events sends reverse order; with 3 events the same seed gives the same permutation, never identity.
25. Late uses `now − (tolerance + 60 s)`; GitHub and Shopify print `n/a`.
26. Late 5xx → High `server_error`; Late 3xx → Inconclusive.
27. Tampered changes the pack's first existing `tamper_paths` field and shows the diff.
28. Retry after error sends attempt 2 even when attempt 1 is fast and 2xx.
29. Retry after error: a 4xx on attempt 1 for a valid delivery → Medium `non_2xx_on_retry`.
30. Burst dispatch spread ≤ 20 ms; otherwise "not simultaneous", not judged.
31. Burst: `non_atomic_insert` handler → High `concurrent_changed_state`.
32. Over budget is strict (`latency > budget`) [judge unit].
33. A uniformly slow handler is flagged `over_budget` (regression for the 3× median rule).
34. Same finding type across scenarios merges into one finding with all evidence (RD F2, F3).
35. Finding numbering follows severity, then first evidence.
36. Run status precedence: Aborted > findings ≥ Medium > Inconclusive > Passed [judge unit, table-driven].
37. Exit-code matrix: 0 / 1 / 2 / 3, `--fail-on`, `--fail-on-inconclusive` [G].
38. Agent killed during D3 → Aborted, D1–D2 kept, D3 "sent, no result", others "not run".
39. Target refused mid-run → delivery "Unreachable", run exit 2.
40. Same seed + same Observations → byte-identical Verdict JSON (`trap rejudge`).

**Probe and reset**
41. An unstable probe (value changes with no delivery) stops the run with the stability message.
42. An async handler is measured after its write lands (settle rule); no false negative within `settle`.
43. Probe non-zero exit, timeout or empty output → "probe failed", affected scenarios Inconclusive.
44. Reset failure stops the run (exit 2).
45. No reset hook → "Order-dependent" flag and Info.
46. A probe template resolves from the group for every event type in the run.
47. The probe value never leaves the machine (server receives "changed: yes/no" only) [C].

**Budgets and copy**
48. Each MVP provider resolves to the PB §1 budget and source tag; Standard Webhooks and unknown → 10 000 ms default.
49. No budget number is rendered without its source tag (copy linter over the UI and CLI).
50. PB §3 "Do NOT say" phrases never appear in any output (copy linter).
51. Forbidden-terms sweep (TT §3) returns nothing on product strings.

**CLI, CI, PR, JSON**
52. RD Run #3 renders TT §1.4.2 byte for byte (UTF-8, colour off) [G].
53. Same run, `--ascii` and `TERM=dumb` and a CJK locale render TT §1.4.3 [G].
54. No line exceeds 100 columns except workflow commands [G].
55. `NO_COLOR` and non-TTY remove all ANSI; `--color=always` restores it [G].
56. Non-TTY output is append-only, with no redraw sequences [G].
57. `GITHUB_ACTIONS=true` emits `::group::` and annotations matching TT §1.4.5 [G].
58. PR comment is edited in place on the second push (fake GitHub API) [C].
59. `--json` validates against `run.schema.json` for every RD run state [C].
60. MCP `get_run` returns the same findings as `--json` [C].
61. `trap test` from fixtures makes no non-loopback connection [I].

**Fixtures**
62. Save writes exactly the files in the preview, through the agent.
63. Scrubbed fixtures contain no value matching the scrub patterns; a residual match blocks the save.
64. Replaying a scrubbed fixture re-signs it and Once passes the official verifier.
65. A new capture with an added field marks fixtures "Shape drifted" with the path.

**Agent and run UI**
66. Pairing code is single-use, expires at its TTL, and binds to the guest session [C].
67. Agent version below minimum → "update needed", Run disabled [E2E].
68. Target not in allow-list → "Couldn't start the run" with the fix command [E2E].
69. Queued shows only if acceptance takes more than 300 ms [E2E].
70. Timeline rows appear in send order; findings insert by severity without reordering rows [E2E].
71. Auto-follow stops once the user scrolls or selects; "Follow live" appears [E2E].
72. Needs-secret draft unchecks re-signing scenarios and offers "Run only Once and Slow budget" [E2E].
73. Side-effect warning appears once before the first run that sends a duplicate [E2E].
74. Every Run state in UF Screen 4 renders its RD §6 copy, light and dark [V].

**Guest**
75. Guest session expires at 24 h (fake clock); expired URL shows only the expiry screen, with no data in the HTML or props [E2E].
76. 100th delivery is stored; 101st is answered identically and not stored; banner shows [E2E].
77. Run quota counts accepted runs only; the 4th run is refused with its copy [E2E].
78. Sign-in claims deliveries, runs and pairing in one transaction; the old guest token is rejected [I].
79. Workspace with both or neither principal violates the DB constraint [I].

**Accessibility**
80. axe-core: zero serious or critical violations on every screen and state, light and dark [A].
81. Token contrast table meets UF §7.1 in both themes (computed, not eyeballed) [A].
82. Keyboard-only path: landing → capture → Prove → draft → Run → open F2 → D3 inspector, with no traps [A].
83. Treegrid, tabs and toolbar follow the TT §3.4 ARIA patterns (one tab stop, roving tabindex) [A].
84. Reduced motion: no animation; final states shown; the landing demo shows its final frame [A, V].
85. Reflow at 320 px and 200 % zoom without loss of content or horizontal page scroll [A, V].
86. Greyscale screenshots still distinguish severity, signature state, pass/fail and over-budget [V].
87. Live region announcements: batched, polite, at most one per 5 s on capture; assertive only for agent disconnect [A].
88. Single-key shortcuts can be turned off and are inert in text inputs [A].

---

## 6. What cannot be validated without users, and the cheapest proxy

Field validation (Q13 interviews and prototype sessions) was waived (decision 31, option B). The items below were the questions those sessions were meant to answer.

| # | Unvalidated claim | Why tests can't settle it | Cheapest proxy | Signal to record |
| - | ----------------- | ------------------------- | -------------- | ---------------- |
| U1 | ≥ 40 % of first runs surface a finding users call **real** (VI success measure; VP H1 kill criterion < 20 %) | "Real" is a human judgement (VP §3: Real + fix-before-release yes) | **Dogfood on ≥ 10 open-source webhook handlers** chosen *before* running, by a rule (e.g. the first 10 GitHub repos with a Stripe or GitHub webhook route and > 50 stars), so the picker can't choose easy targets. Count a finding as "real" only if the **maintainers accept** an issue or PR for it. | Pre-registered repo list; finding rate (accepted findings ÷ repos); time to maintainer response |
| U2 | Level 1 alone finds real issues (H2) | Same | Same corpus, run L1 only first, before writing any probe | L1 finding rate; which scenarios produced them |
| U3 | False-positive share ≤ 20 % | Needs ground truth | Reference handlers (§4 D-12) give exact precision. The OSS corpus gives "finding rejected by maintainer" as an FP proxy | FP rate on the seeded corpus (target 0) and the rejection rate on the OSS corpus |
| U4 | One line of probe config is acceptable (Q5) | Acceptance is attitudinal | Time how long writing a stable probe takes, per dogfooded repo, for someone new to the repo. Count lines and failed stability checks | Minutes to a stable probe; % of repos where a one-line probe was enough |
| U5 | Install in < 30 s, aha in < 5 min (CE M4; VI) | Depends on user machines | Scripted fresh-VM runs (macOS, Ubuntu, Windows/WSL) in CI, from a clean shell to the first verdict | Wall time per OS; failure points |
| U6 | The Timetable run screen is understood in 10 s (TT §5) | Comprehension is human | **Keep the 10-second test** (it is the one user contact left). If 5 developers can't be recruited, use an unmoderated first-click/5-second test service with the same questions and pass marks, and label the result as weaker evidence | Per-question pass counts, verbatims |
| U7 | Severity ranking matches developer priorities (e.g. High vs Critical for Late; Medium for over budget) | Priority is a value judgement | Map each finding type to public incident reports or provider security advisories, and have A09 review the table. In the dogfood corpus, record which findings maintainers fixed first | Agreement between the severity table and the maintainers' fix order |
| U8 | Guest limits (24 h, 100, 3 runs) are enough for the aha | Usage-dependent | **1.x production usage data** (deliveries per inbox in the first 24 h, the distribution of inbox lifetime). It already exists and costs nothing new. Aggregate counts only | p50/p90 deliveries per anonymous inbox in 24 h |
| U9 | The default scenario set (all 8, about 45 s) is acceptable for a first run (UF §10.4) | Patience is human | Measure run duration on the dogfood corpus; compare against the "about 45 s" draft copy and the 5-minute aha | Median run duration per pack |
| U10 | Fix snippets actually fix the finding | Partly testable | Apply each snippet to the matching buggy reference handler and rerun: the finding must disappear (automatable) | Pass/fail per snippet × framework |
| U11 | "Secrets stay on your machine" is trusted | Trust is human | A published threat model plus a reproducible network-capture check in the repo (test 18). Trust itself stays unvalidated | — |
| U12 | Fixtures in CI become a habit (25 % at 30 days) | Only observable after launch | In the dogfood corpus, offer the CI check in each accepted PR and record how many maintainers keep it after 2 weeks | Retained CI checks ÷ offered |

**Residual risk to state plainly.** U1 and U2 are the kill criteria for the whole concept. With field validation waived, the dogfood corpus is the only evidence before build. A11 recommends the PO **pre-register** the following in the decisions log before the first dogfood run:

- the corpus selection rule;
- the "accepted by maintainers" definition;
- the VP thresholds (≥ 40 % go, < 20 % pivot).

This is the same freezing discipline VP §3 applied to the interviews.

---

*End of review. Nothing in this document is approved or decided; owners are named per item.*
