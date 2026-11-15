# Repository Audit — Webhook Trap 1.x

**Phase:** 0 · **Author:** A00 (Orchestrator) · **Date:** 2026-11-11
**Status:** REVIEW_REQUIRED
**Scope:** branch `main` at `27e9d98` (audited on `rewrite/v2`, identical
code). No application code was modified.

Method: A00 audited infrastructure, deployment, CI, history and secrets
directly; two read-only inventory sub-agents read `apps/api` and
`apps/web` + `packages/`. A00 re-read the code behind every critical
finding before recording it.

## 1. Snapshot

| Item | Value |
| ---- | ----- |
| Repository | `github.com/jiordiviera/webhooktrap` (public, MIT, 10 stars, 1 fork, 0 open issues/PRs, no releases or tags). Mirror: `gitlab.com/jiordiviera/webhooktrap` |
| History | 198 commits on `main`, 2026-06-22 → 2026-09-28, single author (two git identities) |
| Layout | pnpm 9 + Turborepo 2.10 monorepo: `apps/api`, `apps/web`, `apps/docs` (empty), `packages/{ui,types,eslint-config,typescript-config}` |
| API | AdonisJS **7** (`@adonisjs/core ^7.3.3`), Lucid, VineJS, Ally, BullMQ, Resend, Drive (R2), Sentry, TypeScript ~6.0, Japa |
| Web | Next.js 16.2 App Router, React 19.2, TanStack Query 5, axios, react-hook-form + zod, Tailwind 4, shadcn (radix) |
| Database | PostgreSQL 16 in production (Neon); SQLite in-memory in tests |
| Realtime | Polling every 3 s on the inbox page |
| Production | `webhooktrap.dev` (Vercel), `api.webhooktrap.dev` (VPS, PM2, Caddy, behind Cloudflare), `docs.webhooktrap.dev` (Mintlify, `docs/` folder). All returned 200 on 2026-11-11 |

Note: `CLAUDE.md`, `CONTEXT.md` and `.docs/09-stack.md` describe the API as
AdonisJS 6 and mention `docs/adr/`, which does not exist. They are out of
date.

## 2. Directory map

| Path | Content | 2.0 disposition |
| ---- | ------- | --------------- |
| `apps/api/` | AdonisJS API: ingest, inboxes, events, replay, share, auth (tokens, OAuth, 2FA, OTP), media, mail worker; 12 migrations; 41 tests | Archive; mine for business rules and test cases |
| `apps/web/` | Next.js app: landing (13 sections), auth flows, dashboard, inbox list/detail, event inspector, replay panel, settings, API tokens, share page | Archive; mine for flows and copy |
| `apps/docs/` | Empty directory (Fumadocs removed in `428e7aa`) | Remove when archiving |
| `packages/ui/` | Tailwind 4 tokens + ~20 shadcn components + loader + brand SVGs | Reuse components as material; tokens not carried over (new brand) |
| `packages/types/` | 1.x DTOs | Archive |
| `packages/eslint-config/`, `packages/typescript-config/` | Shared configs | Archive; 2.0 starts from Laravel's toolchain |
| `docs/` | **Published Mintlify site** (docs.json + legacy mint.json, orphan pages, starter README) | Stays the user docs site; rewritten in Phase 7 |
| `project/` | 2.0 internal governance, ADRs, product, brand, design (new, untracked) | 2.0 working area |
| `.docs/` | French product/decision history 01–10 (vision, journey, business, architecture, MVP spec, naming, competition, typography, stack, hosting) | Keep as A01/A05 input |
| `branding/` | `logo.png`, `icon.jpg`, `brand.css` | Keep as history; 2.0 brand is explored fresh |
| `.github/` | CI workflow, issue templates, PR template | Rewrite for the PHP toolchain |
| Root files | `README`, `CONTRIBUTING`, `SECURITY`, `DEPLOYMENT`, `PRODUCT`, `DESIGN`, `CONTEXT`, `AGENTS`, `CLAUDE`, `LICENSE` (MIT), `docker-compose.yml`, `ecosystem.config.cjs`, `turbo.json`, `pnpm-*` | See rebuild strategy §4 |
| `.claude/`, `.impeccable/`, `.sisyphus/` | Tool state, one tracked file each | Review before release |
| `spikes/live-props/` | Paused 2.0 compatibility spike (untracked) | Phase 4 |

## 3. Architecture (1.x)

```
Provider ──POST /hooks/{id}──▶ Vercel (Next proxy.ts) ──▶ api.webhooktrap.dev/i/{id}
Browser  ──XHR /api/backend/*─▶ Vercel route handler ──▶ api.webhooktrap.dev/api/v1/*
API (AdonisJS, PM2) ──▶ Neon PostgreSQL · Cloudflare R2 · BullMQ(Redis) → mail worker → Resend
Browser polls GET /inboxes/{id}/events every 3 s
```

- Two public ingest URLs exist: `/hooks/{id}` (web origin, rewritten) and
  `/i/{id}` (API origin). Both must be considered live in providers'
  configurations.
- Auth is bearer tokens kept in `localStorage`; nothing is protected
  server-side in the web app (client-side redirects only).
- Replay is synchronous inside the HTTP request (30 s timeout).

## 4. Data model (1.x)

