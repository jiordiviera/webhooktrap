# QA static review: Claude Design directions (Phase 1b)

Author: A11 (QA) · 2026-11-11 · Scope: Timetable, Crash Lab, Notation (Design System + Design canvas each)

> **Rendering was not observed.** The built-in browser has no claude.ai session, so no artboard, preview or
> canvas was opened. Everything below is static analysis of the sources plus the published artifacts' file
> listings. Layout overflow, clipping, font loading and real colour output still need a human look.

## Method

- **Sources:** `claude-design/<territory>/` local trees. I compared them with the published artifacts by listing each artifact's files (`project/*` byte sizes). All 91 artboards, the 3 `canvas.json` files and every system file match byte for byte. The one exception is the Timetable `design-system.json`, which the server regenerates. Each published canvas also carries `project/ds/<folder>/tokens.json`, `tokens.css` and `components/bundle.css`, byte-identical to its system's copy.
- **Rules applied:** the canvas type's `SKILL.md`, `reference/format.md`, `craft.md` and `design-system-components.md`; the system type's `reference/format.md`; `a04-brief.md`; `ux-foundations.md` §4–§9; and `realistic-data.json`.
- **Scripted checks** (throwaway scratchpad scripts, now deleted):
  1. Markup: the `support.js` head line, `<x-dc>` count, `data-dc-script` shape, `data-props` JSON validity, unclosed or stray tags, self-closed non-void tags, unquoted attributes, capitalised tags and HTML content-model violations (nested `a`/`button`, block elements in `<p>`, `li`/`tr`/`td` parents).
  2. Holes: every `{{hole}}` is a dotted lookup. I also ran each artboard's `renderVals()` in Node, under its defaults, every enum combination and every importer's props, and resolved every hole against the result, including `<sc-for>` items.
  3. Imports and links: `dc-import` targets, props and how the child reads them; Google Fonts placement; external, `/_blob/` and `url()` references.
  4. Canvas index: board and file parity, `order`, `w`/`h` against `$preview`, overlaps and note placement.
  5. Tokens: `tokens.json` grammar, WCAG contrast and README claims.
  6. Data fidelity: 78 fingerprints per direction, plus a sweep for timestamps, ids and millisecond values that are not in the dataset.
  7. Accessibility and hygiene: ARIA patterns and hygiene grep.
- **Runtime behaviour:** I read the published `artifact-type/dc-runtime.js` to settle one question. It does handle bare boolean attributes: `checked` becomes `defaultChecked` and `disabled` becomes `true`.

## Summary

| Direction | Artboards | Render risks found | Major | Minor | Verdict |
| --- | --- | --- | --- | --- | --- |
| Timetable | 31 / 31 published, all listed | none confirmed; 1 unverified (CSS-mask glyphs) | 0 | 8 | **READY FOR PO VIEWING** |
| Crash Lab | 31 / 31 published, all listed | none confirmed; 1 clipping risk (mobile capture) | 0 | 8 | **READY FOR PO VIEWING** |
| Notation | 29 / 29 published, all listed | none confirmed | 1 (spec, not render) | 7 | **READY FOR PO VIEWING** |

**No Blockers.** Every `.dc.html` file:

- has the exact `<script src="./support.js"></script>` head line, one `<x-dc>` root and a `data-dc-script` block with `class Component extends DCLogic`;
- has well-formed markup with every attribute quoted, and no self-closed non-void tags;
- puts the Google Fonts `css2` `<link>` only in `<helmet>`;
- loads only `ds/<folder>/tokens.css` and `components/bundle.css` in `<head>`;
- has no external, `/_blob/` or `url()` references (all three canvases have 0 assets and need none);
- resolves every hole: only simple lookups, and all of them resolve in executed `renderVals()`.

All `dc-import` targets exist, and every passed prop is declared and read by the child. Every `canvas.json` is valid: boards and files are 1:1, `order` matches, `Main.dc.html` is first, there are no overlaps, `w`/`h` are within 40–8000, and there is one `designSystems` record. Every `tokens.json` is in list shape with `light` and `dark` themes, unique names, and no DTCG maps, named colours or `var()`; aliases are `{token}` form only. Data shows **F2 = High** everywhere, and **Critical only on F1/D10 (tampered)**. Every Critical occurrence was checked.

### How the light and dark imports work

