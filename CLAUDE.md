# CLAUDE.md

Guidance for Claude Code in this repository.

## What this is

Webhook Trap 2.0: capture webhooks, prove a handler against provider scenarios, replay. A full
rewrite (ADR-009) on Laravel + Inertia React with a Go engine. 1.x lives in git history and on
`main` only. Planning record: `project/` (decisions log, ADRs, domain model, schema, contracts,
operations). Read `project/architecture/code-layout.md` before adding code.

## Stack

- Laravel 13, PHP 8.5, strict types everywhere; Pest 5 (arch tests included).
- Inertia v3 + React 19 + TypeScript + Tailwind CSS 4 + shadcn/ui (official React starter kit), pnpm, Vite+ (`vp`).
- PostgreSQL 18 (default connection, tests too). Valkey or Redis for queue, cache and rate limits (Horizon).
- Laravel Reverb for realtime; client events are fixed to `none` (decision 50).
- Hashing: argon2id only, not env-overridable.
- Go engine `webhooktrap` in `agent/` (ADR-010), toolchain pinned in `agent/go.mod`.
- Image: FrankenPHP with `ext-uv`; Compose profiles `compose.production.yaml` (hosted), `compose.yaml` (self-host).

## Commands

```bash
composer install && pnpm install
php artisan migrate
composer dev                      # app, queue, logs, vite
php artisan test                  # Pest: Unit, Feature, Architecture
vendor/bin/pint                   # format (adds declare(strict_types=1))
vendor/bin/phpstan analyse        # Larastan level 7
pnpm run build | check | types:check
cd agent && mise exec go@1.27.4 -- go test ./...
```

Local services come from lerd (`postgres-pgvector` = PostgreSQL 18, `redis`); prefer the lerd MCP
tools over Docker for local dev. Tests use the `webhooktrap_testing` database.

## Layout and rules (enforced by tests)

- Modules: `app/Modules/{Identity,Workspace,Capture,Agent,Proving,Packs,Fixtures,Sharing,Instance,Retention}`.
  Public layers: `Application`, `Contracts`, `Events`. Private: `Domain` (models), `Http`, `Jobs`, ...
  A module never uses another module's private layers; models never cross modules.
- Adapters: `app/Infrastructure/{Persistence,Broadcasting,Queue,Cache,RateLimit,EngineProcess,RunnerApi,Clock,Ids}`;
  they never depend on modules.
- Redis facade/clients only in `Infrastructure/Queue|Cache|RateLimit`.
- Time only through `App\Infrastructure\Clock\Clock` (no `now()`, `Carbon::now()`, `date()` elsewhere).
- Ids: UUIDv7 in `uuid` columns, external form `<prefix>_` + Crockford base32 (`App\Infrastructure\Ids`).
- No static properties in modules or adapters (FrankenPHP worker mode).
- Engine: `plan`, `judge`, `render` import no I/O, clock or randomness packages (Go arch test).

## Repository rules

- `docs/` is the 2.0 Mintlify site (docs.webhooktrap.dev, deployed on every push to `v2`): edit with care, run `mint broken-links` and `mint validate` in `docs/` before committing, regenerate screenshots with `docs/scripts/screenshots.mjs`. `spikes/`, `.worktrees/`, `.playwright-mcp/` are local only.
- One local commit per finished slice on phase 5 branches; nothing is pushed; no AI attribution in commits.
- `.env.example` holds variable names only. Secrets reach containers as `<NAME>_FILE`.
- New packages beyond Laravel first-party and the starter kit's dependencies are proposals first.
