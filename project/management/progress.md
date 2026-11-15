# Progress — Webhook Trap 2.0

## 2026-11-11 — Security hotfix and Phase 1 report

Two independent tracks, reported separately.

### Track 1 — 1.x security hotfix (local only)

**Status:** REVIEW_REQUIRED
**Branch:** `hotfix/security-2026-11` from `main@27e9d98`, worktree
`.worktrees/hotfix-security`. Not pushed, no PR, not deployed.

- 5 commits for P-01 – P-04 (+ one test helper); 24 files, +1361 / −96.
- API tests 41 → 100 passing; lint, type-check and API `tsc` clean.
- Independent verification by A11: P-01, P-02 VERIFIED; P-03, P-04
  VERIFIED WITH ISSUES. New findings P-11 (High) to P-15 (Low).
- Details, deploy-time actions and exploit specifics: private report
  `SEC-2026-11`, kept outside this repository.

**Approval required:** extend the hotfix to P-11 (and optionally P-12 and
the test tightening); later, the push/deploy sequence.

### Track 2 — Phase 1 (product and brand exploration)

**Status:** REVIEW_REQUIRED — Gate 1
**Workspaces:** `.worktrees/a01-product` (`phase1/a01-product`),
`.worktrees/a02-brand` (`phase1/a02-brand`). Nothing committed or merged.

- A01: `project/product/` vision, personas, requirements (keep/defer/drop of
  33 1.x features), roadmap, open questions.
- A02: `project/brand/` strategy, three visual directions with logos,
  palettes, typography, landing hero and live inbox mock-ups.
- A00: evaluation of the three proposed ADR amendments
  (`project/architecture/amendment-evaluation.md`).

**Approval required (Gate 1):** positioning, brand direction, the three
amendments, blocker B-06 (Redis), ADR-009 open questions.

### Not started (by design)

Phase 2 and any 2.0 implementation. `spikes/live-props/` kept as a Phase 4
asset, untouched.

## 2026-11-11 — Change of direction: Phase 1b

**PO decision 15:** 2.0 is a new product, designed from the webhook problem,
not a rebuild of 1.x. Phase 1 product and brand work marked SUPERSEDED (kept).

- Sequence: A01 (research → concepts → positioning → core experience), then
  A03 (UX exploration) ∥ A02 (≥3 new art directions), then A00 (ADR impact
  review, recommendation).
- Confirmed ADRs stay in force unless A00 shows the new concept challenges
  them; any change will be justified and proposed, not applied.
- Hotfix track unchanged and independent: still waiting on the P-11 decision.
- Phase 2 and implementation: not started.

### Update — Claude Design (decision 19) and hotfix extension (decision 20)

- Claude Design access checked: the Design (canvas) and Design System
  artifact types are available to this account. No canvas created yet; the
  first ones are created once A02/A03 have defined the territories and UX.
- Hotfix: A09 extending the local branch to P-11, P-12, stronger P-03/P-04
  tests and DNS rebinding. Not pushed, not deployed.

### Update — A01 delivered (Phase 1b step 1)

- 8 PROPOSED documents, 45 cited sources, 4 concepts (A Workbench, B Proving
  Ground ★, C Flight Recorder, D Sender Lab). Recommended positioning: "the
  test suite for webhook handlers". ADRs challenged: 006 (local agent in MVP),
  002 (non-PHP agent), 004, 005, 001.
- A02 now defining three brand territories in `.worktrees/p1b-design`
  (branch `phase1b/design`), based on concept B.

### Update — A02 territories delivered; A03 started

- `.worktrees/p1b-design/project/design/brand-territories.md`: I · Timetable
  (time–distance graph, rigorous), II · Crash Lab (crash-test specimen,
  bold), III · Notation (annotated chess game, unexpected). A02 leans
  Timetable, Notation as challenger.
- A03 now translating each territory into UX + one shared realistic dataset.
- Design still built on concept B; PO confirmation of Q1 pending.

### Update — hotfix extension verified (A11)

10 local commits on `hotfix/security-2026-11`, 127 tests. A11: P-11 and DNS
rebinding VERIFIED, P-03/P-04 tests VERIFIED, P-12 VERIFIED WITH ISSUES. Two
new Low findings (P-16 per-IP limiter behind proxy, P-17 over-broad block of
a public range). Not pushed, not deployed. Awaiting PO decision on P-16/P-17.

### Update — A03 delivered; A04 building in Claude Design; P-17

