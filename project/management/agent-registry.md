# Agent Registry — Webhook Trap 2.0

Last updated: 2026-11-11 (Phase 1b restart + 1.x hotfix)

Statuses: `NOT_STARTED` · `IN_PROGRESS` · `BLOCKED` · `REVIEW_REQUIRED` ·
`APPROVED` · `REJECTED` · `COMPLETED`

**Model selection (decision 22):** each run's model, complexity level and
reason are recorded in `model-policy.md` § Run log. Defaults: C4/C3 → Opus 5.5,
C2 → Sonnet 5.5, C1 → Haiku 5.5. No model here is documented as
design-specialised; creative work runs on Opus 5.5 (limitation logged).

| Agent | Default level | Default model | Model used so far |
| ----- | ------------- | ------------- | ----------------- |
| A00 Orchestrator | C4 | Opus 5.5 | Opus 5.5 |
| A01 Product | C3 | Opus 5.5 | Opus 5.5 |
| A02 Brand | C3 | Opus 5.5 | Opus 5.5 |
| A03 UX/UI | C3 | Opus 5.5 | Opus 5.5 |
| A04 Design Engineer | C3 (Claude Design) / C2 (specified components) | Opus 5.5 / Sonnet 5.5 | Opus 5.5 |
| A05 Architect | C4 | Opus 5.5 | — |
| A06 Backend | C2 (C4 for ingest, replay, auth) | Sonnet 5.5 / Opus 5.5 | Sonnet 5.5 (Go prototype) |
| A07 Frontend | C2 | Sonnet 5.5 | — |
| A08 Realtime | C3 | Opus 5.5 | — |
| A09 Security | C4 | Opus 5.5 | Opus 5.5 |
| A10 DevOps | C2 (C4 for production) | Sonnet 5.5 / Opus 5.5 | — |
| A11 QA | C4 for security reviews, C2 for test writing | Opus 5.5 / Sonnet 5.5 | Opus 5.5 |

Agents are launched only when their dependencies are satisfied. Each runs
with a written brief that names its inputs, owned files and acceptance
criteria; writing agents work on their own branch or worktree, and the
Orchestrator integrates.

| ID  | Role                     | Status        | Earliest phase |
| --- | ------------------------ | ------------- | -------------- |
| A00 | Orchestrator             | IN_PROGRESS   | 0              |
| A01 | Product Strategist       | REVIEW_REQUIRED (1b strategy; fit review later) | 1b |
| A02 | Brand Strategist         | REVIEW_REQUIRED (3 territories) | 1b |
| A03 | UX/UI Designer           | REVIEW_REQUIRED (UX per territory + dataset) | 1b (explore), 2 |
| A04 | Design Engineer          | REVIEW_REQUIRED (3 directions published in Claude Design) | 1b, 3 |
| A05 | Software Architect       | NOT_STARTED   | 4              |
| A06 | Backend Engineer         | REVIEW_REQUIRED | 5            |
| A07 | Frontend Engineer        | NOT_STARTED   | 5              |
| A08 | Realtime Engineer        | NOT_STARTED   | 4 (spike), 5   |
| A09 | Security Engineer        | REVIEW_REQUIRED (hotfix extension verified by A11) | 1 (review), 4 |
| A10 | DevOps Engineer          | NOT_STARTED   | 4              |
| A11 | QA & Release Engineer    | DONE (hotfix verification, static design QA) | 4  |

---

## A00 — Orchestrator

- **Mission:** Coordinate the rebuild, own integration, gates, project
  management files and ADR index. Report to the product owner.
- **Owned deliverables:** `project/management/*`,
  `project/architecture/decisions/README.md`, Phase 0 deliverables.
- **Status:** IN_PROGRESS — Phase 0 deliverables at REVIEW_REQUIRED.
- **Dependencies:** none.
- **Last report:** 2026-11-11 — Phase 0 audit complete; two read-only
  inventory sub-agents (API, web) used to read the 1.x code.

## A01 — Product Strategist

- **Workspace:** Phase 1b worktree `.worktrees/p1b-product`, branch `phase1b/a01-product`. Superseded Phase 1 work stays in `.worktrees/a01-product` (`phase1/a01-product`, uncommitted).

- **Mission:** Product vision, positioning, personas, requirements, journeys,
  MVP scope, roadmap. Co-owns documentation and contribution standards with
  A11.
- **Owned deliverables:** `project/product/vision.md`, `personas.md`,
  `requirements.md`, `roadmap.md`.
- **Status:** NOT_STARTED.
- **Dependencies:** Phase 0 approval; inputs `existing-features.md`,
  `PRODUCT.md`, `.docs/01–07`.
- **Status (2026-11-11):** restarted from scratch (decision 15). Phase 1 drafts SUPERSEDED.
- **Last report:** 2026-11-11 — Phase 1b strategy delivered in `.worktrees/p1b-product/project/product/` (research, problems, personas, 4 concepts, vision, positioning, core experience, open questions); recommends concept B "Proving Ground"; flags ADR-001/002/004/005/006.

## A02 — Brand Strategist

- **Workspace:** Phase 1b worktree `.worktrees/p1b-brand`, branch `phase1b/a02-brand` (created when A01 delivers). Superseded work stays in `.worktrees/a02-brand`.

- **Mission:** Independent brand exploration: positioning, several original
  visual directions, logo concepts, colour, typography, guidelines, assets.
  Not constrained by the 1.x terracotta direction.
- **Owned deliverables:** `project/brand/brand-strategy.md`,
  `visual-directions.md`, `identity-system.md`, `brand-guidelines.md`,
  `project/brand/**`.
