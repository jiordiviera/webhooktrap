# Phase 4 notes on the ADR index

**Author:** A05 · **Date:** 2026-11-14 · **Status:** Proposed (for A00)
**Source:** decision 39, PO-3 (approved).

This file records the PO-3 outcome next to the Phase 4 drafts. It does **not** edit the accepted ADR files or the main index (`project/architecture/decisions/README.md`); A00 applies these marks there.

## Phase 0 amendments now Rejected (PO-3)

| Accepted ADR | Amendment (as titled in the accepted file) | Why rejected | Replaced by |
| ------------ | ------------------------------------------ | ------------ | ----------- |
| ADR-006 Secure Replay Engine | "Amendment (Phase 0, Proposed): self-hosted private targets" (flag `WEBHOOKTRAP_ALLOW_PRIVATE_REPLAY_TARGETS`, all private ranges, `APP_ENV` check) | Decision 32 §3: B′ is an explicit allow-list, off by default, never for guests; A09 adds a per-user permission and exclusivity with open sign-up (PO-G) | `0006-amendment-1.md` §3 |
| ADR-007 Queue and Event Reliability | "Amendment (Phase 0, Proposed): Redis optional for self-hosters" | Decision 32 §4: a Redis-compatible server is required (Valkey accepted, HD-8) | `domain-model.md` §1.2 rule 3; `schema.md` §1 |
| ADR-008 Self-Hosted Distribution | "Amendment (Phase 0, Proposed): minimal profile" | Decision 32: one Compose profile only | A10 `operations/hosting-options.md` §3 |

Readers must not implement the private-targets flag, a Redis-less mode or the minimal profile.

## Phase 0 amendments not affected

- ADR-003 "Amendment (Phase 0): guests as an auth guard": assumed by `0003-amendment-1.md`.
- ADR-004 "Amendment (Phase 0): constraints found in the Live Props release": still valid; `0004-amendment-1.md` builds on it.
- ADR-009 "Amendment (Phase 0, Proposed): ingest URL compatibility": outside PO-3; unchanged.

## Other index notes for A00

- ADR-007's `replay` queue no longer executes the engine: server runs are pulled by the remote runner (ADR-010 §12, HD-5); the queue keeps job expiry and metrics.
- ADR-001 amendment (PO-9) points to `domain-model.md` §1; A00 writes it.
- **Decision 46:** ADR-010 and amendment 1 of ADR-003, ADR-005 and ADR-006 are **Accepted**; ADR-004 amendment 1 is **Accepted, conditional on A08's spike**. The status lines of the drafts in this folder say so; A00 updates the ADR index and the accepted ADR files.
- ADR-010 §2 (Go) was approved earlier (decision 44). §12.5 (server runs Level 1 only) added in revision 3.
- ADR-005 amendment 1 gains §9 (FrankenPHP, decision 43); ADR-003 amendment 1 §4 follows the sign-in methods of decision 44.

## Change log

| Date | Change | Source |
| ---- | ------ | ------ |
| 2026-11-14 | Index notes for decisions 43 and 44 | A05 revision 3 |