| Direction | Mechanism | Static result |
| --- | --- | --- |
| Crash Lab (light frames import dark defaults) | `<dc-import name="Main" theme="light">`. Each child declares a `theme` enum (default `dark`), reads `this.props.theme` and sets `data-theme="{{theme}}"` on its `.cl-root`. | Correct. The filmstrip and landing demo strip carry a hard `data-theme="dark"` on purpose ("film stock is dark", per `Filmstrip/README.md`), so the light frames show one dark band by design. |
| Timetable (dark frames import light defaults) | `theme="dark"` passed down up to 3 levels (`CaptureDesktopDark` → `CaptureDesktop` → `DetailPane`, `RunDark` → `Main` → `DeliveryGraph`). Every child sets `data-theme="{{theme}}"` on `.tt`. | Correct at every level. All prop combinations execute. |
| Notation (dark frames import light defaults) | No props. The wrapper is `<div data-theme="dark" class="nt">` and the child has no `data-theme`, so the dark custom properties inherit and `[data-theme="dark"] .nt` switches `color-scheme`. | Correct, provided `dc-import` mounts in the same DOM. The runtime suggests it does. |

---

## Timetable

System https://claude.ai/artifact/7n63VUDFZC31A1bRTJfwNY · Canvas https://claude.ai/artifact/3ovs7azTxTzGFREDuBe6En

### Issues

| # | Sev | File | Evidence | Suggested fix |
| --- | --- | --- | --- | --- |
| T1 | Minor (unverified render risk) | `ds/timetable/components/bundle.css` l.239–274 | Every glyph, including the severity shapes (`tt-g-crit` square, `tt-g-high` triangle), is a CSS `mask-image` from a `data:image/svg+xml` URI. If the canvas sandbox blocks `data:` images, every glyph disappears. The severity words stay, so this is a channel loss, not a failure. | Check on first view. If glyphs are missing, inline the shapes as `<svg aria-hidden="true">`. |
| T2 | Minor | `LandingDesktop`, `RunDraft`, `Main` (scenario lists, budget axis) | Visible copy reads "Measures response time against Stripe's budget (10 000 ms, VERIFY against pack data)" and "10 000 ms · Stripe pack, VERIFY". It is verbatim from the dataset `scenarios[slow].what`, but reads as an internal note on public landing copy. The other two directions dropped it. | Drop the "VERIFY…" parenthetical on the landing page; keep it as a sticky note on the canvas. |
| T3 | Minor | `canvas.json` `designSystems[0].version` | `1794393931-d40e`, but the system's live version is `1794393934-e5f9`. The copied `tokens.json` and `bundle.css` bytes are identical, so this is cosmetic, but the Theme menu may show the copy as one version behind. | Re-run the install step at the current version. |
| T4 | Minor (ARIA) | `CaptureDesktop`, `CaptureMobile` | `role="treegrid"` with `role="row"` children but no `gridcell`s. `CaptureMobile` has `role="tab"` without a `tabpanel`. | Add `role="gridcell"` to row cells and `role="tabpanel"` plus `aria-labelledby`. |
| T5 | Minor (data) | all | Agent version `0.9.2` is not shown anywhere. The connected-pill popover from §5.3 is not drawn. | Optional: show it in the agent pill title or popover. |
| T6 | Minor | `components/AgentPill/preview.html` | The "target unreachable" and "update needed" pills reuse the **High** and **Medium** severity glyphs (`tt-g-high`, `tt-g-med`), so a severity shape appears on a non-finding. | Use a neutral warning glyph for agent states. |
| T7 | Minor | `components/bundle.css` l.1 | `@import` of the same Google Fonts `css2` URL that `<helmet>` already loads. Allowed host, but the fonts load twice. | Remove the `@import` from the canvas copy, or accept it. |
| T8 | Minor (hygiene) | `project/design-system.json` (system) | `lastChange: {"by":"Claude","via":"Claude Code"}`. The system page may show this as "last change by Claude". The brief says no AI attribution. | Set `by` to the PO or team name. |

### Coverage

