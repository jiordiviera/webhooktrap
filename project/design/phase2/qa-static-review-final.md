# QA static review, final · Timetable boards before Gate 2

**Author:** A11 (QA) · **Date:** 2026-11-14 · **Gate:** 2 (before the PO's review)
**Scope:** all 119 non-archive boards on the canvas (`p2-entry` … `p2-states`, `p2-account`) in `claude-design/timetable/canvas/project/`, the Timetable design system in `claude-design/timetable/ds/project/`, and their published copies: canvas `3ovs7azTxTzGFREDuBe6En` (version `1794682024-6f2c`, v46) and DS `7n63VUDFZC31A1bRTJfwNY` (version `1794677504-fff2`, v15). The 50 boards on `archive` are out of scope.
**Checked against:** `screen-specifications.md` (SS, S-00 to S-32 and the rev. 2–5 board lists), `user-flows.md` (FL-1 to FL-26), `accessibility-spec.md` (A11Y), `golden-files.md` §15 (GF), `../realistic-data.md` / `.json` (RD), decisions 35–47 (`project/management/decisions-log.md`), and my previous review `qa-static-review-v3.md`.
**Method:** static only. Board HTML parsed by script (headings per frame, form labels, `aria-*` references, roles, placeholders, value extraction); values cross-checked against RD and the specs by script and by hand; contrast recomputed from `tokens.css` with the WCAG 2.x formula; published files read through the Artifact tool (read only) and compared with the local sources. Nothing was published, committed or changed apart from this file.

## Verdict

**READY WITH FIXES.** No Blockers. Every earlier Major is fixed, coverage of SS S-00 to S-32 is complete at the frame level, the data is faithful, and the published artifacts match the local sources. Four Majors remain. All are cheap, and **F1 should be fixed before the PO opens the boards**: 21 primary call-to-action links render ink on ink (1.00:1) on 12 boards, so the PO would see blank black buttons on Register, Verify email, Password, Recovery and the system pages.

| Severity | Count |
| -------- | ----- |
| Blocker | 0 |
| Major | 4 |
| Minor | 9 |

## 1. Published = local

| Check | Result |
| ----- | ------ |
| Canvas file set | 174 project files published (169 boards, `canvas.json`, 4 DS-copy files) = 174 local files; every published size equals the local size. |
| Canvas content | 21 files fetched and compared by SHA-256, all identical: `canvas.json`, the 4 DS-copy files, `S00Shell`, `S02SignInStates`, `S05Runs`, `S10DraftServer`, `S11RunFindings`, `S12Dialogs`, `S17ASecurityStates`, `S17BEmail`, `S17BSignOut`, `S17DeleteAccount`, `S24Register`, `S31Emails`, `S32LinkPages`, `GF155PrComment`, `StatesSheet2`, `P2Graph`. |
| DS file set | 62 published project files, every size equal to the local file. The 20 SVGs in `assets/Icons` and `assets/Logos` are in the DS asset store, not the file set; sizes differ by 11–17 bytes because the store holds a re-serialised copy (double quotes, explicit closing tags). Checked `tt-unsigned`: same geometry and colours. |
| DS copy on the canvas | **Stale** (Minor m3): `canvas.json` pins DS `1794669859-47a3`, the live DS is `1794677504-fff2`; the canvas copy's `bundle.css` is 34 846 B against 50 324 B in the DS, and `README.md` differs. No board is broken by it: every `tt-*` class used on an in-scope board is defined in the canvas copy or in the board's inline `<style>` (script check, 0 undefined). |
| Holes and imports | Unchanged since v3 and still clean: dark wrappers import existing boards; no unresolved `{{…}}` outside `theme` / `showSprite`. |

## 2. Earlier Majors (v3), rechecked

| v3 | Status | Evidence |
| -- | ------ | -------- |
| M1 ■ on non-findings | **Fixed** | Script: every `#tt-g-crit` use on in-scope boards sits in a run verdict, F1/Tampered or a Critical cell. |
| M2 Unsigned finding as High | **Fixed** | StatesSheet2 "Unsigned · finding": "Critical · expected 4xx · got 200". |
| M3 Path strip #09/#08 | **Fixed** | S04Detail09 `received 14:02:08.731 · ingest 109 ms`; S04Detail08 `14:01:11.442 · 53 ms`. |
| M4 Server draft counts and copy | **Fixed** | S10DraftServer rows 2 / 1 / 1 / 2 / 1 as SS S-10 Shopify table, `5 s · Shopify limit`, Shopify why lines; citations `[pending A01]` as the spec itself has them. |
| M5 Aborted Burst result | **Fixed** | S11RunAborted: Burst "Not run". |
| M6 Invented run rows | **Fixed** | Runs list "Row states" uses `[time]` and "No run number"; the Couldn't-start board has no number; body-not-kept row is `[time] [provider] [event type]`. |
| M7 Guest meter | **Fixed** | `0/100 deliveries · 0/10 runs` (S03CaptureWaiting, S03CaptureMobileWaiting); `4/100 deliveries · 0/10 runs` (S03CaptureGuestFirst). |
| M8 No `h1` | **Fixed** | Every page frame has exactly one `h1` (script, per frame). The four S-17B mobile sheets have an `h2` dialog title over an undrawn page, which is correct. |
| M9 ARIA patterns | **Fixed** | Palette `combobox` + `listbox` + 9 `option` (S00Shell); 16 `input[role=switch]`; inspector sheet `role=dialog` (S11InspectorMobile); pill `aria-expanded` + `aria-controls`; `nav aria-label="Main"`. |
| M10, M11 PV rules | **Resolved** by A03's PV specs and drawn | StatesSheet2 code set (owner, shown, share-off, unchanged, 12-hex, `#invalid`), code grid rows, share tick variant; 25 `button.tt-pcode` named "code a1f3c0" etc.; tooltip and PV-14 note verbatim; `#invalid` without underline and with its tooltip. No reset command or `database:` on hosted boards; the remaining `sql:` snippets (S11RunPassed, S11RunInconclusive, S10DraftStates) are pack templates labelled "Example", as SS §0.5 rule 6 allows. |
| M12 S-11 states | **Fixed** | StatesSheet2: preflight verdict, Probe failed row, Unsettled row, Agent offline finished run, Aborted with numbered partial findings, queued confirm-in-terminal. |
| M13 PR comment | **Fixed** | GF155PrComment (+ dark): private and `--share`, rendered and Markdown; GF156Pairing added. |

Minors rechecked: m12 (retry and lock glyphs now in the Icons preview) and m18 (`P2Graph` `sel` enum includes D3, D13) are fixed; m2 is still open (m5 below). I did not re-verify the other v3 Minors one by one.

## 3. Findings

### Major

| ID | Finding | Evidence | Fix |
| -- | ------- | -------- | --- |
| **F1** | **Primary link buttons render dark on dark.** `.tt a { color: inherit; text-decoration: underline … }` has specificity (0,1,1) and beats `.tt-btn-primary { color: var(--on-ink) }` (0,1,0) and `.tt-btn { text-decoration: none }`. Every `<a class="tt-btn tt-btn-primary">` gets `ink` text on its `ink` fill, **1.00:1 in both themes** (needs 4.5:1), plus an underline in `rule-strong` (4.60:1 light, 3.82:1 dark on `ink`). A04's inline `style="color: var(--on-ink); text-decoration: none"` covers only S17DeleteAccount (2) and S32LinkPages (13). | **21 unfixed links on 12 in-scope boards:** S18SystemPages 6 (Get a new URL, Sign in, Go home, Back to the stream, Get a URL ×2), S14ExampleRun 2 (Get a URL ×2), S25VerifyEmail 2 (Sign in ×2), S29Recovery 2 (Sign in, Forgot password), StatesSheet2 2 (Get a new URL, Pair agent), and 1 each on S06Agents (Pair a new agent), S09Fixtures (Pair agent), S15Projects (New project), S18ExpiredMobile (Get a new URL), S24Register (Sign in), S26Password (Send a new link), S27TwoFactorStates (Back to sign in). All are light-theme boards with no dark wrapper; the bug is theme-independent. **The DS itself shows it:** `components/Button/preview.html` and `components/LinkPage/preview.html` each have an unfixed primary link. Source: `bundle.css` line 8 (both the DS and the canvas copy). Archive boards CaptureDesktop, DetailPane, LandingDesktop, LandingMobile and StatesSheet also have it (out of scope). | Fix once in the DS: `:where(.tt) a { … }` (specificity 0,0,1) or `.tt a:not([class]) { … }`, so component classes win. Republish the DS, re-sync the canvas copy (m3), then drop the inline patches on S17DeleteAccount, S32LinkPages and the three mobile boards. |
| **F2** | **Skip link unreadable when focused** (same root cause). `.tt-skip { color: var(--on-ink); text-decoration: none }` (0,1,0) also loses to `.tt a`, so "Skip to content" is `ink` on its `ink` fill, 1.00:1, when it appears on focus. A11Y requires it as the first focusable element on every app screen. | 83 skip links on 75 in-scope boards (every app board); `bundle.css` lines 8 and 336. Not visible in a static board because it is off-screen until focused, which is why nobody saw it. | Fixed by the F1 change. If F1 is not done, add `.tt a.tt-skip { color: var(--on-ink); }`. |
| **F3** | **Decided items still say pending.** SS change 34 (rev. 5) removed every `[pending PO]` marker after decisions 46 and 47, but the boards still carry them, so the PO will read their decided questions as open. | `[pending PO-J]` (PO-J decided, 46): S17BPasswordLinked, S28ConfirmAuth, S29Recovery, S30RecoveryReview, S30RecoveryReviewMobile (7 occurrences). `[pending PO-I]` (46): S02SignInVariants. `[pending PO-H]` (46): S26Password, 1.x frame. `[pending A05]` beside the `h1` on S17DeleteAccountMobile: SS rev. 5 board 4 says the "new, pending A05" label no longer applies. `[A05/A09: what an owner who has lost the account email can do next; no route exists]` on S31Emails `email-changed`: decision 47 (PO-O) settled it, with no revert link. | Remove these markers. Where useful, replace with "decision 46" or "decision 47". |
| **F4** | **Invented control on a frozen screen.** The "Check your email" frames (desktop and mobile) draw a **Cancel the link** button that calls `DELETE /account/deletion`. SS S-17 `delete` has only **Back to Security** and "Start over". The frame note admits it ("its label is not in A03's spec, drawn as a placeholder [pending A03]"), but the button label itself is unbracketed. That breaks SS §0.1 rule 1, and decision 47 froze account scope: new account cases go to the Phase 5 backlog. A07 would build it as drawn. | S17DeleteAccount, frames "Check your email · no two-step sign-in" and "Mobile · 390 · Check your email". | Remove the button, or bracket it as `[Cancel the link: Phase 5 backlog]`, and log it in `open-questions.md` for A03 and A05. |

### Minor

| ID | Finding | Evidence | Fix |
| -- | ------- | -------- | --- |
| m1 | The S-26 1.x frame uses Maya's address. RD says the imported 1.x accounts are the stand-ins Tomás Okafor, Aiko Brandt and Lena Vos. Maya is not one of them, so a 1.x "Set a password" form showing `maya@brindlecoffee.example` contradicts RD. `launch-1x` correctly uses `tomas@ferrymarket.example`. | S26Password "Reset form · imported 1.x account"; RD `account_security.imported_1x` | Use `tomas@ferrymarket.example`. |
| m2 | `.tt a` underline on non-primary link buttons: 34 `<a class="tt-btn">` on 27 boards show an underline that `.tt-btn` means to remove. A04 patched 3 mobile boards inline (S17ASecurityMobile, S17DeleteAccountMobile, S30RecoveryReviewMobile). | e.g. S04Detail09 (ghost "Open page"), S01Landing, S13SharedRun | Fixed by F1. |
| m3 | The canvas's DS copy is behind DS v15, as described in §1. Boards duplicate the v4/v5 DS CSS inline, so the two will drift. | `canvas.json` `designSystems[0].version`; `ds/timetable/components/bundle.css` 34 846 B vs 50 324 B | Re-sync the copy after F1 and update the pinned version. Inline blocks can then be dropped at A07 handoff. |
| m4 | Dangling `aria-controls="agent-pop"`: the agent pill points to a popover id that isn't on the board. | 46 boards (every board with the desktop top bar except S00Shell's open-popover frames) | In the build, render the popover, or set `aria-controls` only while it exists. On the boards, a note is enough. |
| m5 | (v3 m2, still open) The heading "Findings · 4" sits beside the verdict "3 findings" (RUN-3: Info not counted). | S11RunFindings, S13SharedRun, S14ExampleRun | "Findings · 3 + 1 Info", or no count. |
| m6 | (v3 m13, still open) Breakpoints: no narrow-desktop, tablet or 320 px reflow frames, and no S-13 mobile. SS §0.2 gives those widths no frame size, so this is acceptable for Gate 2. One narrow-desktop Run frame is still recommended before build. | canvas | As v3. |
| m7 | (v3 m10, still open) Run #4 shows D1–D5 plus a summary row, "D6–D17 … all pass. Per-delivery timings for Run #4 are not in the dataset." SS asks for 17 rows. | S11RunPassed | A03 adds Run #4 per-delivery data to RD. |
| m8 | Data placeholders still to fill before A07. All are bracketed, so they are acceptable for Gate 2: `[Subline: A02 copy, not in RD yet]` (S01Landing, S01LandingMobile), `[version]` and `[storage value]` (S19AdminOverview, S19AdminOverviewRp), `[Admin name] [admin email]` (S21AdminUsers), `[other account email]` (S32LinkPages ×3), `[Google account label]` (S17BPasswordLinked, S28ConfirmAuth). | as listed | A02 and A03 add them to RD. |
| m9 | One frame per state is drawn, but only S00Shell draws a focus state for the top bar. No board draws the skip link focused, which is how F2 went unnoticed. | canvas | Add one "focus order" frame (skip link visible, focus ring on the first control) to StatesSheet2 after F1. |

