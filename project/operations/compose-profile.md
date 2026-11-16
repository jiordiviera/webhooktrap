# Compose profile · Webhook Trap 2.0

**Status:** DRAFT, paper only · **Author:** A10 (DevOps) · **Date:** 2026-11-15 (rev. 15)
**Scope:** decision 32 §4 (one self-host profile, Redis-compatible store required, no minimal profile), decision 43 (template `jiordiviera/laravel-inertia-vps-template`), HD-4, HD-5, HD-8, HD-10, ADR-008.
**Derived from:** the template's `compose.production.yaml` (read with `gh api` on 2026-11-14) plus Reverb, Valkey, Postgres + pgBackRest (the private hosted deployment plan §5, option A), healthchecks and limits.
**Rev. 3:** A09 re-check §12.5: SV-2 (proof header and in-image trust, `APP_PORT`, set in the private plan), RD-1 (no share tokens in logs), CI-2 (policy the file must satisfy, §2.2), ENG-1 (`engine` sandbox service). Change table at the end.
**Rev. 4:** applies A09's verification (`security/threat-model.md` §12.9): SV-2 (Caddyfile order and fail-closed rewritten, §2.1), CI-2 (limits now in the YAML, §2, and the new volume/config/secret rules, §2.2), RD-1 (log filter also redacts capture keys and `X-Wt-Edge`, §2.1), ENG-2 (engine statelessness, §2). Change table at the end.
**Rev. 5:** applies A09's verification (`security/threat-model.md` §12.10): SV-2a (every underscore spelling of the forwarded headers stripped on every request, not only unverified ones, §2.1), RN-1a (image Caddyfile refuses `.php/` path info as a second guard for the runner API path, §2.1). Change table at the end.
**Rev. 6:** applies A09's verification (`security/threat-model.md` §12.11): DL-3 (ledger volume, off-server copy by `backup`, `restore.pending` guard in the entrypoint, restore order; §2 notes, §7; runbook in the private hosted deployment plan §5.1), the argon2id memory budget (§3.1), the failed-sign-in alert fields (§6), `/set-password/{token}` in the log-scrubbing note (§2.1). Change table at the end.
**Rev. 7:** applies decision 50 and A08's live-props spike (`spikes/live-props/SPIKE-REPORT.md` §6, C-7, C-8): Reverb image with `ext-uv` or `ext-event` and a boot check, `nofile` set and checked, connection limits, long-poll caps and PHP worker sizing, client events `none` (new §3.2, §5, §6). Change table at the end.
**Rev. 8 (2026-11-15):** applies A09's slice 3 review (`security/slice3-review.md` S3-3, S3-4, decision 2 and decision 4, S3-5 follow-up): a separate `ingest` service runs the capture path on its own FrankenPHP process with `enable_post_data_reading=Off`, its own thread pool and a 15 s body timeout, reached only through `app`'s Caddy over a hop re-signed with the edge secret (new §2.3); `PROXY_TRUST_MODE` set explicitly in both profiles; the fail-open store alert wired to A06's `wt.store_failure` line (§6). The per-address connection cap and the backstop rate limit need a module or another proxy and are proposals for the PO (§2.3, the private hosted deployment plan §4.2, O5). Change table at the end.
**Rev. 14 (2026-11-15, step C2):** the hosted box is larger than the 8 GB reference, so the hosted file carries the larger column (§3); real backup tooling with a local WAL queue, so `postgres` needs no network and no backup secret (§7); Postgres 18 volume path fixed; `db_password` separate from `postgres_password`; secret file owners (§5). Change table at the end.
**Rev. 13 (2026-11-15):** every app secret reaches containers as a file (CLAUDE.md): Compose secrets mounted only on the services that read them, loaded by the entrypoint from `/run/secrets`; `.env.production` holds no secret any more (§5). Change table at the end.
**Rev. 12 (2026-11-15):** agent follow-ups (A06 `13f7443`, `0a088b4`): `AGENT_PAIRING_HMAC_KEY` as a file secret on `app`, the front's FrankenPHP `num_threads` pinned (`APP_NUM_THREADS`, 8) and passed to the app as `LONGPOLL_HTTP_WORKERS`, `AGENT_ALLOW_DEV_BUILDS` refused outside local/testing, and the database must stay session-pooled for the long-poll advisory locks (§3.2). Change table at the end.
**Rev. 11 (2026-11-15):** Horizon serves every queue the app uses (`default`, `broadcasts`, `maintenance`); before, only `default` had a worker, so live updates and maintenance jobs never ran. New §3.4, `horizon` at 448m, `retry_after` 330 s. Change table at the end.
**Rev. 10 (2026-11-15):** applies A09's runtime and HAProxy review (`security/slice3-review.md`, "Runtime and HAProxy (rev 2)", S3-17 to S3-24): a capped class for partial bodies and `INGEST_NUM_THREADS` 8 (§2.4), transparent ingest recreate through HAProxy, fixed JSON answers for every HAProxy status and a server timeout above ingest's worst case, the edge secret only in `app`, `ingest` and `haproxy` (`WT_ROLE`), a whole-file secret check, HAProxy measured and set to 128m; also the FrankenPHP file capability removed from the image (without it, `app` and `ingest` cannot start under `cap_drop: [ALL]`). Change table at the end.
**Rev. 9 (2026-11-15):** applies decision 61 (O5): an official HAProxy LTS service (`haproxy`) between `app` and `ingest` in both profiles, with a per-address cap on captures in flight, a backstop rate limit, the app's own `429`, and small slow bodies held in HAProxy instead of PHP (new §2.4); its config ships in the signed app image and a one-shot `haproxy-config` copies it out; resource table and policy rules updated (§3, `ci-cd.md` §4.2). Change table at the end.
**Not final:** env var names are proposals for A05 to align with Laravel config. **Names only, never values.** Image digests are placeholders filled by the release pipeline.

---

## 1. Files in the 2.0 repository

