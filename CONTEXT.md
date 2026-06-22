# Hookscope — CONTEXT

> Debugger de webhooks developer-first. Receive → Replay → See the response.

## Quick links

- [Documentation complète](./docs/README.md)
- [Product spec MVP](./docs/05-product-spec-mvp.md)
- [Architecture](./docs/04-architecture.md)

## Stack

**pnpm + Turborepo** · **AdonisJS 6** (API) · **Next.js 15** (web) · **PostgreSQL 16** · **TypeScript strict**

- **Pas de Docker** — Postgres natif ou Neon ; **pas de Redis** au MVP
- Realtime : **polling** · Replay **synchrone** · BullMQ/Redis seulement au scale
- Hébergement : **Vercel** (web) · **Railway** (api) · **Neon** (postgres) — voir [docs/10-hebergement.md](./docs/10-hebergement.md)
- **Greenfield** — pas de code `pulse-send`
- Détail : [docs/09-stack.md](./docs/09-stack.md)

## Statut

Pré-développement. Spec validée, code non démarré.

## Branding

- Assets : `branding/logo.png`, `branding/icon.jpg` (Grok Imagine)
- Typo prod : Cormorant Garamond 600 + `branding/brand.css` — voir [docs/08-typographie.md](./docs/08-typographie.md)

## Prochaine action

1. Registrar `hookscope.dev`
2. Scaffold monorepo `apps/api` + `apps/web` + `apps/cli`
3. Slice vertical : ingest → event list → replay → response view