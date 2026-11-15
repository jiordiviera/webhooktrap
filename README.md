# Webhook Trap

> **Capture. Prove. Replay.** Open-source webhook tooling for developers.

Webhook Trap 2.0 is a rewrite in progress (branch `v2`). It captures webhooks
byte for byte, runs provider scenarios against your handler through a local agent, and shows
what went wrong. The 1.x app (AdonisJS + Next.js) stays available on branch `v1`.

## Stack

| Layer    | Technology                                                                   |
| -------- | ---------------------------------------------------------------------------- |
| App      | Laravel 13 (PHP 8.5), modular monolith                                       |
| UI       | Inertia v3, React 19, TypeScript, Tailwind CSS 4, shadcn/ui                  |
| Realtime | Laravel Reverb                                                               |
| Data     | PostgreSQL 18; Valkey or Redis for queues (Horizon), cache and rate limits   |
| Engine   | Go binary `webhooktrap` (agent, CI runs, server runner, sandbox) in `agent/` |
| Image    | FrankenPHP; Docker Compose for hosted and self-host                          |
| Tests    | Pest (unit, feature, architecture), Go tests                                 |

## Local development

Requirements: PHP 8.5, Composer, Node 22 with pnpm, PostgreSQL 18, Valkey or Redis, Go 1.27
(the maintainers use lerd for PHP and services).

```bash
composer install
pnpm install
cp .env.example .env        # names only: set DB_*, REDIS_*, REVERB_* for your machine
php artisan key:generate
php artisan migrate
composer dev
```

Without local services: `docker compose -f compose.dev.yaml up --build` (fill `.env.docker` first).

## Commands

```bash
php artisan test                    # Pest, incl. architecture and config tests
vendor/bin/pint && vendor/bin/phpstan analyse
pnpm run build && pnpm run check && pnpm run types:check
cd agent && go test ./...           # engine
```

## Layout

```
app/Modules/          bounded contexts (Identity, Workspace, Capture, Agent, Proving, ...)
app/Infrastructure/   adapters (Clock, Ids, Queue, Cache, RateLimit, Broadcasting, ...)
agent/                Go engine and agent
docker/               image files (Caddyfile, entrypoint, Reverb boot check, Postgres image)
compose.yaml          self-host profile
compose.production.yaml  hosted profile
project/              planning record: decisions, ADRs, specs
docs/                 documentation site
```

Details: [`project/architecture/code-layout.md`](project/architecture/code-layout.md).

## Self-hosting

Self-hosting 2.0 is not released yet. `compose.yaml` is the single self-host profile that will
ship with the first release; see `project/operations/compose-profile.md`.

## Contributing and security

See [CONTRIBUTING.md](./CONTRIBUTING.md) and [SECURITY.md](./SECURITY.md).

## License

[AGPL-3.0](./LICENSE). If you run a modified Webhook Trap as a network service, you must offer its source to your users.

Like it? [Buy me a coffee](https://buymeacoffee.com/jiordiviera).
