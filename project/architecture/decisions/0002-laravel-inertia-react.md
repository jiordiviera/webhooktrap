# ADR-002: Laravel + Inertia + React

**Status:** Accepted
**Date:** 2026-11-11

## Context

The product requires a strong backend, an interactive developer interface and
real-time updates.

## Decision

Use a Laravel application with an integrated Inertia React frontend.

| Layer              | Technology                                  |
| ------------------ | ------------------------------------------- |
| Backend            | Laravel, current supported stable release   |
| Language           | PHP                                         |
| Frontend           | React                                       |
| Frontend language  | TypeScript                                  |
| Bridge             | Inertia.js v3                               |
| UI                 | Tailwind CSS                                |
| Components         | shadcn/ui                                   |
| Realtime           | Laravel Reverb                              |
| Client broadcasting| Laravel Echo                                |
| Database           | PostgreSQL                                  |
| Queue and cache    | Redis (see ADR-007 amendment)               |
| Testing            | Pest                                        |
| API specification  | OpenAPI                                     |

Exact package versions must be pinned in Composer and the JavaScript lockfile
after a compatibility spike.

### Versions verified in Phase 0 (2026-11-11)

| Package                 | Latest   | Released   |
| ----------------------- | -------- | ---------- |
| `laravel/framework`     | 13.42.0  | 2026-11-10 |
| `inertiajs/inertia-laravel` | 3.7.0 | 2026-11-02 |
| `@inertiajs/react`      | 3.11.0   | 2026-11-02 |
| `laravel/reverb`        | 1.13.0   | 2026-10-19 |
| `@laravel/echo-react`, `laravel-echo` | 2.6.3 | 2026-10-27 |

Local toolchain: PHP 8.5, Composer 2.10 (via lerd).

## Consequences

- One primary application deployment.
- Shared authentication and authorization.
- No separately deployed Next.js frontend.
- Inertia handles page navigation and server-driven props.
- API endpoints remain available for external integrations.
- 1.x component code in `packages/ui` is React + Tailwind 4 + shadcn and can
  be reused as implementation material. The 1.x visual identity
  (`DESIGN.md`, terracotta palette) is **not** carried over by default: the
  2.0 brand is explored independently in Phase 1 (A02).
