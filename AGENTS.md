# Webhook Trap — Agent Guide

## Quick start

```bash
pnpm dev           # turbo: api :3333 + web :7777
pnpm test          # turbo --filter=api
pnpm lint          # turbo lint
pnpm check-types   # turbo check-types
pnpm format        # prettier all
```

## Monorepo structure

```
hookscope/
├── apps/
│   ├── api/          AdonisJS 6 — webhook ingest + replay + auth
│   └── web/          Next.js 16 App Router — landing, inbox, dashboard
├── packages/
│   ├── ui/           shadcn/ui components + Tailwind 4 CSS (globals.css)
│   ├── eslint-config/  ESLint 9 flat config (base, next-js, react-internal)
│   └── typescript-config/  Shared TS configs (base, nextjs, react-library)
├── branding/         logo.png, icon.jpg, brand.css
└── docs/             Vision, architecture, stack, hosting, ADRs
```

- `apps/cli/` and `packages/validators/` are planned but not yet created.

## API (`apps/api`)

**Framework**: AdonisJS 6 (TypeScript 6.0 strict)
**Entry**: `bin/server.ts` → Ignitor → AdonisJS app
**ORM**: Lucid (PostgreSQL prod, SQLite in-memory in tests)
**Auth**: Adonis Auth (access tokens) + OAuth (GitHub, Google)
**Validation**: VineJS (server-side)
**IDs**: `inboxId()` = nanoid(12), `eventId()` = `evt_${ulid()}`, `replayId()` = `rpl_${ulid()}`
**Env**: `.env` at **monorepo root** — loaded from `start/env.ts` via `new URL('../../../', import.meta.url)`

### Routes

- **Public ingest** — `POST /i/:inboxId` (always 200 OK, no auth)
- **Auth/account** — `/api/v1/auth/*`, `/api/v1/account/*`
- **Dashboard** — `/api/v1/inboxes/*`, `/api/v1/events/*`
- **Media** — `/api/v1/media/*` (Cloudflare R2 via Adonis Drive)

### Pattern

Controllers → Services → Models (Lucid ORM with `@belongsTo`/`@hasMany`)
Transformers handle serialization (e.g. `InboxTransformer.transform()`).
Policy objects (`InboxPolicy`) gate CRUD by ownership.

### Tests (Japa)

```bash
node ace test                     # from apps/api
pnpm test                         # from root (turbo --filter=api)
```

- Framework: Japa (`@japa/runner` + `@japa/api-client` + `@japa/plugin-adonisjs`)
- Suites: `tests/unit/` (timeout 2s) and `tests/functional/` (timeout 30s)
- Test setup forces SQLite `:memory:`, `SESSION_DRIVER=memory`, stubs R2/S3 env vars
- Each test group calls `testUtils.db().migrate()` in setup

**Key patterns from tests:**
- Create user via `User.create()` then generate token via `User.accessTokens.create()`
- Pass token as `Authorization: Bearer ${token.value!.release()}`
- Use `client.get/post/delete(...)` from `@japa/api-client`
- Assert with `response.assertStatus()`, `response.assertBodyContains()`

### Important API conventions

- Ingest **always returns** `{ received: true }` even on unknown/expired inbox or oversized body
- Headers `authorization` and `cookie` are **sanitized to `[REDACTED]`** on storage
- Replay uses native `fetch()` (Node), 30s timeout, blocks connection/transfer-encoding/upgrade headers
- Response envelope: `{ success: boolean, data: { ... }, meta?: { ... } }`
- Pagination: `page`, `page_size`, `search`, `sort`, `sort_dir`, `filters` query params
- ID patterns enforced in route: inbox = `/^[A-Za-z0-9]{12}$/`, event = `/^evt_[0-9A-Z]{26}$/`

## Web (`apps/web`)

**Framework**: Next.js 16.2 App Router, port **7777**
**Styling**: Tailwind CSS 4 (via `@tailwindcss/postcss`), config in `packages/ui/postcss.config.mjs`
**Components**: shadcn/ui (Radix Luma style) in `packages/ui/src/components/`
**Fonts**: Cormorant Garamond (display), Lora (body), Geist/system-ui (UI)
**Data fetching**: TanStack Query v5 (`@tanstack/react-query`) with `refetchInterval` for polling
**HTTP**: axios client in `lib/api/client.ts` with auth interceptor
**API proxy**: `proxy.ts` rewrites `/api/v1/*` and `/hooks/:inboxId` → `APP_URL` (default `http://localhost:3333`)

### App structure

```
app/
├── layout.tsx             Root layout (fonts, metadata, Providers)
├── page.tsx               Landing page (multi-section)
├── (auth)/                login, register
├── (workspace)/           dashboard, i/[inboxId], inboxes, profile
└── landing/               Empty (page.tsx is the landing)
```

### Feature organization

```
features/                  Feature-specific code
├── inbox/hooks/           use-inbox-query.ts, use-event-queries.ts
├── dashboard/context/
└── data-table/            Shared data-table components + hooks
components/landing/        Landing page components
lib/                       Shared utilities
├── api/client.ts          axios client with auth
├── events.ts              Typed fetch helpers for events
├── inboxes.ts             Typed fetch helpers for inboxes
└── auth.ts                Token storage
```

### Important web conventions

- `@/` maps to `apps/web/` root, `@workspace/ui/*` maps to `packages/ui/src/*`
- API calls use typed functions per domain (e.g. `fetchInboxEvents()`, `replayEvent()`)
- Auth token stored locally (`lib/auth.ts`), injected via axios interceptor
- TanStack Query stale time: 30s, retry: 1, refetchOnWindowFocus: false
- `proxy.ts` serves as Next.js middleware, matching `/api/v1/:path*` and `/hooks/:inboxId`

## Design system

Source of truth: `DESIGN.md` (OKLCH tokens, typography, spacing, component specs)

**Key rules:**
- **Terracotta palette** (hue ~46–78), never pure black/white — tint warm
- **Two registers**: Landing uses Cormorant + Lora (serif editorial); dashboard/inbox use system-ui (`font-ui`)
- **Signal green** (`oklch(0.55 0.12 145)`) for live/success webhook states only
- **Flat product UI** — borders for separation, not card shadows
- **No** observability dark-blue, crypto neon, glassmorphism, gradient text, SaaS purple

## Deployment

| Service | Platform | What |
|---------|----------|------|
| Web | Vercel | `apps/web` — Next.js |
| API | VPS + PM2 | `apps/api` — AdonisJS long-running |
| DB | Neon | PostgreSQL 16 |
| Media | Cloudflare R2 | Via `@adonisjs/drive` |

## Key docs (read before architecture work)

- `CONTEXT.md` — Project summary, status, next actions
- `PRODUCT.md` — Product purpose, brand, personas, design principles
- `DESIGN.md` — Design tokens, typography, component specs, do's/don'ts
- `docs/04-architecture.md` — Architecture, data model, flow diagrams
- `docs/05-product-spec-mvp.md` — User stories, wireframes, API spec
- `docs/09-stack.md` — Complete stack decisions, phases, alternatives
- `docs/10-hebergement.md` — Hosting setup, env vars, DNS, deploy checklist
- `docs/adr/001-media-library-vercel-blob.md` — Media library ADR
