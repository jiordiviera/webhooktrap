# UX · Territory II: Crash Lab

**Status:** PROPOSED for A00 and PO review · **Author:** A03 (UX/UI) · **Date:** 2026-11-11
**Read with:** `ux-foundations.md` (behaviour, states, a11y: wins on conflicts), `brand-territories.md` §II (A02), `realistic-data.md` (the only content allowed).

Dimensions are approximate CSS px at 1440 × 900 and 390 × 844. Wireframes show structure and hierarchy, not style.

---

## 1. How the idea becomes structure

**Dominant structure: the test bay over the filmstrip, read out on instruments.** A run is a facility session: eight **rig cells** (one per scenario) give the verdict at a glance; under them a **filmstrip** of numbered **frames** (one per delivery) gives the sequence; the selected frame opens in the **readout**, where request and response sit side by side next to the **deformation gauge** (the odometer that rolls `1 → 2`).

Density is deliberately split: **low at overview** (bay, placard: readable from across the room or in a CI screenshot), **high inside readouts** (mono, instruments, rulers).

| | Primary | Secondary |
| - | ------- | --------- |
| Capture stream | **Specimen intake** list: tagged rows, quadrant signature glyph, one yellow Prove plate | Group headers as "batch" labels |
| Delivery detail | **Specimen sheet**: a header of large readings (signature, shape, size, ingest), then tabs | Raw / Decoded |
| Run | **Verdict placard** + **test bay** (8 rigs) + **filmstrip** (14 frames) | **Readout** (inspector) + **damage report** (findings) |
| Landing | Six-frame **filmstrip** of the duplicate impact; dark test cell | Bay grid, assembly line, light "floor" sections |

Type does the hierarchy: **Big Shoulders** (huge, condensed, caps) appears only where a **measurement or verdict** lives; everything else is **Schibsted Grotesk** at reading size; evidence is **Azeret Mono**.

### 1.1 How the key moments read

- **Duplicate:** frames D02 and D03 carry the **same tag `#01`** and a `×2` impact marker printed on the sprocket line between them.
- **1 → 2:** the **deformation gauge** in rig 02 (Twice) and in D03's readout: odometer digits roll from `1` to `2` in **one hard step**; a flag drops beside it labelled **expected 1**, filled with the finding's severity pattern (F2 is High: **45° hatch**, see C9).
- **Over budget:** each frame carries a mini **tolerance ruler** (0–12 s, hatched red zone from 10 s). D08's marker sits at 11 840 ms inside the hatch; D14's at 10 420 ms just inside it.
- **Tampered:** rig 06 shows a chevron with a broken shaft; D10's frame has the solid Critical swatch; the readout shows the body diff `amount_paid 2400 → 0`.
- **Late:** rig 04 reads **Pass · rejected as expected** (400 in 41 ms): a correct rejection is shown as intact.
- **Aborted:** frames after the loss are blank film with "not exposed".

---

## 2. Usability adjustments (PROPOSED; A02 to confirm)