| Screen | Required | Present (file) |
| --- | --- | --- |
| Landing | desktop light + dark, mobile light + dark | `LandingDesktop`, `LandingDesktopDark`, `LandingMobile`, `LandingMobileDark` ✓ |
| Capture | receiving #01 light + dark · waiting (guest) · quota · mobile receiving light + dark · mobile waiting | `CaptureDesktop`, `CaptureDesktopDark`, `CaptureWaiting`, `CaptureQuota`, `CaptureMobile`, `CaptureMobileDark`, `CaptureMobileWaiting` ✓ |
| Delivery detail | #01 Verdict light + dark · #01 Raw · #09 · #08 · mobile #09 · mobile #01 Raw | `DetailPage`, `DetailPageDark`, `DetailPageRaw`, `DetailPage09`, `DetailPage08`, `MobileDetail` (d=09), `MobileDetailRaw` ✓ |
| Run | findings F2/D3 vs D2 light + dark · running D9/14 · draft (no agent) · passed #4 · aborted #2 · inconclusive #1 · mobile report light + dark · D3 sheet | `Main`, `RunDark`, `RunRunning`, `RunDraft`, `RunPassed`, `RunAborted`, `RunInconclusive`, `RunMobile`, `RunMobileDark`, `RunMobileInspector` ✓ |
| States sheet | loading (capture, detail, run) · errors (unreachable, agent lost, guest expired) · empty | `StatesSheet`: all three loading skeletons, "connection refused", "Agent disconnected", "This guest URL expired", "No deliveries match", "No findings" ✓ |
| Extra | — | `DeliveryGraph`, `DetailPane` (component boards) |
| System previews | severity, signature chip, agent pill, run status, live indicator, buttons, signature timeline, cover | all present (+ Logo, Icons, PathStrip). 4 logos and 10 icons uploaded; all blob ids resolve. |

**Missing: none.**

### Data fidelity (spot-check, 75 of 78 fingerprints present)

