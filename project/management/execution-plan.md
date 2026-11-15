# Execution Plan — Webhook Trap 2.0

Last updated: 2026-11-11 · Owner: A00

Statuses: `NOT_STARTED` · `IN_PROGRESS` · `BLOCKED` · `REVIEW_REQUIRED` ·
`APPROVED` · `REJECTED` · `COMPLETED`

A gate passes only when every deliverable exists, its acceptance criteria
are met, dependencies are satisfied, QA has reviewed where applicable, and
the product owner (PO) has approved the decisions that need it.

## Phase summary

| Phase | Status | Gate |
| ----- | ------ | ---- |
| 0 Repository intelligence | REVIEW_REQUIRED | PO approval |
| 1 Product strategy + brand | SUPERSEDED (decision 15) | — |
| 1b Product + design restart | DONE — Gate 1 passed (decision 34) | PO reviews vision, concepts, positioning, UX exploration, art directions |
| 2 UX + design system | IN_PROGRESS | PO approves navigation and principal UX |
| 3 Visual prototype | NOT_STARTED | PO approves prototype; A11 verified |
| 4 Architecture + foundation | NOT_STARTED | Contracts approved; PO approves any breaking change and the `legacy/` move |
| 5 Vertical slices | NOT_STARTED | A11 verifies each slice |
| 6 QA + security hardening | NOT_STARTED | No open Critical/High; A09 sign-off |
| 7 Release + cut-over | NOT_STARTED | PO approves public release and production deploy |
| — 1.x hotfix track | REVIEW_REQUIRED (independent) | PO decides P-11 extension, then deploy |

---

## Phase 0 — Repository intelligence (A00)

| Task | Owner | Status | Evidence |
| ---- | ----- | ------ | -------- |
| Inspect repository, dependencies, docs, deploy, CI | A00 | COMPLETED | `repository-audit.md` |
| Feature inventory | A00 | COMPLETED | `existing-features.md` |
| Production-sensitive files and destructive risks | A00 | COMPLETED | `repository-audit.md` §7, `risk-register.md` |
| Technical debt | A00 | COMPLETED | `repository-audit.md` §8 |
| Reconstruction strategy | A00 | COMPLETED | `rebuild-strategy.md` |
| Agent registry, plan, logs | A00 | COMPLETED | this folder |
| First ADR set (001 – 009) | A00 | REVIEW_REQUIRED | `project/architecture/decisions/` |
| Rewrite branch | A00 | COMPLETED | `rewrite/v2` from `main@27e9d98` |

**Acceptance criteria:** all four Phase 0 deliverables exist; every critical
finding re-verified in code by A00; no application code modified; no
destructive operation performed. **Limitations:** production was only
checked for availability (HTTP 200), not inspected; Mintlify and Vercel
deployment wiring inferred from config, not confirmed in their dashboards.

---

## Phase 1b — Product and design restart (decision 15)

New product, designed from the problem. **Not inputs:** the 1.x feature list
(`existing-features.md`), 1.x screens, `DESIGN.md`, `PRODUCT.md` brand
sections, `branding/`, the superseded Phase 1 drafts and the three
superseded brand directions. **Allowed inputs:** the original problem
(README tagline, `.docs/01` problem statement), technical constraints
(ADR-001 – 009, `repository-audit.md`), public research.

Sequence (amended by decision 19): **A01 → A02 territories → A03 UX per
territory → A04 materialises each direction in Claude Design (one Design
System artifact + one Design canvas per direction) → A01 fit review → A00.**
Steps 2a/2b below are replaced by this sequence; Claude Design artifacts are
the visual deliverables of record, Markdown only explains them. Each in its own worktree; nothing
committed or merged without PO approval.

| Step | Owner | Deliverables | Acceptance criteria |
| ---- | ----- | ------------ | ------------------- |
| 1 | A01 | `project/product/research.md`, `problems.md`, `personas.md`, `concepts.md`, `vision.md`, `positioning.md`, `core-experience.md`, `open-questions.md` | Research cites real sources (issues, forums, docs, competitor material); problems ranked by frequency × pain; ≥3 genuinely distinct product concepts with value proposition, primary user, core loop, what it is *not*, risks; positioning options + recommendation; core experience narrative for each concept; technical implications flagged against ADRs; no reliance on the 1.x feature list |
| 2a | A03 | `project/design/ux-exploration.md`, `project/design/explorations/` | Interaction model per concept; core-experience flows; viewable low/mid-fidelity wireframes for the core experience of each concept, plus a deeper pass on A01's recommended concept; accessibility notes |
| 2b | A02 | `project/brand/brand-strategy.md`, `visual-directions.md`, `project/brand/explorations/` | ≥3 radically different art directions (not variations of the superseded ones): concept, logo concepts, palette with checked contrast, typography (OSS-compatible licences), interface approach; per direction a viewable landing page and core-experience mock-up |
| 3 | A00 | `project/architecture/concept-impact.md`, `project/management/phase-1b-recommendation.md` | ADRs challenged by each concept, with justification; argued recommendation; nothing marked approved |

## Phase 1 — Product strategy and brand identity (SUPERSEDED by decision 15)

Runs **A01 ∥ A02**. They write disjoint files, each in its own worktree; A00
reviews and integrates into `rewrite/v2`. No other agent starts.

