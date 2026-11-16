# CI/CD · Webhook Trap 2.0

**Status:** DRAFT, paper only · **Author:** A10 (DevOps) · **Date:** 2026-11-16 (rev. 16)
**Rev. 16 (2026-11-16):** PO decision of 2026-11-16 (SSR of the public pages): the `ssr` service in the policy (P-1, P-2, P-3 user `65534:65534`, P-7 network `ssr` internal, P-14 no secrets, P-15, P-16, new P-21), 15 more fixtures (49); `pnpm run build` now also writes the SSR bundle, which the image ships and the Pest suite renders with. No workflow change. Change table at the end.
**Rev. 15 (2026-11-16):** decision 72: the `pulsesend_api_key` secret (P-5, P-14 on `app` and `horizon`, P-20 `PULSESEND_API_KEY`). Change table at the end.
**Rev. 13 (2026-11-15, step C2):** the repo is on GitHub, private, default branch `v2` (decisions 65-66; `main` deleted, 1.x on `v1`). CI and container run on `v2` and are green. The Compose policy (`deploy/policy`), `deploy.sh` and `deploy-gate` (`deploy/bin`) exist and are tested in CI. GitHub artifact attestations wait for the repo to go public. §1.3 lists the GitHub settings and secrets (names only). Change table at the end.
**Rev. 12 (2026-11-15):** every app secret is a Compose file secret (CLAUDE.md rule), mounted only where read: P-5 file list, P-14 per service, P-17 without `_FILE` routing, new P-20 (no secret as a plain value in any resolved environment). Change table at the end.
**Rev. 11 (2026-11-15):** agent follow-ups (A06, commits `13f7443`, `0a088b4`): the `agent_pairing_hmac_key` secret in the policy (P-5, P-14), `APP_NUM_THREADS` = `LONGPOLL_HTTP_WORKERS` on `app` (P-17), `AGENT_ALLOW_DEV_BUILDS` refused (new P-18), no transaction-pooled database connection (new P-19). Change table at the end.
**Rev. 10 (2026-11-15):** applies A09's runtime and HAProxy review ("Runtime and HAProxy (rev 2)"): the Compose policy draft pins `haproxy-config`, the `haproxy_config` volume, per-service secrets and networks, `read_only` and the routing variables, with a `user` rule that fits `engine` and `backup` (S3-21, new P-12 to P-17); the HAProxy conformance checks gain the partial-body class, the answer shapes and the ingest recreate (S3-17 to S3-19); `haproxy -c` mounts the errorfile. Change table at the end.
**Rev. 9 (2026-11-15):** applies decision 61: `haproxy -c` on `docker/haproxy.cfg` in `ci.yml`, HAProxy checks in `conformance`, and the Compose policy draft (§4.2) extended to `ingest`, `haproxy` and `haproxy-config`, for A09's review. Change table at the end.
**Rev. 8 (2026-11-15):** applies A09's slice 3 review (`security/slice3-review.md` S3-3, S3-4, decision 2): `caddy validate` also covers `docker/Caddyfile.ingest` and both `INGEST_MODE` values (done in `ci.yml`), and the `conformance` job gains the ingest checks (multipart, slow body, pool isolation, hop trust, fail-open alert). Change table at the end.
**Rev. 7 (2026-11-14):** applies decision 50 and A08's live-props spike (`spikes/live-props/SPIKE-REPORT.md` C-7): the image build fails if `ext-uv`/`ext-event` is missing, a Reverb boot-check test, a client-events config test, and a long-poll cap test. Change table at the end.
**Rev. 6 (2026-11-14):** applies A09's verification (`security/threat-model.md` §12.11): DL-3c (`deploy.sh` refuses while `restore.pending` exists, step 1), TM-T158 (restore drill with the deletion ledger added to `selfhost-smoke`), TM-T145 canary for `/set-password/{token}`, and tests for the failed-sign-in alert and the argon2id concurrency cap. Change table at the end.
**Rev. 5 (2026-11-14):** applies A09's verification (`security/threat-model.md` §12.10): CI-1a (the extracted Compose file is installed only at step 6, after the pre-migrate backup and the migration; steps renumbered), and the SV-2a, RD-2 and RN-1a test hooks (TM-T141, TM-T145, TM-T148 extended in the `conformance` job). See the rev. 5 change table at the end.
**Rev. 4 (2026-11-14):** applies A09's verification (`security/threat-model.md` §12.9): CI-1 (`.env.deploy.previous` written only after checks pass; attestation command and test TM-T147), CI-2 (policy P-5 extended, P-10, P-11), SV-2 test hook (TM-T141 extended). See the rev. 4 change table at the end.
**Rev. 3 (2026-11-14):** applies A09's re-check (`security/threat-model.md` §12.5): CI-1 and CI-2 (on-box verification and Compose policy, one argument grammar), CI-3 and CI-4 (release credentials, workflow rules), RD-1 and SV-2 test hooks. See the change table at the end.
**Scope:** decision 43 (template workflows `ci.yml`, `container.yml`, `deploy.yml` adapted to the 2.0 repo), A09 `threat-model.md` §7.7 (agent supply chain), §7.8, TM-100, TM-101; `hosting-options.md` H-5, H-9; ADR-010 §2.
**Inputs read:** the template's three workflows and README (via `gh api`, 2026-11-14); Action release tags resolved to commit SHAs with read-only `gh api` the same day (§1.2).
**Repo layout assumed (A00/A05 to confirm):** Laravel + Inertia app at the repository root (the template expects this), Go engine/agent module under `agent/` (`go.mod` there), npm wrapper packages under `agent/npm/`, contracts under `contracts/`.
**Nothing here was created on GitHub.** No secret values; names only.

---

## 1. Principles

### 1.1 What changes vs the template

| Template | 2.0 |
| -------- | --- |
| Actions by major tag (`@v4`) | **Pinned to full commit SHAs** with the tag in a comment; Dependabot (`github-actions` ecosystem) proposes bumps, reviewed by a human |
| `appleboy/scp-action`, `appleboy/ssh-action` (no host key pinning) | Plain OpenSSH on the runner with `StrictHostKeyChecking=yes` and a `SSH_KNOWN_HOSTS` secret; one fewer third-party action with access to the deploy key |
| Deploy user in `docker` group, arbitrary script over SSH | Forced-command deploy user; the workflow sends the commit SHA and the two image digests in the one grammar of §4; the box re-verifies signatures and checks the Compose file (the private hosted deployment plan §3.3) |
| Compose file copied by `scp` | Compose file read from the signed image at `/app/deploy/compose.production.yaml`, so it is versioned and verified with the image, **and** checked on the box against a policy that does not come from the image (§4.2) |
| `docker login` with a token on every deploy, credential left on the VPS | Public GHCR package if the repo is public (no token on the box); else a read-only token in a root-only file used only by `deploy.sh`, and `docker logout` after the pull |
| Tests on `postgres:17-alpine` | Postgres 18 + Valkey 8 services, plus Go tests, contract tests, conformance test |
| No signing | Image signed (cosign keyless) and SLSA provenance attested; deploy by verified digest |
| No rollback step | Automatic container rollback to the previous digest when `/up` fails; manual `rollback` dispatch |
| Job permissions mostly default | `permissions: {}` at workflow level; each job grants only what it needs |

### 1.2 Pinned Actions (resolved 2026-11-14, re-audit at implementation)

| Action | Tag | Commit SHA |
| ------ | --- | ---------- |
| `actions/checkout` | v7.0.1 | `3d3c42e5aac5ba805825da76410c181273ba90b1` |
| `shivammathur/setup-php` | 2.40.0 | `eb7c497e18156a6bbabfef1d3a82760b9eda3962` |
| `pnpm/action-setup` | v6.1.1 | `73e46992e05b19abd31f2f3c20e0e7370d762115` |
| `actions/setup-node` | v7.1.0 | `949feb2413d6458794dcd2491c4babbbce0c15c1` |
| `actions/setup-go` | v7.0.0 | `b7ad1dad31e06c5925ef5d2fc7ad053ef454303e` |
| `golang/govulncheck-action` | v1.1.0 | `032d45514ae346b1db93c04b0c90b841c370344f` |
| `docker/setup-buildx-action` | v4.4.1 | `f87e5991a6d7451dcb8d9637bfbc97413f497069` |
| `docker/login-action` | v4.8.0 | `8aa5831fb8d48d8f76594c7ddde61ae58019f550` |
| `docker/build-push-action` | v7.4.0 | `c3c9e263c25d99ce0380d002d59b67737d91b0dc` |
| `docker/metadata-action` | v6.2.0 | `dc802804100637a589fabce1cb79ff13a1411302` |
| `sigstore/cosign-installer` | v4.1.3 | `756fe2d284c7292c4e9b62c8a28f1e5629c59532` |
| `actions/attest-build-provenance` | v4.2.2 | `4d101475d8b20a2381f78447822ac1eab6504dd8` |
| `goreleaser/goreleaser-action` | v7.3.0 | `a4f02ab5355a589de60d917a659ce617ed19b3de` |
| `actions/upload-artifact` | v7.0.2 | `cf430e030ddbb5b0abf93d22962f4752f3646cd9` |
| `actions/download-artifact` | v8.0.2 | `9000827ccba6bdab643e8b6fd33ac0654aef8333` |

