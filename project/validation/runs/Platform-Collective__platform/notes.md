# Platform-Collective/platform (GitHub, main stratum, pilot 2)

- Commit inspected: `5808426f3d4efb1f60d996afd48218777f7d7a13` (default branch `develop`, shallow clone 2026-11-14, 360 MB).
- Handler: `services/github/pod-github/src/server.ts` (`@octokit/webhooks` `createNodeMiddleware`, path `/api/webhook`), event handlers in `services/github/pod-github/src/platform.ts` (`initWebhooks`).

## Result: SKIPPED (rule 6), no run file

Setup clock: clone 15:37:21 to 15:37:35 UTC, read-only assessment until 15:38:30 UTC. **About 2 minutes spent** before the skip decision. Nothing was installed or built.

Why it cannot start locally with test-mode values only:

1. **Needs a registered GitHub App at startup.** `start()` in `server.ts` calls `GET /app` on api.github.com, authenticated as the GitHub App (App ID + private key), *before* the HTTP server listens. If that call fails, the server never starts listening. GitHub Apps have no test or sandbox mode, and creating one means creating an identity on GitHub (not allowed for this study). The handler must also have no internet access while it runs, so the call cannot succeed.
2. **Needs the Huly backend.** `PlatformWorker.create()` calls the Huly account service (`listIntegrations`) and the process exits with code 1 if that fails. The account service depends on the Huly dev stack (`dev/docker-compose.yaml`, about 40 services: database, object storage, queue, transactor, collaborator…).
3. **Build cost.** The service is built with Rush (`rush bundle --to @hcengineering/pod-github`) inside a 360 MB monorepo. It might fit in the timebox, but points 1 and 2 decide.

Stubbing was considered and rejected. A fake api.github.com (local TLS CA plus a hosts override) and a fake account service returning no integrations would let the server listen. But every webhook handler would then find no installation and do nothing, so deliveries would never reach production logic. Any result would fail the "Production path" and "Not an artefact" criteria (15.4). Rule 6: skipped, reason logged.

**PO note:** this is a pilot. If the PO prefers a stubbed run (Level 1 only, octokit verification only), the recipe can be added before the skip is final. Rule 6 says skipped candidates are never revisited, so this needs a decision now.

Scenarios run: none. Findings: none.
