# ADR-006: Secure Replay Engine

**Status:** Accepted — amendment below is **Proposed** (needs product owner and A09 approval)
**Date:** 2026-11-11

## Context

Replaying a captured webhook requires sending an outbound HTTP request to a
user-configured destination. This creates significant SSRF and abuse risks.

## Decision

Replay requests execute asynchronously through dedicated queue jobs. Every
destination must pass security validation.

### Security requirements

- Reject loopback addresses by default.
- Reject private and reserved IP ranges.
- Reject cloud metadata endpoints.
- Validate DNS resolution.
- Protect against DNS rebinding.
- Revalidate the resolved destination at connection time.
- Disable redirects by default.
- Enforce connection and total timeouts.
- Restrict response sizes.
- Apply per-user and per-endpoint quotas.
- Never forward captured authentication secrets automatically.
- Never disable TLS verification by default.

Localhost replay on a hosted instance must eventually use a dedicated
authenticated local agent instead of allowing arbitrary server-side access to
internal networks.

### Replay lifecycle

`Pending → Running → Succeeded / Failed / Timed out`.

Every attempt creates an immutable `ReplayAttempt` record.

## Amendment (Phase 0, Proposed): self-hosted private targets

**Context.** The README's headline use case is "replay to localhost", and 1.x
already supports it for self-hosters through `ALLOW_INSECURE_REPLAY_TARGETS`
(commit `d4a45b7`, PR #8/#11). Blocking loopback with no opt-in would remove
that feature until the local agent exists.

**Decision.** Keep an explicit opt-in for self-hosted instances:

- `WEBHOOKTRAP_ALLOW_PRIVATE_REPLAY_TARGETS=false` by default.
- When `true`, loopback and RFC1918/ULA destinations are permitted.
- Cloud metadata endpoints (`169.254.169.254`, `fd00:ec2::254`,
  `metadata.google.internal`, …) and the rest of link-local stay blocked
  regardless of the flag.
- The application refuses to boot with the flag enabled when
  `APP_ENV=production`, matching 1.x behaviour.
- The Docker Compose quick start enables it and documents
  `host.docker.internal` for reaching apps on the host.

The local agent remains the only path to localhost replay on the hosted
service.
