# Accessibility spec · Webhook Trap 2.0

**Status:** REVIEW_REQUIRED · **Author:** A03 (UX/UI) · **Date:** 2026-11-14, revised for decision 35 and A01 `scenarios-and-judge.md` (SJ), then for decisions 38–39, then for decision 44 (account security, rev. 3), then for A05 revision 5 (rev. 4) · **Phase:** 2
**Resolves:** A-26 (target statement) and A-27 (keyboard scope conflicts) from A11's `testability-review.md` (TR).
**Builds on:** UF §7 (rules), UF per-screen accessibility notes, TT §3.4 (ARIA patterns already built and verified by A11 in `qa-timetable-v2.md`).
**Read with:** `information-architecture.md` (IA), `navigation.md` (NAV), `golden-files.md` (GF), `user-flows.md` (FL), `screen-specifications.md` (S-nn). Data: `realistic-data.md` (RD), regenerated for decision 35 (Run #3 = 16 deliveries).

---

## 1. Target statement (resolves A-26)

> **Conformance target: WCAG 2.2 Level AA**, for every web page of Webhook Trap 2.0: the landing page, the app in all access modes (anonymous, guest, signed in, self-host admin), shared and example runs, sign-in, and error pages, in light and dark themes, at every supported width.
>
> The rules in UF §7 and in this file are **product rules on top of AA**. Where a product rule is stricter than AA, the product rule applies:
> - touch targets ≥ 44 × 44 px (WCAG 2.5.5, AAA), desktop ≥ 24 × 24 px (2.5.8, AA);
> - a 2 px focus ring with 2 px offset at ≥ 3:1 (close to 2.4.13, AAA);
> - severity, signature state, pass/fail, over-budget and state change never carried by colour alone, also in dense rows (stricter than 1.4.1);
> - live announcements rate-limited (§6);
> - every visual timeline has a synced, interactive table equivalent on the same screen.
>
> **Non-web outputs** (CLI, CI log, PR comment Markdown, JSON) are not web content. They follow the product rules in §8 and the golden-file rules in `golden-files.md`, informed by WCAG2ICT where a criterion applies to non-web text (1.4.1 use of colour, 1.3.1 info and relationships).

**Supported browsers and assistive technology** (the conformance claim holds on these):

| Platform | Browser | Screen reader | Role |
| -------- | ------- | ------------- | ---- |
| Windows 11 | Firefox, Chrome (current) | NVDA (current) | primary desktop |
| macOS 15 | Safari (current) | VoiceOver | primary desktop |
| iOS 18 | Safari | VoiceOver | share view, mobile report, capture |
| Android 15 | Chrome | TalkBack | share view, mobile report |
| any | Chrome | none, keyboard only; Windows forced colours (High Contrast) | keyboard, forced colours |

**Defect severity for A11** (so a failure maps to a release decision):

| Failure | Severity | Blocks |
| ------- | -------- | ------ |
| AA failure on a P1 task (get a URL, read a verdict, prove, read findings, open evidence) | Blocker | Gate 3 and release |
| Other AA failure | Major | release |
| Product-rule failure (stricter than AA) | Major | Gate 3 unless the PO waives it in writing |
| Best-practice gap | Minor | — |

---

## 2. Global requirements (all screens)

| # | Requirement | WCAG | Check |
| - | ----------- | ---- | ----- |
| G1 | One `<main>`, one `h1` per page. Landmarks: `header` (top bar), `nav[aria-label="Main"]` (section tabs / bottom nav), `nav[aria-label="Breadcrumb"]`, `main`, `aside[aria-label="Findings"]` on Run, `footer` on landing | 1.3.1, 2.4.1 | axe + manual landmark list |
| G2 | Skip link "Skip to content" as first focusable element, visible on focus | 2.4.1 | keyboard |
| G3 | Page title pattern of NAV §4.3; route changes update `document.title` and move focus to the new `h1` (`tabindex="-1"`) | 2.4.2, 2.4.3 | script |
| G4 | Text contrast ≥ 4.5:1, large text ≥ 3:1, meaningful non-text ≥ 3:1, both themes (A11 already verified tokens: `qa-timetable-v2.md` §6) | 1.4.3, 1.4.11 | token script |
| G5 | Focus visible, 2 px ring + 2 px offset, ≥ 3:1 against element and ground; never removed | 2.4.7, 2.4.11 | manual + screenshot |
| G6 | **Focus not obscured:** sticky top bar, sticky action bars, bottom nav and toasts never cover the focused element; `scroll-padding` set to their heights; toasts sit outside the focus path and never over the focused element | 2.4.11 | script: focus each element, check its box against sticky boxes |
| G7 | Targets ≥ 24 × 24 px desktop (or 24 px spacing), ≥ 44 × 44 px touch | 2.5.8 (+2.5.5 rule) | script |
| G8 | Reflow at 320 CSS px and 200 % zoom with no page-level horizontal scroll. Exceptions allowed by 1.4.10 only: Raw body, code blocks, the delivery graph and the deliveries grid scroll **inside their own region** | 1.4.10, 1.4.4 | script + manual |
| G9 | Text spacing override (1.5 line height, 0.12 em letter, 0.16 em word, 2 em paragraph) loses no content | 1.4.12 | bookmarklet |
| G10 | Popovers (agent pill, budget source, ids on hover) are dismissable (Esc), hoverable and persistent; also open on focus/click, never hover only | 1.4.13 | manual |
| G11 | **Dragging:** the resizable list column and inspector split have a keyboard and single-pointer alternative (splitter with arrow keys; "Wider / Narrower" in the view menu) | 2.5.7 | manual |
| G12 | **Consistent help:** `?` help and the Docs link are in the same place on every app screen | 3.2.6 | manual |
| G13 | **Redundant entry:** the run draft keeps target and scenario choices on "Edit scenarios" and "Run again"; the pairing code is shown, never retyped in the browser | 3.3.7 | manual |
| G14 | **Accessible authentication (rev. 3):** every sign-in has a path with no cognitive test: GitHub, Google, passkey, or a password a password manager can fill (`autocomplete="username webauthn"`, `current-password`, `new-password`); paste allowed in every field, including one-time and recovery codes; the authenticator code is **one** field (`autocomplete="one-time-code"`), never split boxes; recovery codes can be downloaded, copied or printed, never only displayed; no CAPTCHA without an alternative. Email link dropped | 3.3.8, 3.3.7 | AX-23 |
| G15 | Time limits: the guest session (24 h) is exempt (> 20 h). The **pairing code** (≈ 10 min, A-12) warns at 1:00 left and offers **Get a new code** at any time, with no data loss | 2.2.1 | fake clock |
| G16 | **Auto-updating content** (capture stream, running timeline) can be paused: Pause on Capture (`.`), **Freeze view** on a running Run (rows keep arriving in a buffer, counted in a "N new" pill). The run itself is not paused | 2.2.2 | manual |
| G17 | Status messages (copied, toasts, live counts) use the shell announcer (§6), never move focus | 4.1.3 | live-region recorder |
| G18 | `lang="en"` on `html`; code, ids and payloads are not given another `lang` | 3.1.1 | axe |
| G19 | Forced colours: glyphs use `currentColor`; the budget band's meaning is carried by its edge rule and label, which survive; selection uses `Highlight` | 1.4.11 (practice) | Chrome forced-colors emulation |
| G20 | Reduced motion as §7 | 2.3.3 (practice), 2.2.2 | emulation |

---

## 3. Per-component requirements

| Component | Role / pattern | Name and state | Keyboard | Notes |
| --------- | -------------- | -------------- | -------- | ----- |
| **Top bar** | `header` with `nav[aria-label="Main"]` holding section links | current section `aria-current="page"` | Tab | Section tabs are **links**, not ARIA tabs (they change the URL) |
| **Project switcher** | menu button + `menu` | "Project: Brindle Coffee" | Enter/Space opens, ↑↓, Esc | — |
| **Guest meter** | plain text in a `p`, not `progressbar` | full sentence: "Guest session. Expires in 23 hours 49 minutes. 13 of 100 deliveries. 1 of 10 runs." | not focusable; "Sign in to keep" is a link | Warning style (1 h left, quota) adds a word ("Ends soon", "Limit reached"), not colour only |
| **Capture URL chip** | text + copy `button` | "Copy capture URL" | Enter | Full URL in the accessible name even when middle-truncated |
| **Agent pill** | `button` + non-modal popover (`aria-expanded`, `aria-controls`) | "Agent maya-mbp, connected" / "No agent" / "maya-mbp, target unreachable" / "Agent maya-mbp, confirm in your terminal" | Enter opens, Esc closes, focus returns | State word always visible; agent glyphs are not severity shapes (TT §3.5 T6) |
| **Command palette** | modal `dialog` + `combobox` + `listbox` | "Command palette" | type, ↑↓, Enter, Esc | Focus trapped while open, returns to invoker |
| **Shortcut sheet** | modal `dialog` | "Keyboard shortcuts" | Esc | Contains the **Single-key shortcuts** switch (`role="switch"`) |
| **Toast** | content mirrored to the polite announcer; toast itself `role="status"` is **not** used (avoid double reading) | — | its action button is reachable by Tab only while visible; error toasts persist until dismissed | Never the only place a message lives |
| **Ruled banner** (stream lost, quota, offline, errors) | `role="status"` on appear (polite) except errors that block the task (`role="alert"` once) | — | its action is a normal button | — |
| **Capture list** | `treegrid` (TT §3.4, verified) | rows: time, provider, event, size, signature in words; group rows `aria-expanded` and their size ("Order #1042, Shopify, 2 deliveries") | §5.3 | Virtualised lists keep `aria-rowcount` / `aria-rowindex` |
| **Signature chip** | text + glyph (`aria-hidden`) | the label is the word ("Signature invalid") | not focusable | Reason is the first focusable text after it on detail (UF) |
| **Detail tabs** | WAI-ARIA tabs (TT §3.4, verified) | — | ←→ between tabs (automatic activation), Tab into panel | `1` `2` `3` screen keys too |
| **Raw body / code blocks** | `region` with `aria-label` ("Raw body, 2 294 bytes"), `tabindex="0"` | — | arrow keys scroll inside, Tab leaves (no trap) | Header list is a `table` (#, name, value) |
| **Copy controls** | `button` "Copy {thing}" | after copy, label stays; announcer says "Copied" | Enter/Space | — |
| **Scenario strip** | `toolbar` of `button[aria-pressed]` (TT §1.1, verified); 8 or 9 cells, only the run's scenarios | names as S-11 strip table (TT §1.1 pattern, RD Run #3 values) | ←→ Home End; Enter/Space selects | §5.4. An `n/a` or Inconclusive cell keeps its reason in the name ("Late: not applicable, GitHub signs no timestamp") |
| **Delivery graph** | `figure` + `svg role="img"` with `title` + `desc`; lines `aria-hidden` | `desc` = generated summary (§4) | **not focusable**; pointer only; the deliveries grid is its keyboard equivalent | Selection syncs both ways |
| **Deliveries grid** | `grid` (row navigation) with column headers | each row's name: "D5, Twice, invoice.paid, duplicate, 200 in 196 milliseconds, orders count 1 to 2, expected 1, finding F2 High". Burst rows: "D13, Burst, invoice.paid, 200 in 601 milliseconds, state read once after the burst". Rows with no result say it: "D3, Once, sent, no result" / "D4, Twice, not run" | §5.4 | Scenario names are row headers (`rowheader`) for their first row, or separate group rows |
| **Findings list** | list of disclosure buttons (`button[aria-expanded]`), roving focus | "F2, High, Duplicate delivery created a second order, evidence D4 D5 D10 D11". Info findings say "Info, not counted" | ↑↓ within, Enter/Space toggles | One open at a time; order stays by severity |
| **Finding commentary** | `section` with `h3` title, beat labels as `h4` | — | links D-refs | Fix code is a code `region` |
| **State reading (panel)** | text; numerals in the accessible text | TT §1.2 accessible name | — | "changed" spelled out |
| **Probe code** (`#a1f3c0`, SS §0.5) | text in a `span` with `tabindex="0"` when it has the tooltip; tooltip per WAI-ARIA tooltip pattern (`aria-describedby`), shown on hover **and** focus, dismissable with Esc, hoverable (1.4.13) | accessible name "code a1f3c0"; description = the PV-14 tooltip "Code for a value that stays on your machine. Same code = same value within this run." | Tab reaches the first code of each reading only; others in the same reading are plain text | Never colour-coded; a 12-character code wraps rather than truncates (1.4.10). `#invalid` reads "code invalid" with its own description. Shown values (`show: value`) read as plain text, with the tag "shown" read after the value |
| **Provenance line** | plain text after the From line, not a heading or live region | "Run by maya-mbp from the web app"; info tooltip as above | its tooltip trigger is a `button` "About this result" (**NEW**) | Present on owner, shared and example runs |
| **Budget band label** | `button` opening the budget popover | "10 second budget, Webhook Trap default" (BUD-1 label read in words) | Enter | — |
| **Inspector** | `region[aria-label="Inspector, D5"]`; Request/Response columns as `section`s; mobile as WAI-ARIA tabs | — | `d` toggles Compare (switch) | Compare is `role="switch"` "Compare with D4" |
| **Run setup form** | `form` with fieldsets per step (Agent, Target, Events, Scenarios, Verdict level) | each step's status in its legend ("Agent: connected, maya-mbp") | Tab; checkboxes Space | Disabled reasons are text next to the control (not `title`). Once is a checked, **disabled** checkbox with the visible reason "Always runs: it sets the baseline" (PLAN-2). n/a scenarios are disabled with their reason |
| **Pairing code** | large selectable text + copy button | "One-time code 7 K Q 2 M, X R 9 T D" via `aria-label` with spaced characters and a pause at the hyphen | — | Countdown: §6. The "Confirm in your terminal" state is text, announced once on `agent.status` |
| **Verdict line** | `h2`-level status text; becomes focus target on run start | "3 findings: 1 Critical, 1 High, 1 Medium. Also 1 Info. 8 scenarios, 16 deliveries, 43.2 seconds. Exit code 1." | `tabindex="-1"` | Couldn't start and Aborted name their reason ("Couldn't start the run: your handler rejected a correctly signed delivery, HTTP 401") |
| **Run switcher** | menu button + `menu` | "Run #3, 3 findings" | as menu | History rows carry status words |
| **Bottom nav (mobile)** | `nav[aria-label="Main"]` with links, `aria-current` (TT §3.4) | — | — | Never tabs |
| **Landing demo** | `figure` with a visible text alternative paragraph (UF Screen 1) | — | Pause/Play button if animated > 5 s or looping | Final frame under reduced motion |
| **Splitter** | `separator[aria-orientation][aria-valuenow]`, focusable | "Resize list" | ←→ by 40 px, Home/End | 2.5.7 alternative |

---

## 4. Screen-reader pattern for the Timetable delivery graph

The graph is a picture of the run. The **deliveries grid is the accessible source of truth** (UF §0.5, TT §3.4). The pattern:

1. **Figure, not widget.** `<figure>` containing `<svg role="img" aria-labelledby="g-title g-desc">` and a `<figcaption>` with the legend line. No focusable element inside the SVG. Lines, terminals, brackets and captions are `aria-hidden`.
2. **Summary in `desc`, generated from the verdict, ≤ 3 sentences**, in this order: scope, flagged state readings, budget crossings, reordering. Run #3:
   > "Run #3: 16 deliveries in 8 scenarios over 43.2 seconds. Flagged state readings: D5 and D11, orders count 1 to 2, expected 1, finding F2 High; D12, orders count 0 to 1, expected 0, finding F1 Critical. Over the 10 second budget: D10 and D16."

   Running: "Run #3 is running: 11 of 16 deliveries sent." Updated **only at scenario boundaries**, never per delivery (avoids re-reads). Draft: "Planned: 8 scenarios, 16 deliveries."
3. **Link to the table.** Directly after the figure: a visible link "Deliveries table" that moves focus to the grid (also `t`).
4. **Selection sync.** Selecting a grid row highlights its graph line and vice versa (pointer). Selection is announced by the grid row's focus, not by the graph.
5. **Scenario strip** is the graph's column header row in toolbar form; selecting a cell scrolls the grid to the scenario's rows and announces "Twice: 2 deliveries, D4 and D5".
8. **Burst** lines start within 3 ms of each other and are drawn as one released bundle; the `desc` names the spread only when it decides the result ("Burst: deliveries left 34 milliseconds apart, more than 20, so not judged").
6. **Mobile** has no graph by default (table-first, TT1 A4). The optional "Graph" toggle shows the figure with the same `desc`.
7. **Landing hero graph**: same pattern; its text alternative is UF's paragraph, visible below the figure.

---

## 5. Keyboard: scope rules and maps (resolves A-27)

### 5.1 Scope rules

Every key belongs to exactly one layer. A key is handled by the **innermost** layer that defines it; layers never share a key with a different meaning on the same screen.

| Layer | Keys it owns | Active when |
| ----- | ------------ | ----------- |
| **L0 Text entry** | All character keys type text. Only `Esc`, `⌘/Ctrl` chords and `Enter`/`Tab` have meaning | Focus is in an `input`, `textarea`, `[contenteditable]`, or the command palette field |
| **L1 Widget** | **Arrow keys, Home, End, PageUp, PageDown, Enter, Space** | Focus is inside a composite widget (treegrid, grid, toolbar, tablist, findings list, listbox, menu, splitter, code region) |
| **L2 Screen** | **Unmodified letters, digits and punctuation** (`j k p n v . / y w [ ] 1 2 3 t f d s e`) | Focus is anywhere on the screen except L0 |
| **L3 Global** | `⌘K`, `⌘/`, `⌘↵`, `Esc`, `?`, `g`-sequences, `c` | Always, except character keys in L0 |

**Rules.**

1. **Arrow keys never act at screen scope.** They move only inside the focused widget. (Removes the Run screen's screen-level `→`/`←`, which collided with the strip toolbar and the capture treegrid.)
2. **`j` / `k` are the screen's "next / previous item" keys**, defined once per screen as moving the selection in that screen's primary collection, from anywhere in L2. If focus is in that collection, focus moves with the selection; otherwise focus stays where it is and the change is announced (polite, once).
3. **Letters never act in L0.** In a code region (L1) letters still work at L2, since a code region takes no text input.
4. **No single key starts, stops, writes or deletes.** Starting a run is `⌘↵` (draft and finished run). Abort is `Shift X` and opens a confirm dialog. Delete has no shortcut. `s` (Save as fixtures) and `p` (Prove) only **open** a preview or draft; the write or run needs a button press in that view.
5. **Single-key shortcuts switch** (in the shortcut sheet, on by default, remembered per user; WCAG 2.1.4): when off, every L2 key and the unmodified L3 keys (`?`, `g`, `c`) are inert. Chords (`⌘K`, `⌘/`, `⌘↵`, `Shift X`) and L1 keys keep working. Everything stays reachable through buttons and the palette.
6. **One meaning per key across screens where possible.** Exceptions are listed in the maps and the shortcut sheet shows the current screen's meaning.
7. **Modals own the keyboard.** While a dialog is open (palette, sheet, confirm, fixtures preview), only its keys and `Esc` work.

### 5.2 Global map

As NAV §6: `⌘K` palette · `⌘/` and `?` sheet · `g c` / `g r` / `g f` / `g a` / `g s` go to Capture, Runs, Fixtures, Agent, Settings · `c` copy capture URL · `Esc` close innermost, then clear selection · `⌘↵` start a run (Run / Run again).

### 5.3 Capture stream and delivery detail

| Key | Layer | Action |
| --- | ----- | ------ |
| `↑` `↓` | L1 treegrid | previous / next visible row (groups and captures) |
| `→` | L1 treegrid | on a collapsed group: expand; on an expanded group: first child |
| `←` | L1 treegrid | on an expanded group: collapse; on a capture row: go to its group row |
| `Home` / `End` | L1 treegrid | first / last row |
| `Enter` | L1 treegrid | desktop: move focus into the detail pane (first tab); mobile: open the detail page |
| `←` `→` | L1 tablist | Verdict · Raw · Decoded |
| `j` / `k` | L2 | next / previous row (primary collection = the list) |
| `p` | L2 | Prove the selected row: open the draft (a group row proves the group) |
| `Shift P` | L2 | open the draft for the selected row's group |
| `1` `2` `3` | L2 | detail tab Verdict / Raw / Decoded |
| `[` / `]` | L2 | previous / next capture **in the same group** |
| `/` | L2 | focus the filter (enters L0) |
| `n` | L2 | jump to newest (clears the "N new" pill) |
| `.` | L2 | pause / resume live |
| `v` | L2 | grouped / flat |
| `y` | L2 | copy the selected capture's raw body |
| `w` | L2 | toggle wrap in Raw |
| `Esc` | L3 | from detail pane: back to the list row; then clear selection |

### 5.4 Run (draft, running, finished, shared)

| Key | Layer | Action |
| --- | ----- | ------ |
| `←` `→` `Home` `End` | L1 scenario strip | move between cells |
| `Enter` / `Space` | L1 scenario strip | select the scenario (graph highlight, grid scrolls, inspector opens on its first finding delivery) |
| `↑` `↓` `Home` `End` `PageUp` `PageDown` | L1 deliveries grid | previous / next delivery row |
| `Enter` | L1 deliveries grid | open the inspector on the focused delivery |
| `↑` `↓` | L1 findings list | previous / next finding |
| `Enter` / `Space` | L1 findings list | open / close the finding |
| `←` `→` | L1 lower-zone tablist | Deliveries · Inspector |
| `j` / `k` | L2 | next / previous **delivery** in D order (primary collection = deliveries). **Revises UF Screen 4:** `→`/`←` removed from screen scope |
| `[` / `]` | L2 | previous / next **finding** (opens it, selects its first evidence) |
| `t` | L2 | focus the deliveries grid ("timeline") |
| `f` | L2 | focus the findings list |
| `d` | L2 | inspector: toggle "Compare with previous delivery of the same event" |
| `.` | L2 | running: freeze / unfreeze the view (G16) |
| `n` | L2 | running: follow live again (jump to the newest delivery) |
| `s` | L2 | open the Save as fixtures preview (writing needs its button) |
| `e` | L2 | copy the run as JSON |
| `⌘↵` | L3 | draft: Run · finished: Run again (confirm only when the side-effects warning applies). **Revises UF Screen 4:** `r` removed |
| `Shift X` | L3 chord | running: Abort, opens confirm dialog |
| `Esc` | L3 | close inspector → back to Deliveries tab → clear selection |

Shared and example runs: `⌘↵`, `Shift X`, `s` are inert; everything else works.

**Exception noted (rule 6):** `[` / `]` mean "previous/next in group" on Capture and "previous/next finding" on Run. Both are "previous/next related item"; the sheet shows the screen's meaning.

### 5.5 Other screens

| Screen | Keys |
| ------ | ---- |
| Runs list | L1 grid ↑↓ Enter (open run); L2 `j`/`k` rows, `/` filter |
| Fixtures | L1 grid ↑↓ Enter; L2 `j`/`k` |
| Agent | no L2 keys beyond global |
| Settings, Account, Admin | no L2 keys (forms); global only |
| Sign in, register, two-step, confirm-auth, recovery (bare shell) | none, not even global `?`, `g`, `c`: every key goes to the form. Enter submits the focused form |
| Landing | no shortcuts except browser defaults |

### 5.6 Keyboard-only path (must work, no traps)

Landing → **Get a URL** → Capture (waiting) → (captures arrive) → list → `p` → Run draft → pairing code copy → (agent connects) → `⌘↵` → (side-effects confirm, first run only: Enter on **Run anyway**) → running → verdict line → `[` to F1 → `]` to F2 → evidence link D5 → inspector → `d` compare with D4 → `Esc` → `s` preview → Write → toast. (TR test 82; FL-1 to FL-4.)

---

## 6. Live updates: regions and rate limits

### 6.1 One announcer per page

The shell owns two visually hidden regions; components never create their own `aria-live`:

- `#announce-polite` (`role="status"`, `aria-live="polite"`, `aria-atomic="true"`)
- `#announce-alert` (`role="alert"`) — reserved

Components post messages to the announcer with a **channel**. The announcer queues, coalesces and rate-limits per channel, and drops messages that are stale by the time they would be read.

### 6.2 Channels

| Channel | Example message | Rate | Coalescing | Region |
| ------- | --------------- | ---- | ---------- | ------ |
| `capture.arrivals` | "3 new deliveries: Stripe invoice.paid and 2 more." | ≤ 1 per 5 s (UF) | counts merged into one sentence | polite |
| `capture.stream` | "Live updates interrupted. Reconnecting." / "Live updates resumed." | on change, ≤ 1 per 10 s | only the latest state | polite |
| `run.progress` | "Twice: finding, High. Duplicate delivery created a second order." (no F-number while running, FND-6) | per scenario result, ≤ 1 per 3 s | results queued in a burst are merged: "Late: pass. Retry after error: findings High and Medium." | polite |
| `run.final` | "Run #3 finished: 3 findings, 1 Critical, 1 High, 1 Medium. Also 1 Info." / "Run #5 couldn't start: your handler rejected a correctly signed delivery." | once | **supersedes** any queued `run.progress` | polite |
| `run.aborted` | "Run aborted: agent maya-mbp disconnected during D3." | once | — | **alert** (the only assertive message, UF §7.6) |
| `agent.status` | "Agent maya-mbp connected." / "Agent maya-mbp is offline." / "Confirm the agent in your terminal." / "Pairing cancelled in the terminal." | on change, ≤ 1 per 5 s | latest only | polite |
| `pairing.countdown` | "Pairing code expires in 1 minute." / "Pairing code expired." | at 1:00 and 0:00 only | — | polite |
| `ui.confirm` | "Copied." / "Fixtures written: 3 files and webhooktrap.yml." / "Share link copied. Anyone with the link can see this run." / "New link ready. The old link now shows Not found." | immediate | latest only | polite |
| `guest.meter` | "Guest session ends in 1 hour." / "Guest limit reached: 100 of 100 deliveries." / "10 of 10 guest runs used." / "Agent replaced: a new agent paired with this session." | once per threshold | — | polite |

No per-delivery announcements during a run. No announcement for the delivery the user selects (focus already reads it).

### 6.3 Behaviour rules

1. **Hidden tab:** messages are held, then one summary on return ("6 deliveries arrived while this tab was hidden."), matching the `Catching up` state.
2. **User setting** "Announce live updates": **All** (default) · **Results only** (`run.final`, `run.aborted`, `agent.status`, `ui.confirm`) · **Off** (only `run.aborted`). In the shortcut sheet and Account.
3. **Focus is never moved by live data.** Exceptions, all user-initiated: after pressing Run, focus moves to the verdict line ("Running"); after Abort confirms, focus moves to the verdict line ("Aborted"); when an expired screen replaces the page, focus moves to its `h1`.
4. **Page title** changes at most once per scenario (NAV §4.3); titles are not a live region.
5. **Visual counterparts exist for every message** (pill, banner, verdict line, toast), so nothing is screen-reader-only.

---

## 7. Reduced motion

Under `prefers-reduced-motion: reduce` (and a per-user override in Account: "Reduce motion: system / on / off"):

| Element | Normal | Reduced |
| ------- | ------ | ------- |
| New capture row | slides in, 120 ms linear | appears; no movement |
| Now line (running) | advances linearly | jumps per delivery event; static mark with text "now" |
| Live indicator | one blink on arrival | none; text label only |
| Delivery lines | draw from *sent* at true speed | appear complete when the response arrives |
| State reading caption | sets with the terminal at 2.6 s (landing) | final frame immediately |
| Landing hero | 4 s motion then rest; Pause/Play | final frame; Play button replays on request |
| Skeletons | no shimmer in either mode | — |
| Scroll-to (strip select, `n`) | smooth scroll | instant |

Nothing flashes more than 3 times per second in any mode (2.3.1).

---

## 8. Colour-independent meaning

### 8.1 Channels per meaning

| Meaning | Shape / glyph | Word | Position or weight | Colour (extra only) |
| ------- | ------------- | ---- | ------------------ | ------------------- |
| Critical | ■ filled square | "Critical" | first in order | `conflict` red |
| High | ▲ filled triangle | "High" | — | `sev-high` |
| Medium | △ open triangle | "Medium" | — | `sev-medium` |
| Info | ○ open circle | "Info" | — | `sev-info` |
| Pass | ▬ clear bar | "pass" / "Pass" | — | `pass` |
| Running / now | ┃ vertical | "Running", "now" | full-height labelled line | `now` |
| Stopped / not run | ⫽ | "Stopped", "Not run" | ghosted line | `ink-muted` |
| Over budget | △ at the band edge | "over +1 840" | line leaves the band | `budget-edge` |
| State changed (finding) | severity terminal + bracket | "expected 1 · got 2" | bold numeral | severity colour |
| Signature valid / invalid / no secret / unsigned / not checked | distinct station glyphs (TT1 §5.3) | label word | — | — |
| Agent target unreachable / offline / update | `tt-g-blocked` / `tt-g-offline` / `tt-g-update` (not severity shapes) | word | — | ink |
| Inconclusive (scenario or run) | — (em dash) | "Inconclusive" + reason | — | `ink-muted` |
| Unreachable (delivery or scenario) | `tt-g-blocked` | "Unreachable" | — | ink |
| n/a (scenario) | none | "n/a" + reason | ghosted cell | `ink-muted` |
| Couldn't start | `tt-g-blocked` (not a severity shape) | "Couldn't start the run" + reason | — | ink |

### 8.2 Rules

1. **Glyph never alone**, in UI, CLI and PR (TT §1.4). Dense rows may shorten to glyph + ID only if the full word is in the accessible name **and** a legend with words is on screen (the verdict line acts as legend, UF §5.2).
2. **Critical vs High** share a hue (`conflict` vs `sev-high`, 1.01:1, `qa-timetable-v2.md` §6): the distinction is shape (■ vs ▲) plus word, by design. Tests must check shape, not colour.
3. **Errors that are not findings** (Couldn't start, Aborted, unreachable, server error) never use a severity shape (A11 V2).
4. **Greyscale test** on every frame: severity, signature state, pass/fail, over budget and the state change stay distinguishable (TR test 86).
5. **Forced colours:** all glyphs `currentColor`; no meaning carried by background fill alone.

### 8.3 Non-web outputs

| Surface | Rule |
| ------- | ---- |
| CLI / CI | Colour only on glyph and severity word; meaning identical with `NO_COLOR` (GF §4). Live status line redraw ≤ 4/s in a TTY. **Proposed (Q13):** `--progress=plain` (and `WEBHOOKTRAP_PROGRESS=plain`, NAME-3) makes a TTY behave like non-TTY output, append-only with no redraw, for terminal screen-reader users. |
| ASCII fallback | `[C] [H] [M] [I] [-]` carry the severity letter, plus the word (GF §3). |
| PR comment | Glyph + word in every heading and table cell; table has a header row; the glyph column header is empty but each row's word repeats in the Result column. `<details>` summaries carry severity words. |
| JSON / MCP | Severity as a word (`"high"`), never a colour or glyph only. |

---

## 9. Per-view requirements

| View | Must hold (beyond §2–§3) |
| ---- | ------------------------ |
| **Landing** | One `h1`; each section `h2`; demo text alternative tells the whole 1 → 2 story; animated demo has Pause; no-JS CTA (form POST) works; sticky mobile CTA doesn't cover focus (G6). |
| **Capture · waiting** | Provider steps are tabs with panels (TT §3.4); the capture URL is selectable text and copyable; "Waiting…" is text, not only an animated mark. |
| **Capture · receiving** | Treegrid one tab stop; new rows never steal focus or move the selected row; "N new" pill is a button reachable after the list; arrivals announced per §6. |
| **Capture · banners** (stream lost, quota, offline) | Polite announcement once; banner text says what still works. |
| **Delivery detail** | Invalid-signature reason is the first focusable text after the chip and is read when the capture opens; Raw region keyboard-scrollable; header table has column headers; body-not-kept and binary states explain in text. |
| **Run · draft** | Steps are fieldsets with status in the legend; disabled Run has its reason as adjacent text, referenced by `aria-describedby`; pairing countdown per §6. The Verdict level step reads each probe entry with its tag ("orders.count, count") and the PV-14 sentence as text. |
| **Run · running** | Focus to verdict line on start; Freeze view (G16); auto-follow never moves keyboard focus; progress line is text; finding titles appear without F-numbers until the run ends (FND-6), and the final renumbering is announced once in `run.final`, never per change. |
| **Run · finished** | Default open finding (F1) does **not** take focus; findings in severity order with the open one expanded in place; graph `desc` final; State reading text includes "changed". |
| **Run · couldn't start** | Focus stays on the verdict line (moved there by Run); the reason and its fix are the next focusable text; nothing in the strip or grid is focusable except the Once rows that were sent. |
| **Run · aborted / inconclusive / passed** | Status word in the verdict line; "sent, no result" and "not run" rows have the words in their name; Passed lists what was checked and what was not checked (RUN-7) as text. |
| **Inspector (desktop and mobile sheet)** | Mobile sheet is a modal dialog with a heading "D5, Twice"; Request/Response tabs; Compare is a switch; latency against budget always as text ("196 ms of the 10 s budget, Webhook Trap default"). Tampered shows its diff as text ("data.object.amount_paid: 2400 to 0"). |
| **Runs list** | Grid with status words; the worst-severity glyph is decorative next to the word. |
| **Fixtures** | Drift explained in text with the path; no-agent state explains why content isn't shown. |
| **Agent** | Pairing code readable character by character; secret names are text; "never values" stated. |
| **Settings, Account, Admin** | Labels on every field; errors next to the field and summarised at the top on submit (`role="alert"` once); destructive actions confirm in a dialog that names the object; write-only secret fields (admin) say "Saved. The value can't be shown again." |
| **Share / example run** | Same structure as the run, read-only; Sign in is a link; no focusable controls that do nothing. Hidden bodies say so in text ("Body hidden by the owner"); hidden header rows say "hidden in shared views". Provenance line present. Probe codes per §3. |
| **Share dialog** | Modal dialog; "Shared as Maya Lindqvist" is the first text after the title; the visibility choice is a radio group; the PV-14 line is adjacent text tied to **Anyone with the link** by `aria-describedby`; **Hide bodies** is a `switch`; **Include probe values** is a checkbox whose description lists the entries; Copy link and Create a new link confirm through `ui.confirm`; Create a new link and Stop sharing don't need a second dialog (both are reversible by sharing again), but their effect is stated next to them. |
| **Expired / not found** | Focus to `h1`; one primary action. |
| **Sign in, create account** (rev. 3) | Order: providers, passkey, password form, footer links; visual order = DOM order at 1440 and 390. Every field has a visible label (no placeholder-only). "Show" is a `button` with `aria-pressed` and name "Show password". Errors: per field (`aria-describedby`, `aria-invalid`) and one summary `role="alert"` with links to the fields. Uniform failure copy is the same text for every cause, so screen-reader output never leaks more than the screen. |
| **Passkey and security-key ceremonies** (S-02, S-17B, S-27, S-28) | A ceremony starts only from a button press, never on page load (the OS sheet would interrupt the screen reader mid-page); the triggering button gets `aria-busy="true"` and its label changes to the waiting text; the OS sheet is the platform's own UI. On return, focus is on the same button and the result is announced once (`role="status"` for cancelled, `alert` for failure). The 5-minute timeout is the platform's; cancelling is always possible and loses nothing (2.2.1). Users who can't use a key always see "Other ways". |
| **Two-step challenge** | `h1` then the active method panel with its own `h2`; "Other ways" is a list of links that swap the panel and move focus to its `h2`. Remaining attempts are text next to the field. Paused state: `role="alert"` once, with the time as text in UTC. |
| **Security page** | Seven `h2` sections (rev. 4); key list is a `table` with headers (Name, Type, Sync, Added, Last used, Actions); Type and Sync are words; **Blocked** is a word plus its sentence, never colour or an icon alone (§8); "blocked twice" is a third sentence, not a missing button with no explanation. Action buttons include the key name. Disabled Add buttons keep focus and carry their reason via `aria-describedby` (`aria-disabled`, not `disabled`, so the reason is reachable); the same for Change email and Disconnect during the recovery lock. **Where you're signed in** (rev. 4): a `table` with headers (Browser, Network, Signed in, Last seen, How, Actions); "This browser" is a word in the Browser cell, not only position or weight; Sign out buttons include the browser label ("Sign out Firefox 133 on Windows"); networks are read as text (`198.51.100.0/24`), never abbreviated in the accessible name. **Recent security activity**: a `table` (When, What, Browser, Network) with no interactive cells; times as text with UTC. The pending email line is text in section 1, with **Cancel change** right after it. |
| **Change email and sign-out dialogs** (rev. 4) | As security dialogs. Change email: the "Now:" line is text before the field; the uniform sent step moves focus to its title; the second-factor-required body is read once (`role="alert"`) and **Confirm it's you** is the first control. Sign out: the title names the browser or the count; toasts are `role="status"`. |
| **Security dialogs** | Modal dialogs (`role="dialog"`, `aria-modal`, labelled by the title); focus to the title on open, trapped inside, returned to the opener on close (or to the section heading after a removal). The QR code has an `alt` pointing to the setup key; the setup key and recovery codes are text in Martian Mono, read character by character on demand, grouped with spaces so screen readers pause. The "I've saved these codes" checkbox gates **Done**; **Done**'s reason is adjacent text. Destructive buttons say what they do ("Remove and turn off"), not "OK". |
| **Recovery banner** | Ruled banner under the top bar, `role="status"`, announced once per page load, not on every poll; not dismissable; **Cancel recovery** is its only control and comes right after the skip link in tab order. Dates as text with UTC. |
| **Recovery pages** | The steps are an ordered list; the waiting time is text ("2 days 19 hours left"), never a countdown that updates live. |
| **Link pages** (S-32, rev. 4) | Focus to `h1` on load; one primary button, which is a real `button` in a `form` (works without JS); the page never submits by script or on a timer, so a screen-reader user hears the page before anything changes (and mail scanners change nothing). Secondary choices ("Not now", "Cancel, I found my key") are separate, clearly named controls. "This link doesn't work" uses the same words for every cause, with the one next step as the only control. |
| **Confirm it's you, OAuth and second-factor mode** (rev. 4) | When the password is not offered, a sentence says why (not an unexplained missing field). Provider notes ("GitHub may confirm without asking…") are text tied to their button by `aria-describedby`. `reauth_mismatch` is announced once (`role="alert"`) and focus returns to the provider button. |
| **Recovery review** (S-30) | Four `h2` sections; CI tokens a `table` (Token, Project, Last used, Actions); Revoke buttons name token and project; after revoking, focus stays on the row, whose word **Revoked** replaces the button and is announced via the toast. |
| **Delete account** (S-17, rev. 4) | The list of what goes is a real list; the checkbox gates **Delete account**, which stays focusable (`aria-disabled`) with its reason as adjacent text; the refused states (recovery lock, last admin) replace the form with text and one link, so nothing focusable does nothing. |
| **Account emails** | Plain-text part complete on its own; HTML part: real headings, link text that names the action ("Cancel recovery"), not "click here"; no information only in images; `lang="en"`. |

---

## 10. Test procedures for A11

Each procedure gives the tool, the steps, and the pass rule. "All states" = every state in UF §4 tables, RD §6 and IA §9, in light and dark. TR test numbers in brackets.

| ID | Procedure | Tool | Steps | Pass |
| -- | --------- | ---- | ----- | ---- |
| AX-01 | Automated rules on every screen and state | Playwright + axe-core (WCAG 2.2 A/AA tags) | Drive each state with the fake agent and fixtures; run axe in both themes at 1440 and 390 | 0 serious/critical violations [80] |
| AX-02 | Token contrast | Script over `tokens.json` | Compute every text and mark pair in UF §7.1 / `qa-timetable-v2.md` §6, both themes | All pairs ≥ targets [81] |
| AX-03 | Target size | Playwright script | For every focusable/clickable element, read its bounding box; desktop ≥ 24 px (or spacing exception), at 390 ≥ 44 px | 0 failures |
| AX-04 | Focus not obscured | Playwright script | Tab through each screen; for each focused element, compare its box with sticky top bar, action bar, bottom nav, toasts | No focused element fully hidden; partially hidden only if ≥ 50 % visible (AA), product rule: 0 % hidden |
| AX-05 | Reflow and zoom | Playwright | Viewport 320 × 640; and 1280 at 200 % zoom (`deviceScaleFactor` + CSS zoom) | No page-level horizontal scroll; no lost content; allowed scrolling regions per G8 [85] |
| AX-06 | Keyboard-only path | Playwright keyboard script + manual run | §5.6 path; record focus order; check no trap (Tab and Shift Tab leave every region) | Path completes; focus order matches UF focus orders [82] |
| AX-07 | Scope rules (A-27) | Playwright, table-driven | For each screen, for each key in §5.3–5.5: press with focus in L0 (filter), L1 (each widget), L2 (body); assert the action or no action per layer table | 100 % of cells match |
| AX-08 | Single-key switch | Playwright | Turn the switch off; press every L2 key and `?` `g` `c` | No action; chords and L1 still work [88] |
| AX-09 | No single-key side effects | Playwright | Press every unmodified key on a finished run and on a draft | No run starts, aborts, writes or deletes |
| AX-10 | ARIA patterns | Playwright DOM assertions | Treegrid, grid, tabs, toolbar, findings list, splitter: roles, one tab stop, roving tabindex, `aria-expanded`/`aria-selected`/`aria-pressed` updates | Matches §3 [83] |
| AX-11 | Live-region log | Recorder: `MutationObserver` on the two announcer regions + timestamps | Scripted capture burst (10 deliveries in 2 s); scripted run (fake agent emits Run #3, 16 deliveries); agent disconnect at D3 (Run #2); copy actions; countdown with fake clock | Message text and timing per §6.2; ≤ 1 `capture.arrivals` per 5 s; no per-delivery run messages; exactly one `alert`, only for the disconnect [87] |
| AX-12 | Screen reader, desktop | NVDA + Firefox; VoiceOver + Safari | Manual script: landing story; capture an arrival; open #09 and hear the reason; run Run #3; open F2; inspect D5 with compare against D4; read graph summary; Passed run (#4, 9 scenarios); Couldn't start (baseline rejected) | Every fact on screen is reachable and read with its words (severity, state change, budget); nothing read twice for one event |
| AX-13 | Screen reader, mobile | VoiceOver iOS, TalkBack | Share view `/s/{shareToken}` → F2 → D5 sheet; capture list → #01 detail | Same as AX-12 for those views |
| AX-20 | Probe codes | Playwright + NVDA | States-sheet code reading: hover and focus the first code, Esc dismisses, pointer can move onto the tooltip; 12-character code at 320 px | Tooltip text is the PV-14 sentence; name "code a1f3c0"; no truncation, no horizontal scroll (1.4.10, 1.4.13) |
| AX-14 | Reduced motion | Playwright `emulateMedia({ reducedMotion: 'reduce' })` + screenshots | Landing, capture arrival, running run | No transition/animation running (check `getAnimations()` empty); final frames shown [84] |
| AX-15 | Greyscale | Screenshots through a greyscale filter, reviewed against §8.1 | Run findings, running, aborted; capture with all signature states | Every meaning in §8.1 distinguishable [86] |
| AX-16 | Forced colours | Chrome `forced-colors: active` emulation | Run findings, capture | Glyphs visible; budget edge and label visible; focus visible |
| AX-17 | Text spacing | Injected CSS | All screens | No clipping or overlap |
| AX-18 | Graph equivalence | DOM script | For Run #3: every value drawn in the graph (D-number, status, latency, over/within, state before/after, flagged; Burst final reading) exists in the grid row or the Burst scenario row | 16 / 16 rows complete [TR R65] |
| AX-19 | Timing | Fake clock | Pairing code at 1:00 and 0:00; Get a new code at any time | Warning announced; new code without losing draft choices |
| AX-23 | Auth (was a second AX-20; renumbered in rev. 3) | Manual + Playwright | Sign in with each method (password via a password manager, GitHub, Google, passkey); paste into password, one-time code and recovery code fields; check `autocomplete` tokens | 3.3.8 holds; paste works everywhere |
| AX-24 | WebAuthn ceremonies | Playwright with a CDP virtual authenticator (`WebAuthn.addVirtualAuthenticator`, internal + usb) | Register a passkey and a security key from S-17B; sign in with the passkey (button and conditional UI); second step with the key on S-27; cancel each (no user presence); counter-regression authenticator | Focus returns to the starting button; one announcement per outcome; uniform failure copy; no ceremony starts without a button press |
| AX-25 | Two-step challenge and paused state | Playwright + NVDA | Wrong codes to 2 left, method switch, 5 attempts, paused account (fake clock) | Attempts left read with the field; focus moves to the new panel `h2`; paused announced once |
| AX-26 | Security page and dialogs | Playwright DOM + VoiceOver | Table headers, action names with key names, `aria-disabled` reasons at the 10-key limit and without WebAuthn; recovery codes dialog Download/Copy/Print, checkbox gating Done; focus return | All pass at 1440 and 390, both themes |
| AX-27 | Recovery banner | Live-region recorder (as AX-11) | Open a recovery, navigate 5 pages, let polling run 2 min | Exactly one announcement per page load; banner control reachable second in tab order |
| AX-28 | Link pages (rev. 4) | Playwright: `GET` each emailed link with JS on and off; HEAD/GET by a link-preview user agent | Nothing changes on `GET` (token still valid, email unverified, wait not started); the button works without JS; focus on `h1`; same "doesn't work" text for expired, used and unknown | Pass for verify, email change, reset, recovery request and finish |
| AX-29 | Sessions and activity tables (rev. 4) | Playwright DOM + VoiceOver | Headers, "This browser" as text, Sign out names, sign-out dialog focus return to the section heading; mobile list order | All pass at 1440 and 390, both themes |
| AX-21 | CLI without colour or glyphs | Golden tests (GF) + manual VoiceOver Terminal / NVDA Windows Terminal read of `--progress=plain` output | Run #3 summary | Meaning identical without colour; every glyph followed by its word; no redraw noise in plain mode |
| AX-22 | PR comment | Render the Markdown in GitHub (test repo); NVDA + Firefox | Heading, table, open/closed details | Table read with headers; each finding summary carries its severity word |

Results go in A11's Gate 3 report with the defect severity of §1.

---

## 11. Changes in this revision (2026-11-14, decisions 35 and 38–39)

| # | Section | Change | Source |
| - | ------- | ------ | ------ |
| 1 | §3, §4, §5.6, §6.2, §9, §10 | Run #3 examples renumbered: 16 deliveries, D5/D11/D12 flagged, D10/D16 over budget, "3 findings · Also 1 Info", 43.2 s | C-4, RUN-3 (SJ §16.2, §16.6) |
| 2 | §3 strip, §8.1 | 8 or 9 cells; names for n/a, Inconclusive, unreachable and Couldn't start; none use a severity shape | A-7, SJ SCN-1, RUN-1 |
| 3 | §3 grid, §9 | Accessible names for "sent, no result", "not run" and Burst rows (state read once) | SJ §7.1, BURST-4 |
| 4 | §3 setup form | Once is checked and disabled with its reason | PLAN-2 |
| 5 | §6.2, §9 | No F-numbers in live announcements; numbers final at the end | FND-6 |
| 6 | §3 meter, §6.2 | Guest runs out of 10; agent-replaced message | A-13 |
| 7 | §3, §9 | Budget read as the BUD-1 label | BUD-1 |
| 8 | §8.3 | `TRAP_PROGRESS` → `WEBHOOKTRAP_PROGRESS` | NAME-3 |
| 9 | §9 | Share dialog row; hidden bodies said in text | P2 |
| 10 | §3, §10 | AX-13 on `/s/{shareToken}`; new AX-20 for probe codes. Probe code component (name, tooltip on hover and focus, wrap); provenance line; pairing code read as 10 characters; pill state "confirm in your terminal" | PV-14, RUN-8, PO-E, PO-F |
| 11 | §6.2 | `agent.status` adds confirmation and decline messages; `ui.confirm` adds the new-link message | PO-A, PO-E |
| 12 | §9 | Share dialog rewritten (Shared as, PV-14 line, Include probe values); share view hidden headers and provenance; draft reads entry tags | PO-A, PV-7, PV-14 |
| 13 | §2 G14 | **Rev. 3.** Accessible authentication restated for password managers, passkeys, one-field codes, downloadable recovery codes; email link dropped | Decision 44; A05 contracts §3.4 |
| 14 | §5.5, §9 | Bare auth pages take no shortcuts; new §9 rows for sign in, ceremonies, two-step, security page and dialogs, recovery banner and pages, emails | Decision 44; S-02, S-17A/B, S-24 to S-31 |
| 15 | §10 | Duplicate AX-20 (Auth) renumbered AX-23 and expanded; new AX-24 to AX-27 (virtual authenticator, two-step, security page, recovery banner) | Rev. 3 |
| 16 | §9 | **Rev. 4.** Security page rows for sessions, activity, lock reasons and "blocked twice"; new rows for change-email and sign-out dialogs, link pages, S-28 OAuth and second-factor mode, recovery review, delete account | A05 revision 5, U-10 to U-19 |
| 17 | §10 | New AX-28 (link pages: nothing changes on `GET`, works without JS) and AX-29 (sessions and activity tables) | A05 §2.9, §2.7 |

## Open for the PO

None. A-26 and A-27 were resolved in the first version; nothing in decision 35 reopens them.