| Value | Dataset | Found |
| --- | --- | --- |
| Capture URL | `Br7nDLk2Qm9x` | ✓ |
| Guest URL | `k7Qm2xVb9TfL` | ✓ |
| #01 received | `13:58:04.212`, `2 294 B` | ✓ |
| Group | `sub_1QH…4drI`, "Jonah Reyes · Monthly whole bean · renewal", `1.2 s` | ✓ |
| #01 ids | `evt_1QK9BpyOmOFtoVkdOlN4pjPU`, `dlv_01M75EDHNMT8Z3WPK3CGQ054WW` | ✓ |
| #09 reason | "Signed with a different secret", `sha256=5c1e09` vs `sha256=4d2f4b`, hook `512839104`, "7 of 7 checks", `…7c4d` | ✓ |
| #08 | `trap secrets set parcelbird`, `PARCELBIRD_WEBHOOK_SECRET` | ✓ |
| D3 | `14:09:04.068`, `+1 950 ms`, `196 ms`, `orders.count 1 → 2` | ✓ |
| Latencies | D8 `11 840 ms`, D14 `10 420 ms`, D10 `167 ms`, burst `312–601 ms` | ✓ |
| Run | `41.0 s`, `14:09:02.118 → 14:09:43.078`, `run_01M75F1M56RSH0CVKCTFX4FQWC` | ✓ |
| Running / aborted | "D9 of 14 · 22.7 s" / `13:57:40.912`, "D3 of 14" | ✓ |
| Pairing | `HXR-4K7`, `9:41` (code expiry, not a status bar) | ✓ |
| Severities | F1 Critical, F2 High, F3 Medium, F4 Info | ✓ 18 pairings, 0 wrong |
| Invented values | timestamps, ids, ms | none. The only ms values not in the dataset are 300 / 500 ms (the spec's 300 ms checking threshold and a UI delay). |

Absent: `0.9.2`, the #4 run id, and the exact string "1 Critical · 1 High · 1 Medium · 1 Info" (the counts appear as separate chips).

### Contrast (recomputed from `tokens.json`; README claims match to 2 decimals)

| Pair (fg on bg) | Min | Light | Dark |
| --- | --- | --- | --- |
| ink on ground / surface | 4.5 | 16.08 / 14.97 | 15.04 / 13.75 |
| ink-muted on ground / surface | 4.5 | 6.35 / 5.91 | 7.47 / 6.83 |
| conflict (= sev-critical) on ground / surface | 4.5 | 5.46 / 5.08 | 5.93 / 5.42 |
| now on ground | 4.5 | 5.75 | 6.87 |
| pass on ground / surface | 4.5 | 5.30 / 4.93 | 8.46 / 7.73 |
| sev-high on ground / surface | 4.5 | 5.39 / 5.02 | 7.99 / 7.30 |
| sev-medium on ground / surface | 4.5 | 5.20 / 4.84 | 10.50 / 9.60 |
| sev-info on ground / surface | 4.5 | 5.59 / 5.20 | 8.34 / 7.62 |
| on-ink on ink (primary button) | 4.5 | 16.08 | 15.04 |
| on-conflict on conflict | 4.5 | 5.46 | 5.93 |
| ink-muted on allowance-fill | 4.5 | 4.74 | 4.71 |
| rule-strong on ground / surface (non-text) | 3 | 3.49 / 3.25 | 3.94 / 3.60 |
| allowance-edge on allowance-fill (non-text) | 3 | 3.89 | 6.62 |
| focus-ring on ground | 3 | 16.08 | 15.04 |
| conflict vs sev-high (must not be told apart by colour) | — | 1.01 | 1.35 |

All pairs pass. Critical and High are indistinguishable by colour, so this direction depends on its square and triangle glyphs (see T1).

**Verdict: READY FOR PO VIEWING.** The one thing to watch on first open: the severity and icon glyphs (T1).

---

## Crash Lab

System https://claude.ai/artifact/NZYTD3LgZ8eBeDmQMFQTJo · Canvas https://claude.ai/artifact/EBrZR6RqBwwg18w45P7c2J

### Issues

| # | Sev | File | Evidence | Suggested fix |
| --- | --- | --- | --- | --- |
| C1 | Minor (clipping risk) | `CaptureMobile.dc.html` (and its `CaptureMobileLight` import) | The root is `width: 390px; height: 1000px; overflow: hidden`. Any receiving-list content past 1000 px is cut silently. Every other mobile root uses `min-height`. | Use `min-height: 1000px`, drop `overflow: hidden`, and raise the board `h` if needed. |
| C2 | Minor (format) | `Landing`, `LandingMobile` | Demo frames show `t+1.450 s` / `t+1.950 s`. §6 asks for the relative-run form `+1 450 ms`. The run screens label deliveries `D02` and `D03` (zero-padded) where the dataset and copy use `D2` and `D3`. Both forms appear on the same screens. | Use `+1 450 ms` and `D2`/`D3`, or note the padding as a deliberate territory choice. |
| C3 | Minor (a11y) | `Landing.dc.html` (2×), `LandingMobile.dc.html` (2×) | Decorative Once/Twice glyph `<svg>` elements in the demo frames have neither `aria-hidden` nor `role="img"`. | Add `aria-hidden="true"`. |
| C4 | Minor (ARIA) | `CaptureReceiving`, `Detail08NoSecret`, `Detail09Invalid`, `DetailMobile01Raw`, `DetailMobile09`, `StatesLoading` | A `treegrid` with no `gridcell`s. `role="tab"` without a `tabpanel` in 4 detail files. `aria-selected` with no selectable role in `StatesLoading`. | Complete the ARIA patterns. |
| C5 | Minor | `canvas.json` `RunDraft` title | "Run #4 · Draft · agent not installed" and "Run #4 · Passed" share a run number. | Retitle the draft (for example "Run · Draft"). |
| C6 | Minor (brief) | system Icons | 13 icons against the brief's 8–10. | Accept, or trim. |
| C7 | Minor (data) | all | `0.9.2` and `PARCELBIRD_WEBHOOK_SECRET` (#08 fix line) are not shown. The #08 detail shows the command `trap secrets set parcelbird` but not the secret name from the dataset `fix`. | Add the secret name to the #08 fix sentence. |
| C8 | Minor (hygiene) | `project/design-system.json` (system) | `lastChange.by: "Claude"`, `via: "Claude Code"`. | As T8. |

### Coverage

| Screen | Required | Present (file) |
| --- | --- | --- |
| Landing | desktop light + dark, mobile light + dark | `Landing` (dark), `LandingLight`, `LandingMobile` (dark), `LandingMobileLight` ✓ |
| Capture | receiving #01 light + dark · waiting · quota · mobile light + dark · mobile waiting | `CaptureReceiving`, `CaptureReceivingLight`, `CaptureWaiting`, `CaptureQuota`, `CaptureMobile`, `CaptureMobileLight`, `CaptureMobileWaiting` ✓ |
| Delivery detail | #01 Verdict light + dark · #01 Raw · #09 · #08 · mobile #09 · mobile Raw | `Detail01Verdict`, `Detail01VerdictLight`, `Detail01Raw`, `Detail09Invalid`, `Detail08NoSecret`, `DetailMobile09`, `DetailMobile01Raw` ✓ |
| Run | findings light + dark · running · draft · passed · aborted · inconclusive · mobile report light + dark · D3 sheet | `Main` (dark), `RunFindingsLight`, `RunRunning`, `RunDraft`, `RunPassed`, `RunAborted`, `RunInconclusive`, `RunMobileReport`, `RunMobileReportLight`, `RunMobileD3` ✓ |
| States | loading · errors · empty | `StatesLoading` (capture, detail, run), `StatesErrors` (unreachable ×2, agent lost, start failed, stream lost, server, offline, guest expired), `StatesEmpty` ✓ |
| System previews | 8 required sets | all present (+ DeformationGauge, RigCell, ToleranceRuler). 6 logos and 13 icons uploaded; all blob ids resolve. |

**Missing: none.**

### Data fidelity (74 of 78 present)

| Value | Found |
| --- | --- |
| `Br7nDLk2Qm9x`, `k7Qm2xVb9TfL` | ✓ |
| `13:58:04.212`, `2 294 B`, `sub_1QH…4drI`, Jonah Reyes subtitle | ✓ |
| `evt_1QK9BpyOmOFtoVkdOlN4pjPU`, #01, #08 and #09 `dlv_` ids | ✓ |
| #09 "Signed with a different secret", `5c1e09` / `4d2f4b`, `512839104`, "7 of 7 checks" | ✓ |
| D3 `14:09:04.068`, `+1 950 ms`, `196 ms`; D2 `212 ms`; D8 `11 840 ms`; D14 `10 420 ms` | ✓ |
| `orders.count 1 → 2`, `10 000 ms`, `41.0 s`, "D9 of 14", `22.7 s` | ✓ |
| `13:57:40.912`, "D3 of 14", `23 h 49 m`, "100 of 100" | ✓ |
| Verdict aria "4 findings, 1 Critical, 1 High, 1 Medium, 1 Info" | ✓ |
| Severities | ✓ 14 pairings, 0 wrong. Critical appears only on F1 / D10 / Tampered. |
| Invented values | none. 1 204 / 611 / 183 ms are gaps derived from #01–#04 arrival times, which is correct arithmetic. |

### Contrast (README claims match; test-yellow on light ground is 1.24 against the README's 1.23, a rounding difference)

| Pair | Min | Light | Dark |
| --- | --- | --- | --- |
| ink on ground / surface / surface-2 | 4.5 | 16.08 / 17.86 / 14.65 | 16.74 / 15.30 / 13.56 |
| ink-muted on ground / surface-2 | 4.5 | 6.78 / 6.17 | 7.86 / 6.37 |
| pass on ground / surface-2 | 4.5 | 5.16 / 4.70 | 9.25 / 7.49 |
| sev-critical on ground / surface-2 | 4.5 | 5.57 / 5.07 | 5.66 / **4.58** |
| sev-high on ground / surface-2 | 4.5 | 5.21 / 4.75 | 7.64 / 6.18 |
| sev-medium on ground / surface-2 | 4.5 | 5.31 / 4.84 | 13.15 / 10.65 |
| sev-info on ground | 4.5 | 5.71 | 7.86 |
| armed on ground / surface-2 | 4.5 | 5.32 / 4.85 | 10.45 / 8.46 |
| on-yellow on test-yellow (plates) | 4.5 | 12.97 | 12.97 |
| on-critical on sev-critical | 4.5 | 6.18 | 5.66 |
| on-pass on pass | 4.5 | 5.73 | 9.25 |
| rule-strong on ground / surface-2 (non-text) | 3 | 3.66 / 3.33 | 4.08 / 3.31 |
| focus on ground | 3 | 16.08 | 16.74 |
| test-yellow on ground (non-text) | 3 | **1.24 fail** (documented: fill only, always edged with on-yellow) | 13.15 |
| ink on test-yellow | 4.5 | 12.97 | **1.27 fail**. Not used: `.cl-plate` forces `on-yellow`, and no inline yellow background holds text. |

All text pairs in use pass. Critical on surface-2 in dark is the tightest pair at 4.58.

**Verdict: READY FOR PO VIEWING.** Watch the mobile capture frame for clipping (C1).

---

## Notation

System https://claude.ai/artifact/HNKgCdYHshzD5N7xieZhnf · Canvas https://claude.ai/artifact/XXvbGdE7vo62VpDGXWUfqn

### Issues

| # | Sev | File | Evidence | Suggested fix |
| --- | --- | --- | --- | --- |
| N1 | **Major** (spec, not render) | `Main`, `Run-dark`, `Run-mobile-inspector`, `Run-running`, `Capture` | Chess terms stand alone, against §7.10 and §4 (Run): <ul><li>The compare toggle reads "Compare with 2." instead of "Compare with D2".</li><li>The mobile inspector sheet never says D2 or D3; it uses "The move 3.".</li><li>Dataset copy is altered to "Responses to 2. and 3. are identical" (dataset: "D2 and D3").</li><li>The running progress line reads "move 9 of 14" (dataset: "D9 of 14").</li><li>The capture header reads "6 games" (dataset: "6 groups").</li></ul>The desktop table keeps "3. D3", so the mapping exists but is not on first view. | Keep the move number as helper text next to the plain label ("D3 · move 3.", "Compare with D2", "6 groups"), and restore the dataset sentence verbatim. |
| N2 | Minor | `Delivery-01-raw.dc.html` | `$preview` is 1440×2000 but the board is 1440×2150. | Align `$preview.height` to 2150. |
| N3 | Minor (ARIA) | `Main.dc.html`, `Capture.dc.html` | `aria-selected` on a `<tr>` without a grid or treegrid role. The capture list is a `nav` of groups, not the `treegrid` §4 asks for. | Use `role="grid"` on the table and a treegrid list, or drop `aria-selected` for `aria-current`. |
| N4 | Minor (responsive) | `States`, `Landing` | 7 inline `repeat(N, minmax(0, 1fr))` grids in fill pages keep N columns at phone width in the full-window view (`craft.md`). | Use `repeat(auto-fit, minmax(min(<px>, 100%), 1fr))`. |
| N5 | Minor (data) | all | `0.9.2`, `PARCELBIRD_WEBHOOK_SECRET` and the burst range `312–601 ms` are not shown. | Optional. |
| N6 | Minor | `components/bundle.css` l.2 | Duplicate Google Fonts `@import`, as T7. | As T7. |
| N7 | Minor (hygiene) | `project/design-system.json` (system) | `lastChange.by: "Claude"`, `via: "Claude Code"`. | As T8. |

✓ and ✗ appear as signature glyphs (U+2713, U+2717). They are text-presentation dingbats, not emoji: acceptable.

### Coverage

| Screen | Required | Present (file) |
| --- | --- | --- |
| Landing | desktop light + dark, mobile light + dark | `Landing`, `Landing-dark`, `Landing-mobile`, `Landing-mobile-dark` ✓ |
| Capture | receiving light + dark · waiting · quota · mobile light + dark · mobile waiting | `Capture`, `Capture-dark`, `Capture-waiting`, `Capture-quota`, `Capture-mobile`, `Capture-mobile-dark`, `Capture-mobile-waiting` ✓ |
| Delivery detail | #01 Verdict light + dark · Raw · #09 · #08 · mobile #09 · mobile Raw | `Delivery-01-verdict`, `Delivery-01-verdict-dark`, `Delivery-01-raw`, `Delivery-09-invalid`, `Delivery-08-nosecret`, `Delivery-mobile-09`, `Delivery-mobile-01-raw` ✓ |
| Run | findings light + dark · running · draft · passed · aborted · inconclusive · mobile report light + dark · D3 sheet | `Main`, `Run-dark`, `Run-running`, `Run-draft`, `Run-passed`, `Run-aborted`, `Run-inconclusive`, `Run-mobile`, `Run-mobile-dark`, `Run-mobile-inspector` ✓ |
| States | loading (capture, detail, run) · errors · empty | `States`: three loading blocks, "connection refused", "Agent disconnected", "This guest URL expired", "No deliveries match", "No findings" ✓ |
| System previews | 8 required sets | all present (LiveClock = live indicator, MoveList = signature timeline, + PositionPanel). 3 logos and 10 icons uploaded; all blob ids resolve. |

**Missing: none.** One state is weak: the mobile D3 sheet has no "D2" or "D3" labels (N1).

### Data fidelity (71 of 78 present)

| Value | Found |
| --- | --- |
| `Br7nDLk2Qm9x`, `k7Qm2xVb9TfL`, `13:58:04.212`, `2 294 B` | ✓ |
| `sub_1QH…4drI`, Jonah Reyes subtitle, `1.2 s`; Order #1042 "Ana Mbeki · $37.00 · shipped", "5 min 34 s" | ✓ |
| `evt_1QK9BpyOmOFtoVkdOlN4pjPU`, #01, #08 and #09 `dlv_` ids | ✓ |
| #09 reason, `5c1e09` / `4d2f4b`, `512839104`, "7 of 7 checks" | ✓ |
| D3 `14:09:04.068`, `+1 950 ms`, `196 ms` vs D2 `212 ms`, `{"received":true}` | ✓ |
| `11 840 ms`, `10 420 ms`, `167 ms`, `41.0 s`, `14:09:43.078` | ✓ |
| `13:57:40.912`, "D3 of 14", `23 h 49 m`, "100/100" | ✓ |
| F2 JSON on the landing page (severity "high", evidence D2 D3 D8 D9) | ✓ |
| Severities | ✓ 12 pairings, 0 wrong |
| Altered copy | see N1 ("2. and 3.", "move 9 of 14", "games") |
| Invented values | none |

### Contrast (README claims match exactly)

| Pair | Min | Light | Dark |
| --- | --- | --- | --- |
| ink on ground / surface | 4.5 | 15.66 / 14.48 | 14.71 / 13.30 |
| ink-muted on ground / surface | 4.5 | 6.35 / 5.88 | 8.13 / 7.35 |
| pencil (= sev-critical) on ground / surface | 4.5 | 5.53 / 5.12 | 6.22 / 5.63 |
| sev-high on ground / surface | 4.5 | 5.08 / 4.70 | 8.02 / 7.26 |
| sev-medium on ground / surface | 4.5 | 5.01 / 4.64 | 9.98 / 9.03 |
| sev-info on ground / surface | 4.5 | 5.70 / 5.27 | 8.46 / 7.65 |
| pass on ground / surface | 4.5 | 5.92 / 5.48 | 9.07 / 8.21 |
| clock on ground / surface | 4.5 | 5.25 / 4.86 | 8.94 / 8.09 |
| on-board on board | 4.5 | 5.25 | 6.66 |
| on-primary on primary | 4.5 | 5.25 | 8.05 |
| rule-strong on ground / surface (non-text) | 3 | 4.17 / 3.86 | 4.23 / 3.82 |
| focus on ground / surface | 3 | 5.25 / 4.86 | 8.94 / 8.09 |
| board on ground (non-text) | 3 | 5.25 | **2.21**. Mitigated: the dark `.nt-pos` border switches to `rule-strong` (4.23), as the README claims. |
| ink-muted on board | 4.5 | **1.21 fail** | **3.68 fail**. Not used: board text is `on-board`, and severity colours inside the panel sit on `.nt-slip` (ground). |

**Verdict: READY FOR PO VIEWING.** The PO should judge N1: it changes the plain vocabulary on the headline frames.

---

## Cross-direction notes

- **Hygiene sweep** (all `.dc.html`, READMEs, CSS, SVG): no 1.x, `DESIGN.md` or terracotta references, no PulseSend, no lorem, no emoji. No AI attribution in any visible copy; the only instance is the `lastChange` metadata in the three `design-system.json` files. No gradient washes or glassmorphism. Crash Lab uses `repeating-linear-gradient` for hazard and hatch patterns, which are patterns, not washes. No left-border accent cards, and no fake status bars (`9:41` is always the pairing-code expiry).
- **Accessibility:** no icon-only `<button>` or `<a>` lacks a label, and no clickable `div` or `span`. Every input has a label or `aria-label`. Each frame has one `h1` (Timetable's 5 in `Main` sit in mutually exclusive `sc-if` branches). Every bundle has a 2 px `:focus-visible` ring, and none removes outlines. Crash Lab's only infinite animation, `cl-pulse` (about 1 Hz), has a `prefers-reduced-motion` override.
- **Severity never shows by colour alone:** Timetable pairs a word with a shape glyph, Crash Lab a word with a hatch swatch, and Notation a word with an annotation glyph (`??`, `?`, `?!`, `!?`). Dense rows carry an `aria-label` with the full word.
- **Unverifiable statically:**
  - real page heights against each board `h` (clipping or empty tails);
  - Google Fonts axis ranges in the `css2` URLs (all nine families exist in the type's font list, and the requested ranges match the published axes as far as I know);
  - `data:` CSS masks (T1);
  - how the canvas renders `dc-import` nested three levels deep (Timetable).
