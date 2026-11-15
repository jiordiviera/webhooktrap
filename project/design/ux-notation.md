# UX · Territory III: Notation

**Status:** PROPOSED for A00 and PO review · **Author:** A03 (UX/UI) · **Date:** 2026-11-11
**Read with:** `ux-foundations.md` (behaviour, states, a11y: wins on conflicts), `brand-territories.md` §III (A02), `realistic-data.md` (the only content allowed).

Dimensions are approximate CSS px at 1440 × 900 and 390 × 844. Wireframes show structure and hierarchy, not style.

---

## 1. How the idea becomes structure

**Dominant structure: the annotated game page, three reading columns.** **Move list** (left, narrow, the sequence) · **Position** (centre, Board Green panel, the state after the selected move) · **Commentary** (right, widest, findings as annotated prose with the better variation). Moving through moves updates the position and scrolls the commentary, like a chess viewer.

Hierarchy comes from **typography and rules**, never boxes: **Newsreader** (serif) carries meaning (verdict sentences, finding titles, commentary in italic), **Atkinson Hyperlegible Next** carries operation (controls, tables, labels), **Sometype Mono** carries evidence (ids, payloads, the move list's data columns). Severity is **glyph-first** (`??` `?` `?!` `!?`), with colour as the second channel and the plain word always beside it.

| | Primary | Secondary |
| - | ------- | --------- |
| Capture stream | **Game collection**: each related-object group is a game with its moves listed beneath | Detail pane opens on a one-line serif verdict |
| Delivery detail | **The move, annotated**: serif verdict sentence, signature and shape as commentary | Raw / Decoded |
| Run | **Move list** + **Position** + **Commentary** | Inspector (request/response) inside the commentary column under the open finding |
| Landing | The **annotated game spread**: move list writing itself, the giant pencil `?` (High) beside `1 → 2` (N8) | Repertoire grid, agent JSON |

### 1.1 How the key moments read

- **Duplicate:** move 3 repeats move 2's text: `3. invoice.paid (again, as 2.) ? High`.
- **1 → 2:** the **position diagram** shows `orders` as counters: after move 2 one counter ●; after move 3 a second counter ● is placed **in the finding's colour** (F2 is High: `sev-high`, see N8), with `?` High and "1 → 2" beside it. This is the territory's single image.
- **Over budget:** a **chess-clock bar** in the position panel: the allowance (10 000 ms) as the clock's time; when D8 runs past it, the **flag falls** (a small flag glyph drops at the bar's end) and `?!` Medium appears.
- **Pass:** `!` for a good move (Late rejected with 400), `=` for a position that stayed equal when it should (D12, D13 in Burst: `1 → 1`).
- **Inconclusive:** `∞` "Unclear position: add a probe to see your state."
- **Aborted:** helper "Adjourned at move 3" (label stays **Aborted**, see N4).

---

## 2. Usability adjustments (PROPOSED; A02 to confirm)

| # | A02 says | Problem | Proposed adjustment |
| - | -------- | ------- | ------------------- |
| N1 | Duplicate written as `2.` and `2′.` | Run deliveries are already numbered D1…D14 and findings cite them; primes create a parallel numbering ("is 2′ D3?"). | **Move number = D-number.** A repeat is marked in words: `3. invoice.paid (again, as 2.)`. Primes are not used. |
| N2 | Capture groups as games with moves `1. 2. 3.` | Those numbers clash with capture numbers `#01…`. | In the capture stream the in-game ordinal is a **muted sequence marker** (not linkable, not copyable); the capture number `#01` stays at the row's end as the identifier. |
| N3 | Glyphs paired with plain severity "on first view", dismissible | The dismissal makes the screen depend on a user's history; colleagues reading a shared run won't have dismissed it. | Glyph **and** word always (`?? Critical`). In the move list, finding moves show glyph + word; pass moves show the glyph only (`!`, `=`) with the word in the accessible name and a legend under the list. |
| N4 | "Adjourned" may be a run state label if understood | Untested jargon on a status that triggers an action (reconnect). | Label **Aborted**; helper *Adjourned at move 3*. Swap only if the comprehension test (§10, Q4) passes. |
| N5 | "Prove this game" on group headers | Territory word on a control (A02 rule 1). | Control label **Prove** everywhere; the tooltip / accessible name says "Prove all 4 deliveries in this group". |
| N6 | Three columns; commentary widest | The request/response side by side needs ≥ 2 × 320 px; with three columns the inspector has no home. | Column widths **360 / 300 / 732** at 1440. The inspector lives **inside the commentary column**, under the open finding, as "The move" with Request | Response side by side (2 × 350). |
| N8 | The brand image is a red-pencil `??` beside `1 → 2` | `??` is **Critical**. A01 ranks state divergence (the duplicate) as **High** = `?`. Putting `??` on the duplicate would teach the glyph scale wrong on the very first screen, and the real `??` (move 10, tampered) would lose its weight. | In the app the duplicate is **`?` High** with a `sev-high` counter. On landing the margin glyph is also `?` (still giant, still the pencil hand, in `sev-high`). If the PO wants the `??` image, A01 must re-rank "duplicate creates a paid order" as Critical; UX shouldn't fake it. |
| N7 | Serif "earns its place in verdicts" | Event types, ids and numbers in serif would read as marketing and lose tabular alignment. | Serif is **never** used for event types, ids, numbers, times or controls. Only for: run verdict, finding titles, commentary, signature/shape verdict sentences, landing. |