| # | A02 says | Problem | Proposed adjustment |
| - | -------- | ------- | ------------------- |
| C1 | Specimen tags `SPC-0042`, related deliveries `SPC-0042-a`, `-b` | A third id system next to `#01` and `D3`, and the suffix duplicates what grouping already shows. | The **tag is the capture number `#01`**, set in a stencil tag shape. Grouping stays in group headers. "Specimen" is used only in helper copy ("Specimen #01 loaded"). |
| C2 | Frame counters `0001`, `0003` | Breaks the shared vocabulary: findings cite `D3`, the JSON says `D3`, the coding agent says `D3`. | Frame counter reads **`D03`** (zero-padded to two digits, Azeret Mono), keeping the instrument look and the shared name. |
| C3 | Prove is the one yellow control on screen | On the Run screen the primary action is Run / Run again, not Prove. | Rule becomes: **one yellow plate per screen = that screen's primary action** (Prove on Capture and Detail, Run in draft, Run again after a result, Save as fixtures when the run passed). |
| C4 | Rig cell states: idle, armed, firing, intact, damaged | "Damaged" as a label leans dramatic and isn't the shared vocabulary. | Rig label shows the plain result (**Pass**, **Finding · High**); the lab word sits under it as helper ("intact", "deformation recorded"). |
| C5 | Display jumps from 16 px to 64 px; 112 px readings | 112 px in the app pushes the bay below the fold. | 112 px only on landing. App maximum is **64 px** (placard verdict, gauge in readout); rig numbers 40 px. |
| C6 | Impact = 2-frame border inversion per frame | Burst fires three frames at once; three inversions in the same instant read as a flash. | Simultaneous impacts share **one** inversion. Never more than one inversion per 500 ms. None under reduced motion. |
| C7 | Severity by pattern (solid, 45° hatch, hazard stripe, outline) | Hatch and stripe are unreadable below ~14 px. | Pattern swatches are **≥ 16 × 16 px** and always followed by the severity word. In 28 px table rows the swatch is 16 × 16. |
| C9 | Hazard-stripe flag beside the odometer | Hazard stripe is the **Medium** pattern; F2 (duplicate) is **High**. A striped flag would mis-rank it. | The flag uses the finding's pattern: hatch for F2, solid for F1. Hazard stripes stay reserved for Medium and for warning banners. |
| C8 | Dark default for landing and marketing | The app's first screen after "Get a URL" would flip from dark landing to whatever the OS says. | Landing is dark; the app **follows the OS**; the onboarding (waiting) state uses the dark cell style in both modes, as a bridge from the landing, then the stream follows the OS. |

---

## 3. Landing

**Section order:** 1 Hero (dark test cell) → 2 **Filmstrip** (brand image, full-bleed) → 3 **Test bay**: every way a webhook can arrive → 4 **Assembly line**: capture → scrub → commit → CI on plates → 5 Works with your coding agent → 6 Floor section (light): secrets stay on your machine, open source, self-host → 7 Providers → 8 Footer CTA (dark).
Reason: the agent section moves above the trust section so the page alternates dark cell / light floor cleanly (A02 §II.8) and ends on reassurance before the CTA.

### 3.1 Desktop 1440

