# ADR-004 Amendment 1: Direct Echo Events for Run Timelines

**Status:** Accepted. A08's spike (`spikes/live-props/SPIKE-REPORT.md`) returned CONFIRMED WITH CHANGES: 22 tests pass, run twice; the condition of decision 46 is met (decision 50). Text as of revision 3 (spike changes applied). The fallback to throttled Live Props (open-questions G-6) is not needed
**Amends:** ADR-004 Inertia Live Props + Laravel Reverb (Accepted). The accepted file is not edited.
**Date:** 2026-11-14 · **Author:** A05
**Source:** decision 32 §2, concept-impact §2, A01 PERF-1/PERF-4, A09 C-3 review (PV-6), decision 39 (PO-2; ADR-004 subject to A08's spike).
**Security review:** A09 `project/security/threat-model.md` §9.3 (ACCEPT WITH CHANGES).
**Spike:** confirmed with changes, decision 50. Remaining work is Phase 5 testing (below, and open-questions §8).

## Context

ADR-004 uses Live Props: an event triggers one partial reload per open viewer. A run produces up to 50 deliveries plus findings in under a minute, several updates per delivery (sent, responded, probed, judged). With 10 viewers (PERF-4) that is hundreds of partial reloads per run, each re-querying the run. The ADR-004 Phase 0 amendment already notes "each event triggers one partial reload per open viewer".

## Decision

### 1. Two mechanisms, one rule each

| Surface | Mechanism | Why |
| ------- | --------- | --- |
| Lists: capture stream (latest N), runs list, agents list, fixtures list | **Live Props** (unchanged ADR-004) | Low event rate; viewer-specific filtering; reload recovers after reconnect |
| Run timeline: delivery rows, findings, scenario cells, run status while running | **Direct Echo events** applied client-side to the page's run state | High event rate; payload is small, identical for every authorised viewer and non-sensitive |
| Signature verdict on a capture row | Direct Echo event (`capture.signature`) **and** the capture list live prop is not reloaded | One small field per row |

### 2. Payload rules for direct events

- Small (target < 1 KiB) and **identical for every authorised subscriber**.
- Never bodies, headers, header values, signatures, secrets, secret names, probe definitions or raw probe values.
- Probe information only as booleans (`state_changed`, `flagged`) and, where needed, PV-2 display values (PV-6). The default is booleans only; the delivery's display values are fetched by partial reload when the row is opened.
- Each event carries `run_id` and `seq` (monotonic per run, assigned by the server on persistence).
- Every broadcast has a JSON Schema with `additionalProperties: false`; a canary test covers every broadcast across a full run (TM-23).

### 3. Ordering and recovery

- Broadcast **after** the observation is committed (ADR-004 reliability rule stands).
- The client applies events in `seq` order. A gap (received `seq` > last + 1) or a reconnect triggers one partial reload of the run's `timeline` prop, which returns the full state with `last_seq`; later events with `seq ≤ last_seq` are dropped.
- When the run finishes, a final `run.finished` event triggers one partial reload of the run page, so the final numbered findings (FND-6) come from the server, not from client assembly.
- **The client also detects a finished run from a timeline reload** (spike C-5). `run.finished` can arrive inside a reload and be dropped as `seq ≤ last_seq`; the `timeline` prop therefore carries `status`, and a reload showing `run.finished` or a status other than `running` triggers the same final page reload.
- **The gap reload is required** (spike C-6): with more than one `broadcasts` worker 0.5 to 1.5 % of events arrived out of order and a broadcast can be lost.

### 4. Channels and channel authorisation (TM-20, TM-22)

- `private-run.{runId}`: authorised when the principal (`web` user or `guest` session) owns the run's project. Share-link viewers do **not** subscribe (§5).
- `private-project.{projectId}` replaces ADR-004's "endpoint-scoped" channel: in 2.0 a project has one capture URL (P9), and rotation must not change the channel.
- `private-workspace.{workspaceId}` for agent status and pairing completion.
- `private-session.{guestSessionId}` for guest session expiry and end.
- `private-agent.{agentId}` for server→agent job nudges (ADR-010 §5).

Rules:

1. **Two broadcast auth endpoints.** `/broadcasting/auth` accepts only the browser guards (`web`, `guest`). `/agent/v1/broadcasting/auth` accepts only the `agent` guard. Neither falls back to the other's guards.
2. **Principal type first.** Every channel callback checks the principal **type** before ownership: an `Agent` is authorised only for `private-agent.{its own id}`; a `User` or `GuestSession` is never authorised for `private-agent.*`; an `Agent` is never authorised for a browser channel, including `private-workspace` of its own workspace.
3. The server runner (ADR-010 §12) has **no** Reverb access; it long-polls.
4. **Client events (whispers) are disabled explicitly:** `accept_client_events_from = none`, with a config test that fails on `members`, `all` and a missing key (spike C-4). Reverb's stock value relays whispers and a missing key defaults to `all`.
5. Channel names are never proof of authorisation (ADR-004 rule stands). Channel ids are entity UUIDs, not keys.
6. **Both auth endpoints sign the principal into the subscription data** (spike C-3): `channel_data = {"user_id":"<type>:<id>"}` on private channels too, covered by the signature. Two dedicated controllers; no `Broadcast::routes()` or `channels:` option, because per-channel guard lists let a request with two credentials fall back to the other guard.
7. **Per-principal connection cap (TM-24) and connection maximum lifetime are our code inside the Reverb process** (spike C-1): Reverb has neither. Cap: error 4004 then close (guest 10, user 20, agent 2). Lifetime: error 4200 then close, the client reconnects and re-authorises (1 h, checked every 30 s; bound for a missed terminating event about 1 h 30 s). **[A10]** applies the config and the image (`ext-uv` or `ext-event`; without it Reverb dies at about 1,014 sockets), and limits connections per address at the proxy.

Full list: `event-catalogue.md` §1.

### 5. Shared-run viewers (TM-14)

- A shared run that is still running is refreshed by a 5 s Inertia poll, **rate-limited per client address**.
- Controllers build the prop set **per access level**. For `access: 'shared'`, owner-only props (`share`, unscrubbed capture detail, `agent`, `pairing`, target host details) are **absent**, not merely lazy; a partial-reload request naming an owner-only or unknown prop returns nothing for it. The spike proved both halves: seven request shapes leak nothing, and `Inertia::optional()` on the shared page leaks the pairing code and target host.

### 6. Terminating events (TM-21)

The Pusher protocol does not re-authorise subscriptions. When access ends, the server sends a terminating event on the affected channel and the client leaves its channels and reloads:

| Domain event | Terminating broadcast | Channel |
| ------------ | --------------------- | ------- |
| `GuestSessionEnded` (user, expired) | `session.ended` | `private-session.{id}` |
| `GuestSessionClaimed` | `session.ended` (reason `claimed`) | `private-session.{id}` |
| `AgentRevoked` | `agent.revoked` | `private-agent.{id}` |
| `RunUnshared` / share token rotated | none needed: share viewers never subscribe; their next poll gets not-found | — |

**After the terminating event the server closes the principal's sockets** (spike C-2): in the same `broadcasts` job, event first, a `terminate_connections` call for `<type>:<id>`. It closed both sockets of a guest, one of which never subscribed to the session channel, in 7 to 9 ms. The same call follows `AccountDeleted` and `RecoveryCompleted`. The connection maximum lifetime (§4 rule 7) is the backstop when the call does not reach Reverb. Residual R-2 (stale subscriptions receiving minimal payloads) now lasts milliseconds in the normal case; A09 accepted it.

### 7. Throttling

Runs at PERF-4 load need no throttling for direct events. Live Props list events keep ADR-004's throttling. The capture stream live prop is coalesced to at most one reload per 250 ms per project.

## Consequences

- The run page holds client state that events mutate; the page needs a reducer keyed by `seq`. This is front-end complexity Live Props avoided.
- One schema per event in `contracts/broadcast/` with tests on both sides.
- A08's spike validated (all items passed, decision 50): guest-guard channel auth for `private-run`; event ordering under 50 deliveries/run with 10 viewers; reconnect recovery; agent-guard auth for `private-agent` through its own endpoint; **negative tests for every guard × channel pair** (216 cells), including an agent token against `private-workspace` of its own workspace (TM-T20); client events rejected (TM-T22); terminating events and connection max lifetime (TM-T21).

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §4: two auth endpoints, principal-type check first, runner without Reverb | A09 §9.3-1, TM-20; HD-5 |
| 2026-11-14 | 2 | §4: client events disabled; connection cap | §9.3-2, TM-22, TM-24 |
| 2026-11-14 | 2 | §6: terminating events; residual accepted | §9.3-3, TM-21, R-2 |
| 2026-11-14 | 2 | §5: shared-run polling rate-limited; per-access prop sets, no owner props on partial reload | §9.3-4, TM-14 |
| 2026-11-14 | 2 | Consequences: guard × channel negative tests in the spike list; §2 closed schemas + canary | §9.3-5, TM-T20, TM-23 |
| 2026-11-14 | 2 | Status: Accepted, conditional on A08's spike (decision 46); no content change. The accepted ADR file and the ADR index are A00's | decision 46 |

| 2026-11-14 | 3 | Status: Accepted; A08's spike confirmed with changes (22 tests, run twice) | decision 50 |
| 2026-11-14 | 3 | §4 rules 4, 6, 7: explicit `none` for client events with config test; auth response signs `channel_data`; cap and max lifetime are our code in the Reverb process, image needs `ext-uv`/`ext-event` | A08 C-1, C-3, C-4, C-7 |
| 2026-11-14 | 3 | §6: `terminate_connections` after each terminating event (also `AccountDeleted`, `RecoveryCompleted`); R-2 narrowed | A08 C-2 |
| 2026-11-14 | 3 | §3: finished run detected from a timeline reload, gap reload required; §5: absent-not-lazy proven; Consequences: spike results; Phase 5 remainder listed | A08 C-5, C-6, TM-14 |

## Remaining for Phase 5 testing

The spike ran on a laptop with a minimal PHP Pusher client. Not covered, and tested in Phase 5:

1. **A real browser client** (Echo, pusher-js, Inertia Live Props in React), including the client reducer ported from the PHP reference with the same tests, the 5 s share poll in the browser, and reconnect on 4200 (reconnect) and 4004 (no reconnect).
2. **Numbers on the VPS**, with `ext-uv` or `ext-event`, TLS and the real limits; the single-process ceiling, the file-descriptor limit and the proxy connection limit; argon2id p95 on the shared VPS (H-19).
3. **Agent presence and heartbeat cost** at PERF-1.
4. **The capped long-poll on the production server stack** (FPM or FrankenPHP): the `artisan serve` run was inconclusive.

Also left to A11 once real payload producers exist: the TM-23 canary over a complete real run.
