# Navigation · Webhook Trap 2.0

**Status:** REVIEW_REQUIRED (P1 to P11 approved, decision 35) · **Author:** A03 (UX/UI) · **Date:** 2026-11-14, revised for decision 35 and for decisions 38–39 · **Phase:** 2
**Read with:** `information-architecture.md` (objects, routes, entry points; "IA §n" below), `accessibility-spec.md` (keyboard scope rules, "A11Y §n"), `user-flows.md` ("FL-n"), `screen-specifications.md` ("S-nn"). Judge rules, names and exit codes: A01 `scenarios-and-judge.md` ("SJ").
**Revises:** UF §2.3 (breadcrumb, Agent tab scope), UF §3.1 (global keys), UF Screen 4 shortcuts (`r`, `→`/`←`). Each revision is marked.

Wireframes are low fidelity: structure and order only. Sizes are CSS px at 1440 × 900 and 390 × 844, inherited from UF and TT. Visual treatment is Timetable's (TT, TT1).

---

## 1. Navigation model

**In three sentences.** The project is home: a single top bar carries the project (or guest meter), four section tabs (Capture · Runs · Fixtures · Agent), the capture URL and the agent's state, and is identical on every working screen. Inside a section, the screen's own primary list is the navigator (the arrivals board on Capture, the scenario strip and deliveries table on a Run), with selection held in the URL so every view is linkable and back never loses your place. Everything printed outside the browser (CLI, CI, PR, MCP) links back with one canonical URL per run (`/r/{runId}`, owner and members only), deep to the delivery or finding (`/D5`, `/F2`); a shared run has its own rotating link `/s/{shareToken}` (PO-A).

### 1.1 Layers

| Layer | What it contains | Persistence |
| ----- | ---------------- | ----------- |
| **Shell** | Top bar (desktop) / top bar + bottom nav (mobile), command palette, shortcut sheet, toasts, announcer | Same on every app screen; landing and share view have their own reduced shells |
| **Section** | Capture · Runs · Fixtures · Agent (+ Settings in the overflow) | One tab is current (`aria-current="page"`) |
| **Screen** | The page inside a section: Capture stream, Capture detail, Runs list, Run, Fixtures, Agent, Settings pages | Has a path |
| **View state** | Selection, tab, filter, view mode, compare, open finding | Query string; never a history step |

### 1.2 Shell variants

| Variant | Used by | Contents (left → right) |
| ------- | ------- | ----------------------- |
| **Landing header** | `/` | Mark + wordmark · Docs · GitHub · Self-host · Sign in. Sticky after the hero with a compact "Get a URL" (UF Screen 1) |
| **App, guest** | `/p/…` as guest | Mark · guest meter + "Sign in to keep" · section tabs · capture URL chip · agent pill · ⌘K · ? · Sign in |
| **App, signed in** | `/p/…`, `/r/…` as member, `/agents`, `/account` | Mark · project switcher · section tabs · capture URL chip · agent pill · ⌘K · ? · account menu |
| **App, self-host admin** | as signed in, plus | Account menu gains "Instance admin". `/admin` uses the signed-in shell with **no section tabs** and a back link "‹ Back to Brindle Coffee" |
| **Share view** | `/s/{shareToken}` | Mark · "Shared run" label · Sign in. No tabs, no URL chip, no agent pill |
| **Bare** | `/expired`, not found, `/sign-in`, `/pair` | Mark · Sign in (or nothing) |

### 1.3 Section tabs (global navigation)

| Tab | Goes to | Scope | Badge |
| --- | ------- | ----- | ----- |
| **Capture** | `/p/{projectKey}` | project | none (the live indicator lives in the capture header, not the tab) |
| **Runs** | `/p/{projectKey}/runs` | project | `Running` dot while a run is live in this project |
| **Fixtures** | `/p/{projectKey}/fixtures` | project | `Drifted` word when any set drifted (SHP-4) |
| **Agent** | `/agents` (signed in) · `/p/{projectKey}/agent` (guest) | workspace (P4) | none; the agent pill is the status |

**Revises UF §2.3:** Agent is a workspace object (P4, approved). The tab is the same for everyone; for a guest it opens the session's single agent page.