```
┌ dark test cell ────────────────────────────────────────────────────────────────────────────────┐
│ ◔ WEBHOOK TRAP (yellow placard)                      Docs  GitHub ★ 2.1k  Self-host  Sign in    │ 56
│                                                                                                 │
│  YOUR WEBHOOK HANDLER WORKS.                                       (Big Shoulders 112 px, caps, │
│  ONCE.                                                              2 lines, line height 0.9)   │ ~230
│  Then Stripe delivers it again. Webhook Trap runs real events through the impacts providers     │
│  actually deliver (duplicate, reordered, late, tampered) and measures what your state took.     │ 18 px
│  [■ GET A URL, NO SIGN-UP ] (yellow plate)   npx @webhooktrap/agent ⧉                           │ 56
├ filmstrip, full bleed, 6 frames × 220 × 260, sprocket rows top and bottom ─────────────────────┤
│ ▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫ │
│ ┌◔ D01 t+0.000s ◔┐┌ D01 t+0.184 ┐┌ D01 INTACT ┐┌◔ D02 #01 ×2 ┐┌ D02 t+0.196 ┐┌ D02 ▒▒▒▒▒▒▒▒ ┐ │
│ │ invoice.paid → ││ 200 · 184 ms ││ ODOMETER  │ │ same tag    ││ 200 · 196 ms││ ODOMETER [2]│ │ ~300
│ │                ││ [1]          ││ [1]       │ │ → impact    ││ [1]→ rolls  ││ NOT          │ │
│ └◔──────────────◔┘└──────────────┘└───────────┘└─────────────┘└─────────────┘│ IDEMPOTENT  │ │
│ ▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫ │
│ Duplicate delivery created a second order. Stripe may deliver the same event more than once.   │ 18 px
│                                                                           [❚❚ Pause film]      │
├ dark: TEST BAY ─────────────────────────────────────────────────────────────────────────────────┤
│ EVERY WAY A WEBHOOK CAN ARRIVE                                         (Big Shoulders 64)       │
│ ┌ 01 ────────┐┌ 02 ────────┐┌ 03 ────────┐┌ 04 ────────┐   4 × 2 rig cells, 300 × 180         │
│ │ ▶| ONCE    ││ ▶▶| TWICE  ││ ↗| SHUFFLED││ ◷▶| LATE   │   icon, name, what, why,            │ ~420
│ │ Baseline…  ││ Same event…││ Reverse…   ││ t − 360 s…  │   citation                         │
│ └────────────┘└────────────┘└────────────┘└────────────┘                                     │
│ 05 RETRY AFTER ERROR · 06 TAMPERED · 07 BURST · 08 SLOW BUDGET                                 │
├ light floor: ASSEMBLY LINE ─────────────────────────────────────────────────────────────────────┤
│ [CAPTURE #01] ──▶ [SCRUB customer_email →…] ──▶ [COMMIT fixtures/stripe/…] ──▶ [CI trap test]   │ ~240
├ dark: WORKS WITH YOUR CODING AGENT  (copy left · F2 JSON right, Azeret Mono)                    │ ~280
├ light floor: SECRETS STAY ON YOUR MACHINE · OPEN SOURCE · SELF-HOST (3 columns, readings style) │ ~260
├ light floor: Stripe · Shopify · GitHub · Twilio · Slack · Standard Webhooks (text)              │ 80
├ dark: PROVE YOUR FIRST HANDLER IN 5 MINUTES.  [■ GET A URL, NO SIGN-UP ]                         │ 240
└─────────────────────────────────────────────────────────────────────────────────────────────────┘
```

**Film motion:** on scroll into view, frames advance in **steps** (one frame every 280 ms, `steps()`), D02's odometer rolls in one hard step at frame 5, the hatched (High) flag drops at frame 6, the stencil verdict stamps once. Plays once, then holds frame 6. Reduced motion: all six frames visible in final state, no stamping. The stencil NOT IDEMPOTENT is decorative; the plain sentence under the strip carries the meaning.

### 3.2 Mobile 390

```
┌──────────────────────────────────┐
│ ◔ WEBHOOK TRAP          ☰        │ 52 dark
│ YOUR WEBHOOK                     │ Big Shoulders 56, 4 lines
│ HANDLER WORKS.                   │
│ ONCE.                            │
│ Then Stripe delivers it again…   │ 16
│ [■ GET A URL, NO SIGN-UP     ]   │ 52 yellow plate, full width
│ npx @webhooktrap/agent ⧉         │
├ filmstrip as a 2 × 3 contact sheet ┤
│ ┌D01 t+0┐┌D01 200┐               │ 165 × 150 frames,
│ │→      ││ [1]   │               │ read left→right, top→bottom
│ ┌D01 OK ┐┌D02 ×2 ┐               │
│ ┌D02 200┐┌D02 [2]▒┐              │
│ Duplicate delivery created a     │
│ second order.                    │
├──────────────────────────────────┤
│ TEST BAY: 1-column rig list      │ 72 rows: number, icon, name, why
├──────────────────────────────────┤
│ Assembly line: plates stacked    │
│ vertically with ▼ between        │
├──────────────────────────────────┤
│ [■ GET A URL, NO SIGN-UP] sticky │ 56
└──────────────────────────────────┘
```

---

## 4. Capture stream: specimen intake

### 4.1 Desktop 1440