## 4. Coverage (SS S-00 to S-32, FL-1 to FL-26, GF §15)

- **Every SS screen has boards**, and every frame in the rev. 2 redraw list and the rev. 3, rev. 4 and rev. 5 account lists is present. Checked frame by frame by label: S02SignInStates (11 frames, incl. "Too many attempts. Try again after 14:58 UTC."), S24Register (10), S25VerifyEmail (7), S26Password (12), S27TwoFactorPanels and States (4 + 11, incl. Paused), S28ConfirmAuth (10, incl. second-factor mode, GitHub-only and Google-only, `reauth_mismatch`), S29Recovery (14, incl. 3b Declined, RC-2, both "doesn't work" variants), S30RecoveryReview (+ mobile), S17ASecurityStates (22, incl. the four EC-1 pending-email frames), S17BAddKey (13), S17BRemoveRename (10), S17BTotp (10), S17BRecoveryCodes (4), S17BPasswordLinked (17), S17BEmail (20, 2FA and no-2FA sets, mobile sheets), S17BSignOut (8), S17DeleteAccount (12, incl. Check your email desktop and mobile, sending, S-28 second-factor mode), S32LinkPages (37, incl. old-address confirm, Delete your account?, six "doesn't work" types, mobile), S31Emails (6 HTML + 26 plain-text rows = **32 templates**, as SS S-31). Dark variants exist for every ◐ board.
- **Run, capture and agent states:** S-03 list states (10), S-04 signature/shape/partial states, S-06 states (7), S-08 pair panel (8, incl. Confirm in your terminal and Declined), S-09 (5), S-10 refusals (17), S-11 (7 boards + StatesSheet2), S-12 Share (5 states), S-18 (8 frames).
- **Flows:** FL-1 to FL-14 map to pages 1–8; FL-15 to FL-26 map to the `p2-account` boards. FL-22 (1.x) has the S-02 footer, the S-26 1.x frames and `launch-1x`, which offers GitHub or Google but not passkeys, as the note to decision 46 requires.
- **GF §15.1–15.6:** all present. The pairing transcript shows `[y/N]`, the `npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD` command and the PV-14 config confirmation.
- **Gaps:** only m6 (breakpoints), m7 (Run #4 rows) and m9 (focus frame).

## 5. Data fidelity and content rules

- **Values sampled: 385.** 172 distinct tokens extracted by pattern (times, ms, sizes, ids, URLs, dates, emails, codes, D-numbers) and 213 distinct monospace cell values. 170 of the monospace values appear verbatim in RD or the specs. The other 43, and every pattern token not found verbatim, are compositions of RD values (`13:58:04.212 · 2 294 B`, `200 · 196 ms`, `/r/run_01M75F1M56RSH0CVKCTFX4FQWC/D12`, routes from SS on the `webhooktrap.dev` host) or are explained by m7. One wrong value was found: m1. 18 exact copy strings from SS S-02, S-17, S-17B, S-26, S-29, S-00 and §0.5 were matched verbatim.
- **Invented values:** none unbracketed except F4's button label.
- **PV display rules:** satisfied (see M10 above). Run #3 frames stay counts-only, and codes appear only on the states sheet and the S-10 PV-14 sentence, as SS §0.5 says.
- **No last-4 secrets:** no `…xxxx` fragment, "ending in", `••••` or `whsec_` on any token, secret or key row. The only `…4drI`-style ids are Stripe object ids. Run views say `Stripe-Signature · signed on maya-mbp · value not stored`. Capture Raw shows `[redacted · 41 bytes]` for credential headers.
- **Share links:** `/s/q7Vf2LmX9pK4wT8nR3bZcD` (S12Dialogs, S13SharedRun, StatesSheet2, GF152, GF155, GF156), and the new-link variant `/s/Hc3mW8tZ1qN5rB7xK2vYpL`. Private links use `/r/run_…`.
- **Provenance:** "Run by maya-mbp from the web app" with tip (`tt-prov`) on all 7 S-11 boards, S-13 and S-14; "judged on maya-mbp" on finding meta.
- **Pairing:** 10-symbol code `7KQ2M-XR9TD`, read as "One-time code 7 K Q 2 M, X R 9 T D", with used and expired variants. `[y/N]` appears in GF156.

## 6. Accessibility

- **Headings:** one `h1` per page frame on every in-scope board (states boards: one board `h1` plus one per frame). The CLI and graph boards (GF151, GF152, GF154, P2Graph) are terminal or component frames, not pages.
- **Forms:** 250 inputs, 0 without a label (`label[for]`, wrapping `label` or `aria-label`). 77 `aria-invalid="true"`, each with an `aria-describedby` that resolves to an existing id. Error summaries use `role="alert"` once per frame.
- **Roles:** 64 `dialog` and 22 `alertdialog`, all `aria-modal="true"` and labelled. The exception is the two non-modal agent popovers in S00Shell, which is correct for popovers. 16 switches, all `input[role=switch]`. Combobox, listbox and option on the palette. Tabs carry `tablist`, `tab` and `aria-controls`. The strip is a `toolbar`. 107 `status` regions.
- **Contrast**, recomputed from `tokens.css` (light / dark):

| Pair | On ground | On surface | Need |
| ---- | --------- | ---------- | ---- |
| ink | 16.08 / 15.04 | 14.97 / 13.75 | 4.5 |
| ink-muted | 6.35 / 7.47 | 5.91 / 6.83 | 4.5 |
| conflict | 5.46 / 5.93 | 5.08 / 5.42 | 4.5 |
| now | 5.75 / 6.87 | 5.35 / 6.28 | 4.5 |
| pass | 5.30 / 8.46 | 4.93 / 7.73 | 4.5 |
| sev-high | 5.39 / 7.99 | 5.02 / 7.30 | 4.5 |
| sev-medium | 5.20 / 10.50 | 4.84 / 9.60 | 4.5 |
| sev-info | 5.59 / 8.34 | 5.20 / 7.62 | 4.5 |
| on-ink on ink (primary `<button>`, switch on) | 16.08 / 15.04 | — | 4.5 |
| **primary `<a>`, skip link (F1, F2): ink on ink** | **1.00 / 1.00** | — | 4.5 |
| rule-strong (control borders), non-text | 3.49 / 3.94 | 3.25 / 3.60 | 3.0 |
| budget-edge on budget-fill, non-text | 3.89 / 6.62 | — | 3.0 |
| focus ring (ink) | 16.08 / 15.04 | 14.97 / 13.75 | 3.0 |

  Every token pair passes in both themes. The only failures are F1 and F2, which are CSS cascade bugs, not token values. Coloured text directly on `budget-fill` would fail in light (sev-medium 3.89, pass 3.96, conflict 4.08), but graph labels keep their `ground` halo, as v3 found. Email frames (S31) use the light hex values on purpose: `#5c5b55` on `#f7f7f4` is 6.35, and the email button is `#f7f7f4` on `#1c1b15` with `!important`, so F1 doesn't touch it.

## 7. Leftover markers

| Marker | Where | Gate 2 |
| ------ | ----- | ------ |
| `[pending PO-J]` ×7, `[pending PO-I]`, `[pending PO-H]` | see F3 | **Not acceptable**: decided (46) |
| `[pending A05]` beside the `h1` | S17DeleteAccountMobile | **Not acceptable**: SS rev. 5 withdrew it |
| `[A05/A09: … no route exists]` | S31Emails `email-changed` | **Not acceptable**: decided (47, PO-O) |
| `[pending A03]` on "Cancel the link" | S17DeleteAccount | **Not acceptable** as drawn: see F4 |
| `[pending A05 value]` (throttle) | S02SignInStates, S24Register | Acceptable: SS O-SS-9 (b) open; the frame shows the `Retry-After` time |
| `[pending A05 prop: … limit]` (bcrypt) | S17BPasswordLinked | Acceptable: O-SS-9 (d) open |
| `[A05: answer for an unknown or already revoked session id]` | S17BSignOut | Acceptable: O-SS-9 (e) open |
| `[pending A01]` ×4, `[page: pending A01]` | S10DraftServer | Acceptable: SS S-10 Shopify table has the same marker |
| `[PO approves text]` ×17, `[PO approves text and sending]` | S02SignIn (+ Mobile, States, Variants), S26Password, S31Emails | Acceptable: O-SS-7 and decision 41 still open with the PO |
| `[time]`, `[provider]`, `[event type]`, `[elapsed]` | S03CaptureStates, S05Runs, S11RunRunning, StatesSheet2 | Acceptable: bracketed instead of invented (closes v3 M6) |
| `[Subline …]`, `[version]`, `[storage value]`, `[Admin name]`, `[admin email]`, `[other account email]`, `[Google account label]` | see m8 | Acceptable for Gate 2; fill before A07 |

Not markers: `[event.id]`, `[D4]` … `[Full report]` in GF155 (Markdown link syntax), `["D4", "D5", "D10", "D11"]` on the landing (a JSON array), and `[scrubbed]` and `[redacted · N bytes]` (UI copy).

## Changes

| # | Change | Source |
| - | ------ | ------ |
| 1 | New file: final independent static QA of the Timetable boards before Gate 2. 0 Blockers, 4 Majors, 9 Minors. Every v3 Major is confirmed fixed. | A11, Gate 2 |
