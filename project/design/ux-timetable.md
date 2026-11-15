# UX · Territory I: Timetable

**Status:** PROPOSED for A00 and PO review · **Author:** A03 (UX/UI) · **Date:** 2026-11-11
**Read with:** `ux-foundations.md` (behaviour, states, a11y: wins on conflicts), `brand-territories.md` §I (A02), `realistic-data.md` (the only content allowed).

Dimensions are approximate CSS px at 1440 × 900 and 390 × 844. Wireframes show structure and hierarchy, not style.

---

## 1. How the idea becomes structure

**Dominant structure: the time–distance graph (Marey chart) with a synced timetable underneath.** Time runs left → right; *stations* run top → bottom; every delivery is a line. The product's whole argument (webhooks fail in sequences) is drawn as one picture.

| | Primary | Secondary |
| - | ------- | --------- |
| Capture stream | The **arrivals board**: dense rows, absolute times, group header rows with a spacing sparkline | Detail pane opens on a **path strip** (one delivery's line) |
| Delivery detail | Signature + shape verdict as two ruled lines, then the path strip | Raw / Decoded tabs |
| Run | **Delivery Graph** (top) + **Deliveries table** (below, same data) | Findings column (right), Inspector (replaces table on demand) |
| Landing | The graph itself is the hero image, drawn on the bare ground | Everything else is ruled tables |

Hierarchy is made with **width and weight of one family (Archivo)**, hairline rules and position, never boxes. Colour appears only for conflict (red), now (magenta), allowance (amber band) and pass (green), so a passing run is almost monochrome and a finding is unmissable.

### 1.1 How the key moments read

- **Duplicate:** D3 runs **parallel** to D2, 18 px to its right (500 ms at the run's scale), labelled `D3 = D2′`.
- **1 → 2:** on the **state rail** (bottom station) D2 ends at `1`, D3 ends at `2`. A **conflict marker in the finding's severity form** sits on D3's terminus (F2 is High: ▲ in `sev-high`, see A6) and a thin bracket in the same colour links it back to D2's `1`. The finding title sets beside it: **Duplicate delivery created a second order**.
- **Over budget:** the amber allowance band starts at each line's *sent* point and extends 10 000 ms. D8 (11 840 ms) and D14 (10 420 ms) leave the band before reaching *responded*; a △ sits where they cross its edge.
- **Tampered:** D10 has a sideways kink at *sent* (body changed) and a red ■ at *responded* (2xx accepted).
- **Late:** D7's origin stub reaches left of a vertical tolerance tick labelled `t − 360 s`; it ends at *responded* with a green clear bar (400, rejected as expected).
- **Shuffled:** lines cross between the first two stations (see adjustment A1).
- **Aborted:** the line breaks into a dashed stub at the moment the agent was lost.

---

## 2. Usability adjustments (PROPOSED; A02 to confirm)

| # | A02 says | Problem | Proposed adjustment |
| - | -------- | ------- | ------------------- |
| A1 | Stations: *sent · arrived · responded · state* | Against `localhost`, *sent* and *arrived* are the same instant, so one station is wasted, and **Shuffled has nothing to cross**: lines can only cross if two orders are drawn. | Stations become **provider order · sent · responded · state**. *Provider order* places each line at its position in the original capture (`#01`, `#03`, `#04`); Shuffled lines then cross between *provider order* and *sent*. In every other scenario those segments are parallel, so a crossing means exactly one thing. |
| A2 | Time drawn at true relative speed | The run lasts 41 s but most of it is idle or one slow handler; at true scale the 200 ms lines become invisible. | Linear time with **axis breaks**: idle gaps > 1.5 s are cut and marked `⫽ 2.0 s`. Active time (≈ 26 s) then fills ≈ 960 px, about 37 px per second: a 200 ms response is a steep line, an 11 840 ms one a long shallow line. Slope keeps its meaning across the whole graph because the scale never changes, only gaps are removed. |
| A3 | Graph is the main run view; fallback is a table | A graph that needs a legend fails the 10 s test; a table must exist anyway for accessibility (foundations §4). | Graph and table **always both present** on desktop, synced. The graph is capped at 280 px tall. A one-line legend sits in the graph's top-left at first use ("steep = fast · parallel = same event again · crossing = reordered · ▲ ■ conflict"), dismissible and remembered. |
| A4 | Mobile: graph rotates 90°, time runs down | 14 lines on a 350 px-wide rotated graph collapse into a smear, and vertical time scrolls the evidence away from the findings. | Mobile is **table-first**: each row carries its own **path strip** (a 120 × 24 mini line with the allowance band). The full rotated graph stays available behind a "Graph" toggle for curious users, not as the default. |
| A5 | `D2 = D1′` duplicate label | The dataset's Twice pair is D2/D3 (D1 is Once). | `D3 = D2′`. Generalise: the duplicate label always names the earlier delivery of the same event in the same scenario. |
| A6 | The 1 → 2 conflict is a **red ■** | ■ in `conflict` red is also the **Critical** terminal. A01 ranks state divergence as **High**, so a red ■ on D3 would tell users the duplicate is a security issue, and the real Critical (D10, tampered) would look the same. | The conflict marker takes the finding's severity: **▲ `sev-high`** on D3/D9, **■ `conflict`** on D10. The landing hero uses the same ▲. If A02/A01 want the brand image red, A01 must re-rank "duplicate delivery creates a paid order" as Critical; UX can't fake it. |

---

## 3. Landing

**Section order (A01's order kept):** 1 Hero with live graph → 2 the conflict (the graph completes inside the hero) → 3 Every way a webhook can arrive (grid of glyph-graphs) → 4 Real traffic: capture → scrub → commit → CI as one line through four stations → 5 Secrets / open source / self-host as a ruled table → 6 Works with your coding agent → 7 Providers as a ruled list → 8 Footer CTA.
Reason: the hero graph *is* the demo strip, so sections 1 and 2 merge; that buys a tighter first screen.

### 3.1 Desktop 1440

```
┌──────────────────────────────────────────────────────────────────────────────────────────────┐
│ ⟍■ Webhook Trap                                     Docs  GitHub ★ 2.1k  Self-host  Sign in   │ 56
├──────────────────────────────────────────────────────────────────────────────────────────────┤
│ 120 margin                                                                                    │
│  Your webhook handler                                         (left 2/3, Archivo wdth 75/700, │
│  works. Once.                                                  72 px, 2 lines)                │ ~200
│  Providers run a different timetable: twice, out of order, late, retried. Webhook Trap        │
│  replays real events on that timetable and shows you where your state diverged.   (18 px)     │ ~60
│  [ Get a URL, no sign-up ]   npx @webhooktrap/agent ⧉     Open source · self-host · secrets   │ 48
│                                                            stay on your machine               │
├─ full-width graph, no frame, on ground ──────────────────────────────────────────────────────┤
│ provider order ·─────────────────────────────────────────────────────────────────── now ┃     │
│ sent           ·────\───────────────────────\─\──────────────────────────────────────── ┃     │
│ responded      ·─────\──────(amber band)─────\─\─────────────────────────────────────── ┃     │ ~300
│ state          ·──────1 ▬ clear              1  2▲ ⟵ bracket                            ┃     │
│                 D1 invoice.paid               D2 D3 = D2′   Duplicate delivery created a     │
│                                                             second order. Stripe may deliver │
│                                                             the same event more than once.   │
│                                                                              [❚❚ Pause]      │
├──────────────────────────────────────────────────────────────────────────────────────────────┤
│ Every way a webhook can arrive                                              (h2, 32 px)       │
│ ┌ glyph ┐ Once         ┌ glyph ┐ Twice       ┌ glyph ┐ Shuffled     ┌ glyph ┐ Late             │ 4 × 2
│ │ ╲     │ Baseline…    │ ╲╲    │ Same event… │ ╳     │ Reverse…     │ ¦╲    │ t − 360 s…       │ grid,
│ └───────┘ All         └───────┘ Stripe › …  └───────┘ Stripe › …   └───────┘ Stripe › …       │ 270 w
│ Retry after error · Tampered · Burst · Slow budget  (same cells)          + Unsigned, packs → │ each
├──────────────────────────────────────────────────────────────────────────────────────────────┤
│ Real traffic, not fixtures you wrote                                                          │
│  ●───────────────●──────────────────●──────────────────●                                      │
│  capture          scrub              commit              CI                                   │ ~220
│  #01 invoice.paid customer_email →   fixtures/stripe/    trap test · PR annotation            │
│                   customer@…test     invoice.paid.json                                        │
├──────────────────────────────────────────────────────────────────────────────────────────────┤
│ Secrets stay on your machine      │ Where it lives        │ Never on our servers              │ ruled
│ Signing secrets                   │ agent / self-host     │ ✓                                 │ table
│ State probe results               │ agent (digest only)   │ ✓                                 │ ~200
│ Open source · licence · docker compose up                                                    │
├──────────────────────────────────────────────────────────────────────────────────────────────┤
│ Works with your coding agent   (left: 3 lines of copy · right: F2 as JSON, Martian Mono)     │ ~260
├──────────────────────────────────────────────────────────────────────────────────────────────┤
│ Stripe · Shopify · GitHub · Twilio · Slack · Standard Webhooks   (one ruled line, text)      │ 80
├──────────────────────────────────────────────────────────────────────────────────────────────┤
│ Prove your first handler in 5 minutes.        [ Get a URL, no sign-up ]                      │ 200
│ Docs · GitHub · Self-host · Status · Privacy                                                 │
└──────────────────────────────────────────────────────────────────────────────────────────────┘
```

**Hero motion (≈ 4 s, once, then rests):** D1 draws (0–0.8 s) → rail prints `1` + clear bar → D2 draws parallel (1.2 s) → rail `1` → D3 draws (1.8 s) → rail `2` → ▲ drops, bracket draws back to D2 (2.6 s) → finding text sets (3.0 s). The magenta now line keeps advancing linearly at the right edge. Pause control bottom-right (WCAG 2.2.2). Reduced motion: final frame, static now marker with time.

### 3.2 Mobile 390

```
┌──────────────────────────────────┐
│ ⟍■ Webhook Trap          ☰      │ 52
├──────────────────────────────────┤
│ Your webhook                     │ 44 px, 3 lines
│ handler works.                   │
│ Once.                            │
│ Providers run a different        │ 16 px
│ timetable… shows you where your  │
│ state diverged.                  │
│ [   Get a URL, no sign-up    ]   │ 48, full width
│ npx @webhooktrap/agent  ⧉        │
├──────────────────────────────────┤
│ graph, NOT rotated, 3 lines only │
│ sent      ─\──────\─\──── now┃   │ ~220
│ responded ──\──────\─\────   ┃   │ (D1, D2, D3 only;
│ state       1      1  2▲         │  provider-order station
│ Duplicate delivery created a     │  hidden at this width)
│ second order.                    │
├──────────────────────────────────┤
│ Every way a webhook can arrive   │ 2-col grid of glyph cells
│ [╲ Once] [╲╲ Twice]              │ 165 × 120 each
│ [╳ Shuffled] [¦╲ Late] …         │
├──────────────────────────────────┤
│ capture ● scrub ● commit ● CI ●  │ station line runs DOWN
│  (vertical line, labels right)   │
├──────────────────────────────────┤
│ ruled tables stack as key/value  │
├──────────────────────────────────┤
│ [ Get a URL, no sign-up ] sticky │ 52, after hero scrolls out
└──────────────────────────────────┘
```

On mobile the hero graph keeps time left → right because it only needs three lines; this is the one place a short horizontal graph fits.

---

## 4. Capture stream: the arrivals board

### 4.1 Desktop 1440

```
┌ top bar 48 ────────────────────────────────────────────────────────────────────────────────────────────────┐
│ ⟍■  Brindle Coffee ▾   Capture · Runs · Fixtures · Agent      webhooktrap.dev/c/Br7nDLk2Qm9x ⧉  ◉ maya-mbp ⌘K │
├ capture header 56 ─────────────────────────────────────────────────────────────────────────────────────────┤
│ Arrivals   ┃ now 14:07:12  Live   13 deliveries · 6 groups · last 14:06:39     [Grouped│Flat]  [/ Filter ] │
├ list 480 ────────────────────────────────────────────┬ detail 960 ─────────────────────────────────────────┤
│ TIME          PROV.    EVENT                SIZE  SIG │ (Screen 3, §5)                                      │
│ ─────────────────────────────────────────────────── │                                                      │
│ 14:06:39.219  Ungrouped · 1                         │                                                      │ group header
│   14:06:39.219 —       (generic POST)       52 B  ○ │                                                      │ 40 / rows 28
│ 14:04:46.723  Order #1042 · Shopify+Parcelbird · 4  │                                                      │
│               ·  ·   ·        ·  (spacing sparkline) [Prove]                                             │
│   13:59:12.614 Shopify orders/create      1 064 B  ◉ │                                                      │
│   13:59:13.229 Shopify orders/paid        1 064 B  ◉ │                                                      │
│   14:01:11.442 Parcel. shipment.label_cr…   356 B  ◎ │   ◎ = no secret (open inner)                         │
│   14:04:46.723 Parcel. shipment.in_transit  281 B  ◎ │                                                      │
│ 14:03:22.302  sub_1QG…qS1C · Stripe · 1       [Prove]│                                                      │
│   14:03:22.302 Stripe  customer.subscr…upd 1 418 B ◉ Δ shape                                              │
│ 14:03:05.876  Order #1043 · Shopify · 1       [Prove]│                                                      │
│ 14:02:08.731  brindle/brindle-app · GitHub · 2       │                                                      │
│   14:00:16.089 GitHub  push               1 287 B  ◉ │                                                      │
│   14:02:08.731 GitHub  pull_request         686 B  ⊗ Invalid                                              │
│▌13:58:05.416  sub_1QH…4drI · Stripe · 4 · 1.2 s [Prove]  ← selected group, surface fill                     │
│▌  13:58:04.212 Stripe  invoice.paid       2 294 B  ◉ │  ← selected row                                     │
│   13:58:04.395 Stripe  invoice.payment_s… 2 307 B  ◉ │                                                      │
│   13:58:04.823 Stripe  payment_intent.su…   915 B  ◉ │                                                      │
│   13:58:05.416 Stripe  customer.subscr…upd 1 496 B ◉ │                                                      │
└──────────────────────────────────────────────────────┴──────────────────────────────────────────────────────┘
```

- **Columns:** time 104 (Martian Mono 12, tabular) · provider 64 · event type flexible (Archivo 13) · size 72 right-aligned · signature station glyph 24 (+ word for invalid, always) · Prove 56 (visible on group headers always; on rows on hover/focus/selection).
- **Group header row:** object short id, provider, count, spread, and a **spacing sparkline** (dots placed on a 120 px time axis = when each delivery arrived). It is the arrivals-board version of the graph, and teaches "dots close together = a burst from the provider".
- **Selection:** surface fill + a 2 px ink bar at the left edge of the list (not a coloured border card).
- **Live arrival:** a new row slides in from the top over 120 ms linear; the magenta now-tick next to "Live" blinks once (one blink, no loop). Group re-order follows foundations §4 (only when at top).

### 4.2 Mobile 390

```
┌──────────────────────────────────┐
│ ⟍■ Brindle Coffee ▾   ◉ maya-mbp │ 52
├──────────────────────────────────┤
│ Arrivals ┃ Live · 13   [Copy URL]│ 48
├──────────────────────────────────┤
│ sub_1QH…4drI · Stripe · 4 [Prove]│ 44 sticky group header
│ 13:58:04.212  invoice.paid     ◉ │ 52 rows: time+event line,
│   Stripe · 2 294 B               │   provider+size line
│ 13:58:04.395  invoice.payment… ◉ │
│ …                                │
│ brindle/brindle-app · GitHub · 2 │
│ 14:02:08.731  pull_request     ⊗ │
│   GitHub · 686 B · Invalid       │
├──────────────────────────────────┤
│ Capture  Runs  Agent  More       │ 56 bottom tabs
└──────────────────────────────────┘
```

### 4.3 States

| State | Timetable rendering |
| ----- | ------------------- |
| Waiting | Onboarding panel centred, max 720: the URL at 32 px Martian Mono, Copy, provider tabs, then an **empty arrivals board** with one ruled line and the magenta now line advancing: "No arrivals yet. Point Stripe test mode at this URL. The first delivery appears here within a second." (A02 helper). |
| Loading | 8 ruled rows with grey bars at true column widths; no shimmer. |
| Paused | The now line stops and turns `ink-muted`, label "Paused 14:07:12"; pill "4 new while paused · Resume". |
| Catching up | Now line jumps to current time (no animation) and rows insert without motion. |
| Stream lost | Now line becomes dashed; ruled banner above list. |
| Quota reached | A ruled banner in `sev-medium` text with △, persistent. |
| Expired | Full screen: a single horizontal line ending in a buffer stop (logo geometry) + copy + **Get a new URL**. |

---

## 5. Delivery detail

### 5.1 Desktop (right pane, 960)

```
┌ detail pane 960 ──────────────────────────────────────────────────────────────────────┐
│ Stripe                                                                     [Prove] [⋯] │
│ invoice.paid                                     (Archivo wdth 87.5 / 650, 24 px)      │ 88
│ #01 · 13:58:04.212 · 0.2 s after Stripe created it · 2 294 B · in sub_1QH…4drI (4)     │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ path strip: created ·──\ received ·──\ (agent check) ·   ingest 112 ms                │ 48
├────────────────────────────────────────────────────────────────────────────────────────┤
│ ◉ Signature valid   Stripe v1 · checked on maya-mbp · STRIPE_WEBHOOK_SECRET (…3f9a)    │ ruled
│ ═ Shape matches baseline   invoice.paid · API 2024-06-20 · 41 fields, same as 6 earlier│ lines
├ Verdict │ Raw │ Decoded ─────────────────────────────────────────────────────────────────┤ 40
│ Provider metadata (2-col key/value, mono values)                                      │
│ Related in sub_1QH…4drI: #01 ●─#02 ●─#03 ●─#04 ●  (mini sequence on a time axis)        │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

**#09 invalid** replaces the signature line with a ruled block in `conflict` ink, glyph ⊗ + word:

```
│ ⊗ Signature invalid · Signed with a different secret                                    │
│   Expected sha256=5c1e09…  Received sha256=4d2f4b…   Body intact (7 of 7 checks)         │
│   The secret in this repository's webhook settings differs from yours. Hook 512839104    │
│   was edited at 13:31 today.                                                             │
│   Fix: copy the secret from GitHub › brindle/brindle-app › Settings › Webhooks ›         │
│   512839104 into GITHUB_WEBHOOK_SECRET on your machine.            [Re-check]            │
```

**Raw tab:** request line, ordered header table (index · name · value), body in Martian Mono wdth 87.5 at 12 px with a byte-offset gutter (`0000`, `0040`…) in `ink-muted`. Wrap off by default. Query string on its own line labelled "Raw query".

### 5.2 Mobile

Full page: header (provider, event type 20 px, chips), path strip full width, tabs, sticky "Prove" bottom bar when agent connected. Raw: wrap on, gutter hidden (toggle).

### 5.3 Signature glyphs (station circles)

| State | Glyph | Word |
| ----- | ----- | ---- |
| Valid | ◉ two concentric circles, inner filled | Signature valid |
| Invalid | ⊗ outer circle crossed (`conflict`) | Signature invalid (always shown) |
| No secret | ◎ inner open | No secret |
| Unknown scheme | ◌ dashed outer | Unknown scheme |
| Unsigned | ○ single open circle | Unsigned |
| Not checked | ◎ in `ink-muted` | Not checked |

---

## 6. Run: the Delivery Graph

### 6.1 Desktop 1440 · headline frame (findings, F2 open, D3 inspected, compare with D2)

```
┌ top bar 48 ──────────────────────────────────────────────────────────────────────────────────────────────────┐
├ run header 56 ───────────────────────────────────────────────────────────────────────────────────────────────┤
│ Capture › sub_1QH…4drI › Run #3     localhost:3000/webhooks/stripe via ◉ maya-mbp     Once Twice Shuffled Late │
│                                                         Retry Tampered Burst Slow   [Run again] [Save as fixtures] [⋯] │
├ verdict line 48 (ruled, no box) ─────────────────────────────────────────────────────────────────────────────┤
│ 4 findings   ■ 1 Critical  ▲ 1 High  △ 1 Medium  ○ 1 Info     8 scenarios · 14 deliveries · 41.0 s · L1 + probe │
├ graph 1000 × 280 ─────────────────────────────────────────────────────┬ findings 440 ─────────────────────────┤
│        Once   Twice    Shuffled  Late   Retry after error  ⫽ Tamp. Burst  ⫽ Slow budget │ ■ Critical  F1        │
│ prov.  ·│      ·│ │    ╲ ╳ ╱      ·│     ·│               ·│     ·│ │ │    ·│              │ Accepts tampered      │
│ order   │       │ │     ╳ ╳        │      │                │     ⌇ │ │    │              │ payload · D10         │
│ sent   ─┼──────-┼─┼────┼─┼─┼─────¦─┼─────-┼───(amber 10 s)─┼─────┼─┼─┼────┼──(amber)─── │───────────────────────│
│         ╲       ╲ ╲    ╲ ╲ ╲      ╲      ╲________________△      ╲ ╲╲╲    ╲__________△ │▶▲ High  F2            │
│ resp.   ▬       ▬ ▬    ▬ ▬ ▬    ▬(400)    ▬ ╲ (D9 retry)   ■    ▬▬▬               ▬ │ Duplicate delivery    │
│ state   1       1 2▲   0 0 1     0          1  2▲           1    1 1 1             1 │ created a second order│
│         D1     D2 D3   D4D5D6    D7         D8 D9          D10   D11-13           D14│ Level 2 · state probe │
│                  └─┘ "D3 = D2′"                                              now ┃ │ Evidence D2 D3 · D8 D9│
│ legend: steep = fast · parallel = same event again · crossing = reordered · ▲■ conflict ✕│ Measured D3: 200 in   │
├ lower zone 1000 × ~420: [Deliveries] [Inspector · D3]  ← tabs ─────────────────────────┤ 196 ms · orders.count │
│ D3 · Twice · invoice.paid · from #01 · sent 14:09:04.068 (+1 950 ms)   ‹ D2   D4 ›     │ 1 → 2                 │
│ ┌ Request ─────────────────────────────┐┌ Response ──────────────────────────────┐    │ Why it matters        │
│ │ POST localhost:3000/webhooks/stripe  ││ 200 · 196 ms                            │    │ Stripe may deliver…   │
│ │ re-signed (same event id)            ││ ▕█░░░░░░░░░░(10 000 ms allowance)▏      │    │ (Stripe docs › …)     │
│ │ Stripe-Signature t=1794406144,v1=df… ││ {"received":true}                       │    │ Likely cause          │
│ │ X-Trap-Delivery: D3  X-Trap-Dry-Run:1││ Content-Type: application/json          │    │ Responses to D2 and   │
│ │ Body: exact bytes of #01 (2 294 B)   ││ X-Powered-By: Express                   │    │ D3 are identical…     │
│ └──────────────────────────────────────┘└─────────────────────────────────────────┘    │ Fix · Express+Postgres│
│ [✓ Compare with D2]  D2: 200 · 212 ms · {"received":true} · orders.count 0 → 1          │ [code 5 lines]        │
│ State  orders.count  1 → 2  ▲ changed (expected 1)                                      │ [Copy fix] [JSON]     │
│                                                                                        │ △ Medium F3 · ○ Info F4│
└────────────────────────────────────────────────────────────────────────────────────────┴───────────────────────┘
```

- **Graph geometry:** 4 stations at 56 px spacing (provider order, sent, responded, state) + 40 px scenario labels + 16 px axis = 280 px. Scenario segments separated by thin vertical rules with names in Archivo wdth 75, 11 px caps-free. Axis breaks `⫽` with gap durations on hover/focus.
- **Lines:** 1.5 px ink; selected line 2.5 px with D-label in bold; hovered table row highlights its line and vice versa. Pending lines (running) are dashed `ink-muted`.
- **Terminals on *responded*:** clear bar ▬ (pass), △ (over budget), ■ (conflict). **On *state*:** the digest value in Martian Mono 12; a change that is a finding gets its severity terminal (▲ for F2) and a bracket in the same colour.
- **Findings column:** severity terminal + word + ID, title (15 px), evidence D-links. The open finding expands in place (one at a time); the others stay as 2-line entries.
- **Lower zone:** tabs **Deliveries** (the table, dataset §4.2 columns, 28 px rows, all 14 visible) and **Inspector · D3**. Selecting a line or row opens the Inspector tab; `Esc` returns to Deliveries. The graph stays visible above as the navigator.
- **D10 inspector:** request body shows a **diff** line `amount_paid  2400 → 0` with the kink glyph.

### 6.2 Running (D9 of 14)

The graph lays out the planned segments from the start (scenario labels visible, lines absent). The **magenta now line** advances linearly across the active segment; each line draws from *sent* at true speed as the response comes back; the state value prints when the probe answers. Findings appear in the column as they are found (F3 at D8's budget crossing, F2 at D9). Verdict line reads "Running · D9 of 14 · Retry after error · 22.7 s". If the user scrolls or selects, auto-follow stops and a "Follow now" button appears on the graph's right edge.

### 6.3 Other run states

| State | Rendering |
| ----- | --------- |
| Draft | Graph zone shows the **setup** (foundations §4 order) as a ruled form; the right column shows a **ghost graph**: one glyph-graph per selected scenario, so the user sees what will be drawn. |
| Queued | Ghost graph with the now line parked at 0 and "Waiting for maya-mbp…". |
| Passed (#4) | Monochrome graph, every terminal a clear bar, state rail `1 1 1…` (duplicates `1 → 1`). Verdict line "Passed · 8 of 8 scenarios" with a green clear bar. Findings column: "No conflicts." + what was checked (8 scenario names with ✓ in text). **Save as fixtures** becomes the primary button. |
| Aborted (#2) | D1–D2 drawn; D3's line breaks into a dashed stub at 13:57:40.912 with "Agent lost" label; remaining segments ghosted with "not run". Helper (A02): *Agent unreachable since 13:57:40.912. The run stopped at D3 of 14. D1–D2 and their results are kept. Reconnect maya-mbp to resume.* |
| Inconclusive (#1) | State rail shows `—` at every terminal and a dotted rail; an Info entry "Add a probe" with the yaml. |
| Target unreachable mid-run | The line stops at *sent* with an open circle and "connection refused". |

### 6.4 Mobile 390 (table-first, adjustment A4)

```
┌──────────────────────────────────┐
│ ‹ Run #3 · Stripe        [⋯]     │ 52
├──────────────────────────────────┤
│ 4 findings                       │ 24 px
│ ■1 Critical ▲1 High △1 Med ○1 Info│
│ 8 scenarios · 14 deliveries · 41 s│ 96 total
├──────────────────────────────────┤
│ ■ Critical · Accepts tampered…   │ 64 each, tap → detail
│ ▲ High · Duplicate delivery cre… │
│ △ Medium · Exceeded Stripe's …   │
│ ○ Info · Probe doesn't see …     │
├──────────────────────────────────┤
│ Deliveries            [Graph ⇄]  │
│ Twice                            │ scenario heading
│ D2 invoice.paid  200 212ms  0→1  │ 56 rows: line 1 facts,
│   ╲▬ (path strip 120×24)         │ line 2 path strip
│ D3 invoice.paid  200 196ms  1→2▲ │ ▲ + "changed" word in
│   ╲▬                             │ accessible name
│ Retry after error                │
│ D8 invoice.paid  200 11 840ms △  │
│   ╲________△ (leaves band)       │
├──────────────────────────────────┤
│ [ Run again ] [ Save fixtures ]  │ 56 sticky
└──────────────────────────────────┘
```

Inspector: full-screen sheet with **Request | Response** tabs and the "Compare with D2" switch; the D3 path strip sits at the top of the sheet.

---

## 7. Light and dark

- **Light** is the default for the app and the landing: the timetable board ground is the identity. Dark is first-class for the app.
- The graph uses `ink` lines on `ground`; the amber allowance is a 30 % non-text fill, so in dark mode check that the dark `allowance` band still separates from `surface` (≥ 1.5:1 is enough for a band that is also bounded by its edge rule; the **edge rule itself** must hit 3:1).
- `now` magenta in dark (`#E977BB`) is close in lightness to dark `sev-high`; keep the now line **vertical, full height and labelled "now"** so its shape, not hue, identifies it.
- Red ■ on dark: use the dark `conflict` token; the bracket line is 1.5 px, which is non-text meaning, verify ≥ 3:1 against `surface` where it crosses the selected column.

## 8. Component inventory (A04 builds these as the Timetable system)

| Group | Components |
| ----- | ---------- |
| Graph | `TT/DeliveryGraph` (stations, segments, axis breaks, legend) · `TT/DeliveryLine` (states: pending, drawn, selected, hovered, broken/aborted, ghost) · `TT/StationAxis` · `TT/AllowanceBand` · `TT/NowLine` (live, paused, dashed/lost, static) · `TT/StateRailValue` · `TT/ConflictMarker` (■ + bracket) · `TT/Terminal` (clear bar, △, ▲, ■, ○) · `TT/AxisBreak` · `TT/GhostGraph` · `TT/PathStrip` (detail + mobile row) |
| Stream | `TT/ArrivalsHeader` · `TT/GroupHeaderRow` (+ `TT/SpacingSparkline`) · `TT/ArrivalRow` (default, hover, selected, new, body-not-kept) · `TT/SignatureStation` (6 states) · `TT/ShapeFlag` · `TT/NewArrivalsPill` · `TT/OnboardingPanel` (provider tabs) |
| Detail | `TT/DeliveryHeader` · `TT/VerdictLine` (signature, shape) · `TT/InvalidSignatureBlock` · `TT/HeaderTable` · `TT/RawViewer` (offset gutter, wrap, hex) · `TT/JsonTree` · `TT/RelatedSequence` |
| Run | `TT/RunHeader` · `TT/ScenarioChip` · `TT/RunVerdictLine` (all 7 states) · `TT/FindingEntry` (collapsed, open) · `TT/FixSnippet` · `TT/DeliveriesTable` · `TT/Inspector` (`RequestColumn`, `ResponseColumn`, `BudgetBar`, `BodyDiff`, `CompareToggle`, `StateChange`) · `TT/SetupForm` (agent step, target, events, scenario rows with flags, verdict level, warnings) · `TT/FixturesPreview` |
| Global | `TT/TopBar` · `TT/AgentPill` (6 states) · `TT/GuestMeter` · `TT/LiveIndicator` · `TT/RuledBanner` (info, warning, error) · `TT/Toast` · `TT/CommandPalette` · `TT/ShortcutSheet` · `TT/Skeleton` |
| Landing | `TT/HeroGraph` (animated + final frame) · `TT/ScenarioGlyphCell` · `TT/StationLine` (capture → CI) · `TT/RuledTable` · `TT/CodeSample` · `TT/ProviderList` · `TT/StickyCTA` |
| Icons | the 12 from A02 §I.6 + copy, overflow, filter, pause, external, chevrons on the same 45° grid |

## 9. Risks specific to this territory

1. **Graph literacy** (A02's own risk) is the deciding risk. Mitigated by A1–A4 but not removed.
2. **Two numberings on one graph:** *provider order* shows `#01/#03/#04` while lines are labelled `D4…D6`. Must be tested (question 3 below).
3. **Density at 13 px base** is tiring for long reading (finding explanations). Proposed: finding detail text at 15 px, the rest at 13.
4. **Magenta + red + orange + amber + green** all appear in the headline frame. That is still four colours with distinct shapes, but a running run adds the now line on top of findings; check greyscale legibility.
5. **Mobile loses the signature image.** Table-first is right for usability but means the phone never shows the brand's central picture except on landing. Accepted trade-off.

## 10. Comprehension test (run before A04 builds the full system)

- **Who:** 6 Integrators (backend devs who've wired a provider), not shown the legend, not told the product.
- **Stimulus A (10 s):** static desktop graph of Twice + Shuffled + Retry (D2–D9 only), no findings column, no legend.
- **Questions:** (1) Which delivery changed the state wrongly? (target: D3 or D9) (2) Which deliveries were sent in a different order from the provider? (target: D4–D6) (3) Which response was too slow? (target: D8) (4) What does the top row of labels (#01, #03…) mean?
- **Pass:** ≥ 5 of 6 answer (1) and (3) correctly within 10 s; ≥ 4 of 6 answer (2). Question 4 is diagnostic only.
- **Stimulus B:** same with the one-line legend. If B passes and A fails, ship the legend as always-on for the first run.
- **If it fails:** keep the territory's look but make the Deliveries table the primary run view and the graph a 120 px "overview strip" above it (A02's fallback).
