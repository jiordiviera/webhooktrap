# Packs

Read-only provider pack data shared with the engine, versioned (domain-model §8). Files live in `packs/` at the repository root (`manifest.json`, `<id>/<version>.json`, schema in `packs/_schema/`); `Application\PackCatalog` loads them and derives a capture's facts (`Contracts\DerivedFacts`) as a pure function of headers, body and packs.

Layout (created on first use): `Application/` (services, public), `Contracts/` (interfaces and read models, public), `Events/` (domain events, public), `Domain/` (models and rules, private), `Http/` (controllers, requests, private). See `project/architecture/code-layout.md`.