- **Status:** SUPERSEDED (decision 15); restart NOT_STARTED, waits for A01. Phase 1 had delivered `brand-strategy.md`,
  `visual-directions.md`, gallery `explorations/index.html`, three directions
  (Field Recorder, Printout, Postmark) each with logo, palette, typography,
  landing hero and live inbox mock-ups; `tools/palettes.py --check` passes
  (WCAG AA 4.5:1, light and dark). Recommends Field Recorder.
  `identity-system.md` and `brand-guidelines.md` wait for the PO's pick.
- **Dependencies:** Phase 0 approval. Reads A01's positioning draft when
  available but does not wait for it.
- **Last report:** 2026-11-11 — A00 checked: files present, only Google Fonts
  as external URLs, nothing committed.

## A03 — UX/UI Designer

- **Mission:** Information architecture, navigation, flows, wireframes,
  screen specs, states, responsive behaviour, accessibility.
- **Owned deliverables:** `project/design/information-architecture.md`,
  `user-flows.md`, `wireframes.md`, `screen-specifications.md`,
  `accessibility.md`.
- **Phase 1b (exploration only):** `project/design/ux-exploration.md` and
  viewable concept wireframes in `project/design/explorations/`, worktree
  `.worktrees/p1b-ux`, branch `phase1b/a03-ux`. Not the Phase 2 deliverables.
- **Status:** NOT_STARTED — waits for A01.
- **Dependencies:** approved product positioning (A01) and brand direction
  (A02).
- **Last report:** —

## A04 — Design Engineer

- **Mission:** Design tokens, design system, reusable React components,
  complete navigable prototype with realistic mock data.
- **Owned deliverables:** `project/design/design-system.md`, prototype source.
- **Status:** NOT_STARTED.
- **Dependencies:** brand approval (exploration); design approval
  (finalisation).
- **Last report:** —

## A05 — Software Architect

- **Mission:** System architecture, domain boundaries, ADRs, database
  schema, event catalogue, API contracts, architecture tests.
- **Owned deliverables:** `project/architecture/**` (except ADR index),
  schema and contract documents.
- **Status:** NOT_STARTED. Phase 0 ADR drafts were prepared by A00 and are
  handed over at Phase 4.
- **Dependencies:** approved requirements (A01) and prototype gate (Phase 3).
- **Last report:** —

## A06 — Backend Engineer

- **Mission:** Laravel application, domain services, capture engine,
  persistence, endpoints, deliveries, replay engine, API, jobs, retention.
- **Status:** REVIEW_REQUIRED for the Q13 validation prototype only.
  Laravel work is NOT_STARTED.
- **Done (2026-11-14, decision 28):** Go CLI prototype in
  `.worktrees/spike-proving-cli/spikes/proving-cli/`, branch
  `spike/proving-cli`, 3 local commits, never to be merged.
  - Standard library only.
  - Tests pass, including with `-race`; vet is clean.
  - §9 criteria 1, 2 and 4–7 are met. Criterion 3 is partly met: GitHub and
    Shopify were not run against their official SDKs. Criterion 8 (timing) was
    not measured. Criterion 9: F1–F6 fixed (`4104d65`, `cd0d8da`). Final A09 pass on `6d1f107`: F1–F6, N1, N2, L1 and L2 FIXED. Verdict: GO for internal runs; GO with conditions for outside participants (disclose README limits; treat returned summaries as untrusted). One optional Low left: unbounded combining marks.
- **Dependencies:** approved architecture, schema and security contracts.

## A07 — Frontend Engineer

- **Mission:** React/TypeScript/Inertia application, pages, forms, state,
  accessibility, integration.
- **Status:** NOT_STARTED.
- **Dependencies:** approved prototype and API/prop contracts.

## A08 — Realtime Engineer

- **Mission:** Reverb, Echo, Live Props, private channels, delivery and
  replay status updates, reconnection, realtime performance.
- **Status:** NOT_STARTED. A paused compatibility spike exists in
  `spikes/live-props/`.
- **Dependencies:** event catalogue (A05) and channel authorization model
  (A09).

## A09 — Security Engineer

- **Current task:** 1.x hotfix P-01 – P-04 in worktree `.worktrees/hotfix-security`, branch `hotfix/security-2026-11` (local only)

- **Mission:** Threat model, SSRF, auth and guest-session security,
  authorization, secrets, rate limiting, abuse prevention, dependency
  security, security regression tests. **Can block unsafe implementations.**
- **Status:** REVIEW_REQUIRED — 5 local commits for P-01 – P-04, tests
  41 → 100, verified by A11. Extension to P-11 (and P-12) awaits PO.
- **Dependencies:** none for threat modelling; reviews ADR-006 amendment.

## A10 — DevOps Engineer

- **Mission:** Docker/Compose, PostgreSQL, Redis, workers, Reverb
  deployment, CI/CD, health checks, logging, monitoring, backup/recovery,
  self-host install.
- **Status:** NOT_STARTED.
- **Dependencies:** architecture contracts (A05). Must not touch the live
  1.x production stack without approval.

## A11 — QA & Release Engineer

- **Mission:** Test strategy and independent verification of every gate;
  release readiness, changelog. Co-owns documentation and contribution
  standards with A01.
- **Status:** Hotfix verification done 2026-11-11 (P-01, P-02 VERIFIED;
  P-03, P-04 VERIFIED WITH ISSUES; new findings P-11 – P-15 in the private
  report). Phase 4+ role NOT_STARTED.
- **Dependencies:** none for test strategy; verification follows each
  deliverable.
