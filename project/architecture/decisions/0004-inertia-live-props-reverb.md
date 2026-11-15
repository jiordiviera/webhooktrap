# ADR-004: Inertia Live Props + Laravel Reverb

**Status:** Accepted
**Date:** 2026-11-11

## Context

The inbox and delivery monitoring interface must reflect incoming webhook
requests immediately. Traditional polling is unnecessary for the primary live
workflow.

## Decision

Use Laravel Reverb and Inertia Live Props for server-driven real-time updates.

The initial integration must validate compatibility with:

- `inertia-laravel` 3.7 or later.
- Inertia.js client 3.11 or later.
- The compatible React Echo integration.

### Event flow

1. An HTTP request reaches the capture endpoint.
2. The capture service persists the delivery.
3. The database transaction commits.
4. A `DeliveryReceived` event is dispatched.
5. Laravel broadcasts the event through Reverb.
6. The relevant live prop is refreshed.
7. The React interface displays the new delivery.

### Channel strategy

Use private endpoint-scoped channels.

Authorization must verify actual ownership through the authenticated user or
guest session. A channel identifier must never be treated as proof of
authorization.

### Performance rules

- Broadcast after successful persistence.
- Never broadcast full raw payloads by default.
- Use partial reloads for data requiring authorization or viewer-specific
  filtering.
- Use `broadcastProps` only for identical, non-sensitive payloads shared by
  all authorized subscribers.
- Apply throttling to frequently changing statistics.
- Do not broadcast every internal database operation.

### Reliability

Broadcasting must be asynchronous and configured to run after transaction
commit. The persistence of a delivery must never depend on the availability
of Reverb.

## Amendment (Phase 0): constraints found in the Live Props release

From the Live Props pull requests (inertiajs/inertia#3232,
inertiajs/inertia-laravel#901, both merged 2026-11-02):

- **Merge props cannot be live.** A paginated or infinite-scroll delivery
  list cannot itself be a live prop. The live inbox uses a bounded
  "latest N deliveries" live prop; older pages load as ordinary props.
- **Each event triggers one partial reload per open viewer.** A burst of
  webhooks to one endpoint multiplies into reload requests. The delivery list
  prop must be throttled (events arriving within a few ms are already batched
  by the client).
- **Reloads pause while the tab is hidden** and flush on return; the client
  refreshes all live props after a reconnect, so events missed during a
  Reverb outage are recovered without extra code.
- `X-Socket-Id` is sent automatically, so `->toOthers()` works.

## Spike status

A compatibility spike (Laravel 13.42, inertia-laravel 3.7.0,
`@inertiajs/react` 3.11.0, Reverb 1.13, `@laravel/echo-react` 2.6.3) was
installed in `spikes/live-props/` and paused before any application code was
written. It is scheduled in Phase 4 (A08) — see
`project/management/execution-plan.md`. Packages installed cleanly
together; runtime behaviour is not yet validated.