```
┌ top bar 48 ─────────────────────────────────────────────────────────────────────────────────────────────┐
│ ◔ Brindle Coffee ▾   Capture · Runs · Fixtures · Agent    webhooktrap.dev/c/Br7nDLk2Qm9x ⧉   ◕ maya-mbp ⌘K │
├ intake header 64 ───────────────────────────────────────────────────────────────────────────────────────┤
│ INTAKE  (Big Shoulders 28)   ● Live (armed dot)   13 specimens · 6 batches · last 14:06:39   [Grouped│Flat] │
├ list 520 ─────────────────────────────────────────────┬ specimen sheet 920 ─────────────────────────────┤
│ BATCH sub_1QH…4drI · Stripe · 4 · 1.2 s  [■ PROVE]    │ (Screen 3, §5)                                   │ 44
│ ▌#01  13:58:04.212  Stripe  invoice.paid   2 294 B  ◕ │                                                  │ 36 rows
│  #02  13:58:04.395  Stripe  invoice.paym…  2 307 B  ◕ │                                                  │
│  #03  13:58:04.823  Stripe  payment_inte…    915 B  ◕ │                                                  │
│  #04  13:58:05.416  Stripe  customer.sub…  1 496 B  ◕ │                                                  │
│ BATCH Order #1042 · Shopify + Parcelbird · 4 [■ PROVE]│                                                  │
│  #05  13:59:12.614  Shopify orders/create  1 064 B  ◕ │                                                  │
│  #08  14:01:11.442  Parcel. shipment.lab…    356 B  ◌ No secret                                         │
│ BATCH brindle/brindle-app · GitHub · 2    [■ PROVE]   │                                                  │
│  #09  14:02:08.731  GitHub  pull_request     686 B  ⊘ Invalid (cracked target)                          │
│ …                                                     │                                                  │
└───────────────────────────────────────────────────────┴──────────────────────────────────────────────────┘
```

- **Tag column:** `#01` in a 40 × 20 stencil tag (Azeret Mono 12 bold on `surface`, ink outline).
- **Signature quadrant glyph (20 px):** four filled quadrants = valid; cracked target (diagonal break) = invalid, always with the word; outlined quadrants, no fill = no secret; one quadrant missing = unsigned; quadrants with "?" = unknown scheme; greyed = not checked.
- **Prove:** a yellow plate on every batch header. On rows, Prove appears on hover/focus/selection as a **smaller yellow plate**; still one yellow *kind* of control per screen (C3).
- **Live arrival:** the new row appears in one **step** (no slide): its tag inverts for 2 frames (≈ 80 ms) and settles. Armed dot next to Live stays steady (no pulse) except during pairing.

### 4.2 Mobile 390

```
┌──────────────────────────────────┐
│ ◔ Brindle Coffee ▾      ◕ maya   │ 52
│ INTAKE ● Live · 13   [Copy URL]  │ 48
├──────────────────────────────────┤
│ BATCH sub_1QH…4drI · 4 [■ PROVE] │ 48 sticky
│ #01 invoice.paid              ◕  │ 56: tag + event / time,
│     13:58:04.212 · Stripe · 2 KB │     provider, size
│ #02 invoice.payment_succeeded ◕  │
│ …                                │
│ #09 pull_request          ⊘ Invalid│
├──────────────────────────────────┤
│ Capture  Runs  Agent  More       │ 56
└──────────────────────────────────┘
```

### 4.3 States

| State | Crash Lab rendering |
| ----- | ------------------- |
| Waiting | Dark cell panel (both modes, C8): "BAY EMPTY" in Big Shoulders 64, then the plain label "Waiting for the first delivery", URL in Azeret Mono 28 with Copy, provider tabs, and an **outlined quadrant target** idling. Helper (A02): *Send one real event to this URL to load the first specimen.* |
| Loading | 8 rows with tag outlines and grey bars; stepped reveal off. |
| Paused | Armed dot turns outlined grey; label "Paused"; counter "4 new while paused". |
| Catching up | Rows appear without inversion. |
| Stream lost | Hazard-stripe banner (Medium style) "Live updates interrupted. Reconnecting…". |
| Quota reached | Hazard-stripe banner with "GUEST LIMIT · 100 / 100" in Big Shoulders 28 + dataset copy. |
| Expired | Dark cell: "THIS GUEST URL EXPIRED" + helper + yellow plate **Get a new URL**. |