| File | From the template | Used by | Content |
| ---- | ----------------- | ------- | ------- |
| `compose.production.yaml` | `compose.production.yaml` | hosted service only | §2. Image by commit SHA (digest), app on `127.0.0.1:${APP_PORT}`, no runner, limits for the shared VPS |
| `compose.yaml` | new (derived from §2) | self-hosters: `docker compose up -d` | §4. The **single** self-host profile: same services + runner + one-shot `migrate`, FrankenPHP terminates TLS itself or sits behind the operator's proxy |
| `compose.dev.yaml` | template's `compose.yaml`, renamed | contributors | builds locally; Postgres and Valkey included so no host database is needed |
| `docker/Caddyfile` | new (the template uses FrankenPHP's default) | baked into the app image | routes by host; 25 MiB on the capture host; `ws.` → Reverb |
| `deploy/policy/compose-policy.py` | new | the VPS only (installed at C3, **not** shipped in the image) | the Compose policy `deploy.sh` enforces (`ci-cd.md` §4.2) |
| `docker/php.ini` | new | baked into the app image | `post_max_size` just above 25 MiB, `memory_limit`, `expose_php=Off` |
| `docker/entrypoint.sh` | template, extended | app image | template checks (`APP_KEY`, `optimize`) + required-variable check that names the missing variable + engine version check (H-5) |
| `docker/postgres/Dockerfile` | new | `postgres` and `backup` | official `postgres:18.x` + `pgbackrest`; scripts for scheduled backup, verify and status file |
| `.env.production.example`, `.env.example` | template | operators | names and comments only |

The template's rename is needed because self-hosters expect `compose.yaml` to be the thing they run.

---

## 2. `compose.production.yaml` (hosted)

Placeholders: `<subnet-…>` chosen from F-5; limits from §3. Every value in `${…}` comes from `.env.deploy` (image, tag, port) or is a name only.

```yaml
name: webhooktrap                      # pinned: --remove-orphans can never touch another project

x-logging: &logging
  driver: json-file
  options: { max-size: "10m", max-file: "3" }

x-hardening: &hardening
  restart: unless-stopped
  init: true
  security_opt: ["no-new-privileges:true"]
  cap_drop: [ALL]
  cgroup_parent: webhooktrap.slice    # aggregate ceiling (hosted deployment plan §6.1); drop if Docker uses cgroupfs
  logging: *logging

x-app: &app
  <<: *hardening
  image: ${APP_IMAGE:?Set APP_IMAGE}@${APP_DIGEST:?Set APP_DIGEST}   # digest of the signed SHA tag
  env_file: [.env.production]
  environment:
    PROXY_TRUST_MODE: secret          # rev. 8 (S3-5): explicit; the app's own default is `none`
  # rev. 10 (S3-22): no edge secret here; only app, ingest and haproxy get it
  read_only: true
  tmpfs: ["/tmp:size=64m", "/app/storage/framework/cache:size=64m"]
  volumes:
    - storage:/app/storage/app        # only if A05 keeps any local file; else removed
    - backup_status:/status:ro        # DL-3c: the entrypoint refuses to start while /status/restore.pending exists
  networks: [internal]
  depends_on:
    postgres: { condition: service_healthy }
    valkey:   { condition: service_healthy }
  oom_score_adj: 500

services:
  app:
    <<: *app
    networks: [edge, internal, egress]  # egress: mail, OAuth
    ports: ["127.0.0.1:${APP_PORT:?Set APP_PORT}:8080"]   # hosted port from the private plan; loopback only, policy P-6
    environment:
      PROXY_TRUST_MODE: secret
      WT_ROLE: app                      # rev. 10: the entrypoint checks the edge secret in app and ingest only
      INGEST_MODE: proxy                # rev. 8: capture path handed on (§2.3)
      INGEST_UPSTREAM: haproxy:8080     # rev. 9: through the per-address limits (§2.4)
      APP_NUM_THREADS: ${APP_NUM_THREADS:-8}          # rev. 12: the front's FrankenPHP pool (8 GB: 8, 16 GB: 12)
      LONGPOLL_HTTP_WORKERS: ${APP_NUM_THREADS:-8}    # rev. 12: always the same value (§3.2)
    secrets: [app_key, postgres_password, valkey_password, reverb_app_secret, github_client_secret, google_client_secret, error_tracker_dsn, identity_email_hmac_key, identity_recovery_code_key, agent_pairing_hmac_key, edge_proxy_secret]   # rev. 13: per-service list, §5
    mem_limit: 768m                     # CI-2/P-8: every service carries mem_limit, memswap_limit, cpus, pids_limit (8 GB column of §3; 16 GB values in §3)
    memswap_limit: 768m
    cpus: 1.0
    pids_limit: 256
    volumes: [storage:/app/storage/app, engine_sock:/run/engine, backup_status:/status:ro]   # engine socket (ENG-1); restore guard (DL-3c)
    group_add: ["10001"]                # may use the engine's socket
    depends_on:
      postgres: { condition: service_healthy }
      valkey:   { condition: service_healthy }
      reverb:   { condition: service_healthy }
      engine:   { condition: service_healthy }
      haproxy:  { condition: service_healthy }  # rev. 9 (it waits for ingest)
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://127.0.0.1:8080/up"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 20s

  haproxy:                              # rev. 9, decision 61: per-address limits before ingest (§2.4)
    <<: *hardening
    image: haproxy:3.4.7-alpine@sha256:b9fe316f718d5015c5cfebd8ca019591f3e44656f61d93cb8b59a1028961e530
    command: ["haproxy", "-f", "/wt-haproxy/haproxy.cfg"]
    labels: { webhooktrap.config-from: "${APP_DIGEST}" }   # recreated with every app image, so it reloads the config
    environment:                        # rev. 10: Compose's `:-` also covers an empty value (S3-24)
      WT_CAPTURE_MAX_CONN: ${WT_CAPTURE_MAX_CONN:-20}
      WT_CAPTURE_MAX_RATE: ${WT_CAPTURE_MAX_RATE:-1200}
      WT_CAPTURE_MAX_STREAM: ${WT_CAPTURE_MAX_STREAM:-2}
      WT_CAPTURE_STREAM_SLOTS: ${WT_CAPTURE_STREAM_SLOTS:-6}   # INGEST_NUM_THREADS - 2
    secrets: [edge_proxy_secret]        # trusts X-Forwarded-For only on hops signed by `app`
    group_add: ["33"]                   # the secret file is root:www-data 0440
    read_only: true
    volumes: [haproxy_config:/wt-haproxy:ro]
    networks: [internal]
    depends_on:
      haproxy-config: { condition: service_completed_successfully }
      ingest:         { condition: service_healthy }
    mem_limit: 128m                     # rev. 10: measured 79 MiB peak (§2.4)
    memswap_limit: 128m
    cpus: 0.25
    pids_limit: 64
    oom_score_adj: 500
    healthcheck:
      test: ["CMD", "wget", "-q", "-O", "/dev/null", "http://127.0.0.1:8405/"]

  haproxy-config:                       # rev. 9: one-shot, copies docker/haproxy.cfg out of the signed app image
    <<: *hardening
    image: ${APP_IMAGE}@${APP_DIGEST}
    entrypoint: ["cp", "/etc/webhooktrap/haproxy.cfg", "/etc/webhooktrap/received-200.http", "/wt-haproxy/"]
    user: "0:0"                         # owner of the fresh volume; no capability, no network
    restart: "no"
    network_mode: none
    read_only: true
    volumes: [haproxy_config:/wt-haproxy]
    mem_limit: 32m
    memswap_limit: 32m
    cpus: 0.1
    pids_limit: 16
    oom_score_adj: 500

  ingest:                               # rev. 8 (A09 S3-3, S3-4): capture path on its own PHP process (§2.3)
    <<: *app
    command: ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile.ingest", "--adapter", "caddyfile"]
    environment:
      PROXY_TRUST_MODE: secret          # always: its only caller is `app`, which signs with the edge secret
      WT_ROLE: ingest                   # explicit (P-17); the entrypoint refuses any other trust mode for this role
    secrets: [edge_proxy_secret]        # rev. 10: explicit (no longer in the anchor)
    networks: [internal]                # not published, no edge, no internet
    mem_limit: 384m
    memswap_limit: 384m
    cpus: 0.5
    pids_limit: 128
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://127.0.0.1:8080/up"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 20s

  reverb:
    <<: *app
    command: ["/usr/local/bin/reverb-boot"]   # rev. 7 (decision 50): boot check, then `exec php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction` (§3.2)
    networks: [edge, internal]
    ulimits: { nofile: { soft: 10000, hard: 10000 } }
    mem_limit: 256m
    memswap_limit: 256m
    cpus: 0.5
    pids_limit: 256
    healthcheck:
      test: ["CMD", "php", "-r", "exit(@fsockopen('127.0.0.1', 8080) ? 0 : 1);"]
      interval: 15s
      timeout: 5s
      retries: 5

  horizon:                              # replaces the template's `queue` (queue:work)
    <<: *app
    command: ["php", "artisan", "horizon"]
    volumes: [storage:/app/storage/app, backup_status:/status:ro, ledger:/ledger]   # DL-3a: the only service that writes the deletion ledger (purge job)
    networks: [internal, egress]        # sends mail
    stop_grace_period: 60s
    mem_limit: 384m
    memswap_limit: 384m
    cpus: 0.5
    pids_limit: 256
    healthcheck:
      test: ["CMD", "php", "artisan", "horizon:status"]
      interval: 30s
      timeout: 10s
      retries: 3

  scheduler:
    <<: *app
    command: ["php", "artisan", "schedule:work"]
    mem_limit: 128m
    memswap_limit: 128m
    cpus: 0.25
    pids_limit: 128
    healthcheck:                        # heartbeat written by a scheduled task every minute
      test: ["CMD", "php", "artisan", "app:heartbeat-check", "--max-age=120"]
      interval: 60s
      timeout: 10s
      retries: 3

  engine:                               # ENG-1: sandbox for `plan` and `verify` (ADR-010 §1 rule 2)
    <<: *hardening
    image: ${APP_IMAGE:?Set APP_IMAGE}@${APP_DIGEST:?Set APP_DIGEST}   # same signed image; the engine is /usr/local/bin/webhooktrap (H-5)
    entrypoint: ["webhooktrap"]
    command: ["serve-sandbox", "--socket=/run/engine/engine.sock"]   # subcommand name proposed; A05/A08 confirm
    network_mode: none                  # no interface but loopback: nothing to reach, nowhere to send
    read_only: true
    tmpfs: ["/tmp:size=32m"]
    user: "10001:10001"
    # no env_file, no secrets, no DB or Valkey variables, no storage volume: the process holds nothing to steal
    volumes: [engine_sock:/run/engine]
    mem_limit: 128m
    memswap_limit: 128m
    cpus: 0.25
    pids_limit: 128
    oom_score_adj: 500
    healthcheck:
      test: ["CMD", "webhooktrap", "sandbox-ping", "--socket=/run/engine/engine.sock"]
      interval: 30s
      timeout: 5s
      retries: 3

  valkey:                               # HD-8, replaces the template's redis:7-alpine
    <<: *hardening
    image: valkey/valkey:8.x-alpine@sha256:<digest>
    command: ["sh", "-c", "exec valkey-server --requirepass \"$$(cat /run/secrets/valkey_password)\" --appendonly yes --appendfsync everysec --maxmemory $${VALKEY_MAXMEMORY} --maxmemory-policy noeviction"]
    environment: [VALKEY_MAXMEMORY]
    secrets: [valkey_password]
    cap_add: [SETUID, SETGID]           # official image drops to its own user at start
    volumes: [valkeydata:/data]
    networks: [internal]
    mem_limit: 256m
    memswap_limit: 256m
    cpus: 0.25
    pids_limit: 64
    oom_score_adj: 300
    healthcheck:
      test: ["CMD-SHELL", "REDISCLI_AUTH=\"$$(cat /run/secrets/valkey_password)\" valkey-cli ping | grep -q PONG"]
      interval: 10s
      timeout: 5s
      retries: 5

  postgres:
    <<: *hardening
    image: ${PG_IMAGE:?Set PG_IMAGE}@${PG_DIGEST:?Set PG_DIGEST}       # postgres:18.x + pgbackrest
    environment: [POSTGRES_DB, POSTGRES_USER, POSTGRES_PASSWORD_FILE]
    secrets: [postgres_password, backup_cipher_pass, backup_s3_key, backup_s3_secret]
    cap_add: [CHOWN, DAC_OVERRIDE, FOWNER, SETUID, SETGID]   # entrypoint needs these; reviewed at implementation
    command:
      - postgres
      - -c
      - shared_buffers=${PG_SHARED_BUFFERS}
      - -c
      - effective_cache_size=${PG_EFFECTIVE_CACHE_SIZE}
      - -c
      - default_toast_compression=lz4
      - -c
      - archive_mode=on
      - -c
      - archive_command=pgbackrest --stanza=webhooktrap archive-push %p
      - -c
      - log_min_duration_statement=500ms
      - -c
      - log_parameter_max_length=0
    shm_size: 256m
    volumes: [pgdata:/var/lib/postgresql/data, pgsocket:/var/run/postgresql, pgbackrest_spool:/var/spool/pgbackrest]
    networks: [internal]
    stop_grace_period: 60s
    mem_limit: 1200m
    memswap_limit: 1200m
    cpus: 1.0
    pids_limit: 200
    oom_score_adj: 0
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U \"$$POSTGRES_USER\" -d \"$$POSTGRES_DB\""]
      interval: 10s
      timeout: 5s
      retries: 10

  backup:
    <<: *hardening
    image: ${PG_IMAGE}@${PG_DIGEST}
    command: ["backup-loop"]            # nightly full, weekly verify, status file; pgBackRest config from env
    environment: [BACKUP_REPO_TYPE, BACKUP_S3_ENDPOINT, BACKUP_S3_REGION, BACKUP_S3_BUCKET, BACKUP_RETENTION_FULL, BACKUP_RETENTION_ARCHIVE_DAYS, BACKUP_FULL_SCHEDULE, LEDGER_AGE_RECIPIENT, LEDGER_RETENTION_DAYS, LEDGER_SHIP_INTERVAL_SECONDS, LEDGER_S3_PREFIX]
    secrets: [backup_cipher_pass, backup_s3_key, backup_s3_secret]
    user: postgres
    volumes: [pgdata:/var/lib/postgresql/data:ro, pgsocket:/var/run/postgresql, pgbackrest_spool:/var/spool/pgbackrest, backup_status:/status, ledger:/ledger:ro]
    networks: [internal, backup_egress]
    depends_on: { postgres: { condition: service_healthy } }
    mem_limit: 256m
    memswap_limit: 256m
    cpus: 0.25
    pids_limit: 128
    oom_score_adj: 300
    healthcheck:
      test: ["CMD", "backup-status", "--max-full-age=26h", "--max-archive-lag=15m"]
      interval: 5m
      timeout: 30s
      retries: 1

# No top-level `configs:` block and no `driver`/`driver_opts` on any volume (policy P-5, CI-2).
secrets:                                # files under $APP_DIR/secrets; owners in §5
  valkey_password:    { file: ./secrets/valkey_password }
  postgres_password:  { file: ./secrets/postgres_password }
  backup_cipher_pass: { file: ./secrets/backup_cipher_pass }
  backup_s3_key:      { file: ./secrets/backup_s3_key }
  backup_s3_secret:   { file: ./secrets/backup_s3_secret }
  edge_proxy_secret:  { file: ./secrets/edge_proxy_secret }    # shared with the host proxy vhost (hosted deployment plan §3.4)
  agent_pairing_hmac_key: { file: ./secrets/agent_pairing_hmac_key }   # rev. 12: 32+ random bytes, never APP_KEY; root:33 0440

networks:
  edge:          { ipam: { config: [{ subnet: "<subnet-edge>" }] } }      # no IP-based trust; the proxy proves itself with X-WT-Edge (SV-2)
  internal:      { internal: true, ipam: { config: [{ subnet: "<subnet-internal>" }] } }
  egress:        {}
  backup_egress: {}

volumes:                                # plain named volumes only: no `driver`, no `driver_opts`, no `external`
  storage:
  haproxy_config:                       # rev. 9: docker/haproxy.cfg, rewritten by `haproxy-config` on every up
  valkeydata:
  pgdata:
  pgsocket:
  pgbackrest_spool:
  backup_status:
  ledger:                               # DL-3: deletion ledger (usr_ id + deleted_at), outside the database; horizon writes, backup ships it off-server
  engine_sock:                          # unix socket app <-> engine (ENG-1)
```

Notes:
- **Rev. 10: FrankenPHP's file capability is removed in the image** (`setcap -r`, checked with `getcap` in the build). The base image gives the binary `cap_net_bind_service=ep`; under `cap_drop: [ALL]` with `no-new-privileges` the kernel refuses to exec such a binary (`exec: frankenphp: Operation not permitted`, seen in the rev. 10 run), so `app` and `ingest` could not have started in either profile. The image listens on 8080/8443 only. Hosted `/data` tmpfs is owned by uid/gid 33 so Caddy can write its locks.
- `app`, `reverb`, `horizon`, `scheduler` share one image (template pattern). Reverb is the only addition that listens; it is reached by FrankenPHP over `edge`, never published (hosted deployment plan §3.2).
- **Deletion ledger and restore guard (rev. 6, DL-3, runbook in the private hosted deployment plan §5.1).** `ledger` is a plain named volume (no `driver`, policy P-5): `horizon` mounts it writable, `backup` read-only (rev. 16: the restore applies it in `horizon`, the only app-image service that mounts it). `backup-loop` ships it every `LEDGER_SHIP_INTERVAL_SECONDS` to the backup repository's bucket under `LEDGER_S3_PREFIX` (default `ledger/`) with `age -r $LEDGER_AGE_RECIPIENT` (the image gains the `age` and `rclone` packages; the recipient is public, so there is no new secret, and the S3 key and secret are the existing ones). Self-host with a `posix` repository copies to `<repo>/ledger/` instead, with the same loud note as the repository. The `backup` image gains the subcommands `restore` (writes `/status/restore.pending`), `ledger-fetch` (rev. 16: decrypts the newest copy with the PO's age identity read from stdin or `--identity=FILE`, merges it with the local `/ledger/deletions.jsonl` (identical lines once, order kept, JSON-object lines only) into `/status/ledger-merged.jsonl`, 0644, and prints line counts only), `restore-finish` (also removes the merged file); the other services mount `backup_status` read-only. The first line of the app image entrypoint (all of `app`, `reverb`, `horizon`, `scheduler`) exits non-zero while `/status/restore.pending` exists. The apply step is `docker compose run --rm --no-deps --entrypoint php horizon artisan accounts:apply-ledger --file=/status/ledger-merged.jsonl` (rev. 16: `horizon`, which mounts `backup_status` and `ledger`; `app` mounts neither ledger nor the merged file's path for writing, and the default `/ledger/deletions.jsonl` did not exist there), which adds no service.
- The template's `env_file: .env.production` is kept for non-secret settings; secrets move to files (`*_FILE` names, §5) so they do not appear in `docker inspect`.
- `read_only: true` on the app family is a hardening step beyond the template; A05 confirms which paths Laravel writes (views cache, logs → stderr).
- `engine` (ENG-1) is the concrete sandbox for `plan`/`verify` that A09 asked for (TM-149): the hardened app container cannot build a network-free sandbox inside itself (`cap_drop: ALL`, `no-new-privileges`) and holds `/run/secrets/*`. The engine runs in its own container with `network_mode: none`, no secrets, no database variables and no storage, and is called over a Unix socket in a shared volume. Inputs, and for instance-secret `verify` the one secret needed for that call, travel over the socket per request and live in the engine's memory only (A05/A08 define the protocol; the call never gives the engine a way out). Policy P-3 allows `network_mode: none` on this service only. Fallback if A08's spike rejects the socket design: Landlock network rules inside the engine (needs a kernel with Landlock ABI 4 or later; unverified on this VPS). **ENG-1 is settled before instance-secret `verify` ships on self-host** (and before it is enabled on hosted).
- **`engine` is stateless between calls (ENG-2).** One short-lived child per call (or a server that keeps no state from one call to the next), so a parsing fault cannot carry one principal's captures or one instance secret into another call's answer. The engine enforces the 5 s, memory and concurrency limits itself; it never logs inputs, captures or the secret (its log lines carry call id, verb, duration and outcome only); it drops the instance secret when each `verify` call returns; it exposes `plan` and `verify` only, with no probe or reset code path and no configuration file read from its working directory (as RN-7). `app` treats the engine's output as untrusted. The compose side keeps the container empty (no `env_file`, no secrets, no storage volume, `read_only`, `network_mode: none`); the statelessness itself is A05/A08's protocol and is proved by TM-T149 (no outbound socket, no state across two calls, no file read from the working directory).
- `/health` (deep check: DB, Valkey, queue lag, scheduler heartbeat, backup status, runner last poll) is for monitoring, not for Docker.
- There is no `migrate` service in production: the deploy script runs migrations once, after a pre-migrate backup (`ci-cd.md` §4), as the template does.

### 2.1 The image's Caddyfile (shared by hosted and self-host)

```caddyfile
{
	frankenphp
	http_port {$CADDY_HTTP_PORT:8080}
	https_port {$CADDY_HTTPS_PORT:8443}
	auto_https {$CADDY_AUTO_HTTPS:off}
	servers {
		import trust-global-{$PROXY_TRUST_MODE:secret}   # secret, none: nothing here; ip: trusted_proxies static {$TRUSTED_PROXIES}
		timeouts { read_body 30s read_header 10s }
	}
	log {                                              # error lines must not carry share tokens, capture keys or the proxy secret (RD-1)
		output stderr
		format filter {
			wrap console
			fields {
				request>uri regexp "^/s/[^/?#]+" "/s/REDACTED"   # share token (applied first; the next line then covers all paths)
				request>uri regexp "(?s)^.*$" "/REDACTED"        # everything else, query included: the capture key sits in the path on in.<host>, whatever its shape, so no path survives in an error line (host, status and error text stay)
				request>headers>Referer delete
				request>headers>X-Wt-Edge delete
				request>headers>X_Wt-Edge delete
				request>headers>X-Wt_Edge delete
				request>headers>X_Wt_Edge delete
			}
		}
	}
}

{$SERVER_NAME::8080} {
	# SV-2: Caddy runs `handle` before `route` (see "Order" below), so the trust snippet and every
	# `handle` block live inside ONE outer `route`. `route` keeps the order written, top to bottom.
	route {
		import trust-site-{$PROXY_TRUST_MODE:secret}   # FIRST statement of the route: who may set forwarded headers

		# RN-1a: `php_server` splits /index.php/runner/v1/x into script and PATH_INFO, which Laravel routes to the runner API;
		# a host-proxy path matcher on /runner/v1 never sees it. No route of this app uses .php/ path info: refuse it.
		@phpinfo path_regexp (?i)\.php/
		respond @phpinfo 404

		@capture host {$CAPTURE_HOST}
		@capture_path path /c /c/*               # rev. 8
		@ws host {$WS_HOST}
		@share path /s/*

		handle @ws {
			reverse_proxy reverb:8080
		}
		handle @capture {                        # rev. 8: `import ingest-{$INGEST_MODE:local}`; `proxy` hands it to ingest (§2.3)
			request_body { max_size 25MiB }      # (ingest-local: as before)
			log_skip                               # path holds the capture key
			root * /app/public
			php_server
		}
		handle @capture_path { … }               # rev. 8: same import
		handle {
			request_body { max_size {$APP_MAX_BODY:10MiB} }
			log_skip @share                        # path holds the share token (RD-1); no access log is configured anyway
			root * /app/public
			encode zstd gzip
			php_server
		}
	}
	handle_errors 413 {
		respond "Request body too large" 413
	}
}
```

**Order (cited).** Caddy's documented Caddyfile directive order (caddyserver.com/docs/caddyfile/directives, "Directive order", read 2026-11-14) lists `request_header` before the routing group `invoke`, `handle`, `handle_path`, `route`, and the responders (`respond`, `reverse_proxy`, `php_fastcgi`, `file_server`) after them; the same page says the contents of `route` ignore these rules and keep the order in which they are written. Consequences: (1) a top-level `route` sorts after `handle`, and every request here is answered inside a `handle`, so rev. 3's snippet never ran (SV-2); (2) the outer `route` above puts the trust statements, then the three `handle` blocks, in exactly the written order, so the proof is evaluated before any `php_server` or `reverse_proxy`; (3) FrankenPHP's `php_server` is registered by the `frankenphp` global option and sorts with the responders; inside the `route` it stays where it is written. Spellings are checked by `caddy validate` and the order by `caddy adapt` in CI (TM-T141 below). Not run by A10.

**Forwarded-header trust (SV-2, TM-141).** The host proxy and any other local process reach the app from the same address, so trust cannot be by IP on the hosted service. `trust-site-secret` (a snippet baked into the image next to the Caddyfile; it holds no `route` of its own, it is imported as the first statement of the outer `route` above, so its lines run in the order written):

```caddyfile
(trust-site-secret) {
	# 1. strip every spelling of the header names before anything is decided (hyphen form, then the underscore
	#    spellings PHP maps to the same HTTP_* variable: X-WT-Verified, X_WT-Verified, X-WT_Verified, X_WT_Verified)
	request_header -X-WT-Verified
	request_header -X_WT-Verified
	request_header -X-WT_Verified
	request_header -X_WT_Verified
	# 2. verified only when the proxy's secret matches AND the secret is a real 64-hex value (an empty or short
	#    file can never match, even if the placeholder expands to nothing)
	@edge {
		header X-WT-Edge {file./run/secrets/edge_proxy_secret}
		header_regexp X-WT-Edge ^[0-9a-f]{64}$
	}
	request_header @edge X-WT-Verified 1
	# 3. the secret never reaches PHP, Reverb or a capture (all spellings)
	request_header -X-WT-Edge
	request_header -X_WT-Edge
	request_header -X-WT_Edge
	request_header -X_WT_Edge
	# 4. SV-2a (rev. 5): on EVERY request, verified or not, remove all underscore spellings of the forwarded-address
	#    headers, and remove Forwarded, X-Real-IP and X-Forwarded-Port in every spelling. The host proxy overwrites
	#    only the hyphen forms of X-Forwarded-For/-Proto; Caddy, unlike nginx, forwards X_Forwarded_For untouched,
	#    so on a verified request PHP would otherwise see two values for HTTP_X_FORWARDED_FOR. Laravel then trusts
	#    only what the host proxy itself set (X-Forwarded-For, X-Forwarded-Proto).
	request_header -X_Forwarded-For
	request_header -X-Forwarded_For
	request_header -X_Forwarded_For
	request_header -X_Forwarded-Proto
	request_header -X-Forwarded_Proto
	request_header -X_Forwarded_Proto
	request_header -X_Forwarded-Host
	request_header -X-Forwarded_Host
	request_header -X_Forwarded_Host
	request_header -X-Forwarded-Port
	request_header -X_Forwarded-Port
	request_header -X-Forwarded_Port
	request_header -X_Forwarded_Port
	request_header -Forwarded
	request_header -X-Real-IP
	request_header -X_Real-IP
	request_header -X-Real_IP
	request_header -X_Real_IP
	# 5. unverified requests additionally lose the hyphen forms the host proxy would have set
	@unverified not header X-WT-Verified 1
	request_header @unverified -X-Forwarded-For
	request_header @unverified -X-Forwarded-Proto
	request_header @unverified -X-Forwarded-Host
}
```

Notes on the snippet (rev. 5, SV-2a): the underscore spellings are removed in step 4 for every request; only the hyphen forms `X-Forwarded-For`, `-Proto` and `-Host` depend on the proof (step 5), because on a verified request they are the ones the host proxy set. Caddy's `request_header -Name` deletes only that exact (canonicalised) name, which is why the underscore spellings are listed one by one; `-X-WT-Verified` and the three underscore forms are removed **before** step 2 sets the real one, so only the proxy's proof can create it. The `@unverified` matcher is evaluated when its own directive runs, after step 2, because the lines sit in one `route`. A05 adds a second line of defence in PHP: trust forwarded headers only when `X-WT-Verified` is exactly `1` and the app's own check of the proof passes (A05 may instead compare the edge secret with `hash_equals`, which also removes the constant-time question of plan §3.4).

**Fail closed on the secret (SV-2).** Rev. 10 (A09 S3-22, S3-23): the check below now runs only for `WT_ROLE` `app` and `ingest` (also when `INGEST_MODE=proxy` or the role is `ingest`), and checks the whole file: exactly 64 lowercase hex characters with at most one final newline (`docker/entrypoint.sh` §4a, §4b). Rev. 6 text: with `PROXY_TRUST_MODE=secret`, the image entrypoint runs, as the runtime user and before FrankenPHP starts:

```sh
f="${EDGE_PROXY_SECRET_FILE:-/run/secrets/edge_proxy_secret}"
if [ "${PROXY_TRUST_MODE:-secret}" = secret ]; then
  { [ -f "$f" ] && [ -r "$f" ] && [ -s "$f" ] && grep -Eq '^[0-9a-f]{64}$' "$f"; } \
    || { echo "edge proxy secret missing, unreadable or not 64 hex characters" >&2; exit 78; }
fi
```

The container then exits, `/up` never answers, and `deploy.sh` steps 8-9 roll back (nothing serves with a broken proof). Readability: Compose file secrets keep the host file's owner and mode, so a root `0600` file is unreadable by the non-root runtime user. At C3 the file is `root:<app runtime gid>` mode `0440` (A05 states the gid); the entrypoint check above is what notices a mistake. The `header_regexp` line in `@edge` is the second guard for a placeholder that expands to nothing at request time. The log lines of this check never print the value.

`trust-site-none` is the same without the `@edge` block and the `request_header @edge` line (always strip: self-host with FrankenPHP terminating TLS itself, nothing in front); `trust-site-ip` is empty and `trust-global-ip` sets `trusted_proxies static {$TRUSTED_PROXIES}` (self-host behind an operator's proxy that cannot send the header; documented as weaker, since any process or container that can reach the port can forge). The entrypoint check applies only to mode `secret`. A05: Laravel trusts forwarded headers only when `X-WT-Verified` is present (mode `secret`). Spellings (`{file.…}` trims the trailing newline, `header_regexp` with a field name, `log_skip @share`, the `filter` fields, `import` with an env placeholder) are checked by `caddy validate` in CI and proved by TM-T141 and TM-T145; they are the first thing to adjust if validation disagrees. **Rev. 6 (RD-2, A05 rev. 6):** the invite link `/set-password/{token}` needs no extra line here: the image has no access log and the error-log filter above already rewrites every URI to `/REDACTED`; the path is only added to the TM-T145 canary list (the private hosted deployment plan §4.5 items 5-6), which also runs against the image alone.

**How TM-T141 proves the order (CI `conformance` job and C8 on the real chain).** (a) Static: `caddy adapt --config docker/Caddyfile --pretty` and a script asserts that, in the server's route list, the handler that sets or deletes `X-WT-Verified` appears **before** every `reverse_proxy` and `php_server` handler (in rev. 3's layout it appeared after, which is the failure this test would have caught). (b) Behavioural, against the real image behind a CI proxy container that sends the right secret: requests that carry forged `X-WT-Verified: 1`, `X_WT_Verified: 1`, `X-WT_Verified: 1`, `X_WT-Verified: 1`, forged `X-Forwarded-For`/`X_Forwarded_For` and an `X-WT-Edge` of empty, wrong and right value, each sent **directly** to the app port, must show at a CI-only echo route of the app (enabled by `APP_ENV=ci`, returns the PHP-visible `$_SERVER` keys that start `HTTP_X_`): no `HTTP_X_WT_VERIFIED`, no forwarded value, and no `HTTP_X_WT_EDGE`; the same requests sent through the proxy container (right secret) show `HTTP_X_WT_VERIFIED=1` and the proxy's forwarded address, not the forged one. **Rev. 5 (SV-2a):** the through-the-proxy case also sends every underscore spelling of `X-Forwarded-*` and `X-Real-IP`, and `Forwarded`, with the right secret, **through the real Caddy proxy** (the CI nginx container would drop underscore names and hide the fault): the echo route must show exactly one `HTTP_X_FORWARDED_FOR` value (the proxy's) and no `HTTP_X_REAL_IP`, `HTTP_FORWARDED` or `HTTP_X_FORWARDED_PORT`. (c) Fail closed: start the image with the secret file missing, then empty, then 63 hex characters, then mode `0600` root-owned: each must exit non-zero before listening. Any of the checks failing in the `route`-less layout and passing in the `route` layout is the proof of order.

