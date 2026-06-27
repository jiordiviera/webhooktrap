# Hookvane — CONTEXT

> Debugger de webhooks developer-first. Receive → Replay → See the response.

## Quick links

- [Documentation complète](./docs/README.md)
- [Product spec MVP](./docs/05-product-spec-mvp.md)
- [Architecture](./docs/04-architecture.md)

## Stack

**pnpm + Turborepo** · **AdonisJS 6.3** (API) · **Next.js 16.2** (web) · **PostgreSQL 16** · **TypeScript 6.0 strict**

- **Pas de Docker** — Postgres natif ou Neon ; **pas de Redis** au MVP
- Realtime : **polling** · Replay **synchrone** · BullMQ/Redis seulement au scale
- Hébergement : **Vercel** (web) · **VPS** (api derrière Caddy) · **Neon** (postgres) — voir [docs/10-hebergement.md](./docs/10-hebergement.md)
- Détail : [docs/09-stack.md](./docs/09-stack.md)

## Statut

MVP avancé. 20+ commits, 9 migrations. Backend complet : ingest (toujours 200), events listing/détail, replay synchrone avec timeout 30s, auth (access tokens + OAuth GitHub/Google), media (Cloudflare R2), two-factor auth (TOTP). Frontend : landing page, dashboard, inbox detail (events table + event inspector + replay panel), auth UI (login/register/OAuth), settings (sections tabulaires avec profil intégré).

## Branding

- Assets : `branding/logo.png`, `branding/icon.jpg` (Grok Imagine)
- Typo prod : Cormorant Garamond 600 + Lora 400 + `branding/brand.css` — voir [docs/08-typographie.md](./docs/08-typographie.md)
- Palette Terracotta (hue ~46–78), signal green pour succès webhook, jamais de noir/blanc pur

## Prochaine action

1. Share links (Epic D) — API controller + routes + web public page
2. CLI light (Epic E) — npx @hookscope/cli
3. Tests — couverture pour ingest, replay, auth, share
4. Landing page polish — copy, illustrations, SEO