These are the latest releases at the time of reading; their major versions are newer than the template's (`@v4`, `@v3`, `@v6`), so input names must be checked against each release's notes when the workflows are written. Tool versions inside Actions (GoReleaser, cosign, syft) are pinned too, by version **and** checksum where the Action supports it.

### 1.3 Repository settings (PO, GitHub write: **PO GO REQUIRED**)

**Rev. 13, what the PO sets on `jiordiviera/webhooktrap` (names only):**
- **Branch protection on `v2`** (the default branch): required checks `ci / php`, `ci / go`, `ci / infra`, `container / build`; no force push; no deletion.
- **Environment `production`:**
  - the PO as required reviewer;
  - deployment branches: `v2` only;
  - environment secrets `SSH_PRIVATE_KEY` (the `webhooktrap-deploy` key, forced command), `SSH_KNOWN_HOSTS` (the VPS host key line), `SERVER_HOST`, `SERVER_USER`.
- **Actions:**
  - workflow permissions read-only by default (each job asks for what it needs);
  - "Allow GitHub Actions to create and approve pull requests" off;
  - fork PR workflows from outside collaborators need approval (the repo is private, so this only matters at launch).
- **Packages:** `webhooktrap`, `webhooktrap-runner` and `webhooktrap-postgres` on GHCR are created private by the first push (done on 2026-11-15). Each is linked to the repo, with Actions access "write" for this repo only.
- **On the VPS (C3):** root's `docker login ghcr.io` with a classic token scoped `read:packages` only, because the images are private and both `docker pull` and `cosign verify` need it. Stored in `/root/.docker/config.json` (root `0600`), never in the repo.
- **Nothing else** is needed for CI: no repository secret is used by `ci.yml` or `container.yml` (`GITHUB_TOKEN` and OIDC only).

**Private repository limits (rev. 13):**
- **No artifact attestations:** GitHub refuses them for user-owned private repositories ("Feature not available"). `container.yml` runs `attest-build-provenance` only when the repo is public, and `deploy.yml` skips `gh attestation verify` while it is private.
- **What stands until then:** the BuildKit provenance (SLSA, `mode=max`) and the SBOM (SPDX) live in each image index and are covered by the cosign signature. `container.yml` checks all three after signing.
- **Public Rekor:** the keyless cosign signature is recorded in the public Rekor log, as planned. That publishes the repo name, the `v2` ref, the commit SHA and the image digests, never code. The repo was public until decision 66, so its name is already known. Signing without the log would weaken verification; the PO may object before C6.


Branch protection on `v2`, the default branch (rev. 13; `main` is deleted, 1.x lives on `v1`) (required checks: `ci/*`, `container/build`); `production` environment with the PO as required reviewer and deployment branch `v2` only; `release-agent` environment (tags `agent-v*` only) and `npm-publish` environment (PO as required reviewer), §1.4; tag protection for `v*` and `agent-v*`; Actions setting "require SHA pinning" if available on the plan; Dependabot for `github-actions`, `composer`, `npm`, `gomod`, `docker`; secret scanning and push protection on.

### 1.4 Workflow rules (CI-3, CI-4)

- **Triggers.** No `pull_request_target` and no `workflow_run` on workflows that check out or run code from a PR or a fork. `pull_request` only, with `contents: read` and no secrets. A lint (`actionlint` plus a grep for those two triggers) fails CI if they appear.
- **Caches.** No dependency or layer caches (`actions/cache`, `setup-*` `cache:`, buildx `cache-from`/`cache-to`) in the jobs that push images, sign, release or publish (`container.yml` push job, `agent-release.yml`). A cache written by an unreviewed job cannot reach a release job. `ci.yml` may cache, and nothing it caches is used by those jobs.
- **Credentials.** `actions/checkout` with `persist-credentials: false` in every workflow. `GITHUB_TOKEN` permissions stay `{}` at workflow level.
- **Dependabot.** Pull requests are never auto-merged (no auto-merge setting, no merge bot); a human reads each bump, Actions SHA bumps included.
- **Release credentials (CI-3).** `HOMEBREW_TAP_TOKEN` and the Apple keys are secrets of the GitHub environment **`release-agent`**, not repository secrets; that environment accepts only the protected tags `agent-v*` (deployment tag rule). npm publishing uses trusted publishing bound to the workflow file **and** to the GitHub environment **`npm-publish`**, whose required reviewer is the PO; the `npm-publish` job of `agent-release.yml` declares that environment. Nothing in `ci.yml`, `container.yml`, `deploy.yml` or `conformance-remote.yml` can read either environment.

---

## 2. `ci.yml` (from the template's `ci.yml`)

Triggers: `push` to `v2` (rev. 13), `pull_request`. `permissions: { contents: read }`. `concurrency: ci-${{ github.ref }}`, cancel in progress for PRs.

