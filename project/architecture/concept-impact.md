# Concept impact on the ADRs — Phase 1b

**Author:** A00 · **Date:** 2026-11-11 · **Status:** Follow-ups approved by the PO (decision 32, 2026-11-14). ADR drafts still to be written in Phase 4.

**Source:** A01's `open-questions.md` Part 2, in `.worktrees/p1b-product/project/product/`.

Decision 18 still holds. Accepted ADRs stay in force. Nothing here edits an
ADR. Each row says whether a concept calls an ADR into question, and what
would follow if the PO picks that concept.

## Summary

| ADR | A Workbench | B Proving Ground ★ | C Flight Recorder | What would follow if B is chosen |
| --- | ----------- | ------------------ | ----------------- | -------------------------------- |
| 001 Modular monolith | OK | OK, new domains | OK, new domains | Update the domain list: Scenario, Run/Verdict, Agent, Fixture. No structural change. |
| 002 Laravel + Inertia + React | Challenged (agent) | **Challenged** (agent) | OK | New ADR: server stays Laravel; agent/CLI in another runtime. Does not reverse 002. |
| 003 Hybrid access | OK | OK, additions | Strained (teams) | Amendment: guests can pair an agent with a one-time code bound to the session. |
| 004 Live Props + Reverb | OK | **Clarify** | OK | Amendment: run timelines use direct Echo events with small, non-sensitive payloads. Lists keep Live Props. Spike in Phase 4. |
| 005 Raw HTTP preservation | Bar raised | Bar raised | OK | Amendment: also capture the raw request target and the public URL as the provider saw it. |
| 006 Secure replay | **Challenged** | **Challenged** | OK | New ADR "Local agent and client-side scenario engine" plus an A09 threat model. The agent moves from 2.1 to the MVP. |
| 007 Queues / 008 Self-host | OK | OK, lighter server load | **Strained** | None for B. B-06 (Redis) can be decided on its own merits. |
| 009 Complete rewrite | Scope changes | Scope changes | Scope changes | The 1.x data and legacy URL questions shrink: no 1.x feature parity. Re-ask B-04 in light of decision 15. |

## Detail for the recommended concept (B)

### 1. A second runtime: the local agent (ADR-002, ADR-006)

**Why it is needed.**
- Proving runs need millisecond timing: two deliveries 50 ms apart, and bursts.
- They must reach `localhost`.
- They must run offline in CI.
- They must re-sign payloads with secrets that never reach the hosted server.

A server-side queued replay cannot do any of this. The scenario engine must
therefore run in a distributable agent.

**Consequences.**
- 2.0 becomes a Laravel server plus an agent/CLI. A01 leans towards Go;
  this is open question Q3.
- That means a second codebase, with its own release channel and versioning.
- It also needs a pairing protocol, which A09 must threat-model: agent
  authentication, job integrity, allowed targets and protocol versions.

**What does not change.**
- ADR-002's "one primary application deployment" still holds for the server.
- Server-side replay to public destinations stays as ADR-006 describes it.

**Amendment proposals this affects.**
- The ADR-006 amendment in `amendment-evaluation.md` §1 (self-hosted
  allowlist B′, agent in 2.1) is overtaken by B. The agent becomes the
  primary path to localhost.
- B′ is still useful for self-hosted server-side replay.

### 2. Real-time granularity (ADR-004)

A run produces dozens of row updates in a few seconds. If every update
triggered one partial reload per viewer, the server would see a storm of
requests. The proposal is to broadcast direct Echo events (run id, row index,
status, never payloads) for the timeline, and keep Live Props for lists.

ADR-004's principles still apply: broadcast after persisting, and send no
sensitive data. Only the delivery mechanism for this one surface changes.
A08 validates it in the Phase 4 spike (`spikes/live-props/`).

### 3. Capture fidelity (ADR-005)

To diagnose signatures from providers that sign the URL (Twilio, Slack's
legacy scheme), we need two more pieces of data:

- the raw request target;
- the public scheme and host as the provider saw them, behind proxies.

Both become capture attributes. Whether ingest needs a runtime that exposes
raw headers stays an architecture question for A05 in Phase 4.

### 4. Secrets (new rule)

Provider signing secrets are configured only in the agent or on a
self-hosted instance, never on the hosted service (Q4). This matches
ADR-006's existing rule that captured credentials are never forwarded.

### 5. What stays as decided

The following are unaffected by B:

- PostgreSQL
- Redis queue
- Reverb
- Pest
- Docker Compose
- the modular monolith
- anonymous plus authenticated access
- open source plus self-hosted

## Requested PO decisions (only if B is confirmed)

1. Approve drafting a new ADR, "Local agent and client-side scenario engine"
   (A05 and A09, Phase 4), with the agent in the MVP.
2. Approve the scoped amendments above to ADR-003, 004 and 005, to be drafted
   by A05 as Proposed.
3. Choose the agent's language (Q3), or let the Q13 prototype decide.
4. Revisit B-05 and B-06 with the new concept. The localhost-replay
   amendment changes nature, and the Redis question is unaffected.
