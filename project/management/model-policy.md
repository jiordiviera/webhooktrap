# Model Selection Policy — Webhook Trap 2.0

**Adopted:** 2026-11-11 (PO, decision 22) · **Owner:** A00

For every delegation, A00 assesses how complex the task is and picks the
model that fits. Being cheaper is never a reason to give a critical task
(security, architecture, independent review) to a weaker model.

## Models available in this environment

The Agent tool can run four models.

| Tier | Model | Use for |
| ---- | ----- | ------- |
| Top tier, deep reasoning | **Claude Opus 5.5** (`opus`) | Architecture and structural decisions, product strategy and discovery, security analysis and fixes, complex audits, technical trade-offs, complex UX, independent review of critical code |
| Creative and design | **No model here is documented as design-specialised** (see *Limitation*) → **Opus 5.5** by default | Art direction, branding, creative concepts, UX/UI exploration, prototyping in Claude Design |
| Fast and capable | **Claude Sonnet 5.5** (`sonnet`) | Implementing well-specified features, writing tests, targeted refactoring, documentation, simple fixes |
| Fast, lightweight | **Claude Haiku 5.5** (`haiku`) | Repetitive mechanical tasks, inventories, file lookups, format checks with explicit rules |
| Unclassified | **Claude Fable 5.2** (`fable`) | Not used until its profile is established (see *Limitation*) |

### Limitation (rule 5)

None of the documentation available to A00 describes a model as specialised
in creativity or design. A00 does not assume Fable 5.2 is that model.
Creative and design work therefore runs on Opus 5.5 by default.

If the PO wants to test Fable 5.2 on creative work, the proposed method is to
run the same design brief on both models and compare the results blind.

**PO decision (2026-11-11):** no blind trial of Fable 5.2. Creative and design
work stays on Opus 5.5.

## Complexity levels

| Level | Criteria | Default model |
| ----- | -------- | ------------- |
| **C4 Critical** | Security, data or production impact; structural decisions; independent review of the above | Opus 5.5, mandatory |
| **C3 Complex** | Multi-file reasoning, ambiguous scope, creative exploration, synthesis of many sources | Opus 5.5 |
| **C2 Specified** | Clear brief, clear acceptance criteria, bounded files | Sonnet 5.5 |
| **C1 Mechanical** | Deterministic rules, no judgement | Haiku 5.5 |

## Execution rules

1. Every brief states the level and the model, with a one-line reason.
2. An agent may recommend a model change in its report. A00 records the
   recommendation and decides.
3. An independent review never runs from the author's conclusions alone,
   and runs at least at the author's tier.
4. If the ideal model is unavailable, A00 says so and records the
   alternative chosen.
5. Every agent run is recorded in the run log below.

## Run log

Before this policy, every agent inherited the session model, Opus 5.5. That
was checked against the policy afterwards: all levels below call for Opus,
so no run needs to be redone.

