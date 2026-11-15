# Retention

Retention and quotas, cross-cutting maintenance jobs: guest expiry, purge, deletion ledger (domain-model §12).

Layout (created on first use): `Application/` (services, public), `Contracts/` (interfaces and read models, public), `Events/` (domain events, public), `Domain/` (models and rules, private), `Http/` (controllers, requests, private). See `project/architecture/code-layout.md`.