- A03: `ux-foundations.md`, `ux-{timetable,crashlab,notation}.md`, shared
  dataset `realistic-data.{md,json}` (fictional Brindle Coffee Co., 13
  captures, Run #3 with 14 deliveries and 4 findings).
- A04: three parallel runs, one per territory, each creating one Design
  System + one Design canvas artifact in Claude Design (brief:
  `.worktrees/p1b-design/project/design/a04-brief.md`).
- Hotfix: P-17 fixed (`ebe24c0`), 127 tests, A11 verifying. P-16 deferred
  (decision 21).
- Hotfix: P-17 VERIFIED by A11. Branch `hotfix/security-2026-11`: 11 local
  commits, 127 tests, not pushed, not deployed. Open: P-16 (deferred), P-13 –
  P-15 (Low, later), P-05 – P-10 (later hotfixes).
- A04 Notation published in Claude Design: Design System
  https://claude.ai/artifact/HNKgCdYHshzD5N7xieZhnf · canvas (29 artboards)
  https://claude.ai/artifact/XXvbGdE7vo62VpDGXWUfqn. Not yet visually checked.
- A04 Timetable published in Claude Design: Design System
  https://claude.ai/artifact/7n63VUDFZC31A1bRTJfwNY · canvas (31 artboards)
  https://claude.ai/artifact/3ovs7azTxTzGFREDuBe6En. Not yet visually checked.
- A04 Crash Lab published in Claude Design: Design System
  https://claude.ai/artifact/NZYTD3LgZ8eBeDmQMFQTJo · canvas (31 artboards)
  https://claude.ai/artifact/EBrZR6RqBwwg18w45P7c2J. Next: A00 visual check, A01 fit review.

### Update — Model selection policy (decision 22)

- `project/management/model-policy.md`: four levels (C4 critical / C3 complex →
  Opus 5.5, C2 specified → Sonnet 5.5, C1 mechanical → Haiku 5.5) and a run
  log. All runs so far used Opus 5.5 (inherited); reviewed afterwards, all
  were C3/C4, so no rerun needed.
- Limitation: no model available here is documented as design-specialised;
  Fable 5.2 not assumed to be one. Creative work stays on Opus 5.5 unless the
  PO wants a blind A/B trial.
- Running now: A01 fit review (C3, Opus 5.5), A11 static QA of the canvases
  (C3, Opus 5.5).
- A01 fit review done (`.worktrees/p1b-design/project/design/fit-review.md`):
  Timetable 30/35, Notation 25/35, Crash Lab 24/35. Shared must-fixes: remove
  visible VERIFY/[LICENCE] placeholders, verify provider budgets, soften the
  "survived" Passed line, draw the guest's first minute without an agent.

## 2026-11-11 — Phase 1b review ready (REVIEW_REQUIRED)

- A11 static QA (`qa-static-review.md`): all three READY FOR PO VIEWING, no
  blockers; 91 artboards byte-identical to sources, every required frame
  present, contrast confirmed. Rendering not observed (B-08).
- Recommendation: `project/management/phase-1b-recommendation.md` — confirm
  concept B with Q13 validation first; Timetable as lead direction borrowing
  Crash Lab's scenario cells and gauge and Notation's voice/CLI format;
  Notation fallback; drop Crash Lab.
- Nothing approved; no Phase 2; no implementation; no merge.

### Update — Timetable chosen as lead direction (decision 23)

- Iteration plan: A01 verifies provider response budgets (C3, Opus) ∥ A03
  specifies the borrowings, the missing screens (guest first minute without
  agent, CLI, CI/PR output) and the 10-second comprehension test (C3, Opus);
  A00 applies verified budgets to the dataset; then A04 iterates the
  Timetable canvas and design system in Claude Design (C3, Opus).
- Still open for the PO: Q1 (confirm concept B), Q13 (validation first).

## 2026-11-11 — Work stopped at PO request

- Stopped mid-run: A01 provider-budget verification and A03 Timetable v2
  spec (no output files written). No agent running.
- State at stop:
  - Hotfix `hotfix/security-2026-11`: 11 local commits, 127 tests, verified
    by A11; not pushed, no PR, not deployed. P-16 deferred.
  - Phase 1b: strategy (A01), territories (A02), UX (A03), three directions
    in Claude Design (A04), fit review, static QA, recommendation — all
    REVIEW_REQUIRED. Timetable chosen as lead (decision 23); iteration not
    started.
  - Open for the PO: Q1 (concept B), Q13 (validation), Gate 1 decisions.
  - Worktrees kept, all uncommitted except the hotfix branch.

## 2026-11-14 — Resumed at PO request

- Relaunched A01 (provider budgets) ∥ A03 (Timetable v2 spec), both Opus 5.5.
- PO confirmed concept B (decision 24). Still open: Q13 (validation first),
  follow-ups in `project/architecture/concept-impact.md` (agent in MVP, new
  ADR, ADR-003/004/005 amendments), Gate 1.
- A01 provider budgets done (`provider-budgets.md`): Shopify 5 s, GitHub 10 s,
  Slack 3 s, Twilio 15 s documented; **Stripe publishes no timeout** → shown
  as "Webhook Trap default 10 s"; F3 and "Slow budget" copy to be reworded
  (over our budget, not proven failures at Stripe). Dataset update waits for
  A03's v2 spec (budget term).
- Decision 25: A02 exploring 3–4 Timetable logo concepts in a dedicated
  Claude Design canvas (construction, outlined wordmark lockups, 16–128 px
  scale test, in-context, clear space, comparison). The chosen mark will be
  integrated by A04 with the screen iteration.
- A03 v2 spec done (`ux-timetable-v2.md`): "budget" as the single term with a
  source tag (`Stripe docs` / `default` / `trap.yml`); scenario strip above the
  graph; state reading "Expected 1 · got 2" on the state row; findings in
  What happened · Why it matters · Fix; one text format for CLI/CI/PR; 19 new
  boards, ~25 changed. Dataset regeneration running (Sonnet 5.5, C2).
- Dataset regenerated (Sonnet 5.5): zero VERIFY/allowance, budget source tags
  (Stripe `default`, Shopify/GitHub docs), F3 and Slow budget reworded.
- A04 building Timetable v2 on the existing Timetable artifacts (Opus 5.5).
  Logo: current mark kept until the PO picks from A02's exploration.
- A02 logo exploration done: canvas https://claude.ai/artifact/D579LRxyW9S96UXqmZQPCQ
  (22 boards). Concepts: Points (refined), Twice ★, Second pass, Proves.
  Custom geometric wordmark as outlined paths. Resemblance checks partly from
  memory only → legal/visual check needed before adoption. Awaiting PO pick.
- Decision 26: PO chose the **Twice** logo. A04 asked to integrate it at the
  end of Timetable v2. Open: trademark/visual resemblance check before public
  use; real-browser 16 px favicon check.
- Identity PDF (French, 20 pages A4 landscape) delivered to the PO:
  `.worktrees/p1b-design/project/design/brand/identite-visuelle/webhook-trap-identite-visuelle.pdf`
  (working version). Open: legal/visual resemblance check, real-browser 16 px
  check, outlined Archivo wordmark option.
- A04 Timetable v2 published on the existing artifacts (system version
  1794618712-36f8): 19 boards added, 19 changed (+12 via imports), 0 failed;
  "budget" tokens, inline SVG sprite, 5 new components, Twice logo
  integrated. Not rendered. A11 static QA running.
- A11 static QA of Timetable v2: READY FOR PO VIEWING, 0 Blocker/Major,
  7 Minor (`qa-timetable-v2.md`); sprite resolves in imported boards (runtime
  read). Remaining before Gate 1: minor fixes, 10-second comprehension test
  with 5 developers, logo resemblance check, Q13, concept-impact follow-ups.
- 7 Minor QA findings fixed and published (Sonnet 5.5, C2): system
  1794619774-2b2c, canvas 1794619794-5bd0. Timetable v2 ready for PO review.
- A01 validation kit done (`.worktrees/p1b-product/project/product/validation/`):
  H1 first-run finding rate ≥40% go / 20–39% extend / <20% fall back to A;
  ≥8 first runs; decision due Mon 2026-11-30. 10-second test moved before the
  prototype run. PO decisions pending: channels, incentive, prototype go-ahead
  and language (A01 recommends TypeScript/Node), thresholds.

## 2026-11-14 — Go validation prototype built, security review started

- A06 (Sonnet 5.5, C2) built the Go CLI on `spike/proving-cli`.
  - 3 local commits, nothing pushed, no attribution lines.
  - A00 re-ran the checks: `go test`, `go vet` and `gofmt` are clean, and
    there are no third-party modules.
  - Binaries are 7.4–8.0 MB on 4 platforms.
- §9 status:
  - On the sample handler, the buggy run finds Tampered (Critical), Late,
    Twice and Shuffled (High), and Slow budget (Medium). The fixed run finds
    nothing.
  - Level 1 without a probe: Twice is reported as "Inconclusive".
  - Signatures: Stripe and Standard Webhooks were verified with their official
    SDKs. GitHub and Shopify were checked against the algorithm only.
  - Non-local targets are refused. strace showed traffic only to the target.
  - The secret appears in no file.
- Still to do before the first external run:
  - A09 review (Opus 5.5, C4, independent), in progress.
  - A check with a real `stripe listen`.
  - Timing the 2-minute first run.

## 2026-11-14 — A09 review of the Go prototype: SAFE WITH FIXES

A09 (Opus 5.5, C4) ran an independent review and reproduced every finding.
All scratch work is outside the repository.

**Must fix before participants:**
- **F1 (High):** terminal escape injection. Event labels and IDs from the
  payload are printed raw.
- **F2 (High):** `proving.yml` `probe:`/`reset:` run through `sh -c` without
  being shown or confirmed. Running it in a cloned repo executes arbitrary code.

**Should fix:**
- **F3 (Medium):** fixtures are saved without masking.
- **F4 (Medium):** writes follow symlinks.
- **F5 (Medium):** the recorder has no timeouts, no header cap and no cap on
  fixture count.

**Low / Info:**
- **F6:** `import` has no size cap.
- **F7:** masking has known limits.
- **F8:** non-local names trigger a DNS lookup; `--allow-host` is broad.

**Held up under test:**
- the loopback guard (every spelling tried, rebinding, redirects, proxy
  variables);
- the recorder's loopback bind;
- the export;
- file modes;
- no third-party modules and no telemetry.

**Status:** fixes await the PO's go-ahead. The prototype is not shared with
participants until A09 re-verifies.

## 2026-11-14 — A09 re-check of the prototype fixes: SAFE WITH FIXES

- **Fixes landed.** A06 fixed F1–F6 (`4104d65`) and documented F7–F8
  (`cd0d8da`).
- **Re-check results (A09, Opus).**
  - F2–F6 are FIXED, confirmed by re-running the original PoCs and trying
    bypasses.
  - F1 is PARTIAL.
  - No regressions: the sample verdicts and the guard are unchanged.
- **New findings.** Both need a hostile `.proving/` or `proving.yml`.
  - **N1 (Medium):** `proto judge` trusts `run_id` from the run file. The
    path traversal writes outside `.proving/runs`, and the value is printed
    raw.
  - **N2 (Low):** fixture filenames, guard-error hosts and an unvalidated
    `provider:` reach output and the export raw.
- **README.** Needs three more known limits:
  - the DNS lookup happens before the prompt;
  - header names are not masked;
  - a short secret causes false-positive body refusals.
- **Status.** Not yet cleared for participants. Fixing N1/N2 needs the PO's
  go-ahead (it is outside decision 29's scope).

## 2026-11-14 — Phase 1b deliverables committed locally (PO request)

- `phase1b/a01-product`:
  - `0c7fbbc`: discovery.
  - `5eeeec1`: validation kit.
- `phase1b/design`:
  - `8e39871`: exploration, UX, data, identity PDF.
  - `963ed9e`: Claude Design sources, 368 files.
- Left untracked: `crashlab/_ref/`, a copy of Claude Design's own type
  reference docs.
- Status: not pushed, not merged.

## 2026-11-14 — Option B: Gate 1 without field validation

- Decision 31 recorded and risk R-15 opened.
- A11 testability review started (Opus, C3). It writes
  `project/qa/testability-review.md`.
- A06 fixed N1/N2 in `fbe3c38`. A00 re-ran test, vet and gofmt: all clean,
  no attribution in the commits. A09's third pass is running (Opus, C4).

## 2026-11-14 — Gate 1 architecture package approved (decision 32)

- B-04, B-05 and B-06 are resolved:
  - local agent in the MVP, with its new ADR in Phase 4;
  - ADR-003/004/005 amendments to be drafted as Proposed;
  - server-side private replay for self-hosted instances only;
  - Redis required;
  - no 1.x migration, with 1.x sunset on its own domain;
  - hosting decided in Phase 4.
- Gate 1 is waiting only on A11's testability review.

## 2026-11-14 — A09 third pass on the prototype

A09 verdict: SAFE WITH FIXES; the remaining issues do not block use.

**Fixed.** N1, N2 and F1 are now fixed. No bypass was found and there are no
regressions:
- the sample verdicts are unchanged;
- the guard still holds;
- F2 and F4 were spot-checked.

**Open, both Low:**
- **L1:** Unicode tag characters (U+E0000–E007F) are not stripped. They are
  invisible and are not escape sequences.
- **L2:** run and fixture loaders have no size cap and no regular-file check.
  A hostile `.proving/` can exhaust memory or hang the tool (FIFO).

The prototype is cleared for internal proxy validation (decision 31). The
fixes are cheap if the PO wants them.

## 2026-11-14 — Gate 1 passed; Phase 2 started

- **Gate 1 passed (decision 34).** A11's testability review came back READY
  WITH CLARIFICATIONS, with 27 ambiguities and their owners. See
  `project/qa/testability-review.md`.
- **Phase 2 running (Opus, C3).**
  - A01 is writing the scenario and judge spec plus the proxy validation
    protocol, at `p1b-product/project/product/spec/scenarios-and-judge.md`.
  - A03 is writing the IA, navigation, accessibility spec and golden-file
    rules, under `p1b-design/project/design/phase2/`.
- **Prototype.** A06 fixed L1 and L2 in `13b6c19`. A00 checks are clean,
  including the Windows vet. A09 is verifying.

## 2026-11-14 — A03 Phase 2 structure delivered

A03 delivered `p1b-design/project/design/phase2/`:
- `information-architecture.md`
- `navigation.md`
- `accessibility-spec.md`
- `golden-files.md`

All are REVIEW_REQUIRED and uncommitted.

**Navigation model:**
- The project is the home.
- One top bar with the tabs Capture · Runs · Fixtures · Agent.
- The selection lives in the URL.
- One run URL `/r/{runId}` is shared by web, CLI, CI, PR and MCP, with
  deep links to deliveries and findings.

**Resolved:**
- **A-23:** golden files; this also caught a 98-column overflow in the TT
  §1.4.3 ASCII headers.
- **A-26:** WCAG 2.2 AA on all web surfaces.
- **A-27:** four keyboard layers; no single key performs a destructive
  action, and "Run again" moves to `⌘↵`.

**PO decisions P1–P11 pending (IA §10).** Bundled with A01's A-7/A-13/A-18
for one PO review. Key ones:
- P1: the app moves off the capture URL.
- P2: runs are private by default.
- P3: naming of "delivery".
- P4: agents are workspace-scoped.
- P6: hosted public replay as an MVP run target.

**Waiting on A01:**
- every Run state;
- the verdict line;
- run status and exit codes;
- 5 new states.

## 2026-11-14 — A01 scenarios-and-judge spec delivered

- **Spec:** `p1b-product/project/product/spec/scenarios-and-judge.md`
  (REVIEW_REQUIRED, uncommitted).
  - It resolves the 18 A01-owned ambiguities and adds the proxy validation
    protocol.
  - It lists 31 changes for A03.
- **Key rules:**
  - Once sends every event.
  - Severity = worst proven consequence.
  - One status and exit-code table for every surface.
  - The 3× median rule is dropped.
  - Tampered uses a fresh timestamp.
- **For the PO:** the bundled decisions are in `phase2-decisions-pending.md`
  (A-7, A-13, A-18, C-1 to C-4, P1 to P11, protocol pre-registration).

## 2026-11-14 — A09 verdict on L1/L2

**Verdict: SAFE WITH FIXES.**
- **Internal proxy validation:** OK.
- **Outside participants:** not yet. They need L1 completed.

**L2: FIXED.**
- A 300 MB file, a symlink to `/dev/zero` and FIFOs are all refused.
- The swap race (1M reads) did not break it.

**L1: PARTIAL.** Variation selectors and other default-ignorable characters
still get through: 42 format characters in total.

**Next:** A06 is finishing L1 with a sanitiser based on Unicode properties.
This is within decision 33.
- A06 completed L1 in `6d1f107`: the sanitiser now works by Unicode property.
  A00 confirmed the new test fails on the old sanitiser and passes on the new
  one. A09's final check is running.
- 2026-11-14 — Decision 35: PO approved all 22 Phase 2 recommendations; proxy protocol pre-registered (snapshot 2026-11-14, salt in decisions-log).
- 2026-11-14 — A03 launched (flows, screen specs, §16 changes); A01 launched (proxy candidate list, search only, no download).
- 2026-11-14 — Decision 36: A05 launched early on Phase 4 (documents only) in .worktrees/p4-architecture.
- 2026-11-14 — A09 final check: L1 FIXED, no regressions. GO internal; GO with conditions for outside participants. A09 relaunched on C-3 review.
- 2026-11-14 — A09 C-3 review: SAFE WITH CHANGES; rules PV-1..PV-14 (private report) forwarded to A05; PO to confirm they replace PRB-3.
- 2026-11-14 — A05 delivered Phase 4 draft package in .worktrees/p4-architecture (ADR-010, amendments 003–006, domain model, schema, events, contracts, open questions PO-1..PO-9). Uncommitted.
- 2026-11-14 — A03 delivered user-flows.md (14 flows) and screen-specifications.md (24 screens); §16 items 1–14 applied; 8 A11 tests flagged stale. A04 launched on Claude Design update.
- 2026-11-14 — Decision 37: A09 threat model and A10 hosting options launched.
- 2026-11-14 — Decision 38: PO adopted PV-1..PV-14 (replaces PRB-3; settles PO-5).
- 2026-11-14 — A04 published 67 Phase 2 boards + DS updates (Timetable); 50 v2 boards archived. A11 static QA launched.
- 2026-11-14 — A10 delivered hosting-options.md: recommends option A (EU VPS + Compose, separate runner VPS, ~€34–53/month); HD-1..HD-10 for the PO. Runner-as-remote-agent proposal needs A05/A09.
- 2026-11-14 — A09 threat model: all 10 Phase 4 docs ACCEPT WITH CHANGES; PO-A..PO-G raised. Consolidated PO decisions in phase4-decisions-pending.md.
- 2026-11-14 — Decision 39: Phase 4 decisions taken; HD-1 = existing Contabo 1.x VPS (runner stays on its own VM).
- 2026-11-14 — A05 (revision) and A10 (Contabo plan, paper only) launched.
- 2026-11-14 — A11 QA v3: READY WITH FIXES (0/13/18). A04 launched on fixes; PV display copy held for A03/A01/A09.
- 2026-11-14 — Decision 40: 1.x retired everywhere, now; notice to users, no backup. A10 to write decommission runbook (paper).
- 2026-11-14 — A05 revision done. PO asked for 1.x user count and migration options: local .env points to a local DB, not production; read-only inventory SQL written to webhooktrap-private/migration/1x-inventory.sql for the PO to run on Neon.
- 2026-11-14 — A10 delivered contabo-deployment-plan.md, compose-profile.md, 1x-decommission-runbook.md (paper). Decision 40 execution on hold: PO reconsidering after learning 1.x has real users.
- 2026-11-14 — 1.x prod inventory (read-only, via VPS .env, URL never shown): 3 users (1 is the PO), 0 verified, 27 inboxes (22 anonymous, 5 owned, none with a saved replay URL), 15 events total, 1 in last 30 days, last event 2026-10-28. CSVs in webhooktrap-private/migration/. VPS also hosts other apps (/var/www: <neighbour>, <neighbour>, <neighbour>, <neighbour>, tools; /opt: <neighbour>, <neighbour>, apps) — A10's OS-reinstall step must be dropped.
- 2026-11-14 — Decision 41: 1.x data exported to local JSON (counts match prod); accounts to be restored in v2; OS-reinstall step withdrawn (VPS hosts other apps).
- 2026-11-14 — A01 delivered project/validation/proxy-repo-candidates.md: 15 main, 10 pooled reserve; one discarded query parse error (no effect on selection); manual application check in hash order, logged. Awaiting PO approval before any download.
- 2026-11-14 — Decision 42: PO approved A01's repo list as produced; downloads authorised for disposable-container runs.
- 2026-11-14 — A06 launched: disposable-container harness and pilot on 2 main-list repos before the full 15.
- 2026-11-14 — A04 fixed v3 Majors except M10/M11 (PV copy). A01 (spec) and A03 (screens) launched on PV and A09-driven changes.
- 2026-11-14 — A01 spec Revision 2 (PV copy, SIG-3 without last-4, F4 = D6·D15, no live F-numbers); forwarded to A03. Open for A05: C-7 probe-name grammar, C-8 server runs Level 1 only. PO shared laravel-inertia-vps-template (infra-only: FrankenPHP, Compose, GHCR, SSH deploy).
- 2026-11-14 — Pilot: harness isolation verified (run with --network none). Stripe repo: baseline rejected (HTTP 419, CSRF) → Couldn't start, 0 findings. GitHub repo skipped (rule 6: needs real GitHub App + ~40 services). Prototype sends a new X-GitHub-Delivery on Twice (spec says keep). 3 PO decisions pending before the other 13.
- 2026-11-14 — Decision 43: deployment based on laravel-inertia-vps-template; A10 relaunched to rebase the plan.
- 2026-11-14 — Decision 44: Go confirmed; auth = email/password, GitHub, Google, 2FA (TOTP), guest sessions, agent tokens + WebAuthn (passkeys and hardware security keys).
- 2026-11-14 — A03 updated 6 specs + data for decisions 38–41; A04 launched on redraw; A05 launched on WebAuthn + C-7/C-8 (A09 re-check follows).
- 2026-11-14 — A10 rebased ops docs on the template (Postgres 18 in Compose + pgBackRest; forced-command deploy user). A00 gathered VPS facts read-only (vps-facts.md): Caddy host proxy, 8 vCPU/23 GiB/58 G free, 18080–18081 taken, 1.x still online; <neighbour>:5433 and <neighbour>:5000 published on 0.0.0.0 (bypass ufw) — flagged to PO.
- 2026-11-14 — A05 rev 3: WebAuthn, TOTP, recovery, 1.x import (recommends forced reset), C-7/C-8 closed. A09 re-check launched.
- 2026-11-14 — A09 re-check: nothing rejected; required changes for A05 (runner, WebAuthn counter, etc.) and A10 (deploy.sh signature check, share tokens in logs, arg mismatch). Advice: PO-H forced reset, PO-I drop magic links, PO-J with conditions. Go-live blockers on shared VPS (Caddy admin API, 1.x as root, exposed neighbours).
- 2026-11-14 — A05 rev 4 applied all A09 §12.5 rows; awaiting A10 ops changes, then A09 verification.
- 2026-11-14 — Decision 45: open source confirmed; licence decided before first public release; nothing of 2.0 published until then.
- 2026-11-14 — A04 redrew 51/52 boards (+GF156Pairing), DS gains ProbeCode and ProvenanceLine. Next: A03 security screens U-1..U-9, then A11 re-QA before Gate 2.
- 2026-11-14 — A10 ops rev 3 (cosign-verified deploy.sh, Compose policy, log scrubbing, APP_PORT 18480/18481, go-live gates G-1..G-6). A09 verification launched.
- 2026-11-14 — A03 specified account-security screens (S-02 rewrite, S-17A/B, S-24..S-31, FL-15..FL-23); contract gaps listed for A05. A04 launched on 21 boards.
- 2026-11-14 — A09 §12.9: A05 package ACCEPT (4 editorial follow-ups); A10 ops ACCEPT WITH CHANGES (SV-2 wrong). ADRs acceptable once PO decides PO-H/PO-J and confirms PO-I. A10 and A05 relaunched.
- 2026-11-14 — A10 ops rev 4: SV-2 Caddy order fixed (outer route), policy P-10/P-11, /runner/v1 IP allowlist, HSTS, log redaction, ENG-2. A09 verification after A05 rev 5.
- 2026-11-14 — A05 rev 5 done (sessions in MVP, email change, unblock, re-auth, link interstitials, password policy). A09 verification of rev 5 + ops rev 4 launched; A03 U-10..U-19 queued after A04.
- 2026-11-14 — A04 published 43 account & security artboards (DS v14, canvas v44). A03 launched on U-10..U-19.
- 2026-11-14 — A09 §12.10: Phase 4 package ready for PO acceptance pending PO-H/I/J (+R-7) and go-live gates; A05 AR-1/AR-2 and A10 SV-2a/RN-1a/RD-2/CI-1a launched.
- 2026-11-14 — A05 rev 5.1: AR-1, AR-2, R-10, R-11 applied.
- 2026-11-14 — A10 ops rev 5: SV-2a, RN-1a, RD-2, CI-1a applied. Phase 4 docs complete pending PO-H/I/J.
- 2026-11-14 — A03 U-10..U-19 specified (S-32 link pages, sessions, email change, deletion pending A05). A04 (17 boards) and A05 rev 6 (deletion + contract needs) launched.
- 2026-11-14 — Decision 46: PO-H B, PO-I no magic link, PO-J with conditions (R-7 accepted). ADR-010 and amendments 003/005/006 Accepted; 004 conditional on A08 spike.
- 2026-11-14 — A05 rev 6: immediate deletion (re-auth, 1 h purge, backup deletion list), throttles, argon2id, invite /set-password/{token}; ADR statuses Accepted. PO-L, PO-M new. A09 verification launched.
- 2026-11-14 — A04 published 17 account boards (canvas v45, DS v15). Waiting on A09 rev 6 check and PO answer on freezing account scope before A11 final QA.
- 2026-11-14 — A09 rev 6: ACCEPT WITH CHANGES; advice PO-L immediate+DL-1, PO-M no operator restore, R-12 accept (+DL-4), reject R-AE revert link (prefer EC-1). A05 and A10 fix runs launched.
- 2026-11-14 — A05 rev 6.1 applied; A00 spot-checked the edits (remaining bcrypt mentions are 'withdrawn' notes only). DL-4 = PO-N, EC-1 = PO-O.
- 2026-11-14 — A10 ops rev 6: deletion ledger off-server (age-encrypted, ≥21 days) + restore guard, argon2id sizing, failed sign-in alert, /set-password scrubbed. PO: alert channel, custody of age identity. All Phase 4 fix runs done; awaiting PO on freeze + PO-L/M/N/O.
- 2026-11-14 — Decision 47: PO-L..PO-O approved, account scope frozen. A05 rev 6.2 and A03 DL-4/EC-1 specs launched; then A04 boards, then A11 final QA.
- 2026-11-14 — A03 DL-4/EC-1 specs done (5 redraws); waiting on A05 rev 6.2 for 4 pending values before A04.
- 2026-11-14 — A05 rev 6.2: DL-4 /account-deletion/{token} (1 h), EC-1 /email-change-old/{token} (60 min), Phase 5 backlog B5-1..B5-9. H-17 log-scrub note for A10 deferred to gate G-6. A04 launched on 5 boards.
- 2026-11-14 — A04 redrew 5 boards (canvas v46); flagged a contrast bug (primary <a> buttons dark on dark) likely on other account boards. A11 final QA launched.
- 2026-11-14 — A11 final QA: READY WITH FIXES (0/4/9). F1/F2 dark-on-dark primary links and skip links (one DS CSS fix), F3 stale pending markers, F4 'Cancel the link' button outside the freeze (removed; cancel UI goes to Phase 5 backlog). A04 fix run launched.
- 2026-11-14 — A04 fixed F1–F4 (DS v16, canvas v47); A00 spot-checked local sources. Boards ready for Gate 2. Minors m4, m6–m9 deferred to build.
- 2026-11-14 — Decision 48: Gate 2 passed. A08 spike launched.
- 2026-11-14 — Decision 49: Phase 3 started; A04 prototype and A01 test kit launched; PO to recruit 5 developers.
- 2026-11-14 — A01 comprehension-test-kit.md: thresholds frozen (Key ≥4/5, Q1 ≥4/5, Q3/Q4 ≥3/5, graph-hard ≤2/5); round 2 with 5 new devs if Key ≤3; PO needs 8–10 candidates.
- 2026-11-14 — A04 prototype published: https://claude.ai/artifact/5Y3emzJbjy18CThbfagDg6 (Design canvas with Play; TestRun artboard for the 10-second test). Uses D5 vs D4 per current data; the new comprehension kit already uses D5, the old prototype-spec §5 answer key (D3/D2) is superseded. Not click-tested by any agent.
- 2026-11-14 — Sign-up form for the comprehension test created in the PO's Notion (private database + form view with anonymous submissions + tracking table). Artifact db was not used: outside viewers cannot write to an artifact's store. PO publishes the form link from Notion.
- 2026-11-14 — Decision 50: A08 spike CONFIRMED WITH CHANGES; ADR-004 am. 1 Accepted. A05 and A10 apply the changes.
- 2026-11-14 — A05 rev 6.3: A08 spike changes applied; ADR-004 am. 1 Accepted; open-questions §8 and G-6 closed.
- 2026-11-14 — A10 ops rev 7: ext-uv/ext-event boot check + CI gate, nofile 10000, ws. limits (PO decision O4 on Caddy), long-poll slots capped, VPS measurements M-1..M-6 for Phase 5. No agent running now.
- 2026-11-15 — Notion sign-up form completed in Chrome: 10 questions (Prénom, handler, déjà vu 2.0, créneaux, contact, consentement required), public link https://jiordiviera.notion.site/cc4538550f544e56afdb8eeacaa6f859, anonymous responses.
- 2026-11-15 — Decision 51: comprehension test skipped; Phase 5 build starts.
- 2026-11-15 — Decision 52: phase5/foundation branch from rewrite/v2, 1.x code removed there, Laravel at root + agent/; local commits allowed; project/ docs committed; staged files unstaged.
- 2026-11-15 — Decision 53: main checkout switched to phase5/foundation; leftovers moved to ~/workspace/personal/hookscope-1x-leftovers. A10 foundation relaunched in the main checkout.
- 2026-11-15 — A10 foundation done: Laravel 13.42, Inertia 3.7, React 19, Fortify, Reverb 1.13, Horizon, Pest (83 pass), Larastan 7, Go agent skeleton, FrankenPHP image, Compose, CI files. Placeholders listed (deploy policy script, some CI jobs, backup loop, heartbeat/sandbox/runner commands, VPS subnets). Proposed: @laravel/echo-react + pusher-js, actionlint. Folder rename to webhooktrap next.
- 2026-11-15 — Folder renamed to ~/workspace/personal/webhooktrap; worktrees repaired; lerd site webhooktrap.test linked with TLS (PHP 8.5, Node 22, pgsql, redis); workers horizon, reverb, schedule, vite running; / and /up return 200. Slice 1 (Identity + guest sessions) launched.
- 2026-11-15 — Decision 54: fontsource packages approved; A07 told to self-host fonts.
- 2026-11-15 — A07 brand assets committed (6c8b0e6): logos, favicons (svg/ico/apple-touch), 12 icons, Timetable tokens mapped to shadcn, fontsource Archivo + Martian Mono 5.3.0. A00 added PNG logo exports (bf1a800).
- 2026-11-15 — A07 landing S-01 committed (0bde6f6). Frontend build currently broken by A06's in-progress Identity refactor (Fortify routes removed); A06 told to regenerate Wayfinder and fix imports. Landing says 'Open source, MIT' while licence is undecided (decision 45) — flagged.
- 2026-11-15 — Slice 1 backend committed (55822d2, 3255884, 11661dd; build fix 35c39fe): 192 tests pass, Larastan 0 errors. WebAuthn ceremonies pending package approval (web-auth/webauthn-lib 5.x). Common-password list is a 100-entry seed. A09 review launched.
- 2026-11-15 — Decision 55: web-auth/webauthn-lib approved after A09 review.
- 2026-11-15 — A09 slice 1 review: SHIP WITH FIXES (F1 High pre-account takeover; F2/F3 Medium). Deviations (a) accepted on condition of F1, (b) accepted with F12. WebAuthn lib confirmed. A06 relaunched on fixes + WebAuthn.
- 2026-11-15 — Decision 56: 100k common-password list installed (SecLists/Burnett, MIT) with provenance; 192 tests pass.
- 2026-11-15 — A06 fixed F1–F12, WebAuthn ceremonies (webauthn-lib 5.3.12); 244 tests pass (7ba0729, 9cafe14, 743935c). New required keys IDENTITY_EMAIL_HMAC_KEY, IDENTITY_RECOVERY_CODE_KEY. A07 slice 1 UI launched.

## 2026-11-15 — Slice 1 UI (A07)

Done in 540bff0, d3b7829, 9554ab9. Screens S-02, S-17/17A/17B, S-18, S-24 to S-30, S-32, S-00 banner, S-03 waiting.
Build, check, types and 244 tests pass. Visual direction under review: PO found the Timetable look "basique, pas waouh";
A04 is exploring three landing directions, and the app screens will be restyled to the chosen one.

Follow-ups for A06 (backend):
- `POST /confirm-auth/{provider}` returns `Inertia::location` (plain redirect fails from Inertia).
- `csrfToken` prop so link pages work without JavaScript.
- Distinct error codes for OAuth cancelled vs failed.
- `requestedAt` and the RC-2 flag on `recoveryPending`.
- Reset page: email and 1.x flag; attempts-left count; action verb for generic recent-auth; linked-identity label for `reauth_mismatch`.
- `POST /sign-in/password` honours `next`.
- Remove unused `@laravel/passkeys` from package.json.
- TOTP QR encoder: check with a real authenticator app.
- Dev database: local test user `a07-preview@example.test`.

## 2026-11-15 — Slice 1 backend follow-ups (A06)

Done in 4bc655e, 012ceca, a268d88: 273 tests pass and phpstan reports 0 errors.
Covered: Inertia::location on confirm-auth, csrfToken on link pages, oauth_cancelled/oauth_failed,
recoveryPending {requestedAt, completesAt, extended}, reset/S-27/S-28 props, safe `next` (stricter
safeNext), and @laravel/passkeys removed.

Open:
- Copy for S-28 on oauth_failed is a placeholder ("That didn't work. Nothing changed. Try again."), to confirm.
- The Security page does not show `errors.form` codes yet (oauth_failed, identity_in_use).
- A09 to review the `next` handling and the confirm-reason allow-list in the next security pass.

## 2026-11-15 — Slice 1 restyle and form errors (A07)

Done in 9ea8f44 and 1c11dab. All identity screens are in the terminal direction, dark by default with light kept.
The auth pages follow the PO's reference: a narrow sans form plus a run panel, with no fake testimonial or logos.
The official logo is used everywhere, and provider marks come from svgl TSX in components/brand/provider-logos.tsx.
Form errors go through the shared hook use-client-validation:
- stale errors are cleared;
- autofill is read from the DOM on submit;
- field errors show inline only, with a summary for form-level errors.

New copy to confirm:
- "Check the N fields below: …"
- "Your handler returned 200. The database disagreed."

Open:
- An empty submit on the code-entry forms only moves focus, with no message.
- Local test users screens@ and screens2@example.test are in the dev DB.

## 2026-11-15 — Slice 2 backend (A06) and slice 1 delta fixes

Done in f0d0b85, 875eb5a and 42928a2: 305 tests pass and phpstan reports 0 errors.
Projects:
- `project_key` is CSPRNG base62.
- A guest workspace has exactly one project, enforced by a trigger and a partial unique index.
- Ownership goes through `Projects::owned()` and gives a uniform 404.
- Counters are allocated under row lock, with a daily quota.
- Delete is a soft delete, purged after 24 h.

The account-deletion participant hook closes F13 for workspace tables.
The capture URL seam is `ProjectCaptureUrls`, bound to a null object until the Capture slice.
A09 delta fixes D1–D3 are done: history encryption plus clearHistory and no-store, POST connect, masked email.

PO decisions (decision 58, kept as proposed):
- Account quota placeholders: 5000 captures/day and 512 MiB per project (`config/workspace.php`).
- Retention options: capture 1/7/14/30 days, runs 7/30/90 days.
- There is no project restore route; restoring is operator-only within 24 h.

## 2026-11-15 — Slice 2 UI (A07)

Done in 152c2cf. Shell project switcher, pending capture-URL chip, S-15 list and new project, S-16 Capture/CI/Budgets/Danger, guest Danger page.
A00 fix 3fc45f6: route `project.settings.name` renamed to `.rename`. The generated Wayfinder member `settings.name` hit the read-only Function.name and blanked every page importing `@/routes/project`.
Copy proposals are in resources/js/lib/workspace/copy.ts. Section tabs, the agent pill and ⌘K wait for their slices.
Slice 2 still needs A11 verification before it closes.

## 2026-11-15 — Slice 3 runtime closed (A10), slice 4 backend (A06)

- **HAProxy (decision 61):** A09 rev 2 findings S3-17 to S3-24 are applied (a0ae87d, 4451d22, 96df80d):
  - a separate gate for incomplete bodies;
  - 8 ingest threads;
  - JSON on every HAProxy answer;
  - redispatch across ingest restarts;
  - policy pins P-12 to P-17;
  - the secret mounted only on app, ingest and haproxy.
- **Pre-existing blocker fixed:** the FrankenPHP file capability stopped the binary from starting under `cap_drop: ALL`.
- **Open for A06:** a capture that hits the 60 s PHP limit ends on PHP's fatal page instead of the ingest answer.
- **Host change for the PO:** the `in.` vhost change on the host Caddy, at C7 (contabo plan §4.2a).
- **Slice 4 backend (69d4cbf, ff2ac4b):** stream, detail, raw download, live triggers and channel auth; 418 tests pass.
- **Decision 62:** laravel-echo, @laravel/echo-react and pusher-js are installed.

## 2026-11-15 — Agent connection shipped on the branch

- **Server:** A06 (8f99210..da6cc9b, 0a088b4, 2681616).
- **Go client:** A08 (cba1cb1, 47b5d76, f71c7a5, 3d412ca).
- **Ops:** A10 (1c10f9e).
- **Real end to end, run locally:** agent paired → run created → replayed against the refhandler → findings at `/r/{runId}`.
- **A09 review:** BLOCK on AC-1 (probe shell interpolation), then SHIP WITH FIXES after the fixes (5bd2e4e). Residuals:
  - an id may start with `-`;
  - a global long-poll pool can be filled by many workspaces;
  - the final document doesn't compare mode or signing.
- **Before a Windows agent release:** run the Windows tests on a Windows CI runner, and document the `/V:ON` `!` behaviour.
- **Test count:** 538 PHP tests; the Go suite is green.
