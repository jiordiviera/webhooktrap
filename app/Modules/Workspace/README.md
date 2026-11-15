# Workspace

Workspaces and projects; ownership checks (`Workspace::isOwnedBy($principal)`) for every principal (domain-model §3).

Layout (created on first use): `Application/` (services, public), `Contracts/` (interfaces and read models, public), `Events/` (domain events, public), `Domain/` (models and rules, private), `Http/` (controllers, requests, private). See `project/architecture/code-layout.md`.
