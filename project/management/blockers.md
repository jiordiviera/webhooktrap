# Blockers — Webhook Trap 2.0

Last updated: 2026-11-14 (decision 32)

| ID   | Blocker | Impact | Responsible | Proposed resolution | Human decision required |
| ---- | ------- | ------ | ----------- | ------------------- | ----------------------- |
| B-01 | Phase 0 gate needs product owner approval | Phase 1 (A01, A02) cannot start | A00 | Review Phase 0 deliverables | **Yes** — approve Phase 0 |
| B-02 | Critical vulnerabilities live in 1.x production (risk register P-01 – P-04) | Users of the hosted service are exposed for the whole rebuild | A09 | Patch 1.x on a hotfix branch from `main`, separate from `rewrite/v2`; deploy through the existing pipeline | **Yes** — authorise a 1.x hotfix and its production deploy |
| B-03 | ~~`docs/` is both the published Mintlify site and the brief's location for 2.0 internal docs~~ | — | A00 | **Resolved 2026-11-11:** PO decided `docs/` stays the Mintlify site; internal docs moved to `project/`; vulnerability details kept outside the repository | Done |
| B-08 | No agent can render the Claude Design canvases (built-in browser has no claude.ai login) | Visual check rests on the PO's own viewing; static QA found no blockers | A00 | PO views the canvases; or signs in in the built-in browser so A00 can screenshot | **Yes** — PO viewing |

## Resolved

| ID | Blocker | Impact | Owner | Resolution | Status |
| -- | ------- | ------ | ----- | ---------- | ------ |
| B-04 | Product questions left open by ADR-009 (data migration, legacy URL lifetime, hosting). *1.x keep/defer/drop dropped by decision 15* | A05 schema depends on them | A01 | Re-examined from the new product concept in Phase 1b | Resolved — decision 32 (2026-11-14) |
| B-06 | *A01's position came from the superseded Phase 1 work; to be re-examined against the new concept.* **Disagreement A01 vs A00 on Redis optionality.** A01 (product view) supports a Redis-free minimal profile for laptop/small self-hosts; A00 (`project/architecture/amendment-evaluation.md` §2) recommends Redis required at MVP because of ingest-path load on PostgreSQL and a doubled support matrix | Shapes A10's Compose profiles and CI matrix | A00 | PO arbitrates at Gate 1; A05/A10 can supply load data in Phase 4 if the PO wants evidence first | Resolved — decision 32 (2026-11-14) |
| B-05 | Proposed ADR amendments (006 self-hosted private replay, 007/008 Redis optional, 009 legacy URLs) | Shapes A05/A09/A10 contracts | A00 | Decide at the Phase 1 gate at the latest | Resolved — decision 32 (2026-11-14) |
| B-07 | Gate 1 restarted: Phase 1 product and brand work superseded (decision 15) | Phase 2 cannot start | A00 | Phase 1b delivered: `phase-1b-recommendation.md` | Resolved — Gate 1 passed, decision 34 (2026-11-14) |