| Date | Agent | Task | Level | Model | Reason | Change |
| ---- | ----- | ---- | ----- | ----- | ------ | ------ |
| 2026-11-11 | A00 | Orchestration, audit, ADRs, reviews | C4 | Opus 5.5 | Structural and governance decisions | — |
| 2026-11-11 | A01 | Phase 1 product (superseded) | C3 | Opus 5.5 (inherited) | Strategy | — |
| 2026-11-11 | A02 | Phase 1 brand (superseded) | C3 | Opus 5.5 (inherited) | Creative | — |
| 2026-11-11 | A09 | 1.x hotfix P-01 – P-04 | C4 | Opus 5.5 (inherited) | Security | — |
| 2026-11-11 | A11 | Hotfix verification | C4 | Opus 5.5 (inherited) | Independent security review | — |
| 2026-11-11 | A01 | Phase 1b research, concepts, positioning | C3 | Opus 5.5 (inherited) | Discovery and strategy | — |
| 2026-11-11 | A09 | Hotfix P-11, P-12, tests, DNS rebinding | C4 | Opus 5.5 (inherited) | Security | — |
| 2026-11-11 | A02 | Brand territories | C3 | Opus 5.5 (inherited) | Creative | — |
| 2026-11-11 | A03 | UX per territory + dataset | C3 | Opus 5.5 (inherited) | Complex UX | — |
| 2026-11-11 | A11 | Hotfix extension verification | C4 | Opus 5.5 (inherited) | Independent security review | — |
| 2026-11-11 | A00 | P-17 fix | C2 | Opus 5.5 | Two-line change done inline, cheaper than delegating | — |
| 2026-11-11 | A11 | P-17 verification | C4 | Opus 5.5 (inherited) | Independent security review | — |
| 2026-11-11 | A04 ×3 | Directions in Claude Design | C3 | Opus 5.5 (inherited) | Prototyping in Claude Design; no design-specialised model available | — |
| 2026-11-11 | A01 | Fit review of the directions | C3 | Opus 5.5 (inherited) | Product judgement | done |
| 2026-11-11 | A11 | Static QA of the canvases | C3 | Opus 5.5 (inherited) | Format compliance and contrast; Sonnet would also fit | done |
| 2026-11-11 | A01 | Verify provider response budgets | C3 | Opus 5.5 (explicit) | Facts shown on screen as authoritative; research quality matters | stopped by PO |
| 2026-11-11 | A03 | Timetable v2 UX spec (borrowings, new screens, test protocol) | C3 | Opus 5.5 (explicit) | Complex UX synthesis across three directions | stopped by PO |
| 2026-11-14 | A01 | Verify provider response budgets (relaunch) | C3 | Opus 5.5 (explicit) | Facts shown on screen as authoritative | done |
| 2026-11-14 | A03 | Timetable v2 UX spec (relaunch) | C3 | Opus 5.5 (explicit) | Complex UX synthesis across three directions | done |
| 2026-11-14 | A02 | Timetable logo exploration (3–4 concepts) in Claude Design | C3 | Opus 5.5 (explicit) | Creative identity work; PO kept Opus for design | done |
| 2026-11-14 | A03 | Regenerate dataset with verified budgets and v2 copy | C2 | Sonnet 5.5 | Fully specified scripted update; first C2 run under the policy | done |
| 2026-11-14 | A04 | Timetable v2 in Claude Design (system + canvas update) | C3 | Opus 5.5 (explicit) | Prototyping in Claude Design, many interdependent boards | done |
| 2026-11-14 | A02 | Visual identity PDF (French), Timetable + Twice | C3 | Opus 5.5 (explicit) | Brand guidelines; layout quality matters | done |
| 2026-11-14 | A11 | Static QA of Timetable v2 | C3 | Opus 5.5 (explicit) | Independent review at the author's tier | done |
| 2026-11-14 | A04 | Fix 7 Minor QA findings on Timetable v2 | C2 | Sonnet 5.5 | Fully specified fixes with evidence and suggested fix each | done |
| 2026-11-14 | A01 | Validation plan (interviews, prototype spec, comprehension test kit) | C3 | Opus 5.5 (explicit) | Discovery design; drives the go/no-go | done |
| 2026-11-14 | A06 | Go CLI validation prototype (`spike/proving-cli`) | C2 | Sonnet 5.5 | Well-specified throwaway prototype; security-relevant parts reviewed independently by A09 on Opus | done |
| 2026-11-14 | A09 | Independent security review of the Go CLI prototype | C4 | Opus 5.5 (explicit) | Code run by outside participants; handles signing secrets and network requests; review above the author's tier | done: SAFE WITH FIXES |
| 2026-11-14 | A06 | Fix A09 findings F1–F6 on the Go prototype, document F7–F8 | C2 | Sonnet 5.5 | Fully specified fixes; A09 re-verifies on Opus | done |
| 2026-11-14 | A09 | Re-verify fixes F1–F8 on the Go prototype | C4 | Opus 5.5 (explicit) | Independent security re-check | done: SAFE WITH FIXES (1 new Medium, 1 Low) |
| 2026-11-14 | A06 | Fix N1–N2 and add README limits on the Go prototype | C2 | Sonnet 5.5 | Fully specified fixes; A09 re-verifies on Opus | done |
| 2026-11-14 | A09 | Third pass: re-verify N1–N2 on the Go prototype | C4 | Opus 5.5 (explicit) | Independent security re-check | done: SAFE WITH FIXES (2 Low, non-blocking) |
| 2026-11-14 | A11 | Gate 1 requirements testability review | C3 | Opus 5.5 (explicit) | Multi-document review that gates Phase 2 | done: READY WITH CLARIFICATIONS |
| 2026-11-14 | A06 | Fix L1–L2 on the Go prototype | C2 | Sonnet 5.5 | Fully specified small fixes; A09 verifies on Opus | done (`13b6c19`) |
| 2026-11-14 | A09 | Verify L1–L2 and give final verdict on the prototype | C4 | Opus 5.5 (explicit) | Independent security re-check | done: L2 FIXED, L1 PARTIAL |
| 2026-11-14 | A01 | Scenario and judge spec (resolves A11 ambiguities), proxy validation protocol | C3 | Opus 5.5 (explicit) | Defines what counts as a finding; drives copy, screens and tests | done |
| 2026-11-14 | A03 | Phase 2: information architecture, navigation, accessibility spec, golden-file rules | C3 | Opus 5.5 (explicit) | Structural UX work gating Gate 2 | done |
| 2026-11-14 | A06 | Complete L1 sanitiser (Unicode property-based) | C2 | Sonnet 5.5 | Finishes the fix approved in decision 33 | done (`6d1f107`) |
| 2026-11-14 | A09 | Final L1 check and overall prototype verdict | C4 | Opus 5.5 (explicit) | Independent security re-check | done: L1 FIXED; GO internal, GO with conditions for outside participants |
| 2026-11-14 | A03 | Apply A01 §16 changes; user flows and screen specs | C3 | Opus 5.5 (explicit) | Structural UX gating Gate 2 | done (14 flows, 24 screens) |
| 2026-11-14 | A01 | Proxy validation: candidate repository list (search only) | C2 | Sonnet 5.5 | Pre-registered, fully specified procedure | done (15 main + 10 reserve; 10 ambiguities) |
| 2026-11-14 | A05 | Phase 4 early start: agent ADR, ADR amendments, domain model, schema, contracts | C4 | Opus 5.5 (explicit) | Structural architecture decisions | done (ADR-010 + 4 amendments Proposed, 5 docs) |
| 2026-11-14 | A09 | Review C-3 (probe values on the hosted server) | C4 | Opus 5.5 (explicit) | Data-exposure decision gating the build | done: SAFE WITH CHANGES (PV-1..PV-14) |
| 2026-11-14 | A04 | Timetable v3 in Claude Design from A03 screen specs (Gate 2 prototype) | C3 | Opus 5.5 (explicit) | Prototyping in Claude Design; PO kept Opus for design | done (67 boards, 50 archived) |
| 2026-11-14 | A09 | Threat model for ADR-010 and amendments (S-1..S-14) | C4 | Opus 5.5 (explicit) | Security design gating ADR acceptance | done: all 10 docs ACCEPT WITH CHANGES |
| 2026-11-14 | A10 | Hosting and operations options (H-1..H-9) | C3 | Opus 5.5 (explicit) | Structural trade-off feeding a PO decision; no production impact | done (recommends A: EU VPS + Compose + runner VPS) |
| 2026-11-14 | A11 | Static QA of Timetable v3 against Phase 2 specs | C3 | Opus 5.5 (explicit) | Independent review at the author's tier | done: READY WITH FIXES (0 Blocker, 13 Major, 18 Minor) |
| 2026-11-14 | A05 | Revise Phase 4 package with A09 changes and decision 39 | C4 | Opus 5.5 (explicit) | Structural architecture revision | done (A09 re-check items R-A..R-H) |
| 2026-11-14 | A10 | Contabo deployment plan (shared 1.x VPS + runner VM) and Compose profile, on paper | C3 | Opus 5.5 (explicit) | Production-adjacent planning; no server access | done (plan, Compose profile, 1.x decommission runbook) |
| 2026-11-14 | A04 | Fix A11 v3 Majors (except PV copy) and listed Minors in Claude Design | C2 | Sonnet 5.5 | Fully specified fixes with evidence; A11 re-checks | done (M1–M9, M12, M13 fixed; M10, M11 held) |
| 2026-11-14 | A06 | Proxy validation: container harness + pilot runs (2 repos) | C4 | Opus 5.5 (explicit) | Runs untrusted third-party code; isolation must be right | done (1 baseline_rejected, 1 skipped rule 6) |
| 2026-11-14 | A01 | Update scenarios-and-judge spec for PV-1..PV-14 and A09 Phase 4 changes | C3 | Opus 5.5 (explicit) | Defines product rules feeding copy, screens and tests | done (Revision 2) |
| 2026-11-14 | A03 | Update screen specs/flows for PV copy (M10/M11), share token, pairing confirmation, provenance | C3 | Opus 5.5 (explicit) | Structural UX changes from security decisions | done (~50 boards to redraw) |
| 2026-11-14 | A10 | Rebase deployment plan and Compose profile on laravel-inertia-vps-template | C3 | Opus 5.5 (explicit) | Production deployment design on a shared VPS; paper only | done (+ ci-cd.md) |
| 2026-11-14 | A04 | Redraw boards listed by A03 (PV copy, share token, pairing, provenance) | C2 | Sonnet 5.5 | Fully specified board list from A03 | done (51/52 redrawn, canvas v43) |
| 2026-11-14 | A05 | Add WebAuthn (passkeys, security keys) and resolve C-7/C-8 in Phase 4 package | C4 | Opus 5.5 (explicit) | Authentication design | done (rev 3; PO-H, PO-I, PO-J open) |
| 2026-11-14 | A09 | Re-check A05 rev 2–3 (R-A..R-P), runner design, WebAuthn, 1.x import | C4 | Opus 5.5 (explicit) | Independent security re-check gating ADR acceptance | done: no REJECT; changes for A05 and A10 |
| 2026-11-14 | A05 | Rev 4: apply A09 re-check changes (runner, WebAuthn counter, etc.) | C2 | Sonnet 5.5 | Fully specified change list from A09; A09 verifies | done |
| 2026-11-14 | A10 | Apply A09 re-check changes to ops docs (deploy.sh, logs, shared VPS) | C2 | Sonnet 5.5 | Fully specified change list from A09 | done (ops rev 3) |
| 2026-11-14 | A03 | Account-security screens U-1..U-9 (sign-in, WebAuthn, 2FA, recovery, 1.x returning users) | C3 | Opus 5.5 (explicit) | Security-sensitive UX | done (S-24..S-31, 21 boards; contract gaps for A05) |
| 2026-11-14 | A09 | Verify A05 rev 4 and A10 ops rev 3 against §12 | C4 | Opus 5.5 (explicit) | Independent security verification | done: A05 ACCEPT; A10 ACCEPT WITH CHANGES (SV-2 wrong) |
| 2026-11-14 | A04 | Draw 21 account-security boards (S-02, S-17A/B, S-24..S-31) | C2 | Sonnet 5.5 | Fully specified board list from A03 | done (43 artboards, canvas v44) |
| 2026-11-14 | A10 | Fix A09 §12.9 ops items (SV-2 Caddy route order, CI-2 policy, RN-1 proxy allowlist, HSTS, logs, ENG-2) | C2 | Sonnet 5.5 | Fully specified fixes; A09 verifies | done (ops rev 4) |
| 2026-11-14 | A05 | Rev 5: A09 editorial follow-ups + A03 account contract gaps | C4 | Opus 5.5 (explicit) | Authentication contracts | done (sessions in MVP; R-Q..R-X for A09; U-10..U-19 for A03) |
| 2026-11-14 | A09 | Verify A05 rev 5 (R-Q..R-X) and A10 ops rev 4 | C4 | Opus 5.5 (explicit) | Independent security verification | done: A05 ACCEPT (AR-1, AR-2 wording); A10 ACCEPT WITH CHANGES; package ready for PO pending PO-H/I/J |
| 2026-11-14 | A03 | Account specs U-10..U-19 from A05 rev 5 (sessions, email change, unblock, link interstitials, deletion) | C3 | Opus 5.5 (explicit) | Security-sensitive UX | done (17 boards; contract needs for A05) |
| 2026-11-14 | A05 | Apply AR-1, AR-2 wording | C2 | Sonnet 5.5 | Two specified edits; no further review round per A09 | done (rev 5.1) |
| 2026-11-14 | A10 | Apply SV-2a, RN-1a, RD-2, CI-1a | C2 | Sonnet 5.5 | Specified fixes; gate G-6 | done (ops rev 5) |
| 2026-11-14 | A04 | Draw 17 account boards (U-10..U-19) | C2 | Sonnet 5.5 | Fully specified board list | done (canvas v45, DS v15) |
| 2026-11-14 | A05 | Rev 6: account deletion + A03 account contract needs | C4 | Opus 5.5 (explicit) | Authentication and data-deletion design | done (PO-L, PO-M open; R-Y..R-AG for A09; U-20..U-27 for A03) |
| 2026-11-14 | A09 | Verify A05 rev 6 (R-Y..R-AG: deletion, throttles, argon2id, invites) | C4 | Opus 5.5 (explicit) | Independent security verification | done: ACCEPT WITH CHANGES (DL-1..DL-3, TH-1, SE-1, ED-1) |
| 2026-11-14 | A05 | Rev 6.1: DL-1, DL-2, DL-3 (app side), TH-1, SE-1, ED-1 | C2 | Sonnet 5.5 | Specified changes from A09 | done (spot-checked by A00) |
| 2026-11-14 | A10 | DL-3 (ledger off-server, re-apply before start), argon2id memory budget, failed sign-in alert | C2 | Sonnet 5.5 | Specified changes from A09 | done (ops rev 6) |
| 2026-11-14 | A05 | Rev 6.2: record PO-L..PO-O as decided (DL-4, EC-1 requirements) | C2 | Sonnet 5.5 | Specified edits | done (Phase 5 backlog §9) |
| 2026-11-14 | A03 | Specs for DL-4 (email-confirmed deletion) and EC-1 (old-address confirmation) only | C2 | Sonnet 5.5 | Bounded spec addition under the freeze | done (5 redraws) |
| 2026-11-14 | A04 | Redraw 5 boards for DL-4 / EC-1 | C2 | Sonnet 5.5 | Fully specified, bounded | done (canvas v46) |
| 2026-11-14 | A11 | Final static QA of all Timetable boards before Gate 2 | C3 | Opus 5.5 (explicit) | Independent review at the author's tier | done: READY WITH FIXES (0 Blocker, 4 Major, 9 Minor) |
| 2026-11-14 | A04 | Fix final QA F1–F4 and Minors; refresh canvas DS copy | C2 | Sonnet 5.5 | Fully specified fixes; A11 spot-checks | done (DS v16, canvas v47; m4,m6–m9 deferred) |
| 2026-11-14 | A08 | Reverb/Echo spike for ADR-004 amendment 1 (guard × channel, ordering, client events off, long-poll capacity) | C3 | Opus 5.5 (explicit) | Realtime design validation with code | done: CONFIRMED WITH CHANGES (22 tests pass) |
| 2026-11-14 | A04 | Phase 3 navigable prototype of key flows (Claude Design) | C3 | Opus 5.5 (explicit) | Prototyping in Claude Design; PO keeps Opus for design | done (artifact 5Y3emzJbjy18CThbfagDg6, not click-tested) |
| 2026-11-14 | A01 | Update 10-second comprehension test kit to Gate 2 boards and prototype | C2 | Sonnet 5.5 | Adapting an existing, specified kit | done (comprehension-test-kit.md) |
| 2026-11-14 | A05 | Apply A08 spike changes to event catalogue and contracts | C2 | Sonnet 5.5 | Specified changes | done (rev 6.3) |
| 2026-11-14 | A10 | Apply A08 spike changes (ext-uv/ext-event, Reverb and proxy connection limits) | C2 | Sonnet 5.5 | Specified changes | done (ops rev 7) |
| 2026-11-15 | A10 | Phase 5 foundation (first launch, in a worktree) | C3 | Opus 5.5 (explicit) | Sets the structure every slice builds on | stopped by A00: PO asked to work in the main checkout, no changes made |
| 2026-11-15 | A10 | Phase 5 foundation in the main checkout: Laravel 13 + Inertia React, modules, arch tests, Docker/CI, Go agent skeleton | C3 | Opus 5.5 (explicit) | Sets the structure every slice builds on | done (5 commits, 83 tests pass) |
| 2026-11-15 | A06 | Slice 1 backend: Identity (accounts, OAuth, TOTP, WebAuthn, sessions, recovery) + guest sessions | C4 | Opus 5.5 (explicit) | Authentication is C4 per agent registry | done except WebAuthn ceremonies (192 tests pass) |
| 2026-11-15 | A07 | Brand assets into the app: Timetable tokens, logo, favicon, icons, fonts | C2 | Sonnet 5.5 | Specified port of the Gate 2 design system | done (6c8b0e6) |
| 2026-11-15 | A07 | Landing page (S-01) replacing the starter welcome page | C2 | Sonnet 5.5 | Fully specified screen with Gate 2 boards | done (0bde6f6) |
| 2026-11-15 | A09 | Security review of slice 1 (Identity + guest sessions) | C4 | Opus 5.5 (explicit) | Independent review of authentication code | done: SHIP WITH FIXES (1 High, 2 Medium, 9 Low) |
| 2026-11-15 | A06 | Slice 1 fixes F1–F12 + WebAuthn ceremonies (web-auth/webauthn-lib 5.x) | C4 | Opus 5.5 (explicit) | Authentication security fixes | done (244 tests pass) |
| 2026-11-15 | A07 | Slice 1 UI: sign-in, register, verify, reset, 2FA, confirm-auth, account security, link pages, guest capture waiting (from Gate 2 specs) | C3 | Opus 5.5 (explicit) | Security-sensitive UI across many screens | done (540bff0, d3b7829, 9554ab9; 244 tests pass; A06 follow-ups listed in progress.md) |
| 2026-11-15 | A04 | Three landing visual directions (product-centred, terminal, bold) after PO feedback "basique, pas waouh"; standalone HTML mockups in scratchpad, no repo changes | C2 | Opus 5.5 (explicit) | Design exploration, taste-heavy | done (3 HTML mockups in scratchpad/directions; recommends D3 + D1 board below hero) |
| 2026-11-15 | A06 | Slice 1 backend follow-ups from A07 (Inertia::location on confirm-auth, csrfToken on link pages, OAuth cancel/fail codes, recoveryPending fields, reset/S-28 props, safe `next`, drop @laravel/passkeys) | C3 | Opus 5.5 (explicit) | Auth flows, open-redirect risk | done (4bc655e, 012ceca, a268d88; 273 tests pass) |
| 2026-11-15 | A07 | Landing rebuilt in the terminal direction (decision 57) from direction-2-terminal.html | C2 | Opus 5.5 (explicit) | Faithful port of a taste-heavy mockup | done (19973a9) |
| 2026-11-15 | A07 | Restyle slice 1 app screens and shell in the terminal direction; shared tokens in app.css | C2 | Opus 5.5 (explicit) | Visual layer only, behaviour unchanged | done (9ea8f44, 1c11dab; 273 tests; auth pages per PO reui reference; shared form-error hook) |
| 2026-11-15 | A06 | Slice 2: workspace / project ownership (S-15, S-16, shell switcher data, ownership policy, deletion cascade) | C3 | Opus 5.5 (explicit) | Authorization boundary for every later slice | done (f0d0b85, 875eb5a, 42928a2 incl. A09 D1-D3; 305 tests) |
| 2026-11-15 | A09 | Slice 1 delta security review (743935c..HEAD): next, props leaks, link CSRF, OAuth errors, WebAuthn client | C3 | Opus 5.5 (explicit) | Security-relevant auth changes | done (SHIP WITH FIXES: D1 Medium, D2-D3 Low; queued to A06 after slice 2) |
| 2026-11-15 | A07 | Slice 2 UI: S-15 projects, S-16 settings, shell project switcher + URL chip | C2 | Opus 5.5 (explicit) | UI on a reviewed contract | done (152c2cf; route fix 3fc45f6 by A00) |
| 2026-11-15 | A06 | Slice 3: capture URL, ingest /c/{key}, capture persistence, quotas, DL-2 eviction, rotate | C4 | Opus 5.5 (explicit) | Public unauthenticated ingest surface | done (f7bcf66, dc81f97; 356 tests; live ingest smoke OK) |
| 2026-11-15 | A11 | Verification of slices 1 and 2 (spec coverage, full checks, browser exploratory, a11y spot-check) | C2 | Sonnet 5.5 | Verification, read-only on code | done (PASS WITH ISSUES both; F-01..F-17 in project/qa) |
| 2026-11-15 | A07 | User-chosen layout (top bar / sidebar expanded / collapsed), Preferences page, cookie storage (decision 59) | C2 | Opus 5.5 (explicit) | App shell, every page | done (442d89e; full test run to recheck after slice 3 lands) |
| 2026-11-15 | A04-M | Landing motion design (decision 59), CSS/WAAPI only, reduced-motion safe | C2 | Opus 5.5 (explicit) | Taste-heavy motion work | done (1c6a9c6; no new package; Lighthouse on prod build still to run) |
| 2026-11-15 | A09 | Slice 3 ingest security review (invariant, redaction, proxy trust, DL-2, quotas, multipart, rate limit) | C4 | Opus 5.5 (explicit) | Main public unauthenticated surface | done (SHIP WITH FIXES: S3-1..S3-4 Medium; app fixes to A06, runtime to A10) |
| 2026-11-15 | A07 | A11 findings F-01, F-02, F-04 to F-13 (frontend, branded error pages) | C2 | Opus 5.5 (explicit) | QA fixes | done (0ba33fc; full suite to recheck after A06 F-03 lands) |
| 2026-11-15 | A06 | Slice 3 security fixes S3-1, S3-2, S3-5 to S3-13 plus QA F-03, F-17 | C4 | Opus 5.5 (explicit) | Public ingest hardening | done (ff62754, f99bb03, 6933ca4; 389 tests; guest cap 50 MiB approved, decision 60) |
| 2026-11-15 | A10 | Ingest runtime: S3-3 multipart off on ingest pool, S3-4 connection cap and separate ingest pool, proxy backstop limit, trust mode in image | C3 | Opus 5.5 (explicit) | Runtime and proxy config | done (364825d, 1c50937; S3-3 closed, S3-4 partial: per-address cap needs PO decision O5) |
| 2026-11-15 | A10 | HAProxy in both profiles: per-address connection cap and backstop rate limit (decision 61) | C3 | Opus 5.5 (explicit) | New edge component | done (be40efe, 388c0bc; HAProxy between app Caddy and ingest; A09 to review) |
| 2026-11-15 | A09 | Review of runtime + HAProxy config and the residual slow-sender trade-off | C3 | Opus 5.5 (explicit) | New edge component | done (f693825; OK WITH CHANGES: S3-17 Medium blocks G-7; S3-18..S3-24 to A10/A06) |
| 2026-11-15 | A10 | A09 rev 2 findings S3-17 to S3-24 (incomplete-body gate, 8 ingest threads, JSON errors, policy draft, secret mounts) | C3 | Opus 5.5 (explicit) | Go-live gate G-7 | done (a0ae87d, 4451d22, 96df80d; G-7 S3-17 closed by test; FrankenPHP cap fix) |
| 2026-11-15 | A06 | Slice 4 backend: capture stream S-03, delivery detail S-04, raw download, live broadcast + channel auth, guest meter | C3 | Opus 5.5 (explicit) | Untrusted content and channel auth | done (69d4cbf, ff2ac4b; 418 tests; echo transport package awaits PO) |
| 2026-11-15 | A07 | Slice 4 UI: stream S-03, detail S-04, Echo transport for Live Props, rotate throttled state | C3 | Opus 5.5 (explicit) | Untrusted content rendering, live | done (4d34173; live verified with curl; 427 tests) |
| 2026-11-15 | A10 | Horizon supervisors for broadcasts and maintenance queues + queue coverage check | C1 | Opus 5.5 (explicit) | Small config fix | done (12fa6fc; 427 tests; queue coverage arch test) |
| 2026-11-15 | A07 | Run board S-11 as S-14 /r/example from realistic data, replay animation, deep links /D{n} /F{n} (decision 63) | C3 | Opus 5.5 (explicit) | Flagship screen | done (ff6ea64; 431 tests; result column fixed in beaf773) |
| 2026-11-15 | A06 | Packs + capture derivation (provider, event type, object ids, GRP-1..4, SHP-1), re-derive command | C3 | Opus 5.5 (explicit) | Parses hostile bodies | done (536770d, 32b45b3; 464 tests; packs/ at repo root shared with engine) |
| 2026-11-15 | A08 | Go engine core + local CLI: plan, signing, execute+guard, probes, judge, scrub, render, `webhooktrap test`; golden run vs buggy test handler | C4 | Opus 5.5 (explicit) | Core product logic, secrets local | done (091ba3b..65e1785; golden Run #3 finds F1-F4, fixed handler passes; yaml/sql driver modules proposed) |
| 2026-11-15 | A07 | Surface providers, groups and Decoded tab in the stream (packs) | C2 | Opus 5.5 (explicit) | Visible UI | done (394a9da) |
| 2026-11-15 | A06 | Agent /agent/v1 server side (pairing, Ed25519 auth, confirm, hello, jobs long-poll, capture fetch, observations, final) + Run storage, /r/{runId}, runs list, draft (decision 64) | C4 | Opus 5.5 (explicit) | New authenticated machine surface | done (8f99210..da6cc9b; 502 tests; real e2e: Run #1 F1-F5 at /r/{runId}) |
| 2026-11-15 | A08 | yaml.v3 + Go agent client: pair, auth, confirm, agent mode, jobs, upload; e2e against webhooktrap.test | C4 | Opus 5.5 (explicit) | Secrets and target guard on dev machines | done (cba1cb1, 47b5d76; e2e pending A06 server) |
| 2026-11-15 | A09 | Agent connection security review (server + Go client) | C4 | Opus 5.5 (explicit) | New machine surface | done (8f20675; BLOCK on Go connected mode: AC-1 High shell injection via probe templates) |
| 2026-11-15 | A07 | Real run UI: Run/Show live, Runs/Index, Runs/Draft + Prove, pairing panel; e2e recording | C3 | Opus 5.5 (explicit) | Flagship flow | done (9d1ab71; real run from the browser: 4 findings, exit 1; 543 tests) |
| 2026-11-15 | A08 | Agent review fixes, Go side: AC-1 (no shell interpolation), AC-2, AC-8, AC-9, AC-13, AC-15, insecure-dev-origin | C4 | Opus 5.5 (explicit) | Command injection fix | done (f71c7a5, 3d412ca; AC-1 fixed, env-var only probes; Windows tests compiled not run) |
| 2026-11-15 | A06 | Agent review fixes, server side: AC-3..AC-8, AC-10, AC-12, AC-14, dev builds, deployment field | C4 | Opus 5.5 (explicit) | Auth and deletion integrity | done (0a088b4, 2681616; 538 tests) |
| 2026-11-15 | A10 | Agent ops follow-ups: pairing key as file secret, LONGPOLL_HTTP_WORKERS tied to num_threads, dev-build guard P-18, session pooling P-19, G-6 table (1c10f9e); then identity keys to file secrets | C2 | Opus 5.5 (explicit) | Secrets handling | done (1c10f9e, 163b51b, e7c5b55: all secrets as files, P-20; identity keys off ingest after A06 9224c67) |
| 2026-11-15 | A09 | Re-review of agent connection fixes to lift BLOCK | C4 | Opus 5.5 (explicit) | Gate | done (5bd2e4e; BLOCK lifted, SHIP WITH FIXES; Windows CI run gates a Windows release) |
| 2026-11-15 | A06 | Real-run backend gaps: agentPill, /agents pages, per-delivery result, planned count, project key, a 500 during run | C2 | Opus 5.5 (explicit) | Follow-ups | done (d5c6578; 550 tests) |
| 2026-11-15 | A07 | Wire agentPill, Agent pages, per-delivery result, D8 of 17, project links | C2 | Opus 5.5 (explicit) | UI wiring | done (3fc70b3; real e2e Run #4 with pass rows, D8 of 17, pill states) |
| 2026-11-15 | A10 | Deploy prep C2: CI and signed images on GitHub v2, real deploy tooling, plan updated with audit facts | C3 | Opus 5.5 (explicit) | Supply chain and deploy | done (CI + signed images green on GitHub; deploy.sh, policy, backups real; C3 open list to PO) |
| 2026-11-17 | A06 | EngineProcess slice 1: socket client, strict reply decoder, Runs/Draft plan preview, delivery_count at launch; A00 review found the `once` scenario gap | C4 | Opus 5.5 (PO) | Untrusted engine output (S-11, TM-74) | done (739 tests; real-binary test green; ~455k agent tokens) |
