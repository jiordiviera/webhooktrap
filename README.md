# Webhook Trap

> Webhook debugging, simplified. Capture → Inspect → Replay.

## Stack

| Layer | Stack |
|-------|-------|
| Frontend | Next.js 16.2 (App Router), Tailwind CSS 4, shadcn/ui, TanStack Query |
| Backend | AdonisJS 6, Lucid ORM (PostgreSQL) |
| Auth | Adonis Auth (access tokens + GitHub/Google OAuth), TOTP 2FA |
| Media | Cloudflare R2 via Adonis Drive |
| Monorepo | pnpm + Turborepo |
| Hosting | Vercel (web), VPS + PM2 (api), Neon (Postgres) |

## Quick start

```bash
pnpm install
pnpm dev              # api :3333 + web :7777
pnpm test             # turbo --filter=api
pnpm lint             # turbo lint
pnpm check-types      # turbo check-types
pnpm format           # prettier all
```

## Project structure

```
hookscope/
├── apps/
│   ├── api/          AdonisJS 6 — webhook ingest + replay + auth
│   └── web/          Next.js 16 App Router — landing, inbox, dashboard, settings
├── packages/
│   ├── ui/           shadcn/ui components + Tailwind 4 CSS
│   ├── eslint-config/
│   └── typescript-config/
├── branding/         logo, icon, brand CSS
└── docs/             Product specs, architecture, ADRs
```

## Config

Key constants are centralized in `apps/web/lib/config.ts`:

```ts
import { siteUrl, apiUrl, productName } from '@/lib/config'
```

See [AGENTS.md](./AGENTS.md) for agent workflows and conventions.
