# Hosting options · Webhook Trap 2.0

> **HD-1 superseded by decision 39 (2026-11-14).** The PO chose the existing Contabo VPS instead of a new Hetzner VPS; the runner stays on its own small VM (HD-5). Decision 40 then retired 1.x entirely, so 2.0 gets the whole VPS (HR-14 and the "current VPS extended" rejection in §2.4 no longer apply). See `contabo-deployment-plan.md` and `1x-decommission-runbook.md`. The rest of this document (requirements, H-2..H-9, HD-2..HD-10) still applies.

**Status:** DRAFT for a PO decision · **Author:** A10 (DevOps) · **Date:** 2026-11-14
**Scope:** decision 32 §6 (hosting deferred to Phase 4), decision 37 (A10 hosting options on A05's package), `open-questions.md` §3 (H-1 to H-9).
**Inputs read:** A05's Phase 4 drafts (`open-questions.md`, ADR-010, ADR-004/005/006 amendment 1, `schema.md`, `event-catalogue.md`), accepted ADR-007 and ADR-008, decisions 32 and 35 to 37, `execution-plan.md` Phase 4, A01 `scenarios-and-judge.md` §11 (PERF targets), the 1.x hosting note (`.docs/10-hebergement.md`, read only).
**Constraints honoured:** documents only. No account, no provisioning, no install, nothing touched on the live 1.x stack. No project details were sent to any vendor; only public pricing and documentation pages were read.

Prices were read from vendor pages on **2026-11-14**. They are monthly, excluding VAT. Where a page did not show a figure, the cell says **unverified**. Hetzner prices are in EUR; the others are in USD. No currency conversion is applied.

---

## 0. Summary

| | A. One VPS, Compose ★ | B. VPS + managed Postgres | C. Laravel Cloud (+ runner VPS) | D. Fly.io containers |
| - | - | - | - | - |
| **Monthly cost, MVP** | **€34–53** | **≈ €26 + $30–48** | **≈ $148 + €6.49** | **≈ $81** |
| **Ops burden** | Highest: OS, Postgres, backups, upgrades are ours | Medium: DB backups/PITR managed; VPS still ours | Lowest for the app; runner VPS still ours | Medium: platform managed, Postgres managed, Redis ours |
| **Raw request target** | Fully under our control (tested proxy chain) | Same as A | Through a vendor edge: **unverified** | Through Fly proxy: **unverified** |
| **Egress control for the runner** | Separate VM + provider outbound firewall | Same as A | Not available on the platform: runner must live elsewhere | No outbound firewall product; in-VM rules **unverified** |
| **Main risk** | Single box: one failure takes ingest down; backups depend on us | Cross-provider DB latency and egress; two bills | Cost 3×; vendor edge may rewrite URLs or cap bodies | Platform specifics (6PN private network reachable from runner); Redis self-run |
| **Exit path** | Same Compose as self-host: any VPS | Same as A + `pg_dump` | Standard Laravel; Postgres is Neon (`pg_dump`) | Dockerfiles portable; `fly.toml` not |
| **Dogfoods the self-host Compose** | Yes | Mostly | No | No |

**Recommendation:** option A in the EU (Hetzner, Germany or Finland), running the same Compose stack that self-hosters get, with the server-side engine runner on its own small VM, and encrypted off-box backups with point-in-time recovery. Details and what would change it: §5.

---

## 1. Requirements derived from the drafts

| ID | Requirement | Source | Consequence for hosting |
| -- | ----------- | ------ | ----------------------- |
| HR-1 | **Long-lived websockets** (Reverb): browsers on `private-project/run/workspace/session` channels; one socket per connected agent, heartbeat 20 s, disconnect after 60 s | ADR-004 am. 1, ADR-010 §5, H-3 | Rules out serverless PHP (Lambda/Vapor). Proxy must pass `Upgrade`, not buffer, idle timeout ≥ 60 s. Reverb needs an event-loop extension above ~1 000 connections (§4 H-3). |
| HR-2 | **Queue workers** on four queues: `default`, `broadcasts`, `maintenance`, `replay`; scheduler (every-minute guest expiry) | ADR-007, schema §8 | Always-on worker processes and a scheduler. Scale-to-zero platforms must keep them awake. |
| HR-3 | **Redis required**, no Redis-free profile | Decision 32 §4 | A Redis-compatible server with persistence suitable for queues (AOF), `noeviction` for queue keys. |
| HR-4 | **Sandboxed server-side engine runner** (`webhooktrap serve-run`): parses untrusted capture bodies, sends HTTP to user-chosen public URLs (hosted) or allow-listed private hosts (self-host B′) | ADR-010 §1, §7; ADR-006 am. 1; A09 S-9, S-11 | Process isolation, CPU/memory/pid limits, no DB credentials, and an **egress firewall** as defence in depth: deny loopback, RFC 1918, link-local incl. `169.254.169.254` metadata, CGNAT `100.64/10`, ULA `fc00::/7`, the host and the internal Docker networks. Abuse exposure: the runner's IP sends traffic to third parties. |
| HR-5 | **Raw request target preserved** byte for byte through every proxy (`REQUEST_URI`), configured public origin, trusted-proxy list | ADR-005 am. 1 §1; S-6; H-6 | No hop may normalise the path or query (percent-encoding case, `//`, `;`, `+`, repeated keys). Any vendor edge in front of ingest must be verified by test or bypassed. |
| HR-6 | **Bodies:** kept up to 5 242 880 bytes inclusive; hard limit 25 MiB returning a uniform 413; ingest reads and hashes the full body before replying; always `200 {"received":true}` otherwise | ADR-005 am. 1 §4–5 | Proxy body limit 25 MiB; body read timeout; ingest must never return a 5xx from the edge (§4 H-6). |
| HR-7 | **PostgreSQL ≥ 17 (18 preferred)**, bodies as `BYTEA` in a separate table, delete-heavy retention jobs | schema §4, §8; H-8 | Disk sized for 30-day captures; autovacuum tuned; TOAST compression. |
| HR-8 | **Object storage:** not used by the app in the MVP (bodies stay in `BYTEA`) | ADR-005 am. 1 §6 | Only needed as a **backup target** off the box. |
| HR-9 | **Backups** contain capture bodies and probe display values; rotation is part of the hosting decision | schema §8; A09 C-3 §2.6 | Encrypted at rest, off the server, retention bounded so deleted data leaves backups within a stated window; tested restores. |
| HR-10 | **Observability:** queue lag, Reverb health, ingest drop counter (`CaptureDropped`), `#invalid` display values metric, errors; never log bodies, header values, keys or probe values | ADR-005 am. 1 §4; ADR-010 §11.5; event-catalogue rule 3 | Error tracker with scrubbing; metrics that count, not record. |
| HR-11 | **Load targets** (A01 §11): PERF-1 10 deliveries/s for 60 s on one URL + 200 URLs at 1/s (≈ 210 req/s ingest), 50 viewers; PERF-3 20 runs/min; PERF-4 p95 < 500 ms timeline | `scenarios-and-judge.md` §11 | Sizing; the reference environment (H-7) is 2 vCPU / 4 GB. |
| HR-12 | **Data location** (EU or US) | this brief | Webhook payloads often hold third-party personal data (customers' names, emails, addresses). GDPR applies to EU users whatever we choose. |
| HR-13 | **Same artefact as self-host** where possible (ADR-008, one Compose profile) | ADR-008, decision 32 §4 | Hosting on the self-host Compose is free testing of what self-hosters run. |
| HR-14 | **1.x untouched** and online on its own domain until sunset | Decision 32 §5 | 2.0 runs on new infrastructure; no shared box, DB or DNS records with 1.x. |
| HR-15 | **Agent distribution** channels (GitHub releases, Homebrew, install script, npm wrapper) | ADR-010 §2, H-9 | Static hosting and package registries; no server cost. |

### MVP scale assumed for costs

No usage data exists for 2.0 (decision 32 §5: fresh start, no 1.x query). Costs below assume:

- ≤ 500 accounts, ≤ 200 daily active projects, ≤ 100 concurrent browser tabs, ≤ 50 connected agents;
- ingest ≈ 50 000 captures/day at a mean body of 8 KiB → ≈ 0.4 GB/day → **≈ 12 GB for 30 days** of captures, plus runs and indexes: **DB ≈ 20 GB**;
- abuse ceiling to plan for: a guest may store 100 captures × 5 MiB = 500 MiB per 24 h session, so a few hundred abusive guests can add tens of GB in a day (§4 H-1, disk watermark).

Formula for revisions: `DB GB ≈ captures/day × mean body KiB × retention days / 1 048 576 × 1.6` (indexes, metadata, TOAST overhead).

---

## 2. Hosting options for the hosted service

All options share these parts, priced separately in §2.5: transactional email, error tracking, uptime checks, domain.

### Common design choice: the runner is a remote agent

ADR-006 am. 1 has a Laravel job on the `replay` queue invoke the engine binary. For hosting, the safest placement is to run `webhooktrap serve-run` as a **long-running process that pulls server-run jobs over the same HTTPS job API the agents use** (ADR-010 §5), with a runner token instead of an agent key. The Laravel `replay` job then only marks the run as available and nudges.

Why this matters for operations:

- the sandbox holds **no DB or Redis credentials**, only a scoped runner token (meets S-11);
- the runner can live on a **separate VM with its own IP and an outbound firewall**, so an abuse complaint or IP block hits the runner, not ingest;
- every option below can use the same runner VM, including managed platforms that cannot firewall egress.

This is a proposal to A05 and A09 (it changes who calls the binary, not the engine). If they keep the in-process design, the egress rules in option A move to a Docker network on the main host (DOCKER-USER chain), which is weaker: it shares the host IP and kernel.

### 2.1 Option A · One VPS running the self-host Compose stack ★

```
                Internet (providers, browsers, agents)
                         │ 443
        ┌────────────────▼────────────────────────────── VPS 1 (CPX32, 4 vCPU / 8 GB) ─┐
        │  caddy (TLS, 25 MiB limit, websockets)                                       │
        │    ├─ app.<domain>      → app (FrankenPHP or nginx+FPM)                       │
        │    ├─ in.<domain>/c/…   → app (ingest; no cookies on this host)               │
        │    └─ ws.<domain>       → reverb                                              │
        │  horizon (default, broadcasts, maintenance) · scheduler                      │
        │  postgres 18 (volume) · valkey/redis (AOF, volume)                            │
        │  backup sidecar: WAL archive + nightly base backup, encrypted ──────────┐     │
        └───────────────────────────────────────────────────────────────────────│─────┘
                         ▲ HTTPS job API (runner token)                         ▼
        ┌────────────────┴───────────── VPS 2 (CPX02, 1 vCPU / 1 GB) ─┐   Storage Box / R2
        │ runner: webhooktrap serve-run (non-root, read-only rootfs)   │   (EU, off-box)
        │ provider firewall: inbound none; outbound 443/80 + DNS only  │
        │ in-VM nftables: deny private, link-local, CGNAT, ULA         │
        └──────────────────────────────────────────────────────────────┘
```

| Requirement | How it is met |
| ----------- | ------------- |
| HR-1 websockets | Reverb container behind Caddy (native websocket pass-through); `ext-uv` in the Reverb image; `nofile` ≥ 10 000 |
| HR-2 workers | Horizon container (three queues, supervisors per queue) + scheduler container |
| HR-3 Redis | Valkey or Redis container, AOF `everysec`, `noeviction` (§4 H-2) |
| HR-4 runner | Separate VM, Hetzner Cloud Firewall (inbound closed), nftables egress deny list in the VM, systemd or Docker with `--cap-drop ALL`, memory/pid limits, kill switch (`server_replay_public = false`) |
| HR-5 raw target | We own every hop; conformance test in CI against the real proxy chain (§4 H-6). No CDN on the capture host |
| HR-6 bodies | Caddy `request_body max_size` 25 MiB, body read timeout 30 s |
| HR-7 Postgres | Postgres 18 container on a local NVMe volume |
| HR-9 backups | WAL archiving + nightly base backup (pgBackRest or WAL-G) encrypted to a Storage Box (EU) or R2 (EU jurisdiction); 7-day PITR, 14-day retention; Hetzner server backups optional |
| HR-10 observability | Laravel Pulse or Horizon metrics for queue lag; error tracker with scrubbing; external uptime checks on `/up` and the capture URL |
| HR-12 location | Germany (FSN1/NBG1) or Finland (HEL1) |
| HR-13 | Identical Compose file to self-host, plus a small override file for hosted (runner moved out, hosted settings) |

**Cost (Hetzner, read 2026-11-14):**

| Item | Germany | Finland | Source |
| ---- | ------- | ------- | ------ |
| VPS 1: CPX32 (4 vCPU AMD shared, 8 GB, 160 GB NVMe, 20 TB traffic) | €35.99 | €30.49 | hetzner.com/cloud/regular-performance |
| Server backups (7 slots; 20 % of the server price, inferred from "from €1.098" on a €5.49 server) | €7.20 | €6.10 | same page; **percentage inferred, unverified** |
| VPS 2 (runner): CPX02 (1 vCPU, 1 GB, 20 GB) | €6.49 | €5.49 | same page |
| Off-box backups: Storage Box BX11 (1 TB, SFTP/Borg/Restic, 10 snapshots) | €3.20 | €3.20 | hetzner.com/storage/storage-box |
| **Total** | **€52.88** | **€45.28** | |

Start-small variant: CPX22 (2 vCPU, 4 GB, 80 GB, **NBG1 only** in Germany) €19.99, or €16.99 in Finland, gives **€33.68 / €29.68** with the same runner and backups. This is also the H-7 reference size. Resize to CPX32 is a reboot.

Note: Hetzner's cheaper "Cost-Optimized" line (CX23 2 vCPU/4 GB €5.99, CX33 4 vCPU/8 GB €8.99) shows **"currently unavailable"** on 2026-11-14. If it returns, option A falls to about €20/month. Do not plan on it.

**Ops burden:** highest. We patch the OS (unattended-upgrades), upgrade Postgres minors (container image bump), own backups and restore drills, watch disk. One person can run it; the 1.x VPS shows the team already does this.

**Risks:**
- Single point of failure: a host failure stops ingest. Providers retry webhooks (most retry for hours to days), so a short outage loses little; a long one loses captures. Mitigation: documented rebuild from Compose + backups in < 1 h, rehearsed once before launch.
- Disk exhaustion from 5 MiB bodies (guests): disk alert at 70 %, an app-level storage watermark that drops bodies (`body_kept = false`) above a threshold (still `200`), retention jobs monitored.
- Abuse reports against the runner IP (Hetzner forwards and can block): separate VM, per-destination limits (S-9), an identifying `User-Agent` with an abuse contact URL, kill switch.
- Noisy-neighbour CPU on shared vCPU: PERF tests on the same plan (H-7) show the margin.

**Exit path:** the stack is the self-host Compose. Moving to any VPS provider (OVH, Scaleway, DigitalOcean, a dedicated server) is `pg_dump`/restore or a base backup + WAL replay, then a DNS switch. No proprietary service in the path.

### 2.2 Option B · VPS for app, Reverb, workers, Redis + managed PostgreSQL

Same as A, but Postgres is a managed service. Hetzner offers no managed PostgreSQL (as far as its public site shows; **unverified**), so the database is at another provider in the same metro area.

```
VPS 1 (CPX22/CPX32, Germany): caddy · app · reverb · horizon · scheduler · valkey
        │ TLS, public internet or provider peering (≈ 2–5 ms Nuremberg/Falkenstein → Frankfurt, unmeasured)
        ▼
Managed Postgres (Frankfurt): DigitalOcean Managed PostgreSQL, or Neon (AWS eu-central-1)
VPS 2: runner (as in A)
```

| Requirement | Difference from A |
| ----------- | ----------------- |
| HR-7 | Managed Postgres; check PostgreSQL 18 support per vendor (**unverified**) |
| HR-9 | Daily backups and PITR by the vendor (DigitalOcean retention **unverified**; Neon Launch: up to 7-day history at $0.20/GB-month) |
| HR-11 | Every ingest is one or more round trips to a remote DB; at 210 req/s and 2–5 ms RTT this fits, but bodies up to 5 MiB cross the provider boundary (egress/ingress fees on the DB side **unverified**) |
| HR-12 | Both providers have Frankfurt; DPA with two processors |

**Cost:**

| Item | Price | Source |
| ---- | ----- | ------ |
| VPS 1: CPX22 NBG1 (enough without Postgres on the box) | €19.99 | Hetzner |
| VPS 2 runner CPX02 | €6.49 | Hetzner |
| DigitalOcean Managed PostgreSQL, 2 GiB / 1 vCPU / 30–60 GiB, single node | $30.45 | digitalocean.com/pricing/managed-databases |
| *or* Neon Launch: 0.5 CU always on (ingest keeps it awake: 730 h × 0.5 × $0.106) + 20 GB × $0.35 + history ≈ 5 GB × $0.20 | ≈ $46.70 | neon.com/pricing (page dated 2026-11-04) |
| **Total** | **≈ €26.48 + $30–47** | |

DigitalOcean's smallest node (1 GiB, $15.15) is too small for 20 GB of `BYTEA` with a useful cache; a standby node for HA is extra (price not shown, **unverified**).

**Ops burden:** medium. The hardest part of A (backups, PITR, Postgres upgrades) moves to the vendor; the VPS remains ours.

**Risks:** cross-provider latency and bandwidth on large bodies; two vendors to watch; managed-DB connection limits; Neon cold starts if compute is allowed to suspend (must be disabled for ingest). 1.x already uses Neon, so the team knows it; 2.0 must use a **separate** Neon project (HR-14).

**Exit path:** `pg_dump` out of the managed DB into option A's Postgres container. Low lock-in.

### 2.3 Option C · Laravel Cloud for the app, with the runner on a VPS

Laravel Cloud (AWS Graviton, regions include Frankfurt, Ireland, London, Virginia, Ohio) runs the app, worker clusters, Valkey, managed Reverb and Serverless Postgres (Neon). It has no outbound firewall controls we can set, so the runner stays on a small VPS (the runner-as-agent design makes this possible).

```
Laravel Cloud (eu-central-1): edge (TLS/CDN/WAF) → app (Flex 2 GiB) · worker cluster · Valkey 1 GB · Reverb cluster · Serverless Postgres
Hetzner VPS: runner (as in A)        Backups: vendor PITR (Neon)
```

| Requirement | How it is met / gap |
| ----------- | ------------------- |
| HR-1 | Managed Reverb clusters, billed by **max concurrent connections and messages per day** (100 conn / 200 K msgs/day $5; 500 / 1 M $25; 2 000 / 8 M $100). Pusher pings count as messages. At MVP scale the 500 tier fits; a PERF-1-like day would exceed 1 M messages |
| HR-2 | Worker clusters (Growth plan and up); scheduler included |
| HR-3 | Laravel Valkey (Redis-compatible); persistence semantics for queues **unverified** |
| HR-4 | Runner on Hetzner (as A). Engine binary in the app image for `plan`/`verify` via a build command (download + checksum); allowed by the build system (**unverified**) |
| HR-5 | Requests pass a vendor edge (Cloudflare-based edge network per Laravel Cloud docs). Whether it normalises paths or query strings is **unverified**: must pass the H-6 conformance test before choosing C |
| HR-6 | Edge body-size limit **unverified** (must allow 25 MiB and let our app answer 200 above 5 MiB) |
| HR-7 | Serverless Postgres (Neon); PG version choice **unverified** |
| HR-9 | Point-in-time restore by Neon; retention by plan |
| HR-10 | 7-day logs and metrics on Growth; Nightwatch (paid) for tracing |
| HR-12 | Frankfurt region available; per-region prices may differ (examples on the page use US East Ohio) |

**Cost (Growth plan, rates from laravel.com/cloud/pricing and /cloud/docs/pricing, 2026-11-14):**

| Item | Price |
| ---- | ----- |
| Growth plan (worker clusters need Growth) | $20.00 |
| App: Flex 2 GiB, always on (capped at 28 days) | $24.00 |
| Worker cluster: Flex 1 GiB, always on | $12.00 |
| Laravel Valkey 1 GB (Flex), 672 h × $0.03571 | $24.00 |
| Reverb 500 connections | $25.00 |
| Serverless Postgres 0.5 CU, awake all month (720 h × $0.106 × 0.5) | $38.16 |
| Postgres storage 20 GB × $0.50 | $10.00 |
| Monthly usage credit | −$5.00 |
| Runner VPS (Hetzner CPX02) | €6.49 |
| **Total** | **≈ $148 + €6.49** |

**Ops burden:** lowest for the app tier (no OS, no Postgres admin, push-to-deploy). The runner VPS and its firewall are still ours.

**Risks:** about 3× the cost of A at MVP scale, rising with Reverb messages; two unverified blockers (raw target and body limit through the edge); the hosted service would no longer run the self-host Compose, so self-host bugs surface only in CI; vendor price changes (the docs already announce Upstash price changes for 2026-11-30, showing that line items move).

**Exit path:** standard Laravel; Postgres is Neon (`pg_dump`); Reverb is the open-source protocol. Moving to A takes a day of work plus DNS.

### 2.4 Option D · Fly.io containers

Each service is a Fly Machine (Firecracker micro-VM) built from our Dockerfiles; Fly Managed Postgres for the DB.

```
Fly proxy (anycast) → app machine · reverb machine     worker machine · valkey machine (+ volume)
                                                        Fly Managed Postgres (Basic)
runner machine (or the Hetzner runner VM)
```

| Requirement | How it is met / gap |
| ----------- | ------------------- |
| HR-1 | Fly proxy supports websockets; idle timeout and limits **unverified** |
| HR-3 | Valkey on a machine with a volume (we operate it), or Upstash via Fly (pay-per-request; queue polling cost **unverified**) |
| HR-4 | Fly has no outbound firewall product. Machines can reach the organisation's private IPv6 network (6PN, `fdaa::/16`) unless blocked inside the VM; in-VM nftables as root is plausible but **unverified**. Safer: keep the runner on the Hetzner VM |
| HR-5 | Fly proxy path handling **unverified**: H-6 conformance test required |
| HR-6 | Fly proxy body limits **unverified** |
| HR-7 | Fly Managed Postgres Basic ($38) — version and backup retention **unverified** |
| HR-12 | EU regions (Frankfurt, Amsterdam, Paris) exist; prices on the page are for Ashburn/Secaucus and "other regions vary" |

**Cost (docs.fly.io/about/pricing, 2026-11-14, US-east prices):**

| Item | Price |
| ---- | ----- |
| App: shared-cpu-2x 2 GB | $13.39 |
| Reverb: shared-cpu-1x 1 GB | $6.70 |
| Workers + scheduler: shared-cpu-1x 1 GB | $6.70 |
| Valkey: shared-cpu-1x 512 MB + 2 GB volume ($0.15/GB) | $3.99 |
| Runner: shared-cpu-1x 512 MB | $3.69 |
| Managed Postgres Basic + 20 GB × $0.28 | $43.60 |
| Dedicated IPv4 | $2.00 |
| Outbound data ≈ 50 GB × $0.02 | $1.00 |
| **Total** | **≈ $81** |

**Ops burden:** medium: no OS patching, but Fly-specific deploy config, Valkey on a volume is ours, and the machine model has its own failure modes (host failure means restarting a machine on another host; volumes are tied to a host).

**Risks:** several unverified platform behaviours sit on our hardest requirements (raw target, egress); fewer people on the team know Fly than a VPS.

**Exit path:** Dockerfiles are portable; `fly.toml` and Fly Postgres are not. Medium effort.

**Considered and not detailed:** Render (web services from $7/$25, Postgres from $6/$19, Key Value repriced from 2026-10-01; prices from third-party summaries, **unverified** on render.com because the pricing page did not render). It has the same unknowns as D on raw target and egress, at a higher fixed cost per service. Extending the **current 1.x VPS** (H-1 option a) is rejected: HR-14 forbids sharing with the live 1.x stack, the egress firewall would also change 1.x's traffic, and the 1.x box (a ~€4–6 entry plan per the 1.x note) has no room for Postgres, Redis, Reverb and workers.

### 2.5 Costs common to every option (not included above)

| Item | Typical choice | Cost | Status |
| ---- | -------------- | ---- | ------ |
| Transactional email (sign-up, password reset, guest-claim) | Postmark, Resend, SES or the provider already used by 1.x | $0–15 | **unverified**, PO choice |
| Error tracking | Sentry free tier, or self-hosted GlitchTip in the Compose stack (keeps payloads in our hands) | $0 | **unverified** |
| External uptime checks | any free tier (two probes: `/up` and a capture URL expecting `200 {"received":true}`) | $0 | — |
| Domain | existing `.dev` or new; separate capture host recommended (§4 H-6) | ≈ $1–2/month amortised | — |
| Off-box backups if R2 instead of a Storage Box | Cloudflare R2 ($0.015/GB-month, free egress, EU jurisdiction option) | < $1 at 20–40 GB | price from memory, **unverified** today |

---

## 3. Self-host Compose profile (one profile, decision 32)

One `compose.yaml`, no minimal profile (ADR-007/008 Phase 0 amendments are void, PO-3). Hosted uses the same file plus `compose.hosted.yaml` (an override, not a profile) that removes the local runner and sets hosted-only settings.

### 3.1 Services

| Service | Image (proposal) | Role | Health check |
| ------- | ---------------- | ---- | ------------ |
| `proxy` | `caddy:2` | TLS (auto or provided), routing, 25 MiB body limit, websocket pass-through, trusted-proxy boundary | `caddy` admin endpoint or TCP 443 |
| `app` | `webhooktrap/app:<version>` (PHP 8.4, FrankenPHP or nginx + FPM; engine binary in `/usr/local/bin/webhooktrap`) | HTTP: UI, ingest, agent API, runner API | `GET /up` (Laravel), plus an internal `GET /health` that checks DB and Redis |
| `reverb` | same image, `php artisan reverb:start` | Websockets | TCP on the Reverb port; an app-side check that a test broadcast is accepted |
| `horizon` | same image, `php artisan horizon` | Queues `default`, `broadcasts`, `maintenance` | `php artisan horizon:status` |
| `scheduler` | same image, `php artisan schedule:work` | Guest expiry, retention, token cleanup | a heartbeat row/key updated every minute, checked by `/health` |
| `runner` | `webhooktrap/runner:<version>` (static Go binary on a distroless base, non-root) | `serve-run`: pulls server-run jobs over the internal job API with a runner token | process alive + last poll < 60 s (reported to `/health`) |
| `postgres` | `postgres:18` | Database | `pg_isready` |
| `redis` | `valkey/valkey:8` or `redis:8` (PO choice, §5) | Queues, cache, rate limits, Reverb scaling later | `valkey-cli ping` |
| `backup` (optional, on by default) | small image with `pg_dump` + `restic` (or pgBackRest) | Nightly dump, encrypted, to a local volume or S3/SFTP target | last success < 26 h, exposed to `/health` |

Queue lag check (H-4): `/health` reports the age of the oldest job per queue; the documented alert is `replay` or `default` older than 30 s, `maintenance` older than 10 min.

The runner stays idle unless server replay is enabled in instance settings (`server_replay_public`; B′ private destinations off by default). For self-host, the engine's guard (ADR-010 §7) is the primary control. A host firewall rule set (DOCKER-USER chain) is documented as optional hardening because Compose cannot express "deny private ranges" portably (Docker Desktop, rootless Docker), and B′ needs some private hosts reachable anyway.

### 3.2 Networks and volumes

- Networks: `edge` (proxy ↔ app, reverb), `internal` (app, workers, postgres, redis; `internal: true`), `runner` (runner ↔ app's runner API only, plus outbound).
- Volumes: `pgdata`, `redisdata` (AOF), `caddy_data` (certificates), `backups`. The app keeps no local state beyond logs to stdout.

### 3.3 Minimum resources

| Use | vCPU | RAM | Disk | Status |
| --- | ---- | --- | ---- | ------ |
| Recommended / reference (H-7) | 2 | 4 GB | 40 GB + 1.6 × expected capture volume | to be validated by A08/A11 against PERF-1..4 |
| Personal instance, one user | 1 | 2 GB | 20 GB | **untested**; stated as "may work", not supported until measured |

Memory budget at 4 GB (estimate): Postgres `shared_buffers` 1 GB, Valkey `maxmemory` 256 MB, app ≈ 600 MB (FPM 10 workers × 60 MB, or FrankenPHP equivalent), Reverb ≈ 150 MB, Horizon ≈ 300 MB, scheduler and runner ≈ 150 MB, Caddy ≈ 50 MB: ≈ 2.5 GB, leaving page cache for Postgres.

### 3.4 Upgrade story

1. Images are tagged by release (`2.0.3`), never `latest`; the app and runner images come from one release pipeline so the engine version matches (H-5).
2. `docker compose pull` → `docker compose run --rm app php artisan migrate --force` → `docker compose up -d`. Migrations are forward-only and backward-compatible for one minor release (expand/contract), so a rollback of the image does not need a schema rollback.
3. The `backup` service runs a dump before migrations when `BACKUP_BEFORE_MIGRATE=true` (default).
4. Postgres major upgrades are documented separately (dump/restore, or `pg_upgrade` with a one-off container); never automatic.
5. Release notes state the minimum agent version (`min_agent_version`, ADR-010 §8).

### 3.5 Backup and restore story

- What: Postgres only. Redis holds queues and cache; in-flight jobs are re-created by the app's recovery paths (runs time out, broadcasts are regenerated by reload), so Redis is not backed up.
- How: nightly `pg_dump -Fc`, encrypted with `restic` to a local volume and optionally S3/SFTP; operators wanting PITR are pointed to pgBackRest/WAL-G recipes.
- Retention default: 14 daily backups. The docs state that **backups contain capture bodies and probe display values**, so deleted data stays recoverable for up to the backup retention, and operators must include that in their own privacy notice (HR-9).
- Restore: `make restore BACKUP=<id>` (or the documented commands) into an empty `pgdata`, tested in CI on every release with a seeded database.

---

## 4. H-1 to H-9

**H-1 · Hosting options for the PO.** §2 compares four. The three asked for map as: (a) "current VPS extended" → rejected (HR-14), replaced by **A, a new VPS** with the same pattern; (b) managed Postgres + single VPS → **B**; (c) container platform → **C** (Laravel Cloud) and **D** (Fly). All four respect the criteria: Reverb needs long-lived sockets (no serverless PHP), raw `REQUEST_URI` (tested, §H-6), an egress-firewalled runner (separate VM in every option), and bounded backup rotation (§3.5; hosted: 7-day PITR, 14-day retention, encrypted, EU). Recommendation in §5.

**H-2 · Redis.** One Redis-compatible instance for the MVP:
- `appendonly yes`, `appendfsync everysec` (at most ~1 s of queued jobs lost on a crash; jobs are dispatched after commit, so the state is in Postgres and can be re-dispatched by the recovery jobs);
- `maxmemory` set (256 MB self-host default, 512 MB hosted) with **`noeviction`**: an evicted queue key is a lost job. Cache keys must all carry a TTL so memory stays bounded; alert at 70 % memory;
- key prefixes per use (`queues:`, `cache:`, `rl:`) and the infrastructure adapter as the only client (decision 32 §4 architecture test);
- if cache pressure ever competes with queues, split into two instances (queue: AOF + noeviction; cache: no persistence + `allkeys-lru`). Not needed at MVP scale;
- licence: Redis 8 is AGPLv3/RSAL/SSPL tri-licensed; Valkey 8 is BSD and a drop-in for phpredis, Horizon and Reverb scaling. Shipping either image is fine for an open-source Compose file; Valkey avoids questions from self-hosters' legal teams. PO choice.

**H-3 · Reverb.**
- Connections: one per open tab plus one per connected agent. MVP: ≤ 150 concurrent. Reverb's default `stream_select` loop is limited to ~1 024 open files; the image includes `ext-uv` so the loop is not the limit, and the container sets `nofile` to 10 000.
- Proxy: Caddy passes websockets without configuration; nginx (if used) needs `Upgrade`/`Connection` headers, `proxy_buffering off` and `proxy_read_timeout` ≥ 120 s. The 20 s agent heartbeat keeps connections inside every common idle timeout (Cloudflare's is 100 s if a CDN is ever put in front).
- A dedicated host name (`ws.<domain>`) keeps websocket routing and limits separate from ingest.
- Scaling past one process: Reverb's Redis-backed scaling (`REVERB_SCALING_ENABLED`) on the same Redis; not needed at MVP scale.
- Failure mode: agents fall back to long polling (ADR-010 §5) and browsers to Inertia reloads, so a Reverb outage degrades latency, not correctness. The health check and an alert on Reverb restarts are still required. Capacity figures wait for A08's spike.

**H-4 · Docker Compose.** §3: one profile, services app, reverb, horizon (default + broadcasts + maintenance), scheduler, runner (engine, `replay` work), postgres, redis/valkey, proxy, optional backup. Health checks for DB, Redis, Reverb, queue lag (§3.1). The "minimal" profile is not shipped.

**H-5 · Engine binary packaging.**
- One release pipeline builds the Go engine for linux/amd64 and linux/arm64 and the PHP image from the same tag.
- App image: multi-stage build copies the binary into `/usr/local/bin/webhooktrap` for `plan --json` and `verify --json` (network-free calls).
- Runner image: `distroless/static` (or `scratch` + CA certificates) + the binary, non-root UID, read-only root filesystem, `cap_drop: [ALL]`, `no-new-privileges`, `pids_limit`, memory 256 MB, CPU 1.
- Version pinning: the app reads `webhooktrap version --json` at boot and refuses to start on a mismatch with the version it was built for; the runner reports its version in hello (ADR-010 §8), so a mismatched runner is refused by protocol.
- Checksums of the binary are verified in the build; release signing is A09's (S-12).

**H-6 · Proxy configuration.**
- **Raw request target.** Caddy and FrankenPHP pass Go's request target; nginx passes `$request_uri` unchanged when `proxy_pass` has no URI part and FPM gets `fastcgi_param REQUEST_URI $request_uri`. Neither statement is trusted on its own: a **conformance test** runs in CI against the real Compose proxy chain and asserts byte equality of `raw_target` for a corpus: `%2F` vs `%2f`, `%7E`, `+`, `%20`, `//a//b`, `/./`, `/../`, `;param`, repeated keys `a=1&a=2&a=`, empty query `?`, `#` absent, non-ASCII percent-encoded, 8 KiB query. Any hosted vendor edge (options C, D, or a CDN) must pass the same test before it is put in front of ingest.
- **No CDN on the capture host** for the MVP. A CDN may normalise URLs (Cloudflare has URL-normalisation settings; whether they reach the origin depends on zone settings, **unverified**) and would see webhook payloads as a third party.
- **Separate capture host** (`in.<domain>`), set as `CAPTURE_PUBLIC_ORIGIN`: no app cookies on ingest, its own body and timeout rules, and the option to move ingest later without changing the UI host.
- **Trusted proxies:** Laravel `TrustProxies` limited to the Compose `edge` network CIDR; `CAPTURE_TRUST_FORWARDED_ORIGIN=false` by default (S-6).
- **Body limit:** 25 MiB at the proxy → uniform 413 (Caddy `request_body max_size`; unit spelling `25MiB` to be checked against Caddy's parser); a 30 s body read timeout against slow uploads; header size limit at the proxy default.
- **Rate limiting (429 per address):** standard Caddy has no rate limiter (it needs a plugin and a custom build). Proposal: per-address limiter in Laravel middleware on Redis, before any capture-key lookup, returning 429 for every capture URL alike, plus an nftables connection limit on the host for floods. A05/A09 to confirm this counts as "edge" for ADR-005 am. 1 §4.
- **No 5xx leak:** during deploys the proxy retries the upstream for a few seconds (`lb_try_duration`) so ingest returns 200 rather than 502; behaviour with request bodies **unverified** in Caddy and part of the deploy test.
- **Websockets:** no buffering, long read timeout (H-3).

**H-7 · Reference performance environment.** Hetzner CPX22 (2 vCPU AMD shared, 4 GB, 80 GB NVMe) running the reference Compose stack: €19.99/month in NBG1 or €16.99 in HEL1, billed hourly, so it exists only during perf runs (a few euros per month). The load generator runs on a separate VM in the same location (CPX32) so it does not steal CPU. Shared vCPU varies between runs: each PERF run records the host CPU steal time, and a target missed with steal > 5 % is re-run before being reported (PERF-9 already says "reported, not failed" elsewhere). Provisioning it needs a PO-approved account (§5).

**H-8 · PostgreSQL version.** **PostgreSQL 18** (native `uuidv7()`, released September 2025, so past its early minor releases by launch). Settings: `default_toast_compression = lz4` (cheaper body storage), autovacuum tuned for the daily batch deletes on `captures`/`capture_bodies` (lower `autovacuum_vacuum_scale_factor` on those tables), `statement_timeout` for web requests. Suggestion to A05: if capture volume grows, monthly partitioning of `captures`/`capture_bodies` turns retention into `DROP PARTITION`. For option B/C/D, PG 18 availability at the vendor is **unverified**; PG 17 with an app-generated UUIDv7 is the fallback.

**H-9 · Agent distribution.**
- **GitHub Releases:** archives per OS/arch built by GoReleaser, `checksums.txt`, signatures (method by A09, S-12).
- **Homebrew:** a tap repository (`homebrew-tap`) updated by GoReleaser.
- **Install script:** a static `install.sh` served from the main domain (or GitHub Pages), which downloads from GitHub Releases and verifies the checksum and signature before installing.
- **npm:** `@webhooktrap/agent` with per-platform packages as `optionalDependencies` (the esbuild pattern), so no postinstall download is needed: works with `--ignore-scripts`, offline mirrors and npm's integrity check. This needs the `@webhooktrap` npm scope, which the PO must create or approve (account action, not done here).
- Cost: zero (GitHub and npm free tiers). Windows (Scoop/winget) after the MVP.

---

## 5. Recommendation

**Option A: one EU VPS running the self-host Compose stack, the engine runner on a separate small VM, encrypted off-box backups with 7-day PITR.** Start on CPX22 (€33.68/month all in, Germany) for the beta and move to CPX32 (€52.88) before public launch or when PERF tests show the margin is thin.

Reasons:

1. **It is the self-host product.** ADR-008 makes Compose the primary distribution; hosting on it means every hosted day tests what self-hosters run (HR-13). Options C and D would need a second deployment path.
2. **Every hard requirement is under our control.** Raw request target (HR-5), 25 MiB limit with uniform responses (HR-6), and egress control (HR-4) depend on hops we configure and test. In C and D, three of these are unverified vendor behaviours.
3. **Cost:** €34–53 against ≈ $81 (D) and ≈ $148 (C) at MVP scale.
4. **The team already runs a VPS** for 1.x (Caddy + process manager + cron), so the skills exist.
5. **The runner on its own VM** isolates SSRF and abuse risk by IP and kernel, whatever option is picked.

What would change it:

| If… | Then… |
| --- | ----- |
| The PO does not want to own Postgres backups and restores | **B**: same VPS, managed Postgres in Frankfurt (+$30–47/month) |
| Sustained load exceeds one 8 GB box (about 3× PERF-1), or ingest must survive a host failure | **B** first (DB off the box), then a second app VPS behind a load balancer |
| Nobody can be on call for the OS and the box | **C**, after the raw-target and body-limit conformance tests pass through Laravel Cloud's edge |
| A legal requirement for US data location | Same option A in Hetzner US (prices are 2–3× EU: CPX21 3 vCPU/4 GB €32.49) or another US VPS provider |
| Hetzner keeps shared plans scarce or blocks the runner IP | Same Compose on another EU VPS provider; the runner VM moves independently |
| A08's spike shows Reverb or PHP needs more than 4 vCPU at PERF-1 | Size up, or split Reverb onto its own VM |

### Decisions the PO must take

| # | Decision | Recommendation |
| - | -------- | -------------- |
| HD-1 | Hosting option | **A** (one VPS + runner VM), with B as the planned next step |
| HD-2 | Data location | **EU** (Germany or Finland) |
| HD-3 | Provider accounts: who creates them and when (a **new** Hetzner project separate from anything 1.x uses; backup target; npm scope `@webhooktrap`; email provider) | PO creates or approves each account when Phase 4 implementation starts; A10 provisions only after that approval |
| HD-4 | Backup policy for hosted: retention and disclosure | 7-day PITR, 14-day encrypted daily backups, off the box in the EU; stated in the privacy notice as "deleted data leaves backups within 14 days" |
| HD-5 | Runner placement: runner as a remote agent on its own VM (needs A05/A09 agreement since ADR-006 am. 1 describes an in-process call) | **Accept**, send to A05/A09 |
| HD-6 | Monthly hosting budget ceiling for the MVP | €60/month for A (leaves room for CPX32 + email) |
| HD-7 | Domain plan: 2.0 domain vs 1.x domain (decision 32 §5 keeps 1.x on its own domain), and a separate capture host | Separate capture host (`in.` subdomain); domain assignment by the PO |
| HD-8 | Redis or Valkey image in the Compose file | **Valkey 8** (BSD licence, drop-in) |
| HD-9 | Error tracking: SaaS with scrubbing, or self-hosted | Self-hosted GlitchTip or a SaaS with request bodies disabled; PO to choose based on who may see stack traces |
| HD-10 | Hosted server-side runs (P6) at launch, given the abuse exposure | **Yes, behind a kill switch**, with A09's S-9 limits and an abuse contact page |

---

## 6. Sources (read 2026-11-14)

- Hetzner Cloud, Regular Performance plans and backups: https://www.hetzner.com/cloud/regular-performance
- Hetzner Cloud, Cost-Optimized plans (shown as unavailable): https://www.hetzner.com/cloud/cost-optimized
- Hetzner Storage Box: https://www.hetzner.com/storage/storage-box
- Laravel Cloud pricing: https://laravel.com/cloud/pricing and plans/resources: https://laravel.com/cloud/docs/pricing
- Laravel Forge pricing (reference for a Forge + VPS variant of A/B: $12–19/month on top of the VPS): https://laravel.com/forge/pricing
- Fly.io pricing: https://docs.fly.io/about/pricing
- DigitalOcean Managed Databases: https://www.digitalocean.com/pricing/managed-databases
- Neon pricing (page dated 2026-11-04): https://neon.com/pricing
- Render (third-party summaries only, unverified): https://render.com/pricing.md, https://render.com/blog/lower-prices-for-key-value-services
- 1.x hosting note (read only): `.docs/10-hebergement.md`

Forge is not a separate option: it is a management layer over A or B ($12 Hobby, $19 Growth per month). It would replace our Compose-based deploy with Forge's provisioning, which conflicts with HR-13; worth it only if the PO prefers a dashboard to Compose.