| Job | Steps | Notes |
| --- | ----- | ----- |
| `php` | checkout (`persist-credentials: false`) → setup-php 8.5 with `pdo_pgsql, redis, intl, bcmath, gd, zip, pcntl` → pnpm + node 22 (cache) → `composer install` → `composer audit` → `pnpm install --frozen-lockfile` → `pnpm audit --prod` (report, fail on high) → `cp .env.example .env && php artisan key:generate` → `pnpm run build` → static checks (Pint, Larastan; A05 picks) → `php artisan test` (Pest) | services: `postgres:18.x` (pinned digest) with healthcheck, `valkey/valkey:8.x` with password; env `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST`, `REDIS_PASSWORD`, `QUEUE_CONNECTION`, `CACHE_STORE` (test values are CI-only, defined in the workflow as the template does). The template's "detect Laravel application" step is dropped: the 2.0 repo is always an app |
| `go` | checkout → setup-go (version from `agent/go.mod`, `check-latest: false`) → `go vet ./...` → `go test -race ./...` → arch tests (D-1, D-4 in ADR-010) → govulncheck | working directory `agent/` |
| `realtime` (rev. 7, decision 50) | Pest config tests: `accept_client_events_from` is `none` in the loaded config and no env var changes it (TM-T160, proposed); the long-poll gate answers **503 with `Retry-After`** when all slots are taken and never queues (TM-T161, proposed); the slot values refused at boot when fewer than 2 workers would stay free; the per-principal cap and lifetime from A05's code. Then, against the built image with Compose: Reverb up, 1,100 raw websocket connections opened (above the old 1,014 limit) with `nofile` as in the YAML, the process stays up (a cheap smoke, not the Phase 5 measurement) | needs `php` toolchain and the image from `container.yml` step 3; the 1,100-socket step runs in `conformance` |
| `contracts` | Pest validates engine fixtures, Go tests validate server fixtures against `contracts/**` schemas (ADR-010 consumer-driven contract) | needs `php` and `go` toolchains |
| `conformance` | build the app image locally (no push) → `docker compose -f compose.production.yaml` with a CI `.env` + a host-proxy container built from the vhost template of the production variant (nginx or Caddy, F-1) on `127.0.0.1` → run the raw-socket corpus sender (H-6, the private hosted deployment plan §4.3) through FrankenPHP alone, then through the proxy → 25 MiB / 413 → websocket upgrade on `ws.` → **1,100 sockets held through the proxy container (rev. 7: the process must survive past the old 1,014 limit; the proxy template's `limit_conn` values are raised in the CI copy only, so that the Reverb limit is what is tested)** → `/up` | also `docker compose config --quiet` for all three Compose files, `caddy validate` on `docker/Caddyfile`, `nginx -t` on the vhost template. Added in rev. 3: Compose policy test (**TM-T146**: fixtures with a privileged service, a host bind mount, a Docker socket mount, a non-loopback port and an extra `cap_add` must each fail, the real file must pass; extended in rev. 4 with a named volume with `driver_opts` bind of `/`, a `configs` entry with `file` outside `$APP_DIR`, a `secrets` file outside `$APP_DIR/secrets`, a negative `oom_score_adj` and a service missing `mem_limit`); forged-header test (**TM-T141**: direct request with forged `X-Forwarded-*`, with a wrong and with the right `X-WT-Edge`; extended in rev. 4 with the underscore spellings, an empty `X-WT-Edge`, extended in rev. 5 (SV-2a) with every underscore spelling of `X-Forwarded-*`, `Forwarded` and `X-Real-IP` sent **with the right secret** through the real Caddy proxy (must show no second value in `HTTP_X_FORWARDED_FOR`), a missing, empty, short and root-`0600` secret file, and the `caddy adapt` order assertion of `compose-profile.md` §2.1); on-box verification test (**TM-T147**, below); token log test (**TM-T145**: canary `/s/…` request, also with the app stopped, then grep proxy and container logs; extended in rev. 5 (RD-2) with a canary in each emailed-link path and in `/index.php/s/…`, in rev. 6 with `/set-password/<canary>` and `/index.php/set-password/<canary>`); runner path test (**TM-T148**, extended in rev. 5 (RN-1a) with `/index.php/runner/v1/x` and `/index.php%2Frunner/v1/x`, from a non-runner address, against the vhost template and against the image alone); failed-sign-in alert test (rev. 6, the private hosted deployment plan §6.6: synthetic failures through the real sign-in route fire WARN and CRIT once each; the alert body, `/health` and the log line are grepped for the canary email and address: zero hits); argon2id cap test (rev. 6, `compose-profile.md` §3.1: with `PASSWORD_HASH_MAX_CONCURRENT=2` and the `app` limit of the 8 GB column, a burst of 50 concurrent wrong-password sign-ins from distinct addresses leaves the container's peak RSS under its limit and every over-cap request gets the same answer for known and unknown emails); engine isolation (`network_mode: none`, socket call works, no outbound connection possible) |
| `ingest` checks (rev. 8, part of `conformance`; IDs to be assigned by A09) | against the built image through the Compose stack: (1) multipart and urlencoded captures are stored byte for byte with their SHA-256 (A06's Pest test, run here against `ingest`); (2) a sender that drips 1 byte every 2 s is cut at 15 s and the ingest thread is free again; (3) with every ingest thread held by slow senders, `GET /up` on the app host answers within its normal time; (4) through the hop, forged `X-WT-Verified`/`X-Forwarded-*` (all spellings) and a wrong `X-WT-Edge` give the peer address, the right secret gives the host proxy's; a container on `internal` calling `ingest:8080` directly is untrusted; captures carry no `Via` the client did not send; (5) a capture during `docker compose restart ingest` answers `200`; (6) with Valkey stopped, captures still answer `200` and `wt.store_failure` appears once per kind per minute (the private hosted deployment plan §6.8) | the same checks were run by hand on the pinned FrankenPHP base on 2026-11-15 (`compose-profile.md` §2.3); the CI version uses the real app |
| `haproxy` checks (rev. 9, part of `conformance`; IDs to be assigned by A09) | (1) 21 slow captures from one verified address: the 21st gets the `429`, header for header the app's own (`Date` aside), and another address gets `200`; (2) more than 1200 captures a minute from one address with Valkey stopped get the same `429`; (3) IPv6: two addresses in one /64 share the bucket; (4) a forged `X-Forwarded-For` without the host proxy's proof, or sent straight to `haproxy:8080` from another container, does not change the key; (5) small-body slow senders, more of them than ingest has threads, do not delay a normal capture; (6) a declared body over 25 MiB gets the JSON `413` at once; (7) the stored capture carries no header HAProxy or the hop added (no `Via`, `X-Forwarded-Host`, `Accept-Encoding`); (8) `docker compose up` twice: `haproxy-config` reruns and `haproxy` serves the config of the current app image; **rev. 10:** (9) 3 partial senders (body over 32 KiB then dripped, or chunked) from one address: the 3rd gets the `429`; (10) partial senders from 8 addresses: 6 are admitted, the others get the `429`, and a complete-body capture from a fresh address still answers within its normal time; (11) a large body sent at full speed while the partial slots are free gets `200`; (12) every answer HAProxy produces (`400`, the timeout answer, `413`, `429`, `503`) compared header by header with ingest's (the timeout answer must equal ingest's `200 {"received":true}`); (13) captures sent across `docker compose up --force-recreate ingest` all get `200`; (14) the edge secret file with an extra line, two final newlines or capitals: `app` and `ingest` refuse to start; `reverb`, `horizon`, `scheduler`, `migrate` start without it | (1)-(13) run by hand on 2026-11-15 (`compose-profile.md` §2.4, rev. 10 against the image built from this repository); (14) on the entrypoint alone |
| `selfhost-smoke` | `docker compose -f compose.yaml up -d` with `TLS_MODE=off` → wait healthy → migrate ran → `/up` → `backup` runs one full to the local repo → restore into a scratch volume → row-count check → **TM-T158 (rev. 6, DL-3):** create a user, back up, delete the user through the real flow (ledger line written before the row purge), restore to the point before the deletion, assert the app image refuses to start while `restore.pending` exists, assert the user is back, run `ledger-fetch`, `accounts:apply-ledger` and `restore-finish`, assert the user and its sessions, tokens and agents are gone, then start the stack; also assert a shipped ledger object exists and the weekly `verify` finds it consistent | only on `main` and nightly (slow) |

`workflow_dispatch` input `target_url` on a separate `conformance-remote.yml` lets the PO run the same corpus against the real `in.<domain>` after a host-proxy change (plan R-2); it uses a test capture key stored as the `CONFORMANCE_CAPTURE_URL` secret.

---

## 3. `container.yml` (from the template's `container.yml`)

Triggers: `push` to `v2` (build + push; rev. 13), `pull_request` (build only, no push, no secrets). Job permissions: `contents: read`, `packages: write`, `id-token: write` (cosign keyless, attestations), `attestations: write`.