---

## 3. Landing

**Section order:** 1 Hero (book page) → 2 **The annotated game** (brand image) → 3 **Repertoire**: every way a webhook can arrive → 4 **Works with your coding agent** (the same game as JSON) → 5 **Record the game, replay it in every PR** (capture → scrub → commit → CI) → 6 Secrets stay on your machine · open source · self-host → 7 Providers → 8 Footer CTA.
Reason: in this territory the notation *is* the machine-readable format, so the agent section directly follows the repertoire and pays off the hero (A02 §III.8: "the notation is machine-readable"); it also serves the Agent-assisted persona this territory suits best.

### 3.1 Desktop 1440

```
┌ buff ground ────────────────────────────────────────────────────────────────────────────────────┐
│ ▦ Webhook Trap (Newsreader 600)                      Docs  GitHub ★ 2.1k  Self-host  Sign in    │ 56
│                                                                                                 │
│  Your webhook handler works. Once.                          (Newsreader display 60, left-aligned │ ~160
│                                                              across 2 of 3 columns)             │
│  Providers play the other moves: twice, out of order, late, retried. Webhook Trap replays      │ 17 italic
│  real events as they really arrive and annotates what your code did, move by move.             │
│  [ Get a URL, no sign-up ] (Board Green block)   npx @webhooktrap/agent ⧉                       │ 52
├ the annotated game: a book spread, two pages with a gutter rule ────────────────────────────────┤
│ LEFT PAGE (600)                              │ RIGHT PAGE (600)                                 │
│ Twice · invoice.paid                         │ ┌ position (Board Green 280 × 200) ┐             │
│ 1.  invoice.paid          200 · 212 ms   !   │ │ orders                           │             │ ~380
│     position: orders 1                       │ │  ●   →   ●  ● (sev-high)         │             │
│ 2.  invoice.paid (again)  200 · 196 ms  ?    │ │ 1        2                       │             │
│     position: orders 2           (margin: giant pencil ? in sev-high, Newsreader italic 160) │    │
│                                              │                                                  │
│ A natural move, and a losing one. Stripe delivers each event at least once.                     │ 17 italic
│ Better was (2. … = with an event.id guard).        ? High                                       │
│ Plain line: Duplicate delivery created a second order.                       [❚❚ Pause]         │
├ repertoire ─────────────────────────────────────────────────────────────────────────────────────┤
│ Every way a webhook can arrive (Newsreader 40)                                                  │
│ Once       Baseline single delivery.             All providers                                  │ ruled 2-col
│ Twice      The same event again…                 Stripe › Webhooks › Handle duplicate events     │ list, 8 rows
│ Shuffled · Late · Retry after error · Tampered · Burst · Slow budget                            │ ~420
│   each: name (serif 21) · what (sans) · the trap it springs (italic) · citation                 │
├ works with your coding agent: left = 3 lines commentary · right = F2 as JSON (Sometype Mono)    │ ~280
├ record the game, replay it in every PR: capture → scrub → commit → CI as a numbered move line   │ ~220
│   1. capture #01  2. scrub customer_email  3. commit fixtures/stripe/invoice.paid.json  4. CI   │
├ secrets / open source / self-host: three short commentary paragraphs, ruled                     │ ~240
├ Stripe · Shopify · GitHub · Twilio · Slack · Standard Webhooks (sans, one ruled line)           │ 80
├ Prove your first handler in 5 minutes.  [ Get a URL, no sign-up ]                               │ 220
└─────────────────────────────────────────────────────────────────────────────────────────────────┘
```

