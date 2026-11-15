# Brand Territories: Webhook Trap 2.0

**Status:** PROPOSED for A00 and PO review · **Author:** A02 (Brand Strategist) · **Date:** 2026-11-11
**Based on:** A01's product strategy in `.worktrees/p1b-product/project/product/` (vision, positioning, core-experience Part 2, personas, concepts, problems). Recommended concept: **B · Proving Ground**.
**Consumers:** A03 (UX) turns these into experiences. A04 builds full visual systems and screens in Claude Design and verifies every contrast pair.

**Provenance.** These territories were written from A01's strategy alone. None of the following was opened or used: the superseded directions, `.worktrees/a02-brand/`, the 1.x `DESIGN.md`/`PRODUCT.md`/`branding/`, the 1.x palette or typefaces, or other dev-tool brands.

---

## 0. The brief in one paragraph, and the rules all three territories share

Webhook Trap 2.0 is not "a better inspector". It is **the test suite for webhook handlers**. It captures real events, replays them the way providers actually deliver (twice, shuffled, late, after a timeout, tampered, unsigned), and **judges the handler by what happened to its state**. The brand has to make one moment unforgettable: **one event delivered twice, a state counter going from 1 to 2, and a finding appearing.** The tone A01 set is *precise, calm, a little dry. It judges code, not people, and uses provider docs as the authority.*

Rules every territory below follows:

1. **Product nouns don't change.** *Prove, capture, delivery, scenario (Once, Twice, Shuffled, Late, Tampered, Unsigned, Slow budget, Retry after error, Burst, Sequence pack), run, finding, fixture, agent, probe* stay as A01 named them. A territory's metaphor shapes visuals, motion and some copy. It never renames controls. **Plain label first, territory flavour second** (for example, "Agent disconnected" as the label, with the territory's line in the helper text).
2. **The verdict is the headline, raw bytes are one click away** (A01 principle 2). Every territory gives the raw payload a first-class monospace treatment.
3. **Severity is never encoded by colour alone.** Each territory pairs colour with a second channel (glyph, shape, pattern, weight).
4. **WCAG AA.** All text tokens below are ≥ 4.5:1 against their own ground (computed from the hex values; A04 re-verifies on every surface). Fills and lines that are not text are marked *non-text* and need ≥ 3:1 only where they carry meaning.
5. **Avoided in all three:** purple or blue SaaS gradients, Inter-everywhere templates, dark-blue observability dashboards, crypto neon, glassmorphism, gradient text, emoji, left-border cards.
6. **Name.** A01's open question Q12 notes that "Trap" suggests capture more than proof. Each territory below **keeps "Webhook Trap"** and gives "Trap" a meaning native to its own world. That argues the name can carry the "proof" category without a rename. A rename stays open, but none of the three requires one.

The three territories, in one line each:

| | Territory | Stance: what the product *is* |
| - | --------- | ----------------------------- |
| **I** | **Timetable** (quietly rigorous) | A **dispatcher's working timetable**. Providers run a real delivery schedule, and we test your handler against it, drawn as a time–distance graph. |
| **II** | **Crash Lab** (bold, physical) | A **destructive test facility**. Your handler is the specimen, scenarios are impacts, and findings are measured damage. |
| **III** | **Notation** (unexpected, cerebral) | A **chess game record**. Deliveries are moves, state is the position, and findings are annotations (`??`, `?`, `?!`) with the better move shown. |

---

# Territory I: Timetable

## I.1 Core idea

Providers don't deliver once, on demand. They run a **timetable**: at least once, in no guaranteed order, within a time allowance, with retries on a schedule. Webhook Trap draws your handler's run as a **time–distance graph** (the railway planner's *Marey chart*). Each delivery is a line travelling from *provider sent* → *arrived* → *handler responded* → *state*. Duplicates run as parallel lines, reordering shows as lines that cross, latency is the slope, and the provider's budget is a band the line must clear. A finding is a **conflict**: the point where two paths that should leave state unchanged don't.

It expresses Proving Ground's belief in its purest form: *webhooks fail in sequences, not in payloads.* The brand's central image is the sequence itself.

**What "Trap" means here:** *trap points* are the short spur on a railway that catches a runaway vehicle **before it fouls the main line**. Webhook Trap catches a bad handler before it reaches production.

## I.2 Personality and voice

**Traits:** exact, unhurried, timestamped. It reads like a good signalling log: every statement has a time, a place and a reference.

- **Hero headline:** *Your webhook handler works. Once.*
- **Subline:** *Providers run a different timetable: twice, out of order, late, retried. Webhook Trap replays real events on that timetable and shows you where your state diverged.*
- **Alternate line (campaign):** *On time, once. Now try the real schedule.*
- **Finding:** `Conflict at D2` · **Not idempotent.** The second delivery of `evt_1Nx…` changed state: `subscriptions 1 → 2`. Stripe delivers each event at least once (Stripe docs › Webhooks › Duplicate events). Guard on `event.id` before writing.
- **Empty state (capture stream):** *No arrivals yet. Point Stripe test mode at this URL. The first delivery appears here within a second.*
- **Error (agent lost mid-run):** *Agent unreachable since 14:02:11.384. The run stopped at D3 of 8. D1–D2 and their results are kept. Reconnect `maya-mbp` to resume.*

Voice rules: numbers and times are always exact (`412 ms`, never "fast"). No exclamation marks. Words like "conflict", "path" and "allowance" are allowed. Railway jargon ("relief train", "headway") is not used in the UI.

## I.3 Logo

- **Mark: the trap points.** On a 16-unit grid:
  - a horizontal **main line** (stroke 2u) runs the full width at y = 6;
  - a **spur** leaves it at x = 4 on a 45° diagonal down to the right (stroke 2u);
  - the spur ends in a solid 3 × 3u **buffer stop**.
  - The main line and spur are in ink. The buffer stop is the only colour, in Conflict red.
  - It reads as "a path diverted and stopped safely".
- **Wordmark:** "Webhook Trap" in Archivo, width 87.5, weight 650, tracking −1 %. Sentence case, no all-caps.
- **Lockup:** the mark's main line aligns with the wordmark's x-height midline, so the line seems to run into the type. The mark also works alone as the app icon.
- **16 px favicon:** three shapes at 2 px strokes (horizontal, diagonal, square). Tested shape: ink on ground with a red square. In dark mode, ground-ink inverts and the red stays.
- **Do not:** animate the mark like a train, add wheels, or use split-flap lettering.

## I.4 Colour system

**Logic: ink does the work, and colour means one of three things only.** The ground is a faintly warm, achromatic "timetable board". Lines, rules and type are all in one ink. Colour appears only for (a) a conflict or finding, (b) **now** (the live position on the time axis), and (c) the provider's **allowance band**. A passing run is nearly monochrome, which is calm by design. Red is unmissable because nothing else is red.

The "now" line is **magenta**, borrowed from the convention for the *active route* on navigation displays. It is distinctive, it can't be mistaken for a severity colour, and it isn't blue.

| Token | Role | Light OKLCH | Light hex | Dark OKLCH | Dark hex | Contrast vs own ground (L / D) |
| ----- | ---- | ----------- | --------- | ---------- | -------- | ------------------------------ |
| `ground` | page | `oklch(0.975 0.004 100)` | `#F7F7F4` | `oklch(0.19 0.006 100)` | `#141411` | — |
| `surface` | panels, selected row | `oklch(0.95 0.005 100)` | `#EFEFEB` | `oklch(0.23 0.007 100)` | `#1E1D19` | — |
| `rule` | hairlines, grid (non-text) | `oklch(0.86 0.006 100)` | `#D2D1CD` | `oklch(0.34 0.008 100)` | `#393833` | — |
| `ink` | text, graph lines | `oklch(0.22 0.010 100)` | `#1C1B15` | `oklch(0.93 0.006 100)` | `#E9E8E3` | 16.1 / 15.0 |
| `ink-muted` | secondary text, axis labels | `oklch(0.47 0.010 100)` | `#5C5B55` | `oklch(0.72 0.008 100)` | `#A6A59F` | 6.4 / 7.5 |
| `conflict` | accent 1 = Critical | `oklch(0.53 0.20 29)` | `#C51F17` | `oklch(0.68 0.18 30)` | `#F36451` | 5.5 / 5.9 |
| `now` | accent 2 = Live, now line | `oklch(0.52 0.20 345)` | `#B12083` | `oklch(0.72 0.16 345)` | `#E977BB` | 5.8 / 6.9 |
| `allowance` | provider budget band (non-text, 30 % fill) | `oklch(0.82 0.11 85)` | `#E5BF6D` | `oklch(0.55 0.10 85)` | `#8C6C1F` | non-text |
| `pass` | Pass / clear | `oklch(0.50 0.10 155)` | `#2A7449` | `oklch(0.74 0.13 155)` | `#5EC386` | 5.3 / 8.5 |
| `sev-critical` | Critical (security) | = `conflict` | `#C51F17` | = `conflict` | `#F36451` | 5.5 / 5.9 |
| `sev-high` | High (state divergence) | `oklch(0.52 0.125 52)` | `#9F5013` | `oklch(0.75 0.14 55)` | `#F1944F` | 5.4 / 8.0 |
| `sev-medium` | Medium (budget, 4xx trap) | `oklch(0.52 0.105 80)` | `#886108` | `oklch(0.82 0.13 85)` | `#EBBD57` | 5.2 / 10.5 |
| `sev-info` | Info (suggestion) | `oklch(0.50 0.04 250)` | `#52657A` | `oklch(0.75 0.05 250)` | `#97B1CD` | 5.6 / 8.3 |

- **Agent states:**
  - connected = `pass`;
  - pairing = `now`, with a moving tick;
  - target unreachable = `sev-high`;
  - disconnected mid-run or aborted = `ink-muted` with a dashed line (the graph line literally breaks);
  - version too old = `sev-medium`.
- **Second channel for severity (line weight and terminal shape on the graph):** Critical = filled square ■, High = filled triangle ▲, Medium = open triangle △, Info = open circle ○, Pass = a short horizontal "clear" bar.
- **Contrast intent:** all text tokens ≥ 4.5:1 on `ground` and `surface`. Text on a `conflict` fill uses `ground` (5.5:1 light, 5.9:1 dark).

## I.5 Typography

| Role | Family | Licence / source | Use |
| ---- | ------ | ---------------- | --- |
| Display + UI (one family) | **Archivo** (variable: wght 100–900, **wdth 62–125**) | OFL · Google Fonts | Condensed widths (75–87.5) for headlines, column heads and graph labels. Normal width (100) for UI text. Tabular figures throughout. |
| Mono (payloads, IDs, times) | **Martian Mono** (variable: wdth 75–112.5) | OFL · Google Fonts | Width 87.5 for payloads and headers, so dense JSON fits without being cramped. Times like `14:02:11.384` always in mono. |

**Type logic: one grotesk, many widths.** Hierarchy comes from width and weight, the way a printed timetable works, never from a second display face. Numbers are the main content, so tabular figures are on by default (A04: verify `tnum` in Archivo, with Public Sans as fallback).

**Scale (px, 4 px baseline):** 11 (axis labels, mono) · 12 (table meta) · **13 (UI base)** · 15 (pane titles) · 18 · 24 (run verdict) · 32 · 48 · 72 (landing display, wdth 75, weight 700). Line height 1.35 for UI and 1.1 for display.

## I.6 Iconography

- **Rules:**
  - 16 px grid, 1.5 px stroke, square caps and mitred joins;
  - **only horizontal, vertical and 45° lines** (the geometry of the graph);
  - circles are allowed only for "stations" (pipeline stages);
  - no fills except the severity terminals.
- **Key icons:**
  1. **Prove:** one diagonal line passing through three station ticks, ending in an arrowhead.
  2. **Once:** a single diagonal.
  3. **Twice:** two parallel diagonals (the second one dashed in its "pending" state).
  4. **Shuffled:** two diagonals that cross.
  5. **Late:** a diagonal starting to the left of a vertical tolerance line.
  6. **Tampered:** a diagonal with a sideways kink (the path was altered).
  7. **Unsigned:** a diagonal through an *open* (unfilled) station circle.
  8. **Slow budget:** a shallow diagonal running out of a shaded band.
  9. **Burst:** three diagonals fanning out from one point.
  10. **Verdict (pass / finding):** a horizontal "clear" bar, or two lines meeting at a filled square (conflict).
  11. **Agent:** a station circle linked by a vertical line to a small square (your machine).
  12. **Signature:** two concentric station circles, the inner one filled when valid.

## I.7 Interface approach

- **Layout philosophy:** a ruled sheet.
  - 8-column grid on a 4 px baseline. Hairline rules separate everything, with no cards and no shadows.
  - Time always runs **left → right** on wide screens. Every timestamp shows both absolute (`14:02:11.384`) and relative (`+412 ms`) time.
- **Density:** high. 28 px rows and 13 px base. A run with 40 deliveries fits on one laptop screen without scrolling the graph.
- **Capture stream ("arrivals"):**
  - Rows read like an arrivals board: arrival time · provider · event type · related object · size · signature glyph.
  - Related deliveries are grouped under a **group header row** (object ID, with a count and a tiny inline graph of their arrival spacing), indented rather than boxed.
  - New rows slide in from the top with a 120 ms linear move, and the magenta now-tick blinks once.
  - **Prove** sits right-aligned on every group header and every row.
- **Delivery detail:**
  - The right pane opens with a **path strip**: a single mini-graph line from sent → arrived → responded, with the allowance band behind it.
  - Below it are the tabs Verdict / Raw / Decoded. Raw is Martian Mono with header order preserved and byte offsets in the gutter.
- **Run screen (the main screen):**
  - **Top:** target (`localhost:3000/webhooks/stripe` via `maya-mbp`), scenario chips, and run state.
  - **Centre: the Delivery Graph**, the signature component.
    - **y-axis stations:** *sent · arrived · responded · state*. **x-axis:** time.
    - Each delivery is a line labelled D1, D2… Its slope between *arrived* and *responded* is the latency. The provider's allowance is an amber band, and a line that leaves the band late gets a △.
    - The **state rail** at the bottom shows the probe digest as a value at each line's end.
  - **Below the graph:** a synced table, one row per delivery. Hovering a row highlights its line, and the reverse.
  - **Right column:** findings sorted by severity. Each one cites its deliveries (`D1, D2`) and links to request and response side by side.
- **Signature components:**
  - **Duplicate delivery:** D2 runs parallel to D1, labelled `D2 = D1′`, with the same event ID and a new delivery ID.
  - **State counter 1 → 2:** on the state rail, D1 ends at `1` and D2 ends at `2`. A red ■ **conflict marker** sits on D2's end and draws a thin red bracket back to D1's end. The finding title sets in beside it.
  - **Shuffled:** crossing lines, where the crossing point itself is the evidence.
  - **Late:** the line's origin sits left of the tolerance line, so it was "sent" outside the window.
  - **Tampered / Unsigned:** the line passes the *arrived* station with an open or kinked marker. If the handler still returns 2xx, the conflict lands on *responded*.
- **Motion principles:**
  - Lines draw at **true relative speed** (scaled), with linear easing and no bounce. The now line advances linearly.
  - UI transitions take 120–180 ms, linear or ease-out.
  - Reduced motion: lines appear complete, and the now line becomes a static marker with a timestamp.
- **Mobile stance:** read-first.
  - The graph **rotates 90°**, so time runs top → bottom and the stations become columns.
  - The run summary and findings read like a timetable column. Capture works on mobile. Prove is offered only when an agent is connected.

## I.8 Landing approach

- **Hero layout:**
  - Left two-thirds: the headline in Archivo wdth 75 / 700 at 72 px, the subline, the primary CTA **Get a URL, no sign-up**, and the `npx …` copy chip.
  - The full width under the type is **the brand image**.
- **Brand image: "the conflict".** A live Delivery Graph on the bare ground, with no frame.
  1. D1 draws from *sent* to *state* in ink. The state rail prints `1`, and a small green clear bar appears.
  2. A beat later, D2 draws **parallel**. The rail prints `2`.
  3. The red ■ drops onto D2's terminus, the bracket draws back to D1, and the finding sets in: **Not idempotent. Delivery 2 created new state.**
  4. The magenta now line keeps moving.
  - The whole sequence lasts about 4 s and loops once, then rests on the finished state.
- **Below:**
  - "Every way a webhook can arrive": the scenario catalogue as a **grid of small graphs** (one glyph-graph per scenario plus its provider citation).
  - Capture → scrub → commit → CI drawn as one long line passing through four stations.
  - Secrets / open source / agent sections set as ruled tables.
- **Not:** stock railway photography, trains, split-flap animations.

## I.9 Risks and who it might not suit

- **Graph literacy.** Marey charts are unfamiliar to most people. If the Delivery Graph needs a legend to be read, it fails. A03 must test whether "parallel = duplicate, crossing = reordered" is self-evident within 10 s. The fallback is a plain sequence table with the graph as secondary.
- **Too quiet for acquisition.** A near-monochrome brand can read as "another serious tool" in a crowded search result. It relies on the graph animation to be remembered.
- **Density puts off beginners.** Agent-assisted developers who glance at the UI may want a big verdict, not a graph.
- **Best for:** Integrators and QA/CI owners who like instruments. **Weaker for:** casual first-minute visitors.

---

# Territory II: Crash Lab

## II.1 Core idea

A crash-test facility doesn't ask *does the car drive?* It asks **what happens to what's inside when it is hit the way the road hits it.** Webhook Trap is that facility for handlers. A captured real event is the **specimen**, each scenario is a **calibrated impact** (double impact, offset, delayed, intrusion), and the verdict is **measured damage to state**, read off instruments against tolerances. The brand's physical vocabulary includes:

- quadrant targets;
- high-speed frame counters;
- hazard yellow;
- rig numbers;
- tolerance rulers.

These make an abstract failure (a duplicated row) feel as concrete as a crumpled bumper.

It expresses Proving Ground as **destructive testing done calmly**. The drama is in the visuals and the restraint is in the words. "Break it before Stripe does" (A01's working line) belongs here.

**What "Trap" means here:** a **speed trap** is the twin-beam timing gate that measures a vehicle at the moment of impact. Two beams, one measurement, just as two deliveries give one verdict.

## II.2 Personality and voice

**Traits:** clinical, physical, measured. It is never gleeful about breakage. The facility is serious *because* the impacts are violent.

- **Hero headline:** *Your webhook handler works. Once.*
- **Subline:** *Then Stripe delivers it again. Webhook Trap runs real events through the impacts providers actually deliver (duplicate, reordered, late, tampered) and measures what your state took.*
- **Alternate line (campaign):** *Break it before Stripe does.*
- **Finding:** `TEST 07 · TWICE` **Deformation recorded.** `subscriptions 1 → 2` after delivery 2 of 2. The handler is not idempotent. Stripe delivers each event at least once. Fix: check `event.id` before writing.
- **Empty state:** *Bay empty. Send one real event to this URL to load the first specimen.*
- **Error:** *Rig offline. The agent on `maya-mbp` stopped responding at frame 0003. Frames 0001–0002 and their readings are kept.*

Voice rules:
- Measured values come first, then the conclusion. Test numbers and frame numbers are always zero-padded (`0003`).
- Lab words ("specimen", "rig", "frame", "reading", "tolerance") are allowed in headings and helper text. Control labels stay plain ("Prove", "Run again").
- Never use blood, injury or fear language. Damage is to **state**, never to people.

## II.3 Logo

- **Mark: the off-axis quadrant target.** A circle divided into four quadrants, alternately ink and Test Yellow, with a thin ink outer ring. The quadrant axes are **rotated 15° off vertical**, the way a target looks on a specimen that has just moved. That rotation makes it ownable rather than a generic photogrammetry marker.
- **Plate (large sizes):** the target sits on a square yellow plate with four corner registration ticks.
- **Wordmark:** **WEBHOOK TRAP** in Big Shoulders at its heaviest display cut, all caps, tracking +2 %. It is set on a yellow placard in the horizontal lockup, like the rig label painted on a test sled.
- **Secondary stencil wordmark:** Big Shoulders Stencil, for stickers, merch and the CLI splash only.
- **16 px favicon:** the target alone, 14 px circle, four quadrants, no ticks, no plate. It is legible at any size because it is only four solid shapes. On dark grounds the yellow quadrants carry it, and on light grounds the ink ring defines it.
- **Do not:** add crash dummies, cars, cracked glass or explosion graphics.

## II.4 Colour system

**Logic: colour is a material, not a mood.**
- **Test Yellow + ink** is the brand: the paint of targets, placards and hazard stripes. It appears as **fill only**, never as text on a light ground.
- The ground is **concrete** in light mode (the lab floor) and **near-black** in dark mode (the test cell under lights). Dark is the default for landing and marketing. Light is equally first-class in the app.
- Severity uses a separate red/orange family, plus **pattern as a second channel**: Critical = solid fill, High = 45° hatch, Medium = yellow/ink hazard stripe, Info = outline only.

| Token | Role | Light OKLCH | Light hex | Dark OKLCH | Dark hex | Contrast vs own ground (L / D) |
| ----- | ---- | ----------- | --------- | ---------- | -------- | ------------------------------ |
| `ground` | page (concrete / test cell) | `oklch(0.94 0.004 95)` | `#ECEBE8` | `oklch(0.16 0.006 95)` | `#0E0D0A` | — |
| `surface` | readouts, frames | `oklch(0.975 0.003 95)` | `#F7F7F4` | `oklch(0.21 0.008 95)` | `#191814` | — |
| `rule` | frame borders, rulers (non-text) | `oklch(0.82 0.006 95)` | `#C5C4C0` | `oklch(0.32 0.010 95)` | `#34332D` | — |
| `ink` | text | `oklch(0.17 0.008 95)` | `#100F0C` | `oklch(0.95 0.010 95)` | `#F0EEE7` | 16.1 / 16.7 |
| `ink-muted` | labels, units | `oklch(0.43 0.008 95)` | `#51504B` | `oklch(0.72 0.010 95)` | `#A6A59E` | 6.8 / 7.9 |
| `test-yellow` | accent 1, brand fill (ink on it 13.0:1) | `oklch(0.87 0.17 95)` | `#F7D22A` | `oklch(0.87 0.17 95)` | `#F7D22A` | fill only on light · 13.2 as text on dark |
| `pass` | intact | `oklch(0.48 0.12 150)` | `#197037` | `oklch(0.75 0.16 150)` | `#55C975` | 5.2 / 9.3 |
| `sev-critical` | accent 2: solid fill | `oklch(0.50 0.20 27)` | `#BB0916` | `oklch(0.66 0.21 27)` | `#F84C45` | 5.6 / 5.7 |
| `sev-high` | 45° hatch | `oklch(0.51 0.16 40)` | `#AD3A00` | `oklch(0.73 0.17 45)` | `#FC7F41` | 5.2 / 7.6 |
| `sev-medium` | hazard stripe (fill) + text token | `oklch(0.49 0.10 80)` | `#7E5904` | = `test-yellow` | `#F7D22A` | 5.3 / 13.2 |
| `sev-info` | outline only | `oklch(0.47 0.015 95)` | `#5D5B52` | `oklch(0.72 0.015 95)` | `#A7A59A` | 5.7 / 7.9 |
| `armed` | Live (camera armed / recording) | `oklch(0.48 0.085 222)` | `#0D6880` | `oklch(0.79 0.12 215)` | `#48CEEA` | 5.3 / 10.5 |

- **Agent states:**
  - not installed = `ink-muted` outline target;
  - pairing = `armed`, pulsing at 1 Hz;
  - connected = `pass` ("rig online");
  - target unreachable = `sev-high` hatch;
  - disconnected mid-run = target with the quadrants greyed;
  - version too old = hazard stripe.
- **Contrast intent:** text on `sev-critical` uses the light `surface` (6.2:1). Ink on yellow is 13:1. `armed` cyan stays at low chroma and is used for small indicators only, never large fields, to stay clear of neon.

## II.5 Typography

| Role | Family | Licence / source | Use |
| ---- | ------ | ---------------- | --- |
| Display | **Big Shoulders** (display optical size, weights 800–900) + **Big Shoulders Stencil** (sparingly) | OFL · Google Fonts | Huge condensed caps for headlines, test numbers and readings (`1 → 2` at 112 px). Stencil only for rig labels and marketing. |
| UI | **Schibsted Grotesk** | OFL · Google Fonts | Sturdy, plain grotesk for every control, table and sentence. |
| Mono / instruments | **Azeret Mono** | OFL · Google Fonts | Payloads, frame counters (`t+0.412 s`), IDs. Its technical, slightly squared shapes read as instrument output. |

**Type logic: loud and quiet, with nothing in between.** Display type is enormous, condensed and all caps, and appears only where a **measurement or verdict** lives. Everything else is calm sans at reading size. No medium-sized headings: the jump from 16 px to 64 px is deliberate.

**Scale (px):** 12 · **14 (UI base)** · 16 · 20 · 28 · 64 · 112 (landing reading). Display line height 0.9, tracking +1 %. UI line height 1.45.

## II.6 Iconography

- **Rules:**
  - 20 px grid, **solid fills** with a 2 px outer contour;
  - sharp corners, circles and squares as primitives;
  - every icon is built from the **quadrant target** or the **impact arrow** (a heavy chevron).
- **Key icons:**
  1. **Prove:** the quadrant target.
  2. **Once:** a single chevron hitting a vertical wall bar.
  3. **Twice:** two stacked chevrons hitting the same wall.
  4. **Shuffled:** a chevron hitting at 30° (an *offset impact*).
  5. **Late:** a chevron with a clock-dial tail.
  6. **Tampered:** a chevron with a jagged break in its shaft (*intrusion*).
  7. **Unsigned:** a target missing one quadrant.
  8. **Slow budget:** a gauge with its needle past a hatched red zone.
  9. **Burst:** three chevrons converging.
  10. **Verdict:** an intact target (pass), or a target split by a crack line (finding).
  11. **Agent:** a camera on a tripod (the rig's eye on your machine).
  12. **Signature:** a target inside a square seal.

## II.7 Interface approach

- **Layout philosophy: the test bay.**
  - The run screen is a **grid of rigs**, one cell per scenario. Each cell carries a big stencil number (`07`), the scenario name and the result.
  - Cells fire in sequence and lock into their result like instruments settling.
  - Below the bay sits **the readout**: the selected rig's frames and measurements.
- **Density:** low at the overview, high inside readouts. The bay is for at-a-glance verdicts from across the room (or a CI screenshot). The readout is a full, dense instrument panel.
- **Capture stream ("specimen intake"):**
  - Each delivery is a row with a **specimen tag** (`SPC-0042`), provider, event type, size, and a quadrant glyph for the signature verdict (four quadrants = valid, one missing = unsigned, crack = invalid).
  - Related deliveries share a tag prefix (`SPC-0042-a`, `-b`).
  - **Prove** is a yellow-plate button on every row and every group: the one yellow control on screen.
- **Delivery detail:** a **specimen sheet**. A header of readings (status, latency against the tolerance ruler, size, signature), then the tabs Verdict / Raw / Decoded, with Raw in Azeret Mono on `surface`.
- **Run timeline: the filmstrip.**
  - Deliveries are **frames** laid horizontally. Each frame shows its number (`0001`), `t+` time, scenario, re-signed ✓, status, and a mini tolerance ruler for latency.
  - The user scrubs across frames, and the selected frame opens below with request and response side by side.
- **Findings: the damage report.** A table ordered by severity. Each row has a pattern swatch (solid / hatch / stripe / outline), a title, the evidence frames, why it matters (provider citation) and a fix snippet.
- **Signature components:**
  - **Duplicate delivery:** two consecutive frames carrying the **same specimen tag** and a `×2` impact marker between them.
  - **State counter 1 → 2: the deformation gauge.** A mechanical counter (odometer digits in Big Shoulders) rolls from `1` to `2` in one stepped turn. A hazard-stripe flag drops beside it, labelled **expected 1**.
  - **Tolerance ruler:** the provider budget drawn as a ruler with a hatched red zone. The latency marker either stops before it or crosses into it.
  - **Rig cells:** the bay overview, whose states are idle, armed, firing, intact and damaged.
- **Motion principles:**
  - **Stepped, not eased** (`steps()` timing), like high-speed playback. Frames advance in discrete jumps, and the odometer rolls in one hard step.
  - An impact is a single 2-frame **inversion** of the frame border. It never flashes more than 3 times per second, and there are no full-screen flashes (photosensitivity).
  - Reduced motion: no stepping, results appear in their final state.
- **Mobile stance: report first.**
  - The phone is where a CI link or a teammate's share link is opened, so the bay collapses into a vertical **test report**: verdict placard, then rig list, then the damage report.
  - The filmstrip becomes a vertical stack of frames. Capture works. Running a proof from a phone is possible but not a design priority.

## II.8 Landing approach

- **Hero layout:**
  - Dark test cell. Headline in Big Shoulders at 112 px, all caps on two lines (*YOUR WEBHOOK HANDLER WORKS. / ONCE.*), with the subline in Schibsted Grotesk.
  - CTA: a yellow plate, **Get a URL, no sign-up**, plus the `npx …` chip.
- **Brand image: "the filmstrip".** A full-bleed strip of **six high-speed frames**, each with quadrant targets in its corners, a frame counter and `t+` time.
  1. Frames 1–2: delivery 1 approaches and lands, and the odometer reads `1`.
  2. Frame 3: intact.
  3. Frames 4–5: the same specimen tag, `×2`, impact. The odometer **rolls to `2`**.
  4. Frame 6 freezes, a hazard-stripe flag drops, and the finding is stamped across the frame in stencil: **NOT IDEMPOTENT**.
  - On scroll, the strip plays at "high speed", stepping frame by frame.
- **Below:**
  - "Every way a webhook can arrive" as the **test bay**: a grid of rig cells with their impact icons and provider citations.
  - Capture → scrub → commit → CI as a horizontal "assembly line" of plates.
  - Self-host, open source and secrets sections set on concrete-light sections, so the page alternates cell and floor.
- **Not:** photos of crashed cars, dummies, or anything that implies real injury.

## II.9 Risks and who it might not suit

- **Tone mismatch.** A01 asked for *calm, a little dry, no fear-mongering*. A crash lab is dramatic by nature. The territory only works if the words stay clinical, and a copywriter who reaches for drama will break it.
- **Hazard yellow is loud in a work tool.** Over a long session it may tire the eye. A04 must hold yellow to the Prove control, the logo and Medium stripes, and keep it there.
- **Metaphor strain on non-impact scenarios.** "Shuffled" and "Slow budget" aren't natural impacts, so the icon set has to work harder.
- **Generic-marker risk.** Quadrant targets are a public engineering symbol. The 15° off-axis cut and the plate must stay consistent, or the mark becomes clip-art.
- **Best for:** acquisition, launch moments and conference stickers. **Weaker for:** QA/CI owners who want a quiet instrument, and enterprise buyers in Horizon 2 ("watch production") where crash imagery feels wrong.

---

# Territory III: Notation

## III.1 Core idea

A webhook integration is a **game played against the provider**. You make the obvious move (process every delivery as it arrives), and the provider replies with the moves its docs allow: the same event again, events in the other order, a late one, a retry after your 400. Webhook Trap records the game **as chess literature records it**:

- numbered moves;
- the **position** (your state) after each one;
- **annotations** on the moves that lose (`??` blunder, `?` mistake, `?!` dubious, `!?` interesting, `!` good, `∞` unclear);
- the **better variation** printed in brackets.

The tool doesn't shout. It annotates, like a strong player reviewing your game.

It expresses Proving Ground through **sequence plus state together**. A move list *is* a sequence, a position *is* state, and an annotation *is* a verdict with a reason and a fix. The mapping is unusually complete:

| Product concept (A01) | Notation equivalent |
| --------------------- | ------------------- |
| Related-object group | a **game** |
| Delivery | a **move** (`1.`, `2.`, `3.`) |
| Probe digest | the **position** after the move |
| Finding Critical / High / Medium / Info | `??` / `?` / `?!` / `!?` |
| Pass | `!` (or `=`, "balanced", for a state that stayed equal) |
| Inconclusive (needs probe) | `∞` (unclear position) |
| Fix suggestion | the **variation**: `(2. Twice = with an event.id guard)` |
| Slow budget exceeded | **flag fell** (chess clock) |
| Run aborted, agent lost | **adjourned** |
| Fixtures saved / CI | the **game record** that can be replayed at any time |

**What "Trap" means here:** an **opening trap** is a short sequence that punishes the natural-looking move. That is exactly what each scenario is. *Twice* is the trap that punishes "just insert the row".

## III.2 Personality and voice

**Traits:** analytical, dry-witted, generous (it always shows the better move). It writes like a good annotator: short sentences, a precise verdict, and no humiliation of the player.

- **Hero headline:** *Your webhook handler works. Once.*
- **Subline:** *Providers play the other moves: twice, out of order, late, retried. Webhook Trap replays real events as they really arrive and annotates what your code did, move by move.*
- **Alternate line (campaign):** *A natural move, and a losing one.*
- **Finding:** **2. Twice ??** `subscriptions 1 → 2`. *A natural move, and a losing one: Stripe may deliver any event more than once.* Better was **(2. Twice = )**: check `event.id` before writing. [See variation]
- **Empty state:** *No moves yet. Point a provider at this URL, and the first event opens the game.*
- **Error:** **Adjourned.** *The agent left the board at move 3. Moves 1–2 and their positions are recorded. The run resumes when `maya-mbp` reconnects.*

Voice rules:
- Annotation glyphs are **always paired with a plain-language severity** on first view ("?? Critical").
- Chess terms are allowed in helper copy and empty/error states, never on controls. "Adjourned" may be a run state *label* only if A03's tests show it is understood. Otherwise the label is "Aborted" and the helper text says *Adjourned at move 3*.
- No "checkmate", no "you lost", no gloating.

## III.3 Logo

- **Mark: the trapped square.** A square divided into a **2 × 2 board**:
  - top-left Board Green, top-right and bottom-left Buff;
  - bottom-right **Red Pencil**: the square where the trap springs;
  - a 1 u ink outline around the whole mark, so buff squares hold on a buff ground.
- **Wordmark:** "Webhook Trap" in **Newsreader** at display optical size (72), weight 600, roman, sentence case, slightly tightened (−1.5 %). It reads like the title on a chess book spine.
- **Lockup:** the mark to the left of the wordmark, its height equal to the cap height plus descender.
- **Campaign glyph (not part of the logo):** a giant red-pencil **`??`** in Newsreader italic, used on landing, stickers and social cards.
- **16 px favicon:** four 7 px squares inside a 1 px outline. Three tones remain distinct, and the red square reads as "something here".
- **Do not:** use chess pieces as the logo, knights or kings, or crowns.

## III.4 Colour system

**Logic: a tournament board and a red pencil.**
- **Buff and Board Green** are the two tones of a vinyl tournament board. Buff is the reading ground. Green carries brand blocks and the **position** panel, so the "board" is where state lives.
- **Red Pencil** is the annotator's colour: Critical, the `??` glyph, and the trapped square.
- Severity is **glyph-first** (`??` `?` `?!` `!?` `!`), so colour is the second channel, which is the reverse of most tools.

| Token | Role | Light OKLCH | Light hex | Dark OKLCH | Dark hex | Contrast vs own ground (L / D) |
| ----- | ---- | ----------- | --------- | ---------- | -------- | ------------------------------ |
| `ground` | buff page / night hall | `oklch(0.965 0.014 95)` | `#F6F4E9` | `oklch(0.20 0.015 155)` | `#111813` | — |
| `surface` | commentary column, move highlight | `oklch(0.94 0.018 95)` | `#EFEBDE` | `oklch(0.24 0.018 155)` | `#19221C` | — |
| `rule` | hairlines (non-text) | `oklch(0.84 0.020 95)` | `#CECBBC` | `oklch(0.35 0.020 155)` | `#333E36` | — |
| `ink` | text | `oklch(0.22 0.020 155)` | `#131D17` | `oklch(0.93 0.015 95)` | `#EBE8DD` | 15.7 / 14.7 |
| `ink-muted` | move numbers, meta | `oklch(0.46 0.020 155)` | `#505C53` | `oklch(0.75 0.015 95)` | `#B1AEA4` | 6.4 / 8.1 |
| `board` | accent 1: position panel, brand blocks | `oklch(0.50 0.07 152)` | `#436F4F` | `oklch(0.42 0.07 152)` | `#2D583A` | buff text on it 5.3 · ink (dark) on it 6.7 |
| `pencil` = `sev-critical` | accent 2: `??` | `oklch(0.52 0.20 27)` | `#C2181D` | `oklch(0.70 0.18 28)` | `#FA695C` | 5.5 / 6.2 |
| `sev-high` | `?` | `oklch(0.53 0.14 45)` | `#AB4B16` | `oklch(0.76 0.14 50)` | `#F7945A` | 5.1 / 8.0 |
| `sev-medium` | `?!` | `oklch(0.52 0.10 85)` | `#836412` | `oklch(0.81 0.11 88)` | `#DFBD69` | 5.0 / 10.0 |
| `sev-info` | `!?` | `oklch(0.50 0.11 300)` | `#6D5398` | `oklch(0.77 0.10 300)` | `#BEA6EB` | 5.7 / 8.5 |
| `pass` | `!` / `=` | `oklch(0.47 0.09 152)` | `#2E6941` | `oklch(0.77 0.11 150)` | `#7FC88F` | 5.9 / 9.1 |
| `clock` | Live (clock running) | `oklch(0.50 0.08 205)` | `#157079` | `oklch(0.77 0.08 205)` | `#73C3CC` | 5.3 / 8.9 |

- **Agent states:**
  - not installed = `ink-muted` empty seat;
  - pairing ("awaiting opponent") = `clock`, with a ticking hand;
  - connected ("at the board") = `pass`;
  - target unreachable ("flag fell") = `sev-high`;
  - disconnected mid-run ("adjourned") = `ink-muted` with a `½` pause glyph;
  - version too old = `sev-medium`.
- **Inconclusive** = `∞` in `sev-info`.
- **Contrast intent:** all glyphs are set at ≥ 15 px bold, so they are also checked as large text. Note that dark `board` (2.2:1 against the dark ground) is a *surface*, not a text colour, and is always bounded by a rule.

## III.5 Typography

| Role | Family | Licence / source | Use |
| ---- | ------ | ---------------- | --- |
| Display + commentary | **Newsreader** (variable: opsz 6–72, wght 200–800, with italics) | OFL · Google Fonts | Headlines at display opsz. **Commentary and finding explanations in italic text opsz**, like annotations in a chess book. Annotation glyphs `?? ? ?! !? ! = ∞` are set in Newsreader bold. |
| UI | **Atkinson Hyperlegible Next** | OFL · Google Fonts | Controls, tables, labels. Designed for legibility, which balances the bookish serif. |
| Mono | **Sometype Mono** | OFL · Google Fonts | Payloads, IDs, the move list's data columns. |

**Type logic: a book with a tool inside.** Serif carries *meaning* (headlines, verdicts, commentary). Sans carries *operation*. Mono carries *evidence*. The reading register is larger than in most dev tools, because findings are meant to be read, not scanned.

**Scale (px, ratio about 1.25 in UI and 1.333 in display):** 12 · 13 · **15 (UI base)** · **17 (commentary, italic)** · 21 · 28 · 40 · 60 (landing). Line height 1.5 for commentary and 1.35 for UI.

## III.6 Iconography

- **Rules:**
  - icons are **typographic**: drawn on Newsreader's x-height and cap-height, 1.25 px stroke, round terminals, with the slight contrast of a pen stroke;
  - they sit *in* lines of text, like symbols in a chess book;
  - severity uses the annotation glyphs themselves, not pictograms.
- **Key icons:**
  1. **Prove:** a `+` inside a circle (in notation `+` means *check*).
  2. **Once:** `1.` (a move number in a ring).
  3. **Twice:** `1. 1.` stacked (the move repeated).
  4. **Shuffled:** two move numbers with a swap arc (`2 ⇄ 3`).
  5. **Late:** a chess-clock face with the flag raised.
  6. **Tampered:** a move with a strike-through and an over-written character.
  7. **Unsigned:** a scoresheet line with no signature.
  8. **Slow budget:** a chess clock whose flag has fallen.
  9. **Burst:** three moves on one line (a simultaneous exhibition).
  10. **Verdict:** the glyph set `!` `=` `?!` `?` `??` `!?` `∞`.
  11. **Agent:** the two-faced chess clock (your machine and ours, taking turns).
  12. **Signature:** a pen nib crossing a baseline (players sign the scoresheet).

## III.7 Interface approach

- **Layout philosophy: the annotated game page.** Three reading columns, arranged like a chess book spread:
  1. **Move list** (left, narrow): numbered deliveries.
  2. **Position** (centre): the state after the selected move, on a Board Green panel.
  3. **Commentary** (right, widest): findings as annotated prose with variations.
  - Hierarchy comes from typography and rules, never from boxes.
- **Density:** medium. Data stays dense in the move list (mono, 26 px rows), while commentary is given room. Expect less on screen at once than in Timetable, and each item is better explained.
- **Capture stream ("the game collection"):**
  - Each related-object group is a **game**, titled by object (`sub_1Nx… · Stripe`), with its moves listed underneath (`1. checkout.session.completed  2. customer.subscription.created  3. invoice.paid`).
  - The signature verdict appears as a small glyph after each move: `✓`, `✗`, `–` for unsigned.
  - **Prove** reads as **Prove this game** on group headers and **Prove** on single moves.
- **Delivery detail:** the move expanded. A one-line verdict in serif, then tabs Verdict / Raw / Decoded, with Raw in Sometype Mono.
- **Run timeline: the move list.**
  - Each delivery is written as notation, for example: `2. customer.subscription.created (Twice) ?? · 201 · 84 ms`.
  - Selecting a move updates the **position** panel and scrolls the commentary to its annotation. Keyboard ← → steps through moves, like a chess viewer.
- **Findings: commentary.** Each finding is a paragraph:
  - the move reference + glyph + plain severity (`2. ?? Critical`);
  - an italic explanation with the provider citation;
  - the **variation** in brackets, which opens the framework-aware fix snippet.
- **Signature components:**
  - **Duplicate delivery:** the move appears twice with the same move text, the second carrying `??`, for example `2. … subscription.created` and `2′. … subscription.created ??`.
  - **State counter 1 → 2: the position diagram.** The Board Green panel shows the probe digest as a tiny board of **counters** (`subscriptions ● → ● ●`). The second counter is placed in Red Pencil, and the annotation `??` appears beside it.
  - **Chess-clock bar:** latency against the provider allowance as a clock bar. When the allowance runs out, the flag falls (Slow budget, Medium `?!`).
  - **Variations:** the fix suggestion as an indented bracketed line that can be expanded.
  - **`∞` banner:** "Responses identical. Unclear position: add a probe to see your state."
- **Motion principles:**
  - Considered and unhurried: 200–260 ms ease-in-out. Moving between moves **slides** position counters to their new places, like a piece moving.
  - An annotation glyph **inks in** once (a stroke-draw of about 300 ms) when a finding first appears, and never again.
  - Reduced motion: no slides or ink-in, just instant states.
- **Mobile stance: the pocket chess book.** This is the best of the three on a phone. A move list is naturally vertical: the position panel pins to the top, and commentary follows each move inline. PR findings and share links read beautifully on mobile. Capture works. Proving from mobile is possible but secondary.

## III.8 Landing approach

- **Hero layout:**
  - On buff, a book-like layout. The headline in Newsreader display at 60 px, left-aligned across two columns. The subline in italic commentary style.
  - CTA **Get a URL, no sign-up** as a Board Green block, with the `npx …` chip next to it.
- **Brand image: "the annotated game".** A single spread, typeset like a page from a chess annotation book:
  - **Left:** the move list writes itself:
    - `1. customer.subscription.created` (position `subscriptions 1`) `!`
    - `2. customer.subscription.created (Twice)` (position `subscriptions 2`) and a giant red-pencil **`??`** inks into the margin.
  - **Right:** the position diagram shows one counter, then a second counter is placed in red.
  - **Below,** in italic commentary: *A natural move, and a losing one. Stripe delivers each event at least once. Better was (2. … = with an event.id guard).*
  - This is the single image to put on every card: **the `??` next to `1 → 2`.**
- **Below:**
  - "Every way a webhook can arrive" as a **repertoire**: each scenario named like an opening ("Twice", "Shuffled", "Late"…), with the trap it springs and its provider citation.
  - Capture → scrub → commit → CI as "record the game, replay it in every PR".
  - The agent/MCP section shows the same game in JSON: the notation *is* machine-readable.
- **Not:** 3D chess pieces, a rendered chessboard hero, chess-champion imagery, or "4D chess" jokes.

## III.9 Risks and who it might not suit

- **Gimmick risk.** If the metaphor leaks into control labels, or every string tries to be clever, it turns cute and annoying. Discipline: *plain labels, notation in glyphs and commentary only.*
- **Chess literacy.** `??` and `!` are widely understood, but `?!`, `!?` and `∞` are not. Glyphs must always carry their plain severity, at least until users dismiss the hint.
- **Serif in a dev tool.** Some developers read serif type as "marketing" or "slow". The UI sans and mono must dominate the working screens, and the serif must earn its place in verdicts.
- **Less dense than an instrument.** QA/CI owners scanning 200 deliveries may prefer Timetable's graph.
- **Best for:** Integrators, Agent-assisted developers (structured, readable verdicts), anyone reading a PR finding on a phone, and social and word of mouth (the `??` is shareable). **Weaker for:** high-volume runs, and Horizon 2 monitoring dashboards.

---

## Comparison

| | **I · Timetable** | **II · Crash Lab** | **III · Notation** |
| - | ----------------- | ------------------ | ------------------ |
| **Idea / stance** | The provider's real delivery schedule, plotted. The product is a dispatcher's graph. | Destructive testing. The product is a test facility, the handler a specimen. | A game record. The product is an annotator reviewing your moves. |
| **"Trap" means** | Trap points: catch the runaway before the main line | Speed trap: two beams, one measurement | Opening trap: punishes the natural move |
| **How time and sequence read** | Continuous time axis, lines with slope (Marey graph) | Discrete numbered frames (filmstrip) | Numbered moves (notation list) |
| **How state 1 → 2 shows** | Values on a state rail, with a red ■ conflict marker | Odometer rolls in one step, hazard flag "expected 1" | Position diagram: a second counter placed in red, plus `??` |
| **Density** | High (instrument) | Low overview, high readouts | Medium (reading) |
| **Colour logic** | Monochrome ink. Colour only for conflict, now (magenta) and allowance | Colour as material (Test Yellow + ink). Severity as colour + pattern | Two-tone board (buff/green) + red pencil. Severity glyph-first |
| **Type** | One grotesk across widths (Archivo) + Martian Mono | Huge condensed caps (Big Shoulders) + Schibsted Grotesk + Azeret Mono | Book serif with italics (Newsreader) + Atkinson Hyperlegible Next + Sometype Mono |
| **Motion** | Linear, time-true, lines draw | Stepped, frame-by-frame, single-frame impacts | Unhurried slides, one-time ink-in |
| **Hero image** | Two parallel lines, `1` then `2` on the rail, red conflict square | Six-frame filmstrip, odometer `1 → 2`, stencil NOT IDEMPOTENT | Annotated move list, giant red `??` beside `1 → 2` |
| **Mobile** | Graph rotates (time runs down), read-first | Collapses to a test report | Pocket chess book (strongest) |
| **Main risk** | Graph literacy, too quiet to be remembered | Tone drifts dramatic, yellow fatigue | Gimmick creep, serif doubts, chess literacy |
| **Best persona fit** | Integrator, QA/CI owner, later the Owner (H2) | Acquisition, launch, Integrator | Integrator, Agent-assisted developer, PR readers |

## Recommendation (PROPOSED: A00 and the PO decide)

**Push Timetable hardest. Develop Notation fully as the real challenger. Use Crash Lab as the bold foil.**

- **Timetable** is the territory whose *central image is the product's core belief*. Webhooks fail in sequences, and the Delivery Graph shows the sequence, the allowance and the state divergence in one picture. It scales from the first-minute capture stream to dense CI runs. It carries into Horizon 2 (the same graph over production time) without a rebrand. The trap-points story answers Q12 cleanly. Its risks (graph literacy, quietness) are testable early: A03 should run a 10-second comprehension test on the Delivery Graph before A04 invests in the full system.
- **Notation** has the strongest **voice and shareable image** (`??` next to `1 → 2`), the most complete mapping onto A01's states (`∞` for inconclusive, adjourned for aborted, flag fell for budget), and the best mobile and PR-reading story. If the comprehension test on the graph fails, or the PO wants more personality, Notation should win.
- **Crash Lab** is worth building to calibrate how loud the brand can be. Its landing would be the most memorable of the three. As a daily work tool, though, it fights A01's "calm, a little dry" tone and the Horizon 2 enterprise direction. Pieces of it may survive as campaign material whichever territory wins.

A04 should build each territory as its own complete system and not blend them before the decision. Blending would erase the differences the PO needs to choose between.
