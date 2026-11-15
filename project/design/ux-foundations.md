# UX Foundations: Webhook Trap 2.0

**Status:** PROPOSED for A00 and PO review · **Author:** A03 (UX/UI) · **Date:** 2026-11-11
**Based on:** A01 `core-experience.md` Part 2 (concept B, Proving Ground), `vision.md`, `personas.md`, `positioning.md`; A02 `brand-territories.md`.
**Consumers:** A04 builds every screen in Claude Design from this file plus one territory file (`ux-timetable.md`, `ux-crashlab.md`, `ux-notation.md`) and the shared dataset (`realistic-data.md` / `.json`).

This file is **territory-neutral**. It fixes *what* each screen contains, in what priority, how it behaves, and every state it must show. The territory files decide *how it is structured and drawn*. Where a territory file contradicts this one on behaviour, accessibility or states, this file wins; on layout and visual hierarchy, the territory file wins.

Everything here is PROPOSED.

---

## 0. Read this first (A04)

1. **Four screens, three territories, one dataset.** Build Landing, Capture stream, Delivery detail and Run for each territory, at **1440 × 900** and **390 × 844**, light and dark. Use only `realistic-data.json`. Don't invent ids, numbers or copy.
2. **The headline frame of every territory is the Run screen in the `findings` state with D3 selected** (the 1 → 2 moment). That is what the PO will compare first.
3. **Plain labels are fixed.** Control labels and state labels come from §6 and `realistic-data` §6. Territory flavour goes in helper text, empty states and marketing only (A02 rule 1).
4. **Severity is never colour alone.** Every severity shows its word ("Critical") plus the territory's second channel.
5. **Every visual timeline has a synced table.** The graph, filmstrip or move list is a view; the table is the accessible source of truth and must exist on the same screen (it may be collapsed on mobile).
6. **The Run draft is a state of the Run screen, not a modal.** Prove always leads to `/runs/new` with the selection prefilled.
7. **Landing hero copy and the 1 → 2 story use the dataset's numbers**: `invoice.paid`, D2 then D3, `orders.count 1 → 2`, finding "Duplicate delivery created a second order". A02's examples say `subscriptions 1 → 2`; the dataset wins so that all three territories tell the same story.
8. **The 1 → 2 finding is High, not Critical.** A01 ranks state divergence High; Critical means security (F1, tampered). All three A02 brand images draw the duplicate with a Critical-looking mark (Timetable's red ■, Crash Lab's red/hazard treatment, Notation's `??`). Each territory file proposes the honest High encoding (A6, C9, N8). Making it red needs A01 to re-rank it, not a design choice.

---

## 1. UX principles (derived from A01's product principles)

| # | Principle | What it means on screen |
| - | --------- | ----------------------- |
| U1 | **Verdict first, bytes one click away** | Every screen opens on a judgement (signature valid, 4 findings). Raw bytes are always ≤ 1 click or 1 key away, never the default view. |
| U2 | **Evidence is linkable** | Every claim (a finding, a signature verdict, a shape change) names the delivery that proves it, and that name is a link. |
| U3 | **Exact numbers, both clocks** | Times show absolute (`14:09:04.068`) and relative (`+1 950 ms`). Latencies in ms with a thin-space thousands separator (`11 840 ms`). Never "fast", "slow" or "a while ago" without the number. |
| U4 | **Live without losing your place** | New data never moves what the user is reading or pointing at. It is announced and offered (a "3 new" pill), not forced. |
| U5 | **Honest about what we can't see** | Inconclusive, No secret, Not checked and Body not kept are first-class states with their own wording and next step, not greyed-out absences. |
| U6 | **Secrets never in the browser** | No screen has a field for a signing secret. Secret-related states point to the agent command. |
| U7 | **No upsell walls** | Guest limits are visible up front as facts. The way to keep work is "Save as fixtures"; signing in is offered, never required to see what you already captured. |
| U8 | **Machine parity** | Every verdict visible on screen has a "Copy as JSON" equivalent; the CLI command for the current action is shown where useful. |

---

## 2. Information architecture

### 2.1 Object model (what the user navigates)

```
Workspace
├── Guest session ─────────── (no account; 24 h; quotas)             PROPOSED limits, Q9 open
│   └── Capture URL  webhooktrap.dev/c/k7Qm2xVb9TfL
└── Project "Brindle Coffee" ─ (signed in)
    ├── Capture URL  webhooktrap.dev/c/Br7nDLk2Qm9x
    ├── Deliveries #01…#13 ── grouped by related object (Group)
    │     └── Delivery: Verdict · Raw · Decoded
    ├── Runs #1…#4 ────────── each made from 1+ deliveries
    │     ├── Scenarios (8) → run deliveries D1…D14
    │     ├── Findings F1…F4 (each cites D-numbers)
    │     └── Fixtures preview → written to repo by the agent
    ├── Agent(s)  maya-mbp ─── target, version, secret names
    ├── Fixtures & suite        (out of this round; linked only)
    └── Packs                   (out of this round; linked only)
```

Two numbering systems, never mixed: **captured deliveries are `#01`…** (arrival order in the project), **run deliveries are `D1`…** (order within one run). A run delivery always shows its source capture (`D3 · from #01`).

### 2.2 Routes (PROPOSED)

| Route | Screen | Guest | Signed in |
| ----- | ------ | :---: | :-------: |
| `/` | Landing | ● | ● (hero shows "Open Brindle Coffee") |
| `/c/{captureId}` | Capture stream (home) | ● | ● |
| `/c/{captureId}?d={deliveryId}&tab=raw` | Capture stream with a delivery selected | ● | ● |
| `/c/{captureId}/d/{deliveryId}` | Delivery detail, full page (deep link, mobile) | ● | ● |
| `/c/{captureId}/runs/new?from={deliveryId or groupId}` | Run, draft state | ● (quota) | ● |
| `/c/{captureId}/runs/{runId}?d=D3` | Run | ● | ● |
| `/r/{runId}` | Run, read-only share | ● | ● |
| `/c/{captureId}/runs` | Runs list (minimal this round) | ● | ● |
| `/agent`, `/fixtures`, `/packs`, `/settings` | Secondary (not designed this round; nav entries only) | partial | ● |

The capture id is the project's home. A signed-in user with several projects gets a project switcher in the top bar; a guest has exactly one session.

### 2.3 Navigation model

- **Global top bar (all app screens), 48 px desktop / 52 px mobile.** Left → right:
  1. Mark (→ project home, not landing, when inside the app).
  2. **Context switcher**: project name (signed in) or "Guest session" badge with its meter: `expires in 23 h 49 m · 13/100 deliveries · 1/3 runs`.
  3. **Section tabs**: Capture · Runs · Fixtures · Agent (Packs and Settings in the overflow). Mobile: these move to a bottom tab bar (Capture · Runs · Agent · More).
  4. Spacer.
  5. **Capture URL chip** (monospace, truncated in the middle, copy button). Always one click from anywhere.
  6. **Agent status pill** (see §5.3). Clicking opens the agent popover: status, target, version, secret names, the pair/update command.
  7. Command palette trigger (`⌘K` / `Ctrl K`), help (`?`), account / Sign in.
- **No left sidebar.** The capture stream's own list is the left column; a second nav column would cost 200+ px for 4 items.
- **Breadcrumb inside Run**: `Capture › sub_1QH…4drI › Run #3`. It is the only back path on desktop besides the browser.
- **Guest to account**: "Sign in to keep" in the guest meter. Signing in **claims** the session: deliveries, runs and agent pairing move into a new project named after the first provider ("Stripe project"), renameable. The capture URL stays the same. No data is lost and nothing is re-entered.

### 2.4 Primary flows

```
F1 First minute (guest)      Landing ─[Get a URL]→ Capture (waiting) ─[provider sends]→ Capture (receiving) → Delivery (Verdict)
F2 Prove (aha)               Delivery or Group ─[Prove]→ Run draft ─[agent missing? pair inline]→ [Run] → Run (running) → Run (findings) → select F2 → D3 side by side
F3 Fix loop                  Run (findings) ─[Run again]→ Run #4 (running) → Run (passed) ─[Save as fixtures]→ fixtures preview → saved
F4 Signature diagnosis       Capture ─ #09 "Signature invalid" → Verdict tab reason + fix → (fix on GitHub) → [Re-check]
F5 Shared reading (mobile)   PR comment link → /r/{runId} → verdict + findings → tap F2 → D3 request/response
```

---

## 3. Global elements

| Element | Behaviour |
| ------- | --------- |
| **Live indicator** | In the capture header. Four states: `Live` (stream connected), `Paused` (manual), `Catching up` (tab was hidden), `Reconnecting` (stream lost). Text label always present; the territory adds its own mark (Timetable's magenta now-tick, Crash Lab's armed dot, Notation's clock). |
| **Toasts** | Bottom-left on desktop, top on mobile, max 2 stacked, auto-dismiss 5 s except errors. Used for: copied, fixtures written, run started elsewhere. Never for findings (findings live on the Run screen). |
| **Command palette** | `⌘K`. Searches deliveries (by event type, id, object id), runs, and actions ("Prove selected", "Copy capture URL", "Pair agent", "Toggle grouped view"). |
| **Shortcut sheet** | `?` opens a modal listing every shortcut for the current screen. Includes a switch "Single-key shortcuts" (on by default) to meet WCAG 2.1.4. |
| **Copy affordances** | Every id, URL, header value, body and command has a copy control. Copy confirms in place ("Copied") for 1.5 s and in an `aria-live` region. |

### 3.1 Global keyboard map

Single-key shortcuts are disabled while focus is in a text input, and can be turned off.

| Key | Action | Scope |
| --- | ------ | ----- |
| `⌘K` / `Ctrl K` | Command palette | Global |
| `?` | Shortcut sheet | Global |
| `g` then `c` / `r` / `a` | Go to Capture / Runs / Agent | Global |
| `c` | Copy capture URL | App |
| `Esc` | Close popover, sheet, inspector; then clear selection | Global |

Screen-specific keys are listed with each screen.

---

## 4. Screens

### Screen 1 · Landing

**Purpose:** convert a visitor who searched a debugging pain ("stripe webhook signature failed") into someone with a live capture URL, and plant the idea that one delivery isn't a test.
**Primary task:** click **Get a URL, no sign-up**.
**Secondary tasks:** copy the `npx` command; understand the 1 → 2 story; check "secrets stay on my machine" and open source.

#### Section inventory (order is A01 §2.8; territories may reorder sections 3–7 and must say why)

| # | Section | Must contain | Priority |
| - | ------- | ------------ | -------- |
| 1 | **Hero** | H1 *Your webhook handler works. Once.* · territory subline (A02) · primary CTA **Get a URL, no sign-up** · secondary `npx @webhooktrap/agent` copy chip · trust line "Open source · self-hostable · secrets stay on your machine" | P1, above the fold at 1440 × 900 and 390 × 844 |
| 2 | **Demo strip (the brand image)** | One `invoice.paid` delivered once (pass, `orders.count 1`), then again (`orders.count 2`), then the finding **Duplicate delivery created a second order** with "Stripe may deliver the same event more than once." Must be fully understandable with motion off. | P1, its top edge visible above the fold on desktop |
| 3 | **Every way a webhook can arrive** | The 8 scenarios from the dataset (+ Unsigned, Sequence pack as "more"), each with name, one-line what, one-line why, provider citation | P2 |
| 4 | **Real traffic, not fixtures you wrote** | capture → scrub → commit → CI, with the dataset's fixture file names and scrub rules | P2 |
| 5 | **Secrets stay on your machine** + open source + self-host (`docker compose up`) | Plain statements; where secrets live; licence; repo link | P2 |
| 6 | **Works with your coding agent** | Short MCP/JSON example: the F2 finding as JSON (from the dataset) | P2 |
| 7 | **Providers strip** | Stripe, Shopify, GitHub, Twilio, Slack, Standard Webhooks, as text names (logos only if each provider's brand rules allow; use text in comps) | P3 |
| 8 | **Footer CTA** | "Prove your first handler in 5 minutes." + the primary CTA again · footer links (Docs, GitHub, Self-host, Status, Privacy) | P2 |

**Header (landing):** mark + wordmark, links Docs · GitHub (with star count) · Self-host · Sign in. Sticky after scrolling past the hero, 56 px, with a compact CTA "Get a URL".

#### Behaviour and states

| State | What changes |
| ----- | ------------ |
| First visit | As above. |
| **Returning guest with a live session** | Hero CTA becomes **Open your capture** and shows the URL `webhooktrap.dev/c/k7Qm2xVb9TfL` with `13 deliveries · expires in 23 h 49 m`. A text link "Get a new URL" stays. |
| Signed in | Header shows "Open Brindle Coffee"; hero CTA becomes **Open Brindle Coffee**. |
| CTA pressed | Button shows a 1-line progress label "Creating your URL…" (≤ 400 ms expected), then navigates to `/c/{id}` in the waiting state. If it fails: inline error under the button "Couldn't create a URL. Try again." with the button re-enabled. Never a modal. |
| Reduced motion | The demo strip shows its **final frame** (both deliveries, `1 → 2`, finding visible). A "Play" button may replay it on request. |
| No JavaScript | Hero and demo final frame render server-side; CTA is a plain link that creates the session server-side. |

**Desktop (1440):** content column max 1200 px, 120 px side margins. Hero ≤ 760 px tall so the demo strip's top shows at 900 px height.
**Mobile (390):** 20 px gutters. Hero stacked: H1 (≤ 3 lines), subline, full-width CTA (48 px tall), `npx` chip below. Demo strip follows directly, full-bleed, and must be readable at 390 px without horizontal scrolling (territories say how). Scenario grid becomes a 1-column list or 2-column compact grid. Sticky bottom CTA bar appears after the hero scrolls out (52 px), hidden again near the footer CTA.

**Accessibility:** one H1; each section an `h2`; the demo strip has a text alternative that tells the whole story in one paragraph ("Delivery 1 of invoice.paid: 200, orders.count 1. Delivery 2, same event: 200, orders.count 2. Finding: Duplicate delivery created a second order."). Animated demo has pause/stop (WCAG 2.2.2) if it runs longer than 5 s or loops.

---

### Screen 2 · Capture stream (main application interface)

**Purpose:** the project's home. Show every delivery as it arrives, judged (signature, shape), grouped by the object it concerns, and offer **Prove** everywhere.
**Primary task:** spot a delivery or group worth proving, check its verdict, press **Prove**.
**Secondary tasks:** copy the URL into a provider; find a specific delivery; diagnose a signature failure; pause the stream.

#### Layout zones (territory-neutral)

```
┌ Top bar 48 ─────────────────────────────────────────────────────────────────────────────┐
├ Capture header 56: URL (large, copy) · Live indicator · counts · view toggle · filter ───┤
├ List column (default 480, resizable 400–640) ┬ Detail pane (remaining ≈ 960) ───────────┤
│ Group header row ……………………………… [Prove]       │ = Screen 3 (Delivery detail)            │
│   delivery row                       [Prove]  │                                         │
│   delivery row                                │                                         │
│ Group header row ……………………………… [Prove]       │                                         │
└───────────────────────────────────────────────┴─────────────────────────────────────────┘
```

#### Content inventory and priority

| Item | Content (dataset example) | Priority |
| ---- | ------------------------- | -------- |
| Capture URL | `webhooktrap.dev/c/Br7nDLk2Qm9x` + Copy | P1 |
| Live indicator | `Live` | P1 |
| Counts | `13 deliveries · 6 groups · last 14:06:39` | P2 |
| Group header | object short id (`sub_1QH…4drI`), provider, subtitle ("Jonah Reyes · Monthly whole bean · renewal"), count (4), spread (1.2 s), worst signature state in the group, **Prove** | P1 |
| Delivery row | arrival time `13:58:04.212` · provider · event type `invoice.paid` · size `2 294 B` · signature state (label or glyph + accessible label) · shape flag only when not "matches" · **Prove** on hover/focus/selection | P1 |
| Shape flag | "New shape" / "Shape differs" (#11) | P2 |
| Ungrouped bucket | #13 curl | P2 |
| Guest meter | in top bar | P1 for guests |
| Filters | text filter (event type, id, object id), provider chips, signature state chips | P2 |

**Ordering:** groups ordered by most recent activity; deliveries inside a group in arrival order (oldest first, so the group reads as a sequence). Flat view (`v`): all deliveries newest first, each showing its group short id.
**Default selection:** on load, the newest group's newest delivery is selected unless the URL names one.
**Group expansion:** groups with ≤ 6 deliveries are expanded; larger ones show the first 3 + "12 more".

#### Actions

| Action | Where | Notes |
| ------ | ----- | ----- |
| **Prove** (primary) | every group header (proves the group) and every delivery row (proves that delivery); also in the detail pane header | Opens Run draft prefilled. Group Prove pre-selects Shuffled and Burst; single-delivery Prove disables them with reason "Needs 2+ related events". |
| Copy URL | capture header, top bar | |
| Pause / Resume live | capture header | |
| Filter | capture header | |
| Grouped / Flat | capture header | Remembered per user |
| Row overflow | Copy delivery id · Copy as curl · Download raw · Delete (signed in only, confirm) | |

#### Shortcuts

| Key | Action |
| --- | ------ |
| `j` / `k` or `↓` / `↑` | Next / previous row (groups and deliveries in visual order) |
| `←` / `→` | Collapse / expand group (on a group row) |
| `Enter` | Move focus into the detail pane (desktop) · open detail page (mobile) |
| `p` | Prove the focused row (delivery or group) |
| `Shift P` | Prove the focused row's group |
| `1` `2` `3` | Detail tab Verdict / Raw / Decoded |
| `/` | Focus filter |
| `n` | Jump to newest |
| `.` | Pause / resume live |
| `v` | Grouped / flat |
| `y` | Copy selected delivery's raw body |

#### Live behaviour

- **Arrival budget:** provider POST → row visible in **< 1 s** (dataset ingest 38–140 ms). Signature verdict arrives with the row; if the agent check takes longer than 300 ms, the row appears with "Checking…" and updates in place (no reflow).
- **Where new rows go:**
  - If the list is scrolled to the top **and** the pointer isn't over the list: insert with the territory's arrival motion (≤ 180 ms; none under reduced motion). A group that receives a new delivery moves to the top.
  - Otherwise: **don't move anything**. Show a pill pinned to the top of the list: "3 new deliveries" (`n` to jump). Counts in the header update.
  - The selected row never moves under the user's eyes; scroll position is anchored to it.
- **Hidden tab:** stop animating. On return, state "Catching up · 6 deliveries arrived while this tab was hidden", insert them without motion, clear after 4 s.
- **Announcements:** one polite `aria-live` message at most every 5 s, batched: "3 new deliveries: Stripe invoice.paid and 2 more."

#### States (all must be designed)

| State | Trigger | List column | Detail pane | Header |
| ----- | ------- | ----------- | ----------- | ------ |
| **Waiting for first delivery** | new URL, 0 deliveries | Replaced by the **onboarding panel**: URL huge + Copy, provider tabs (Stripe · GitHub · Shopify · Standard Webhooks · curl) with the dataset's exact instructions, "Waiting…" with the live mark, and a "Send a test request" button that POSTs a sample from the browser | Hidden (single column, centred, max 720) | Live |
| **Loading** | first paint | 8 skeleton rows with the real column widths (no shimmer under reduced motion) | Skeleton of the Verdict tab | `Loading deliveries…` |
| **Receiving** | normal | As specified | Selected delivery | Live |
| **Paused (manual)** | `.` or button | Frozen; pill counts arrivals: "4 new while paused · Resume" | Unchanged | Paused |
| **Catching up** | tab was hidden | Inserts without motion | Unchanged | Catching up |
| **Stream lost** | connection drop | Unchanged, banner above list: "Live updates interrupted. Reconnecting… Deliveries are still being captured. Last update 14:07:51." | Unchanged | Reconnecting |
| **Guest quota reached** | 100/100 | Persistent banner (not dismissible) with the dataset copy; list stays fully usable; Prove still works while runs remain | Unchanged | Meter shows 100/100 in the warning style |
| **Guest expired** | 24 h passed | Whole screen replaced: "This guest URL expired" + **Get a new URL** + Sign in. Nothing is shown from the expired session. | — | — |
| **Filter: no results** | filter matches nothing | "No deliveries match "invoice.voided"" + Clear filter | Last selection kept | — |
| **Body not kept** | > 5 MB | Row shows size and a "body not kept" mark | Raw tab explains, shows SHA-256 | — |
| **Server error** | 5xx loading list | Error block with Retry and ref code | — | — |
| **Offline** | browser offline | Banner "You're offline. Showing what was loaded at 14:07." | — | Offline |

#### Desktop 1440

List 480 px; rows 28–36 px depending on territory density; group headers 40–48 px. Detail pane ≥ 880 px so Raw shows 100 mono characters per line at 13 px.

#### Mobile 390

Single column list. Tapping a row pushes the Delivery detail page. Group headers are sticky while scrolling their group. **Prove** on mobile: shown on group headers and on the detail page; if no agent is connected, Prove opens the draft with "Pair from your computer" and the command to copy (pairing needs a terminal). Pause/filter in a header overflow. The URL chip collapses to "Copy URL" in the capture header.

#### Accessibility

- The list is a `treegrid` (groups expandable) with roving tabindex; one Tab stop for the whole list.
- **Focus order:** top bar → capture header (URL copy, live toggle, filter, view toggle) → list (one stop) → detail pane tabs → detail content → detail actions.
- Signature state per row is text or a glyph with an accessible name ("Signature invalid: signed with a different secret").
- New-row motion is ≤ 180 ms and suppressed under `prefers-reduced-motion`.

---

### Screen 3 · Delivery detail (one received webhook)

**Purpose:** answer "is this delivery right?" and give the exact evidence.
**Primary task:** read the verdict; on a failure, read the reason and fix; then **Prove**.
**Secondary tasks:** inspect exact bytes and header order; copy body/curl; read decoded fields; see related deliveries.

Appears as the right pane of the capture stream (desktop) and as a full page (mobile, deep link).

#### Structure

```
Header      provider · event type (large) · #01 · received 13:58:04.212 (+0.2 s after Stripe created it) · size · [Prove] [⋯]
Verdict row signature chip · shape chip · group link ("4 in sub_1QH…4drI")
Tabs        Verdict (default) │ Raw │ Decoded
Footer      Prev / next in group
```

#### Tab contents

| Tab | Content (in order) | Notes |
| --- | ------------------ | ----- |
| **Verdict** | 1. **Signature** block: state label, scheme, who checked it, detail sentence; for invalid: **Reason** (bold) + evidence + **Fix** + Re-check · 2. **Shape**: matches / new / differs (+ field diff for #11) · 3. **Provider metadata** (key–value, mono values): provider event id, API version, livemode, delivery headers that matter (`X-GitHub-Delivery`, `X-Shopify-Webhook-Id`, `webhook-id`) · 4. **Related deliveries** in the group as a mini sequence | P1 above the fold: signature + shape |
| **Raw** | Request line with **raw query string** exactly as received · **headers in received order**, original casing, values unwrapped (no reformatting), index numbers 1…n · body exact bytes in mono with byte offset gutter, line wrap toggle (off by default), "Copy body" / "Download raw" / "Copy as curl" · `authorization` and `cookie` show `[REDACTED]` with a note | Never pretty-print in Raw. Hex view for binary. |
| **Decoded** | Dataset `decoded_summary` lines as a human summary at top, then a collapsible JSON tree (keys sorted as received), with ids as copyable chips and timestamps (`created`) shown with their human date | Unknown content types fall back to text |

#### Actions

| Action | Kind |
| ------ | ---- |
| **Prove** | Primary (header). Label: "Prove" on single delivery. |
| Re-check signature | Secondary, only on invalid / no secret after a change |
| Copy body · Copy as curl · Download raw · Copy delivery id · Copy as JSON (verdict) | Overflow + Raw tab toolbar |
| Open group | Link |

#### Shortcuts

`1` `2` `3` tabs · `p` Prove · `y` copy body · `w` toggle wrap in Raw · `[` / `]` previous / next delivery in group · `Esc` back to list (desktop) / back (mobile).

#### Signature chip: every state

| State | Label | Helper (dataset) | Shows | Second channel requirement |
| ----- | ----- | ---------------- | ----- | -------------------------- |
| Valid | Signature valid | Checked on maya-mbp with STRIPE_WEBHOOK_SECRET (…3f9a). | Scheme, secret name hint, timestamp age vs tolerance | Territory glyph (filled) + word |
| **Invalid** | Signature invalid | **Reason:** Signed with a different secret. Detail (#09) + Fix + Re-check | Expected vs received prefixes, body integrity checks | Distinct glyph (crossed/cracked/✗) + word; never only red |
| No secret | No secret | Signed by Parcelbird, but no secret is set on your agent. | Agent command `trap secrets set parcelbird` | Neutral glyph + word |
| Unknown scheme | Unknown scheme | Signature-like header (X-Signature) we don't recognise. | **Define scheme** link (packs) | Neutral glyph + word |
| Unsigned | Unsigned | No signature header. Nothing to check. | — | Open/empty glyph + word |
| Not checked | Not checked | Secrets live on your machine. Connect the agent to check signatures. | Pair agent | Neutral + word |
| Checking | Checking… | — | Inline progress (≤ 300 ms usual) | Text |

#### Other states

| State | Behaviour |
| ----- | --------- |
| Loading | Header renders from list data immediately; tabs show skeleton for ≤ 300 ms. |
| Body not kept | Raw: "Body not kept (12.4 MB, limit 5 MB). Headers and SHA-256 stored." Decoded disabled with reason. Prove disabled: "Can't replay without the body". |
| Binary | Raw shows hex + ASCII columns; Decoded disabled. |
| Not found / expired | Full pane message with dataset copy + back to stream. |
| Read-only (shared) | Prove becomes "Sign in to prove". |

#### Desktop / mobile

- **Desktop:** pane width ≥ 880; header 72–96 px; tabs sticky under header when scrolling.
- **Mobile:** full page; header compresses to provider + event type + chips; **Prove** becomes a sticky bottom bar button (52 px) when an agent is connected, otherwise a secondary "Prove" that opens the pairing explanation. Raw tab: horizontal scroll inside the code block only, wrap toggle defaults **on** on mobile.

#### Accessibility

Tabs follow the WAI-ARIA tabs pattern (arrow keys between tabs). Code blocks are focusable regions with labels ("Raw body, 2 294 bytes"). Header list is a `table` with columns #, name, value. The invalid-signature reason is the first focusable text after the chip and is announced when the delivery opens.

---

### Screen 4 · Run (replay view and its responses)

**Purpose:** prove a handler: replay real captured events the way the provider delivers them against `localhost` via the agent, and show what the handler did to its state.
**Primary task (draft):** confirm target and scenarios, press **Run**.
**Primary task (result):** understand the verdict, open the worst finding, see the evidence delivery side by side, fix, **Run again**.
**Secondary tasks:** save fixtures; share; copy as JSON for the coding agent; inspect any delivery's request/response.

#### Zones (territory-neutral; territories restructure)

```
┌ Top bar ───────────────────────────────────────────────────────────────────────────────┐
├ Run header 64: breadcrumb · Run #3 · target localhost:3000/webhooks/stripe via maya-mbp │
│              (agent pill) · 8 scenarios · status · [Run again] [Save as fixtures] [⋯]   │
├ Verdict banner 64–96: status word · counts by severity · one sentence · duration        │
├ Timeline zone (primary)                                 ┬ Findings (≈ 400)              │
│  one entry per run delivery D1…D14, grouped by scenario │  F1 Critical …                 │
│  budget marker · state digest · verdict per entry       │  F2 High …                     │
├ Inspector (opens on selection; bottom split or panel)   │  F3 Medium …                   │
│  Request (left) │ Response (right) │ probe before→after │  F4 Info …                     │
└─────────────────────────────────────────────────────────┴───────────────────────────────┘
```

#### Content inventory and priority (findings state)

| Item | Dataset content | Priority |
| ---- | --------------- | -------- |
| Verdict | "4 findings · 1 Critical · 1 High · 1 Medium · 1 Info · 8 scenarios · 14 deliveries · 41.0 s" | P1 |
| Findings list | F1–F4: severity word + second channel, title, evidence D-links, one-line measured | P1 |
| Timeline | D1–D14 with scenario, event, sent `+ms`, signing, status, latency vs 10 000 ms budget, `orders.count before → after`, verdict | P1 |
| **The 1 → 2 marker** | On D3 (and D9): `orders.count 1 → 2`, visibly different from every other digest | P1 |
| Target + agent | `localhost:3000/webhooks/stripe` via `maya-mbp` · Connected | P1 |
| Scenario summary | 8 scenario rows with result line (dataset §4.1) | P2 |
| Inspector | request/response side by side for the selected D | P2 (P1 once a finding is opened) |
| Probe & levels | "Verdicts from Level 1 transport and Level 2 state probe (orders.count, reset between scenarios)" | P2 |
| Finding detail | Why it matters + citation, likely cause, fix snippet with framework label, Copy fix | P1 when a finding is open |
| Fixtures | state "Not saved" + Save | P2 |
| History | Run #1 Inconclusive, #2 Aborted, #3, (#4 Passed) in a run switcher | P3 |

**Default selection on finishing a run:** the **highest-severity finding** opens (F1), and its first evidence delivery is selected. **For the headline comparison frame, A04 selects F2 → D3** (the brand's moment).

#### Finding detail (when a finding is open)

```
F2 · High (+ second channel) · Duplicate delivery created a second order       Level 2 · state probe
Evidence  D2 D3 · D8 D9 (links)          Measured  D3: 200 in 196 ms · orders.count 1 → 2
Why it matters  Stripe may deliver the same event more than once… (Stripe docs › Webhooks › Handle duplicate events)
Likely cause    Responses to D2 and D3 are identical, so only the probe could see this.
Fix (Express + Postgres)   [code]   [Copy fix] [Copy finding as JSON]
```

#### Inspector: request and response side by side

| Request (left) | Response (right) |
| -------------- | ---------------- |
| `POST http://localhost:3000/webhooks/stripe` · size · sent `14:09:04.068` (`+1 950 ms`) | Status **200** · latency **196 ms** against the **10 000 ms** budget (bar or territory equivalent) |
| Signing: "re-signed (same event id)" + `Stripe-Signature` (truncated, copyable) | Response headers |
| Headers in sent order incl. `X-Trap-Run`, `X-Trap-Delivery`, `X-Trap-Dry-Run` | Body snippet `{"received":true}` (exact) |
| Body: "Exact bytes of capture #01"; **for D10 (Tampered) a diff vs the capture**: `amount_paid 2400 → 0` | Probe: `orders.count 1 → 2` (before → after) |
| Compare with: previous delivery of the same event (D2) toggle, which shows both responses identical | |

The "Compare with D2" toggle is what makes F2 understandable: same request, same response, different state.

#### Actions

| Action | Kind | Notes |
| ------ | ---- | ----- |
| **Run** | Primary in draft | `⌘ Enter` |
| **Run again** | Primary after a result | Same config; new run number |
| Abort | Secondary while running | Confirm; keeps completed deliveries |
| Save as fixtures | Secondary after a result | Opens preview: files + scrub rules → Write via agent |
| Share | Secondary | Copies `/r/{runId}` |
| Copy as JSON | Secondary | The whole run verdict, for the coding agent |
| Edit scenarios | Secondary | Returns to draft with the same selection |
| Copy fix / Copy finding as JSON | In finding detail | |

#### Shortcuts

| Key | Action |
| --- | ------ |
| `j` / `k` or `→` / `←` | Next / previous delivery (D-number order) |
| `[` / `]` | Previous / next finding |
| `Enter` | Open inspector for the focused delivery |
| `t` / `f` | Focus timeline / findings |
| `d` | Inspector: toggle "Compare with previous delivery of the same event" |
| `r` | Run again (asks to confirm if the side-effects warning applies) |
| `⌘ Enter` | Start run (draft) |
| `Shift X` | Abort (confirm) |
| `s` | Save as fixtures |
| `e` | Copy run as JSON |

#### Run draft (state `draft`)

Same screen, the timeline zone replaced by the **setup**, in this fixed order (each step collapses to a one-line summary when satisfied):

1. **Agent**: status pill + action. Not installed → the pair command `npx @webhooktrap/agent pair HXR-4K7` with copy, code expiry `9:41`, and the pairing state live. Connected → "maya-mbp · 0.9.2".
2. **Target**: `http://localhost:3000/webhooks/stripe` (editable text; must be in the agent's allow-list; validation message from the dataset `start_failed`).
3. **Events**: the source deliveries (#01, #03, #04), removable.
4. **Scenarios**: 8 rows, each with checkbox, name, one-line what, one-line why + citation, delivery count, and flags: "Needs secret" / "Needs 2+ related events" / "May repeat side effects". Pack defaults pre-checked (Stripe: all 8).
5. **Verdict level**: "Transport (always)" + "State probe: orders.count from trap.yml" (or "Add a probe" link with the one-line yaml) + reset hook status.
6. **Warnings** (only when relevant): side effects; needs secret; no reset hook.
7. Summary line "8 scenarios · 14 deliveries · about 45 s" + **Run**.

#### Live behaviour (running)

- Run starts in **< 1 s** from pressing Run (state `queued` visible only if the agent hasn't accepted within 300 ms).
- Each delivery appears **when sent** (pending), then updates when the response and probe arrive. Order: sent → responded → probe digest → verdict.
- A **progress line** in the verdict banner: "Running · D9 of 14 · Retry after error · 22.7 s".
- Findings appear in the findings column **as soon as they are known** (F2 after D3), sorted by severity, without reordering the timeline.
- The view auto-follows the newest delivery **only if** the user hasn't scrolled or selected anything since Run; otherwise a "Follow live" button appears.
- Announcements (polite): each scenario's result ("Twice: finding, duplicate delivery created a second order"), and the final verdict. No per-delivery announcements.

#### States (all must be designed)

| State | Verdict banner | Timeline zone | Findings | Actions |
| ----- | -------------- | ------------- | -------- | ------- |
| **Draft** | "Draft · 8 scenarios · 14 deliveries · about 45 s" | Setup (above) | Hidden; shows "Findings appear here as the run goes" | Run |
| **Queued** | "Queued · waiting for maya-mbp…" | Planned deliveries as ghost rows | — | Cancel |
| **Running** | progress line | Rows filling in; pending rows ghosted | Fill in live | Abort |
| **Passed** | "Passed · 8 of 8 scenarios" + the sentence from the dataset | All rows pass; duplicates show `1 → 1` | Empty state: "No findings." + what was checked | Run again · Save as fixtures (emphasised) |
| **Findings** | "4 findings" + counts + duration | All rows, findings marked | F1–F4 | Run again · Save as fixtures · Share |
| **Aborted** | "Aborted · Agent lost during D3 of 14. D1–D2 are kept." | Completed rows; remaining rows struck/ghosted with "not run" | Partial findings, if any | Reconnect agent (shows command) · Run remaining |
| **Inconclusive** | "Inconclusive" + "Responses are identical, but we can't see your database. Add a probe…" | All rows; digests show "—" | One Info item explaining the probe, with the yaml | Add probe (copy yaml) · Run again |
| **Order-dependent** (flag) | Adds "Order-dependent: no reset hook ran" under the verdict | Unchanged | Info item | Add reset |
| **Needs secret** (draft) | — | Affected scenarios flagged and unchecked; "Run only Once and Slow budget" offered | — | Run (subset) |
| **Side-effects warning** (draft) | — | Warning block before Run; Run asks to confirm once | — | Run anyway · Learn the dry-run header |
| **Start failed** | Error with dataset copy (target not allow-listed) | Setup stays | — | Copy fix command · Retry |
| **Read-only shared** | Same as findings, plus "Shared by Maya Lindqvist" | Same | Same | "Sign in to run on your agent"; no Run again |

#### Agent states as seen on this screen

| Agent state | Where | Effect |
| ----------- | ----- | ------ |
| Not installed | Draft step 1 expanded | Run disabled ("Pair an agent to run against localhost") |
| Pairing | Draft step 1, live | Run disabled; updates to Connected automatically, no reload |
| Connected | Pill | Run enabled |
| Target unreachable (refused / TLS) | Draft step 2 error, or mid-run on the delivery row ("connection refused" as the response) | In draft: Run disabled with the dataset helper. Mid-run: that delivery's result is "Unreachable", the run continues and ends Inconclusive for affected scenarios. |
| Disconnected mid-run | Banner + Aborted state | Completed deliveries kept |
| Version too old | Draft step 1 warning | Run disabled; shows update command |

#### Desktop 1440

Findings column 400 px fixed; timeline zone ≥ 880 px. Inspector opens as a bottom split at 45 % of the content height (resizable), or a right panel replacing findings when the territory prefers; request/response columns each ≥ 420 px.

#### Mobile 390 (report-first)

Order: verdict banner → findings list (each expandable to detail + evidence links) → scenario results (8 rows) → deliveries list (compact rows) → inspector opens as a full-screen sheet with **Request / Response as two tabs** (side by side doesn't fit) plus a "Compare with D2" switch. Running from mobile is allowed only with an agent connected; draft setup is a single scroll. Sticky bottom bar: Run again / Save as fixtures.

#### Accessibility

- **Focus order:** top bar → run header actions → verdict banner (focusable summary) → findings list → timeline (one stop; arrows inside) → inspector.
- The timeline's visual form (graph, filmstrip, move list) must be mirrored by a `table`/`grid` with the columns of dataset §4.2; selecting in one selects in the other.
- Latency against budget: always as text too ("11 840 ms, 1 840 over the 10 000 ms budget").
- The 1 → 2 state change is text (`orders.count 1 → 2`), with "changed" spelled out in its accessible name ("orders count changed from 1 to 2, finding F2").

---

## 5. Cross-cutting states

### 5.1 Run status vocabulary (labels are fixed)

`Draft` · `Queued` · `Running` · `Passed` · `N findings` · `Aborted` · `Inconclusive`. Territory names (Adjourned, Rig offline, Conflict) appear only as helper text unless a comprehension test proves the territory word works better (see each territory file).

### 5.2 Severity (labels are fixed)

| Severity | Meaning (A01) | Dataset example | Order |
| -------- | ------------- | --------------- | ----- |
| Critical | Security | F1 Accepts tampered payload | 1 |
| High | State divergence | F2 Duplicate delivery created a second order | 2 |
| Medium | Budget exceeded, 4xx trap | F3 Exceeded Stripe's response budget | 3 |
| Info | Suggestion | F4 Probe doesn't see subscription state | 4 |

Severity always renders as: **word** + territory channel (glyph, pattern or annotation). In dense rows the word may shrink to its first letter only if the full word is in the accessible name and a legend is on screen.

### 5.3 Agent pill (global)

| State | Pill label | Pill action |
| ----- | ---------- | ----------- |
| Not installed | No agent | Opens pair instructions |
| Pairing | Pairing… | Shows code + expiry |
| Connected | maya-mbp | Popover: target, version, secrets (names only) |
| Target unreachable | maya-mbp · target unreachable | Popover with reason |
| Disconnected | maya-mbp · offline | Popover: last seen, reconnect command |
| Version too old | maya-mbp · update needed | Update command |

### 5.4 Fixtures (shown on Run, linked from capture)

Not saved · Saved (in sync) · Drifted (with the #11 field diff). Copy in dataset §6.

### 5.5 Errors

Server error, offline, not found, read-only: dataset §6. Errors are inline where the failed thing lives, never a full-screen takeover except for expired / not found.

---

## 6. Content and formatting rules

- **Numbers:** thin-space thousands (`11 840 ms`, `2 294 B`). Bytes under 10 000 in B, then KB with one decimal.
- **Times:** 24 h, milliseconds for deliveries (`14:09:04.068`), seconds elsewhere. Relative in run (`+1 950 ms`). Dates `Nov 11, 2026`.
- **Ids:** middle-truncated (`sub_1QH…4drI`), full on hover/focus and copy. Never truncate event types.
- **Mono** for ids, headers, bodies, commands, times in tables, digests.
- **Labels** in sentence case; verbs on buttons ("Prove", "Run again", "Save as fixtures", "Copy URL").
- **Voice:** precise, calm, a little dry. Measured value first, then the conclusion. No exclamation marks. No blame ("Your handler returned 200", not "You broke it").

## 7. Accessibility rules (all territories)

1. **Contrast:** text ≥ 4.5:1, large text (≥ 24 px, or ≥ 18.66 px bold) ≥ 3:1; meaningful non-text (budget lines, severity marks, focus rings, chart lines that carry data) ≥ 3:1 against adjacent colours. Verify in light and dark.
2. **Non-colour cues:** severity, signature state, pass/fail, over-budget and the 1 → 2 change each have a non-colour channel (word, glyph, pattern, weight, position). Test every screen in greyscale.
3. **Focus:** visible 2 px focus ring with 2 px offset, ≥ 3:1 against both the element and the ground; never removed. Roving tabindex in lists, timelines and tables.
4. **Keyboard:** every action reachable; shortcuts listed in `?`; single-key shortcuts switchable off; no keyboard traps in code blocks.
5. **Reduced motion:** no drawing, rolling, sliding or stepping; final states appear instantly; live indicators become static marks with text. Nothing flashes more than 3 times per second in any mode.
6. **Live regions:** polite, batched (§4), never assertive except for "Run aborted: agent disconnected".
7. **Targets:** ≥ 24 × 24 px desktop, ≥ 44 × 44 px touch.
8. **Reflow:** usable at 320 CSS px and 200 % zoom; the mobile pattern doubles as the reflow pattern.
9. **Text alternatives:** every visual timeline and the landing demo has a text equivalent carrying the same facts.
10. **Language:** chess/railway/lab terms never stand alone; a plain word sits next to every glyph on first view.

## 8. Responsive rules

| Width | Layout |
| ----- | ------ |
| ≥ 1280 | Full layouts as specified (designs at 1440) |
| 1024–1279 | Capture: list 400 px. Run: findings column 340 px; inspector becomes a right panel over findings |
| 768–1023 | Capture: list only, detail as an overlay panel 640 px. Run: findings move below the verdict banner |
| < 768 | Mobile patterns (designs at 390) |

## 9. Deliverables checklist for A04 (per territory)

| Screen | Desktop 1440 (light + dark) | Mobile 390 (light; dark for Run only) |
| ------ | --------------------------- | ------------------------------------- |
| Landing | full page | full page |
| Capture | receiving (#01 selected) · waiting (guest) · quota reached | receiving list · waiting |
| Delivery detail | #01 Verdict · #01 Raw · #09 invalid signature (Verdict) · #08 no secret | #09 invalid · #01 Raw |
| Run | **findings, F2 open, D3 inspector, compare with D2** · running (D9 of 14) · draft with agent not installed · passed (#4) · aborted (#2) · inconclusive (#1) | findings report · D3 inspector sheet |
| Components | severity set, signature chip set, agent pill set, run status set, live indicator set | — |

## 10. Open questions for the PO

1. **Guest quotas** (Q9): 24 h, 100 deliveries, 3 runs, 1 agent are placeholders. They show on several screens.
2. **Claiming a guest session on sign-in** (§2.3): assumed yes, no data loss. Needs an ADR-003 note.
3. **Provider budgets** in the dataset are marked VERIFY. They are drawn on screen as fact, so they must be verified before any public demo.
4. **Default scenario set** for Stripe: all 8 in the dataset. A shorter default (Once, Twice, Shuffled, Tampered) would make the first run faster (~15 s). Decide after the CLI prototype (Q13).
