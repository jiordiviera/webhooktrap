# UX · Timetable v2 (lead direction)

**Status:** PROPOSED for A00 and PO review · **Author:** A03 (UX/UI) · **Date:** 2026-11-14
**Decision this follows:** PO decision 23, *I · Timetable* leads, with borrowings from Crash Lab and Notation.
**Read with:** `ux-timetable.md` (v1, still the base: everything not changed here stands), `ux-foundations.md` (behaviour, states, a11y; wins on conflicts), `realistic-data.md` (the only content source), `fit-review.md` (A01), `qa-static-review.md` (A11). **When v1 and v2 disagree, v2 wins.**
**Built sources this changes:** `claude-design/timetable/ds/` (system, https://claude.ai/artifact/7n63VUDFZC31A1bRTJfwNY) and `claude-design/timetable/canvas/` (canvas, https://claude.ai/artifact/3ovs7azTxTzGFREDuBe6En).

Dimensions are CSS px at 1440 × 900 and 390 × 844. Wireframes show structure, not style.

---

## 0. Decisions at a glance

| # | Decision | Where |
| - | -------- | ----- |
| D1 | **"Budget" is the only word** for the provider's response-time limit, in UI copy, components, tokens and docs. "Allowance" is retired. | §3.2 |
| D2 | Every budget number carries its **source tag**: `Stripe docs`, `default` or `trap.yml`. "Stripe's budget" is only allowed when the source is the provider's docs. Until `provider-budgets.md` marks a value as provider-documented, the UI treats it as **`default`**. | §4 |
| D3 | Crash Lab's rig cells become the **Scenario strip**: 8 equal ruled cells directly **above the Delivery Graph**, in graph order, linked to graph segments by hover/focus and by the scenario icons that replace the graph's text label row. | §1.1 |
| D4 | Crash Lab's deformation gauge becomes the **State reading** ("Expected 1 · got 2"), the readout of the graph's **state station**: inline on the state rail, in the Deliveries table, as the inspector's bottom row and at the top of the mobile D3 sheet. | §1.2 |
| D5 | Notation's commentary becomes the **Finding commentary**: three labelled beats (*What happened · Why it matters · Fix*), Archivo only, no serif, no italics, no chess. The fix always states what the timetable should show after it. | §1.3 |
| D6 | One **Run record** text format serves CLI, CI logs and (as Markdown) PR comments: header → deliveries by scenario → scenario strip → findings in commentary voice → footer. ≤ 100 columns, ASCII fallback, 16-colour ANSI only. | §1.4 |
| D7 | Guest first minute: arrival shows **Not checked** honestly, plus what *can* be read without secrets; the detail pane carries one "Prove the handler" block with the only primary button and a *without / with the agent* table. Prove opens the draft with pairing live. | §2.1–2.3 |
| D8 | Glyphs move from `data:` CSS masks to an **inline SVG sprite** (`<use href="#tt-g-…">`), `currentColor`. | §3.5 |
| D9 | The Passed verdict claims only what was checked: *"8 of 8 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: state outside the probe, such as subscriptions.status."* | §3.3 |
| D10 | 10-second test on 5 developers, two rounds at most. Notation fallback only if Timetable's own table-first fallback also fails. | §5 |

---

## 1. Borrowings, translated into Timetable

Timetable's grammar stays: one family (Archivo) in widths and weights, Martian Mono for evidence, hairline rules and position instead of boxes, colour only for finding / now / budget / pass, terminals as the severity shape (■ Critical, ▲ High, △ Medium, ○ Info, ▬ pass). Each borrowing below is rebuilt in that grammar. Nothing is pasted: no yellow plates, hatch swatches, odometers, serif, italics, `??`, "move", "position" or "rig".

### 1.1 Scenario strip (from Crash Lab's rig cells)

**What it is.** The run at a glance: one cell per scenario, giving the scenario's result before anyone reads the graph. In timetable terms it is the **column-header row** of the timetable: the place where a printed timetable names each service and its notes.

**Where it sits.** Desktop: between the verdict line and the Delivery Graph, same left edge and width as the graph (1000 px), 8 cells × 125 px, 64 px tall. Cells are in **graph order** (left to right = the order scenarios ran). Cells are equal width; the graph's segments are not (they follow time), so the link between a cell and its segment is made three ways:

1. **Same order.** Cell 1 is the leftmost segment, cell 8 the rightmost.
2. **Same icon.** The graph's 40 px text label row is replaced by a 24 px row of **segment brackets** (a hairline bracket spanning the segment) with the scenario's 16 px icon centred on it (`tt-once`, `tt-twice`, …). Each strip cell shows the same icon before its name.
3. **Live link.** Hover or focus on a cell gives its segment a `surface` fill and draws its lines at 2.5 px; hover on a segment does the same to its cell.

The graph is now 24 (brackets) + 224 (4 stations × 56) + 16 (axis) = **264 px**. Above the lower zone: top bar 48 + run header 56 + verdict line 48 + strip 64 + graph 264 = **480 px**, leaving 420 px for the Deliveries table or Inspector at 900 px.

**Cell anatomy** (no box; cells are separated by vertical hairlines `rule`, the row has a `rule-strong` hairline below it):

```
┌ 125 ─────────────────┐
│ ⟍ Twice              │  line 1 · icon 12 + name · Archivo wdth 75 / 600 / 12 px · ink
│ ▲ F2 High            │  line 2 · terminal glyph 12 + result · Archivo wdth 87.5 / 650 / 13 px · severity colour (pass: ink)
│ expected 1 · got 2   │  line 3 · key reading · Martian Mono wdth 87.5 / 11 px · ink-muted
└──────────────────────┘
```

**Content for Run #3** (dataset §4.1 and §4.2; line 3 is derived from those rows):

| # | Line 1 | Line 2 | Line 3 | Accessible name |
| - | ------ | ------ | ------ | --------------- |
| 1 | Once | ▬ Pass | `0 → 1` | Once: pass. D1, orders.count 0 to 1. |
| 2 | Twice | ▲ F2 High | `expected 1 · got 2` | Twice: finding F2, High. D2, D3. orders.count expected 1, got 2. |
| 3 | Shuffled | ▬ Pass | `○ F4 Info · 0 → 1` | Shuffled: pass, with suggestion F4, Info. D4 to D6. |
| 4 | Late | ▬ Pass | `400 · 41 ms` | Late: pass. D7 rejected with 400 in 41 ms. |
| 5 | Retry after error | ▲ F2 High | `△ F3 · 11 840 ms` | Retry after error: findings F2, High, and F3, Medium. D8, D9. |
| 6 | Tampered | ■ F1 Critical | `expected 400 · got 200` | Tampered: finding F1, Critical. D10 returned 200, expected 400. |
| 7 | Burst | ▬ Pass | `312–601 ms` | Burst: pass. D11 to D13. |
| 8 | Slow budget | △ F3 Medium | `10 420 ms · +420` | Slow budget: finding F3, Medium. D14 took 10 420 ms, 420 over the budget. |

A cell with more than one finding shows the **worst** on line 2 and the next one, glyph + ID, on line 3 (Retry after error). The full words are in the accessible name, and the verdict line directly above acts as the legend (foundations §5.2 rule for dense rows).

**Behaviour.** Click or Enter on a cell: the graph segment stays highlighted, the Deliveries table scrolls to that scenario's rows, and the Inspector opens on the scenario's first finding delivery (Twice → D3, Retry → D9 for F2, Tampered → D10), or on its first delivery if it passed. The selected cell gets a `surface` fill and a 2 px `ink` rule under it (not a border box).

**States** (all driven by Main's existing `state` prop):

| Run state | Strip |
| --------- | ----- |
| Findings (#3) | As the table above. |
| Running (D9 of 14) | Cells 1–4 final (as above). Cell 5: line 2 `┃ Running` in `now`, line 3 `△ F3 · D9 sent`. Cells 6–8: line 2 `Waiting` in `ink-muted`, line 3 empty. |
| Passed (#4) | All 8: `▬ Pass`. Line 3 on Twice and Retry after error: `expected 1 · got 1`. Others: empty. |
| Aborted (#2) | Cell 1 `▬ Pass`. Cell 2: line 2 `⫽ Stopped` in `ink-muted`, line 3 `during D3 of 14`. Cells 3–8: `Not run`, glyph ⫽, `ink-muted`. |
| Inconclusive (#1) | Only the run's 2 scenarios (Once, Twice), left-aligned at the same cell width. Once: `▬ Pass`, line 3 `transport only`. Twice: line 2 `— Inconclusive`, line 3 `responses identical`. |
| Draft | **Hidden.** The setup's scenario rows are the list; the ghost graph stays. |
| Queued | All 8 cells `Waiting`. |

**Mobile 390.** After the verdict block and before the findings list: a **2 × 4 grid** of cells, 179 × 44 each, hairlines between. Line 1: glyph + scenario name left, result word right (`▲ Twice … F2 High`). Line 2: the key reading. Tapping a cell scrolls the Deliveries list to that scenario heading. On the mobile **landing** nothing changes.

**ARIA.** `<div role="toolbar" aria-label="Scenario results">` with 8 `<button type="button" aria-pressed>` (pressed = selected), roving tabindex, ← → move, Enter/Space select. Accessible names as in the table.

**Component:** `TT/ScenarioStrip` + `TT/ScenarioCell` (pass, finding by severity, multi-finding, running, waiting, stopped, not run, inconclusive, selected, hover).

### 1.2 State reading: "Expected 1 · got 2" (from Crash Lab's deformation gauge)

**What it is.** The readout of the graph's bottom station, *state*. The gauge's job (make `1 → 2` unmissable and say what was expected) moves to the place Timetable already prints state values. No odometer, no rolling digits, no flag: a number set in type, its expected value beside it, the severity terminal, and a bracket.

**Rule.** A state reading is **flagged** when the probe digest after a delivery differs from what that delivery should have left. Run #3 has three flagged readings, all from the dataset:

| Delivery | Before → after | Expected | Got | Terminal | Source |
| -------- | -------------- | -------- | --- | -------- | ------ |
| D3 | 1 → 2 | 1 | 2 | ▲ High (F2) | dataset §4.2 |
| D9 | 1 → 2 | 1 | 2 | ▲ High (F2) | dataset §4.2 |
| D10 | 0 → 1 | 0 | 1 | ■ Critical (F1) | F1 "expected: 400 and no change" |

Every other delivery's reading is unflagged and prints as before (`0 → 1`, `1 → 1`).

**Variants.**

1. **Graph, on the state rail (inline).** At the line's terminus: the after-value in Martian Mono 12 / 700, the severity terminal (12 px), then `expected 1` in Archivo wdth 75 / 600 / 11 px in the severity colour, set to the **right** of the terminal. The existing bracket in the same colour links back to the earlier delivery's value (D3 → D2's `1`, D9 → D8's `1`). D10 has no bracket (nothing earlier to compare with); its caption reads `expected 0`.
2. **Deliveries table, State column.** `1 → 2 · expected 1` with the terminal before it; unflagged rows show `0 → 1` only.
3. **Inspector, bottom row (panel).** The inspector reads top to bottom like the stations: Request (*sent*) and Response (*responded*) side by side, then the **State** row full width:

```
├ State ──────────────────────────────────────────────────────────────────────────────┤
│ State after D3 · orders.count                                     ▲ High · F2 ›     │  Archivo 12 / 600 ink-muted · right: glyph + word + finding link
│ Expected 1 · got 2                                                                  │  Martian Mono wdth 87.5, 28 px: "Expected 1 ·" ink 400, "got 2" sev-high 700
│ before 1 → after 2 · D2 left it at 1                                                │  Archivo 12 ink-muted, D2 is a link
│ Same request, same response, a different state.                                     │  caption, Archivo wdth 87.5 / 600 / 15 px, only when Compare is on (rule below)
└─────────────────────────────────────────────────────────────────────────────────────┘
```

   **Caption rule:** "Same request, same response, a different state." (Notation's best sentence, with Timetable's noun *state* instead of *position*) shows only when **Compare with** is on and the two deliveries have the same status and identical response body bytes but a different state result. D3 vs D2 qualifies. D9 vs D8 does not (D8 took 11 840 ms, so its "response" differs in the way that matters).

4. **Mobile D3 sheet, top.** Under the D3 path strip: the same three lines at 24 px numerals, then Request | Response tabs and the Compare switch.

**States.**

| Case | Reading |
| ---- | ------- |
| Flagged (D3) | `Expected 1 · got 2`, severity terminal, bracket |
| Unchanged as expected (passed #4, duplicate) | `Expected 1 · got 1`, ▬ in `pass`, caption-free; the line under reads "unchanged, as expected" |
| Not measured (inconclusive #1) | `—` in `ink-muted`, line under: "Not measured. Add a probe to see your state." with the yaml link |
| Not reached (aborted #2, D3) | `—`, line under: "Not measured: the agent was lost during D3." |

**Accessible name** (panel): "State after D3, orders.count: expected 1, got 2. Finding F2, High." The graph caption is `aria-hidden`; the table cell carries the same words.

**Landing hero.** The hero's state rail uses variant 1: `1`, `1`, then `2 ▲ expected 1` with the bracket back to D2. Motion: the caption sets with the ▲ at 2.6 s (v1 §3.1 timing).

**Component:** `TT/StateReading` (inline, cell, panel, sheet) × (flagged by severity, unchanged, not measured, not reached).

### 1.3 Finding commentary (from Notation's commentary voice)

**What it is.** The open finding's body, rewritten as three short beats. Notation's strength was voice: it states what happened, why it matters, and the better path, generously and without blame. Timetable keeps that order and that generosity and drops the chess: no "move", no annotation glyphs, no italics, no serif.

**Typography (Archivo only).**

| Element | Style |
| ------- | ----- |
| Finding title | Archivo wdth 87.5 / 650 / 18 px / 1.25, ink |
| Meta line | `▲ High · F2 · Level 2 · state probe · evidence D2 D3 · D8 D9` · Archivo 12 / ink-muted; glyph and word in the severity colour; D-refs are Martian Mono 12 links |
| Beat label | `What happened`, `Why it matters`, `Fix` · Archivo wdth 87.5 / 600 / 12 px, ink-muted, sentence case, 6 px above its text |
| Beat text | Archivo wdth 100 / 400 / 15 px / 1.45, ink, max 60 ch; ids, event types, digests and D-refs in Martian Mono 13 |
| Citation | its own line under *Why it matters*: Archivo 13, ink-muted, `Stripe docs › Webhooks › …` (paths, not URLs, until A01 supplies verified links) |
| Caption (optional) | Archivo wdth 87.5 / 600 / 15 px, ink, one per finding at most, above the beats |
| Fix code | `TT/FixSnippet` as in v1, then **Copy fix** and **Copy finding as JSON** |

Spacing: 12 px between beats. Labels stack above text at every width (the findings column is 440 px). The CLI uses a left gutter instead (§1.4).

**Voice rules.**

1. *What happened* opens with the delivery and the measurement, then the conclusion. Numbers before adjectives. The likely cause, when we have one, is its last sentence and says "Likely cause:".
2. The subject is the delivery or the handler ("Your handler returned 200"), never the person ("You broke").
3. *Why it matters* states provider behaviour, then its citation line, then the consequence **in this run** when there is one ("In this run, …"). A claim stops where the cited page stops.
4. *Fix* is one imperative sentence plus **what the timetable should show after the fix** ("After the fix, D3 and D9 should leave orders.count at 1."). This is Notation's "better move", translated as the path that would have cleared.
5. At most 3 sentences per beat and 25 words per sentence. No exclamation marks, no "simply" or "just", no railway or chess words.

**Copy (replaces dataset §4.4 "Why it matters" / "Likely cause" / "Summary" lines in UI; the Fix code blocks are unchanged):**

**F1 · Critical · Accepts tampered payload** · Level 1 · transport · evidence D10
- *What happened:* D10 changed `amount_paid` from 2400 to 0 and kept the original signature. Your handler returned 200 in 167 ms and created an order: `orders.count 0 → 1`, expected 400 and no change. Likely cause: Late (D7) was rejected, so the timestamp check runs; the signature check probably fails into a log line instead of a 400.
- *Why it matters:* Stripe signs the exact bytes it sends. If a failed check doesn't stop the request, anyone who can reach this URL can create paid orders.
  Citation: Stripe docs › Webhooks › Verify signatures
- *Fix:* Return 400 as soon as `constructEvent` throws, before any write. After the fix, D10 should get 400 and leave `orders.count` at 0. (Express snippet, dataset F1.)

**F2 · High · Duplicate delivery created a second order** · Level 2 · state probe · evidence D2 D3 · D8 D9
- *Caption:* Same request, same response, a different state.
- *What happened:* D3 delivered the same `invoice.paid` event as D2. Both got `200 {"received":true}`, but `orders.count` went 1 → 2, expected 1. The retry D9 did the same. Only the probe could see this.
- *Why it matters:* Stripe may deliver the same event more than once, and retries failed or timed-out deliveries with backoff for up to 3 days in live mode. Each duplicate creates another order.
  Citation: Stripe docs › Webhooks › Handle duplicate events · Retry behavior
- *Fix:* Record `event.id` before creating the order and return 200 early when it is already recorded. After the fix, D3 and D9 should leave `orders.count` at 1. (Express + Postgres snippet, dataset F2.)

**F3 · Medium · Exceeded the 10 000 ms response budget** · Level 1 · transport · evidence D8 · D14 (title per §4: "Exceeded Stripe's response budget" only if the source is `Stripe docs`)
- *What happened:* D8 took 11 840 ms (+1 840 over) and D14 10 420 ms (+420 over). The median of the other 8 `invoice.paid` deliveries was 200 ms. Likely cause: the handler waits for the Parcelbird label API before it responds (commit 'feat(shipping): create Parcelbird label on invoice.paid', captured #07).
- *Why it matters:* If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout. In this run, D8 went over our default budget and the scenario then sent the retry D9, which created the duplicate order in F2.
  Citation: Stripe docs › Webhooks › Quickly return a 2xx response
- *Fix:* Acknowledge with 200 first, then queue the label job. After the fix, D8 and D14 should respond within the budget. (Express snippet, dataset F3.)

**F4 · Info · Probe doesn't see subscription state** · Level 2 · suggestion · evidence D4 D5 D6
- *What happened:* Shuffled passed on `orders.count` (0 → 1 across the shuffled order), but `customer.subscription.updated` arrived first and `subscriptions.status` isn't probed.
- *Why it matters:* Out-of-order delivery usually breaks status fields, not counts.
  Citation: Stripe docs › Webhooks › Event ordering
- *Fix:* Add `subscriptions.status` to the probe in `trap.yml`. The next run then checks both. (trap.yml snippet, dataset F4.)

These fix two A01 common issues at the same time: F2 no longer says Stripe "always retries after a timeout" (issue 3), and "Each duplicate ships Jonah another bag of coffee" becomes "Each duplicate creates another order" (issue 6). **The Jonah sentence is removed from every product surface.** Jonah stays only as data (the group subtitle and the decoded payload).

**Ordering.** Findings stay in severity order while one is open: the open entry expands in place, so F1 Critical stays above an open F2 (the fault A01 found in Notation must not appear here).

**Component:** `TT/FindingCommentary` (open), used inside `TT/FindingEntry` (collapsed stays v1: glyph + word + ID, title, 2 lines).

### 1.4 Run record: the compact text format (from Notation's text-native record)

One format, four outputs: `trap prove` in a terminal (live, then summary), `trap test` in CI logs, `--format markdown` (PR comment and CI step summary), `--json` (unchanged machine output, the "Copy finding as JSON" twin). The Timetable idea survives in text as a **printed timetable**: deliveries listed under their scenario like services under a route, exact relative times, the state column, and the scenario strip as the summary row.

**Structure (always this order):** header (target, events, checks, budget with source) → deliveries grouped by scenario → scenario strip → findings in severity order, in commentary voice → footer (counts, run id, report link, next step).

**Rules.**

| Topic | Rule |
| ----- | ---- |
| Width | Hard maximum **100 columns**. Body text wraps at 96. Finding beats use an 18-column hanging indent. GitHub workflow command lines (`::error …`) are exempt: CI consumes them, nobody reads them raw. |
| Glyphs | `▬` pass · `■` Critical · `▲` High · `△` Medium · `○` Info · `┃` running/now · `⫽` stopped. Glyph never alone: the severity word follows. |
| ASCII fallback | Automatic when `--ascii`, `TERM=dumb`, the locale's charset isn't UTF-8, or the locale is CJK (these glyphs are East Asian *Ambiguous* width and render double there). Map: `▬`→`[-]`, `■`→`[C]`, `▲`→`[H]`, `△`→`[M]`, `○`→`[I]`, `┃`→`|`, `⫽`→`//`, `→`→`->`, `·`→`|`, `×`→`x`, `–` and `−`→`-`, `′`→`'`, `›`→`>`, `…`→`...`. |
| Colour | 16-colour ANSI only, so the user's terminal theme owns the palette: Critical bold red, High red, Medium yellow, Info blue, pass green, `Running` magenta, secondary text dim. Colour is applied to the glyph and severity word only. Off when `NO_COLOR` is set or output isn't a TTY (unless `--color=always`). Meaning never depends on it. |
| Numbers | Thousands separator is a plain space (`11 840 ms`), not a thin space, so columns align in every font. Raw integers in `--json`. |
| Times | Relative `+ms` from run start. Absolute timestamps only in `--verbose` and JSON. |
| Live (TTY) | Rows append as responses arrive. A finding prints a `new finding` line under the delivery that produced it. One status line at the bottom is redrawn (≤ 4 times per second) and replaced by the summary at the end. |
| Non-TTY (CI) | Append-only, no redraw, no status line. Delivery rows go inside a collapsible log group where the CI supports it. |
| Exit codes | `0` passed, or findings below `--fail-on` · `1` findings at or above `--fail-on` · `2` run incomplete (aborted, start failed, target unreachable) · `3` usage or config error. **Inconclusive exits 0 but is never printed as passed** ("Inconclusive (not passed)"); `--fail-on-inconclusive` makes it exit 1. |
| Fail threshold | `--fail-on high` by default (Critical and High fail). Medium is reported, not failing, because CI runner latency isn't the provider's network. |
| Names | `trap`, `webhooktrap.dev` and `@webhooktrap/agent` are the dataset's placeholders (pending brand and Q3). Flags and subcommands below (`prove --group`, `show F1`, `test --fail-on`) are PROPOSED with the CLI prototype (Q13). |

#### 1.4.1 `trap prove`, live (TTY, at "Running · D9 of 14")

Dataset Run #3. The budget line shows the `default` variant (§4); with a provider-documented value it reads `budget    10 000 ms (Stripe docs › …)` with the path from `provider-budgets.md`.

~~~text
$ trap prove --group sub_1QH3UF1HXb5oPQT3wfdt4drI

Webhook Trap · Run #3 · Prove invoice.paid · renewal
  target    http://localhost:3000/webhooks/stripe via maya-mbp · Express 4
  events    #01 invoice.paid · #03 payment_intent.succeeded · #04 customer.subscription.updated
  checks    Level 1 transport · Level 2 probe orders.count · reset npm run db:reset:webhooks
  budget    10 000 ms (Webhook Trap default)

    D    +ms      event                          http    latency  state  result
  Once
    D1        +0  invoice.paid                   200     184 ms  0 → 1  ▬ pass
  Twice
    D2    +1 450  invoice.paid                   200     212 ms  0 → 1  ▬ pass
    D3    +1 950  invoice.paid = D2′             200     196 ms  1 → 2  ▲ F2 High, expected 1
          new finding  ▲ F2 High · Duplicate delivery created a second order
  Shuffled
    D4    +4 210  customer.subscription.updated  200     143 ms  0 → 0  ▬ pass
    D5    +4 380  payment_intent.succeeded       200     121 ms  0 → 0  ▬ pass
    D6    +4 540  invoice.paid                   200     205 ms  0 → 1  ▬ pass, ○ F4 Info
          new finding  ○ F4 Info · Probe doesn't see subscription state
  Late
    D7    +6 820  invoice.paid  t − 360 s        400      41 ms  0 → 0  ▬ pass (rejected)
  Retry after error
    D8    +8 900  invoice.paid                   200  11 840 ms  0 → 1  △ F3 Medium, +1 840 over
          new finding  △ F3 Medium · Exceeded the 10 000 ms response budget
    D9   +22 740  invoice.paid = D8′ (retry)       …                    waiting for the response

  ┃ Running · D9 of 14 · Retry after error · 22.7 s · ▲ F2 High · △ F3 Medium · ○ F4 Info
~~~

#### 1.4.2 `trap prove`, summary (TTY, after the run; the status line is replaced)

The delivery rows above stay on screen, completed:

~~~text
    D    +ms      event                          http    latency  state  result
  Once
    D1        +0  invoice.paid                   200     184 ms  0 → 1  ▬ pass
  Twice
    D2    +1 450  invoice.paid                   200     212 ms  0 → 1  ▬ pass
    D3    +1 950  invoice.paid = D2′             200     196 ms  1 → 2  ▲ F2 High, expected 1
          new finding  ▲ F2 High · Duplicate delivery created a second order
  Shuffled
    D4    +4 210  customer.subscription.updated  200     143 ms  0 → 0  ▬ pass
    D5    +4 380  payment_intent.succeeded       200     121 ms  0 → 0  ▬ pass
    D6    +4 540  invoice.paid                   200     205 ms  0 → 1  ▬ pass, ○ F4 Info
          new finding  ○ F4 Info · Probe doesn't see subscription state
  Late
    D7    +6 820  invoice.paid  t − 360 s        400      41 ms  0 → 0  ▬ pass (rejected)
  Retry after error
    D8    +8 900  invoice.paid                   200  11 840 ms  0 → 1  △ F3 Medium, +1 840 over
          new finding  △ F3 Medium · Exceeded the 10 000 ms response budget
    D9   +22 740  invoice.paid = D8′ (retry)     200     205 ms  1 → 2  ▲ F2 High, expected 1
  Tampered
    D10  +25 110  invoice.paid  body changed     200     167 ms  0 → 1  ■ F1 Critical, expected 0
          new finding  ■ F1 Critical · Accepts tampered payload
  Burst
    D11  +27 480  invoice.paid                   200     601 ms  0 → 1  ▬ pass
    D12  +27 480  payment_intent.succeeded       200     312 ms  1 → 1  ▬ pass
    D13  +27 480  customer.subscription.updated  200     448 ms  1 → 1  ▬ pass
  Slow budget
    D14  +30 160  invoice.paid                   200  10 420 ms  0 → 1  △ F3 Medium, +420 over
~~~

Then the summary:

~~~text
Scenarios  8 · 4 passed · 4 with findings
  ▬ Once               pass                 200 in 184 ms · orders.count 0 → 1
  ▲ Twice              F2 High              200, 200 · orders.count 1 → 2 on D3, expected 1
  ▬ Shuffled           pass · F4 Info       200 × 3 · orders.count 0 → 1 · status not probed
  ▬ Late               pass                 400 in 41 ms · rejected as expected
  ▲ Retry after error  F2 High · F3 Medium  D8 11 840 ms → retry D9 → orders.count 1 → 2
  ■ Tampered           F1 Critical          200 in 167 ms · accepted a changed body
  ▬ Burst              pass                 200 × 3 · 312–601 ms · orders.count 0 → 1
  △ Slow budget        F3 Medium            10 420 ms · 420 ms over budget

■ F1 Critical · Accepts tampered payload                                                     D10
  What happened   D10 changed amount_paid from 2400 to 0 and kept the original signature. Your
                  handler returned 200 in 167 ms and created an order: orders.count 0 → 1,
                  expected 400 and no change. Likely cause: Late (D7) was rejected, so the
                  timestamp check runs; the signature check probably fails into a log line
                  instead of a 400.
  Why it matters  Stripe signs the exact bytes it sends. If a failed check doesn't stop the
                  request, anyone who can reach this URL can create paid orders.
                  Stripe docs › Webhooks › Verify signatures
  Fix             Return 400 as soon as constructEvent throws, before any write. After the fix,
                  D10 should get 400 and leave orders.count at 0.
                  Snippet (Express): trap show F1

▲ F2 High · Duplicate delivery created a second order                              D2 D3 · D8 D9
  What happened   D3 delivered the same invoice.paid event as D2. Both got 200
                  {"received":true}, but orders.count went 1 → 2, expected 1. The retry D9 did
                  the same. Same request, same response, a different state: only the probe could
                  see this.
  Why it matters  Stripe may deliver the same event more than once, and retries failed or
                  timed-out deliveries with backoff for up to 3 days in live mode. Each
                  duplicate creates another order.
                  Stripe docs › Webhooks › Handle duplicate events · Retry behavior
  Fix             Record event.id before creating the order and return 200 early when it is
                  already recorded. After the fix, D3 and D9 should leave orders.count at 1.
                  Snippet (Express + Postgres): trap show F2

△ F3 Medium · Exceeded the 10 000 ms response budget                                    D8 · D14
  What happened   D8 took 11 840 ms (+1 840 over) and D14 10 420 ms (+420 over). The median of
                  the other 8 invoice.paid deliveries was 200 ms. Likely cause: the handler
                  waits for the Parcelbird label API before it responds.
  Why it matters  If Stripe times out waiting, the delivery counts as failed and is retried.
                  Stripe doesn't publish the timeout. In this run, D8 went over our default
                  budget and the scenario then sent the retry D9, which created the duplicate
                  order in F2.
                  Stripe docs › Webhooks › Quickly return a 2xx response
  Fix             Acknowledge with 200 first, then queue the label job. After the fix, D8 and
                  D14 should respond within the budget.
                  Snippet (Express): trap show F3

○ F4 Info · Probe doesn't see subscription state                                        D4 D5 D6
  What happened   Shuffled passed on orders.count (0 → 1 across the shuffled order), but
                  customer.subscription.updated arrived first and subscriptions.status isn't
                  probed.
  Why it matters  Out-of-order delivery usually breaks status fields, not counts.
                  Stripe docs › Webhooks › Event ordering
  Fix             Add subscriptions.status to the probe in trap.yml. The next run then checks
                  both.
                  Snippet (trap.yml): trap show F4

4 findings · 1 Critical · 1 High · 1 Medium · 1 Info
8 scenarios · 14 deliveries · 41.0 s · run_01M75F1M56RSH0CVKCTFX4FQWC
  report    https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC
  next      trap show F1   (start with the Critical finding)
~~~

Exit code 1 (F1 and F2 are at or above High).

#### 1.4.3 ASCII fallback (same run, `--ascii`)

~~~text
$ trap prove --group sub_1QH3UF1HXb5oPQT3wfdt4drI

Webhook Trap | Run #3 | Prove invoice.paid | renewal
  target    http://localhost:3000/webhooks/stripe via maya-mbp | Express 4
  events    #01 invoice.paid | #03 payment_intent.succeeded | #04 customer.subscription.updated
  checks    Level 1 transport | Level 2 probe orders.count | reset npm run db:reset:webhooks
  budget    10 000 ms (Webhook Trap default)

    D    +ms      event                          http    latency  state   result
  Once
    D1        +0  invoice.paid                   200     184 ms  0 -> 1  [-] pass
  Twice
    D2    +1 450  invoice.paid                   200     212 ms  0 -> 1  [-] pass
    D3    +1 950  invoice.paid = D2'             200     196 ms  1 -> 2  [H] F2 High, expected 1
          new finding  [H] F2 High | Duplicate delivery created a second order
    ...

Scenarios  8 | 4 passed | 4 with findings
  [-] Once               pass                 200 in 184 ms | orders.count 0 -> 1
  [H] Twice              F2 High              200, 200 | orders.count 1 -> 2 on D3, expected 1
  [-] Shuffled           pass | F4 Info       200 x 3 | orders.count 0 -> 1 | status not probed
  [-] Late               pass                 400 in 41 ms | rejected as expected
  [H] Retry after error  F2 High | F3 Medium  D8 11 840 ms -> retry D9 -> orders.count 1 -> 2
  [C] Tampered           F1 Critical          200 in 167 ms | accepted a changed body
  [-] Burst              pass                 200 x 3 | 312-601 ms | orders.count 0 -> 1
  [M] Slow budget        F3 Medium            10 420 ms | 420 ms over budget

[C] F1 Critical | Accepts tampered payload                                                     D10
  What happened   D10 changed amount_paid from 2400 to 0 and kept the original signature. Your
                  handler returned 200 in 167 ms and created an order: orders.count 0 -> 1,
                  expected 400 and no change. Likely cause: Late (D7) was rejected, so the
                  timestamp check runs; the signature check probably fails into a log line
                  instead of a 400.
  Why it matters  Stripe signs the exact bytes it sends. If a failed check doesn't stop the
                  request, anyone who can reach this URL can create paid orders.
                  Stripe docs > Webhooks > Verify signatures
  Fix             Return 400 as soon as constructEvent throws, before any write. After the fix,
                  D10 should get 400 and leave orders.count at 0.
                  Snippet (Express): trap show F1

[H] F2 High | Duplicate delivery created a second order                              D2 D3 | D8 D9
  What happened   D3 delivered the same invoice.paid event as D2. Both got 200
                  {"received":true}, but orders.count went 1 -> 2, expected 1. The retry D9 did
                  the same. Same request, same response, a different state: only the probe could
                  see this.
  Why it matters  Stripe may deliver the same event more than once, and retries failed or
                  timed-out deliveries with backoff for up to 3 days in live mode. Each
                  duplicate creates another order.
                  Stripe docs > Webhooks > Handle duplicate events | Retry behavior
  Fix             Record event.id before creating the order and return 200 early when it is
                  already recorded. After the fix, D3 and D9 should leave orders.count at 1.
                  Snippet (Express + Postgres): trap show F2

[M] F3 Medium | Exceeded the 10 000 ms response budget                                    D8 | D14
  What happened   D8 took 11 840 ms (+1 840 over) and D14 10 420 ms (+420 over). The median of
                  the other 8 invoice.paid deliveries was 200 ms. Likely cause: the handler
                  waits for the Parcelbird label API before it responds.
  Why it matters  If Stripe times out waiting, the delivery counts as failed and is retried.
                  Stripe doesn't publish the timeout. In this run, D8 went over our default
                  budget and the scenario then sent the retry D9, which created the duplicate
                  order in F2.
                  Stripe docs > Webhooks > Quickly return a 2xx response
  Fix             Acknowledge with 200 first, then queue the label job. After the fix, D8 and
                  D14 should respond within the budget.
                  Snippet (Express): trap show F3

[I] F4 Info | Probe doesn't see subscription state                                        D4 D5 D6
  What happened   Shuffled passed on orders.count (0 -> 1 across the shuffled order), but
                  customer.subscription.updated arrived first and subscriptions.status isn't
                  probed.
  Why it matters  Out-of-order delivery usually breaks status fields, not counts.
                  Stripe docs > Webhooks > Event ordering
  Fix             Add subscriptions.status to the probe in trap.yml. The next run then checks
                  both.
                  Snippet (trap.yml): trap show F4

4 findings | 1 Critical | 1 High | 1 Medium | 1 Info
8 scenarios | 14 deliveries | 41.0 s | run_01M75F1M56RSH0CVKCTFX4FQWC
  report    https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC
  next      trap show F1   (start with the Critical finding)
~~~

#### 1.4.4 Other verdicts (summary block only)

~~~text
Webhook Trap · Run #4 · Passed
8 of 8 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: state outside the
probe, such as subscriptions.status.
  ▬ Once  ▬ Twice  ▬ Shuffled  ▬ Late  ▬ Retry after error  ▬ Tampered  ▬ Burst  ▬ Slow budget
Duplicates left orders.count at 1 → 1 · slowest response 238 ms
~~~

~~~text
Webhook Trap · Run #2 · Aborted
Agent maya-mbp disconnected at 13:57:40.912 during D3 of 14. D1–D2 and their results are kept.
Reconnect maya-mbp to run the rest.
~~~

Exit code 2.

~~~text
Webhook Trap · Run #1 · Inconclusive (not passed)
Responses are identical, but we can't see your database. Add a probe to check for duplicate side
effects. In trap.yml:
  probe:
    - as: orders.count
      sql: select count(*) from orders where stripe_invoice_id = '{{invoice.id}}'
~~~

Exit code 0 (1 with `--fail-on-inconclusive`).

#### 1.4.5 CI log (`trap test` in a GitHub Actions job)

Offline, from the saved fixtures (dataset §4.5). For comparability the sample reuses Run #3's measurements. The lines between `::group::` and `::endgroup::` are byte-identical to §1.4.2's delivery rows; the summary is byte-identical to §1.4.2's summary.

~~~text
$ trap test --fail-on high
Webhook Trap · trap test · 3 fixtures · 8 scenarios (Stripe pack defaults)
  target    http://localhost:3000/webhooks/stripe · sent from this machine, no agent
  fixtures  fixtures/stripe/invoice.paid.json · payment_intent.succeeded.json
            customer.subscription.updated.json · trap.yml
  checks    Level 1 transport · Level 2 probe orders.count · reset npm run db:reset:webhooks
  signing   STRIPE_WEBHOOK_SECRET from the environment
  budget    10 000 ms (Webhook Trap default)
  fails on  High and above. Medium and Info are reported, not failing.

::group::Deliveries · 14
    D    +ms      event                          http    latency  state  result
  Once
    D1        +0  invoice.paid                   200     184 ms  0 → 1  ▬ pass
  Twice
    D2    +1 450  invoice.paid                   200     212 ms  0 → 1  ▬ pass
    D3    +1 950  invoice.paid = D2′             200     196 ms  1 → 2  ▲ F2 High, expected 1
          new finding  ▲ F2 High · Duplicate delivery created a second order
  Shuffled
    D4    +4 210  customer.subscription.updated  200     143 ms  0 → 0  ▬ pass
    D5    +4 380  payment_intent.succeeded       200     121 ms  0 → 0  ▬ pass
    D6    +4 540  invoice.paid                   200     205 ms  0 → 1  ▬ pass, ○ F4 Info
          new finding  ○ F4 Info · Probe doesn't see subscription state
  Late
    D7    +6 820  invoice.paid  t − 360 s        400      41 ms  0 → 0  ▬ pass (rejected)
  Retry after error
    D8    +8 900  invoice.paid                   200  11 840 ms  0 → 1  △ F3 Medium, +1 840 over
          new finding  △ F3 Medium · Exceeded the 10 000 ms response budget
    D9   +22 740  invoice.paid = D8′ (retry)     200     205 ms  1 → 2  ▲ F2 High, expected 1
  Tampered
    D10  +25 110  invoice.paid  body changed     200     167 ms  0 → 1  ■ F1 Critical, expected 0
          new finding  ■ F1 Critical · Accepts tampered payload
  Burst
    D11  +27 480  invoice.paid                   200     601 ms  0 → 1  ▬ pass
    D12  +27 480  payment_intent.succeeded       200     312 ms  1 → 1  ▬ pass
    D13  +27 480  customer.subscription.updated  200     448 ms  1 → 1  ▬ pass
  Slow budget
    D14  +30 160  invoice.paid                   200  10 420 ms  0 → 1  △ F3 Medium, +420 over
::endgroup::

Scenarios  8 · 4 passed · 4 with findings
  ▬ Once               pass                 200 in 184 ms · orders.count 0 → 1
  ▲ Twice              F2 High              200, 200 · orders.count 1 → 2 on D3, expected 1
  ▬ Shuffled           pass · F4 Info       200 × 3 · orders.count 0 → 1 · status not probed
  ▬ Late               pass                 400 in 41 ms · rejected as expected
  ▲ Retry after error  F2 High · F3 Medium  D8 11 840 ms → retry D9 → orders.count 1 → 2
  ■ Tampered           F1 Critical          200 in 167 ms · accepted a changed body
  ▬ Burst              pass                 200 × 3 · 312–601 ms · orders.count 0 → 1
  △ Slow budget        F3 Medium            10 420 ms · 420 ms over budget

■ F1 Critical · Accepts tampered payload                                                     D10
  What happened   D10 changed amount_paid from 2400 to 0 and kept the original signature. Your
                  handler returned 200 in 167 ms and created an order: orders.count 0 → 1,
                  expected 400 and no change. Likely cause: Late (D7) was rejected, so the
                  timestamp check runs; the signature check probably fails into a log line
                  instead of a 400.
  Why it matters  Stripe signs the exact bytes it sends. If a failed check doesn't stop the
                  request, anyone who can reach this URL can create paid orders.
                  Stripe docs › Webhooks › Verify signatures
  Fix             Return 400 as soon as constructEvent throws, before any write. After the fix,
                  D10 should get 400 and leave orders.count at 0.
                  Snippet (Express): trap show F1

▲ F2 High · Duplicate delivery created a second order                              D2 D3 · D8 D9
  What happened   D3 delivered the same invoice.paid event as D2. Both got 200
                  {"received":true}, but orders.count went 1 → 2, expected 1. The retry D9 did
                  the same. Same request, same response, a different state: only the probe could
                  see this.
  Why it matters  Stripe may deliver the same event more than once, and retries failed or
                  timed-out deliveries with backoff for up to 3 days in live mode. Each
                  duplicate creates another order.
                  Stripe docs › Webhooks › Handle duplicate events · Retry behavior
  Fix             Record event.id before creating the order and return 200 early when it is
                  already recorded. After the fix, D3 and D9 should leave orders.count at 1.
                  Snippet (Express + Postgres): trap show F2

△ F3 Medium · Exceeded the 10 000 ms response budget                                    D8 · D14
  What happened   D8 took 11 840 ms (+1 840 over) and D14 10 420 ms (+420 over). The median of
                  the other 8 invoice.paid deliveries was 200 ms. Likely cause: the handler
                  waits for the Parcelbird label API before it responds.
  Why it matters  If Stripe times out waiting, the delivery counts as failed and is retried.
                  Stripe doesn't publish the timeout. In this run, D8 went over our default
                  budget and the scenario then sent the retry D9, which created the duplicate
                  order in F2.
                  Stripe docs › Webhooks › Quickly return a 2xx response
  Fix             Acknowledge with 200 first, then queue the label job. After the fix, D8 and
                  D14 should respond within the budget.
                  Snippet (Express): trap show F3

○ F4 Info · Probe doesn't see subscription state                                        D4 D5 D6
  What happened   Shuffled passed on orders.count (0 → 1 across the shuffled order), but
                  customer.subscription.updated arrived first and subscriptions.status isn't
                  probed.
  Why it matters  Out-of-order delivery usually breaks status fields, not counts.
                  Stripe docs › Webhooks › Event ordering
  Fix             Add subscriptions.status to the probe in trap.yml. The next run then checks
                  both.
                  Snippet (trap.yml): trap show F4

4 findings · 1 Critical · 1 High · 1 Medium · 1 Info
8 scenarios · 14 deliveries · 41.0 s · run_01M75F1M56RSH0CVKCTFX4FQWC
  report    https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC
  next      trap show F1   (start with the Critical finding)

::error title=F1 Critical · Accepts tampered payload::D10 changed amount_paid from 2400 to 0 and kept the original signature. The handler returned 200 and created an order. Run trap show F1 for the fix.
::error title=F2 High · Duplicate delivery created a second order::D3 (same event as D2) and the retry D9 each took orders.count 1 → 2, expected 1. Run trap show F2 for the fix.
::warning title=F3 Medium · Exceeded the 10 000 ms response budget::D8 11 840 ms (+1 840 over), D14 10 420 ms (+420 over).
::notice title=F4 Info · Probe doesn't see subscription state::subscriptions.status isn't probed. Run trap show F4 for the trap.yml line.
Failed: 2 findings at High or above (F1 Critical, F2 High). F3 and F4 don't fail this job.
Not uploaded: results stay in this log. Add --upload to keep run history.
~~~

The job exits 1. GitHub shows the `::group::` as a collapsed "Deliveries · 14" section and turns the four workflow commands into annotations on the run and the PR checks tab (2 errors, 1 warning, 1 notice). Other CI systems print the same text; the workflow commands appear as plain lines only when `--ci github` is set (auto-detected from `GITHUB_ACTIONS=true`).

#### 1.4.6 PR comment (Markdown)

Posted by the GitHub integration (or written to `$GITHUB_STEP_SUMMARY`) from `trap test --format markdown`. **One comment per PR, edited in place on every push** (found by the hidden marker on line 1), never a new comment per push. Critical and High findings are open; Medium and Info are collapsed. Glyphs are kept (GitHub renders them); every glyph has its word.

~~~markdown
<!-- webhooktrap:pr-comment -->
### Webhook Trap · 4 findings · check failed

**■ 1 Critical · ▲ 1 High · △ 1 Medium · ○ 1 Info** · 8 scenarios · 14 deliveries · 41.0 s · fails on High and above

`trap test` against `http://localhost:3000/webhooks/stripe` · Level 1 transport + Level 2 probe `orders.count` · budget 10 000 ms (Webhook Trap default)

| | Scenario | Result | Reading |
| :-: | --- | --- | --- |
| ▬ | Once | Pass | 200 in 184 ms · orders.count 0 → 1 |
| ▲ | Twice | **F2 High** | 200, 200 · orders.count 1 → 2 on D3, expected 1 |
| ▬ | Shuffled | Pass · F4 Info | 200 × 3 · orders.count 0 → 1 · status not probed |
| ▬ | Late | Pass | 400 in 41 ms · rejected as expected |
| ▲ | Retry after error | **F2 High** · F3 Medium | D8 11 840 ms → retry D9 → orders.count 1 → 2 |
| ■ | Tampered | **F1 Critical** | 200 in 167 ms · accepted a changed body |
| ▬ | Burst | Pass | 200 × 3 · 312–601 ms · orders.count 0 → 1 |
| △ | Slow budget | F3 Medium | 10 420 ms · 420 ms over budget |

<details open>
<summary><b>■ F1 Critical · Accepts tampered payload</b> · D10</summary>

**What happened.** D10 changed `amount_paid` from 2400 to 0 and kept the original signature. Your handler returned 200 in 167 ms and created an order: `orders.count 0 → 1`, expected 400 and no change. Likely cause: Late (D7) was rejected, so the timestamp check runs; the signature check probably fails into a log line instead of a 400.

**Why it matters.** Stripe signs the exact bytes it sends. If a failed check doesn't stop the request, anyone who can reach this URL can create paid orders.
<sub>Stripe docs › Webhooks › Verify signatures</sub>

**Fix.** Return 400 as soon as `constructEvent` throws, before any write. After the fix, D10 should get 400 and leave `orders.count` at 0.

```js
app.post('/webhooks/stripe', express.raw({ type: 'application/json' }), (req, res) => {
  let event;
  try {
    event = stripe.webhooks.constructEvent(req.body, req.get('stripe-signature'), endpointSecret);
  } catch (err) {
    return res.status(400).send('Invalid signature'); // stop here, don't log and continue
  }
  // ...handle event
});
```
</details>

<details open>
<summary><b>▲ F2 High · Duplicate delivery created a second order</b> · D2 D3 · D8 D9</summary>

Same request, same response, a different state.

**What happened.** D3 delivered the same `invoice.paid` event as D2. Both got `200 {"received":true}`, but `orders.count` went 1 → 2, expected 1. The retry D9 did the same. Only the probe could see this.

**Why it matters.** Stripe may deliver the same event more than once, and retries failed or timed-out deliveries with backoff for up to 3 days in live mode. Each duplicate creates another order.
<sub>Stripe docs › Webhooks › Handle duplicate events · Retry behavior</sub>

**Fix.** Record `event.id` before creating the order and return 200 early when it is already recorded. After the fix, D3 and D9 should leave `orders.count` at 1.

```js
// before creating the order
const { rowCount } = await db.query(
  'INSERT INTO processed_events (id) VALUES ($1) ON CONFLICT DO NOTHING',
  [event.id]
);
if (rowCount === 0) return res.sendStatus(200); // already handled
```
</details>

<details>
<summary>△ F3 Medium · Exceeded the 10 000 ms response budget · D8 · D14</summary>

**What happened.** D8 took 11 840 ms (+1 840 over) and D14 10 420 ms (+420 over). The median of the other 8 `invoice.paid` deliveries was 200 ms. Likely cause: the handler waits for the Parcelbird label API before it responds.

**Why it matters.** If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout. In this run, D8 went over our default budget and the scenario then sent the retry D9, which created the duplicate order in F2.
<sub>Stripe docs › Webhooks › Quickly return a 2xx response</sub>

**Fix.** Acknowledge with 200 first, then queue the label job. After the fix, D8 and D14 should respond within the budget.

```js
res.sendStatus(200);            // acknowledge first
await queue.add('create-label', { invoiceId: event.data.object.id });
```
</details>

<details>
<summary>○ F4 Info · Probe doesn't see subscription state · D4 D5 D6</summary>

**What happened.** Shuffled passed on `orders.count` (0 → 1 across the shuffled order), but `customer.subscription.updated` arrived first and `subscriptions.status` isn't probed.

**Why it matters.** Out-of-order delivery usually breaks status fields, not counts.
<sub>Stripe docs › Webhooks › Event ordering</sub>

**Fix.** Add `subscriptions.status` to the probe in `trap.yml`. The next run then checks both.

```yaml
probe:
  - as: orders.count
    sql: select count(*) from orders where stripe_invoice_id = '{{invoice.id}}'
  - as: subscriptions.status   # add this
    sql: select status from subscriptions where stripe_id = '{{subscription.id}}'
```
</details>

---
[Full report](https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC) · reproduce locally with `trap test` · this comment is updated on every push
~~~

---

## 2. New screens

### 2.1 Guest first minute, desktop 1440 (no agent paired)

Data for these frames (fold into `realistic-data` later, §7): guest capture URL `webhooktrap.dev/c/k7Qm2xVb9TfL`; guest meter `expires in 23 h 49 m · 4/100 deliveries · 0/3 runs`; deliveries #01–#04 of group `sub_1QH…4drI` with their dataset times, sizes and headers; signature **Not checked** on all four (no agent); shape **New shape (first capture)** (a guest has no baseline); pairing code `HXR-4K7`, expiry `9:41`.

The flow is foundations F1 + F2: **Get a URL → first capture → signature verdict → Prove → pair the agent → Run**. Four frames:

#### G1 · First capture, #01 selected, no agent (light + dark)

```
┌ top bar 48 ────────────────────────────────────────────────────────────────────────────────────────────────┐
│ ⟍■  Guest session · expires in 23 h 49 m · 4/100 deliveries · 0/3 runs · Sign in to keep                    │
│     Capture · Runs · Fixtures · Agent        webhooktrap.dev/c/k7Qm2xVb9TfL ⧉       ○ No agent    ⌘K         │
├ capture header 56 ─────────────────────────────────────────────────────────────────────────────────────────┤
│ Arrivals ┃ Live   4 deliveries · 1 group · last 13:58:05.416                         [Grouped│Flat] [Filter] │
├ list 480 ──────────────────────────────────────────┬ detail 960 ───────────────────────────────────────────┤
│ 13:58:05.416  sub_1QH…4drI · Stripe · 4 · 1.2 s     │ Stripe                                           [⋯]  │
│               ·  ·   ·    ·   (spacing sparkline)  │ invoice.paid                                          │
│               4 · signatures not checked           │ #01 · 13:58:04.212 · 0.2 s after Stripe created it    │
│▌ 13:58:04.212 Stripe invoice.paid        2 294 B ◎  │     · 2 294 B · in sub_1QH…4drI (4)                   │
│  13:58:04.395 Stripe invoice.payment_s…  2 307 B ◎  │ path strip: created ·──\ received   ingest 56 ms      │
│  13:58:04.823 Stripe payment_intent.su…    915 B ◎  ├───────────────────────────────────────────────────────┤
│  13:58:05.416 Stripe customer.subscr…upd 1 496 B ◎  │ ◎ Not checked   Stripe-Signature v1 header present,   │
│                                                    │                 timestamp 0.2 s old.                  │
│                                                    │                 Secrets live on your machine. Connect │
│                                                    │                 the agent to check signatures.        │
│                                                    │ ═ New shape (first capture)  invoice.paid ·           │
│                                                    │                 API 2024-06-20 · 41 fields            │
│                                                    ├ Prove the handler that receives these ────────────────┤
│                                                    │ Webhook Trap replays these 4 events against your      │
│                                                    │ handler the way Stripe delivers them: twice, out of   │
│                                                    │ order, late, retried. The agent runs on your machine, │
│                                                    │ sends deliveries to localhost and keeps your signing  │
│                                                    │ secrets there.                                        │
│                                                    │ [ Prove invoice.paid ]   See an example run ›         │
│                                                    │ Without the agent (now)    │ With the agent (~30 s)   │
│                                                    │ See each delivery live     │ Check signatures with    │
│                                                    │ Read exact bytes, headers  │   your own secrets       │
│                                                    │ Copy any delivery as JSON  │ Run 8 scenarios against  │
│                                                    │ Download the exact bytes   │   localhost              │
│                                                    │ Plan a run: scenarios, why │ See what each delivery   │
│                                                    │   and citations            │   did to your state      │
│                                                    │                            │ Save scrubbed fixtures   │
│                                                    │                            │   into your repo         │
│                                                    ├ Verdict │ Raw │ Decoded ────────────────────────────────┤
└────────────────────────────────────────────────────┴───────────────────────────────────────────────────────┘
```

- **Signature verdict without an agent.** Label stays the dataset's **Not checked** (◎ in `ink-muted`). The helper first says what *was* read without a secret (the `Stripe-Signature` v1 header is present and its timestamp is 0.2 s old, from #01's headers and dataset "Timestamp 0.2 s old"), then the dataset helper. This answers M3 honestly: instant, but not a fake ✓.
- **One "Not checked" per group, not per row.** When every row in a group shares Not checked, the group header says `4 · signatures not checked` once and rows show the glyph only (accessible name keeps "Not checked"). Any row in a different state still shows its word.
- **"Prove the handler that receives these"** is a ruled block (no box: a `rule-strong` hairline above, the title in Archivo wdth 87.5 / 650 / 15) that appears for a guest with no run yet, between the verdict lines and the tabs. **Prove invoice.paid** is the screen's only primary button; the pane header's Prove is hidden while the block is visible (same place, one primary). The group header keeps its secondary Prove.
- **See an example run ›** opens the read-only Run #3 (`webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC`, dataset `read_only` state, with "Example run" replacing "Shared by Maya Lindqvist"). PROPOSED for A01: it lets a guest see the aha before installing anything.
- The *without / with* table is the answer to "what a guest can do without an agent" (§2.3). It collapses to one line ("What works without the agent ›") after the first run.
- Live arrival: rows slide in 120 ms linear, as v1. Selection follows the first row only if the user hasn't selected anything (foundations U4).

#### G2 · Prove → draft, pairing live (light)

Prove leads to `/runs/new` (foundations §0.6: the draft is a state of the Run screen, not a modal). This is the existing **RunDraft** with three guest differences:

```
├ run header ─ Capture › sub_1QH…4drI › New run   Draft · 8 scenarios selected · 14 deliveries · about 45 s ┤
├ setup (left 1000) ───────────────────────────────────────────────────┬ ghost graph (right 440) ──────────┤
│ 1 Agent                                       Waiting for your agent… │ 8 glyph-graphs, one per scenario  │
│   The agent runs on your machine, sends deliveries to localhost and   │                                   │
│   keeps your signing secrets there.                                   │ Guest · runs left 3 of 3          │
│   ┌ one-time code ────────┐   npx @webhooktrap/agent pair HXR-4K7 ⧉   │                                   │
│   │ HXR-4K7               │   Run the command in a terminal.          │                                   │
│   │ expires in 9:41       │   This step turns to Connected by itself; │                                   │
│   └───────────────────────┘   no reload.                              │                                   │
│   What the agent does: sends deliveries only to targets you allow ·   │                                   │
│   keeps signing secrets on your machine (we see names, never values) ·│                                   │
│   open source, MIT                                                    │                                   │
│ 2 Target   http://localhost:3000/webhooks/stripe   (checked against the agent's allow-list once it connects)
│ 3 Events   #01 invoice.paid · #03 payment_intent.succeeded · #04 customer.subscription.updated            │
│ 4 Scenarios (Stripe pack: all 8 pre-checked) … as RunDraft                                                 │
│ 5 Verdict level · 6 Warnings … as RunDraft                                                                 │
│ [ Run ] disabled · "Pair an agent to run against localhost"                                               │
```

1. Top bar shows the guest meter and `○ No agent` → `Pairing…` once the command is copied.
2. The **one-time code** is set large (Martian Mono 32 px) in a ruled frame, because a guest may type it on another machine; the expiry counts down (`expires in 9:41`). At 0: "Code HXR-4K7 expired." + **Get a new code** (the new value is not in the dataset; draw only the button).
3. Right column adds `Guest · runs left 3 of 3` under the ghost graph (dataset limit 3 runs; 0 used).

States in this frame: `not_installed` (before copy) and `pairing` (after copy) from dataset §6 Agent. A04 draws `pairing`.

#### G3 · Paired, signatures checked (light)

Same draft, a few seconds later:

- Step 1 collapses to one line: `◉ Connected · maya-mbp · agent 0.9.2 · target localhost:3000/webhooks/stripe`.
- Agent pill `◉ maya-mbp` with its **popover open** (closes A11 T5): `Connected · maya-mbp` · `Target localhost:3000/webhooks/stripe · agent 0.9.2` · Secrets (names only): `STRIPE_WEBHOOK_SECRET …3f9a`, `GITHUB_WEBHOOK_SECRET …7c4d`, `SHOPIFY_API_SECRET …b21e`, Parcelbird: not set.
- A ruled toast, bottom left: **"Signatures checked on maya-mbp: 4 of 4 valid (STRIPE_WEBHOOK_SECRET …3f9a)."** with **View capture**. The four capture rows are now ◉ Signature valid.
- **Run** is enabled: `Run · 8 scenarios · 14 deliveries · about 45 s`.
- If the agent had no Stripe secret, step 1 instead shows dataset `needs_secret` ("Twice, Late and Tampered must re-sign. Add STRIPE_WEBHOOK_SECRET on maya-mbp, or run only Once and Slow budget.") and the command `trap secrets set stripe`. Not drawn; listed for the States sheet.

### 2.2 Guest first minute, mobile 390

Pairing needs a terminal on the machine that runs the handler, so mobile's job is: show the capture, explain, and hand the command over cleanly.

```
GM1 · capture (light + dark)           GM2 · #01 detail (light)               GM3 · Pair from your computer (light)
┌──────────────────────────────────┐   ┌──────────────────────────────────┐   ┌──────────────────────────────────┐
│ ⟍■ Guest · 23 h 49 m · 4/100 ○  │   │ ‹ Arrivals                  [⋯] │   │ ‹ Prove                          │
├──────────────────────────────────┤   │ Stripe                           │   │ Pair the agent from your         │
│ Arrivals ┃ Live · 4   [Copy URL] │   │ invoice.paid                     │   │ computer                         │
├──────────────────────────────────┤   │ #01 · 13:58:04.212 · 2 294 B     │   │ The agent runs where your handler│
│ sub_1QH…4drI · Stripe · 4 [Prove]│   │ path strip ─────────────────     │   │ runs, so pairing needs a terminal│
│ 4 · signatures not checked       │   │ ◎ Not checked                    │   │ on that machine.                 │
│ 13:58:04.212  invoice.paid     ◎ │   │ Stripe-Signature v1 header       │   │ ┌ one-time code ──────────────┐  │
│   Stripe · 2 294 B               │   │ present, timestamp 0.2 s old.    │   │ │ HXR-4K7                     │  │
│ 13:58:04.395  invoice.payment… ◎ │   │ Secrets live on your machine.    │   │ │ expires in 9:41             │  │
│ 13:58:04.823  payment_intent…  ◎ │   │ Connect the agent to check       │   │ └─────────────────────────────┘  │
│ 13:58:05.416  customer.subscr… ◎ │   │ signatures.                      │   │ npx @webhooktrap/agent pair      │
│                                  │   │ ═ New shape (first capture)      │   │ HXR-4K7                          │
│ Signatures show Not checked      │   ├ Prove the handler that receives  │   │ [ Copy command ]                 │
│ until an agent is paired.        │   │ these ─────────────────────────  │   │ Or open this capture there:      │
│                                  │   │ (same copy as desktop)           │   │ webhooktrap.dev/c/k7Qm2xVb9TfL   │
│                                  │   │ Without the agent (now)          │   │ [ Copy link ]                    │
│                                  │   │ …4 lines                         │   │ ─────────────────────────────    │
│                                  │   │ With the agent (~30 s)           │   │ Waiting for your agent…          │
│                                  │   │ …4 lines                         │   │ This page updates by itself.     │
├──────────────────────────────────┤   │ Verdict │ Raw │ Decoded          │   │ Meanwhile on this phone: read    │
│ Capture  Runs  Agent  More       │   ├──────────────────────────────────┤   │ deliveries, Raw bytes, and plan  │
└──────────────────────────────────┘   │ [ Prove ] secondary, 52 sticky   │   │ the run.                         │
                                       └──────────────────────────────────┘   └──────────────────────────────────┘
```

- GM2's sticky Prove is **secondary** while no agent is connected (foundations §4 Screen 3 mobile); it opens GM3.
- GM3 uses the system share sheet on **Copy command** where available (label stays "Copy command"). When the agent connects, the status line turns to `◉ Connected · maya-mbp`, and a **Continue to the run** button appears (primary).
- Touch targets ≥ 44 px; the code is selectable text, not an image.

### 2.3 What a guest can do without an agent (product rule, for all screens)

| Capability | Without agent | With agent | Why |
| ---------- | :-----------: | :--------: | --- |
| Receive and see deliveries live, grouped | ✓ | ✓ | Hosted capture |
| Read Raw bytes, ordered headers, raw query; Decoded | ✓ | ✓ | Hosted capture |
| Download the exact bytes; Copy delivery as JSON | ✓ | ✓ | U8 machine parity |
| See provider, event type, shape (first capture or vs baseline) | ✓ | ✓ | No secret needed |
| Signature: header present, scheme, timestamp age | ✓ | ✓ | No secret needed |
| Signature valid / invalid | — (Not checked) | ✓ | Secrets never leave the machine (U6) |
| Open the run draft: scenarios, why, citations | ✓ | ✓ | Plan before installing |
| Run scenarios against localhost | — | ✓ | Only the agent can reach localhost |
| State probe, findings | — | ✓ | Probe runs on the machine |
| Save as fixtures (written into the repo) | — | ✓ | Written through the agent |
| See an example run (read-only) | ✓ | ✓ | PROPOSED, A01 |

Copy rule: a disabled capability is never greyed out silently. It says what unlocks it ("Pair an agent to run against localhost").

### 2.4 CLI frames (`trap prove`)

Terminal artboards, not browser pages. A terminal window at **960 px** wide holding 100 columns of Martian Mono wdth 100 at 13 px / 1.45 line height, 24 px padding, no OS window chrome (a plain 1 px `rule` frame with the title `Terminal · ~/code/brindle-app`). Colours: the 16-colour mapping of §1.4 drawn with the theme's tokens (red = `conflict`, yellow = `sev-medium`, blue = `sev-info`, green = `pass`, magenta = `now`, dim = `ink-muted`; High uses red without bold, Critical red bold).

| Frame | Content | Theme |
| ----- | ------- | ----- |
| `CliProveLive` | §1.4.1 exactly | dark terminal |
| `CliProveSummary` | §1.4.2 rows + summary | dark terminal |
| `CliProveSummaryLight` | same | light terminal |
| `CliProveAscii` | §1.4.3 exactly, no colour | light terminal |

### 2.5 CI log frame

`CiLog`: a neutral log viewer at 1440 fill, Martian Mono 12, line numbers in `ink-muted`, the step title `trap test --fail-on high` with a failed marker (■ + "Failed", words, not a provider logo). Draw the **Deliveries · 14** group collapsed (▸), the summary expanded, the four annotations rendered as ruled lines under the log (`■ Error F1 Critical · Accepts tampered payload`, `▲ Error F2 High · …`, `△ Warning F3 Medium · …`, `○ Notice F4 Info · …`), then the last two lines and `exit 1`. No imitation of any CI vendor's chrome or logo.

### 2.6 PR comment frame

`PrComment` (light, 1440 fill): left, the comment **as rendered** (760 px, a neutral comment card: author `webhooktrap` with the product mark, no GitHub logo or chrome replica); right, the **Markdown source** (§1.4.6) in Martian Mono 12. `PrCommentDark`: rendered only, 960 px. Both show F1 and F2 open, F3 and F4 collapsed.

---

## 3. Must-fixes (A01 and A11), with the exact change

Line numbers refer to the current local sources under `claude-design/timetable/`. After the edits, this sweep must return nothing in `canvas/project` and `ds/project`: `grep -rnE 'VERIFY|TO CONFIRM|\[LICEN|TBD|TODO|lorem|allowance|Allowance|survived duplicates|always retries|Jonah another'` (token and class names included, since D1 renames them).

### 3.1 Placeholders (A01 must-fix 3, common issue 1; A11 T2)

| File | Now | Change to |
| ---- | --- | --------- |
| `LandingDesktop.dc.html` l.102 | `Licence and repository: [TO CONFIRM]` | `MIT licence · source on GitHub` ("GitHub" is the link; no URL in copy) |
| `LandingMobile.dc.html` l.88 | `licence and repository: [TO CONFIRM]` | `MIT licence · source on GitHub` |
| `LandingDesktop.dc.html` l.164 (Slow budget `what`) | `Measures response time against Stripe’s budget (10 000 ms, VERIFY against pack data)` | `Measures response time against the provider's budget` (landing has no number) |
| `Main.dc.html` l.340, `RunDraft.dc.html` l.125 (Slow budget `what`) | same VERIFY string | `Measures response time against the budget: 10 000 ms (Webhook Trap default)` (source tag per §4) |
| `Main.dc.html` l.135 (budget bar axis) | `10 000 ms · Stripe pack, VERIFY` | `10 000 ms · default` |
| Any landing or header | (v1 wireframe showed `GitHub ★ 2.1k`) | No star counts or other unsourced social proof anywhere |

### 3.2 One term: "budget" (A01 Timetable must-fix 2)

**Choice: budget.** It is A01's word (core-experience M6 "latency against the provider budget line", §2.4 "latency against the budget"), the scenario's name (**Slow budget**), the F3 title and the dataset's word. A second word for the same limit forces the reader to check whether they differ, and "allowance" is railway flavour, which A02's rule 1 keeps out of labels. Applied everywhere, including internals, so engineers and A04 read the same word as users.

| File | Now | Change to |
| ---- | --- | --------- |
| `Main.dc.html` l.133 | `of the 10 000 ms allowance` | `of the 10 000 ms budget · default` |
| `Main.dc.html` l.170 (table header) | `Allowance 10 000 ms` | `Budget 10 000 ms · default` |
| `RunMobile.dc.html` l.74 | `196 ms of the 10 000 ms allowance` | `196 ms of the 10 000 ms budget · default` |
| `DeliveryGraph.dc.html` l.193 (graph summary) | `cross the 10 000 ms allowance` | `cross the 10 000 ms budget` |
| `LandingDesktop.dc.html` l.45 (hero band label) | `10 000 ms allowance continues` | `budget 10 000 ms · default` |
| `Main.dc.html` l.90 (legend) | `▲ ■ conflict` | `▲ ■ finding` (UI copy never says "conflict"; the colour token keeps its name) |
| `ds/project/tokens.json`, `tokens.css`, `components/bundle.css`, `Cover/preview.html` | `allowance-fill`, `allowance-edge` | `budget-fill`, `budget-edge` (no alias; nothing ships yet) |
| `ds/project/README.md` l.1, l.13, l.34 and the contrast table rows | "allowance"; "'path', 'conflict' and 'allowance' are [used]" | "budget"; "'path' and 'budget' are used in UI copy; 'conflict' names a colour token only" |
| `DeliveryGraph/README.md`, `PathStrip/README.md` + `preview.html`, `Severity/preview.html`, `Button/README.md`, `DeliveryGraph/preview.html` | "allowance" | "budget" ("open triangle at the budget edge"; "colour means finding, now or budget only") |
| Component names | `TT/AllowanceBand` | `TT/BudgetBand` |

### 3.3 The Passed line (A01 common issue 2)

**Now** (`Main.dc.html` l.64, `StatesSheet.dc.html` l.115, `ds/project/README.md` l.24): "8 of 8 scenarios passed. Your handler survived duplicates, reordering, late, tampered and slow deliveries."

**Change to:**
- Verdict line label: **Passed** · meta `8 of 8 scenarios · 14 deliveries · Level 1 + Level 2 (orders.count)`.
- Helper: **"8 of 8 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: state outside the probe, such as subscriptions.status."**
- `RunPassed` findings column: "No findings." (was "No conflicts.") then a ruled **What was checked** list: "Status and latency of every delivery against the 10 000 ms budget (default)" · "Tampered and Late were rejected" · "orders.count after every delivery: duplicates left it at 1 → 1" · "Slowest response 238 ms". Then **Not checked**: "State the probe doesn't read, such as subscriptions.status. Add it to trap.yml to check it." with the F4 yaml as a link.
- CLI/PR: §1.4.4.

### 3.4 ARIA patterns (A11 T4)

**Treegrid** (`CaptureDesktop`, `CaptureMobile`, and the new guest capture frames):

~~~html
<div role="treegrid" aria-label="Deliveries grouped by related object">   <!-- not focusable itself -->
  <div role="row" class="tt-board-head">
    <span role="columnheader">Time</span><span role="columnheader">Provider</span>
    <span role="columnheader">Event</span><span role="columnheader">Size</span>
    <span role="columnheader">Signature</span><span role="columnheader"><span class="tt-sr">Actions</span></span>
  </div>
  <div role="row" aria-level="1" aria-expanded="true" aria-setsize="6" aria-posinset="5" tabindex="-1">
    <span role="gridcell" aria-colspan="5">sub_1QH…4drI · Stripe · 4 · 1.2 s</span>
    <span role="gridcell"><button type="button" class="tt-btn">Prove</button></span>
  </div>
  <div role="row" aria-level="2" aria-setsize="4" aria-posinset="1" aria-selected="true" tabindex="0">
    <span role="gridcell">13:58:04.212</span><span role="gridcell">Stripe</span>
    <span role="gridcell">invoice.paid</span><span role="gridcell">2 294 B</span>
    <span role="gridcell"><svg …><use href="#tt-g-sig-valid"/></svg> <span class="tt-sr">Signature valid</span></span>
    <span role="gridcell"></span>
  </div>
</div>
~~~

- One tab stop: the selected row has `tabindex="0"`, all other rows `-1` (roving). Remove `tabindex="0"` from the treegrid container.
- `CaptureMobile`: replace each `<a class="tt-rb" role="row">` with `<div role="row" tabindex="-1">` holding `gridcell`s (time, event + provider/size, signature). Enter opens the detail; a row is not a link.
- Group header rows carry `aria-expanded`; delivery rows carry `aria-selected`.

**Tabs with panels** (`DetailPane` Verdict/Raw/Decoded, `Main` and `RunMobile` lower-zone tabs, `CaptureWaiting` and `CaptureMobile` provider tabs, the mobile inspector's Request/Response):

~~~html
<div role="tablist" aria-label="Delivery views">
  <button role="tab" id="dp-tab-verdict" aria-controls="dp-panel-verdict" aria-selected="true"  tabindex="0">Verdict</button>
  <button role="tab" id="dp-tab-raw"     aria-controls="dp-panel-raw"     aria-selected="false" tabindex="-1">Raw</button>
  <button role="tab" id="dp-tab-decoded" aria-controls="dp-panel-decoded" aria-selected="false" tabindex="-1">Decoded</button>
</div>
<div role="tabpanel" id="dp-panel-verdict" aria-labelledby="dp-tab-verdict" tabindex="0">…</div>
<div role="tabpanel" id="dp-panel-raw"     aria-labelledby="dp-tab-raw"     tabindex="0" hidden></div>
<div role="tabpanel" id="dp-panel-decoded" aria-labelledby="dp-tab-decoded" tabindex="0" hidden></div>
~~~

- Every tab has a panel, even if the frame only draws the selected one (empty `hidden` panels for the rest). Replace `aria-label` on panels with `aria-labelledby`.
- **Ids are prefixed per board** (`dp-`, `main-`, `cw-`, `cm-`, `rm-`, …) and, for components imported more than once, by an `idp` prop, so imports never duplicate ids.
- Mobile bottom navigation (Capture · Runs · Agent · More) is `<nav aria-label="Main">` with links and `aria-current="page"`, never tabs.

**Graph and strip.** The Delivery Graph is `<figure>` + `<svg role="img" aria-labelledby="…title …desc">` with the summary sentence as `desc`; its interactive equivalent is the Deliveries table (roving, ↑ ↓ moves the graph highlight). The Scenario strip is a toolbar (§1.1).

### 3.5 Glyphs as inline SVG, not `data:` masks (A11 T1, T6)

**Now:** `ds/project/components/bundle.css` l.239–274 defines every glyph as `--m:url("data:image/svg+xml,…")` used as a CSS mask. If the sandbox blocks `data:` images, every glyph (including the severity shapes Critical ■ and High ▲, which share a colour) disappears.

**Change:**
1. Delete the 36 `.tt-g-*{--m:url(data:…)}` rules and the mask declaration on `.tt-g`. Keep `.tt-g` as the 12 px default (`width: 12px; height: 12px; flex: none; vertical-align: -1px;`, the density the boards use), with `.tt-g-16` (16 px, `vertical-align: -3px`) and `.tt-g-10` as size modifiers and the `.tt-c-*` colour classes.
2. Add one **sprite** to each top-level board, right after `<x-dc>`: `<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">` with one `<symbol id="tt-g-…" viewBox="0 0 16 16">` per glyph. Each symbol is the decoded data URI with `fill='#000'` → `fill="currentColor"` and `stroke='#000'` → `stroke="currentColor"`; geometry unchanged.
3. Each glyph use: `<svg class="tt-g tt-c-high" aria-hidden="true" focusable="false"><use href="#tt-g-high"/></svg>`. Data-driven glyphs use a hole in the attribute (`href="#{{s.icon}}"`), which `<use>` allows.
4. **No duplicate sprites:** boards imported by others take a `sprite` prop (`off` when imported) and wrap their sprite in `<sc-if value="{{showSprite}}">`. Component boards viewed alone render it.
5. **Check once:** open `Main` and confirm a glyph inside the imported `DeliveryGraph` renders. If `<use>` cannot reach a sprite across `dc-import`, inline the paths instead (one `<sc-if>` per glyph kind) and report it.
6. **Agent states get their own glyphs** (T6), on the same 16-unit grid, `ink` colour, never a severity shape:
   - `tt-g-blocked` (target unreachable): `<path d="M2 8H10M11.5 4V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" fill="none"/>` (a line running into a stop)
   - `tt-g-offline` (disconnected): `<path d="M2 8H14" stroke="currentColor" stroke-width="1.5" stroke-dasharray="2.5 2" fill="none"/>` (the broken line)
   - `tt-g-update` (update needed): `<path d="M8 13.5V3M4 7 8 3 12 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" fill="none"/>`
   - Pill words stay from foundations §5.3; `target unreachable` and `update needed` no longer use `sev-high` / `sev-medium` colour.
7. The DS `Icons` preview shows the sprite's symbols, not mask classes.

### 3.6 Design-system record and other A11 minors

| # | Change |
| - | ------ |
| T3 | **Refresh the canvas's design-system record last.** After the system is republished with §3.2 and §3.5, re-run the canvas's design-system install step (do not hand-edit `canvas.json`) so `designSystems[0].version` equals the system's live version and `project/ds/timetable/tokens.json`, `tokens.css` and `components/bundle.css` are byte-identical to the system's. Verify by listing both artifacts' files and comparing sizes. |
| T5 | Agent version `0.9.2` appears in G3's popover and draft step 1 (§2.1). |
| T7 | Keep the Google Fonts `@import` in `bundle.css`. The canvas copy must stay byte-identical to the system's (T3), and the system previews need it. Accepted double load. |
| T8 | Set `lastChange.by` in the system's `design-system.json` to `Webhook Trap design` if the format lets the author set it. If the server rewrites it on publish, leave it and list it in the report; don't retry. |
| A01-6 | Remove "Each duplicate ships Jonah another bag of coffee" (`Main.dc.html` l.207, `RunMobile.dc.html` l.40) via §1.3's copy. |
| A01-3 | "always retries after a timeout" (same lines): replaced by §1.3's F2 copy. |

---

## 4. Provider budgets: how the UI attributes a number

A01 is verifying the numbers into `provider-budgets.md`. **This spec sets no number.** Frames keep the dataset's 10 000 ms (Stripe) as the drawn value, and its **source tag follows `provider-budgets.md`**.

**Three sources** (one per provider and event family, recorded in `provider-budgets.md`):

| Source | When | Short tag | Long form (popover, F3, CLI header) |
| ------ | ---- | --------- | ----------------------------------- |
| Provider-documented | The provider's docs state a number, and A01 has recorded the page | `Stripe docs` | "Stripe's budget: 10 000 ms. Source: Stripe docs › Webhooks › …" (path per `provider-budgets.md`) |
| Product default | The provider documents no number, **or** `provider-budgets.md` hasn't verified one yet | `default` | "Budget: 10 000 ms, Webhook Trap's default." + "Stripe doesn't publish a response-time limit." only if `provider-budgets.md` records exactly that |
| Project setting | `budget:` in `trap.yml` (PROPOSED for A01) | `trap.yml` | "Budget: {n} ms, set in trap.yml." |

**Rules.**
1. **A budget number never appears without its tag.** Short tag next to the number in dense places; long form in the popover, F3 and the CLI header.
2. **Possessive only with a source.** "Stripe's budget" and the F3 title "Exceeded Stripe's response budget" are allowed only for `Stripe docs`. Otherwise: "the budget", "Exceeded the 10 000 ms response budget".
3. **Unverified means `default`.** Until `provider-budgets.md` marks Stripe as provider-documented, every frame draws the `default` variant. The swap later is a copy change only.
4. **No visual difference on the graph** between sources. The band, edge rule and △ terminal are the same; the tag carries the provenance.
5. **Behaviour claims stay with their citations.** F3's "If Stripe times out waiting, the delivery counts as failed and is retried" (with "Stripe doesn't publish the timeout") cites *Quickly return a 2xx response*; it is about behaviour, not the number, so it stays in both variants. Severity stays Medium in both (A01 to confirm).
6. **If `provider-budgets.md` changes the Stripe value**, D8 (11 840 ms) and D14 (10 420 ms) may no longer be over budget, which changes F3 and the Retry after error story. Then A03 regenerates `realistic-data` and A04 rebuilds from it; no frame is patched by hand.

**Where the number and tag appear** (Run #3, `default` variant):

| Place | Exact string |
| ----- | ------------ |
| Verdict line meta | `… · 41.0 s · L1 + probe · budget 10 000 ms · default` |
| Graph, at the band's far edge | `budget 10 000 ms · default` (Archivo wdth 75, 11 px, ink-muted); the band label is a button opening the popover |
| Deliveries table header | `Budget 10 000 ms · default`; cells `within`, `over +1 840`, `over +420` |
| Inspector response column | `196 ms of the 10 000 ms budget · default` |
| Mobile path strip label | `196 ms · budget 10 000 ms · default` |
| Draft · Verdict level | `Level 1 · transport (always): status, latency against the 10 000 ms budget (default), consistency across duplicates` |
| Draft and Scenarios tab · Slow budget row | `Measures response time against the budget: 10 000 ms (Webhook Trap default)` |
| F3 | title and beats as §1.3 |
| Landing scenario cell | `Measures response time against the provider's budget` (no number, no tag) |
| CLI / CI header | `budget    10 000 ms (Webhook Trap default)` |
| PR comment | `budget 10 000 ms (Webhook Trap default)` |

**Popover** (`TT/BudgetSource`, opened from the graph label or table header): title `Response budget · 10 000 ms` · body (default) "Webhook Trap's default for Stripe. A line that crosses the band's edge took longer than this." · (provider) "From Stripe docs › Webhooks › … ." · a "Change it in trap.yml" line only once the `budget:` key is approved.

---

## 5. 10-second comprehension test (Run screen and landing hero)

Closes A01 Timetable must-fix 1 and A02's condition. Run it on A04's v2 frames before the PO signs off the direction.

**Participants:** 5 backend developers who integrated a provider's webhooks in the last 12 months; ≥ 3 have used Stripe; ≥ 1 owns CI or QA for their team; none has seen Webhook Trap or works on it. Remote, moderated, 20 minutes, light theme.

**Stimuli** (static renders, shown full screen at 1440 × 900, first viewport only):

| # | Stimulus | From frame |
| - | -------- | ---------- |
| S1 | Landing hero, final frame of the motion (or the motion itself if the frame animates: 4 s motion + 6 s rest) | `LandingDesktop` |
| S2 | Graph only: Scenario strip + Delivery Graph, findings column and lower zone masked, legend hidden | `Main` (cropped) |
| S3 | Run headline frame as drawn (strip, graph, F2 open, D3 inspector) | `Main` |

**Order:** S1 → S2 → S3, the real journey (landing first), with the graph shown before the full Run so its diagnostic isn't answered from the findings text.

**Procedure per stimulus:** blank screen → stimulus for exactly 10 s → blank screen → questions, answered aloud without seeing it again → then a second, untimed look with one question: "What made this hard to read, if anything?"

**Questions and pass marks** (an answer counts if it carries the target meaning; A03 and A01 score independently, disagreements take the stricter score):

| Stimulus | Question | Target | Pass |
| -------- | -------- | ------ | ---- |
| S1 | What does this product do? | Replays or tests webhook deliveries against your handler (duplicates, order, timing) | ≥ 4 of 5 |
| S1 | What went wrong in the picture? | The same event arrived twice and created a second order / count went to 2 | ≥ 4 of 5 |
| S1 | What would you click first? | Get a URL (or the npx command) | ≥ 4 of 5 |
| S2 | Which deliveries were the same event sent again? | D2 and D3 (or D8/D9) | ≥ 3 of 5 (diagnostic) |
| S2 | Which response took too long? | D8 or D14 | ≥ 3 of 5 (diagnostic) |
| S2 | Which delivery changed the state when it shouldn't have? | D3 (or D9) | ≥ 3 of 5 (diagnostic) |
| S3 | Which delivery changed the state when it shouldn't have? | D3 (or D9) | ≥ 4 of 5 |
| S3 | What changed? | A second order was created / orders.count went from 1 to 2, expected 1 | ≥ 4 of 5 |
| S3 | What is the most serious problem in this run? | Tampered / accepts a changed body / F1 Critical | ≥ 4 of 5 |
| S3 | Which scenarios passed? | At least 3 of Once, Shuffled, Late, Burst | ≥ 3 of 5 |

**What each result means:**

| Result | Action |
| ------ | ------ |
| S1 and S3 pass (S2 any) | Ship v2 as specified. If S2 < 3 of 5, the graph legend stays always visible for a user's first 3 runs. |
| S3 fails on "which delivery" or "what changed" (≤ 3 of 5) | **Timetable's own fallback** (`RunFallbackTableFirst`): strip + Deliveries table become the primary run view, the graph shrinks to a 120 px overview strip. Round 2 with 5 new developers on that frame. |
| S1 fails on "what went wrong" (≤ 3 of 5) | Hero fallback: the finding sentence becomes the hero headline block and the graph its illustration underneath. Round 2. |
| **Round 2 still fails** S3 "which delivery" or "what changed" (≤ 3 of 5), **or** S1 "what went wrong" (≤ 3 of 5), **or** in either round ≥ 3 of 5 name the graph as what made it hard | **Switch to the Notation fallback.** Notation's A01 must-fixes apply first. The borrowings here (scenario strip, state reading, commentary, run record) carry over unchanged in meaning; only their typography changes. |

With 5 people this is a signal, not a statistic: the thresholds are set so that one confused participant never triggers a switch and three always do. Results go into `comprehension-results.md` (A03) with verbatim answers.

---

## 6. Frame list for A04

### 6.1 Existing artboards that change

Every board that draws a glyph changes for §3.5 (sprite + `<use>`); that is all of them except the five dark wrappers, which only import. The table lists the other changes.

| Board | Changes |
| ----- | ------- |
| `Main` (+ `RunDark` via import) | Scenario strip (§1.1); graph label row → segment brackets with icons; State reading inline + inspector panel (§1.2); F2 open as Finding commentary (§1.3); budget strings + tags (§3.2, §4); legend "finding"; VERIFY removed; Passed copy in the `passed` branch (§3.3); tabs ARIA (§3.4) |
| `DeliveryGraph` | Bracket + icon row; `expected 1` / `expected 0` captions on the state rail; band label `budget 10 000 ms · default`; summary text "budget"; `sprite` prop |
| `RunRunning`, `RunPassed`, `RunAborted`, `RunInconclusive` | Strip states (§1.1); State reading states (§1.2); Passed copy and "What was checked" (§3.3) |
| `RunDraft` | VERIFY removed; budget strings (§4); strip hidden |
| `RunMobile` (+ `RunMobileDark`) | 2 × 4 strip; F2 commentary; budget string; Jonah and "always retries" removed; tabs ARIA |
| `RunMobileInspector` | State reading at the top of the sheet; Request/Response tabs ARIA |
| `LandingDesktop` (+ Dark), `LandingMobile` (+ Dark) | MIT licence line; Slow budget cell copy; hero band label; hero state rail `2 ▲ expected 1`; the "Real traffic" section's CI station shows 3 lines of the run record (`▲ Twice  F2 High …`, `■ Tampered  F1 Critical …`, `exit 1`) in Martian Mono |
| `CaptureDesktop` (+ Dark), `CaptureMobile` (+ Dark) | Treegrid ARIA; mobile rows as `div role="row"`; provider tabs with panels (mobile) |
| `CaptureWaiting`, `CaptureMobileWaiting` | Tab panels `aria-labelledby` |
| `DetailPane`, `DetailPage`, `DetailPageDark`, `DetailPageRaw`, `DetailPage09`, `DetailPage08`, `MobileDetail`, `MobileDetailRaw` | Tabs with panels (incl. empty Decoded panel); glyphs |
| `StatesSheet` | Passed copy; add `needs_secret` in the guest pairing context (§2.1 G3) |
| `CaptureQuota` | Glyphs only |
| **System** (`ds/project`) | Tokens `budget-*`; bundle.css without masks; README voice and copy tables; new previews `ScenarioStrip`, `StateReading`, `FindingCommentary`, `RunRecord` (§1.4 specimens: Unicode, ASCII, Markdown), `BudgetSource`; `Icons` preview from the sprite; `AgentPill` with neutral agent glyphs; `RunStatus` README Passed copy |

### 6.2 New artboards (19)

| Board | Size | Theme | Content |
| ----- | ---- | ----- | ------- |
| `GuestFirstCapture` | 1440 fill × 1200 | light | §2.1 G1 |
| `GuestFirstCaptureDark` | 1440 fill × 1200 | dark (imports light) | §2.1 G1 |
| `GuestProvePair` | 1440 fill × 1500 | light | §2.1 G2 |
| `GuestPaired` | 1440 fill × 1500 | light | §2.1 G3, popover open, toast |
| `GuestMobileCapture` | 390 × 1000 | light | §2.2 GM1 |
| `GuestMobileCaptureDark` | 390 × 1000 | dark | §2.2 GM1 |
| `GuestMobileDetail` | 390 × 1700 | light | §2.2 GM2 |
| `GuestMobilePair` | 390 × 1100 | light | §2.2 GM3 (waiting) |
| `CliProveLive` | 960 × 760 | dark terminal | §1.4.1 |
| `CliProveSummary` | 960 × 2400 | dark terminal | §1.4.2 |
| `CliProveSummaryLight` | 960 × 2400 | light terminal | §1.4.2 |
| `CliProveAscii` | 960 × 2100 | light terminal | §1.4.3 |
| `CiLog` | 1440 fill × 1700 | light | §1.4.5, §2.5 |
| `PrComment` | 1440 fill × 2000 | light | §1.4.6 rendered + source |
| `PrCommentDark` | 960 × 1800 | dark | §1.4.6 rendered |
| `RunFallbackTableFirst` | 1440 fill × 1200 | light | §5 fallback, ready for round 2 |
| `ScenarioStrip` | 1000 × 560 | light | component board: all cell states (§1.1), imported by `Main` and `RunMobile` |
| `StateReading` | 960 × 640 | light | component board: 4 variants × 4 states (§1.2) |
| `FindingCommentary` | 440 × 1400 | light | component board: F1–F4 open (§1.3), imported by `Main` and `RunMobile` |

Mobile boards are fixed width, use `min-height`, never `overflow: hidden` on the root (A11 C1 lesson). Heights are estimates: size each board to its content.

### 6.3 Canvas organisation

Rows top to bottom, each with a `title1` note; boards left to right in the order listed.

1. **Run · headline (v2: scenario strip, state reading, commentary)**: `Main`, `RunDark`, `RunMobile`, `RunMobileDark`, `RunMobileInspector`
2. **Run · states**: `RunRunning`, `RunDraft`, `RunPassed`, `RunAborted`, `RunInconclusive`, `RunFallbackTableFirst` (note: "Fallback · only if the 10-second test fails")
3. **Guest first minute · no agent paired** (new): `GuestFirstCapture`, `GuestFirstCaptureDark`, `GuestProvePair`, `GuestPaired`, `GuestMobileCapture`, `GuestMobileCaptureDark`, `GuestMobileDetail`, `GuestMobilePair`
4. **Outside the browser · CLI, CI, PR comment** (new): `CliProveLive`, `CliProveSummary`, `CliProveSummaryLight`, `CliProveAscii`, `CiLog`, `PrComment`, `PrCommentDark`
5. **Landing · the graph is the hero**: unchanged order
6. **Capture stream · the arrivals board**: unchanged order
7. **Delivery detail · verdict first, bytes one click away**: unchanged order
8. **States sheet and shared components**: `StatesSheet`, `DeliveryGraph`, `DetailPane`, `ScenarioStrip`, `StateReading`, `FindingCommentary`

`Main.dc.html` stays first in `order`. The canvas title stays `Webhook Trap · Timetable · Screens`; the system stays `Webhook Trap · Timetable` (update both artifacts in place, no new artifacts).

### 6.4 Build order

1. System: tokens rename, sprite and glyph change, new component previews. Publish.
2. Canvas component boards (`ScenarioStrip`, `StateReading`, `FindingCommentary`, updated `DeliveryGraph`), then `Main`, then the other changed boards.
3. New rows 3 and 4.
4. Sweep (§3 grep), re-install the design system in the canvas (T3), list both artifacts' files.

---

## 7. Data additions and open points

**For `realistic-data.md` / `.json` (A03, separate task; until then this file is the source for these strings):** F1–F4 commentary beats (§1.3); Passed helper (§3.3); the two Slow budget `what` strings (§3.1); budget `source` per provider (from `provider-budgets.md`); guest first-minute block (§2.1 data); G3 toast; strip line 3 readings (§1.1); Run #1's two scenarios = Once, Twice (3 deliveries: D1, D2, D3); CLI, CI and PR samples (§1.4).

**Open for A01 / PO:**
1. `budget:` in `trap.yml` (third source) and its popover line.
2. "See an example run" for guests (read-only Run #3).
3. F3 severity when the budget is a product default (proposed: stays Medium).
4. CLI flags and subcommands (`prove --group`, `show F1`, `test --fail-on`, `--fail-on-inconclusive`, `--format markdown`, `--ci github`) with the Q13 CLI prototype.
5. Default `--fail-on high` for CI.