### A01 — Product Strategist

**Inputs:** `PRODUCT.md`, `.docs/01–07`, `existing-features.md`,
`repository-audit.md`, ADR 001 – 009, the orchestration brief.

| Deliverable | Acceptance criteria |
| ----------- | ------------------- |
| `project/product/vision.md` | One-paragraph positioning; problem, audience, differentiation vs named alternatives (from `.docs/07-concurrence.md`, refreshed); explicit non-goals matching brief §8 |
| `project/product/personas.md` | 3 – 4 personas grounded in real webhook workflows (provider integration, local dev, staging debugging, self-hoster); each with jobs, pains, success signal |
| `project/product/requirements.md` | Numbered functional requirements per domain (Capture, Endpoint, Delivery, Replay, Workspace, Identity, Monitoring, Retention) with MVP / later tag; non-functional requirements (limits, retention, latency, availability); **keep / defer / drop for every 1.x feature in `existing-features.md`**; options + recommendation for each ADR-009 open question; MVP Definition of Done matching the brief |
| `project/product/roadmap.md` | MVP → 2.1 → later, mapped to milestones M0 – M5; what is out of scope and why |

### A02 — Brand Strategist

**Inputs:** the brief, `vision.md` draft when available (does not wait for it),
`.docs/06-naming-branding.md` for history only. **Not** constrained by
`DESIGN.md`, `branding/` or the terracotta palette. The product name stays
"Webhook Trap".

| Deliverable | Acceptance criteria |
| ----------- | ------------------- |
| `project/brand/brand-strategy.md` | Brand positioning, personality, voice and tone with do/don't examples |
| `project/brand/visual-directions.md` | **At least three** genuinely different directions. Each: concept, palette (OKLCH, light + dark, WCAG AA contrast checked for text and status colours), type pairing (licence-compatible with open source), logo concept, and two applied mock-ups — the live inbox screen and the landing hero — rendered as viewable HTML/SVG in `project/brand/explorations/` |
| `project/brand/identity-system.md` | For the **recommended** direction only, drafted after A00 review: tokens, logo usage, iconography, motion principles |
| `project/brand/brand-guidelines.md` | Same: written once the PO selects a direction |

### Gate 1

- PO approves positioning (A01 `vision.md`).
- PO selects a brand direction (A02); identity system and guidelines are then
  finalised.
- PO decides ADR-009 open questions and the Proposed amendments (blockers
  B-04, B-05).
- A11 reviews requirements for testability (light review, no code).

---

## Phase 2 — UX and design system

| Task | Owner | Depends on | Acceptance |
| ---- | ----- | ---------- | ---------- |
| Information architecture, navigation | A03 | Gate 1 | Covers anonymous, authenticated, self-host admin |
| User flows | A03 | IA | Every MVP DoD step in brief §9 has a flow, including errors |
| Wireframes, screen specs | A03 | flows | All MVP screens; empty/loading/error states; mobile |
| Accessibility spec | A03 | screen specs | WCAG 2.2 AA targets, keyboard maps for inbox and inspector |
| Design system exploration | A04 | brand direction | Tokens + core components, not finalised before design approval |

Gate 2: PO approves navigation and principal UX.

## Phase 3 — Visual prototype (A04)

Navigable prototype in the 2.0 stack's frontend (React + TypeScript +
Tailwind + shadcn) with realistic mock data and real client-side behaviour.
Gate 3: A11 verifies states and responsiveness; PO approves.

## Phase 4 — Architecture and foundation

| Task | Owner | Parallel with |
| ---- | ----- | ------------- |
| System architecture, domain model, schema, event catalogue, API + Inertia prop contracts | A05 | A09, A10 |
| Threat model, auth/guest-session design, SSRF design, rate-limit and quota policy | A09 | A05, A10 |
| `legacy/` move (PO approval), Laravel bootstrap, Docker Compose, CI, health checks | A10 | A05, A09 |
| Live Props + Reverb + guest channel auth spike (resume `spikes/live-props/`) | A08 | after A05 event catalogue draft + A09 channel auth model |
| Test infrastructure, architecture tests, test strategy | A11 | A10 |

## Phase 5 — Vertical slices

Order: identity + guest sessions → workspace/project ownership → endpoints →
HTTP ingestion → delivery persistence → request inspector → live inbox →
replay engine → response inspector → retention → API → self-hosting. Each
slice: contract agreed (Rule 2), implementation (A06/A07/A08), A11
verification, A09 review for security-relevant slices.

## Phase 6 — QA and security hardening

A11 and A09 independently run the checklist in brief Phase 6 and the quality
gates in the ADR plan §7.

## Phase 7 — Open-source release

Docs, community files, changelog, release notes, demo assets; production
cut-over per `rebuild-strategy.md` §5.

---

## 1.x hotfix track (proposed, needs PO authorisation)

| Task | Owner | Acceptance |
| ---- | ----- | ---------- |
| Fix P-01 – P-04 on `hotfix/security-2026-11` from `main` | A09 | Regression test per finding; existing 41 tests pass |
| Review by someone other than the fixer | A11 | Reproduces each fix |
| Deploy to VPS (+ Vercel if web changes) | PO / A10 | Only with explicit PO approval |
| Follow-ups P-05 – P-10 | A09 | Prioritised after the critical four |