**Settings** (project) is in the top bar overflow (`⋯`) and the command palette, not a tab: it's visited rarely.

**Mark** goes to the current project's Capture inside the app, to `/` on landing and bare shells (UF).

### 1.4 Local navigation per area

| Area | Local navigation |
| ---- | ---------------- |
| **Capture** | Capture header: live indicator (Live · Paused · Catching up · Reconnecting) · counts · Grouped/Flat · filter. List ↔ detail pane. Detail tabs Verdict · Raw · Decoded. Prev/next in group. |
| **Run** | Run header: breadcrumb, "From" line, run switcher (`Run #3 ▾`, history in this project), actions. Scenario strip (selects a scenario). Graph (pointer only; mirrors the table). Lower zone tabs **Deliveries · Inspector**. Findings column. |
| **Runs** | Filters: source (Web · CLI · CI), status. Rows open `/r/{runId}`. |
| **Fixtures** | One row per fixture set; opens its file list (through the agent) and drift detail. |
| **Agent** | List of agents (signed in), each opens its page; "Pair a new agent". |
| **Settings / Account / Admin** | Section list on the left of the content (forms, not live data). |

---

## 2. Mobile behaviour (< 768)

| Element | Mobile rule |
| ------- | ----------- |
| Top bar (52) | Mark · project name or compact guest meter (`Guest · 23 h 49 m · 4/100 · 0/10 runs`) · agent pill (glyph + short word) · `⋯`. URL chip moves into the capture header as "Copy URL". Command palette from `⋯`. |
| Bottom nav (56) | `<nav aria-label="Main">`: **Capture · Runs · Agent · More**. More = Fixtures, Settings, Help, Sign in / account. Hidden while a full-screen sheet is open and while the on-screen keyboard is up. |
| Push, not panes | Capture row → pushes capture detail page (`/p/…/d/01`). Run delivery → full-screen inspector sheet (`/r/{runId}/D5`; in a share view `/s/{shareToken}/D5`). Each push is a history step, so the system back gesture returns. |
| Sticky action bar (52) | One per screen at most: Prove (capture detail), Run again / Save as fixtures (run). Never on top of the bottom nav: the action bar sits above it, or replaces it on push pages. |
| Run | Report-first order (UF Screen 4 mobile): verdict → findings → scenario strip (2 columns; 4 rows for 8 scenarios, 5 rows for 9) → deliveries list → inspector sheet. |
| Pairing | Prove without an agent opens "Pair from your computer" (TT GM3); the code and command are copyable, the share sheet is offered. |
| Focus not obscured | Sticky bars and the bottom nav reserve scroll padding (`scroll-padding-bottom`) so a focused element is never hidden under them (WCAG 2.4.11, A11Y §3). |
| Landscape phone | Same as portrait; bottom nav stays. |
| 768–1023 | Desktop top bar, section tabs shortened to text without icons; capture detail as an overlay panel 640 px; run findings below the verdict (UF §8). |

---

## 3. Breadcrumbs

**Revises UF §2.3 and TT §2.1 G2 (P5, approved).** A breadcrumb shows **where you are**, not where the object came from.

| Screen | Breadcrumb | Provenance line (separate, under the title) |
| ------ | ---------- | ------------------------------------------ |
| Capture stream | none (it is home) | — |
| Capture detail, full page | `Brindle Coffee › Capture › #01` | `In sub_1QH…4drI (4)` (group link) |
| Runs list | none (section root) | — |
| Run draft | `Brindle Coffee › Runs › New run` | `From sub_1QH…4drI · #01 #03 #04` |
| Run | `Brindle Coffee › Runs › Run #3` | `From sub_1QH…4drI · #01 #03 #04` (or `From fixtures · webhooktrap test` for CI runs, or `From CLI · maya-mbp`) |
| Fixtures set | `Brindle Coffee › Fixtures › webhooktrap/fixtures/stripe` | — |
| Agent | `Agents › maya-mbp` | — |
| Settings | `Brindle Coffee › Settings › CI tokens` | — |
| Admin | `Instance › Server replay` | — |
| Share view | none | `Shared by Maya Lindqvist` / `Example run` |

