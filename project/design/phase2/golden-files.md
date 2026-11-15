# Golden files · CLI, CI and PR output

**Status:** REVIEW_REQUIRED · **Author:** A03 (UX/UI) · **Date:** 2026-11-14, revised for decision 35 and for decisions 38–39 · **Phase:** 2
**Resolves:** A-23 (golden-file details) from A11's `testability-review.md` (TR).
**Source format:** TT §1.4 (`ux-timetable-v2.md`, the Run record) for layout, and the dataset (`realistic-data.md`, Run #1–#4, regenerated for decision 35) for values. **Judge, status, exit codes and names:** A01 `scenarios-and-judge.md` (SJ). The reference samples in §15 replace TT §1.4.1–1.4.6's samples (SJ §16.17–16.19); TT §1.4 keeps its rules table.
**Engine constraints this relies on:** TR §4 D-2 (injected clock), D-3 (seeded randomness), D-11 (pure renderer over a Verdict + terminal profile). A05 records them in the agent ADR.
**Read with:** `accessibility-spec.md` §8.3 (non-web output rules), `information-architecture.md` §8 (cross-surface consistency).

A golden file is the exact expected output of the text renderer for one Verdict under one terminal profile. Golden tests compare bytes. This file sets what the bytes must obey, how they're produced and who may change them.

---

## 1. Principles

1. **Render, don't post-process.** Every profile (Unicode or ASCII, colour or not, wide or narrow) is rendered from the Verdict by the same layout code with that profile's glyph table and width. No profile is produced by search-and-replace on another profile's text. (This is the root of A-23 item 1, §14.)
2. **Determinism by injection, not by scrubbing.** Goldens come from a fixed Verdict, a fixed clock and a fixed seed, so they contain real-looking values (`run_01M75F1M56RSH0CVKCTFX4FQWC`, `+25 191`) and need no normalisation. Normalisers exist only for the small real-time suite (§6.3).
3. **The spec shows goldens, not hand-typed samples.** After the first goldens are approved, TT §1.4's code blocks are replaced by the golden files (or generated from them). The spec can't drift from the product again (A11 V4).
4. **One string table.** CLI, CI and PR text come from the same strings as the UI (IA §8.2 rule 4), so a copy fix changes goldens and screens together.

---

## 2. Terminal profiles and file layout

### 2.1 Profile dimensions

| Dimension | Values | Selected by |
| --------- | ------ | ----------- |
| **Charset** | `unicode` · `ascii` | `ascii` when `--ascii`, `TERM=dumb`, the locale charset isn't UTF-8, or the locale is CJK (`ja`, `ko`, `zh`, `zh_*`) (TT §1.4) |
| **Colour** | `none` · `ansi16` | `none` when `NO_COLOR` is set (any value), stdout isn't a TTY, or `--color=never`; `ansi16` when a TTY or `--color=always`. `--color=always` wins over `NO_COLOR` |
| **Mode** | `tty-live` · `append` | `tty-live` when stdout is a TTY and `--progress` isn't `plain`; else `append` (non-TTY, CI, `--progress=plain` or `WEBHOOKTRAP_PROGRESS=plain`, PROPOSED in Q13) |
| **Width** | `100` · `80` | §5.1 |
| **CI dialect** | `none` · `github` | `github` when `--ci github` or `GITHUB_ACTIONS=true` |
| **Format** | `text` · `markdown` · `json` | `--format`, `--json` |

### 2.2 Required profile set per case

Every text case (§11) is rendered in these profiles. Others are covered by unit tests of the profile selector, not goldens.

| Profile id | Charset | Colour | Mode | Width | Dialect |
| ---------- | ------- | ------ | ---- | ----- | ------- |
| `u-plain-100` | unicode | none | append | 100 | none |
| `u-ansi-100` | unicode | ansi16 | append | 100 | none |
| `a-plain-100` | ascii | none | append | 100 | none |
| `u-plain-80` | unicode | none | append | 80 | none |
| `u-gh-100` | unicode | none | append | 100 | github |
| `u-live-100` | unicode | ansi16 | tty-live | 100 | none (final screen, §9) |

Markdown and JSON have one profile each.

### 2.3 Layout

```
golden/
  manifest.json                   one entry per file: case, profile, status (provisional | approved), approved_by, reason
  text/<case>/<profile>.txt       exact bytes (no ANSI except u-ansi-100 and u-live-100)
  text/<case>/u-ansi-100.txt      raw bytes with ESC sequences
  text/<case>/u-ansi-100.visible  same, ESC shown as ␛ (generated, for review diffs only, never compared)
  text/<case>/u-live-100.screen   final screen text from a headless terminal
  text/<case>/u-live-100.trace    control-sequence inventory and redraw timestamps (§9)
  markdown/<case>.md
  json/<case>.json                canonical JSON (§10)
```

The exact path inside the CLI repo is A05's choice. File names use the case ids of §11.

---

## 3. Character set and ASCII fallback

### 3.1 Unicode profile: allowed characters

Only 7-bit ASCII plus this list may appear in a `unicode` golden. A lint fails on anything else (emoji, NBSP, thin space, box-drawing beyond `┃`, smart quotes).

| Char | Name | Use | ASCII map |
| ---- | ---- | --- | --------- |
| `▬` | U+25AC | pass | `[-]` |
| `■` | U+25A0 | Critical | `[C]` |
| `▲` | U+25B2 | High | `[H]` |
| `△` | U+25B3 | Medium | `[M]` |
| `○` | U+25CB | Info | `[I]` |
| `┃` | U+2503 | running / now | `\|` |
| `⫽` | U+2AFD | stopped / not run | `//` |
| `→` | U+2192 | before → after, sequence | `->` |
| `·` | U+00B7 | separator | `\|` |
| `×` | U+00D7 | count (`200 × 3`) | `x` |
| `–` | U+2013 | range (`312–601`) | `-` |
| `−` | U+2212 | minus (`t − 360 s`) | `-` |
| `′` | U+2032 | prime: duplicate of (`D2′`) | `'` |
| `›` | U+203A | citation path | `>` |
| `…` | U+2026 | truncation, pending | `...` |

All of these are East Asian Width *Neutral* or *Ambiguous*. **Display width is computed with ambiguous = 1 column.** That is only safe because CJK locales switch to ASCII (TT §1.4). The lint checks it: every `unicode` golden line's width with ambiguous = 1 must equal its width under the renderer's own function.

**`D2′` (A-23 item 2):** stays `D2′` in Unicode and becomes `D2'` in ASCII. Width is 1 in both, so columns don't move.

### 3.2 ASCII profile

- Bytes 0x0A and 0x20–0x7E only (plus ESC in `ansi` profiles, which aren't required for ASCII).
- Glyph mappings change cell widths (`[C]` is 3 columns where `■` is 1; `->` is 2 where `→` is 1). **Column widths are computed after mapping, per profile** (principle 1).

Consequences for TT §1.4.3:

| Element | Unicode | ASCII | Rule |
| ------- | ------- | ----- | ---- |
| `state` column | 5 wide (`0 → 1`) | 6 wide (`0 -> 1`) | Width = widest cell in that profile. The extra space in the ASCII header (`state   result`) is **correct**, not a typo |
| `result` column | starts after state | starts one column later | follows from the above |
| Finding header right edge (evidence `D12`, `D4 D5 · D10 D11`) | ends at column 96 | **must also end at column 96** | Right-aligned elements align to the wrap width (§5.2) in every profile. TT §1.4.3 currently ends them at column 98 (`[C]` replaced `■` and kept the padding): that sample is wrong and the golden fixes it |
| Summary scenario lines (`[H] Twice  F2 High  …`) | glyph 1 + space | glyph 3 + space | name column starts after the glyph's width; names stay aligned among themselves |

---

## 4. Colour

| Rule | Detail |
| ---- | ------ |
| Palette | 16-colour SGR only: `0` reset, `1` bold, `2` dim, `22` normal intensity, `31` red, `32` green, `33` yellow, `34` blue, `35` magenta, `39` default foreground. No 256-colour, truecolour, background, underline, blink or italics |
| What is coloured | The glyph and the severity word only (Critical `1;31`, High `31`, Medium `33`, Info `34`, pass `32`, `Running` `35`), plus dim (`2`) for secondary text (TT §1.4). Nothing else |
| Reset | Every coloured span ends with `0` or the matching off code **on the same line**. No colour state crosses a newline |
| Same text | Stripping every SGR sequence from `u-ansi-100` must give exactly `u-plain-100` (lint). Colour adds nothing, removes nothing |
| `NO_COLOR` | Any value disables colour (no-color.org). `--color=always` overrides it |
| Review | `.visible` files show `␛[31m▲ F2 High␛[0m` so reviewers can read colour diffs |

---

## 5. Width and wrapping (A-23 item 3)

### 5.1 Width selection

| Situation | Width W |
| --------- | ------- |
| Non-TTY (CI, pipe, file) | 100 |
| TTY with ≥ 100 columns | 100 |
| TTY with 80–99 columns | the terminal's width, narrow layout (§5.3) |
| TTY with < 80 columns | 80 (the terminal wraps; not supported for reading) |
| `--width N` (PROPOSED) | clamp N to 80–100 |

Goldens are taken at 100 and 80. Widths in between are covered by the layout unit tests.

### 5.2 Line rules

| Line kind | Max | Rule |
| --------- | --- | ---- |
| Prose (finding beats, helper text, header values) | W − 4 (96 at 100) | Wrapped on spaces; never inside a token (id, number with its unit, `D3`, `orders.count`, `1 → 2`). Hanging indent for beats: 18 columns (TT §1.4) |
| Table rows (deliveries, scenario summary) | W | Never wrapped. Cells are truncated as §5.3 says, never the row |
| Right-aligned element on a header line (finding evidence) | ends at W − 4 | If it doesn't fit after the title, it moves to its own line, right-aligned |
| Unbreakable token longer than the space left (URL, path, command) | **exempt** | Printed whole on its own line at the line's indent. Not hyphenated or split |
| GitHub workflow commands (`::error …`, `::warning …`, `::notice …`, `::group::…`) | **exempt** | One line each, any length (§8). CI consumes them; people read the summary above |
| Markdown | **exempt** | Paragraphs are single lines (GitHub soft-wraps); code blocks keep their source width (§7) |

**Lint:** in every `text` golden, every line not in an exempt class is ≤ W display columns; lines that contain prose are ≤ W − 4. No trailing spaces, no tab characters, `\n` line endings only, file ends with exactly one `\n`.

Measured on TT §1.4 today (for the record): every Unicode block is ≤ 97 columns; prose lines ≤ 96; the only lines over 100 are the four `::` workflow commands (max 202) and Markdown paragraphs and code (max 336). This matches the rules above once the ASCII header fix of §3.2 is applied.

### 5.3 Narrow layout (80 ≤ W < 100), PROPOSED

Applied in this order until rows fit:

1. Result cell drops its trailing detail: `▲ High, expected 1` → `▲ High`; `△ Medium, +1 840 over` → `△ Medium`. (The detail is still in the finding below.)
2. Event cell truncates in the middle with `…` (ASCII `...`), minimum 16 columns: `customer.subscr…updated`.
3. The `+ms` column keeps its place; nothing else is dropped.

The narrow golden (`u-plain-80`) is generated once these rules are implemented and approved by A03 with the first batch.

---

## 6. Determinism: timestamps, ids and numbers

### 6.1 Inputs fixed for every golden

| Input | Value | Source |
| ----- | ----- | ------ |
| Wall clock at run start | `2026-11-11T14:09:02.118Z` (Run #3; other runs per RD §4.6) | RD |
| Monotonic offsets | the RD `+ms` and latency values (RD §4.2, from its PLAN-3 timing model; preflight before D1) | RD §4.2 |
| Seed | fixed per case, recorded in the manifest | TR D-3 |
| Run id, ids | from the RD (`run_01M75F1M56RSH0CVKCTFX4FQWC`); ids the RD doesn't give come from the seeded generator | RD, TR D-3 |
| `TZ` | `UTC` | — |
| Locale | `C.UTF-8` (Unicode cases); `ja_JP.UTF-8` (CJK → ASCII case); `POSIX` (non-UTF-8 → ASCII case) | — |
| Terminal | `COLUMNS` and the TTY flag set by the harness, never read from the CI machine | — |
| Environment | `NO_COLOR`, `GITHUB_ACTIONS`, `CI`, `TERM` explicitly set or unset per profile | — |

### 6.2 Formatting rules that goldens lock

| Value | Format | Example |
| ----- | ------ | ------- |
| Thousands | **plain space U+0020**, never locale-dependent, never NBSP or thin space | `11 840 ms`, `+25 191` |
| Relative time | `+` and integer ms since run start (agent accepted), right-aligned | `+1 320` |
| Duration | seconds with one decimal and a space | `43.2 s`, `25.3 s` |
| Absolute time (`--verbose` only) | ISO 8601 UTC with ms and `Z` | `2026-11-11T14:09:06.838Z` |
| Latency | integer ms | `196 ms` |
| Ids | full in the footer and URLs; never truncated in text output (truncation is a UI-only rule) | `run_01M75F1M56RSH0CVKCTFX4FQWC` |
| Budget | number + unit + source in parentheses | `10 000 ms (Webhook Trap default)` |

### 6.3 Normalisation (real-time suite only)

The tagged real-time integration suite (TR D-4) runs a real handler, so latencies and ids vary. It never compares bytes against goldens. It:

1. replaces ids with **same-width** placeholders (`run_` + 26 × `0`), so column checks still work;
2. parses delivery rows by the column positions in the header line and asserts structure: row count, D order, scenario grouping, status, verdict word, finding ids;
3. asserts latencies only against the margins of TR D-5 (never near a boundary).

---

## 7. PR comment Markdown

| Rule | Detail |
| ---- | ------ |
| Marker | Line 1 is exactly `<!-- webhooktrap:pr-comment -->` (product name per A-20). The integration finds the comment by it and edits in place |
| Structure | TT §1.4.6 order: heading → counts line → context line → scenario table → findings (Critical/High `<details open>`, Medium/Info `<details>`) → footer |
| Glyph + word | Every heading, table row and `<summary>` carries the severity word next to the glyph |
| Line breaks | Paragraphs are one source line; no trailing-double-space breaks; no hard wraps in prose. Width rules don't apply |
| Payload-derived text | Event types, field names, values and ids from captures appear **only inside code spans or code blocks**, are capped at 120 characters (`…` after), and never as raw HTML. A value containing a backtick uses a longer fence. Table cells escape `\|`. This prevents a crafted payload from injecting Markdown or HTML into the PR |
| Links | Only to the instance's own base URL. **Private upload (default, PO-C):** `Full report` → `/r/{runId}` (opens for project members only) and the footer adds "This report is private to the project. Upload with --share to give it a public link." (**NEW**). **With `--share`:** every link uses `/s/{shareToken}` (`Full report`, `/s/{shareToken}/F2`, `/D12`) and the footer line is omitted. A PR comment never links a run id to people outside the project as if it were a share link. **PROPOSED (IA E7):** evidence D-refs in each finding `<summary>` become links `[D10](…/D10)` on whichever base applies. This changes TT §1.4.6; the first approved golden records the choice |
| Not uploaded | No report link; footer says "reproduce locally with `webhooktrap test`" |
| Size limit | GitHub caps a comment body (65 536 characters; A05 to confirm). If the render would exceed 60 000 characters, apply in order: collapse every finding; drop fix code blocks (keep "Snippet: `webhooktrap show Fn`"); drop Medium and Info bodies; then list findings as one line each with a link. A golden covers the overflow case |
| Heading | `### Webhook Trap · {status label} · check failed` exactly when the exit code isn't 0, else `· check passed` (EXIT-2) |
| Determinism | Same as text goldens (§6) |

---

## 8. CI workflow commands (`github` dialect)

| Rule | Detail |
| ---- | ------ |
| When | Only with `--ci github` or `GITHUB_ACTIONS=true` (TT §1.4.5) |
| Group | Delivery rows inside `::group::Deliveries · 16` … `::endgroup::`. The lines between them are **byte-identical** to the `u-plain-100` delivery rows (TR R48) |
| Annotations | One per finding, after the summary, in finding order: Critical and High → `::error`, Medium → `::warning`, Info → `::notice` |
| Escaping | Message: `%` → `%25`, CR → `%0D`, LF → `%0A`. Properties (`title=`): also `:` → `%3A`, `,` → `%2C`. A golden case has a comma in a finding title |
| Annotation cap | GitHub limits annotations per step and type (A05 to confirm the current numbers). Above the cap, emit the first N − 1 and one "and K more findings at {severity}; see the log" |
| Colour | Never in the `github` dialect, even if forced; GitHub logs render SGR, but annotations don't |
| Last lines | `Failed: …` / `Passed …` / `Incomplete: …` line, then the upload lines (§15.6: `report`, and `share` only with `--share`, or the "Uploaded privately…" line), then the process exits with the EXIT-1 code. The token value is never printed, in any encoding (TM-60). The exit code is recorded in the manifest and asserted |

---

## 9. Live TTY output

The `tty-live` mode redraws one status line. Its golden is not a byte file:

| File | Content | Assertion |
| ---- | ------- | --------- |
| `u-live-100.screen` | The final screen text after replaying the byte stream through a headless terminal emulator (100 × 60) | Equal to `u-plain-100` (rows + summary) plus nothing else: the status line is gone at the end |
| `u-live-100.trace` | The list of control sequences used, with fake-clock timestamps | Only `CR`, `ESC[2K` (erase line) and the §4 SGR set. No cursor movement up, no alternate screen, no title setting. Redraws ≤ 4 per second of fake time. Every delivery row is printed exactly once (appended, never redrawn). **No F-number appears before the summary** (FND-6): rows and `new finding` lines carry severity and title only |
| Mid-run snapshot | Screen at "Running · D11 of 16" (§15.1) | Equal to the approved `run3-running-d11` golden |

---

## 10. JSON

- Validated by `run.schema.json` (A-24). Goldens are **canonical JSON** (RFC 8785 JCS: sorted keys, no insignificant whitespace), then pretty-printed with 2 spaces for review. Comparison is on the canonical form.
- Raw integers for ms and counts, no thousands separators. Times ISO 8601 UTC.
- Severity, status and scenario as lowercase words. No glyphs, no colour.
- Content per SJ §13. No secret, no signature header value and no body content in any JSON golden (JSON-2); bodies are referenced by SHA-256. Probe values: the **local** `--json` golden has real values; the **uploaded** golden (hosted profile, what the hosted API serves) has display values only: counts matching `^-?(0|[1-9][0-9]{0,3})$`, codes `^#[0-9a-f]{6}$` (or 12 hex after PV-4), shown values only for entries declared `opted_*` on an owner run (PV-2, PV-5). The uploaded golden has no probe definition, `database:`, reset command, stderr, salt or finding prose (PV-8, A05 TM-16), and Level 2 findings carry the executor ("judged on maya-mbp", RUN-9). A lint scans JSON goldens for the RD's secret-name value patterns, for `stdout` fields, for any value outside those grammars in uploaded goldens, and for the canary strings of A09 T1/T6 (`WTCANARY-…`, `6505551234`).

---

## 11. Case catalogue (MVP)

Every case is now **ready**: SJ closed the A-items the first version waited on. Exit codes follow EXIT-1 (3 definition → 2 incomplete → 1 findings ≥ `--fail-on` → 0); 2 wins over 1 (C-2).

| Case id | What | Surfaces | Exit |
| ------- | ---- | -------- | ---- |
| `run3-findings` | RD Run #3: 16 deliveries, 3 findings + 1 Info (§15.2) | text (all 6 profiles), markdown, json (local and uploaded), github | 1 |
| `run3-running-d11` | Run #3 live at D11 of 16 (§15.1) | live snapshot | — |
| `run3-fail-on` | Run #3 with `--fail-on critical`, `medium`, `never` | text (u-plain-100) | 1, 1, 0 |
| `run4-passed` | Run #4 Passed · 1 Info, 9 scenarios, 17 deliveries (§15.4) | text, markdown, json | 0 |
| `run2-aborted` | Run #2 Aborted (`agent_lost`) during D3 of 16: D3 "sent, no result", D4–D16 "not run" | text, json | 2 |
| `run1-inconclusive` | Run #1 Inconclusive (`needs_probe`): one event, Once + Twice | text, json; with and without `--fail-on-inconclusive` | 0 / 1 |
| `incomplete-with-findings` | Run #3's first 12 deliveries, then the agent is lost: F1 and F2 proven, Aborted | text, markdown | 2 (not 1) |
| `start-failed` | Couldn't start, `target_not_allowed` (RD `start_failed`) | text | 2 |
| `baseline-rejected` | Couldn't start, `baseline_rejected`: D1 got 401, nothing else sent | text, markdown | 2 |
| `target-unreachable-start` | Couldn't start, `target_unreachable` (ONCE-6) | text | 2 |
| `target-unreachable-isolated` | One unreachable delivery mid-run: delivery and scenario `unreachable`, run continues | text | 2 |
| `target-lost` | 3 unreachable in a row: Aborted, `target_lost` | text | 2 |
| `probe-unstable` / `reset-failed-start` / `reset-failed-later` / `probe-failed` | Probe and reset errors (PRB-5, PRB-9, PRB-10) | text | 2 / 2 / 2 / 0 (Inconclusive parts) |
| `needs-secret` | Couldn't start, `needs_secret` (Stripe without STRIPE_WEBHOOK_SECRET) | text | 2 |
| `user-abort` | Ctrl-C during D7 (EXIT-3) | text | 2 |
| `usage-error` | Unknown flag | text | 3 |
| `config-error-budget` | `budget: 12000` for a Shopify target (BUD-2) | text | 3 |
| `over-cap` | Plan of 54 deliveries (CAP-1) | text | 3 |
| `two-providers` | Selection with Stripe and Shopify events (DEF-1) | text | 3 |
| `cjk-locale` | Run #3 with `ja_JP.UTF-8` | text → ASCII | 1 |
| `non-utf8-locale` | Run #3 with `POSIX` | text → ASCII | 1 |
| `color-forced` | `NO_COLOR=1 --color=always` | text ansi | 1 |
| `verbose` | Run #3 `--verbose` (absolute times, seed) | text | 1 |
| `pr-overflow` | Synthetic run with 40 findings | markdown | 1 |
| `gh-comma-title` | Finding title with a comma | github | 1 |
| `show-f1` | `webhooktrap show F1` | text | 0 |
| `pair-yes` / `pair-no` | Pairing transcript, answer `y` / `N` (§15.6, PO-E) | text | 0 / 1 |
| `pair-non-tty-guest` | Non-TTY pairing into a guest workspace: refused, `--yes` doesn't apply | text | 1 |
| `pair-origin-mismatch` | Server answers with another origin: refused before the prompt | text | 1 |
| `reconfirm-after-claim` | Prompt shown after the session was claimed (§15.6) | text | 0 |
| `config-confirm` | PRB-11 confirmation with the PV-14 sentence and entry tags (§15.6) | text | 0 |
| `first-hosted-probe` | First hosted run with a probe: the one PV-14 line before the header | text | 1 |
| `probe-lints` | PV-9 name warning and PV-10 whole-table warning (warnings only) | text | 0 |
| `guest-show-ignored` / `show-fallback` | `show:` ignored in a guest session; `show: value` on a personal-looking value falls back to a code (note names the entry, never the value) | text | 0 |
| `upload-private` / `upload-share` | `--upload` (private, members link + note) and `--upload --share` (share line) | text, markdown | 1 |
| `canary-hosted` | A09 T1/T6: Run #3 with canary probe values; uploaded JSON, PR comment and share HTML contain no canary in any encoding | json (uploaded), markdown | 1 |

All goldens stay **provisional** until Q12 (brand) settles, because the token `webhooktrap` appears on almost every line (NAME-5: renaming is a mechanical substitution and the goldens are regenerated). Provisional goldens still gate CI; approval flips the manifest status.

---

## 12. Update policy

1. **Only the harness writes goldens**: `UPDATE_GOLDEN=1` with the normal test command (A05 names it). CI never writes or auto-accepts.
2. **A golden diff fails CI** and prints a unified diff with `␛` for ESC and column positions for changed lines, so a one-space shift is visible.
3. **Every golden change is a reviewed change.** CODEOWNERS on `golden/`: **A03** (copy and layout) and **A11** (test structure). Both approve. The PR states one cause per changed file in the manifest `reason`: `copy`, `layout-rule`, `data` (RD regenerated), `renderer-fix`, `new-case`.
4. **No golden change to make a failing test pass** without one of those causes. A renderer bug found by a golden is fixed in the renderer, not in the golden.
5. **Data changes regenerate everything.** If RD is regenerated (for example a budget change in `provider-budgets.md` that moves D10/D16, TT §4 rule 6), all goldens regenerate in one PR with reason `data`.
6. **Spec sync.** Once approved, TT §1.4 points to (or embeds) the golden files. A check compares any CLI sample left in design docs with its golden and fails on difference.
7. **Approval batches.** The first batch (`run3-findings` all profiles, `run4-passed`, `run2-aborted`, `baseline-rejected`, `start-failed`, `usage-error`, `cjk-locale`) is approved at the end of Phase 4's first renderer milestone. The rest follow in the second batch.

---

## 13. Lint checks (run on every golden, every CI run)

| Check | Applies to |
| ----- | ---------- |
| Character allow-list (§3.1), ASCII-only (§3.2) | text |
| Width per line kind (§5.2), exempt classes recognised by prefix (`::`) or by single-token rule | text |
| No trailing whitespace, no tabs, LF only, one final newline | text, markdown |
| SGR set and reset per line (§4); stripped ANSI equals plain | ansi |
| `::group::` content equals `u-plain-100` rows | github |
| Glyph followed by its word (regex per glyph: `▲ (F\d+ )?High`, etc.) | text, markdown |
| Budget number always with its source tag (TR R44) | text, markdown |
| Forbidden phrases (PB §3 "Do NOT say", TT §3 sweep) | text, markdown |
| No secrets, digests or probe output | all |
| Payload strings only in code spans (§7) | markdown |

---

## 14. A-23 resolution summary

| A-23 item | Resolution |
| --------- | ---------- |
| ASCII column header has an extra space after `state` | **Intended.** Each profile is rendered from the Verdict with its own glyph table; column widths are computed after mapping. `0 -> 1` is 6 columns, so the ASCII state column is one wider (§3.2). A11's proposed "same width in both" is not adopted, because it would force Unicode padding or ASCII truncation. Found while checking: TT §1.4.3's **finding headers** are wrong in the other direction (right edge at column 98 instead of 96, because `[C]` replaced `■` without re-layout). The golden corrects them |
| `D2′` uses the Unicode prime | Kept in Unicode, `'` in ASCII, both width 1. All non-ASCII characters are on an allow-list with their ASCII mappings and are East Asian Ambiguous or Neutral; CJK locales get ASCII (§3.1) |
| 100-column rule vs long `::error` lines | Formal exempt classes: GitHub workflow commands, single unbreakable tokens, Markdown. Everything else ≤ W, prose ≤ W − 4, enforced by lint (§5.2) |
| (added) colour, timestamps, ids, update policy | §4, §6, §12 |

---

## 15. Reference samples (regenerated for decision 35)

These are the expected `u-plain-100` texts for RD Run #3 and the other verdicts, rendered from `realistic-data.json` with the layout of TT §1.4. They replace TT §1.4.1–1.4.6's samples (14 deliveries, "4 findings", `trap`). Once the product renderer exists, its approved goldens replace this section (§12 rule 6). Glyphs are shown; the `ansi` profile only adds colour to glyph + severity word (§4).

**Two rules applied here that TT §1.4 didn't have:**
1. **No F-number before the summary** (FND-6). Rows and `new finding` lines are printed while the run is live and are never redrawn, so they carry the severity word and title only. F-numbers appear first in the summary.
2. **Burst rows show state `—`**, followed by one `final state` line for the scenario (BURST-4).

### 15.1 `run3-running-d11` (live, TTY)

~~~text
$ webhooktrap prove --group sub_1QH3UF1HXb5oPQT3wfdt4drI

Webhook Trap · Run #3 · Prove invoice.paid · renewal
  target    http://localhost:3000/webhooks/stripe via maya-mbp
  handler   Express (detected from X-Powered-By)
  events    #01 invoice.paid · #03 payment_intent.succeeded · #04 customer.subscription.updated
  checks    Level 1 transport · Level 2 probe orders.count · reset npm run db:reset:webhooks
  budget    10 000 ms (Webhook Trap default)

    D    +ms      event                          http    latency  state  result
  Once
    D1    +1 320  invoice.paid                   200     184 ms  0 → 1  ▬ pass
    D2    +1 684  payment_intent.succeeded       200     121 ms  1 → 1  ▬ pass
    D3    +1 985  customer.subscription.updated  200     143 ms  1 → 1  ▬ pass
  Twice
    D4    +3 978  invoice.paid                   200     212 ms  0 → 1  ▬ pass
    D5    +4 720  invoice.paid = D4′             200     196 ms  1 → 2  ▲ High, expected 1
          new finding  ▲ High · Duplicate delivery created a second order
  Shuffled
    D6    +6 766  customer.subscription.updated  200     138 ms  0 → 0  ▬ pass
    D7    +7 084  payment_intent.succeeded       200     117 ms  0 → 0  ▬ pass
    D8    +7 381  invoice.paid                   200     199 ms  0 → 1  ▬ pass
          new finding  ○ Info · Probe doesn't see subscription state
  Late
    D9    +9 430  invoice.paid  t − 360 s        400      41 ms  0 → 0  ▬ pass (rejected)
  Retry after error
    D10  +11 321  invoice.paid                   200  11 840 ms  0 → 1  △ Medium, +1 840 over
          new finding  △ Medium · Over the 10 s budget for Stripe
    D11  +25 191  invoice.paid = D10′ (retry)      …                    waiting for the response

  ┃ Running · D11 of 16 · Retry after error · 25.3 s · ▲ High · △ Medium · ○ Info
~~~

### 15.2 `run3-findings` (after the run; the status line is replaced)

Delivery rows (also the content of `::group::Deliveries · 16`):

~~~text
    D    +ms      event                          http    latency  state  result
  Once
    D1    +1 320  invoice.paid                   200     184 ms  0 → 1  ▬ pass
    D2    +1 684  payment_intent.succeeded       200     121 ms  1 → 1  ▬ pass
    D3    +1 985  customer.subscription.updated  200     143 ms  1 → 1  ▬ pass
  Twice
    D4    +3 978  invoice.paid                   200     212 ms  0 → 1  ▬ pass
    D5    +4 720  invoice.paid = D4′             200     196 ms  1 → 2  ▲ High, expected 1
          new finding  ▲ High · Duplicate delivery created a second order
  Shuffled
    D6    +6 766  customer.subscription.updated  200     138 ms  0 → 0  ▬ pass
    D7    +7 084  payment_intent.succeeded       200     117 ms  0 → 0  ▬ pass
    D8    +7 381  invoice.paid                   200     199 ms  0 → 1  ▬ pass
          new finding  ○ Info · Probe doesn't see subscription state
  Late
    D9    +9 430  invoice.paid  t − 360 s        400      41 ms  0 → 0  ▬ pass (rejected)
  Retry after error
    D10  +11 321  invoice.paid                   200  11 840 ms  0 → 1  △ Medium, +1 840 over
          new finding  △ Medium · Over the 10 s budget for Stripe
    D11  +25 191  invoice.paid = D10′ (retry)    200     205 ms  1 → 2  ▲ High, expected 1
  Tampered
    D12  +27 246  invoice.paid  body changed     200     167 ms  0 → 1  ■ Critical, expected 0
          new finding  ■ Critical · Accepts tampered payload
  Burst
    D13  +29 263  invoice.paid                   200     601 ms  —      ▬ pass
    D14  +29 265  payment_intent.succeeded       200     312 ms  —      ▬ pass
    D15  +29 266  customer.subscription.updated  200     448 ms  —      ▬ pass
          final state  0 → 1 after the burst, expected 1 · released within 3 ms  ▬ pass
  Slow budget
    D16  +31 714  invoice.paid                   200  10 420 ms  0 → 1  △ Medium, +420 over
~~~

Summary:

~~~text
Scenarios  8 · 4 passed · 4 with findings
  ▬ Once               pass                 200 × 3 · orders.count 0 → 1
  ▲ Twice              F2 High              200, 200 · orders.count 1 → 2 on D5, expected 1
  ▬ Shuffled           pass · F4 Info       reversed · orders.count 0 → 1 · status not probed
  ▬ Late               pass                 400 in 41 ms · rejected as expected
  ▲ Retry after error  F2 High · F3 Medium  D10 11 840 ms → retry D11 → orders.count 1 → 2
  ■ Tampered           F1 Critical          200 in 167 ms · accepted a changed body
  ▬ Burst              pass · F4 Info       200 × 3 · 312–601 ms · orders.count 0 → 1 (final)
  △ Slow budget        F3 Medium            10 420 ms · 420 ms over budget

■ F1 Critical · Accepts tampered payload                                                     D12
  What happened   D12 changed amount_paid from 2400 to 0 and was signed for the original body.
                  Your handler returned 200 in 167 ms and created an order: orders.count 0 → 1,
                  expected a 4xx and no change. Likely cause: Late (D9) was rejected, so the
                  timestamp check runs; the signature check probably fails into a log line
                  instead of stopping the request.
  Why it matters  Stripe signs the exact bytes it sends. If a failed check doesn't stop the
                  request, anyone who can reach this URL can create paid orders.
                  Stripe docs › Webhooks › Verify signatures
  Fix             Return 400 as soon as constructEvent throws, before any write. After the fix,
                  D12 should get 400 and leave orders.count at 0.
                  Snippet (Express): webhooktrap show F1

▲ F2 High · Duplicate delivery created a second order                            D4 D5 · D10 D11
  What happened   D5 delivered the same invoice.paid event as D4. Both got 200
                  {"received":true}, but orders.count went 1 → 2, expected 1. The retry D11 did
                  the same after D10. Only the probe could see this. Likely cause: The handler
                  doesn't record the event id before it writes.
  Why it matters  Stripe may deliver the same event more than once, and retries failed or
                  timed-out deliveries with backoff for up to 3 days in live mode. Each
                  duplicate creates another order.
                  Stripe docs › Webhooks › Handle duplicate events · Retry behavior
  Fix             Record event.id before creating the order and return 200 early when it is
                  already recorded. After the fix, D5 and D11 should leave orders.count at 1.
                  Snippet (Express): webhooktrap show F2

△ F3 Medium · Over the 10 s budget for Stripe                                          D10 · D16
  What happened   2 of 8 valid invoice.paid deliveries took longer than the 10 s budget (Webhook
                  Trap default): D10 (11 840 ms) and D16 (10 420 ms). Stripe doesn't publish its
                  timeout, so these are over our default budget, not proven failures at Stripe.
                  Likely cause: Only some deliveries of invoice.paid were slow, so the slow step
                  is intermittent: usually an external call or a lock wait before the response.
  Why it matters  If Stripe times out waiting, the delivery counts as failed and is retried.
                  Stripe doesn't publish the timeout. In this run, D10 went over our default
                  budget, and the retry D11 created the duplicate order in F2.
                  Stripe docs › Webhooks › Quickly return a 2xx response
  Fix             Acknowledge with 200 first, then queue the slow work. After the fix, D10 and
                  D16 should respond within the budget.
                  Snippet (Express): webhooktrap show F3

○ F4 Info · Probe doesn't see subscription state                                          D6 D15
  What happened   Shuffled and Burst passed on orders.count. But customer.subscription.updated
                  was delivered first in Shuffled (D6) and inside the burst (D15), and no probe
                  reads subscription state.
  Why it matters  Out-of-order delivery usually breaks status fields, not counts.
                  Stripe docs › Webhooks › Event ordering
  Fix             Add subscriptions.status to the probe in webhooktrap.yml. The next run then
                  checks both.
                  Snippet (webhooktrap.yml): webhooktrap show F4

3 findings · 1 Critical · 1 High · 1 Medium · 1 Info
8 scenarios · 16 deliveries · 43.2 s · run_01M75F1M56RSH0CVKCTFX4FQWC
  report    https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC
  next      webhooktrap show F1   (start with the Critical finding)
~~~

Exit code 1 (F1 Critical and F2 High are at or above the default `--fail-on high`).

### 15.3 `run3-findings`, github dialect tail

~~~text
::error title=F1 Critical · Accepts tampered payload::D12 changed amount_paid from 2400 to 0 and was signed for the original body. The handler returned 200 and created an order. Run webhooktrap show F1 for the fix.
::error title=F2 High · Duplicate delivery created a second order::D5 (same event as D4) and the retry D11 each took orders.count 1 → 2, expected 1. Run webhooktrap show F2 for the fix.
::warning title=F3 Medium · Over the 10 s budget for Stripe::D10 11 840 ms (+1 840 over), D16 10 420 ms (+420 over).
::notice title=F4 Info · Probe doesn't see subscription state::No probe reads subscription state. Run webhooktrap show F4 for the webhooktrap.yml line.
Failed: 2 findings at High or above (F1 Critical, F2 High). F3 and F4 don't fail this job.
Not uploaded: results stay in this log. Add --upload to keep run history.
~~~

### 15.4 Other verdicts (summary block only)

~~~text
Webhook Trap · Run #4 · Passed · 1 Info
9 of 9 scenarios passed at Level 1 + Level 2 (orders.count).
Not checked: subscription state, which no probe reads · side effects outside your database, such
as emails.
  ▬ Once  ▬ Twice  ▬ Shuffled  ▬ Late  ▬ Retry after error  ▬ Tampered  ▬ Unsigned  ▬ Burst
  ▬ Slow budget
○ F1 Info · Probe doesn't see subscription state
~~~

Exit 0.

~~~text
Webhook Trap · Run #2 · Aborted
Agent maya-mbp disconnected at 13:57:04.120 during D3 of 16. D1–D2 and their results are kept.
D3 was sent, with no result. D4 to D16 were not run. Reconnect maya-mbp to run it again.
~~~

Exit 2.

~~~text
Webhook Trap · Run #1 · Inconclusive (not passed)
Responses are identical, but we can't see your database. Add a probe to check for duplicate side
effects. In webhooktrap.yml:
  probe:
    - as: orders.count
      sql: select count(*) from orders where stripe_invoice_id = '{{invoice.id}}'
~~~

Exit 0 (1 with `--fail-on-inconclusive`). This block is local output; the `probe:` lines are the pack's template, not read from the user's file. On the hosted run page the same template shows under the label "Example" (SS §0.5 rule 6).

~~~text
Webhook Trap · Couldn't start the run
Your handler rejected a correctly signed delivery (HTTP 401). Check the secret on maya-mbp and the
target path.
Nothing else was sent. This run doesn't count toward guest quotas.
~~~

Exit 2 (`baseline-rejected`). The same block shape serves every Couldn't start reason, with the RD `couldnt_start_*` sentence. A run number is printed only if the server stored the run (agent connected); the D1 row is printed above the block when D1 was sent.

### 15.5 PR comment, heading and table (`run3-findings`)

~~~markdown
<!-- webhooktrap:pr-comment -->
### Webhook Trap · 3 findings · check failed

**■ 1 Critical · ▲ 1 High · △ 1 Medium · ○ 1 Info** · 8 scenarios · 16 deliveries · 43.2 s · fails on High and above

`webhooktrap test` against `http://localhost:3000/webhooks/stripe` · Level 1 transport + Level 2 probe `orders.count` · budget 10 000 ms (Webhook Trap default)

| | Scenario | Result | Reading |
| :-: | --- | --- | --- |
| ▬ | Once | Pass | 200 × 3 · orders.count 0 → 1 |
| ▲ | Twice | **F2 High** | 200, 200 · orders.count 1 → 2 on D5, expected 1 |
| ▬ | Shuffled | Pass · F4 Info | reversed · orders.count 0 → 1 · status not probed |
| ▬ | Late | Pass | 400 in 41 ms · rejected as expected |
| ▲ | Retry after error | **F2 High** · F3 Medium | D10 11 840 ms → retry D11 → orders.count 1 → 2 |
| ■ | Tampered | **F1 Critical** | 200 in 167 ms · accepted a changed body |
| ▬ | Burst | Pass · F4 Info | 200 × 3 · 312–601 ms · orders.count 0 → 1 (final) |
| △ | Slow budget | F3 Medium | 10 420 ms · 420 ms over budget |
~~~

The four `<details>` blocks follow TT §1.4.6's structure with §15.2's beat text; evidence links per §7 (`[D12](…/r/{runId}/D12)` for a private upload, `[D12](…/s/{shareToken}/D12)` with `--share`). The context line adds the provenance: `` `webhooktrap test` against `http://localhost:3000/webhooks/stripe` · Uploaded from CI (token github-actions) · … ``. Footer for a private upload: "This report is private to the project. Upload with --share to give it a public link." (**NEW**).

### 15.6 Pairing, probe disclosure and upload lines (decisions 38–39)

Pairing, answer `y` (`pair-yes`). The server line is the origin from the agent's own configuration and comes first (PO-E):

~~~text
$ npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD
Server     https://webhooktrap.dev
Workspace  Guest session · expires 2026-11-12 13:55 UTC
Join? Only continue if you created this code in your own browser just now. [y/N] y
Joined as maya-mbp. Leave this terminal open while you run.
~~~

Answer `N` (`pair-no`), exit 1:

~~~text
Join? Only continue if you created this code in your own browser just now. [y/N] n
Not joined. This agent is not paired, and the code is used up.
~~~

After a claim (`reconfirm-after-claim`):

~~~text
This agent's workspace changed. Check it before it accepts jobs.
Server     https://webhooktrap.dev
Workspace  Maya Lindqvist (maya@brindlecoffee.example)
Continue with this workspace? [y/N] y
Confirmed. Target approvals were cleared; the first run to each target asks again.
~~~

Config confirmation (`config-confirm`, PRB-11; local, so the commands are shown):

~~~text
webhooktrap.yml asks to run these commands on this machine:
  probe  orders.count  count  select count(*) from orders where stripe_invoice_id = '{{invoice.id}}'
  reset                       npm run db:reset:webhooks
Probe values stay on your machine. The web app shows counts up to 9 999 as numbers and other
values as short codes such as #a1f3c0, which only show whether a value changed.
Run these commands? You're asked again only when webhooktrap.yml changes. [y/N]
~~~

The tag column uses `count`, `code`, `shown (show: value)` or `shown (show: number)` (PV-14). First hosted run with a probe (`first-hosted-probe`): the same two-line sentence, once, before the `Webhook Trap · Run #3` header.

Local notes and warnings (never block; `probe-lints`, `guest-show-ignored`, `show-fallback`):

~~~text
warning  Probe names are visible in the web app and on shared links. (orders.cus_9fk2.count)
warning  This probe reads the whole table. Its value can reveal your volume over time. Scope it
         with {{invoice.id}}. (orders.count)
note     show: is ignored for guest sessions; sign in to use it
note     customer.email is marked show: value, but its value looks like personal data, so the web
         app shows a code.
~~~

Notes and warnings wrap by §5.2 with a 9-column hanging indent. The entry name in brackets is the `as:` name; a note never contains a probe value.

Upload lines (`upload-private`, `upload-share`), after the last line of §15.2/§15.3:

~~~text
  report    https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC
Uploaded privately. Add --share to get a link anyone can open.
~~~

~~~text
  report    https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC
  share     https://webhooktrap.dev/s/q7Vf2LmX9pK4wT8nR3bZcD
~~~

The `report` link opens for project members only (PO-A). The CI token is never printed; the run's provenance is "Uploaded from CI (token github-actions)".

---

## 16. Changes in this revision (2026-11-14, decisions 35 and 38–39)

| # | Section | Change | Source |
| - | ------- | ------ | ------ |
| 1 | Header, §15 | Reference samples regenerated from RD (16 deliveries, "3 findings", `webhooktrap`); they replace TT §1.4.1–1.4.6's samples | SJ §16.17–16.19, C-4 |
| 2 | §3.2, §5.3, §6 | Examples renumbered (`D4 D5 · D10 D11`, `+25 191`, `43.2 s`) | C-4 |
| 3 | §5.3, §9, §15 | No F-number in live rows or `new finding` lines | SJ FND-6, §16.18 |
| 4 | §7 | PR heading says "check failed" exactly when exit ≠ 0 | SJ EXIT-2 |
| 5 | §8 | `::group::Deliveries · 16`; `Incomplete:` last line; exit per EXIT-1 | SJ EXIT-1 |
| 6 | §10 | JSON content per SJ §13; local vs uploaded probe values | SJ JSON-1, JSON-2, PRB-3, C-3 |
| 7 | §11 | Every case ready; exit codes per case; new cases for config errors, cap, two providers, `--fail-on`, exit 2 over 1, target lost, user abort | SJ §7.4, CAP-1, DEF-1, BUD-2, C-2 |
| 8 | §2.1, §11 | `WEBHOOKTRAP_PROGRESS`; provisional until Q12 instead of A-20 | SJ NAME-3, C-1 |
| 9 | §7, §8, §15.5 | PR links: private upload → `/r/{runId}` + private footer; `--share` → `/s/{shareToken}`; provenance in the context line; token never printed | PO-A, PO-C; A01 RUN-8, RUN-10 |
| 10 | §10 | Uploaded JSON follows PV-2/PV-5 grammars, no PV-8 data, "judged on"; canary lint | Decision 38; A09 T1, T6; A01 RUN-9 |
| 11 | §11, new §15.6 | Cases and texts for pairing (`[y/N]`, origin first, N, non-TTY guest, reconfirm), PV-14 config confirmation and first-hosted line, PV-9/PV-10 lints, `show:` notes, upload private/share | PO-E, PO-F, PV-5, PV-9, PV-10, PV-14 |
| 12 | §15.2, §15.5 | F4 evidence `D6 D15` and text; Burst row `pass · F4 Info` | A01 PRB-12a |
| 13 | §15.4 | Run #1 block noted as a pack template (local), labelled Example on hosted pages | PV-8 |

## Open for the PO

None for the PO. One copy point is settled here and flagged to A01 for agreement: SJ §6.3 asks only that live `new finding` lines drop the F-number. Live **rows** carried `▲ F2 High` in TT, which has the same problem (the number isn't final), so rows drop it too (§15 rule 1).
