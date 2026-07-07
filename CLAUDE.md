# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Webhook Trap — a developer-first webhook debugger. Receive a webhook → inspect the raw payload → replay it to a destination → see the response (status, latency, body). Tagline: "Capture. Inspect. Replay."

## Commands

```bash
pnpm install
pnpm dev              # turbo: api on :3333 + web on :7777
pnpm build             # turbo build (all packages)
pnpm test              # turbo --filter=api (Japa)
pnpm lint              # turbo lint
pnpm check-types       # turbo check-types
pnpm format            # prettier --write on **/*.{ts,tsx,md}
```

Single package/test scoping via turbo filters or by cd-ing into the package:

```bash
pnpm --filter api dev
pnpm --filter web dev
cd apps/api && node ace test                          # all API tests
cd apps/api && node ace test --files "events.spec.ts"  # one file
cd apps/api && node ace test unit                      # one suite (unit | functional)
```

PM2 (VPS deployment of the API only, not used in local dev):

```bash
pnpm pm2:start / pm2:stop / pm2:restart / pm2:logs
```

## Monorepo layout

pnpm + Turborepo. Two apps, shared packages:

```
apps/
├── api/    AdonisJS 6 — webhook ingest, replay, auth, media
└── web/    Next.js 16 App Router — landing, dashboard, inbox
packages/
├── ui/                 shadcn/ui components + Tailwind 4 (packages/ui/src/components)
├── types/              @workspace/types — shared TS types between api and web
├── eslint-config/       flat ESLint 9 configs (base, next-js, react-internal)
└── typescript-config/   shared tsconfig bases (base, nextjs, react-library)
branding/    logo, icon, brand.css
docs/        product/architecture docs + docs/adr/ (see "Key docs" below)
```

`apps/cli/` and `packages/validators/` are planned but do not exist yet — don't assume them.

## API (`apps/api`)

AdonisJS 6, TypeScript strict, ESM with `#alias/*` subpath imports (see `imports` in `apps/api/package.json`, e.g. `#controllers/*`, `#services/*`, `#models/*`). ORM is Lucid — Postgres in prod/dev, SQLite `:memory:` in tests.

**Entry**: `bin/server.ts` → Ignitor. **Env**: loaded from monorepo-root `.env` (walks up from `apps/api/start/env.ts` looking for the nearest `.env`), validated via `Env.schema` in `start/env.ts` — add new vars there, not ad hoc `process.env` reads. See `.env.example` for required vars.

**Migrations**: Lucid CLI via ace (`node ace migration:run`, `node ace migration:make <name>`). Migrations run automatically on `pnpm dev` startup.

**Request flow**: Controller (`app/controllers`) → Service (`app/services`) → Model (`app/models`, Lucid `@belongsTo`/`@hasMany`). Transformers (`app/transformers`) serialize models for responses. Policy objects (e.g. `InboxPolicy`) gate CRUD by ownership — check these before adding new mutating routes.

**Routes** (`start/routes.ts`, plus `start/routes/2fa.ts`):
- `POST/GET/PUT/PATCH/DELETE /i/:inboxId` — public ingest, always returns `{ received: true }` even for unknown/expired inbox or oversized body (never leak inbox existence via status code)
- `/api/v1/auth/*`, `/api/v1/account/*` — auth (access tokens, GitHub/Google OAuth via Ally, TOTP 2FA via `@nulix/adonis-2fa`)
- `/api/v1/inboxes/*`, `/api/v1/events/*` — dashboard CRUD, replay, share links
- `/api/v1/media/*` — Cloudflare R2 via Adonis Drive

**ID formats are enforced at the route level** — reuse these regexes when adding routes: inbox = `/^[A-Za-z0-9]{12}$/` (nanoid(12)), event = `/^evt_[0-9A-Z]{26}$/` (`evt_${ulid()}`), replay = `rpl_${ulid()}`.

**Security & replay constraints**:
- `authorization` and `cookie` headers are sanitized to `[REDACTED]` before storage
- Replay uses native `fetch()` with **30s timeout** (non-negotiable for API reliability)
- Blocks forwarding of `connection`/`transfer-encoding`/`upgrade` headers (HTTP/2 incompatible)
- Always returns `{ received: true }` for public ingest (`/i/:inboxId`) — never leak inbox existence or auth status via response code

**Response shape**: `{ success: boolean, data: {...}, meta?: {...} }`. List endpoints accept `page`, `page_size`, `search`, `sort`, `sort_dir`, `filters`.

### Tests

