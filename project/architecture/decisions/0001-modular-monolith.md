# ADR-001: Modular Monolith

**Status:** Accepted
**Date:** 2026-11-11

## Context

Webhook Trap needs several independent business capabilities: capture,
delivery inspection, replay, monitoring and user management.

Microservices would introduce unnecessary deployment, networking and
observability complexity at this stage.

## Decision

Adopt a modular monolith with explicit domain boundaries.

Initial domains:

- Capture
- Endpoint
- Delivery
- Replay
- Workspace
- Identity
- Monitoring
- Retention

Each domain owns its business rules, application services and domain events.

Proposed layout (a convention, not a requirement to create empty folders
before their first use):

```
app/
├── Domain/          Capture, Endpoint, Delivery, Replay, Identity, Workspace, Monitoring, Retention
├── Application/     Capture, Delivery, Replay, Workspace
├── Infrastructure/  Persistence, Broadcasting, Storage, Http
├── Http/            Controllers, Middleware, Requests
└── Providers/

resources/js/
├── components/  features/{endpoints,deliveries,replay,monitoring,workspaces}/
├── layouts/  pages/  hooks/  types/

tests/  Unit/  Feature/  Integration/  Architecture/
```

## Consequences

Positive:

- Easier development and deployment.
- Strong separation of business responsibilities.
- Simpler testing.
- Future extraction of individual modules remains possible.

Negative:

- Requires discipline to prevent cross-domain coupling.
- Module boundaries must be enforced through architecture tests (Pest `arch()`)
  and code review.