**Motion (≈ 3.5 s, once):** move 1 writes in (260 ms ease-in-out) → its position counter slides in → move 2 writes → the second counter slides into place in `sev-high` → the `?` inks into the margin (300 ms stroke draw) → commentary fades in. Reduced motion: final spread.
**Copy note:** the dataset's Twice pair is D2/D3 in the run; on landing the excerpt is renumbered `1.`/`2.` because it shows only that scenario. That is the one place numbering restarts, and it is labelled "Twice · invoice.paid".

### 3.2 Mobile 390

```
┌──────────────────────────────────┐
│ ▦ Webhook Trap           ☰       │ 52
│ Your webhook handler             │ Newsreader 40, 3 lines
│ works. Once.                     │
│ Providers play the other moves…  │ 16 italic
│ [   Get a URL, no sign-up    ]   │ 52 Board Green, full width
│ npx @webhooktrap/agent ⧉         │
├ position pinned at top of spread ┤
│ ┌ orders  ●  →  ● ●(high)┐        │ 96 Board Green
│ └ 1           2          ┘        │
│ 1. invoice.paid  200  !          │ move list
│ 2. invoice.paid (again) 200  ?   │
│    ? (pencil, 64 px) High        │
│ A natural move, and a losing     │ italic commentary
│ one…  Better was (… event.id …)  │
├──────────────────────────────────┤
│ Repertoire: 1-column list        │
├──────────────────────────────────┤
│ JSON sample (horizontal scroll   │
│ inside code block only)          │
├──────────────────────────────────┤
│ [ Get a URL, no sign-up ] sticky │ 56
└──────────────────────────────────┘
```

---

## 4. Capture stream: the game collection

### 4.1 Desktop 1440

```
┌ top bar 48 ──────────────────────────────────────────────────────────────────────────────────────────┐
│ ▦ Brindle Coffee ▾   Capture · Runs · Fixtures · Agent    webhooktrap.dev/c/Br7nDLk2Qm9x ⧉  ◷ maya-mbp ⌘K │
├ header 56: Games (Newsreader 21) · ◷ Live (clock) · 13 deliveries · 6 games · last 14:06:39 [Grouped│Flat] [/ Filter] ┤
├ list 480 ─────────────────────────────────────────────┬ detail 960 ──────────────────────────────────┤
│ Subscription sub_1QH…4drI · Stripe          [Prove]   │ (Screen 3, §5)                                │ game header 52:
│ Jonah Reyes · Monthly whole bean · renewal (italic)   │                                               │  title sans 15/600
│ ▌1. invoice.paid               ✓  13:58:04.212  #01   │                                               │  subtitle serif ital 15
│  2. invoice.payment_succeeded  ✓  13:58:04.395  #02   │                                               │ moves 30 px:
│  3. payment_intent.succeeded   ✓  13:58:04.823  #03   │                                               │  ordinal muted (N2)
│  4. customer.subscription.upd… ✓  13:58:05.416  #04   │                                               │  event type sans 14
│ Order #1042 · Shopify + Parcelbird          [Prove]   │                                               │  glyph, time mono 12,
│ Ana Mbeki · $37.00 · shipped                          │                                               │  #id mono 12 muted
│  1. orders/create              ✓  13:59:12.614  #05   │                                               │
│  2. orders/paid                ✓  13:59:13.229  #06   │                                               │
│  3. shipment.label_created     ○ No secret     #08    │                                               │
│  4. shipment.in_transit        ○ No secret     #12    │                                               │
│ Repository brindle/brindle-app · GitHub     [Prove]   │                                               │
│  1. push                       ✓  14:00:16.089  #07   │                                               │
│  2. pull_request               ✗ Invalid        #09   │                                               │
│ …                                                     │                                               │
└───────────────────────────────────────────────────────┴───────────────────────────────────────────────┘
```