Hosted: `SERVER_NAME=:8080`, `CADDY_AUTO_HTTPS=off` (the host proxy does TLS), `PROXY_TRUST_MODE=secret`. Self-host with built-in TLS: `SERVER_NAME` = the three host names, `CADDY_AUTO_HTTPS=on`, ports 80 → 8080 and 443 → 8443 published (no extra capability needed in the container). Directive spellings (`log_skip`, `max_size` units) are checked by `caddy validate` in CI.

### 2.2 What `deploy.sh` enforces on this file (CI-2)

On the VPS the file is not trusted just because it is in the signed image: `deploy.sh` checks the resolved configuration against `deploy/policy/compose-policy.py` before `up` (`ci-cd.md` §4.2, rules P-1..P-9). In short, this file must keep: project name `webhooktrap`; exactly the eleven services above (rev. 8: `ingest`; rev. 9: `haproxy` and the one-shot `haproxy-config`; the policy rules gain them in `ci-cd.md` §4.2, for A09's review); images from the two hard-coded repositories by digest (plus `valkey/valkey` by digest); `cap_drop: [ALL]` everywhere and `cap_add` only where listed (valkey, postgres); no `privileged`, host namespaces, devices or Docker socket; bind mounts only under `$APP_DIR`; no `external` volume or network, **no volume with `driver` or `driver_opts`** (a local-driver bind of `/` is a named volume), **no top-level `configs`**, top-level `secrets` with `file` only from `$APP_DIR/secrets/`; `oom_score_adj` ≥ 0; ports only on `127.0.0.1` (`app` publishes `${APP_PORT}` → 8080); `cgroup_parent: webhooktrap.slice`, `mem_limit`, `pids_limit` on every service (the YAML in §2 now carries `mem_limit`, `memswap_limit`, `cpus` and `pids_limit` on all eleven, so it passes P-8). The resolved configuration is never printed (it inlines `.env.production`). A change that breaks a rule ships with the policy change in the same PR, reviewed by A09 and installed by the PO.

### 2.3 Ingest runtime (rev. 8, A09 slice 3 S3-3, S3-4)

**Why a second process.** `enable_post_data_reading` is a per-directory INI setting: it cannot be switched per request, and turning it off for the whole app would empty `$_POST` and `$_FILES` for the app's own forms. FrankenPHP has one thread pool per process (a separate pool per route exists only for worker scripts, which this app does not use). Both findings therefore need the capture path in a process of its own. It is the same image, run with `docker/Caddyfile.ingest`, as the `ingest` service in **both** profiles. Self-hosters configure nothing: `compose.yaml` wires it.

**Path of a capture request.**

```
client ─▶ (host proxy, hosted only) ─▶ app: Caddy, trust snippet (SV-2) ─▶ [rev. 9: haproxy, §2.4] ─▶ ingest: Caddy ─▶ PHP (own pool)
                                        @capture host {$CAPTURE_HOST}             trust-site-secret
                                        @capture_path path /c /c/*                enable_post_data_reading Off
                                        reverse_proxy ingest:8080                 read_body 15s, 25 MiB, 413 JSON
```

- **Rev. 15, WebSockets on the app origin (decision 62):** the browser opens `wss://<app host>/app/<key>`, not the `ws.` host (`resources/js/lib/realtime/echo.ts`). So the front sends a WebSocket upgrade on `/app/*` to Reverb as well. A plain request to `/app/...` and Reverb's HTTP API (`/apps/...`) are not routed there; this was tested with a stand-in Reverb.
- `docker/Caddyfile` (front): with `INGEST_MODE=proxy` the capture host and every `/c`, `/c/…` path go to `{$INGEST_UPSTREAM:ingest:8080}`; everything else stays in the front's own PHP. With `INGEST_MODE=local` (default, the image run alone) the front serves capture itself, as before. The hop is placed **after** the trust snippet, so the TM-T141 order assertion is unchanged (checked with `caddy adapt`: the `X-WT-Verified` handler precedes every `reverse_proxy` and `php` handler).
- **The hop is re-signed.** The front sets `X-WT-Edge` from `/run/secrets/edge_proxy_secret` on every request to ingest, whatever its own `PROXY_TRUST_MODE`, and sends exactly one `X-Forwarded-For` and one `X-Forwarded-Proto`: the host proxy's values on a verified request in mode `secret`; the TCP peer and the front's scheme otherwise (mode `none`, or unverified); Caddy's `{client_ip}` under `trusted_proxies_strict` (right-most untrusted, the same entry the app reads) and the trusted proxy's scheme in mode `ip`. Ingest always runs `trust-site-secret`, so a container on `internal` that calls ingest without the secret is an ordinary untrusted client. The entrypoint refuses `WT_ROLE=ingest` in any mode but `secret`, and refuses `INGEST_MODE=proxy` without a 64-hex secret file.
- **Fidelity through the hop.** Host and request target pass unchanged. The front removes Caddy's own `X-Forwarded-Host` and `X-WT-Verified` from the hop, and restores the client's own `Via` (Caddy appends `1.1 Caddy` to it): a capture shows the client's `Via` exactly, or none. The `Via` Caddy adds to responses is removed, so the answer stays the ADR-005 one. Go re-serialises the target on the hop as it already does at the host proxy and in FrankenPHP (the private hosted deployment plan §4.3): the H-6 corpus runs through the hop too.
- **Restarts.** `lb_try_duration 10s` on the front covers a restart of the next hop. Rev. 10 (S3-18): with HAProxy in the path, HAProxy itself holds a capture while ingest restarts or is recreated (§2.4); a longer outage answers HAProxy's fixed JSON `503` with `Retry-After`.
- **Ingest's own limits** (`docker/Caddyfile.ingest`): `php_ini enable_post_data_reading Off` (S3-3: no form or multipart parsing, no upload temp files, `php://input` carries every byte); `num_threads {$INGEST_NUM_THREADS:8}` (S3-4, its own pool; rev. 10: 8, A09 S3-17); `read_body {$INGEST_READ_BODY_TIMEOUT:15s}`, `read_header 10s` (S3-4: one slow sender holds an ingest thread at most 15 s; the app front keeps 30 s for its own routes); `max_header_size 64KiB` (S3-7; the image already refused header blocks over about 32 KiB in the local run, so this is a stated bound rather than a change); `request_body max_size 25MiB` and a `413` with the same JSON body and headers as PHP's (S3-15; rev. 10: `Cache-Control: no-store, private`, as Symfony sends it).

**Measured on the pinned FrankenPHP base (local run, 2026-11-15; front and ingest containers, test script in place of the app).** Multipart and urlencoded bodies through the hop: `enable_post_data_reading` off, `$_POST` and `$_FILES` empty, a 3 MB multipart body arrived with the same SHA-256 as sent. A slow sender (1 byte every 2 s) was cut at 15.0 s of PHP read time. With ingest at 2 threads held by 4 slow senders, a request on the app host answered in under 10 ms while a capture waited 13 s for a free ingest thread: the pools are separate. Forged `X-WT-Verified`, `X-Forwarded-For` (all spellings), a wrong `X-WT-Edge`, and direct calls to ingest from another container were all treated as untrusted; the right secret kept the host proxy's address and scheme. A capture sent while ingest restarted (3 s) got `200`; with ingest down past 10 s, `502`. The real app's tests (A06: multipart stored byte for byte with its hash) still apply.

**Per-address connection cap and backstop rate limit (S3-4, A09 decision 2): closed by HAProxy, §2.4 (rev. 9, decision 61).**

### 2.4 HAProxy capture gate (rev. 9, decision 61; A09 S3-4 and decision 2)

**Where it sits.** Between `app`'s Caddy and `ingest`, in both profiles: `app` sends the capture path to `{$INGEST_UPSTREAM}` = `haproxy:8080`, which forwards to `ingest:8080`. Decision 61 says "in front of the stack"; it is placed at the hop instead, for three reasons: (1) that is the only place where the client address is already resolved the same way in every profile and trust mode (§2.3), so HAProxy needs no trust logic of its own beyond checking the hop's signature; (2) in self-host with built-in TLS, putting HAProxy in front of everything would mean HAProxy terminating TLS and taking certificate management away from Caddy; (3) only capture needs these limits, and app and websocket traffic stay as they are. **Hosted: nothing changes on the shared host Caddy** (same vhosts, same upstream `127.0.0.1:${APP_PORT}`, no new port, no module). The limits are keyed on the address the host Caddy puts in `X-Forwarded-For`, after `app` has verified the host proxy's `X-WT-Edge` (SV-2). A request that reaches the app port without the proof is keyed on its TCP peer, as before.

**Which address it keys on.** `app` signs every hop with `X-WT-Edge` and sends exactly one `X-Forwarded-For` (§2.3). HAProxy reads the secret from `/run/secrets/edge_proxy_secret` as a pattern file. On a signed hop the key is the last `X-Forwarded-For` value; on anything else (a container on `internal` calling `haproxy:8080` directly, an unsigned request) it is the TCP source address. Keys are masked like the app's limiter: IPv4 /32, IPv6 /64. Without a readable secret file HAProxy refuses to start.

**Limits (`docker/haproxy.cfg`, values from the private hosted deployment plan §4.2).**

| Limit | Value | Env (unset = default) | Answer |
| ----- | ----- | --------------------- | ------ |
| Captures in flight per address (S3-4) | 20 | `WT_CAPTURE_MAX_CONN` | the app's `429`: `Content-Type: application/json`, `Cache-Control: no-store`, `Retry-After: 60`, `{"error":"rate_limited"}` (empty body for `HEAD`) |
| Requests per address per minute (backstop, decision 2) | 1200 (twice the app's 600, so it acts only while the app limiter fails open) | `WT_CAPTURE_MAX_RATE` | the same `429` |
| Declared body over 25 MiB | `Content-Length` > 26214400 | none | ingest's `413` JSON, at once |
| Slow small bodies (S3-4) | the body is held in HAProxy (`wait-for-body`, up to its 32 KiB buffer, at most 15 s) before ingest is called | none | rev. 10 (S3-19): exactly ingest's answer for a body it cuts at 15 s, `200 {"received":true}`; ingest never sees it |
| **Partial bodies in flight per address (rev. 10, S3-17)** | 2 | `WT_CAPTURE_MAX_STREAM` | the same `429` |
| **Partial bodies in flight in total (rev. 10, S3-17)** | 6 = `INGEST_NUM_THREADS` − 2, so 2 ingest threads always stay free for complete bodies | `WT_CAPTURE_STREAM_SLOTS` (set it with `INGEST_NUM_THREADS`) | the same `429`; a server `maxconn` of the same value backs it |

None of them depends on the capture key. The front removes `Server` and `Via` from every capture answer, so the HAProxy `429` and the app's `429` carry the same headers (checked header by header, `Date` aside).

**Partial bodies (rev. 10, A09 S3-17).** After `wait-for-body`, a request is *partial* when it is chunked (`Transfer-Encoding`) or its `req.body_len` is below its `Content-Length` (the body was larger than the 32 KiB buffer). Only those can reach ingest before their body is complete and hold a PHP thread for up to 15 s. They go to the backend `ingest_stream` (same server) and are tracked in a second table keyed like the first. A request still incomplete when `wait-for-body` expires never reaches ingest at all (it gets the timeout answer). Complete bodies, most webhooks, are not affected. Accepted residual (A09): a few addresses together can fill the 6 partial slots, and large or chunked captures then get the `429` for everyone until the slots free; the `429` depends on load only.

**Every answer HAProxy produces (rev. 10, A09 S3-19).** Fixed body, `Content-Type: application/json`, `Cache-Control: no-store, private`:

| Status | Body | Note |
| ------ | ---- | ---- |
| `408` (headers or body not in time) | answered as `200 {"received":true}` | byte for byte ingest's answer for a body it cuts at 15 s (an errorfile with a 200 status line, `docker/haproxy-received-200.http`) |
| `400` | `{"error":"bad_request"}` | malformed request or header block over the buffer |
| `413` | `{"error":"too_large"}` | as ingest |
| `429` | `{"error":"rate_limited"}`, `Retry-After: 60` | as the app |
| `500`, `502`, `503`, `504` | `{"error":"unavailable"}`, `Retry-After: 60` | ingest unreachable past the retries; a real outage, so providers are told to retry |

`timeout server` is 100 s, above ingest's worst case: PHP's `max_execution_time` (60 s) is wall-clock under FrankenPHP's ZTS build (a `sleep` past `set_time_limit` was cut, measured) and includes the 15 s body read and S3-10's lock waits, so the app's own deadline always answers first and no `504` can depend on a key's state. For A06: a request that reaches that 60 s limit ends in PHP's fatal error page, not the ingest answer; whether that path can depend on the key is A06's/A09's to check.

**Ingest restarts (rev. 10, A09 S3-18).** Docker's DNS name of `ingest` disappears while it is recreated and may come back on a new address. HAProxy keeps the last answer while the name is missing (`hold nx`, `refused`, `timeout` 30 s), takes a new address as soon as it is answered (`hold obsolete 1s`, `timeout resolve 1s`), and retries refused connections about once a second for 30 s (`retries 30`, `retry-on conn-failure empty-response`), re-picking the server on every retry (`option redispatch 1`). Without the last part, retries stayed on the old address and recreates took 30 s; with it, measured: six captures sent across a `docker restart` and across two recreates onto a new address (old address taken by another container) all got `200`, the slowest after 4.1 s. Ingest down for more than 30 s: the JSON `503` after 32.7 s.

**What HAProxy does not do.** It adds and changes no header, target or body: no `option forwardfor`, no header rules, no `Via`; no traffic log at all (the path holds the capture key, RD-1), only startup lines. Header blocks over about 31 KiB get HAProxy's `400` (`tune.bufsize 32768`; the image already refused about 32 KiB, §2.3). It re-resolves `ingest` through Docker's DNS, so a recreated ingest is found again.

**How its config gets there.** `docker/haproxy.cfg` is copied into the signed app image (`/etc/webhooktrap/haproxy.cfg`). The one-shot `haproxy-config` (app image, `cp` only; rev. 10 copies the config and the `received-200.http` errorfile, `user: "0:0"` with no capability, `network_mode: none`, read-only root) writes it into the named volume `haproxy_config`, and `haproxy` mounts that volume read-only. So the config is exactly the one of the deployed app digest, with no bind mount and no top-level `configs` (P-5), and no third image to sign or pass to `deploy.sh`. `haproxy` carries a label with the app digest (self-host: `WEBHOOKTRAP_VERSION`), so each new app image recreates it and it loads the new config. Neither image has `/wt-haproxy`, so Docker always creates the volume root-owned, and the one-shot can write it without `CAP_DAC_OVERRIDE`. Compose `image` volumes would avoid the one-shot, but they failed on the local Docker 29.1 daemon ("file name too long"), so they are not used.

**Residual (rev. 9) closed in rev. 10** by the partial-body class above (A09's decision on S3-17).

**Measured, rev. 10 (2026-11-15; the runtime image built from this repository; `app`, `ingest`, `haproxy`, `haproxy-config` exactly as defined in `compose.production.yaml`, under `cap_drop: [ALL]`, `no-new-privileges`, read-only roots, secret `root:33 0440`; database-free path via a malformed key).**
- **One address:** with 2 partial senders (40 KB sent, then 1 byte per second) in flight, a 3rd from the same address got the `429` with a `Content-Length` body and again with a chunked body; a complete body from that address got `200` at once. The first two held ingest for 16 s and got `200`.
- **Several addresses:** 8 addresses each pushing a partial body (5 `Content-Length`, 3 chunked): 6 admitted, 2 got the `429`; meanwhile a complete-body capture from a fresh address got `200` in under 10 ms. A 2 MB body at full speed got the `429` while the 6 slots were full (the accepted residual) and `200` once they freed.
- **General cap:** the 21st slow capture from one address got the `429`; the 20 slow ones got `200`.
- **Answers:** HAProxy's timeout answer and ingest's own answer were identical, status, headers and body (`Date` aside); same for the `413`.
- **Ingest memory at 8 threads** under its 384m limit: 86 MiB idle; peak 165 MiB with 6 × 5 MB bodies in flight, 188 MiB with 8 × 25 MB, 194 MiB with 16 × 5 MB queued; no OOM. A stored body (not measured: no database in this run) adds at most one more copy of the kept body (5 MiB) per thread, about 80 MB, so 384m stands.
- **HAProxy memory** under its limit: 22 MiB idle, 35 MiB with about 25,000 tracked addresses, 79 MiB peak with 1,000 connections holding full 32 KiB buffers on top; extrapolated to 50k entries and 1,024 connections, about 90 MiB, so the limit is 128m.

**Measured, rev. 9 (local run, 2026-11-15; pinned FrankenPHP base as front and ingest with a test script, pinned HAProxy).**
- **Headers:** captures through `app` → `haproxy` → `ingest` show no `Via`, `X-Forwarded-*` or `Accept-Encoding` that the client or the trust chain did not set.
  - This run found that Go's transport in the Caddy hop added `Accept-Encoding: gzip`; fixed with `compression off` in `docker/Caddyfile`.
  - The host proxy's verified address and scheme pass through, and the client's own `Via` is kept exactly.
- **Bodies:** a 3 MB multipart body arrived with the same SHA-256 as sent (`$_FILES` empty). A 30 MB body got the JSON `413` in 0.07 s.
- **Cap:**
  - With 20 slow senders from one address in flight, the 21st got the `429` in under 10 ms, another address got `200`, and once they finished the same address got `200` again. The cap counts per request, even over Caddy's pooled connections.
  - Two senders in one IPv6 /64 shared a bucket; another /64 did not.
  - With the front in mode `none`, and with direct unsigned calls from another container, a forged `X-Forwarded-For` did not change the key.
- **Slow bodies:** with 8 small-body slow senders (twice ingest's threads), a fresh capture answered at once and the slow ones got `408` at 15 s. A sender that first sent 40 KB reached ingest and was cut there at 15 s.
- **Wiring:** the one-shot copy, the read-only `haproxy` with the secret as `root:33 0440` read through `group_add`, the healthcheck, and the rerun of the copy on a second `up` all worked in a Compose run.

---

## 3. Resource limits for the shared VPS

**Rev. 14: the hosted box is larger than the 8 GB reference (sizes in the private plan).** `compose.production.yaml` now carries the right-hand column below, with the rev. 8-11 additions:

| Service | Limit · CPUs |
| ------- | ------------ |
| app | 1200m · 1.5 (`APP_NUM_THREADS` 12) |
| ingest | 512m · 0.75 |
| haproxy | 128m · 0.25 |
| haproxy-config | 32m · 0.1 |
| reverb | 384m · 0.5 |
| horizon | 512m · 1.0 |
| scheduler | 128m · 0.25 |
| engine | 192m · 0.25 |
| valkey | 384m · 0.5 |
| postgres | 2048m · 1.5 (`shared_buffers` 1 GB, `effective_cache_size` 3 GB) |
| backup | 384m · 0.5 |

- **Sum:** about 5.8 GiB. The slice proposal is `MemoryMax=6G`, `CPUQuota=300%` (the private hosted deployment plan §6.1), so the ceilings fit under it with no overcommit.
- **Settings in `.env.production`:** `PASSWORD_HASH_MAX_CONCURRENT=3`, `LONGPOLL_AGENT_SLOTS=6`, `VALKEY_MAXMEMORY=288mb`.
- **§3.1 rule at 1200m:** 3 × 70 MiB ≤ 300 MiB (25 %), and a 560 MB baseline ≤ 600 MB (50 %).

The 8 GB column below stays as the self-host reference and the history.


Ceilings, not reservations. The slice caps the sum; per-service limits cap each part. Final numbers after F-2/F-6 (the private hosted deployment plan §1.2).

| Service | 8 GB box (slice `MemoryMax` 3.2 GB, `CPUQuota` 200 %) | 16 GB box (slice 5 GB, 300 %) | Other |
| ------- | --------------------- | ---------------------- | ----- |
| postgres | 1.2g · 1.0 cpu · `shared_buffers` 512 MB | 2g · 1.5 · 1 GB | `pids_limit` 200 |
| app | 768m · 1.0 | 1.2g · 1.5 | FrankenPHP `num_threads` from env, sized to the limit |
| haproxy (rev. 9, 10) | 128m · 0.25 | 128m · 0.25 | rev. 10 measured: 79 MiB peak with 1,000 full buffers and 25k addresses; about 90 MiB at 1,024 connections and 50k entries (§2.4) |
| haproxy-config (rev. 9) | 32m · 0.1 | 32m · 0.1 | one-shot `cp` at each `up`, then exits |
| ingest (rev. 8, 10) | 384m · 0.5 | 512m · 0.75 | rev. 10: `INGEST_NUM_THREADS` 8 on both boxes (S3-17), measured 194 MiB peak at 8 threads, about 275 MiB with stored bodies (§2.4); the body is streamed, at most 5 MiB kept |
| reverb | 256m · 0.5 | 384m · 0.5 | `nofile` soft = hard 10 000, checked at boot (§3.2) |
| horizon | 448m · 0.5 (rev. 11) | 512m · 1.0 | rev. 11: three supervisors, measured 304 MiB with the default queue scaled to 2 (§3.4) |
| scheduler | 128m · 0.25 | 128m · 0.25 | |
| valkey | 256m (`VALKEY_MAXMEMORY` ~75 % of it) · 0.25 | 384m · 0.5 | |
| backup | 256m · 0.25 | 384m · 0.5 | low I/O weight |
| engine | 128m · 0.25 | 192m · 0.25 | `pids_limit` 128, no network |
| **Sum of limits** | ≈ 3.95 GB with `ingest`, `haproxy` at 128m and `horizon` at 448m (3.99 GB while the one-shot runs); the slice ceiling of 3.2 GB still binds | ≈ 5.84 GB (slice 5 GB) | `memswap_limit` = `mem_limit` everywhere (no swap use) |

**Rev. 9 proposal (PO to choose).** The per-service limits are ceilings: they now add up to about 0.75 GB more than the slice on the 8 GB box (rev. 11) (rev. 7: about 0.18 GB). Steady state is expected near 2.0 GB: the earlier 1.8 GB estimate plus `ingest` (measured 86 MiB idle) and `haproxy` (22 MiB idle). Two ways forward:
- **(a) Recommended: keep the slice at 3.2 GB and the limits above.** The slice protects the neighbours and is what binds. If 2.0 as a whole reaches it, the kernel kills inside the slice by `oom_score_adj`: app-family containers first, never Postgres or a neighbour. Revisit with the PERF-1..4 numbers.
- **(b) Raise the slice to 3.6 GB (45 % of RAM).** This needs a PO GO for the slice unit on the host (the private hosted deployment plan §6.1).

Not proposed: trimming `app`. The rule in §3.1 (FrankenPHP baseline at most 50 % of the limit) keeps it at 768m until A08 measures the baseline without capture traffic. CPU: the `cpus` values add up to 4.75 against `CPUQuota=200%`; the quota binds, as before.

The 8 GB column gives 2.0 at most 40 % of RAM and 2 vCPUs; steady state is expected near 1.8 GB (hosting-options §3.3, to be measured by A08/A11 with PERF-1..4).

### 3.1 argon2id memory budget (rev. 6, A09 R-AB, H-19)

**Parameters and cost.** argon2id through Laravel `Hash`: memory **64 MiB** (`ARGON_MEMORY=65536` KiB), time cost **4** (`ARGON_TIME`), **1** thread (`ARGON_THREADS`), at or above OWASP's minimum. Cost per hash: a 64 MiB working set held for one hash, about **100-300 ms** of one core (A08 measures the p95 on the VPS, target at most 300 ms); with allocator overhead count **70 MiB**. libargon2 allocates outside PHP's `memory_limit`, so the container `mem_limit` is the only bound. The dummy-hash path for unknown emails and accounts without a password (TH-1c) costs the same and counts as a hash.

**Who hashes.** Only the `app` container, on the request path (sign-in, `/confirm-auth`, register, reset, change, set-password, recovery finish). `horizon`, `scheduler`, `reverb` and `engine` hash nothing; a job that must hash needs A10's sign-off, because `horizon` (384m) would then hold its own cap.

**Cap on hashes in flight.** `PASSWORD_HASH_MAX_CONCURRENT`, enforced in the app with a counter in Valkey (`INCR`, refuse and `DECR` above the cap, 10 s key TTL so a crash cannot leak a slot). Over the cap the request waits at most 2 s, then gets the same throttled-style answer whatever the email, so the cap is not an oracle. The throttles (A05: 5 / 15 min per email + address, 20 / h per account, 30 / 15 min per /64) run **before** the cap and cost no hash, but they limit one source, not a botnet; the cap bounds the memory a botnet can pin.

| | 8 GB box | 16 GB box |
| --- | --- | --- |
| `app` `mem_limit` | 768m | 1.2g |
| `app` `cpus` | 1.0 | 1.5 |
| `PASSWORD_HASH_MAX_CONCURRENT` | 2 | 3 |
| Hash memory at the cap (× 70 MiB) | 140 MiB (18 % of the limit) | 210 MiB (17 %) |
| FrankenPHP and Caddy baseline (estimate, A08 to measure with PERF-1..4) | about 380 MB, `num_threads` 8 | about 560 MB, `num_threads` 12 |
| Headroom left | about 250 MB | about 450 MB |
| Hashes per second at the cap (about 300 ms each, one core each) | about 3 (the CPU limit binds before the cap) | about 5 |

**Rule (the cost is not free to raise).** `PASSWORD_HASH_MAX_CONCURRENT × (ARGON_MEMORY / 1024 × 1.1)` must stay at or below **25 %** of the `app` `mem_limit`, and the baseline at or below 50 %. A06 adds a boot check beside the `PASSWORD_ARGON2ID` one: read the container limit from `/sys/fs/cgroup/memory.max` and refuse to boot when the rule fails (self-host operators who raise `ARGON_MEMORY` get the refusal instead of an OOM loop). Doubling memory to 128 MiB halves the cap at the same limit or needs `mem_limit` raised by the same amount; the slice ceiling (the private hosted deployment plan §6.1) binds first on the 8 GB box, so a cost increase is a budget decision for the PO.

**Failure mode.** Past the budget, the kernel OOM-kills `app` (it restarts; `oom_score_adj: 500`), not a neighbour. Sign-in capacity is about 3 hashes per second on the 8 GB box, far above real use; an attack above that is answered with the throttled answer and raises the failed-sign-in alert (the private hosted deployment plan §6.6). Passkey, OAuth and reset sign-ins do not hash and stay available.


### 3.2 Reverb capacity (rev. 7, decision 50, A08 spike §6, C-7, C-8)

**Fact (laptop, one process).** The image's PHP had no `ext-uv`, `ext-ev` or `ext-event`, so ReactPHP fell back to `stream_select` (`FD_SETSIZE` 1024). Reverb **exited at about 1,014 subscribed sockets and dropped every client**. A refused or unsubscribed socket still holds a file descriptor, so `REVERB_APP_MAX_CONNECTIONS` alone does not protect the process.

1. **Extension in the image.** The runtime image installs **`ext-uv`** (preferred; `libuv` from the distribution) or **`ext-event`** (`libevent`), in the one image shared by `app`, `reverb`, `horizon`, `scheduler` (one image, template pattern). Which of the two builds on the FrankenPHP base is untested (the spike could download nothing): A10 builds both on paper, picks the one that installs and loads, and records it in the Dockerfile comment. Nothing else changes in the image.
2. **Boot check (`/usr/local/bin/reverb-boot`, Reverb service only).** In order, and it exits non-zero with one log line naming the failed check: (a) `extension_loaded('uv') || extension_loaded('event')`; (b) the soft `nofile` limit (`ulimit -Sn`) is at least `REVERB_MIN_NOFILE` (4096); (c) `REVERB_ACCEPT_CLIENT_EVENTS_FROM` resolves to `none` in the loaded config; then `exec php artisan reverb:start …`. A failed check makes the container restart-loop and `app` never becomes healthy, which is the intended loud failure. The check never prints env values.
3. **`nofile`.** `ulimits: { nofile: { soft: 10000, hard: 10000 } }` on `reverb` (§2 YAML, already present, now verified by check (b)). Sockets are capped well below it: `REVERB_APP_MAX_CONNECTIONS` 1500 on the 8 GB box, 3000 on the 16 GB box (proposals, to be set from the Phase 5 measurement, §3.3). The margin covers Valkey, DB and log descriptors.
4. **Connection limits, two layers.** *Reverb:* `REVERB_APP_MAX_CONNECTIONS` (subscribed sockets) plus the per-principal cap and maximum lifetime, which are A05's own code in the Reverb process because Reverb has neither (decision 50). *Host proxy for `ws.<domain>`:* cap on concurrent connections and on new connections per second, per client address, plus an overall cap; rules in the private hosted deployment plan §4.4. The proxy limit exists because raw sockets that never subscribe use descriptors that Reverb's own limit does not count.
5. **Client events.** `config/reverb.php` sets `accept_client_events_from` to `none` explicitly (not left at the package default), no env override ships, and a Pest config test asserts it (CI `php` job, `ci-cd.md` §2).

**Long-poll and PHP workers (TM-40, A08 C-8).** One long-poll holds one PHP worker. Measured with 4 workers: 12 concurrent `jobs/next?wait=5` requests ran serially in batches of 4 and a plain request waited 4.3 s. So long-polls are capped and the caps are sized from the worker count (FrankenPHP `num_threads`: 8 on the 8 GB box, 12 on the 16 GB box, §3.1 table):

| | 8 GB box | 16 GB box |
| --- | --- | --- |
| `num_threads` (rev. 12: `APP_NUM_THREADS`, set in `docker/Caddyfile`; before rev. 12 nothing set it and FrankenPHP took 2 × host CPUs, 16 on the VPS) | 8 | 12 |
| Agent long-poll slots (`LONGPOLL_AGENT_SLOTS`, at most 50 % of workers) | 4 | 6 |
| Runner long-poll slots (`LONGPOLL_RUNNER_SLOTS`) | 2 | 2 |
| Workers always free for UI, API and capture, and for the up to `PASSWORD_HASH_MAX_CONCURRENT` hashes (§3.1) | 2 | 4 |
| Maximum wait (`LONGPOLL_MAX_WAIT_SECONDS`) | 25 | 25 |

**Rev. 12 wiring.** `docker/Caddyfile` sets `num_threads {$APP_NUM_THREADS:8}`, and Compose passes the same value to the app as `LONGPOLL_HTTP_WORKERS`, which caps the agent slots (`config/agent.php`). Both are always set from `APP_NUM_THREADS`, never apart (policy P-17).

**Session-pooled database only (rev. 12).** The long-poll slots are PostgreSQL **session** advisory locks: taken on the app's connection, freed when it closes or the process dies. They need a connection that stays the app's for the whole poll: a direct connection, or a pooler in **session** mode. **Never PgBouncer (or PgCat, Odyssey, Supavisor) in transaction or statement mode**: the lock would land on a server connection handed to another client, so slots would leak or be shared. Both profiles connect straight to `postgres`; adding a pooler is a policy change that must keep session mode (`ci-cd.md` P-19). Self-hosters who put their own pooler in front of an external Postgres must use session mode.

Rules: the slot gate is shared by all long-poll endpoints (agent and runner count against their own pool, never against the free workers); when no slot is free the endpoint answers **503 with `Retry-After`** immediately (`LONGPOLL_RETRY_AFTER_SECONDS`, 5 to 15 with jitter) and never queues; the free-worker count above must stay at or above 2 (a boot check in the app compares the slot values with `num_threads` and refuses a configuration that leaves fewer). Agents fall back to the realtime channel first (ADR-010 §5), so the cap is a backstop. Re-measure on FrankenPHP: the spike's capped run on `artisan serve` was inconclusive.

### 3.3 Still to measure on the VPS (Phase 5, decision 50)

Nothing here is a number yet; all are open until measured on the real box with the real image. Each result goes into this file and the private hosted deployment plan §6.7.

| # | Measurement | Why | Feeds |
| - | ----------- | --- | ----- |
| M-1 | Reverb sockets held on one process with `ext-uv`/`ext-event` (ramp 500, 1000, 1500, 3000, up to the limit; RSS and CPU at each step; confirm no exit past 1,014) | the laptop run died at 1,014 | `REVERB_APP_MAX_CONNECTIONS`, `reverb` `mem_limit`, `nofile` |
| M-2 | Fan-out latency p50/p95/p99 at the PERF-4 profile (50 deliveries per run, 10 viewers, 50 concurrent runs) and on one channel with many subscribers | linear growth with subscribers on one channel | per-channel design, A05 |
| M-3 | argon2id hash time p50/p95 at 64 MiB, t=4 and t=3, on the shared VPS with neighbours busy (laptop: p50 189 ms, p95 202 ms at t=4) | target ≤ 300 ms; the VPS is shared | `ARGON_TIME`, `PASSWORD_HASH_MAX_CONCURRENT` (§3.1) |
| M-4 | Capped long-poll under FrankenPHP (503 + `Retry-After` rate, plain-request latency with all slots busy) | `artisan serve` run was inconclusive | slot values above |
| M-5 | Host-proxy `ws.` limits through the real chain (TLS, limit rules refuse as written) | not testable on the laptop | the private hosted deployment plan §4.4 |
| M-6 | Real browser client over the real proxy; agent heartbeat cost | not covered by the spike | A11, A05 |

### 3.4 Queues and Horizon workers (rev. 11)

Every queue the app dispatches to has its own Horizon supervisor, in every environment (`config/horizon.php`; any `APP_ENV` other than `production` and `local` gets the defaults through the `*` environment). Before rev. 11 only `default` had a worker, so the live triggers (`broadcasts`) and the scheduled purges (`maintenance`) were queued and never run.

| Supervisor | Queue | What runs there | Processes (production / local / other) | Timeout | Tries |
| ---------- | ----- | --------------- | -------------------------------------- | ------- | ----- |
| `supervisor-default` | `default` | mail, any job without a queue name | 1-2 (auto) / 1 / 1 | 60 s | 1 |
| `supervisor-broadcasts` | `broadcasts` | Live Prop triggers (`LiveTrigger`, millisecond jobs, coalesced) | 1 / 1 / 1, never shared with long jobs | 10 s | 3 (1 s backoff) |
| `supervisor-maintenance` | `maintenance` | identity maintenance, account, project and capture purges (scheduled, `withoutOverlapping`) | 1 / 1 / 1, `nice` 10 | 300 s | 1 (the next scheduled run redoes it) |

- **`retry_after` 330 s** on the `redis` queue connection (`REDIS_QUEUE_RETRY_AFTER`): above the longest timeout, so a purge still running is never handed to a second worker. A job lost by a crashed worker comes back after 330 s; for a live trigger that only delays a refresh.
- **Memory (measured 2026-11-15, image built from this repository, production config):** about 35 MiB per PHP process. 1 master, 3 supervisors and 4 workers came to 304 MiB, 278 MiB of it anonymous. Hence `horizon` 448m, leaving about 144 MiB for jobs. The previous config allowed 10 `default` workers, which could not have fitted in 384m.
- **Guard.** `tests/Architecture/QueueSupervisorTest.php` scans `app/` and `routes/` for `onQueue(…)`, `$queue = …`, `viaQueue()` and `queue: …`, outside comments. It fails when a queue has no supervisor in `production`, `local`, `ci` or `staging`, when a name is not a string literal, when a timeout reaches `retry_after`, or when `broadcasts` shares its supervisor or has a long timeout or many tries. Checked against a config without the production `maintenance` supervisor: it fails and names the four maintenance jobs.
- **Local (lerd).** The `horizon` worker (`php artisan horizon`) starts the three supervisors (`horizon:supervisors`). A maintenance job and a live trigger dispatched by hand were both consumed. The trigger then failed on the local `.env`, not on the queue: `REVERB_PORT` is 8080 while lerd's Reverb listens on 8081.

---

## 4. Self-host profile: `compose.yaml`

One file, derived from §2 by these changes only (decision 32: single profile, Redis-compatible store required):

| Area | `compose.production.yaml` | `compose.yaml` (self-host) |
| ---- | ------------------------- | -------------------------- |
| Image reference | `${APP_IMAGE}@${APP_DIGEST}` (commit SHA) | `ghcr.io/<owner>/<repo>:${WEBHOOKTRAP_VERSION}` (release tag, never `latest`), same for the Postgres image and the runner |
| TLS and ports | `127.0.0.1:${APP_PORT}:8080`, host proxy in front | `TLS_MODE=auto`: publishes `${HTTP_PORT:-80}:8080` and `${HTTPS_PORT:-443}:8443` (+ `443/udp` for HTTP/3), FrankenPHP gets certificates (`caddy_data` volume added). `TLS_MODE=off`: same as production, bound to `${BIND_ADDRESS:-127.0.0.1}:${APP_PORT}` behind the operator's proxy |
| Migrations | run by the deploy script | one-shot `migrate` service (`php artisan migrate --force`, `restart: "no"`), started after `backup` runs a pre-migrate backup when `BACKUP_BEFORE_MIGRATE` is on; `app`, `reverb`, `horizon`, `scheduler` depend on it with `service_completed_successfully` |
| Agent (rev. 12) | `agent_pairing_hmac_key` file secret on `app`; `APP_NUM_THREADS` = `LONGPOLL_HTTP_WORKERS` | same; the operator creates `secrets/agent_pairing_hmac_key` (`openssl rand -hex 32`); `AGENT_ALLOW_DEV_BUILDS` must stay unset (the entrypoint refuses it unless `APP_ENV` is local or testing) |
| HAProxy gate (rev. 9, §2.4) | `haproxy` and `haproxy-config`, `INGEST_UPSTREAM=haproxy:8080` on `app` | same, wired by default; defaults 20 in flight and 1200/min per address, partial bodies 2 per address and 6 in total (`WT_CAPTURE_*`, rev. 10); a self-host operator whose own proxy hides client addresses must use mode `secret` or `ip`, or every capture shares one bucket; limits 128m and 32m; rev. 10: the edge secret only on `app`, `ingest`, `haproxy` (not `migrate`, `reverb`, `horizon`, `scheduler`) |
| Ingest (rev. 8, §2.3) | `ingest` service, `INGEST_MODE=proxy` on `app` | same service and wiring, nothing to configure; `PROXY_TRUST_MODE` is `${PROXY_TRUST_MODE:-none}` on the app family and always `secret` on `ingest`; `secrets/edge_proxy_secret` is needed in every TLS mode (it signs the hop) |
| Engine sandbox (ENG-1) | `engine` service, `network_mode: none`, socket volume `engine_sock` | same service and volume, same limits; settled before instance-secret `verify` ships. Rev. 16: the image creates `/run/engine` as 10001:10001 0770, so a new `engine_sock` volume is seeded with that owner; the socket is 0660 and `app` reaches it through `group_add: ["10001"]`. A volume created by an older image stays root-owned and the engine cannot bind: `docker compose down engine app && docker volume rm <project>_engine_sock` (it holds only the socket), or `chown 10001:10001` and `chmod 0770` its directory |
| Runner | not present (own VM) | `runner` service: `ghcr.io/<owner>/<repo>-runner:${WEBHOOKTRAP_VERSION}` (distroless static, non-root), `read_only`, `cap_drop: [ALL]`, `pids_limit: 256`, 256 MB, 1 CPU; network `runner` only (with `app`), no DB/Valkey/Reverb variables; `RUNNER_TOKEN_FILE` from a secret created by `php artisan runner:token` on first start; idle while server runs are off |
| Backup repository | `s3` (R2 EU) | default `posix` on the `pgbackrest_repo` volume, with a loud note that same-disk backups are not disaster backups; `s3`/`sftp` documented |
| `cgroup_parent`, `oom_score_adj` | set (shared box) | removed (unknown host) |
| Limits | §3 (and §3.2 for Reverb: same image, same boot check, `REVERB_APP_MAX_CONNECTIONS` 500 on the 4 GB reference size until measured) | reference size (2 vCPU / 4 GB): postgres 1.5g, app 768m, reverb 256m, horizon 448m (rev. 11), scheduler 128m, valkey 384m, backup 256m, runner 256m |
| Networks | fixed subnets from F-5 | Docker-assigned; `PROXY_TRUST_MODE` defaults to `none` with built-in TLS and to `secret` behind a proxy (`ip` only as the weaker documented option, with `TRUSTED_PROXIES` defaulting to the `edge` network, declared with a documented default subnet the operator may change) |
| Admin bootstrap | console command over SSH | `ADMIN_SETUP_TOKEN` printed to the log on first start if unset (TM-19) |
| Kill switch | `SERVER_REPLAY_PUBLIC` false until C11 | same default false; the operator turns it on |

Networks in self-host: `edge` (app, reverb), `internal` with `internal: true` (app, ingest, haproxy, reverb, horizon, scheduler, migrate, postgres, valkey, backup), `egress` (app, horizon), `backup_egress` (backup), `runner` (runner, app). Postgres and Valkey never join a network with internet access; the runner never joins `internal` (S-11, TM-80). The engine guard is the primary SSRF control on self-host; a DOCKER-USER egress recipe is documented as optional hardening (A09 §7.2 item 5).

**Redis-compatible store:** `REDIS_HOST` may point at an operator's own Redis or Valkey; the bundled `valkey` service stays in the file (decision 32 forbids a minimal profile), and the architecture test accepts either (HD-8).

---

## 5. Environment variable names (no values)

Secrets also accept `<NAME>_FILE`. The template's names are kept where they exist.

**Secrets as files (rev. 13, CLAUDE.md).** No secret is a plain variable in a container: `.env.production` and the self-host `.env` hold settings only. Each secret is a file in `secrets/`. **Owners (rev. 14, checked on the built images):** files the app family reads are `root:33 0440`; `postgres_password`, `backup_cipher_pass`, `backup_s3_key` and `backup_s3_secret` are `root:999 0440`, because the official Postgres entrypoint re-reads `POSTGRES_PASSWORD_FILE` as uid 999 and the `backup` service runs as uid 999; `valkey_password` is `root:33 0440` (Valkey reads it as root, the app as uid 33). `edge_proxy_secret` is `root:33 0440` (HAProxy reads it through `group_add: 33`), mounted as a Compose secret only where the app reads it. The entrypoint loads `<NAME>` from `<NAME>_FILE` if set, else from `/run/secrets/<file>` if mounted (an unreadable mounted file stops the start), else leaves a plain variable alone (local development only; refused on hosted by P-20).

| Variable | File | Mounted on | Why there |
| -------- | ---- | ---------- | --------- |
| `APP_KEY` (`APP_PREVIOUS_KEYS` during a rotation, file `app_previous_keys`, mounted only then) | `app_key` | app, ingest, reverb, horizon, scheduler, migrate | every Laravel process boots with it |
| `DB_PASSWORD` | `db_password` (rev. 14: same value as `postgres_password`, separate file because the app reads as uid 33 and Postgres as uid 999) | app, ingest, horizon, scheduler, migrate | not `reverb`: it never queries the database |
| `REDIS_PASSWORD` | `valkey_password` (shared with `valkey`) | app, ingest, reverb, horizon, scheduler | cache, queue, limiter; `migrate` does not use Valkey |
| `REVERB_APP_SECRET` | `reverb_app_secret` | app, reverb, horizon | channel auth signing on `app`; every broadcast is sent from the `broadcasts` queue |
| `MAIL_PASSWORD` | `mail_password` (may be empty) | horizon | mail is only ever queued |
| `GITHUB_CLIENT_SECRET`, `GOOGLE_CLIENT_SECRET` | `github_client_secret`, `google_client_secret` (may be empty) | app | OAuth sign-in |
| `ERROR_TRACKER_DSN` | `error_tracker_dsn` (may be empty) | every Laravel process | reporting (no config reads it yet: A05/A06) |
| `IDENTITY_EMAIL_HMAC_KEY`, `IDENTITY_RECOVERY_CODE_KEY` | `identity_email_hmac_key`, `identity_recovery_code_key` | app | used only on the HTTP path of `app` (account deletion, recovery codes); `ingest` does not get them: the app's serve check skips `WT_ROLE=ingest` (A06, 9224c67), which Compose sets explicitly (P-17) |
| `AGENT_PAIRING_HMAC_KEY` | `agent_pairing_hmac_key` | app | pairing endpoints |
| edge proxy secret | `edge_proxy_secret` | app, ingest, haproxy | §2.1, §2.3, §2.4 |
| `POSTGRES_PASSWORD_FILE`, Valkey `requirepass`, backup keys, `RUNNER_TOKEN_FILE` | `postgres_password`, `valkey_password`, `backup_*`, `runner_token` | postgres; valkey; **backup only** (rev. 14: `postgres` no longer mounts backup secrets, it only queues WAL, §7); runner | |

Required at start: `APP_KEY` everywhere; the identity keys on `app` only; the pairing key on `app` (entrypoint §3, §4d). Framework defaults in `config/` for drivers this deployment does not use (Ably, AWS, Memcached, Mercure, Postmark, Pusher, Resend, Slack) get no file.

| Group | Names |
| ----- | ----- |
| Deploy (`.env.deploy`, written by the deploy script) | `APP_IMAGE`, `APP_DIGEST`, `IMAGE_TAG`, `PG_IMAGE`, `PG_DIGEST`, `APP_PORT` |
| Instance (template) | `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL`, `LOG_CHANNEL`, `LOG_LEVEL`, `APP_VERSION` (image) |
| Hosts and TLS | `SERVER_NAME`, `APP_HOST`, `CAPTURE_HOST`, `WS_HOST`, `TLS_MODE`, `CADDY_AUTO_HTTPS`, `CADDY_HTTP_PORT`, `CADDY_HTTPS_PORT`, `HTTP_PORT`, `HTTPS_PORT`, `BIND_ADDRESS`, `ACME_EMAIL`, `PROXY_TRUST_MODE` (`secret`, `ip`, `none`), `EDGE_PROXY_SECRET_FILE`, `TRUSTED_PROXIES` (mode `ip` only), `APP_MAX_BODY`, `ENGINE_SOCKET`; rev. 8: `INGEST_NUM_THREADS` (4), `INGEST_READ_BODY_TIMEOUT` (15s), and, set by Compose only, `INGEST_MODE` (`local`, `proxy`), `INGEST_UPSTREAM` (`ingest:8080`), `WT_ROLE` (`ingest`); rev. 9: `WT_CAPTURE_MAX_CONN` (20), `WT_CAPTURE_MAX_RATE` (1200); rev. 12: `APP_NUM_THREADS` (8; the front's FrankenPHP pool, also passed as `LONGPOLL_HTTP_WORKERS`), `AGENT_PAIRING_HMAC_KEY(_FILE)`, `AGENT_ALLOW_DEV_BUILDS` (APP_ENV local or testing only; refused elsewhere by the entrypoint and policy P-18); rev. 10: `WT_CAPTURE_MAX_STREAM` (2), `WT_CAPTURE_STREAM_SLOTS` (6, = `INGEST_NUM_THREADS` − 2), `INGEST_NUM_THREADS` default 8, `WT_ROLE` (`app`, `ingest`, `worker`; unset: `app` for FrankenPHP, else `worker`) |
| Capture (`CAPTURE_PROXY_HEADERS` also lists `X-WT-Edge`, `X-WT-Verified`) | `CAPTURE_PUBLIC_ORIGIN`, `CAPTURE_TRUST_FORWARDED_ORIGIN`, `CAPTURE_PROXY_HEADERS`, `CAPTURE_BODY_KEEP_MAX_BYTES`, `CAPTURE_REQUEST_MAX_BYTES`, `STORAGE_WATERMARK_PERCENT`, `STORAGE_BUDGET_BYTES`, `STORAGE_BUDGET_WARN_PERCENT` |
| Database (template) | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD(_FILE)`; container: `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD_FILE`, `PG_SHARED_BUFFERS`, `PG_EFFECTIVE_CACHE_SIZE` |
| Valkey (template names kept) | `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD(_FILE)`, `VALKEY_MAXMEMORY` |
| Laravel drivers (template) | `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `BROADCAST_CONNECTION` |
| Reverb | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET(_FILE)`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`, `REVERB_SCALING_ENABLED`, `REVERB_PUBLIC_HOST` (sent to the browser at runtime, see note); rev. 7: `REVERB_APP_MAX_CONNECTIONS` (proposed 1500), `REVERB_ACCEPT_CLIENT_EVENTS_FROM` (fixed `none`, no override in `.env.example`), `REALTIME_MAX_CONNECTIONS_PER_PRINCIPAL`, `REALTIME_MAX_CONNECTION_LIFETIME_SECONDS` (A05's own code in the Reverb process, decision 50), `REVERB_MIN_NOFILE` (4096), `LONGPOLL_AGENT_SLOTS`, `LONGPOLL_RUNNER_SLOTS`, `LONGPOLL_MAX_WAIT_SECONDS`, `LONGPOLL_RETRY_AFTER_SECONDS` |
| Mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD(_FILE)`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |
| OAuth | `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET(_FILE)`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET(_FILE)` |
| Server runs | `SERVER_REPLAY_PUBLIC`, `WEBHOOKTRAP_ALLOW_PRIVATE_REPLAY_TARGETS` |
| Runner | `RUNNER_SERVER_ORIGIN` (fixed `http://app:8080` in `compose.yaml`: plain http only to a single-label Compose name), `RUNNER_TOKEN_FILE` (fixed `/run/secrets/runner_token`), `RUNNER_CONCURRENCY`, rev. 16: `RUNNER_GUARD_CEILING` (`public` default, or `allowlist`), `RUNNER_ALLOWLIST_FILE` (required with `allowlist`; `./runner/` is mounted read-only at `/etc/webhooktrap-runner`) |
| Backup | `BACKUP_ENABLED`, `BACKUP_BEFORE_MIGRATE`, `BACKUP_REPO_TYPE`, `BACKUP_S3_ENDPOINT`, `BACKUP_S3_REGION`, `BACKUP_S3_BUCKET`, `BACKUP_S3_KEY_FILE`, `BACKUP_S3_SECRET_FILE`, `BACKUP_CIPHER_PASS_FILE`, `BACKUP_RETENTION_FULL`, `BACKUP_RETENTION_ARCHIVE_DAYS`, `BACKUP_FULL_SCHEDULE` |
| Error tracking (HD-9) | `ERROR_TRACKER_DSN`, `ERROR_TRACKER_SEND_BODIES` (always false) |
| Password hashing, rev. 6 (A06 reads, A10 sets) | `ARGON_MEMORY` (65536), `ARGON_TIME` (4), `ARGON_THREADS` (1), `PASSWORD_HASH_MAX_CONCURRENT` (2 on 8 GB, 3 on 16 GB) |
| Deletion ledger, rev. 6 | `LEDGER_PATH` (`/ledger/deletions.jsonl`), `LEDGER_AGE_RECIPIENT` (public key), `LEDGER_RETENTION_DAYS` (21), `LEDGER_SHIP_INTERVAL_SECONDS` (60), `LEDGER_S3_PREFIX` (`ledger/`) |
| Sign-in alert, rev. 6 | `SIGNIN_ALERT_WINDOW_SECONDS` (300), `SIGNIN_ALERT_WARN_FAILURES` (50), `SIGNIN_ALERT_WARN_THROTTLED` (10), `SIGNIN_ALERT_CRIT_FAILURES` (250), `OPS_ALERT_EMAIL` |
| Admin | `ADMIN_SETUP_TOKEN` |
| Self-host image | `WEBHOOKTRAP_VERSION` |

**Note for A05 (one image for every domain):** the template builds the front end inside the image. Laravel's Reverb starter reads `VITE_REVERB_*` at **build** time, which would bake our domain into the image and break self-host. The Reverb host, port and key must reach the browser at runtime (Inertia shared props from `REVERB_PUBLIC_HOST` etc.).

---

## 6. Healthchecks summary

| Service | Check | Used by |
| ------- | ----- | ------- |
| app | `GET /up` on loopback (template) | Compose, deploy script |
| ingest (rev. 8) | `GET /up` on loopback, through its own Caddy and PHP | Compose (`haproxy` depends on it) |
| haproxy (rev. 9) | `wget` on its loopback-only health frontend (`127.0.0.1:8405`) | Compose (`app` depends on it) |
| fail-open stores (rev. 8, A09 decision 2, S3-11) | A06's `wt.store_failure` warning (`kind` = `rate_limit` or `cache`, `suppressed`), at most once a minute per kind, in the `app` and `ingest` logs | alert (the private hosted deployment plan §6.8) |
| reverb | TCP connect on 8080; `reverb-boot` has already refused to start without `ext-uv`/`ext-event` or with `nofile` < 4096 (rev. 7, §3.2) | Compose (`app` depends on it) |
| horizon | `horizon:status` | Compose |
| scheduler | heartbeat age < 120 s | Compose, `/health` |
| valkey | `ping` with auth from env file, not argv | Compose |
| postgres | `pg_isready` | Compose |
| backup | last full < 26 h, archive lag < 15 min; ledger last ship < 15 min, weekly ledger verify (rev. 6, DL-3) | Compose, `/health`, alert |
| sign-in failures (rev. 6) | `signin_failures_5m` and `signin_alert_level` in `/health`; counts only | `/health`, alert (the private hosted deployment plan §6.6) |
| engine | socket ping (`sandbox-ping`) | Compose (`app` depends on it) |
| runner (self-host) | last poll age, reported to `/health` | `/health` |

---

## 7. Upgrades and backups (both profiles)

- Images per release (self-host) or per commit digest (hosted); never `latest` at run time (the template also pushes `latest`; it is kept only as a convenience tag).
- Migrations expand/contract, backward-compatible for one release, so an image rollback needs no schema rollback (template README says the same).
- Pre-migrate backup before every migration (hosted: deploy script; self-host: `backup` then `migrate`).
- Postgres major upgrades are a manual documented procedure after a verified backup. **Rev. 14:** Postgres 18 images keep the cluster under `/var/lib/postgresql/18/docker` and refuse a volume at `/var/lib/postgresql/data`, so `pgdata` is mounted at `/var/lib/postgresql` (that layout also allows `pg_upgrade --link` later).
- **Backup tooling (rev. 14, `docker/postgres/wt-backup`, replaces the stubs).**
  - **WAL archiving without network on `postgres`:** `archive_command=pgbackrest-queue %p %f` copies each segment into a queue on the `pgbackrest_spool` volume. `backup`, which has the egress network and the repository secrets, pushes the queue with `pgbackrest archive-push`. So `postgres` has neither a route to the repository nor a backup secret.
  - **Schedule:** a daily full at `BACKUP_FULL_SCHEDULE` (UTC `HH:MM`, default 02:30), retention by time (`BACKUP_RETENTION_FULL` days, default 14), repository encrypted (`aes-256-cbc`, `backup_cipher_pass`), `posix` (self-host default) or `s3` (R2).
  - **Status:** `/status/backup.json` (last full, last backup, ledger shipping and verify; rev. 16: `archive_lag_seconds`, the age of the oldest `.ready` or queued WAL segment, 0 when none, and `archive_lag_at`, when it was measured, both written by `backup-loop` once a minute) feeds `backup-status` and `/health`.
  - **Commands:** `backup-now` (deploy step 4), `restore` (writes `restore.pending`; recovery runs once in a `backup` container, which can reach the repository), `ledger-fetch`, `restore-finish`.
  - **Proved locally on the built image (2026-11-15):** full and incremental backups, WAL through the queue (nothing left queued), the ledger shipped age-encrypted and decrypted with the identity, `backup-status` ok, and a restore into a fresh volume recovered from the repository with every row.
  - `BACKUP_RETENTION_ARCHIVE_DAYS` is dropped: WAL follows the full retention.
- Backups: Postgres only; Valkey and `caddy_data` are re-creatable. Restore: `docker compose run --rm backup restore [--target-time=…]` into a stopped stack with an empty `pgdata`. Disclosure of backup contents and the 14 + 1 day window in the privacy notice (HR-9, TM-81).
- **Deletion ledger (rev. 6, DL-3).** Shipped off-server with the backups, kept at least 21 days, and **re-applied after every restore before the app, workers and queues start**: `restore` writes `restore.pending`, the app image refuses to start until `accounts:apply-ledger` and `restore-finish` have run (steps in the private hosted deployment plan §5.1; self-host runs the same steps against `compose.yaml`).

---

## Change table (rev. 1 → rev. 2)

| Area | Rev. 1 | Rev. 2 | Reason |
| ---- | ------ | ------ | ------ |
| Base | own design, `compose.yaml` + `compose.hosted.yaml` override | template's `compose.production.yaml` extended; self-host `compose.yaml` derived from it; template's dev file renamed `compose.dev.yaml` | decision 43 |
| Edge | `proxy` (Caddy) service on 80/443 | no proxy service; FrankenPHP routes by host; hosted behind the host proxy on loopback, self-host TLS by FrankenPHP | decision 43 |
| Queue | `horizon` | `horizon` (replaces template's `queue: queue:work`) | queues and dashboard kept |
| Redis | `valkey` | `valkey` with password from a secret file, replacing template's passwordless `redis:7-alpine` | HD-8, TM-80 |
| Postgres | `postgres:18` | custom `postgres:18.x + pgbackrest` image, used by `postgres` and `backup` | `archive_command` needs pgBackRest in the container |
| Migrations | `migrate` service everywhere | hosted: deploy script (template); self-host: `migrate` service | template model kept for hosted |
| Secrets | env vars | `*_FILE` secrets for every credential | not visible in `docker inspect` |
| Limits | dedicated-box values | shared-VPS values with slice, OOM order; reference values for self-host | decision 41 |
| Logs | relied on `daemon.json` | per-service `json-file` caps | shared VPS |
| Runner | in the file, disabled on hosted | only in self-host `compose.yaml` | HD-5 |
| Front end | not covered | runtime Reverb config instead of `VITE_*` build vars | one image for all domains |

---

## Change table (rev. 2 → rev. 3, A09 re-check §12.5)

| ID | Area | Rev. 2 | Rev. 3 | Where |
| -- | ---- | ------ | ------ | ----- |
| SV-2, TM-141 | Forwarded headers | `trusted_proxies static {$TRUSTED_PROXIES}`, edge gateway as trusted | `PROXY_TRUST_MODE` (`secret` hosted); `X-WT-Edge` secret checked and stripped, `X-WT-Verified` set only on match, forwarded headers stripped otherwise; `edge_proxy_secret` secret | §2, §2.1, §5 |
| SV-2 (facts) | App port | `${APP_PORT:-<template default>}` | `${APP_PORT:?}`, hosted value in the private plan | §2 |
| RD-1, TM-145 | Logs | `log_skip` on the capture host only | redacting filter on the default logger, `log_skip @share`, no Referer in logs | §2.1 |
| CI-2, TM-146 | Compose file | no stated policy | §2.2 and `deploy/policy/compose-policy.py` (installed on the box, not in the image) | §1, §2.2 |
| ENG-1, TM-149 | `plan`/`verify` sandbox | undecided | `engine` service: `network_mode: none`, no secrets, socket volume; Landlock as fallback; limits row added | §2, §3, §4, §6 |

## Change table (rev. 3 → rev. 4, A09 verification §12.9)

| ID | Area | Rev. 3 | Rev. 4 | Where |
| -- | ---- | ------ | ------ | ----- |
| SV-2, TM-141 | Trust snippet order | snippet was a top-level `route`, which Caddy orders after `handle`: never ran | outer `route` wraps snippet and `handle` blocks; order cited from Caddy's directive-order page; TM-T141 proves it by `caddy adapt` and by direct requests | §2.1 |
| SV-2 | Fail closed | `{file.…}` could expand to empty | entrypoint refuses to start if secret missing, empty, unreadable or not 64 hex; `header_regexp` guard; file `root:<gid>` `0440` | §2.1 |
| SV-2 | Header spellings | exact names only | underscore spellings of `X-WT-Verified`, `X-WT-Edge`, `X-Forwarded-*`, `X-Real-IP` stripped | §2.1 |
| CI-2 | YAML vs P-8 | limits only in the §3 table | `mem_limit`, `memswap_limit`, `cpus`, `pids_limit` on all eight services; no `driver_opts`/`configs` stated | §2, §2.2 |
| RD-1 | Error-log filter | `/s/` and `Referer` only | also whole URI (capture keys) and `X-Wt-Edge` (all spellings) | §2.1 |
| ENG-2, TM-149 | Engine | per-call secret in memory | stateless between calls, never logs inputs, drops secret after `verify`, only `plan`/`verify`, output untrusted | §2 notes |

## Change table (rev. 4 → rev. 5, A09 verification §12.10)

| ID | Area | Rev. 4 | Rev. 5 | Where |
| -- | ---- | ------ | ------ | ----- |
| SV-2a, TM-T141 | Underscore spellings | removed only for unverified requests; verified requests reached PHP with `X_Forwarded_For` next to the proxy's value | all underscore spellings of `X-Forwarded-*` and `X-Real-IP`, plus `Forwarded` and `X-Forwarded-Port` (every spelling), removed on **every** request; hyphen `X-Forwarded-For/-Proto/-Host` still removed only when unverified; TM-T141 adds the verified case through the real Caddy proxy | §2.1 |
| RN-1a, TM-T148 | `.php/` path info | not handled in the image | `@phpinfo path_regexp (?i)\.php/` answered `404` inside the outer `route`, second guard behind the host proxy's own matcher (the private hosted deployment plan §4.5); TM-T148 covers `/index.php/runner/v1/x` and `/index.php%2Frunner/v1/x` | §2.1 |

## Change table (rev. 5 → rev. 6, A09 verification §12.11)

| ID | Area | Rev. 5 | Rev. 6 | Where |
| -- | ---- | ------ | ------ | ----- |
| DL-3b | Ledger | not present | `ledger` volume (horizon writes, backup reads), `backup` ships it to the bucket prefix `ledger/` every 60 s, `age`-encrypted to a public recipient, kept at least 21 days; five `LEDGER_*` names | §2 YAML and notes, §5 |
| DL-3c, TM-T158 | Restore order | `restore` into a stopped stack | `restore` writes `restore.pending`; app image entrypoint refuses to start while it exists; apply via `run --no-deps --entrypoint php horizon artisan accounts:apply-ledger --file=/status/ledger-merged.jsonl` (rev. 16; no new service, P-1 holds); `restore-finish` | §2 notes, §7 |
| R-AB | argon2id memory | not covered | parameters, per-hash cost, `PASSWORD_HASH_MAX_CONCURRENT`, sizing table and 25 % rule; boot check proposed to A06 | §3.1, §5 |
| R-AA residual | Failed sign-in alert | none | `/health` fields and `SIGNIN_ALERT_*`, `OPS_ALERT_EMAIL` names | §5, §6 |
| R-AF, RD-2 | `/set-password/{token}` | not listed | covered by the blanket error-log redaction; added to TM-T145 canaries | §2.1 |

## Change table (rev. 6 → rev. 7, decision 50, A08 spike)

| ID | Area | Rev. 6 | Rev. 7 | Where |
| -- | ---- | ------ | ------ | ----- |
| Decision 50, A08 C-7 | Reverb image | no event-loop extension; Reverb exited at about 1,014 sockets | `ext-uv` or `ext-event` in the shared image; `reverb-boot` refuses to start without it | §2 YAML, §3.2 |
| Decision 50 | `nofile` | `nofile` 10 000 in YAML, unchecked | same value, checked at boot (at least 4096); socket caps set far below it | §3, §3.2 |
| Decision 50 | Connection limits | none stated | `REVERB_APP_MAX_CONNECTIONS` plus per-principal cap and lifetime (A05 code) at Reverb; per-address and overall limits at the host proxy for `ws.` | §3.2, §5; hosted deployment plan §4.4 |
| Decision 50, A08 C-8 | Long-poll | not covered | 503 + `Retry-After` when no slot; agent slots at most 50 % of `num_threads`, runner 2, at least 2 workers always free; boot check on the values | §3.2, §5 |
| Decision 50 | Client events | package default | `none` set explicitly, no env override, boot check and Pest config test | §3.2; `ci-cd.md` §2 |
| Decision 50 | VPS measurements | none | M-1..M-6 listed for Phase 5 (Reverb sockets, p99, argon2id time, capped long-poll, proxy limits, real browser) | §3.3 |

## Change table (rev. 7 → rev. 8, A09 slice 3 review)

| ID | Area | Rev. 7 | Rev. 8 | Where |
| -- | ---- | ------ | ------ | ----- |
| S3-3, decision 4 | Multipart and form bodies | parsed by PHP before the app, temp files, `php://input` empty for multipart | `ingest` runs with `enable_post_data_reading Off`; the app keeps the default | §2, §2.3 |
| S3-4 | Slow bodies | one thread pool for app and capture, 30 s body read | own pool (`INGEST_NUM_THREADS`), 15 s body read on ingest; per-address cap is proposal O5 | §2.3, §3 |
| S3-5 follow-up | Trust mode | image default only | `PROXY_TRUST_MODE` explicit in both profiles; ingest always `secret`; hop re-signed with the edge secret | §2, §2.3, §4 |
| (found here) | Secret in every process | only `app` mounted `edge_proxy_secret`, but the entrypoint checks it in every process in mode `secret` | mounted on the app family (`reverb`, `horizon`, `scheduler`, self-host `migrate`) so they start | §2, §4 |
| S3-7, S3-15 | Header block, 413 shape | none on the capture path; text 413 | `max_header_size 64KiB` and a JSON 413 on ingest | §2.3 |
| Decision 2, S3-11 | Fail-open alert | none | wired to `wt.store_failure` | §6; hosted deployment plan §6.8 |

## Change table (rev. 8 → rev. 9, decision 61)

| ID | Area | Rev. 8 | Rev. 9 | Where |
| -- | ---- | ------ | ------ | ----- |
| S3-4, O5 | Per-address cap | proposal | HAProxy: 20 captures in flight per address (/32, /64), the app's `429` | §2.4 |
| Decision 2, O5 | Backstop rate limit | proposal | HAProxy: 1200 per minute per address, the same `429` | §2.4 |
| S3-4 | Slow small bodies | held an ingest thread for up to 15 s | held in HAProxy (`wait-for-body`), `408` at 15 s | §2.4 |
| (found here) | Hop headers | Go added `Accept-Encoding: gzip` to the hop | `compression off`; `Server` also removed from capture answers so every `429` looks the same | §2.3, §2.4 |
| Decision 61 | Services | nine | eleven (`haproxy`, one-shot `haproxy-config`); config shipped in the signed app image | §2, §2.2, §2.4 |
| Decision 61 | Resources | sum 3.8 GB | sum 3.86 GB against the 3.2 GB slice; proposal (a) keep the slice, (b) raise it to 3.6 GB | §3 |

## Change table (rev. 9 → rev. 10, A09 runtime and HAProxy review)

| ID | Area | Rev. 9 | Rev. 10 | Where |
| -- | ---- | ------ | ------ | ----- |
| S3-17 | Partial bodies | could hold every ingest thread from one address | own class: 2 per address, 6 in total (`INGEST_NUM_THREADS` − 2), same `429`; ingest at 8 threads, measured under 384m | §2.4, §3 |
| S3-18 | Ingest recreate | HAProxy answered `503` | DNS hold, 30 retries, redispatch on every retry; measured `200` across restart and recreate | §2.3, §2.4 |
| S3-19 | HAProxy answers, server timeout | HTML pages; `timeout server` 60 s | fixed JSON for every status, timeout = ingest's `200`; `timeout server` 100 s | §2.4 |
| S3-22 | Edge secret spread | every app-family process | `app`, `ingest`, `haproxy` only; `WT_ROLE` selects the check | §2, §2.1, §4 |
| S3-23 | Secret file shape | any one line matching | the whole file | §2.1 |
| S3-24 | HAProxy sizing, empty env | 96m; empty value broke the start | 128m measured; table 50k; Compose `:-` defaults | §2, §2.4, §3 |
| (found here) | Image capability | binary with a file capability | removed; `app` and `ingest` now start under `cap_drop: [ALL]` | §2 notes |
| (found here) | Answer headers | `Cache-Control: no-store` in our answers | `no-store, private`, as the app sends | §2.3, §2.4 |

## Change table (rev. 10 → rev. 11, Horizon queues)

| ID | Area | Rev. 10 | Rev. 11 | Where |
| -- | ---- | ------- | ------- | ----- |
| (A06 finding) | Horizon | one supervisor for `default`; `broadcasts` and `maintenance` had no worker | one supervisor per queue in every environment; `broadcasts` 10 s, 3 tries, own worker; `maintenance` 300 s | §3.4 |
| (A06 finding) | Queue connection | `retry_after` 90 s | 330 s, above the longest timeout | §3.4 |
| (A06 finding) | Resources | `horizon` 384m; up to 10 workers allowed | 448m; at most 4 workers, measured 304 MiB | §3, §3.4 |
| (A06 finding) | Guard | none | architecture test: every dispatched queue has a supervisor | §3.4 |

## Change table (rev. 11 → rev. 12, agent follow-ups)

| ID | Area | Rev. 11 | Rev. 12 | Where |
| -- | ---- | ------- | ------- | ----- |
| A06 `13f7443` | Pairing key | none | `AGENT_PAIRING_HMAC_KEY` from `secrets/agent_pairing_hmac_key` on `app`; in the entrypoint's `_FILE` list and required for `WT_ROLE=app` | §2, §4, §5 |
| A06 `0a088b4` | Front threads, long-poll | `num_threads` unset (2 × host CPUs) | `APP_NUM_THREADS` 8, passed as `LONGPOLL_HTTP_WORKERS` | §3.2 |
| A06 `0a088b4` | Dev agent builds | none | refused outside APP_ENV local/testing | §4, §5 |
| A06 `0a088b4` | Advisory locks | none | session-pooled database only | §3.2 |

## Change table (rev. 12 → rev. 13, secrets as files)

| ID | Area | Rev. 12 | Rev. 13 | Where |
| -- | ---- | ------- | ------- | ----- |
| CLAUDE.md rule | App secrets | `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`, `REVERB_APP_SECRET`, `MAIL_PASSWORD`, OAuth secrets, `ERROR_TRACKER_DSN` and the identity keys as plain variables in `.env.production` | Compose file secrets per service, loaded by the entrypoint from `/run/secrets` | §2, §5 |

## Change table (rev. 13 → rev. 14, step C2)

| ID | Area | Rev. 13 | Rev. 14 | Where |
| -- | ---- | ------- | ------- | ----- |
| Host size | Hosted limits | 8 GB column | the hosted column in the file, sum about 5.8 GiB | §3 |
| C2 | Backups | stubs | pgBackRest with a WAL queue, ledger shipping, status, restore; proven locally | §7 |
| (found here) | Postgres 18 volume | `/var/lib/postgresql/data`, refused by the image | `/var/lib/postgresql` | §7 |
| (found here) | DB password file | one file for postgres and the app | `postgres_password` (uid 999) and `db_password` (uid 33), same value | §5 |