---

## 5. Delivery detail: specimen sheet

### 5.1 Desktop (920 pane)

```
┌ specimen sheet 920 ──────────────────────────────────────────────────────────────────────┐
│ #01 (tag)  Stripe · invoice.paid  (Schibsted 20 / 600)                   [■ PROVE] [⋯]     │ 64
├ readings row: 4 cells × 210, labels 12 px caps-free, values Big Shoulders 28 ────────────┤
│ SIGNATURE        │ SHAPE             │ SIZE              │ INGEST                         │ 88
│ ◕ VALID          │ MATCHES           │ 2 294 B           │ 112 ms                         │
│ Stripe v1 · …3f9a│ 41 fields · 6 prev│ application/json  │ 0.2 s after created            │
├ Verdict │ Raw │ Decoded ─────────────────────────────────────────────────────────────────┤
│ Provider metadata (key/value, Azeret Mono values)                                        │
│ Batch sub_1QH…4drI: [#01][#02][#03][#04] tags in arrival order                           │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**#09 invalid:** the SIGNATURE reading becomes "⊘ INVALID" in `sev-critical` text, and a full-width block under the readings with a **solid Critical swatch** (16 × 16) and the reason, expected/received prefixes, body integrity "7 / 7 checks", the fix, and **Re-check**. The other readings stay.

**Raw:** Azeret Mono 12 on `surface`; header table with index; body with byte gutter; wrap toggle.

### 5.2 Mobile

Readings become a 2 × 2 grid (165 × 72); tabs below; yellow **PROVE** sticky plate at the bottom when an agent is connected.

---

## 6. Run: the test bay

### 6.1 Desktop 1440 · headline frame (findings, F2 open, D03 selected, compare with D02)

```
┌ top bar 48 ─────────────────────────────────────────────────────────────────────────────────────────────────┐
├ run header 48: Capture › sub_1QH…4drI › Run #3 · localhost:3000/webhooks/stripe via ◕ maya-mbp   [■ RUN AGAIN] [Save as fixtures] [⋯] │
├ verdict placard 80 ────────────────────────────────────────────────────────────────────────────────────────┤
│ TEST 03 · 4 FINDINGS  (Big Shoulders 64)   ■ 1 Critical  ▨ 1 High  ▤ 1 Medium  □ 1 Info   8 scenarios · 14 deliveries · 41.0 s │
├ test bay: 8 rig cells × 172 × 112 ─────────────────────────────────────────────────────────────────────────┤
│┌01 ONCE ──┐┌02 TWICE ─┐┌03 SHUFFLED┐┌04 LATE ──┐┌05 RETRY ─┐┌06 TAMPERED┐┌07 BURST ─┐┌08 SLOW ──┐       │
││ Pass     ││ Finding  ││ Pass      ││ Pass     ││ Finding  ││ Finding   ││ Pass     ││ Finding  │       │
││ intact   ││ ▨ High   ││ □ Info    ││ rejected ││ ▤ Med ▨ H││ ■ Critical││ intact   ││ ▤ Medium │       │
││ [1]      ││ [1]→[2]▒ ││ [1]       ││ 400 41ms ││ [1]→[2]▒ ││ [1] amt 0 ││ [1]      ││ 10 420ms │       │
│└──────────┘└──────────┘└───────────┘└──────────┘└──────────┘└───────────┘└──────────┘└──────────┘       │
├ filmstrip: 14 frames × 96 × 104, grouped under rig brackets ───────────────────────────────────────────────┤
│ ▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫▫ │
│ [D01][D02]×2[▌D03▐][D04][D05][D06][D07][D08][D09][D10][D11][D12][D13][D14]        [Table ⇄]              │
│  200  200    200    200  200  200  400  200  200  200  200  200  200  200                                 │
│  ├┤   ├┤     ├┤     ├┤   ├┤   ├┤   ├┤   ├──▒▌ ├┤   ├┤   ├┤   ├┤   ├┤   ├─▒▌   (tolerance rulers)           │
│  1    1      2▒     0    0    1    0    1    2▒   1    1    1    1    1                                   │
│ └01┘└──02──┘ └────03──────┘ └04┘ └──05───┘ └06┘ └────07─────┘ └08┘                                         │
├ readout 940 ───────────────────────────────────────────────────────────┬ damage report 452 ────────────────┤
│ D03 · TWICE · invoice.paid · tag #01 · t+1.950 s · 14:09:04.068  ‹ ›    │ ■ CRITICAL  F1  Accepts tampered  │
│ ┌ REQUEST ──────────────────────┐┌ RESPONSE ─────────────────────┐┌GAUGE┐│   payload · D10                   │
│ │ POST localhost:3000/webhooks/ ││ 200 (Big Shoulders 40)         ││ ┌─┐ ││▶▨ HIGH  F2  Duplicate delivery    │
│ │ stripe · 2 294 B              ││ 196 ms                         ││ │2│ ││   created a second order          │
│ │ re-signed (same event id)     ││ ├──┤░░░░░░░░░░░░▒▒▒ 10 s ruler  ││ └─┘ ││   Level 2 · state probe           │
│ │ Stripe-Signature t=…,v1=df77… ││ {"received":true}              ││ ▒▒▒ ││   Evidence D02 D03 · D08 D09      │
│ │ X-Trap-Delivery: D3           ││ X-Powered-By: Express          ││EXPEC││   Measured: 200 in 196 ms ·       │
│ │ Body = exact bytes of #01     ││                                ││TED 1││   orders.count 1 → 2              │
│ └───────────────────────────────┘└────────────────────────────────┘└─────┘│   Why it matters · Likely cause   │
│ [■ Compare with D02]  D02: 200 · 212 ms · {"received":true} · orders.count 0 → 1   ← identical response  │   Fix (Express + Postgres) [code] │
│                                                                         │   [Copy fix] [JSON]               │
│                                                                         │ ▤ MEDIUM F3 · □ INFO F4           │
└─────────────────────────────────────────────────────────────────────────┴───────────────────────────────────┘
```

- **Vertical budget at 900 px:** top bar 48 + header 48 + placard 80 + bay 112 + filmstrip 140 = 428; readout zone 472. The bay and filmstrip stay fixed; readout and damage report scroll independently.
- **Rig cell:** number (Big Shoulders 40), name, plain result + severity swatch + word, helper lab word, mini gauge or key reading. Clicking a rig filters the filmstrip highlight to its frames and selects its first finding-bearing frame.
- **Frame:** `D03` counter, status, mini tolerance ruler (0–12 s, hatch from 10 s), probe digest; the frame of a finding gets its severity swatch on the top edge; the selected frame has a 2 px ink border and the sprocket holes above it filled.
- **`×2` marker** sits between D02 and D03 (and `retry` between D08 and D09) on the sprocket line.
- **Deformation gauge (readout):** 3-digit odometer window, the changed digit in `sev-high` text, hatched flag "expected 1" under it. In the passed run it reads `1` with no flag.
- **Damage report:** a table-like list ordered by severity; the open finding expands in place.
- **Table toggle** on the filmstrip swaps frames for the 14-row table (dataset §4.2) in the same space; this is also the accessible equivalent.

### 6.2 Running (D09 of 14)

Rigs fire in sequence: idle (outlined) → **armed** (cyan dot, steady) → firing (number inverts once) → result locks in. Frames are blank film until exposed; each frame exposes in one step when its response arrives and its probe value prints when the probe answers. The placard reads "TEST 03 · RUNNING · D09 / 14" with elapsed time ticking in Azeret Mono. Findings land in the damage report as they are found. Auto-follow moves the selected frame with the run until the user picks one.

### 6.3 Other run states

| State | Rendering |
| ----- | --------- |
| Draft | Placard "TEST 04 · DRAFT · 8 scenarios · 14 deliveries · about 45 s". The bay shows 8 **selectable rig cells** (checkbox = the cell itself, with "Needs secret" / "Needs 2+ related events" / "May repeat side effects" hazard tags). Agent, target, events and verdict level sit in a left column above the bay ("Rig setup"). Yellow plate **RUN**. |
| Queued | Bay cells outlined, placard "WAITING FOR RIG maya-mbp…". |
| Passed (#4) | Placard "TEST 04 · PASSED · 8 / 8" with a solid `pass` swatch; every rig "Pass · intact"; gauges read `1`; yellow plate moves to **Save as fixtures**. Damage report: "No damage recorded." + list of checks. |
| Aborted (#2) | Placard "TEST 02 · ABORTED"; helper (A02): *Rig offline. The agent on maya-mbp stopped responding at D03. D01–D02 and their readings are kept.* Frames from D03 on are blank film labelled "not exposed"; rigs after 02 greyed with quadrants greyed. Actions: Reconnect (command) · Run remaining. |
| Inconclusive (#1) | Gauges show `–` with "no probe"; placard "INCONCLUSIVE"; damage report Info item with the probe yaml. |
| Target unreachable mid-run | That frame shows "NO CONTACT · connection refused" across it, outlined, no ruler. |

### 6.4 Mobile 390 (report-first)

```
┌──────────────────────────────────┐
│ ‹ Run #3              [⋯]        │ 52
├──────────────────────────────────┤
│ TEST 03                          │ Big Shoulders 48
│ 4 FINDINGS                       │
│ ■1 Crit ▨1 High ▤1 Med □1 Info   │
│ 8 scenarios · 14 deliveries · 41s│ ~150
├──────────────────────────────────┤
│ DAMAGE REPORT                    │
│ ■ Critical · Accepts tampered…   │ 64 each → detail sheet
│ ▨ High · Duplicate delivery…  [2]│ mini odometer in row
│ ▤ Medium · Exceeded Stripe's…    │
│ □ Info · Probe doesn't see…      │
├──────────────────────────────────┤
│ RIGS (8 rows, 56 each)           │
│ 02 TWICE  Finding ▨ High  1→2    │
├──────────────────────────────────┤
│ FRAMES (vertical stack, 64 each) │
│ D03 · 200 · 196 ms · ├┤ · 1→2 ▒  │
├──────────────────────────────────┤
│ [■ RUN AGAIN] [Save fixtures]    │ 56 sticky
└──────────────────────────────────┘
```

Readout: full-screen sheet, gauge at top (64 px digits), then Request | Response tabs, Compare with D02 switch.

---

## 7. Light and dark

- **Landing dark, app follows the OS** (C8). Test both app modes fully; the brand will be remembered dark, so A04 should present dark app frames first.
- **Yellow is fill only on light grounds** (ink on yellow 13:1). Never yellow text on concrete. In dark, `sev-medium` text *is* yellow; make sure hazard-stripe fills and Medium text don't sit next to the yellow Prove plate in the same row.
- **Hazard stripes and hatches** are non-text: their stripes need ≥ 3:1 against the ground they sit on, in both modes. On concrete-light the yellow stripe alone fails 3:1, so the **ink stripes** must carry the contrast.
- **Armed cyan** is only for small indicators (dots, a frame edge), never fills larger than 12 × 12.
- Concrete light ground `#ECEBE8` is darker than `surface`; readouts sit on `surface` so mono text gets the stronger ground.