Why: a run reached from the Runs list, a CLI link or a PR comment must not show a back path through a group the user never visited. The group stays one click away on the "From" line. Guests: the first crumb is "Guest session".

Breadcrumbs are `<nav aria-label="Breadcrumb">` with an ordered list; the current page is text with `aria-current="page"`.

---

## 4. Back behaviour and history

### 4.1 What is a history step

| Change | History | Example |
| ------ | ------- | ------- |
| Section or screen change (path) | **push** | Capture → Run draft; Runs → Run #3 |
| Opening a full-page detail or a mobile sheet | **push** | `/p/…/d/01`, `/r/…/D5` on mobile |
| Selection in a list, detail tab, filter typing, view toggle, compare toggle, open finding (desktop) | **replace** | `?sel=02`, `?tab=raw`, `/r/…/F2` on desktop |
| Draft → run started | **replace** (`/runs/new` → `/r/{runId}`) | Back from a run doesn't return to a draft that already ran; "Edit scenarios" is the explicit way back |
| Run again | **push** (new run) | Back returns to the previous run |
| Sign-in round trip | `?next=` restores the exact URL | Deep link from a PR survives sign-in |
| Guest expiry | the current URL renders the expired screen in place; no redirect | Back can't reveal old data |

### 4.2 Restoring place

1. **Back restores selection and scroll** from the URL plus a per-entry scroll memory. The selected row is the scroll anchor (UF U4).
2. **Esc** closes the innermost layer first: popover → sheet → inspector → selection. It never navigates between screens. On mobile push pages, the header's `‹` is a real back.
3. **Live data never changes the URL.** New captures don't change `sel`; the run's default selection on finish (F1 opens) is a `replace` only if the user hasn't selected anything.
4. **Unsaved draft** (Run draft with edits) asks before leaving only if the user changed the target or scenarios; leaving a pristine draft is silent.

### 4.3 Page titles (WCAG 2.4.2)

`{screen-specific} · {project} · Webhook Trap`. Examples: `Capture (13) · Brindle Coffee · Webhook Trap` · `#09 Signature invalid · Brindle Coffee · Webhook Trap` · `Run #3 · 3 findings · Brindle Coffee · Webhook Trap` · `Run #3 · Running D11 of 16 · …` (updated at most once per scenario, not per delivery) · `Run #5 · Couldn't start · …` · `Shared run · 3 findings · Webhook Trap`. Guests: `… · Guest session · Webhook Trap`. The title never contains payload values.

---

## 5. Deep links from CLI, CI, PR and MCP

