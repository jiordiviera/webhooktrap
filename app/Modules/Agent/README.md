# Agent

Local agents: pairing, heartbeats, job delivery (nudge + pull) and observation intake; knows nothing about findings (domain-model §5, ADR-010).

Layout (created on first use): `Application/` (services, public), `Contracts/` (interfaces and read models, public), `Events/` (domain events, public), `Domain/` (models and rules, private), `Http/` (controllers, requests, private). See `project/architecture/code-layout.md`.