- **Signature glyph after the move:** `✓` valid · `✗` + "Invalid" · `○` + "No secret" · `◇` + "Unknown scheme" · `–` unsigned · `·` not checked. Words are visible for every non-valid state in this territory (they are short).
- **Live arrival:** a new move **writes in** (opacity + 4 px slide, 200 ms ease-in-out) at the end of its game; a new game slides in at the top. The clock icon by "Live" ticks once per arrival (no continuous animation).

### 4.2 Mobile 390

```
┌──────────────────────────────────┐
│ ▦ Brindle Coffee ▾      ◷ maya   │ 52
│ Games ◷ Live · 13   [Copy URL]   │ 48
├──────────────────────────────────┤
│ Subscription sub_1QH…4drI [Prove]│ 60 sticky game header
│ Jonah Reyes · renewal (italic)   │
│ 1. invoice.paid             ✓    │ 52 moves, 2 lines:
│    13:58:04.212 · #01 · 2 294 B  │  move + glyph / meta
│ 2. invoice.payment_succeeded ✓   │
│ …                                │
│ 2. pull_request       ✗ Invalid  │
├──────────────────────────────────┤
│ Capture  Runs  Agent  More       │ 56
└──────────────────────────────────┘
```

### 4.3 States

| State | Notation rendering |
| ----- | ------------------ |
| Waiting | A blank scoresheet: label "Waiting for the first delivery", URL in Sometype Mono 28 + Copy, provider tabs, then an empty move list with `1. …` in muted ink. Helper (A02): *No moves yet. Point a provider at this URL, and the first event opens the game.* |
| Loading | 3 game skeletons (title bar + 3 move lines). |
| Paused | Clock icon shows a stopped hand; "Paused · 4 new moves". |
| Catching up | Moves appear without write-in. |
| Stream lost | Ruled banner, italic helper "Live updates interrupted…". |
| Quota reached | Ruled banner with `?!` + "Guest limit reached: 100 of 100 deliveries" + dataset helper. |
| Expired | Book-page layout: "This guest URL expired" (Newsreader 40) + helper + **Get a new URL**. |

---

## 5. Delivery detail: the move, annotated

### 5.1 Desktop (960 pane)

```
┌ detail 960 ───────────────────────────────────────────────────────────────────────────────┐
│ Stripe · #01 · 13:58:04.212 · 2 294 B · game sub_1QH…4drI, move 1 of 4    [Prove] [⋯]      │ 32 meta (sans 13)
│ invoice.paid                                    (sans 24 / 600, never serif: N7)           │ 40
│ Signed by Stripe and checked on maya-mbp. Same shape as the 6 earlier captures.            │ serif 17, one line
├ Verdict │ Raw │ Decoded ─────────────────────────────────────────────────────────────────────┤
│ Signature   ✓ Signature valid · Stripe v1 · STRIPE_WEBHOOK_SECRET (…3f9a) · t 0.2 s old      │ ruled key rows
│ Shape       = Shape matches baseline · API 2024-06-20 · 41 fields                          │
│ Provider    evt_1QK… · api_version 2024-06-20 · livemode false (Sometype Mono)             │
│ The game    1. invoice.paid  2. invoice.payment_succeeded  3. payment_intent…  4. customer… │ inline notation
└────────────────────────────────────────────────────────────────────────────────────────────┘
```

**#09 invalid:** the serif line reads **"Signed with a different secret."** followed by italic commentary *The body is intact (7 of 7 checks), so the secret in this repository's webhook settings differs from yours. Hook 512839104 was edited at 13:31 today.* and the fix as a **variation**: `(Better: copy the secret from GitHub › … › 512839104 into GITHUB_WEBHOOK_SECRET.)` with **Re-check**. The Signature row shows `✗ Signature invalid` with expected/received prefixes in mono.

**Raw:** Sometype Mono 13, header table with index, body with byte gutter, wrap toggle.

### 5.2 Mobile

Full page: meta line, event type 20, serif verdict line (wraps to 2 lines max), tabs, sticky **Prove** bar when an agent is connected.

---

## 6. Run: the annotated game

### 6.1 Desktop 1440 · headline frame (findings, F2 open, move 3 selected, compare with move 2)

