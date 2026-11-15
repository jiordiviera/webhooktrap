# Identity

Who the principal is: users, guest sessions (`guest` guard), personal/CI/runner tokens, second factors, passkeys, recovery (domain-model §2).

Layout (created on first use): `Application/` (services, public), `Contracts/` (interfaces and read models, public), `Events/` (domain events, public), `Domain/` (models and rules, private), `Http/` (controllers, requests, private). See `project/architecture/code-layout.md`.
