# Code layout · Webhook Trap 2.0

**Status:** Phase 5 foundation · **Author:** A10 with A05's architecture · **Date:** 2026-11-15
**Read with:** ADR-001 (modular monolith), `domain-model.md` §1 (modules and rules), ADR-010 (engine).

This is where code goes. It realises ADR-001's convention with the Phase 4
module list (domain-model §1), not ADR-001's original domain list.

## Repository root

```
app/                 Laravel application (PHP 8.5, strict types)
├── Modules/         bounded contexts, one folder each (below)
├── Infrastructure/  adapters: no business rules, never depend on a module
├── Http/            kit-level middleware and shared controllers only
├── Providers/       service providers (bindings for adapters live here)
├── Actions/, Concerns/, Models/   React starter kit code; moves into Identity in slice 1
agent/               Go module for the `webhooktrap` engine and agent (ADR-010)
bootstrap/ config/ database/ public/ resources/ routes/ storage/   Laravel standard
resources/js/        Inertia React + TypeScript + Tailwind 4 + shadcn/ui (starter kit layout)
tests/
├── Architecture/    Pest arch() and source-scan rules (domain-model §1.2)
├── Feature/         HTTP and config tests (PostgreSQL, like production)
└── Unit/            pure PHP tests
docker/              image files: Caddyfile and trust snippets, php.ini, entrypoint, reverb-boot, postgres image
.github/workflows/   ci, container, deploy (Actions pinned to commit SHAs)
deploy/policy/       (planned) Compose policy installed on the VPS, never read from the image (ci-cd.md §4.2)
compose.yaml         self-host profile (compose-profile.md §4)
compose.production.yaml   hosted profile (compose-profile.md §2)
compose.dev.yaml     contributors: builds locally (optional; lerd is the default local setup)
docs/                live Mintlify docs site (not touched by the 2.0 build)
project/             planning record (decisions, ADRs, specs)
```

`spikes/`, `.worktrees/` and `.playwright-mcp/` are local only and ignored.

## Modules (`app/Modules/<Module>`, namespace `App\Modules\<Module>`)

| Module | Owns |
| ------ | ---- |
| Identity | users, guest sessions (`guest` guard), tokens, factors, passkeys, recovery |
| Workspace | workspaces, projects, ownership checks |
| Capture | capture URLs, captures (raw HTTP preservation) |
| Agent | agents, pairing, job delivery, observation intake |
| Proving | runs, run jobs, scenarios, deliveries, findings (server side) |
| Packs | read-only provider pack data |
| Fixtures | fixture metadata |
| Sharing | visibility, scrubbed share rendering |
| Instance | self-host administration |
| Retention | retention and quotas (maintenance jobs, deletion ledger) |

Inside a module, folders are created on first use:

| Folder | Visibility | Holds |
| ------ | ---------- | ----- |
| `Application/` | public | application services other modules call |
| `Contracts/` | public | interfaces, read models (incl. read-only Eloquent models a module exposes on purpose) |
| `Events/` | public | domain events (the only thing that crosses modules besides services) |
| `Domain/` | private | Eloquent models, value objects, rules |
| `Http/` | private | controllers, form requests, Inertia pages' props |
| `Jobs/`, `Listeners/`, `Console/`, `Database/` | private | queue jobs, event listeners, commands, factories |

## Infrastructure adapters (`app/Infrastructure/<Adapter>`)

Persistence, Broadcasting, Queue, Cache, RateLimit, EngineProcess, RunnerApi,
Clock, Ids. Each folder has a README stating its job. Implemented in the
foundation: `Clock` (`Clock` contract, `SystemClock`, bound in
`AppServiceProvider`) and `Ids` (`IdGenerator` for UUIDv7, `EntityId` for the
`<prefix>_` + Crockford base32 external form, schema §1.1).

## Rules enforced by tests

| Rule | Source | Test |
| ---- | ------ | ---- |
| No module uses another module's `Domain`, `Http`, `Jobs`, `Listeners`, `Console`, `Database` (models never cross) | domain-model §1.2 rules 1, 2 | `tests/Architecture/ModuleRulesTest.php` |
| Eloquent models stay out of a module's `Application`, `Events`, `Http` | rule 1 | same |
| Infrastructure never depends on modules | ADR-001 | same |
| Redis facade/clients (`Illuminate\Redis`, `Redis`, `Predis`) only in `Infrastructure/Queue`, `Cache`, `RateLimit`; Valkey and Redis are interchangeable | rule 3, decision 32 §4, HD-8 | same |
| `now()`, `today()`, `time()`, `date()`, `Date` facade only in `Infrastructure/Clock` | rule 4, D-10 | same |
| `::now()`, `::today()` static calls only in `Infrastructure/Clock` | rule 4 | `tests/Architecture/ClockSourceScanTest.php` |
| `declare(strict_types=1)` everywhere in `app/` | foundation | `ModuleRulesTest.php` + Pint `declare_strict_types` |
| No static properties in modules and adapters | ADR-005 amendment 1 §7 (worker mode) | `tests/Architecture/WorkerModeStateTest.php` |
| Reverb client events are `none`, not env-overridable | decision 50 | `tests/Feature/Config/RealtimeConfigTest.php` |
| Hashing is argon2id, not env-overridable | domain-model §2.1 rev. 6 | `tests/Feature/Config/HashingConfigTest.php` |

Rules 5 and 6 (Proving never formats probe values; finding prose rendered by
the server) get their tests with the Proving slice. Adding a module means
adding it to `tests/Architecture/Modules.php`.

## Go engine (`agent/`)

Module `github.com/jiordiviera/webhooktrap/agent`, binary `cmd/webhooktrap`.
Packages per ADR-010: `plan`, `execute`, `judge`, `render`, `guard`,
`signing`, `scrub`, plus `internal/version`. No package imports `time.Now()`
outside a future clock adapter (ADR-010 D-2; lint rule added with the first
code that needs time).