## 8. Component inventory (Crash Lab system)

| Group | Components |
| ----- | ---------- |
| Run | `CL/VerdictPlacard` (draft, queued, running, passed, findings, aborted, inconclusive) · `CL/RigCell` (idle, selectable, armed, firing, pass, finding by severity, greyed/aborted, flagged needs-secret/side-effects) · `CL/Filmstrip` (+ `CL/SprocketRow`, `CL/RigBracket`, `CL/ImpactMarker ×2/retry`) · `CL/Frame` (blank, exposing, pass, finding, selected, not-exposed, no-contact) · `CL/ToleranceRuler` (mini, full) · `CL/DeformationGauge` (odometer 1–3 digits, rolling, with flag) · `CL/SeverityFlag` (solid, hatch, stripe, outline) · `CL/Readout` (`RequestPanel`, `ResponsePanel`, `BodyDiff`, `CompareSwitch`) · `CL/DamageReport` + `CL/DamageEntry` (collapsed, open) · `CL/FixSnippet` · `CL/RigSetup` (agent, target, events, verdict level) · `CL/FixturesPreview` |
| Stream | `CL/IntakeHeader` · `CL/BatchHeader` · `CL/SpecimenRow` (default, hover, selected, new, body-not-kept) · `CL/SpecimenTag` · `CL/QuadrantGlyph` (6 signature states) · `CL/ShapeFlag` · `CL/NewSpecimensPill` · `CL/BayEmptyPanel` (onboarding) |
| Detail | `CL/SpecimenSheetHeader` · `CL/ReadingCell` · `CL/InvalidSignatureBlock` · `CL/HeaderTable` · `CL/RawViewer` · `CL/JsonTree` · `CL/BatchTags` |
| Severity | `CL/SeveritySwatch` (solid, hatch, stripe, outline; 16 px min) + word |
| Global | `CL/TopBar` · `CL/YellowPlate` (primary action, sizes S/M/L) · `CL/AgentPill` (6 states, target icon) · `CL/GuestMeter` · `CL/LiveIndicator` (armed dot) · `CL/HazardBanner` · `CL/Toast` · `CL/CommandPalette` · `CL/ShortcutSheet` · `CL/Skeleton` |
| Landing | `CL/HeroCell` · `CL/HeroFilmstrip` (6 frames, stepped + final) · `CL/BayGridCell` · `CL/AssemblyPlate` · `CL/FloorSection` · `CL/CodeSample` · `CL/StencilStamp` (decorative) |
| Icons | the 12 from A02 §II.6 (target + chevron family) + copy, overflow, filter, pause |

