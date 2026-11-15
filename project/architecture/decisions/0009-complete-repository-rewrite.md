# ADR-009: Complete Repository Rewrite

**Status:** Accepted — open questions below
**Date:** 2026-11-11

## Context

The existing Webhook Trap implementation (1.x) is a pnpm/Turborepo monorepo
with an AdonisJS 7 API and a Next.js 16 web app (see
`project/management/repository-audit.md`). Production runs the web app
on Vercel, the API under PM2 on a VPS, and PostgreSQL on Neon.

The new product uses Laravel and Inertia rather than preserving the previous
architecture.

## Decision

Rebuild the application from scratch inside the existing repository. The
previous implementation is not part of the new runtime architecture.

Preserve only useful product knowledge, documentation, branding, domain
decisions and validated functionality.

### Migration strategy

1. Audit the existing repository. *(done — Phase 0, 2026-11-11)*
2. Preserve relevant assets and documentation.
3. Create a dedicated rewrite branch. *(done — `rewrite/v2`)*
4. Archive the old implementation outside the new runtime tree.
5. Bootstrap the new Laravel application.
6. Introduce new CI and development infrastructure.
7. Replace outdated documentation.
8. Release the new implementation as Webhook Trap 2.0.

Never delete the existing production data or deployment configuration without
an explicit backup and migration decision.

### Inventory from the Phase 0 audit

Keep (port or reuse):

- `branding/` (logo, icon, `brand.css`), `DESIGN.md`, `PRODUCT.md`.
- Design tokens and shadcn components in `packages/ui`.
- `LICENSE` (MIT, confirmed), `SECURITY.md`, `CONTRIBUTING.md`, issue and PR
  templates (to be rewritten for the new toolchain).
- Product and decision history in `.docs/`.
- SSRF guard test cases from `apps/api/tests/unit/ssrf_guard.spec.ts` as the
  starting regression list for ADR-006.

Archive (not part of the 2.0 runtime):

- `apps/api`, `apps/web`, `packages/types`, `packages/eslint-config`,
  `packages/typescript-config`, Turborepo/pnpm workspace config,
  `ecosystem.config.cjs`, the 1.x `docker-compose.yml` and CI workflow.

`docs/` belongs to the live Mintlify user docs site
(`docs.webhooktrap.dev`) and is not used for internal 2.0 documents
(product owner decision, 2026-11-11). Internal governance, architecture,
product, brand and design documents live in `project/`. Sensitive
vulnerability details are kept outside the repository entirely.

### Amendment (Phase 0, Proposed): ingest URL compatibility

Providers in production are configured with 1.x URLs
(`/i/{inboxId}` and `/hooks/{inboxId}`, 12-character nanoid IDs). 2.0 must
accept those paths and legacy IDs for capture, alongside the new opaque
endpoint IDs, until a deprecation is announced. Capture keeps the 1.x rule of
never leaking endpoint existence through status codes.

## Open questions

These need a product decision before Phase 2.

1. **Production data.** Migrate 1.x inboxes, events and users from Neon into
   the 2.0 schema, or launch 2.0 empty and keep 1.x read-only for a period?
2. **Legacy URL lifetime.** How long must `/i/{id}` and `/hooks/{id}` keep
   working, and do legacy inboxes map to 2.0 endpoints?
3. **1.x features not in the 2.0 plan.** Decide keep / defer / drop for each:
   - GitHub and Google OAuth sign-in
   - TOTP two-factor authentication
   - Public share links for a single event
   - Media uploads (avatars) on Cloudflare R2
   - Transactional mail (OTP, email verification, password reset)
4. **Hosting.** Does the hosted service stay on the current VPS (now running
   PHP, Reverb and workers) or move?
