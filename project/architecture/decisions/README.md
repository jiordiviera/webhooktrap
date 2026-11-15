# Architecture Decision Records — Webhook Trap 2.0

Webhook Trap 2.0 is a rewrite of the product on Laravel + Inertia React (see
[ADR-009](0009-complete-repository-rewrite.md)). These records capture the
decisions that shape it.

Final architectural principle: **Webhook Trap is designed around the
reliability of webhook capture first.** Real-time updates, replay and
collaboration are built on top of that foundation, never the other way around.

| ADR                                                  | Title                                   | Status   |
| ---------------------------------------------------- | --------------------------------------- | -------- |
| [001](0001-modular-monolith.md)                      | Modular monolith                        | Accepted · domain list superseded by the Phase 4 domain model (PO-9) |
| [002](0002-laravel-inertia-react.md)                 | Laravel + Inertia + React               | Accepted |
| [003](0003-hybrid-anonymous-authenticated-access.md) | Hybrid anonymous and authenticated access | Accepted · amendment 1 Accepted (decision 46) |
| [004](0004-inertia-live-props-reverb.md)             | Inertia Live Props + Laravel Reverb     | Accepted · amendment 1 Accepted (spike confirmed, decision 50) |
| [005](0005-raw-http-preservation.md)                 | Raw HTTP preservation                   | Accepted · amendment 1 Accepted (decision 46) |
| [006](0006-secure-replay-engine.md)                  | Secure replay engine                    | Accepted · Phase 0 amendment Rejected · amendment 1 Accepted (decision 46) |
| [007](0007-queue-and-event-reliability.md)           | Queue and event reliability             | Accepted · Phase 0 amendment Rejected (Redis-compatible store required, decision 32) |
| [008](0008-self-hosted-distribution.md)              | Self-hosted first-class distribution    | Accepted · Phase 0 amendment Rejected (single profile, decision 32) |
| [009](0009-complete-repository-rewrite.md)           | Complete repository rewrite             | Accepted · 1.x retired (decisions 40–41), accounts re-created in v2 |
| 010 (Phase 4 worktree: `.worktrees/p4-architecture/project/architecture/decisions/0010-local-agent-and-scenario-engine.md`) | Local agent and scenario engine (Go) | Accepted (decision 46) |

Decisions marked *Confirmed* in the orchestration brief (Laravel, Inertia +
React, Reverb + Live Props, PostgreSQL, Redis, modular monolith, anonymous +
authenticated access, open source + self-hosted, independent visual
identity) are authoritative. Amendments only propose changes; none take
effect without product owner approval.

## Phase 0 audit amendments

The Phase 0 audit (2026-11-11) compared the plan with the 1.x codebase and
the current package registries. Amendments are marked **Amendment (Phase 0)**
inside each record. Summary:

- **ADR-006** keeps self-hosted replay to loopback/RFC1918 behind an explicit
  flag, as 1.x does today (`ALLOW_INSECURE_REPLAY_TARGETS`, commit `d4a45b7`).
  Replay to localhost is the use case the README advertises.
- **ADR-007 / ADR-008** let self-hosters run without Redis (database queue
  and cache drivers); Redis stays the recommended production setup.
- **ADR-009** requires existing ingest URLs to keep working and lists the
  product decisions still open (data migration, 1.x features to carry over).
- **ADR-004** records the constraints found in the Live Props release notes.

The PO asked for an evaluation of the three amendments rather than a
decision: see [`../amendment-evaluation.md`](../amendment-evaluation.md).
None of them is accepted or rejected.

## Open questions

Tracked in [ADR-009 § Open questions](0009-complete-repository-rewrite.md#open-questions).

## Format

New records follow the same layout: Status, Context, Decision, Consequences.
Number them sequentially; never renumber or delete an accepted record —
supersede it with a new one.

## Phase 4 (2026-11-14)

ADR-010 and amendment 1 to ADR-003, 004, 005 and 006 were drafted by A05,
threat-modelled by A09 (verdict: ready, §12.10) and accepted by the PO in
decision 46. ADR-004 amendment 1 stays conditional on A08's spike. Their
source files live in the Phase 4 worktree until it is merged. The Phase 0
amendments to ADR-006, 007 and 008 are Rejected (decisions 32 and 39, PO-3).
ADR-001's domain list is superseded by `domain-model.md` §1 (PO-9).