```
┌ top bar 48 ───────────────────────────────────────────────────────────────────────────────────────────────┐
├ run header 56: Capture › sub_1QH…4drI › Run #3 · localhost:3000/webhooks/stripe via ◷ maya-mbp · 8 scenarios  [Run again] [Save as fixtures] [⋯] ┤
├ verdict 72 ────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 4 findings (Newsreader 28)   ?? 1 Critical  ? 1 High  ?! 1 Medium  !? 1 Info   8 scenarios · 14 deliveries · 41.0 s │
├ move list 360 ───────────────────┬ position 300 ───────────┬ commentary 732 ─────────────────────────────────┤
│ Once                              │ ┌ Board Green ────────┐ │ 3. ? High · Duplicate delivery created a second   │
│  1. invoice.paid    200 184  !    │ │ After 3.            │ │    order                     (Newsreader 21)     │
│ Twice                             │ │ orders              │ │ Level 2 · state probe · moves 2, 3 · 8, 9        │
│  2. invoice.paid    200 212  !    │ │  ●  →  ● ●          │ │ A natural move, and a losing one: the second      │
│▌ 3. invoice.paid (again, as 2.)   │ │  1     2 (high ●)   │ │ delivery of the same invoice.paid event created   │
│        200 196  ? High            │ │ ? High · 1 → 2      │ │ another order. Stripe may deliver the same event  │ 17 italic
│ Shuffled                          │ │                     │ │ more than once (Stripe docs › Webhooks › Handle   │
│  4. customer.subscr…upd 200 143 = │ │ Clock               │ │ duplicate events).                                 │
│  5. payment_intent.suc… 200 121 = │ │ ▕█░░░░░░░░░░▏10 s   │ │ Responses to 2 and 3 are identical, so only the   │
│  6. invoice.paid    200 205  !  !?│ │ 196 ms              │ │ probe could see this.                             │
│ Late                              │ │ Signing             │ │ (Better: 3. … = with an event.id guard)  [Show ▾] │ variation → fix code
│  7. invoice.paid    400  41  !    │ │ re-signed, same id  │ │ ─────────────────────────────────────────────────  │
│ Retry after error                 │ └─────────────────────┘ │ The move · 3. invoice.paid · 14:09:04.068 · +1 950 │
│  8. invoice.paid  200 11 840 ?! ⚑ │  ‹ 2.     4. ›          │ ┌ Request (350) ───────┐┌ Response (350) ───────┐ │
│  9. invoice.paid (again, as 8.)   │                         │ │ POST localhost:3000/… ││ 200 · 196 ms           │ │
│        200 205  ? High            │                         │ │ re-signed (same id)  ││ {"received":true}      │ │
│ Tampered                          │                         │ │ Stripe-Signature t=… ││ X-Powered-By: Express  │ │
│ 10. invoice.paid (body changed)   │                         │ │ Body = exact bytes #01││                        │ │
│        200 167  ?? Critical       │                         │ └──────────────────────┘└────────────────────────┘ │
│ Burst                             │                         │ [Compare with 2.]  2.: 200 · 212 ms · same body ·  │
│ 11–13. invoice.paid · pi · sub    │                         │ orders 0 → 1                                       │
│        200 601/312/448  ! = =     │                         │ ─────────────────────────────────────────────────  │
│ Slow budget                       │                         │ 10. ?? Critical · Accepts tampered payload  ▸      │
│ 14. invoice.paid  200 10 420 ?! ⚑ │                         │  8. ?! Medium · Exceeded Stripe's response budget ▸│
│ legend: ! good · = unchanged ·    │                         │  6. !? Info · Probe doesn't see subscription state▸│
│ ?? Critical ? High ?! Medium !? Info                        │                                                    │
└───────────────────────────────────┴─────────────────────────┴────────────────────────────────────────────────────┘
```

- **Move list rows:** 26 px (two-line rows 44 px for finding moves, so the glyph + word fit), scenario headings 24 px in sans 12 small caps-free with a rule. Data columns (status, latency) in Sometype Mono, right-aligned. ⚑ = flag fell (over budget).
- **Commentary ordering:** the **open finding** first; under it "The move" (inspector); then the other findings as one-line annotations in severity order (`??` first). Clicking a move without a finding (e.g. 1.) shows "1. invoice.paid ! · Good move: 200 in 184 ms, orders 0 → 1." and The move below.
- **Position panel:** Board Green surface with a 1 px rule (required in dark, A02 III.4). Counters are 20 px discs; the new counter is filled in the finding's severity colour (`sev-high` for F2), carries `?` High beside it and the text "1 → 2". The chess-clock bar shows latency against 10 000 ms; when over, the flag glyph sits at the bar's end and the bar's overflow is hatched.
- **Keyboard:** `←` / `→` step through moves (position slides, commentary scrolls), `[` / `]` step through findings. Same as foundations.

