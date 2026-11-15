# ADR-008: Self-hosted First-Class Distribution

**Status:** Accepted — amendment below is **Proposed** (depends on ADR-007 amendment)
**Date:** 2026-11-11

## Context

Webhook Trap is an open-source project. A developer should be able to deploy
the complete platform independently.

## Decision

Provide Docker Compose as the primary self-hosted deployment method.

Initial services:

- Application.
- PostgreSQL.
- Redis (optional — see ADR-007 amendment).
- Queue workers.
- Reverb.
- Reverse proxy.

Use environment-based configuration. Provide:

- `.env.example`
- Docker Compose configuration.
- Database migrations.
- Health checks.
- Persistent volumes.
- Backup and restore documentation.
- Upgrade instructions.

No mandatory dependency on a proprietary cloud service.

## Amendment (Phase 0, Proposed): minimal profile

Ship two Compose profiles:

- **default** — app, PostgreSQL, Redis, queue worker, Reverb, reverse proxy.
- **minimal** — app, PostgreSQL, one combined worker, Reverb; database queue
  and cache drivers.

Both must pass the same smoke test in CI.
