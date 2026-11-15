# QA static review: Timetable v2 (Claude Design)

- **Reviewer:** A11, QA (C3, independent review at the author's tier). The author's report was not used as evidence; every claim below was re-checked from the files.
- **Date:** 2026-11-14
- **Scope:** Design System `https://claude.ai/artifact/7n63VUDFZC31A1bRTJfwNY` (version `1794618712-36f8`) and Canvas `https://claude.ai/artifact/3ovs7azTxTzGFREDuBe6En` (version `1794618837-a405`), against `ux-timetable-v2.md`, `realistic-data.json`, `provider-budgets.md`, `logo-exploration.md` and the previous review `qa-static-review.md` (Timetable T1–T8).
- **Limit:** static review only. The artifacts could not be rendered on claude.ai from this environment (no signed-in session), so nothing below was seen in a browser. Render behaviour is inferred from the published canvas runtime (`artifact-type/dc-runtime.js`) and from running each board's `DCLogic` in Node.

## Verdict

**READY FOR PO VIEWING.** No Blocker, no Major. 7 Minor findings, all copy, documentation or hygiene; none affects rendering. The sprite risk the brief flagged as the main one resolves in favour of rendering (see "Sprite verdict").

## Method

1. Downloaded every `project/` file of both artifacts with Artifact `read` (`paths`) and ran `diff -r` against the local sources. Listed both artifacts' asset stores and compared each asset record in `design-system.json` with the store.
2. Read both `artifact-type/reference/format.md` files (canvas and system) and checked each board against them.
3. Wrote a Node harness (scratchpad, not shipped) that, for all 50 boards: extracts the template and `data-props`; runs `renderVals()` with the default props, every `enum` option, and every prop set an importer passes through `<dc-import>` (resolved from the importer's own values, recursively), 156 board/prop combinations in total; walks the template resolving every `{{hole}}`, `sc-if`, `sc-for` and handler; checks tag balance; follows each `<dc-import>` into the same "document"; then checks every `<use href>` against the `<symbol>` ids actually rendered in that document, duplicate ids, tab/panel pairing and treegrid focus.
4. Grep sweeps (Python, not the shell proxy) for the spec §3 terms, emoji, attribution, 1.x, PulseSend, trap-points geometry and network URLs.
5. Data: programmatic comparison of all 14 Run #3 deliveries and all 13 captures with `realistic-data.json`, plus manual checks of findings, budgets and guest data.
6. Contrast: recomputed WCAG 2.1 ratios from `tokens.json` for both themes.

## Issues

| # | Severity | File | Evidence | Fix |
| - | -------- | ---- | -------- | --- |
| V1 | Minor (hygiene) | system `project/design-system.json` `lastChange` | `"by": "Webhook Trap team", "via": "Claude Code"`. The system page shows `via` in its last-change line, so an AI tool name is visible to the PO. Spec §3.6 T8 asked for `by: "Webhook Trap design"`; `by` was changed to a different team string. | Set `via` to a neutral value (for example `"design pipeline"`) or leave it out (it is optional in the format); align `by` with T8 or record the change in the spec. If the server rewrites it on publish, list it as accepted. |
| V2 | Minor (meaning) | `StatesSheet.dc.html`, two error banners | "Couldn't start the run" and "Something went wrong on our side" use `tt-g-crit` (the Critical ■). The system reserves ■ for Critical findings; T6 already removed severity shapes from agent states for the same reason. A system error now carries the same shape as F1. | Use a neutral glyph in `ink` (for example `tt-g-blocked`) or none; keep the `conflict` top rule that marks the banner as an error. |
| V3 | Minor (copy accuracy) | `FindingCommentary`, `CliProveSummary`, `CliProveSummaryLight`, `CliProveAscii`, `CiLog`, `PrComment`, `PrCommentDark`, system `README.md` and `FindingCommentary` preview (F2 "Why it matters") | "retries failed or timed-out deliveries with backoff for up to 3 days" has no "in live mode". `provider-budgets.md` l.33/l.50: "for up to three days in live mode (three times over a few hours in a sandbox)". Run #3 runs in test mode. `Main`, `RunDraft` and the landing scenario rows already say "in live mode". | Append "in live mode" to the F2 sentence everywhere (one string; the 100-column CLI lines will need rewrapping). |
| V4 | Minor (spec drift) | `ux-timetable-v2.md` §1.4.3 (ASCII block, F3) | The spec still says "Stripe treats a slow response as failed and retries it. In this run, D8's timeout caused the retry D9". `CliProveAscii` correctly deviates and uses the safe wording from `provider-budgets.md` l.62–64, matching every other F3. The board is right; the spec is stale. | Update §1.4.3 (and any other §1.4 copy of F3) to the shipped wording, so a rebuild from the spec doesn't reintroduce the overclaim. |
| V5 | Minor (doc) | system `README.md` (and its canvas copy), Content fundamentals | "Stripe publishes no timeout, so Stripe's 10 000 ms is `default`." uses the possessive the same paragraph forbids for `default`. | "…so the 10 000 ms budget for Stripe is `default`." |
| V6 | Minor (spec deviation) | system `components/bundle.css` | `.tt-g` defaults to 12 × 12 px with `.tt-g-16` as the 16 px modifier. Spec §3.5 step 1 says keep `.tt-g { width: 16px; height: 16px; … }`. Boards use `tt-g-16` where 16 px is needed, so nothing is broken. | Accept as a deliberate density choice and note it in the Icons README, or follow the spec. |
| V7 | Minor (copy) | `Main.dc.html` Scenarios tab, `RunDraft`, `LandingDesktop` ("Retry after error" `what`) | "First delivery exceeds the budget (treated as failed), then Stripe's retry". This is the scenario's own rule, not a claim about Stripe, but "treated as failed" next to "Stripe's retry" can read as Stripe behaviour, which `provider-budgets.md` l.64 warns against. | "First delivery exceeds the budget, so the scenario treats it as failed and sends Stripe's retry, compressed to +2 s." |

Previous review T1–T8, re-checked:

| Old | Status now |
| --- | ---------- |
| T1 `data:` CSS masks | **Fixed.** No `data:` or `mask` in any shipped CSS or board (only in docs saying they are gone). Inline sprite in 21 boards and 11 previews. |
| T2 VERIFY copy | **Fixed.** 0 hits for the §3 sweep regex in `canvas/project` and `ds/project`. |
| T3 stale design-system record | **Fixed.** `canvas.json designSystems[0].version` = `1794618712-36f8` = the system's live version; canvas `ds/timetable/{tokens.json,tokens.css,components/bundle.css,README.md}` are byte-identical to the system's (same sha256). |
| T4 treegrid / tab ARIA | **Fixed.** See Coverage. |
| T5 agent `0.9.2` | **Fixed.** `RunDraft` (draft step 1 and G3 popover), `StatesSheet`, AgentPill README. |
| T6 severity glyphs on agent states | **Fixed** in AgentPill (`tt-g-blocked`, `tt-g-offline`, `tt-g-update`, ink). Related new case: V2. |
| T7 double font load | Accepted by spec; `@import` kept, canvas copy identical. |
| T8 `lastChange` attribution | **Partly fixed.** `by` is no longer "Claude"; `via` still names the tool (V1). |

## 1. Local vs published

- **Canvas:** 50 boards + `canvas.json` + `ds/timetable/` (4 files) published = local, `diff -r` reports no difference (55 files). The `artifact-type/`, `SKILL.md` and `index.html` files are the type's own.
- **System:** all 39 published `project/` files are identical to local. The 18 SVGs under `assets/` are not published as files: they live in the asset store, and `design-system.json` names each one by blob id. All 18 ids exist in the store with the recorded size and type (22 blobs in the store; the 4 extras are the retired trap-points files, unreferenced). The store copies are 11–17 bytes larger than the local files because the server expanded self-closing tags (`<path …/>` → `<path …></path>`); after normalising that, the Twice mark, Twice favicon and `tt-twice` icon blobs are identical to local, and the local mark equals `timetable-logos/svg/twice-mark-light.svg`.
- `index.html` and `artifact-type/*` were not compared (type-owned).

## 2. Format checks (every board)

| Check | Result |
| ----- | ------ |
| `<script src="./support.js">` | 50 / 50 |
| `<x-dc>` + one `data-dc-script` with valid JSON `data-props` | 50 / 50 |
| Tag balance (template parsed as a tree) | 0 mismatches, 0 unclosed, no self-closed non-SVG tags |
| Holes resolve (`{{…}}`, `sc-if value`, `sc-for list`, handlers) over 156 prop combinations | 0 undefined holes, 0 non-array lists, 0 non-function handlers; every `sc-if` has `hint-placeholder-val`, every `sc-for` `hint-placeholder-count` |
| `renderVals()` | runs without throwing in all combinations |
| `<dc-import>` targets exist, pass `theme`, carry `hint-size`, only pass declared props | 31 imports, all pass |
| Network | only `https://fonts.googleapis.com/css2?…Archivo…Martian+Mono…` (helmet, 50 boards; `@import` in bundle.css). No other `src`/`href` to http(s), no `/_blob` on the canvas (its asset store is empty and nothing references it). `stripe.com`, `webhooktrap.dev` appear only as text inside payloads and copy. |
| `/_blob` URLs | 2, both in the system's `Logo/preview.html` (`061aaf…` favicon light, `0c373e…` favicon dark); both exist in the system's store. |
| `canvas.json` | valid JSON; 50 boards = 50 files (none missing or unlisted); `order` starts with `Main.dc.html`; no board rectangles overlap; 8 `title1` notes in the §6.3 rows. |
| `tokens.json` (system and canvas copy) | valid JSON, list grammar (`color.themes` light/dark, flat `tokens`), aliases resolve (`on-ink`, `on-conflict`, `sev-critical`, `focus-ring`). `tokens.css` matches the values. |
| `design-system.json` | valid; `lastChange.at` 2026-11-14, note names the v2 changes and the Twice logo; asset groups Logos (8 Twice files) and Icons (10). See V1. |
| System previews | 16 / 16 start with the `<!-- @dsCard … -->` marker; no iframes; no CDN scripts. |
| Mobile roots | no fixed `height` or `overflow: hidden` on any board root. |

## 3. Sprite verdict: `<use href="#…">` inside an imported board

**Verdict: resolves. Not a render risk on the canvas.** Reasoning from the published `artifact-type/dc-runtime.js` (version served with `1794618837-a405`):

1. **Same document, no shadow DOM.** The runtime replaces the board's `<x-dc>` with a `div#dc-root` in the board's own document and renders into it with React (`createRoot(...).render`). `attachShadow` and `shadowRoot` occur 0 times in the runtime.
2. **Imports render inline.** `<dc-import>` compiles to `createElement(registry.getDC(name), props)` (function `Ee`): the sibling `.dc.html` is fetched, its template compiled, and the child component is mounted as a React child of the importer, inside the same root. No iframe is created for an import (`iframe` appears only in the runtime's tag blocklist).
3. **`href="#tt-g-…"` survives sanitising.** The URL check (`ae`/`le`) parses the value against an `https:` base, so a fragment resolves to `https:` and is allowed; interpolated values (`href="#{{s.icon}}"`) go through the same path.
4. **Ids are not rewritten.** No id-prefixing or scoping of attributes in the runtime, so `<symbol id="tt-g-high">` keeps its id.

Therefore a glyph in `DeliveryGraph` (imported by `Main` with `sprite="off"`) finds the `<symbol>` in `Main`'s sprite, because both are nodes of the same document. The harness confirms it statically: in every real document (each board with its defaults, plus every importer-supplied prop set) **0 of the `<use>` references are unresolved**. The only unresolved cases are synthetic: a component board viewed alone with `sprite` forced to `off`, which no canvas entry does. No document renders two sprites (every importer that has a sprite passes `sprite="off"`; the dark wrappers have none and let the child draw its own).

**Residual, unverified:** canvas thumbnails and exports (`capture.js`) were not exercised. If an export serialises one board at a time, the sprite travels with it; if it rasterises an imported child in isolation, glyphs could drop there only. Check one PNG export on first view.

## 4. Spec coverage

**19 new boards (§6.2): 19 / 19 present**, each in its §6.3 row: `GuestFirstCapture`, `GuestFirstCaptureDark`, `GuestProvePair`, `GuestPaired`, `GuestMobileCapture`, `GuestMobileCaptureDark`, `GuestMobileDetail`, `GuestMobilePair`, `CliProveLive`, `CliProveSummary`, `CliProveSummaryLight`, `CliProveAscii`, `CiLog`, `PrComment`, `PrCommentDark`, `RunFallbackTableFirst`, `ScenarioStrip`, `StateReading`, `FindingCommentary`.

**Changed boards (§6.1):** all present and changed as listed. Spot checks: `Main` has the strip (imported `ScenarioStrip`), bracket row in `DeliveryGraph`, `StateReading` panel, F2 as commentary, legend "finding"; `RunPassed`/`StatesSheet` carry the Passed copy and "What was checked"; `RunMobile` has the 2 × 4 strip and F2 commentary; `StatesSheet` has Needs secret; `LandingDesktop`/`LandingMobile` have the MIT line. `CliProveLive` is byte-for-byte §1.4.1 (diff of the rendered `<pre>` text: 0 lines). `CliProveAscii` differs from §1.4.3 only in F3, where the board is correct (V4).

**Must-fixes (§3):**

| Item | Result |
| ---- | ------ |
| §3 sweep regex (`VERIFY`, `TO CONFIRM`, `[LICEN`, `TBD`, `TODO`, `lorem`, `allowance`, `Allowance`, `survived duplicates`, `always retries`, `Jonah another`) | 0 hits in `canvas/project` and `ds/project`, including token and class names |
| Licence | "MIT licence · source on GitHub" in `LandingDesktop` and `LandingMobile`; "open source, MIT" in `RunDraft`. No star counts. |
| Budget source tags | Every 10 000 ms carries `default` or "(Webhook Trap default)", except the F3 title "Exceeded the 10 000 ms response budget", which §4 prescribes. No "Stripe's budget" in any board. Strings from §4's table found verbatim: `budget 10 000 ms · default` (graph, Main, RunMobile, Fallback, Landing), `Budget 10 000 ms · default` (table header), `196 ms of the 10 000 ms budget · default`, `196 ms · budget 10 000 ms · default`, `10 000 ms (Webhook Trap default)` (CLI, CI, PR, draft). BudgetSource popover: "Webhook Trap's default for Stripe…" + "Stripe doesn't publish a response-time limit." |
| Passed line | "8 of 8 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: state outside the probe, such as subscriptions.status." in `Main` (passed), `StatesSheet`, README, RunStatus preview; "No findings." and "What was checked" in `RunPassed`. |
| Treegrid | `CaptureDesktop` and `CaptureMobile` (and the four guest capture wrappers through them): container has no `tabindex`; header row of `columnheader`s (desktop); group rows `aria-level=1`, `aria-expanded`, `aria-setsize/posinset`, `gridcell`s with `aria-colspan="5"` + action cell (6 columns); delivery rows `aria-level=2` with `gridcell`s; exactly one row with `tabindex="0"` in every mode (receiving, quota, guest first, mobile); mobile rows are `div role="row"`, not links. |
| Tabs and panels | Every `role="tab"` controls an existing `tabpanel` whose `aria-labelledby` points back; selected tab's panel visible, others `hidden`; no panel uses `aria-label`. Checked in every document: DetailPane (3 + 3, incl. empty Decoded), Main (3 or 2 by state), CaptureWaiting and CaptureMobile waiting (5 + 5), mobile inspector Request/Response (2 + 2). Ids are board-prefixed (`dp-`, `cw-`, `cm-`, `rm-`, …) and `idp`-prefixed on imports: 0 duplicate ids in any document. Mobile bottom nav is `<nav aria-label="Main">` with `aria-current`. Strip is `role="toolbar"` with `aria-pressed`. Graph is `figure` + `svg role="img"` with `<desc>`. |
| No `data:` masks | None (see T1). |
| Twice logo | The Twice path `M0 3H16M0 12H12M1 3L10 12M6 3L14 11` + conflict square is in every top bar, lockup and favicon (12 boards, Logo and Cover previews, 8 uploaded files). 0 remnants of either trap-points geometry (`M0 6H16M4 6 11 13` or `M0 5H16M3 5L8 10H11`) in canvas or system files; the 4 retired files sit outside `project/` and their blobs are unreferenced. "Trap points" survives only as the name's story in the README and Logo README, as intended. |
| 16 px floor | Favicons 16 × 16; Logo preview shows 64 / 32 / 16 ("hard floor"); top-bar marks 20 px; lockups render the mark at 22 px (landing mobile header), 24 px (desktop header), 18 px (footer). Nothing below 16. |

## 5. Data fidelity (against `realistic-data.json`)

All checks pass. 30 values listed; the first two rows are programmatic over every record.

| # | Value | Data | Boards |
| - | ----- | ---- | ------ |
| 1 | 14 Run #3 deliveries: event, `+ms`, sent time, status, latency, `orders.count` before → after | `run.deliveries[0..13]` | `Main` (all 14 × 6 fields), `CliProveSummary` (all 14 × 3): 14 / 14 match |
| 2 | 13 captures: time, provider, event, size, signature state | `deliveries[0..12]` | `CaptureDesktop`, `CaptureMobile`: 13 / 13 match (e.g. #09 GitHub `pull_request` 686 B, invalid) |
| 3 | F1 severity | Critical | ■ F1 Critical everywhere; Critical shape used only for F1 / D10 / Tampered among findings (V2 is a non-finding use) |
| 4 | F2 severity | High | ▲ F2 High everywhere (CLI red, not bold, per §2.4) |
| 5 | F3 severity | Medium | △ F3 Medium |
| 6 | F4 severity | Info | ○ F4 Info |
| 7 | Counts | 1 / 1 / 1 / 1, 4 findings | "4 findings · 1 Critical · 1 High · 1 Medium · 1 Info" |
| 8 | Scenarios | 8, 4 passed | "Scenarios 8 · 4 passed · 4 with findings" |
| 9 | Duration | 40 960 ms | "41.0 s" |
| 10 | Run id | `run_01M75F1M56RSH0CVKCTFX4FQWC` | CLI ×3, CiLog, PrComment, Main |
| 11 | Stripe budget | 10 000, `default` | tagged `default` everywhere (§4) |
| 12 | Shopify / GitHub / Parcelbird budgets | 5 000 Shopify docs / 10 000 GitHub docs / 15 000 default | BudgetSource README; popover variant "From Shopify docs" |
| 13 | F3 wording | "over our budget only" (`provider-budgets.md` l.64) | "Stripe doesn't publish its timeout, so these are over our default budget, not proven failures at Stripe." (FindingCommentary, CLI, PR). Does not overclaim. See V3, V7 for neighbours. |
| 14 | F3 evidence | D8 11 840 (+1 840), D14 10 420 (+420) | match |
| 15 | F1 measured | 200 in 167 ms, 0 → 1, expected 400 | match |
| 16 | F2 measured | D3 196 ms, 1 → 2; D9 1 → 2 | match |
| 17 | Late | 400 in 41 ms, t − 360 s | match |
| 18 | Burst | 312–601 ms | match |
| 19 | Agent | `maya-mbp`, `0.9.2`, code `HXR-4K7` | match |
| 20 | Target | `http://localhost:3000/webhooks/stripe`, Express 4 | match |
| 21 | Probe / reset | `orders.count`, `npm run db:reset:webhooks` | match |
| 22 | Guest URL | `webhooktrap.dev/c/k7Qm2xVb9TfL` | Capture, Waiting, GuestMobilePair, RunDraft |
| 23 | Project URL | `…/c/Br7nDLk2Qm9x` | Capture, Detail, Main, RunDraft |
| 24 | Guest expiry | `23 h 49 m` | Capture desktop/mobile, RunDraft (Waiting shows "24 h · 0/100", a fresh session: consistent) |
| 25 | Group | `sub_1QH…4drI` | middle-truncated as specified |
| 26 | Agent lost | 13:57:40.912, D3 of 14 | `RunAborted` via `Main` |
| 27 | Scenario lines | `scenario_results[*].line` | CLI summary block identical |
| 28 | Signature #08 | no secret, Parcelbird | Capture, DetailPage08 |
| 29 | Secrets | `STRIPE_WEBHOOK_SECRET …3f9a` | AgentPill README, DetailPane |
| 30 | Levels | Level 1 transport, Level 2 probe | verdict meta and CLI header |

## 6. Contrast (recomputed from `tokens.json`, WCAG 2.1)

Text pairs (need 4.5:1). Light / dark. Every value equals the README to 2 decimals.

| Pair | Light | Dark | README |
| ---- | ----- | ---- | ------ |
| `ink` on `ground` | 16.08 | 15.04 | match |
| `ink` on `surface` | 14.97 | 13.75 | match |
| `ink-muted` on `ground` | 6.35 | 7.47 | match |
| `ink-muted` on `surface` | 5.91 | 6.83 | match |
| `conflict` on `ground` | 5.46 | 5.93 | match |
| `conflict` on `surface` | 5.08 | 5.42 | match |
| `now` on `ground` | 5.75 | 6.87 | match |
| `now` on `surface` | 5.35 | 6.28 | match |
| `pass` on `ground` | 5.30 | 8.46 | match |
| `pass` on `surface` | 4.93 | 7.73 | match |
| `sev-high` on `ground` | 5.39 | 7.99 | match |
| `sev-high` on `surface` | 5.02 | 7.30 | match |
| `sev-medium` on `ground` | 5.20 | 10.50 | match |
| `sev-medium` on `surface` | 4.84 | 9.60 | match (lowest text pair) |
| `sev-info` on `ground` | 5.59 | 8.34 | match |
| `sev-info` on `surface` | 5.20 | 7.62 | match |
| `on-ink` on `ink` | 16.08 | 15.04 | match |
| `on-conflict` on `conflict` | 5.46 | 5.93 | match |
| `ink` on `budget-fill` | 12.01 | 9.48 | match |
| `ink-muted` on `budget-fill` | 4.74 | 4.71 | match |

Mark pairs (need 3:1 unless decorative):

| Pair | Light | Dark | README |
| ---- | ----- | ---- | ------ |
| `rule-strong` on `ground` / `surface` | 3.49 / 3.25 | 3.94 / 3.60 | match |
| `budget-edge` on `budget-fill` | 3.89 | 6.62 | match |
| `budget-edge` on `ground` | 5.20 | 10.50 | match |
| `rule` on `ground` (decorative) | 1.42 | 1.57 | match |
| `budget-fill` on `ground` (meaning in edge + label) | 1.34 | 1.59 | match |
| `conflict` vs `sev-high` | 1.01 | 1.35 | match: shape-only distinction, as documented |
| `now` vs `sev-high` | 1.07 | 1.16 | README gives dark only (1.16); light 1.07 is also shape-only (the now line is a labelled vertical) |

No board hard-codes a colour (0 hex or `rgb()` values in board markup outside scripts); everything goes through the tokens, so these ratios cover all 50 boards.

## 7. Hygiene

| Check | Result |
| ----- | ------ |
| Emoji | 0 (scan of U+2600–27BF, U+1F000–1FAFF, U+FE0F across canvas and system) |
| AI attribution | 0 in visible copy. `design-system.json` `via: "Claude Code"` (V1). `canvas.json` holds the system's claude.ai URL, which is the required link, not attribution. |
| 1.x references | 0 (`1.x`, "Webhook Trap 1", `v1.0`); `v1` hits are the Stripe-Signature / Standard Webhooks scheme names, which are correct |
| PulseSend | 0 |
| Leftover placeholders | 0 (`TBD`, `TODO`, `lorem`, `[TO CONFIRM]`, `[LICEN…`) |