Japa (`@japa/runner` + `@japa/api-client` + `@japa/plugin-adonisjs`), config in `apps/api/tests/bootstrap.ts`. Suites: `tests/unit/` (2s timeout) and `tests/functional/` (30s timeout, boots the HTTP server). Test env forces SQLite in-memory, `SESSION_DRIVER=memory`, and stubs R2/S3 vars — no real network/DB needed.

Patterns seen throughout the suite:
- Each functional test group calls `testUtils.db().migrate()` in its `group.setup()`
- Create auth: `User.create()` then `User.accessTokens.create()`, send as `Authorization: Bearer ${token.value!.release()}`
- Use `client.get/post/patch/delete(...)` from `@japa/api-client`, assert via `response.assertStatus()` / `response.assertBodyContains()`

### Background Workers

Mail worker (`apps/api/workers/mail_worker.ts`) handles async email tasks. In local dev, run via:

```bash
pnpm worker:dev          # Watch mode for development
```

In production (VPS), managed via PM2 with `pm2:start:worker` / `pm2:logs:worker`.

## Web (`apps/web`)

Next.js 16.2 App Router on port 7777. Tailwind CSS 4 (`@tailwindcss/postcss`, config lives in `packages/ui`). Components from shadcn/ui in `packages/ui/src/components`. 

**Data fetching**: TanStack Query v5 with house defaults: `staleTime: 30s`, `retry: 1`, `refetchOnWindowFocus: false`. Polling (no websockets) — use `refetchInterval` for live updates (events/replays). Query keys follow `['resource', id]` convention (e.g., `['inbox', inboxId]`, `['events', inboxId]`).

**Path aliases**: `@/` → `apps/web/`, `@workspace/ui/*` → `packages/ui/src/*`.

**API access**: two mechanisms coexist —
- **Dashboard API**: `apps/web/app/api/backend/[...path]/route.ts` proxies authenticated XHR calls to AdonisJS. `lib/api/client.ts` (axios with auth interceptor + token refresh) uses `baseURL: '/api/backend'`, so components call `/inboxes`, `/events/${id}`, etc.
- **Public ingest alias**: `proxy.ts` (Next middleware) routes `/hooks/:inboxId` → `/i/:inboxId` on AdonisJS (never authenticated, returns 200 OK always).

Typed fetch helpers live per-domain in `lib/` (`lib/events.ts`, `lib/inboxes.ts`) rather than calling axios directly from components. Auth token persistence is in `lib/auth.ts`. All API calls must go through these helpers to maintain auth interceptor behavior.

**Config**: centralize constants in `apps/web/lib/config.ts` (`siteUrl`, `apiUrl`, `productName`) instead of reading `env` or hardcoding strings elsewhere.

**Shared types** (`@workspace/types`): Import shared API types from here rather than re-defining in web. Example: `import type { Inbox, Event } from '@workspace/types'`. Add new types here when they must be shared between api and web.

```
app/
├── layout.tsx        Root layout (fonts, metadata, Providers)
├── page.tsx           Landing page (multi-section)
├── (auth)/            login, register
└── (workspace)/        dashboard, i/[inboxId], inboxes, profile
features/               feature-scoped code, e.g. features/inbox/hooks/use-inbox-query.ts
components/landing/     landing-page-only components
```

## Design system

Full spec in `DESIGN.md` (OKLCH tokens, typography, spacing, component specs) and product/brand intent in `PRODUCT.md`. Load-bearing rules:

- Terracotta palette (hue ~46–78) — never pure black/white, tint warm
- Two typographic registers: landing uses Cormorant Garamond + Lora (serif, editorial); dashboard/inbox use system UI (`font-ui`) — don't mix them
- Signal green `oklch(0.55 0.12 145)` is reserved for live/success webhook states only
- Flat product UI — borders for separation, not card shadows
- Explicitly avoid: observability dark-blue dashboards, crypto neon, glassmorphism, gradient text, generic SaaS purple/Inter templates

## Key docs (read before architecture-level work)

- `CONTEXT.md` — current status and next actions (French)
- `PRODUCT.md` — purpose, personas, design principles, anti-references
- `DESIGN.md` — design tokens and component do's/don'ts
- `docs/04-architecture.md` — architecture and data-flow diagrams
- `docs/05-product-spec-mvp.md` — user stories, API spec
- `docs/09-stack.md` — full stack decisions and alternatives considered
- `docs/10-hebergement.md` — hosting, env vars, DNS, deploy checklist
- `docs/adr/` — architecture decision records
