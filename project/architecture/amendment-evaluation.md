# Evaluation of the Proposed ADR Amendments

**Author:** A00 · **Date:** 2026-11-11 · **Status:** REVIEW_REQUIRED
**Decision owner:** Product owner (PO), with A09 sign-off on amendment 1.

The PO asked for an evaluation, not a verdict. Each section lists the
options, what each costs, and a recommendation. Nothing here changes the
status of ADR-006, 007, 008 or 009: the amendments stay **Proposed**.

---

## 1. Localhost replay (ADR-006)

**Problem.** "Replay to localhost" is the headline use case in the 1.x
README. ADR-006 blocks loopback and private ranges and leaves a local agent
for later. A server cannot reach a developer's `localhost` anyway unless it
runs on the same machine, so there are two separate cases:

- **Self-hosted on the developer's machine or LAN.** The server can reach
  the target; the question is whether it is allowed to.
- **Hosted service (webhooktrap.dev).** The server can never reach the
  developer's machine. Only something running on that machine can.

### Options

| | Option | Covers | Security cost | Build cost |
| - | ------ | ------ | ------------- | ---------- |
| A | Block always until the agent ships | nothing | none | none |
| B | Self-host opt-in flag (1.x parity): loopback + RFC1918 allowed when `WEBHOOKTRAP_ALLOW_PRIVATE_REPLAY_TARGETS=true`, refused when `APP_ENV=production`, metadata always blocked | self-hosted only | **High if misconfigured:** an internet-facing instance with the flag on gives every user (guests included) SSRF into the host's network. The `APP_ENV` check is weak protection; many self-hosters run `production` and would simply not be able to use it, or would set `local` to get around it | small |
| B′ | B, tightened: an explicit allowlist of hosts/CIDRs + ports instead of "all private ranges", usable only by authenticated users (never guests), logged in the audit log, and a boot-time warning | self-hosted only | Medium: limited to what the operator lists | small |
| C | **Dedicated local agent** (`webhooktrap` CLI): runs on the developer's machine, connects *outbound* to the server over the existing Reverb websocket with an API key, receives replay jobs for endpoints it is subscribed to, executes them against local targets, posts results back as `ReplayAttempt`s | hosted **and** self-hosted | Low on the server (no inbound or internal requests). New surface on the client: agent authentication, job integrity, and the agent must restrict itself to local targets by default | **Large:** new distributable (binary or npm), protocol, versioning, docs. 1.x already planned this (`apps/cli`, never built) |
| D | Replay from the user's browser | — | — | Not viable: browsers' Private Network Access rules and CORS block a public page from calling `localhost` services in general |

### Assessment

- **C is the only option that serves the hosted service**, which is where
  most anonymous users are. It is also the only one that keeps the server
  free of internal network access. It is not small: it needs its own ADR
  (protocol, auth, packaging) and A09 threat model.
- **B alone repeats the 1.x design** with a known failure mode (flag left on
  in a public instance). **B′ removes most of that risk** for a small cost.
- The MVP Definition of Done says "replay to a *permitted* destination", so
  localhost is not strictly required for the MVP.

### Recommendation (for PO decision)

- MVP: **B′** for self-hosted instances, off by default, never available to
  guests.
- 2.1: **C**, specified in a new ADR before any build, with A09 threat model.
- Hosted service at MVP: public destinations only, with documentation that
  points users to tunnels or the upcoming agent.

---

## 2. Redis optionality (ADR-007 / ADR-008)

**Problem.** Redis is a confirmed decision. The amendment asked whether
self-hosters may run without it, using Laravel's database queue and cache
drivers, to keep the "few moving parts" promise of 1.x.

### Options

| | Option | Pros | Cons |
| - | ------ | ---- | ---- |
| A | Redis required everywhere | One supported configuration; simplest CI; Reverb can scale horizontally later (Reverb's multi-node scaling uses Redis pub/sub) | One more container for self-hosters |
| B | Redis optional, database drivers fully supported (amendment as proposed) | Smallest self-host footprint | **Doubles the supported matrix** (CI, bug reports). On the **ingest hot path**, rate-limit and quota counters would hit PostgreSQL on every webhook, competing with delivery inserts during bursts — exactly the path the final architectural principle says must be most reliable. Database queue polling adds steady DB load |
| C | Redis required for MVP; code written only against Laravel's queue/cache/rate-limiter abstractions (no Redis-specific calls), so optionality stays cheap to add later | Keeps the confirmed decision; no matrix explosion; keeps the door open | Self-hosters run Redis at MVP |

### Assessment

The original amendment underweighted the ingest-path cost. Redis is a small,
well-understood container in a Compose file; the self-hosting pain it adds is
lower than the risk of a second, less-tested configuration on the most
critical path.

### Recommendation (for PO decision)

**C.** Do not adopt the amendment for the MVP. Add an architecture test
(A05/A11) that forbids direct Redis facade usage outside an infrastructure
adapter. Revisit with load-test data in 2.1.

---

## 3. Legacy ingest URL compatibility (ADR-009)

**Problem.** Providers in production post to `/i/{id}` (API origin) and
`/hooks/{id}` (web origin) with 12-character IDs. 2.0 uses new opaque IDs.

### Facts that bound the decision

- Anonymous 1.x inboxes expire after 48 h, so only **account-owned** inboxes
  can still be configured in providers at cut-over.
- Compatibility only matters if those inboxes are **migrated** (ADR-009 open
  question 1). If 2.0 launches empty, legacy URLs have nothing to deliver to.
- We do not know how many owned inboxes are actively receiving traffic.
  That number needs a read-only query on the production database, which is
  the PO's call.
- Cost is small: a nullable unique `legacy_id` column on endpoints and two
  routes that resolve it, keeping the "always `{received:true}`" contract.
  A 12-character alphanumeric ID carries about 71 bits of entropy, enough to
  remain an unguessable capability.

### Options

| | Option | Pros | Cons |
| - | ------ | ---- | ---- |
| A | Break legacy URLs at cut-over | No legacy code | Silent data loss for active integrations (providers get `200` and the data goes nowhere) |
| B | Support permanently | Zero user impact | Permanent second ID scheme and two extra routes |
| C | Support for a fixed window (e.g. 6–12 months) for migrated owned inboxes, show a "legacy URL" notice in the UI with the new URL, then retire with notice | Protects active users, bounded cost | Requires the migration path and a communication plan |

### Recommendation (for PO decision)

**C, conditional on migrating owned inboxes.** Before deciding the window,
run one read-only production query: owned inboxes that received at least one
event in the last 30 days. If that number is zero or near it, option A
becomes reasonable.

---

## Summary for Gate 1

| Amendment | Recommendation | Needs |
| --------- | -------------- | ----- |
| 1 Localhost replay | B′ for self-hosted MVP; local agent (C) as a 2.1 ADR | PO + A09 |
| 2 Redis optional | Not for MVP (option C) | PO |
| 3 Legacy ingest URLs | Time-boxed support for migrated owned inboxes | PO + one read-only production query |