Steps:
1. checkout (`persist-credentials: false`); setup-buildx. No caches in this job (§1.4, CI-4): no `cache-from`/`cache-to`, no `actions/cache`.
2. Build the **engine binary** first in a Dockerfile stage (`golang:<pinned>` → `CGO_ENABLED=0 go build -trimpath -ldflags "-s -w -X …version=$GITHUB_SHA"`), copied into the runtime stage at `/usr/local/bin/webhooktrap` (H-5). The same stage feeds the runner image.
3. `build-push-action` for three targets: `runtime` (app) → `ghcr.io/<owner>/<repo>`, `runner` → `ghcr.io/<owner>/<repo>-runner`, `docker/postgres` → `ghcr.io/<owner>/<repo>-postgres`. Tags: `${{ github.sha }}` (template) and `main` (convenience; the template's `latest` is renamed so nobody deploys it by habit). `provenance: mode=max`, `sbom: true`. Base images pinned by digest in the Dockerfiles (Dependabot `docker` bumps them).
4. On push: `cosign sign --yes <image>@<digest>` for each image (keyless, identity = this workflow on `v2`, rev. 13); `attest-build-provenance` with `push-to-registry: true`.
4a. **Realtime extension gate (rev. 7, decision 50; test ID to be assigned by A09, proposed TM-T159).** Before the push, on the locally built `runtime` image: `docker run --rm --entrypoint php <image> -r 'exit(extension_loaded("uv")||extension_loaded("event")?0:1);'` must exit 0, or the job fails and nothing is pushed. A second step proves the boot check itself: `docker run --rm --entrypoint reverb-boot` with `-d extension=` overridden so neither extension loads (or a stub PHP ini that disables it) must exit non-zero with the log line naming the extension; with `--ulimit nofile=1024:1024` it must exit non-zero naming `nofile`; with both fine it must reach `reverb:start`. Runs on pull requests too (build only).
5. Output the digests as a job summary and as an artifact `digests.json` (used by `deploy`).

On PRs from forks no secret or `packages: write` is available (template already pushes only on `push`).

---

## 4. `deploy.yml` (from the template's `deploy.yml`)

Trigger: `workflow_dispatch` with inputs `sha` (default: the dispatched commit) and `action` (`deploy` | `rollback`). `environment: production` (PO approval = **PO GO REQUIRED** for every run). `concurrency: { group: deploy-production, cancel-in-progress: false }`. Permissions: `contents: read`, `packages: read`, `id-token: write` only if verification uses OIDC (not needed for public Sigstore verification).

**GitHub secrets (names):** `SSH_PRIVATE_KEY` (the forced-command key), `SSH_KNOWN_HOSTS`, `SERVER_HOST`, `SERVER_USER`. `GHCR_USERNAME`/`GHCR_TOKEN` from the template only if the package is private (then they live on the VPS, not in GitHub, see §1.1). **Vars:** `APP_DIR` (template), `APP_PORT` (template).

**Argument grammar (one definition; the private hosted deployment plan §3.3 refers here).** The forced command accepts exactly one of:

```
deploy <commit-sha> <app-digest> <pg-digest>     # 40 lowercase hex; sha256:<64 hex>; sha256:<64 hex>
rollback
```

Nothing else: no image names, tags, paths or flags. The image repositories are constants inside `deploy.sh`, so a stolen deploy key cannot name another image.

**Workflow steps (GitHub runner):**
1. Check the `container` workflow succeeded for `sha` (`gh run list --commit`, read-only), else fail (template README rule, now enforced).
2. Resolve `ghcr.io/<owner>/<repo>:<sha>` (and `-postgres`) to digests from `digests.json`; **`cosign verify`** each with `--certificate-identity` = `container.yml` on `refs/heads/v2` (rev. 13) and the GitHub OIDC issuer; `gh attestation verify` for provenance. Fail closed. This is a **fast-fail only**: the box does not rely on what the runner says (TM-147).
3. `ssh -o StrictHostKeyChecking=yes -o UserKnownHostsFile=<from secret> $SERVER_USER@$SERVER_HOST "deploy <sha> <app-digest> <pg-digest>"` (or `"rollback"`). `deploy-gate` ignores anything else.

**`<APP_DIR>/bin/deploy.sh` (on the VPS, root-owned, run through the sudoers line; it runs as root, so it verifies for itself, CI-1, CI-2):**

**Rev. 13:** implemented in `deploy/bin/deploy.sh`, with the forced command in `deploy/bin/deploy-gate`. Both are installed from the repo at C3 and never read from the image.
- **Gate:** it accepts the two forms only, on one line. A trailing second line was refused in a test.
- **Constants:** `OWNER_REPO=jiordiviera/webhooktrap`, the identity of `container.yml` on `refs/heads/v2`. `APP_DIR` and `APP_PORT` come from `/etc/webhooktrap/deploy.conf` (root `0644`, written at install from the private plan), so the script in the repo holds no host-specific value.
- **Restore guard:** `restore.pending` is read with the pinned Valkey image (`test -e`), so no 2.0 code runs and no new image is pulled.
- **Migration:** it runs with `--no-deps` after starting only `postgres` and `valkey`.
- **Health:** every service must report healthy. The one-shot `haproxy-config` is not listed by `ps` once it has exited.
- **Not yet proven:** the cosign line is fixed by TM-T147 at C6, on the box with the pinned binary.

Constants in the script (installed at C3, changed only by a PO-approved reinstall): `APP_REPO=ghcr.io/<owner>/<repo>`, `PG_REPO=ghcr.io/<owner>/<repo>-postgres`, `CERT_ID=https://github.com/<owner>/<repo>/.github/workflows/container.yml@refs/heads/v2`, `OIDC_ISSUER=https://token.actions.githubusercontent.com`, the path of the pinned `cosign` binary and of `trusted_root.json` (§4.3).

1. Validate the arguments against the grammar above; refuse otherwise. `flock` against concurrent runs. Re-exec under `env -i` with a fixed `PATH`; refuse if a `.env` file exists in `$APP_DIR` (Compose would read it); never read `COMPOSE_*` or `DOCKER_*` from the caller. `cd "$APP_DIR"`; require `.env.production` and `secrets/`. Refuse while `$APP_DIR`'s `backup_status` volume holds `restore.pending` (a restore is half done: the deletion ledger has not been re-applied, DL-3c; `docker run --rm -v webhooktrap_backup_status:/s:ro <pinned busybox digest> test -e /s/restore.pending`, read-only, no 2.0 code run). **Nothing is copied or written** (CI-1).
2. **Verify on the box, then pull.** For each of `APP_REPO@<app-digest>` and `PG_REPO@<pg-digest>`: `cosign verify --certificate-identity "$CERT_ID" --certificate-oidc-issuer "$OIDC_ISSUER" --certificate-github-workflow-sha <sha> --trusted-root <pinned root> <ref>`, then the provenance check with the same identity flags and the SLSA provenance v1 predicate type. **The exact command is not assumed:** `actions/attest-build-provenance` pushes a Sigstore bundle as an OCI referrer of the image, and older `cosign` releases do not discover referrers. The pinned `cosign` version and the verify-attestation (or bundle-based) command line are therefore fixed by test TM-T147 on a real image built by `container.yml`, and `deploy.sh` runs exactly the line the test proved; no manual bypass is documented or provided. The `--certificate-github-workflow-sha` flag binds the digest to the commit named in the argument. Any failure: exit, nothing pulled, nothing changed. Only after both pass, pull by the verified references (registry login from the root-only file if private; `docker logout` after). (`rollback`: the same checks on the digests and SHA recorded in `.env.deploy.previous`.) Needs egress to the registry and to Sigstore's transparency log; offline verification from the signature bundle is an option once the bundles carry inclusion proofs (decide at implementation).
3. **Extract and check the Compose file.** `docker create --pull=never` on the app reference (no code runs), `docker cp <id>:/app/deploy/compose.production.yaml` to a candidate path (regular file, ≤ 64 KiB, in a root-only directory under `$APP_DIR`), `docker rm`. Write the candidate `.env.deploy` beside it (not at its final path). Run the policy (§4.2) on `docker compose --project-directory "$APP_DIR" --env-file .env.production --env-file <candidate .env.deploy> -f <candidate> config --format json` (rev. 14: both files, in this order, in every Compose call). Fail: exit, nothing changed. Pass: log the candidate's SHA-256. **Rev. 5 (CI-1a): neither the candidate Compose file nor the candidate env file is installed yet;** the installed `$APP_DIR/compose.production.yaml`, `.env.deploy` and `.env.deploy.previous` stay byte-identical until step 6.
4. **Pre-migrate backup**, run with the candidate files (`-f <candidate> --env-file <candidate .env.deploy>`): `docker compose … run --rm backup backup-now --type=incr` (skipped only when the database is empty, i.e. first deploy; skipped for `rollback`). Fail = stop, nothing changed.
5. **Migrate** (template), same candidate files: `docker compose … run --rm app php artisan migrate --force --no-interaction` (skipped for `rollback`). Fail = stop; the installed files are still the previous deployment's, so nothing needs restoring (the migration itself is not reverted, see step 9).
6. **Only now, after steps 2, 3, 4 and 5 passed (CI-1, CI-1a):** copy `.env.deploy` to `.env.deploy.previous` and the installed `compose.production.yaml` to `compose.production.yaml.previous` (template's "save the previous tag", automated; skipped on the first deploy, when neither exists; for `rollback` the two pairs are swapped instead), then write the new `.env.deploy` (`APP_IMAGE`, `APP_DIGEST`, `IMAGE_TAG`, `PG_IMAGE`, `PG_DIGEST`, `APP_PORT`) **and install the checked Compose file** as `$APP_DIR/compose.production.yaml` (root, `0644`) from the SHA-256 logged at step 3. Each write is atomic (temp file in the same directory, `mv`); the Compose file is installed before the env file, so the pair is never new-env with old-file for longer than one `mv`. A refused or failed deploy (any exit before this step, including backup and migrate) leaves `.env.deploy`, `.env.deploy.previous`, `compose.production.yaml` and its `.previous` untouched; test TM-T147 checks that too.
7. `docker compose … up -d --remove-orphans` with the installed files (project name pinned, so orphans can only be ours).
8. **Health:** up to 30 attempts × 2 s: `curl -fsS http://127.0.0.1:${APP_PORT}/up` and `docker compose ps --format json` shows every service `healthy`.
9. On failure: restore `.env.deploy.previous` and the previous Compose file (`compose.production.yaml.previous`), `up -d` again, re-check `/up`, print `docker compose ps`, exit 1 (**automatic container rollback**). The migration is not reverted: migrations must be backward-compatible (expand/contract), as the template README requires.
10. On success: remove `ghcr.io/<owner>/<repo>*` images older than the previous two (by repository name only, never `prune -a`); log the deployed SHA, digests and Compose hash to `$APP_DIR/deploy.log`.

### 4.1 What the box trusts

| Input | Trusted? | Check |
| ----- | -------- | ----- |
| Arguments from SSH | no | strict grammar, constants for repositories |
| Digests and SHA | no | cosign signature and provenance with the pinned identity, SHA bound to the certificate |
| Compose file from the image | no | policy in §4.2, which lives on the box |
| `.env.production`, `secrets/` | yes | root-owned, written by the PO |
| The policy, `cosign`, `trusted_root.json`, `deploy.sh` | yes | root-owned under `$APP_DIR/bin` and `$APP_DIR`, changed only by PO go |

### 4.2 Compose policy (CI-2, TM-146)

**Rev. 13:** implemented in `deploy/policy/compose-policy.py`. CI (`infra` job) runs it on the real hosted file, and runs 49 breaking fixtures (rev. 16: 15 for `ssr`) (`deploy/policy/test_compose_policy.py`, TM-T146); each fixture must fail with its rule. `config` omits a default `oom_score_adj: 0`, so the policy reads an absent value as 0. Rev. 14 of `compose-profile.md`: `postgres` holds only `postgres_password`, and the app family reads `db_password`.

Implemented in one root-owned script (Python 3 standard library, run with `-I`, which Ubuntu 24.04 ships; if it is absent, installing it is a PO decision) that reads the resolved JSON from `docker compose config`. It is versioned in the repo (`deploy/policy/compose-policy.py`), **installed on the box at C3 and never read from the image**; changing it is a PO-approved host change. Tests TM-T146 run it in CI against fixtures.

| # | Rule |
| - | ---- |
| P-1 | Project `name` is `webhooktrap`; service names are exactly: `app`, `ingest`, `haproxy`, `haproxy-config`, `reverb`, `horizon`, `scheduler`, `engine`, `valkey`, `postgres`, `backup` (rev. 9 adds the first three after `app`, for A09's review); no `build`. |
| P-2 | Images: the seven app-image services (`app`, `ingest`, `haproxy-config`, `reverb`, `horizon`, `scheduler`, `engine`) use `APP_REPO@<app digest from the arguments>`; `postgres` and `backup` use `PG_REPO@<pg digest>`; `valkey` uses `valkey/valkey` and `haproxy` uses `haproxy` (official, rev. 9); both must carry an `@sha256:` digest. Rev. 9: `haproxy`'s `webhooktrap.config-from` label equals the app digest. |
| P-3 | Absent keys: `privileged`, `devices`, `device_cgroup_rules`, `pid`, `ipc`, `uts`, `userns_mode`, `cgroup`, `runtime`, `gpus`, `volumes_from`, `sysctls`; `network_mode` is only allowed as `none`, and only on `engine` and `haproxy-config` (rev. 9); rev. 10 (S3-21): `user` is absent except on `engine` (`10001:10001`), `backup` (`postgres`) and `haproxy-config` (`0:0`), and `group_add` only as `10001` on `app` and `33` on `haproxy`; `security_opt` is only `no-new-privileges:true`. |
| P-4 | Every service has `cap_drop: [ALL]`. `cap_add` only on `valkey` (SETUID, SETGID) and `postgres` (CHOWN, DAC_OVERRIDE, FOWNER, SETUID, SETGID); nothing else. |
| P-5 | Volumes are named volumes of this project (none `external`), or bind mounts whose resolved source is under `$APP_DIR` (no `..`, no symlink out). Never the Docker socket, `/`, `/proc`, `/sys`, `/dev`, `/etc`, `/var/lib/docker`, or the directory that holds other apps (anything outside `$APP_DIR`). `secrets` sources are exactly the known files under `$APP_DIR/secrets/` (rev. 12, 13: `valkey_password`, `postgres_password`, `db_password`, `backup_cipher_pass`, `backup_s3_key`, `backup_s3_secret`, `edge_proxy_secret`, `agent_pairing_hmac_key`, `app_key`, `reverb_app_secret`, `mail_password`, `github_client_secret`, `google_client_secret`, `error_tracker_dsn`, `identity_email_hmac_key`, `identity_recovery_code_key`; rev. 15: `pulsesend_api_key`). **Top-level `volumes` entries (CI-2):** none may have `driver` (other than absent or `local` without options) or any `driver_opts` (a `local` volume with `type: none`, `o: bind`, `device: /` is a host bind that looks like a named volume); `external` stays refused. **Top-level `configs`:** the key is absent (the file defines none); if one is ever allowed it follows the same rule as `secrets`: `file` only under `$APP_DIR/secrets/`, `content`/`environment` sources only by explicit policy change. Any service `configs:` reference or `secrets:` entry with a host source outside that directory fails. |
| P-6 | Published ports (rev. 9: `ingest`, `haproxy` and `haproxy-config` publish nothing): only on `app` (and `reverb` if the PO chose the fallback); each has `host_ip: 127.0.0.1`; `app` publishes `${APP_PORT}` to 8080 and nothing else. |
| P-7 | Networks are project-scoped: none `external`, none with driver `host`, `macvlan` or `ipvlan`; `internal` is `internal: true`. |
| P-8 | Every service (rev. 9: including the one-shot `haproxy-config`) has `cgroup_parent: webhooktrap.slice` (when F-3 shows the systemd driver; the cgroupfs case is a policy variant decided at C3), and `mem_limit` and `pids_limit`. |
| P-9 | `env_file` entries are only `$APP_DIR/.env.production`. |
| P-10 | `oom_score_adj` is present and ≥ 0 on every service (a negative value lets 2.0 outlive the neighbours under memory pressure). |
| P-12 | **`haproxy-config` (rev. 10, S3-21).** `entrypoint` is exactly `["cp", "/etc/webhooktrap/haproxy.cfg", "/etc/webhooktrap/received-200.http", "/wt-haproxy/"]`; `volumes` exactly `[haproxy_config:/wt-haproxy]`; no `secrets`, `env_file`, `environment` or `command`; `read_only: true`; `network_mode: none`; `restart: "no"`. |
| P-13 | **Volume `haproxy_config` (rev. 10).** Mounted read-write only on `haproxy-config` and read-only only on `haproxy` (both at `/wt-haproxy`), nowhere else. `haproxy` mounts no other volume. |
| P-14 | **Secrets per service (rev. 10, 12; S3-21, S3-22).** Exactly: `app`: `app_key`, `db_password`, `valkey_password`, `reverb_app_secret`, `github_client_secret`, `google_client_secret`, `error_tracker_dsn`, `identity_email_hmac_key`, `identity_recovery_code_key`, `agent_pairing_hmac_key`, `edge_proxy_secret`, `pulsesend_api_key` (rev. 15); `ingest`: `app_key`, `db_password`, `valkey_password`, `error_tracker_dsn`, `edge_proxy_secret` (never the identity keys); `reverb`: `app_key`, `valkey_password`, `reverb_app_secret`, `error_tracker_dsn`; `horizon`: `app_key`, `db_password`, `valkey_password`, `reverb_app_secret`, `mail_password`, `pulsesend_api_key` (rev. 15), `error_tracker_dsn`; `scheduler`: `app_key`, `db_password`, `valkey_password`, `error_tracker_dsn`; `haproxy`: `edge_proxy_secret` only; `valkey`: `valkey_password`; `postgres`: `postgres_password` only (rev. 13: it queues WAL locally, `backup` pushes it); `backup`: `backup_cipher_pass`, `backup_s3_key`, `backup_s3_secret`; `engine`, `haproxy-config`: none. |
| P-15 | **Networks per service (rev. 10).** `ingest` and `haproxy` exactly `[internal]`; `app` `[edge, internal, egress]`; `reverb` `[edge, internal]`; `horizon` `[internal, egress]`; `scheduler`, `valkey`, `postgres` `[internal]`; `backup` `[internal, backup_egress]`; `engine` and `haproxy-config` `network_mode: none`. |
| P-16 | **`read_only: true` (rev. 10)** on `app`, `ingest`, `haproxy`, `haproxy-config`, `reverb`, `horizon`, `scheduler`, `engine`. `haproxy`: no `env_file`; `environment` only `WT_CAPTURE_MAX_CONN`, `WT_CAPTURE_MAX_RATE`, `WT_CAPTURE_MAX_STREAM`, `WT_CAPTURE_STREAM_SLOTS`, each a whole number (A09's list named the first two; the partial-body class of S3-17 adds the other two). |
| P-17 | **Routing (rev. 10, S3-21).** `ingest`: `PROXY_TRUST_MODE=secret`, `WT_ROLE=ingest` set explicitly in its `environment` (rev. 12: the app's identity serve check skips that role, 9224c67, and the entrypoint does not export a role it infers, so a missing value would make ingest refuse to serve), `command` exactly the `Caddyfile.ingest` line; `app`: `PROXY_TRUST_MODE=secret`, `WT_ROLE=app`, `INGEST_MODE=proxy`, `INGEST_UPSTREAM=haproxy:8080`, and `LONGPOLL_HTTP_WORKERS` equal to `APP_NUM_THREADS`, a whole number of at least 4 (rev. 11); `haproxy`: `command` exactly `["haproxy", "-f", "/wt-haproxy/haproxy.cfg"]`. So the gate cannot be bypassed or the trust mode lowered without the policy failing. Every rule from P-12 on has a TM-T146 fixture that breaks it. |
| P-18 | **No dev agent builds (rev. 11).** `AGENT_ALLOW_DEV_BUILDS` is absent from every service's resolved `environment` (which inlines `.env.production`), or `0`/`false`; and `APP_ENV` is `production` on every app-image service. The entrypoint refuses the variable outside `APP_ENV` `local`/`testing` as well, which also covers production self-host. |
| P-19 | **Session-pooled database only (rev. 11).** The long-poll slots are PostgreSQL session advisory locks (`config/agent.php`), held for the life of the connection; a transaction pooler would hand the lock's session to another client or lose it. So: `DB_HOST` on every app-image service is `postgres` (the Compose service, no pooler). If a pooler is ever added (PgBouncer, PgCat, Odyssey, Supavisor, by image name), that is a policy change, and the rule then requires its pool mode in session mode (`PGBOUNCER_POOL_MODE`/`POOL_MODE`/`pool_mode` = `session` in its resolved environment); `transaction` or `statement` fails. |
| P-20 | **No secret as a plain value (rev. 12, CLAUDE.md).** In every service's resolved `environment` (which inlines `.env.production`), none of `APP_KEY`, `APP_PREVIOUS_KEYS`, `DB_PASSWORD`, `REDIS_PASSWORD`, `REVERB_APP_SECRET`, `MAIL_PASSWORD`, `PULSESEND_API_KEY` (rev. 15), `GITHUB_CLIENT_SECRET`, `GOOGLE_CLIENT_SECRET`, `ERROR_TRACKER_DSN`, `IDENTITY_EMAIL_HMAC_KEY`, `IDENTITY_RECOVERY_CODE_KEY`, `AGENT_PAIRING_HMAC_KEY`, `POSTGRES_PASSWORD` is set; their `_FILE` forms may only point under `/run/secrets/`. The entrypoint reads each from its Compose secret (`/run/secrets/<lower-case name>`; `DB_PASSWORD` from `postgres_password`, `REDIS_PASSWORD` from `valkey_password`). |
| P-21 | **`ssr` (rev. 16, PO 2026-11-16, compose-profile §2.5).** An app-image service (P-2) whose `entrypoint` is exactly `["node", "/app/bootstrap/ssr/ssr.js"]`, no `command`; no `env_file`, `secrets`, `volumes`, `tmpfs`, `group_add` or `cap_add`; `environment` only `NODE_ENV`, `NODE_OPTIONS`; `user: 65534:65534` (P-3); `read_only` (P-16); networks exactly `[ssr]`, a network with `internal: true` (P-7, P-15) that `app` also joins (`app` becomes `[edge, internal, egress, ssr]`); no published port (P-6); a healthcheck; `mem_limit` at most 256 MiB. `app` has `INERTIA_SSR_URL=http://ssr:13714`. |
| P-11 | The policy never prints the resolved configuration (it inlines `.env.production`): on failure it prints rule id and service name only; the JSON stays in a root-only temp file that is deleted on exit. |

A rule that the real file legitimately needs to break is a policy change in the same PR, reviewed by A09, then installed by the PO.

### 4.2a Test TM-T147: the on-box verification command (CI-1)

Run in the `conformance` job and again at C6 on the box before the first deploy, against real images that `container.yml` pushed for a throwaway commit (signature and provenance included), with the pinned `cosign` and `trusted_root.json` that `deploy.sh` will use. It runs the very commands of §4 step 2 (not a copy). Expectations:

| Case | Expected |
| ---- | -------- |
| Correct digest, signature and provenance, correct SHA | passes; the command line used is printed once and is the one written into `deploy.sh` |
| Digest never signed (push an unsigned image) | refused |
| Signed by another workflow (different `--certificate-identity`) or another issuer | refused |
| Right workflow, other commit (`--certificate-github-workflow-sha` differs from the argument) | refused |
| Signed but no provenance attestation attached (or attestation of another digest) | refused |
| Attestation present only as an OCI referrer / Sigstore bundle (what `attest-build-provenance` pushes) | found and verified by the pinned version; if not found, the test fails and the pin or command changes, never the check |
| Any refusal above | nothing pulled, `.env.deploy`, `.env.deploy.previous` and `compose.production.yaml` byte-identical before and after (hash compared), a later `rollback` still targets the older deployment |

### 4.3 Files on the box that deploy depends on

**Rev. 14, `deploy/bin/c3-finish.sh` (C3, run once as root on the box; idempotent; prints no secret).** It completes the layout the PO prepared (`/etc/webhooktrap/deploy.conf`, `bin/deploy.sh`, `bin/deploy-gate`, `bin/compose-policy.py`, `secrets/`). Run it from a checkout of the reviewed commit.

**0. `bin/` against the checkout.** `deploy.sh`, `deploy-gate` and `compose-policy.py` are compared byte for byte with the checkout. A difference is reported; `--install-bin` replaces the file (root, 0755, 0755, 0644). Copies installed before rev. 14 differ (`deploy.sh` rollback and env files, below).

**1. cosign and the trusted root.**

| File | Source | How it is checked |
| ---- | ------ | ----------------- |
| `bin/cosign` | `https://github.com/sigstore/cosign/releases/download/v3.1.4/cosign-linux-<arch>` | SHA-256 pinned in the script, not fetched beside the binary: amd64 `424e2c97…f4bbfa`, arm64 `ade83b7a…dedad2`. The values come from the release's `cosign_checksums.txt` |
| `bin/trusted_root.json` | `sigstore/root-signing` at commit `c9bda74`, the last change to `targets/trusted_root.json` (2025-09-22) | SHA-256 pinned: `6494e21e…6b0c66` |

- **Why v3.1.4:** it is the version CI signs with (the pinned `cosign-installer` default).
- **Proof in CI:** `container.yml` runs `deploy.sh`'s exact `cosign verify --trusted-root …` line against the images it has just signed, with the same version and the same pinned root. This is TM-T147's line, proven before the box uses it.
- **When Sigstore rotates its root:** the pin changes in the script and in `container.yml` together. CI fails first if the new root is needed.

**2. The slice.** `webhooktrap.slice`: `MemoryHigh=5632M`, `MemoryMax=6G`, `MemorySwapMax=0`, `CPUQuota=300%`, `TasksMax=4096`, `IOWeight=50`, then `daemon-reload`.

**3. `.env.production`.**
- **Source:** written from `deploy/hosted/env.production.template` (settings only).
- **Generated on first write:** the Reverb app id and key (public identifiers).
- **Re-runs:** an existing file is kept, and the run reports names missing from it.
- **P-20:** the script refuses if the template or the file sets any secret name.
- **Values the PO confirms before C6:**
  - `GUEST_MODE`;
  - `WEBAUTHN_RP_ID` (`app.webhooktrap.dev`, or the apex; it can never change once users have passkeys);
  - `MAIL_MAILER` and `MAIL_FROM_ADDRESS`;
  - the OAuth client ids;
  - `BACKUP_S3_ENDPOINT` and `BACKUP_S3_BUCKET` (R2);
  - `LEDGER_AGE_RECIPIENT`.
- **Blocker before the second deploy:** with `BACKUP_REPO_TYPE=s3` and no endpoint, bucket or key files, `backup` fails, and so does step 4 (the pre-migrate backup) of every deploy after the first.
- **CI:** the `infra` job resolves `compose.production.yaml` with this template and runs the policy on it.

**4. GHCR access.**
- **Recommended:** make the three packages (`webhooktrap`, `webhooktrap-postgres`, `webhooktrap-runner`) public. The repo is public, the images contain only public code and no secret, the runner image is pulled by users anyway, and the box then needs no registry token to store or rotate.
- **Alternative:** a token scoped `read:packages` only, passed as a file with `--ghcr-user` and `--ghcr-token-file`, used for root's `docker login ghcr.io`.
- **Check:** the script tests anonymous access first and reports the mode it found.

**5. Summary.** It prints:
- each `bin/` file's owner, mode and hash, to compare with `sha256sum deploy/bin/* deploy/policy/compose-policy.py` from the deployed commit;
- the cosign version;
- the slice values;
- for each secret: owner and mode against the expected ones, and whether it is set or empty;
- whether `db_password` and `postgres_password` hold the same value;
- the edge secret's shape;
- for `webhooktrap-deploy`: sudoers parses, its only sudo rule is `NOPASSWD` on `bin/deploy.sh`, the password is locked, it is not in `docker`, every key is forced to `deploy-gate` with `restrict`.

**Rev. 14, `deploy.sh` rollback.** It now re-verifies the previous digests (signature, with the commit from `IMAGE_TAG`) and re-runs the policy on the previous Compose file before swapping, as this section says. Copies installed before rev. 14 must be replaced.

**Rev. 14, `deploy.sh` env files.** Every `docker compose` call passes `--env-file .env.production` and then `--env-file <.env.deploy>` (the later wins). `postgres`, `valkey` and `backup` have no `env_file` and list bare names (`environment: [POSTGRES_USER, …]`), which Compose resolves only from these files: with `.env.deploy` alone they were unset, and Postgres would not have initialised nor Valkey started. The policy (step 3) runs on the same resolution.


`$APP_DIR/bin/deploy.sh`, `bin/deploy-gate`, `bin/compose-policy.py`, `bin/cosign` (pinned version, SHA-256 recorded; fetched and checked by the PO at C3, updated the same way), `trusted_root.json` (Sigstore trust root, refreshed by the PO quarterly or when verification fails for a reason other than the signature). All root-owned, none writable by `webhooktrap-deploy`.

**Manual rollback:** dispatch with `action: rollback` → `deploy.sh rollback` swaps `.env.deploy` and `.env.deploy.previous`, **re-verifies the previous digests and re-checks the previous Compose file** (steps 2 and 3), swaps the Compose files with the env files at step 6, and runs steps 7–9 without a backup or migration. Any older SHA can also be redeployed with `action: deploy` and that SHA (its migrations are already applied; Laravel skips them). A schema rollback, if ever needed, is a separate PO-approved procedure with a restore point from step 7.

---

## 5. Agent release (`agent-release.yml`)

Built 2026-11-16; the runbook is `agent-release.md`, which supersedes the plan that stood here.
Differences from that plan: the tag is `agent-v*` (not `agent/v*`); the workflow file is
`agent-release.yml`; plain `go build` through `agent/release/build.sh` instead of GoReleaser (no
new tool; archives are byte-for-byte reproducible, checked locally); the cosign bundle
`SHA256SUMS.sigstore.json` plus `.sig`/`.pem` taken from it; the GitHub release stays a draft
until npm has published, then `finalize` publishes it and runs `install.sh` against it. Not built
yet: SBOM per archive, the second-build reproducibility job, the Homebrew tap (`publish` job,
`HOMEBREW_TAP_TOKEN`) and §6 signing. npm trusted publishing is bound to `agent-release.yml` and
the environment `npm-publish` (PO as required reviewer, tags `agent-v*` only). `ci.yml` job
`agent-dist` runs the build and npm packing as a dry run on every push and pull request.

**PO account actions (HD-3, PO GO REQUIRED):** `agent-release.md` §2 (npm org with 2FA,
typosquat placeholders, placeholder publish of the seven packages, trusted publishers, the
`npm-publish` environment and the `agent-v*` tag ruleset); create the Homebrew tap repository
when item 4 is built.

---

## 6. Signing for macOS and Windows (A09 §7.7 item 5)

| Option | What it needs | Cost |
| ------ | ------------- | ---- |
| Apple notarisation (`notarytool` in the `build` job, macOS runner) | Apple Developer Program membership (PO account), API key as secrets of the `release-agent` environment `APPLE_API_KEY_ID`, `APPLE_API_ISSUER_ID`, `APPLE_API_KEY_P8` | yearly fee (Apple's published price, not re-read today) |
| Windows Authenticode | a code-signing certificate or a cloud signing service (PO account) | yearly fee, vendor-dependent, unverified |
| Defer both to after the MVP | binaries installed via npm, Homebrew and `curl` usually carry no quarantine flag; browser downloads on macOS will warn | €0 |

**Recommendation:** defer to after the MVP, document the warning, keep Sigstore signatures and provenance from day one. PO decision.

---

## 7. Licence (PO decision)

The template has **no licence** (`gh api` reports `license: null`; its README asks the user to choose one and to keep attribution). The 1.x repository carried MIT. 2.0 needs an explicit licence before the repository or the images, the agent and the npm packages are published, because without one nobody else may legally use, self-host or package them (the self-host product of ADR-008 depends on it). The PO chooses; common shapes for this kind of product are a single permissive licence for everything, or a copyleft network licence for the server with a permissive licence for the agent and npm wrapper. This is not legal advice. The choice also decides whether the GHCR packages are public (§1.1). Third-party licences of Go modules (incl. the pure-Go SQL drivers, ADR-010 §2) and npm/Composer dependencies are checked in CI (`go-licenses`, `composer licenses`, `pnpm licenses`) against an allow-list once the licence is chosen.

---

## 8. Secrets and variables summary (names only)

| Where | Names |
| ----- | ----- |
| GitHub `production` environment secrets | `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`, `SERVER_HOST`, `SERVER_USER` |
| GitHub `production` environment vars | `APP_DIR`, `APP_PORT` (values in the private plan) |
| GitHub repository secrets | `CONFORMANCE_CAPTURE_URL` |
| GitHub `release-agent` environment secrets (CI-3) | `HOMEBREW_TAP_TOKEN`; optional `APPLE_API_KEY_ID`, `APPLE_API_ISSUER_ID`, `APPLE_API_KEY_P8` |
| GitHub `npm-publish` environment | no secret; trusted-publishing binding, PO as reviewer |
| VPS only (root-only files) | `.env.production`, `secrets/*`: every app secret is a file (rev. 12, list in `compose-profile.md` §5), `.env.production` holds no secret, optional GHCR read token; `bin/` tools and `trusted_root.json` (§4.3) |
| None | `NPM_TOKEN` (trusted publishing), cosign keys (keyless) |

---

## Change table

| Area | Template | This file | Reason |
| ---- | -------- | --------- | ------ |
| Action pinning | major tags | commit SHAs (§1.2) + Dependabot | decision 43, TM-101 |
| Tests | PHP on Postgres 17 | PHP on Postgres 18 + Valkey; Go tests, govulncheck, contracts, conformance, self-host smoke | 2.0 stack, H-6, HR-13 |
| Images | one app image, `latest` + SHA | app, runner, postgres images; SHA + branch name (`v2`); signed; SLSA provenance; SBOM | H-5, TM-100 |
| Deploy transport | appleboy scp/ssh actions | OpenSSH with pinned host key, forced command | shared VPS |
| Compose file delivery | scp | extracted from the signed image | integrity |
| Registry login | every deploy, left on box | public package or root-only token + logout | shared VPS |
| Deploy order | pull → migrate → up → `/up` | verify signature → pull → pre-migrate backup → migrate → up → health → auto rollback | HD-4, rollback requirement |
| Rollback | manual edit of `.env.deploy` | automatic on failed health + `rollback` dispatch | — |
| Agent release | none | GoReleaser, checksums, Sigstore, SLSA, reproducibility check, npm trusted publishing with provenance, Homebrew PR | A09 §7.7, H-9 |
| Licence | none | PO decision required before publishing | template README |

## Change table (rev. 3, A09 re-check §12.5)

| ID | Area | Before | Now | Where |
| -- | ---- | ------ | --- | ----- |
| CI-1, TM-147 | Image verification | on the GitHub runner only; the box pulled any digest it was given | box re-verifies signature, provenance and commit binding before pulling; runner check kept as fast-fail | §4 steps 2-3 |
| CI-1 | Deploy arguments | hosted deployment plan §3.3: one argument; here: SHA + two digests | one grammar, defined here; plan refers to it; repositories are constants in `deploy.sh` | §4 |
| CI-2, TM-146 | Compose from the image | `config --quiet` then `up`, as root | policy P-1..P-9 on the box before `up`; the policy is not read from the image; rollback re-checks | §4.2 |
| CI-3 | Release credentials | repository secrets; npm binding to the workflow only | `release-agent` environment (tags `agent-v*`), `npm-publish` environment (PO reviewer), trusted publishing bound to both | §1.3, §1.4, §5, §8 |
| CI-4 | Workflow rules | not stated | no `pull_request_target`/`workflow_run` over untrusted code, no caches in push/release/publish jobs, `persist-credentials: false`, no Dependabot auto-merge | §1.4, §3 |
| TM-T141, TM-T145, TM-T146 | CI tests | none | added to the `conformance` job | §2 |
| (facts) | `APP_PORT` | unspecified | fixed in the private plan | §8 |

## Change table (rev. 4, A09 verification §12.9)

| ID | Area | Rev. 3 | Rev. 4 | Where |
| -- | ---- | ------ | ------ | ----- |
| CI-1 | Rollback state | `.env.deploy` copied to `.previous` at step 3, before the checks | copied at step 6, only after steps 2 and 5 pass; atomic; refused deploy leaves state untouched | §4 steps 3, 6 |
| CI-1, TM-T147 | Attestation command | `cosign verify-attestation` assumed | command fixed by a test on a real image; unsigned, other workflow, other SHA, missing attestation all refused | §4 step 2, §4.2a |
| CI-2, TM-T146 | Compose policy | P-1..P-9 | P-5: volumes with `driver`/`driver_opts` refused, `configs` absent, `file` sources only under `$APP_DIR/secrets`; P-10 `oom_score_adj` ≥ 0; P-11 never print resolved config; fixtures added | §4.2, §2 |
| SV-2, TM-T141 | Test | forged headers | underscore spellings, empty/short/missing/unreadable secret, `caddy adapt` order check | §2 |

## Change table (rev. 5, A09 verification §12.10)

| ID | Area | Rev. 4 | Rev. 5 | Where |
| -- | ---- | ------ | ------ | ----- |
| CI-1a | Compose file install | extracted file installed at step 5, before backup and migrate: a failure at backup or migrate left the new file beside the old `.env.deploy` | file checked at step 3 and kept as a candidate; backup (4) and migrate (5) run against the candidate; **installed only at step 6** with the env files, previous pair kept; steps renumbered (pull folded into 2, working-directory checks into 1) | §4 steps 1-9, §4.3 |
| CI-1a, TM-T147 | Test | state unchanged on refused verification | also unchanged when backup or migrate fails (hash of `compose.production.yaml`, `.env.deploy`, `.env.deploy.previous` compared before and after) | §4.2a |
| SV-2a, TM-T141 | Test | underscore spellings of forwarded headers on unverified requests | also on **verified** requests (real Caddy proxy with the right secret); `Forwarded`, `X-Real-IP`, `X-Forwarded-Port` in all spellings | §2 |
| RN-1a, TM-T148 | Test | path variants of `/runner/v1/…` | adds `/index.php/runner/v1/x` and `/index.php%2Frunner/v1/x`, against the host proxy template and the image Caddyfile alone | §2 |
| RD-2, TM-T145 | Test | `/s/<canary>` | adds a canary token in each emailed-link path and `/index.php/s/<canary>`, with the app stopped too | §2 |

## Change table (rev. 6, A09 verification §12.11)

| ID | Area | Rev. 5 | Rev. 6 | Where |
| -- | ---- | ------ | ------ | ----- |
| DL-3c | `deploy.sh` | no restore awareness | step 1 refuses while `restore.pending` exists (read-only check); the app image refuses to start for the same reason | §4 step 1; `compose-profile.md` §2 notes |
| DL-3, TM-T158 | Test | restore into a scratch volume, row-count check | restore drill with a deleted user: refusal to start, user back, ledger fetch and apply, user gone, then start | §2 `selfhost-smoke` |
| R-AF, RD-2, TM-T145 | Test | canary in each emailed-link path | adds `/set-password/<canary>` and its `/index.php/` spelling | §2 `conformance` |
| R-AA, R-AB | Tests | none | failed-sign-in alert (thresholds fire once, no PII in body, `/health` or log) and argon2id concurrency cap (peak RSS under the limit, same answer known or unknown) | §2 `conformance` |

## Change table (rev. 7, decision 50, A08 spike)

| ID | Area | Rev. 6 | Rev. 7 | Where |
| -- | ---- | ------ | ------ | ----- |
| Decision 50, A08 C-7, TM-T159 (proposed) | Image | no check for an event-loop extension | build fails if `ext-uv`/`ext-event` is missing; `reverb-boot` tested for missing extension, low `nofile`, client events not `none` | §3 step 4a |
| Decision 50, TM-T160 (proposed) | Client events | none | Pest config test: `none`, no env override | §2 `realtime` |
| Decision 50, A08 C-8, TM-T161 (proposed) | Long-poll | none | 503 + `Retry-After` when slots are full; boot check on slot values | §2 `realtime` |
| Decision 50 | Capacity smoke | websocket upgrade only | 1,100 sockets through the proxy container must not kill Reverb (smoke, not a measurement; Phase 5 measures) | §2 `conformance` |

## Change table (rev. 8, A09 slice 3 review)

| ID | Area | Rev. 7 | Rev. 8 | Where |
| -- | ---- | ------ | ------ | ----- |
| S3-3, S3-4 | Caddy validation | `docker/Caddyfile`, three trust modes | also both `INGEST_MODE` values and `docker/Caddyfile.ingest` (in `ci.yml`) | §2 |
| S3-3, S3-4, decision 2 | Conformance | none for ingest | multipart bytes, 15 s body cut, pool isolation, hop trust and `Via`, restart, fail-open alert | §2 |

## Change table (rev. 9, decision 61)

| ID | Area | Rev. 8 | Rev. 9 | Where |
| -- | ---- | ------ | ------ | ----- |
| Decision 61 | HAProxy config | none | `haproxy -c` with a throwaway secret in `ci.yml` | §2 |
| Decision 61, S3-4 | Conformance | ingest checks | plus cap, rate, `429` shape, /64, forged keys, slow senders, `413`, headers, reload on redeploy | §2 |
| Decision 61 | Compose policy | eight services | eleven; `haproxy` by digest; `network_mode: none` and `user: 0:0` on the one-shot; `group_add` values | §4.2 |

## Change table (rev. 10, A09 runtime and HAProxy review)

| ID | Area | Rev. 9 | Rev. 10 | Where |
| -- | ---- | ------ | ------ | ----- |
| S3-21 | Policy draft | `user` rule refused `engine`/`backup`; one-shot, volume, secrets, networks, routing unpinned | P-3 fixed; P-12 to P-17 pin them, each with a TM-T146 fixture | §4.2 |
| S3-17 to S3-19, S3-22, S3-23 | Conformance | checks (1)-(8) | plus (9)-(14): partial-body class, answer shapes, ingest recreate, secret shape and spread | §2 |
| S3-19 | `haproxy -c` | config only | config and the `received-200.http` errorfile | §2 (`ci.yml`) |

## Change table (rev. 11, agent follow-ups)

| ID | Area | Rev. 10 | Rev. 11 | Where |
| -- | ---- | ------- | ------- | ----- |
| A06 `13f7443` | Pairing key | none | `agent_pairing_hmac_key` file secret on `app` only; allowed source and per-service rule | §4.2 P-5, P-14, P-17; §8 |
| A06 `0a088b4` | Long-poll workers | not pinned | `LONGPOLL_HTTP_WORKERS` = `APP_NUM_THREADS` on `app` | §4.2 P-17 |
| A06 `0a088b4` | Dev agent builds | none | refused on hosted (P-18) and by the entrypoint outside local/testing | §4.2 P-18 |
| A06 `0a088b4` | Advisory locks | none | session-pooled database only | §4.2 P-19 |

## Change table (rev. 12, secrets as files)

| ID | Area | Rev. 11 | Rev. 12 | Where |
| -- | ---- | ------- | ------- | ----- |
| CLAUDE.md rule | App secrets | plain variables in `.env.production` (all but the pairing key and the edge secret) | Compose file secrets, mounted per service; P-20 refuses plain values | §4.2 P-5, P-14, P-17, P-20; §8 |

## Change table (rev. 13, step C2)

| ID | Area | Rev. 12 | Rev. 13 | Where |
| -- | ---- | ------- | ------- | ----- |
| Decisions 65, 66 | Branch | `main` | `v2` everywhere (triggers, identity, deploy branch); `main` deleted, 1.x on `v1` | §1.3, §2-4 |
| C2 | Policy, deploy | drafts | `deploy/policy/compose-policy.py` (+ 34 fixtures in CI), `deploy/bin/deploy.sh`, `deploy-gate` | §4, §4.2 |
| C2 | Attestations | always | only when the repo is public; until then signed BuildKit provenance and SBOM, checked in `container.yml` | §1.3, §3 |
| C2 | CI test job | overrode `phpunit.xml` drivers (182 failures on GitHub) | no override; `APP_URL` as the tests expect | §2 |

## Change table (rev. 14, C3 finish)

| ID | Area | Rev. 13 | Rev. 14 | Where |
| -- | ---- | ------- | ------- | ----- |
| C3 | Box completion | manual | `deploy/bin/c3-finish.sh`: pinned cosign and trusted root, slice, `.env.production` from a template, GHCR mode, summary | §4.3 |
| TM-T147 | Verify line | proven at C6 | proven in `container.yml` with the box's pins | §3, §4.3 |
| §4.3 | Rollback | swap only | re-verifies signatures and policy first | §4.3 |
| §4 | Compose env files | `.env.deploy` only (postgres, valkey, backup got no values) | `.env.production` then `.env.deploy` in every call; CI resolves with the hosted template | §4, §4.3 |
| CI | cosign version | installer default | `cosign-release: v3.1.4` explicit, same pin as the box | §4.3 |
| Step 8 | Health wait | 30 × 2 s, every service | 90 × 2 s; `backup` left out (5 min check about backup freshness, gated by step 4); scheduler `start_period: 150s`, `start_interval: 5s` | §4 |

## Change table (rev. 15, decision 72)

| ID | Area | Rev. 14 | Rev. 15 | Where |
| -- | ---- | ------- | ------- | ----- |
| Decision 72 | Mail secret | none | `pulsesend_api_key` in the policy (P-5, P-14 on `app` and `horizon`, P-20), the CI secret stand-ins and `c3-finish.sh` (template check and summary) | §4.2 |

## Change table (rev. 16, SSR of the public pages)

| ID | Area | Rev. 15 | Rev. 16 | Where |
| -- | ---- | ------- | ------- | ----- |
| PO 2026-11-16 | Compose policy | eleven services | twelve: `ssr` (P-1, P-2, P-3, P-7, P-14, P-15, P-16, new P-21); 49 fixtures | §4.2 |
| PO 2026-11-16 | Build | `pnpm run build` writes `public/build` | also `bootstrap/ssr/ssr.js` (`vp build && vp build --ssr`); the image keeps the bundle without source maps and gains the `node` binary | §3 |