`users`, `auth_access_tokens`, `inboxes` (12-char nanoid PK, nullable
`user_id`, `expires_at`), `events` (`evt_` + ULID, `query`/`headers` JSON,
`body_text`, `body_json`), `replays` (`rpl_` + ULID), `share_tokens`,
`user_oauth_accounts`, `otps`, `media` (polymorphic, no FK).

**Capture fidelity is lossy:** header names lowercased, duplicates merged,
order lost; raw body bytes never stored; JSON/form bodies parsed and
re-serialised (empty strings become `null`); multipart files dropped; binary
decoded as lossy UTF-8; `size_bytes` is the re-serialised size. Provider
HMAC signatures cannot be verified against stored bodies, and replays are not
byte-exact. ADR-005 addresses this.

## 5. Security findings

Ten findings (P-01 – P-10: four Critical, three High, three Medium) are
tracked in [`risk-register.md`](risk-register.md) §A. Their details are kept
in a private report outside this repository (`SEC-2026-11`).

Non-sensitive design observations relevant to 2.0: only a short list of
headers is redacted, and replays forward the literal `[REDACTED]` value;
a missing inbox returns 403 rather than 404; CSRF and CSP are disabled in
the API configuration.

## 6. Tests and CI

- API: 41 Japa tests (ingest 6, inboxes 6, API tokens 6, OTP 5+1, media 2,
  profile 1, replay 2 — both negative, SSRF guard 12).
- Untested: login/signup, OAuth, 2FA, password reset, share links, a
  successful replay, event detail, body limits, non-JSON bodies, header
  redaction, purge command, mail worker.
- Web: no tests. Lint (`--max-warnings 0`) and `tsc --noEmit` are clean;
  `ui` and `types` name their script `typecheck`, so Turbo skips them.
- CI (`.github/workflows/ci.yml`): lint, check-types, API tests on Node 24
  with a Redis service. No build of web or docs, no deploy job.

## 7. Deployment and configuration

- `docker-compose.yml` (dev only): postgres 16, api (`pnpm dev`), web
  (`pnpm dev`); sets `ALLOW_INSECURE_REPLAY_TARGETS=true` and a fallback
  `APP_KEY` — not a production compose file.
- `ecosystem.config.cjs`: PM2 apps `webhooktrap-api` and
  `webhooktrap-mail-worker` on the VPS.
- `DEPLOYMENT.md` documents Railway, Fly.io and VPS + Caddy + systemd;
  `.docs/10-hebergement.md` documents the actual Vercel + VPS + Neon setup.
- Env vars: see `.env.example` (APP, DB, OAuth, R2, Resend, Sentry, Redis,
  `ALLOW_INSECURE_REPLAY_TARGETS`, `NEXT_PUBLIC_*`).
- **Production-sensitive files:** the local, untracked `.env`; VPS-side
  `.env`, Caddyfile and PM2 state; Neon database; R2 bucket; OAuth app
  credentials; Mintlify GitHub integration; Vercel project; DNS at
  Cloudflare. None of these are touched by Phase 0. `.env.test` is tracked
  but holds test-only values.

## 8. Technical debt (selected)

API: duplicate Content-Length checks; inconsistent error shapes; mixed
`vine.compile`/`vine.create`; wrong sort column for `lastEventAt`;
`ingestUrl` points at `/hooks` while the API route is `/i`; mail queue ignores
`REDIS_*`; purge command never scheduled; no retention; no replay response
cap; dead code (`verify_email.ts`, `assertMimeMatchesBuffer`, unused limiter,
unused AWS SDK deps); unescaped `%`/`_` in ILIKE search; `@japa/api-client` in
runtime deps.

Web: dead components and an unimported `landing.css` the hero depends on;
non-JSON bodies render as "(empty)"; multipart proxied through `req.text()`
(likely corrupts uploads); `PaginatedMeta` defined twice with different
shapes; clickable table rows not keyboard-accessible; placeholder-only
labels; `console.log` leftovers; developer-facing error copy ("port 3333");
naming drift between Webhook Trap / hookscope / webhooktrap; three env
loaders; `@uiw/react-json-view` alpha.

Docs: two Mintlify configs (`docs.json`, legacy `mint.json`) with different
GitHub URLs; orphan pages; boilerplate README; stale `CLAUDE.md` /
`CONTEXT.md`.

## 9. Branches

| Branch | vs `main` | Note |
| ------ | --------- | ---- |
| `main` | — | Production |
| `rewrite/v2` | +0 (uncommitted 2.0 docs) | 2.0 work |
| `redesign-warm-workbench` | +1 (`801b2b7`) | Unmerged 1.x redesign (59 files); also pushed to GitLab. Not carried into 2.0 |
| `docs/styling`, `feat/nav-cleanup-sponsor-link`, `release/oss`, issue-7 branch | 0 ahead | Fully merged; safe to delete later (not done) |

## 10. Reusable product knowledge

- Product intent, personas and anti-references: `PRODUCT.md`, `.docs/01–07`.
- Business rules worth keeping: always-`{received:true}` ingest; 1 MiB body
  cap; 48 h anonymous lifetime; 30 s replay timeout; hop-by-hop header
  stripping; SSRF block list and its 12 test cases; ID shapes.
- UX flows worth reviewing: anonymous-vs-saved inbox choice; copy as cURL /
  JSON; default replay URL per inbox; Cmd+K search; the three-pane inbox from
  `redesign-warm-workbench`.
- Content: landing copy, Mintlify guides (Stripe/GitHub/Shopify
  integrations, concepts, security).