## 9. Risks specific to this territory

1. **Tone drift.** The structure invites drama (stencil stamps, hazard flags). Copy must stay clinical; the plain sentence always carries the meaning (§3.1).
2. **Three overview layers** (placard, bay, filmstrip) before the evidence. On 1440 × 900 the readout starts at y ≈ 430; on 1280 × 720 laptops it starts near the fold. If tests show users miss the readout, collapse the bay into a single 48 px row of rig chips while a frame is selected.
3. **Yellow fatigue** in long sessions (A02). The plate rule (C3) limits it to one control per screen.
4. **Pattern legibility** in dense tables (C7).
5. **Horizon 2 fit:** crash imagery on a production-monitoring dashboard feels wrong. Not a design-round blocker, but the PO should weigh it.

## 10. Comprehension test

- **Who:** 6 Integrators, not told the product.
- **Stimulus A (10 s):** the desktop Run headline frame without the damage report.
- **Questions:** (1) Did this handler pass? (target: no) (2) Which scenario produced the most serious problem? (target: Tampered, rig 06) (3) What does the number change in rig 02 mean? (target: "a second order/record was created") (4) Order the four pattern swatches from most to least serious, without labels visible (diagnostic).
- **Stimulus B (tone):** landing hero + filmstrip. Ask for three adjectives. **Fail signal:** ≥ 2 of 6 say "aggressive", "alarmist", "gimmicky" or similar; then reduce stencil and hazard use on landing.
- **Pass:** ≥ 5 of 6 answer (1) and (3); ≥ 4 of 6 answer (2) within 10 s.
