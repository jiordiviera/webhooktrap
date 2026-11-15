# ADR-007: Queue and Event Reliability

**Status:** Accepted — amendment below is **Proposed** (needs product owner approval)
**Date:** 2026-11-11

## Context

The system processes incoming deliveries, broadcasts, replay jobs and
retention tasks. These operations have different reliability and latency
requirements.

## Decision

Use Redis-backed Laravel queues. Separate workloads logically:

- `replay`: outbound HTTP execution.
- `broadcasts`: real-time events.
- `maintenance`: retention and cleanup.
- `default`: ordinary asynchronous work.

Persist incoming deliveries before dispatching downstream work. Use
after-commit dispatching for database-dependent jobs and events.

Broadcast failures must not invalidate successfully captured deliveries.

For critical workflows requiring guaranteed event publication, evaluate a
transactional outbox in a later ADR.

## Amendment (Phase 0, Proposed): Redis optional for self-hosters

Redis is a confirmed decision. This amendment does not replace it; it asks
whether self-hosters may opt out of it.

1.x runs without Redis, and the self-hosting docs promise few moving parts.
Laravel's `database` queue and cache drivers support the same queue names and
after-commit semantics.

- Redis is the default and recommended driver for the hosted service and for
  production self-hosting.
- A self-hosted instance may set `QUEUE_CONNECTION=database` and
  `CACHE_STORE=database` and drop the Redis container. Rate limiting then
  uses the database cache store.
- CI runs the test suite against the database drivers so that path stays
  working.
