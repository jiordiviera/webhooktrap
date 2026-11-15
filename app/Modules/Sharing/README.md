# Sharing

Visibility and scrubbed share rendering (`/s/{shareToken}`); owner-only props are absent for viewers (domain-model §10).

Layout (created on first use): `Application/` (services, public), `Contracts/` (interfaces and read models, public), `Events/` (domain events, public), `Domain/` (models and rules, private), `Http/` (controllers, requests, private). See `project/architecture/code-layout.md`.
