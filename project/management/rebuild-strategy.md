# Rebuild Strategy — Webhook Trap 2.0

**Phase:** 0 · **Author:** A00 · **Date:** 2026-11-11 · **Status:** REVIEW_REQUIRED

## 1. Principles

1. **Capture reliability first.** Every later feature (live inbox, replay,
   collaboration) sits on a capture path that persists before it does
   anything else.
2. **1.x keeps running until 2.0 replaces it.** No production asset is
   touched by the rewrite. 1.x only receives security hotfixes.
3. **One writer per file set.** Agents that write work on their own branch
   or worktree; the Orchestrator integrates into `rewrite/v2`.
4. **Gates, not momentum.** A phase starts only when the previous gate is
   APPROVED by the product owner where required, with QA evidence where
   applicable.
5. **Simplest thing that meets the requirement.** No empty abstractions,
   no new framework or infrastructure without an ADR.

## 2. Two tracks

| Track | Branch | Purpose | Ships to |
| ----- | ------ | ------- | -------- |
| **1.x maintenance** | `hotfix/*` from `main` | Close critical vulnerabilities (risk register P-01 – P-04, then P-05 – P-10) | Current production, via the existing pipeline, with PO approval |
| **2.0 rebuild** | `rewrite/v2` (+ per-agent branches) | Phases 0 – 7 | Nothing until the Phase 7 cut-over |

The tracks never merge code into each other. Knowledge flows one way: 1.x
fixes become 2.0 security regression tests (A09/A11).

## 3. Phase overview

| Phase | Name | Lead agents | Gate owner |
| ----- | ---- | ----------- | ---------- |
| 0 | Repository intelligence | A00 | PO |
| 1 | Product strategy + brand identity | A01 ∥ A02 (A09 consulted) | PO |
| 2 | UX + design system | A03, A04 (explore) | PO |
| 3 | Visual prototype | A04 (A11 verifies) | PO |
| 4 | Architecture + engineering foundation | A05 ∥ A09 ∥ A10, then A08 spike; A11 test infra | PO for breaking changes; A11 + A09 for contracts |
| 5 | Vertical slices | A06, A07, A08 (A11 verifies each) | A11 per slice |
| 6 | QA + security hardening | A11 ∥ A09 (independent) | A09 (security), A11 (release) |
| 7 | Open-source release + cut-over | A11, A01, A10 | PO |

Details, dependencies and acceptance criteria: [`execution-plan.md`](execution-plan.md).

## 4. Repository transition

Before Phase 4 (no destructive step happens in Phases 0 – 3):

1. Phases 0 – 3 only **add** files under `project/` and a prototype folder
   on `rewrite/v2`. `docs/` (live Mintlify site) is not touched.
2. At the start of Phase 4, with PO approval, move the 1.x tree on
   `rewrite/v2` to `legacy/` (`git mv`, history preserved):
   `apps/`, `packages/`, `turbo.json`, `pnpm-*`, `ecosystem.config.cjs`,
   1.x `docker-compose.yml`, 1.x CI workflow. `main` is untouched.
3. Bootstrap Laravel at the repository root on `rewrite/v2` (A10/A05).
4. `docs/` stays the user docs site; its content is rewritten for 2.0 in
   Phase 7 and published only at cut-over.
5. Delete `legacy/` from `rewrite/v2` only after the cut-over is stable and
   with PO approval. `main`'s history keeps the 1.x code forever.

Files kept at root and rewritten in place during Phase 7: `README.md`,
`CONTRIBUTING.md`, `SECURITY.md`, `LICENSE`, `.github/` templates.
`PRODUCT.md`, `DESIGN.md`, `CONTEXT.md`, `CLAUDE.md`, `AGENTS.md`,
`DEPLOYMENT.md` are replaced by their 2.0 equivalents when those exist.

## 5. Production cut-over (Phase 7, needs PO approval)

Outline only — A10 owns the detailed runbook in Phase 4:

1. Decide data strategy (ADR-009 open question 1): migrate users and live
   inboxes, or launch empty with 1.x read-only for a period.
2. Full Neon backup and restore test before anything else.
3. Deploy 2.0 next to 1.x (new host or new subdomain) and verify it serves
   legacy ingest URLs `/i/{id}` and `/hooks/{id}`.
4. Switch DNS at Cloudflare; keep 1.x warm for rollback.
5. Retire 1.x (Vercel project, PM2 processes) only after the rollback window.

## 6. What carries over

| Asset | Carry over as |
| ----- | ------------- |
| Business rules (ingest contract, limits, timeouts, ID shapes) | Requirements input (A01) and contracts (A05) |
| SSRF guard test cases (12) + P-01 – P-10 | Security regression suite (A09/A11) |
| `PRODUCT.md`, `.docs/01–07` | A01 input |
| `packages/ui` components | Implementation material for A04 (not the visual identity) |
| Landing copy and Mintlify guides | Content input for A01 and Phase 7 docs |
| `redesign-warm-workbench` three-pane inbox | Reference for A03 |
| `branding/` | History; A02 decides what survives |

## 7. What 2.0 deliberately does differently

- Raw byte capture and ordered headers (ADR-005) instead of parsed bodies.
- Capability-based guest access instead of "anonymous inboxes are public by
  ID".
- Session-cookie auth through Inertia instead of bearer tokens in
  `localStorage`.
- Async, immutable replay attempts with redirect and rebinding protection.
- Websocket live updates instead of 3 s polling.
- Scheduled, idempotent retention.
- One deployable application instead of Vercel + VPS + proxy layer.