### 6.2 Running (move 9 of 14)

Moves write in as they are sent (move text first, then status and latency when the response arrives, then the glyph when the probe answers). The position panel follows the newest move with its counters sliding (220 ms). A running clock in the verdict line: "Running · move 9 of 14 · Retry after error · 22.7 s". Annotation glyphs **ink in once** when a finding is first found; commentary paragraphs appear in the commentary column as findings arrive.

### 6.3 Other run states

| State | Rendering |
| ----- | --------- |
| Draft | Verdict line "Draft · 8 scenarios · 14 deliveries · about 45 s". Move list column becomes **"Opening"**: the scenario rows with checkboxes and their flags, written as a repertoire (name, what, the trap it springs). Position column: agent and target ("At the board: maya-mbp" as helper; label "Connected"). Commentary column: verdict level, probe, warnings, **Run**. |
| Queued | Empty move list with `1. …` muted; helper "Awaiting opponent: maya-mbp" (label "Queued"). |
| Passed (#4) | Verdict "Passed · 8 of 8 scenarios" with `!`. Every duplicate move shows `=` ("orders 1 → 1"). Commentary: "No mistakes found." + the list of what was checked. **Save as fixtures** primary. |
| Aborted (#2) | Label **Aborted**; helper (A02) *Adjourned. The agent left the board at move 3. Moves 1–2 and their positions are recorded. The run resumes when maya-mbp reconnects.* Moves 3–14 shown muted with "not played". `½` pause glyph by the agent pill. |
| Inconclusive (#1) | `∞` beside every duplicate move; position panel "Unclear position" with no counters; commentary: the probe yaml as the variation. |
| Target unreachable mid-run | The move shows "no reply · connection refused" and `∞`. |

### 6.4 Mobile 390 (the pocket book)

```
┌──────────────────────────────────┐
│ ‹ Run #3              [⋯]        │ 52
│ 4 findings  (Newsreader 24)      │
│ ??1 Crit ?1 High ?!1 Med !?1 Info│ 88
├ position, pinned while scrolling ┤
│ After 3. orders ● → ● ●(high) 1→2│ 64 Board Green strip
├──────────────────────────────────┤
│ Twice                            │
│ 2. invoice.paid  200 212   !     │ 44
│ 3. invoice.paid (again, as 2.)   │ 64 finding move
│    200 196   ? High              │
│    A natural move, and a losing  │ commentary inline,
│    one… Stripe may deliver…      │ italic 16, collapsible
│    (Better: … event.id guard) ▾  │
│    [The move ›]                  │ → full-screen sheet:
│ Retry after error                │   Request | Response tabs,
│ 8. invoice.paid 200 11 840 ?! ⚑  │   Compare with 2.
├──────────────────────────────────┤
│ [ Run again ] [ Save fixtures ]  │ 56 sticky
└──────────────────────────────────┘
```

This is the strongest mobile reading of the three territories: the sequence is naturally vertical and commentary sits under its move.

---

## 7. Light and dark

- **Light (buff)** is the identity and the default for landing and app. **Dark ("night hall")** is green-tinted; check that it doesn't read as a "dark green dashboard": keep large surfaces at `ground`/`surface`, Board Green only in the position panel and the CTA block.
- **Dark Board Green** (`#2D583A`) is 2.2:1 against the dark ground: it is a **surface**, so the position panel always has a 1 px `rule` border, and text on it uses dark `ink` (6.7:1).
- **Severity colours on Board Green** (the new counter sits on the panel): `sev-high` and Red Pencil fills against `#436F4F` are low contrast fill-on-fill. Give every coloured counter a 1.5 px buff outline so it reads by shape and outline, and keep "1 → 2" and the glyph as text beside it.
- **Info `!?` is violet**: the only cool-violet in the product. Keep it small (glyph + word), never a fill, to stay clear of "generic SaaS purple".

## 8. Component inventory (Notation system)

| Group | Components |
| ----- | ---------- |
| Run | `NT/RunVerdictLine` (7 states) · `NT/MoveList` (+ `NT/ScenarioHeading`, `NT/MoveRow`: pass `!`, equal `=`, finding with glyph + word, over-budget flag, pending, not played, unreachable `∞`, selected) · `NT/MoveLegend` · `NT/PositionPanel` (counters, severity-coloured counter, "after N.", unclear `∞`) · `NT/Counter` · `NT/ChessClockBar` (within, flag fallen) · `NT/Commentary` · `NT/FindingAnnotation` (open, one-line) · `NT/Variation` (collapsed bracket, expanded fix code) · `NT/TheMove` (`RequestColumn`, `ResponseColumn`, `BodyDiff`, `CompareWithMove`) · `NT/Opening` (draft scenario repertoire rows with flags) · `NT/FixturesPreview` |
| Severity | `NT/AnnotationGlyph` (`??` `?` `?!` `!?` `!` `=` `∞`, with ink-in animation) + `NT/SeverityWord` |
| Stream | `NT/GamesHeader` · `NT/GameHeader` (title, italic subtitle, Prove) · `NT/MoveEntry` (default, hover, selected, new, body-not-kept) · `NT/SignatureMark` (6 states + word) · `NT/ShapeMark` · `NT/NewMovesPill` · `NT/BlankScoresheet` (onboarding) |
| Detail | `NT/MoveHeader` · `NT/VerdictSentence` · `NT/KeyRow` · `NT/InvalidSignatureCommentary` · `NT/HeaderTable` · `NT/RawViewer` · `NT/JsonTree` · `NT/InlineGame` |
| Global | `NT/TopBar` · `NT/AgentPill` (6 states, clock icon) · `NT/GuestMeter` · `NT/LiveClock` · `NT/RuledBanner` · `NT/Toast` · `NT/CommandPalette` · `NT/ShortcutSheet` · `NT/Skeleton` · `NT/PrimaryBlock` (Board Green CTA) |
| Landing | `NT/HeroPage` · `NT/AnnotatedSpread` (animated + final) · `NT/MarginGlyph` (giant annotation glyph, any severity) · `NT/RepertoireRow` · `NT/CodeSample` · `NT/MoveLine` (capture → CI) · `NT/ProviderList` |
| Icons | the 12 from A02 §III.6 (typographic) + copy, overflow, filter, pause |

## 9. Risks specific to this territory

1. **Gimmick creep.** Every string is tempted to become a chess line. Rule: controls and labels plain (N5); chess only in glyphs, helper copy and commentary.
2. **Glyph literacy** for `?!`, `!?`, `∞` (A02). Mitigated by N3 (word always) and the legend under the move list.
3. **Three columns at 1280:** columns become 320 / 260 / 620, and the inspector's two sub-columns drop to ~300 each; acceptable. Below 1280 the position panel moves on top of the commentary.
4. **Serif in a dev tool** (A02). N7 limits it to meaning; the working screens are mostly sans and mono.
5. **Less dense:** a 200-delivery run means a long move list. Proposed: collapse each passing scenario to one line ("Burst · 3 moves · ! = =") by default.

## 10. Comprehension test

- **Who:** 6 Integrators; 3 who play chess casually, 3 who don't (ask at recruitment).
- **Stimulus A (10 s):** the desktop Run headline frame with the legend hidden.
- **Questions:** (1) Rank `??`, `?`, `?!`, `!?` from most to least serious, words hidden (target: in that order) (2) Which move changed the state wrongly? (target: 3 or 9) (3) What does the coloured second counter mean? (target: "a second order was created") (4) Shown "Adjourned at move 3" vs "Aborted at move 3", which tells you what to do next? (5) Three adjectives for the screen.
- **Pass:** ≥ 5 of 6 answer (2) and (3); (1) fully right for ≥ 4 of 6 **including at least 2 non-players**, otherwise keep the words always visible (N3 already assumes this). "Adjourned" becomes the label only if ≥ 5 of 6 prefer it **and** say what to do next. Fail signal on (5): ≥ 2 of 6 say "cute", "gimmicky", "for chess fans".