| Source | Printed text | URL | Opens |
| ------ | ------------ | --- | ----- |
| `webhooktrap prove` footer | `report    https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC` | `/r/{runId}` | Run, F1 open (default selection) |
| `webhooktrap show F1` | `open      https://webhooktrap.dev/r/run_…/F1` (PROPOSED line) | `/r/{runId}/F1` | F1 open, D12 selected |
| `webhooktrap prove` new-finding line | — (no URL per line; keeps rows ≤ 100 cols; title without F-number, FND-6) | — | — |
| `webhooktrap test --upload` | same `report` line + `Uploaded privately. Add --share to get a link anyone can open.` | `/r/{runId}` (members only, PO-C) | Run, provenance "Uploaded from CI (token github-actions)" |
| `webhooktrap test --upload --share` | `report` line + `share     https://webhooktrap.dev/s/q7Vf2LmX9pK4wT8nR3bZcD` | `/s/{shareToken}` | Shared run |
| `webhooktrap test` without upload | `Not uploaded: results stay in this log. Add --upload to keep run history.` | none | — |
| PR comment | `[Full report](…/r/{runId})` and, proposed, evidence links `[D12](…/r/{runId}/D12)` in each finding's summary; with `--share` the same links use `/s/{shareToken}` | `/r/{runId}…` (members) or `/s/{shareToken}…` | As above |
| GitHub annotations (`::error`) | message ends with `Run webhooktrap show F1 for the fix.` | none (annotations don't take links reliably) | — |
| MCP tool results | `url` field per run and finding | `/r/{runId}`, `/F{n}` | — |
| Pair command | `npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD` | none | Browser shows "Confirm in your terminal", then Connected after `y` |

**Rules.**
1. A deep link opens the same screen a click would, with the same default selection. `/F2` opens F2 and selects its first **offending** evidence delivery (D5, FND-5), with Compare on against its reference (D4). `/D5` opens the inspector on D5 with no finding forced open.
2. Unknown D or F number on a valid run: open the run and show an inline note "D22 isn't in this run (16 deliveries)". The run itself still loads.
3. Viewer without access: "not found" with Sign in, and `?next=` keeps the deep part (IA §5.3).
4. Links printed by the CLI always use the instance's public base URL (self-host prints its own host).

---

## 6. Global keyboard shortcuts

Scope rules (which layer a key belongs to, and what happens in text fields and with single-key shortcuts off) are in A11Y §5. This table is the **global** layer only. Screen keys are in A11Y §5.3–5.5.

| Key | Action | Single-key switch off |
| --- | ------ | --------------------- |
| `⌘K` / `Ctrl K` | Command palette | works |
| `⌘/` / `Ctrl /` | Shortcut sheet (always available) | works |
| `?` | Shortcut sheet | off (use `⌘/`) |
| `g` then `c` / `r` / `f` / `a` | Go to Capture / Runs / Fixtures / Agent | off (use the palette) |
| `g` then `s` | Project settings | off |
| `c` | Copy capture URL | off |
| `Esc` | Close the innermost layer, then clear selection | works |
| `⌘↵` / `Ctrl Enter` | **Start a run**: Run (draft) or Run again (finished run). **Revises UF Screen 4** (`r` removed) | works |

**Revises UF §3.1:** adds `g f`, `g s`, `⌘/`, and moves "Run again" off the single key `r` (a single key that starts work against the user's handler is too easy to hit, A-27).

`g` sequences: the second key must follow within 1 s; a pending `g` shows a small "g…" hint in the shortcut area of the top bar and is announced only if the sheet says so (not by default).

---

## 7. Wireframes (low fidelity)

Legend: `[ ]` button · `▾` menu · `⧉` copy · `◉ ○ ◎` agent / signature states (TT glyphs) · `…` truncated · `┃` now/live.

### 7.1 App shell, signed in, desktop 1440

```
┌ top bar 48 ──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ ⟍■  Brindle Coffee ▾   Capture  Runs  Fixtures  Agent   ⋯     webhooktrap.dev/c/Br7nDLk2Qm9x ⧉   ◉ maya-mbp   ⌘K  ?  ML ▾ │
├──────────────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ <screen>                                                                                                     │
│   breadcrumb (when not a section root)                                                                       │
│   screen header · local navigation                                                                           │
│   content                                                                                                    │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
 toasts: bottom-left, max 2                                announcer: visually hidden, in the shell
```

Project switcher `▾`: projects list · "New project" · (self-host admin) "Instance admin" is in the account menu, not here.

### 7.2 App shell, guest, desktop

```
┌ top bar 48 ──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ ⟍■  Guest session · expires in 23 h 49 m · 13/100 deliveries · 1/10 runs · Sign in to keep                    │
│     Capture  Runs  Fixtures  Agent   ⋯         webhooktrap.dev/c/k7Qm2xVb9TfL ⧉     ○ No agent   ⌘K  ?  Sign in │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
⋯ menu (guest): Settings › End session now · Help · Docs
```

### 7.3 App shell, mobile 390

```
┌──────────────────────────────────┐
│ ⟍■ Brindle Coffee      ◉ maya  ⋯ │ 52   (guest: ⟍■ Guest · 23 h 49 m · 4/100  ○  ⋯)
├──────────────────────────────────┤
│ breadcrumb or screen title       │
│ content                          │
│                                  │
├──────────────────────────────────┤
│ [ sticky action, if any ]        │ 52
├──────────────────────────────────┤
│ Capture   Runs   Agent   More    │ 56   <nav aria-label="Main">
└──────────────────────────────────┘
```

### 7.4 Landing (shell only; sections per UF Screen 1)

```
┌ header 56 ─────────────────────────────────────────────────────────────────┐
│ ⟍■ Webhook Trap                         Docs  GitHub  Self-host   Sign in   │
├────────────────────────────────────────────────────────────────────────────┤
│ H1  Your webhook handler works. Once.                                       │
│ [ Get a URL, no sign-up ]   npx @webhooktrap/agent ⧉                        │
│ (returning guest: [ Open your capture ] webhooktrap.dev/c/k7Q… · 13 · 23 h)  │
│ hero graph (final frame under reduced motion)                               │
├ sections 3–7 ───────────────────────────────────────────────────────────────┤
├ footer CTA · Docs · GitHub · Self-host · Status · Privacy ──────────────────┤
└────────────────────────────────────────────────────────────────────────────┘
```

### 7.5 Capture (section root, desktop)

```
├ capture header 56 ───────────────────────────────────────────────────────────────────────┤
│ Arrivals ┃ Live   13 deliveries · 6 groups · last 14:06:39       [Grouped│Flat] [/ Filter] │
├ list 480 (treegrid, one tab stop) ───────────┬ detail pane ──────────────────────────────┤
│ group row ……………………………… [Prove]              │ header: provider · event · #01 · [Prove] │
│   capture row                                 │ verdict lines: signature · shape · group │
│   capture row  ▌selected                      │ tabs: Verdict │ Raw │ Decoded             │
│ group row ……………………………… [Prove]              │ tab panel                                │
│                                               │ ‹ prev in group · next in group ›        │
└───────────────────────────────────────────────┴──────────────────────────────────────────┘
```

### 7.6 Runs (section root)

```
├ Runs ───────────────────────────────────────────── [Source: All ▾] [Status: All ▾] ┤
│ Run    Status          Source              From                 Started    Length │
│ #4     ▬ Passed · 1 Info Web · maya-mbp    sub_1QH…4drI         14:32:02   23 s   │
│ #3     ■ 3 findings    Web · maya-mbp      sub_1QH…4drI         14:09:02   43 s   │
│ #2     ⫽ Aborted       Web · maya-mbp      sub_1QH…4drI         13:57:02   2 of 16│
│ #1     — Inconclusive  Web · maya-mbp      #01                  13:28:02   1 s    │
│ (empty: "No runs yet. Prove a delivery to start one."  See an example run ›)       │
└────────────────────────────────────────────────────────────────────────────────────┘
```

Status words follow RUN-1. A row with findings shows the worst severity's shape before "N findings". Full spec: S-05.

### 7.7 Run (shell and zones only; full spec in S-11)

```
├ run header 56 ────────────────────────────────────────────────────────────────────────────┤
│ Brindle Coffee › Runs › Run #3 ▾          [Run again ⌘↵] [Save as fixtures] [Share] [⋯]   │
│ From sub_1QH…4drI · #01 #03 #04 · localhost:3000/webhooks/stripe via ◉ maya-mbp           │
├ verdict line 48 ──────────────────────────────────────────────────────────────────────────┤
├ scenario strip 64 (toolbar; 8 or 9 cells) ──────────────────────┬ findings 440 ────────────┤
├ delivery graph 264 (figure; mirrors the table) ────────────────┤ F1 ■ … F2 ▲ (open) …     │
├ lower zone: [Deliveries] [Inspector · D5]  (tabs) ─────────────┤                          │
│ deliveries grid  or  inspector (Request │ Response, State row) │                          │
└────────────────────────────────────────────────────────────────┴──────────────────────────┘
```

Draft state: strip hidden, setup replaces graph + lower zone, ghost graph on the right (TT G2). Share view: same zones, header actions replaced by "Sign in to run it again".

### 7.8 Fixtures

```
├ Fixtures ──────────────────────────────────────────── repo: ~/code/brindle-app via ◉ maya-mbp ┤
│ Set                     Files   Scenarios   State            Last CI run                   │
│ webhooktrap/fixtures/stripe  3 + webhooktrap.yml  8  Saved · in sync  —                     │
│ (drifted: "Shape drifted · customer.subscription.updated has cancellation_details.feedback") │
│ no agent: "Fixtures live in your repo. Pair an agent to read them here." [Pair agent]       │
└─────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 7.9 Agent(s)

```
├ Agents ─────────────────────────────────────────────────────────── [Pair a new agent] ┤
│ ◉ maya-mbp   Connected   agent 0.9.2   targets localhost:3000   secrets: 3 names     │
│   (opens: status · version · target allow-list · secret names, never values · Revoke) │
│ pair panel: one-time code 7KQ2M-XR9TD · expires in 9:41 · npx @webhooktrap/agent@1… ⧉│
└───────────────────────────────────────────────────────────────────────────────────────┘
```

### 7.10 Project settings (and Account, same pattern)

```
├ Brindle Coffee › Settings › Capture URL ───────────────────────────────────────────┤
│ Capture URL    │  webhooktrap.dev/c/Br7nDLk2Qm9x ⧉                                │
│ CI tokens      │  [Rotate URL…]  (confirm: providers must be updated)             │
│ Budgets        │  Retention: 30 days ▾                                            │
│ Danger         │                                                                  │
└────────────────┴──────────────────────────────────────────────────────────────────┘
```

### 7.11 Instance admin (self-host)

```
├ ‹ Back to Brindle Coffee      Instance › Server replay ──────────────────────────────┤
│ Overview       │ Server-side replay                                                 │
│ Access         │ Public destinations   ( ) Off  (•) On                              │
│ Users          │ Private destinations  (•) Off  ( ) On   ← off by default           │
│ Limits         │   Allow-list (hosts or CIDRs)  [ 10.0.4.0/24          ] [Add]      │
│ Server replay  │   Warning: the server will send to these addresses on behalf of   │
│ Secrets  ◇     │   any user of this instance.                                       │
│ Agents   ◇     │ [Save]                                                              │
└────────────────┴────────────────────────────────────────────────────────────────────┘
```

### 7.12 Share view, anonymous (mobile shown)

```
┌──────────────────────────────────┐
│ ⟍■ Shared run          Sign in   │
├──────────────────────────────────┤
│ Run #3 · 3 findings              │
│ Shared by Maya Lindqvist         │
│ Run by maya-mbp from the web app │
│ ■1 Critical ▲1 High △1 Med ○1 Info│
│ findings · strip · deliveries     │
│ [ Sign in to run it again ]       │
└──────────────────────────────────┘
```

### 7.13 Expired and not found (bare shell)

```
┌ ⟍■ ─────────────────────────────────────────── Sign in ┐
│ This guest URL expired                                  │   (or: "Not found")
│ Guest captures are kept for 24 hours. …                 │
│ [ Get a new URL ]   Sign in so URLs don't expire        │
└─────────────────────────────────────────────────────────┘
```

---

## 8. What this file does not cover

- User flows: `user-flows.md`. Full screen specs, states and copy: `screen-specifications.md`.
- CLI command names and flags: `webhooktrap` per C-1 (decision 35) until Q12 settles; flags beyond SJ §12 stay PROPOSED (Q13).

---

## 9. Changes in this revision (2026-11-14, decisions 35 and 38–39)

| # | Section | Change | Source |
| - | ------- | ------ | ------ |
| 1 | Header, §1.3, §3 | P4, P5 marked approved | decision 35 |
| 2 | §2 | Guest meter shows runs out of 10; strip 2 columns, 5 rows when 9 scenarios | A-13, A-7 |
| 3 | §3, §5, §7 | `trap` → `webhooktrap`; fixtures under `webhooktrap/fixtures/` | SJ NAME-1, NAME-2 |
| 4 | §4.3, §5, §7 | Run #3 is 16 deliveries and "3 findings"; deep links point at D5/D12; running title "D11 of 16" | C-4, RUN-3 |
| 5 | §5 rule 1 | `/F2` selects the first offending delivery with Compare on its reference | SJ FND-5 |
| 6 | §8 | Pointers to the new flow and screen files | — |
| 7 | Summary, §1.2, §2, §5, §7.9, §7.12 | Share view at `/s/{shareToken}`; `/r/{runId}` members only; CI `--share` and private-upload lines; PR links per share state; pairing code `7KQ2M-XR9TD` with `@1`; provenance line in the share wireframe | Decision 39 (PO-A, PO-C, PO-E, PO-F); A01 RUN-8 |

## Open for the PO

None. Navigation questions were P1 to P11, all approved.
