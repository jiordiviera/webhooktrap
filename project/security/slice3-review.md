# A09 security review · Slice 3 (Capture: capture URLs, HTTP ingestion, capture persistence)

**Date:** 2026-11-15 · **Reviewer:** A09 · **Branch:** `phase5/foundation`
**Range read:** `f7bcf66` (Capture module, migration, middleware, listeners, jobs, tests) and `dc81f97` (contracts.md "Slice 3 notes"), plus the code they call in Identity, Workspace, Retention and Infrastructure, `docker/Caddyfile`, `docker/caddy/trust-site-*.caddy` and `docker/php.ini`.
**Read against:** threat model TB-1 (TM-01 to TM-07), S-6, S-7, §7.5, §7.6, §9.4; ADR-005 amendment 1 §1 to §9; domain-model §4 and §2.10; DL-2 (§12.11).
**Suite:** `php artisan test` reported 356 passed with 3452 assertions.
**Live checks:** a few `curl` requests against `https://webhooktrap.test` (lerd, nginx + PHP-FPM, not the image). They covered unknown, malformed and real keys, `HEAD`, an unregistered method, a JSON body, a multipart body and forged proof headers. The probes left two captures in the local dev database, under paths ending `/a09` and `/a09m`.

Exploit detail is kept in the private notes, not here.

## Verdict

**SHIP WITH FIXES.** Slice 3 has no High finding. The ingest invariant holds, DL-2 holds, and the integrity triggers are correct under concurrency. Four Medium findings must be closed before hosted go-live:

- S3-1: the per-address limit and shared provider IPs.
- S3-2: plaintext secret headers.
- S3-3: multipart parsing.
- S3-4: concurrency on slow bodies.

S3-2 is a small code change in this module. S3-1, S3-3 and S3-4 need A10 (edge and runtime) and, for S3-1, a one-line ADR-005 wording change from A05. None of them blocks merging the slice. The Low findings are small fixes for A06.

## What is right

- **Ingest invariant (TM-01, ADR-005 am. §4).** `ServeCaptureHost` is prepended to the global stack, so it runs before sessions, cookies, CSRF, CORS, maintenance mode and the router. It answers every outcome other than `rate_limited` and `too_large` with the same object: `200`, `Content-Type: application/json`, `Cache-Control: no-store, private`, and `{"received":true}` (an empty body for `HEAD`). The outcomes covered are unknown key, malformed key, rotated key, owner not live, guest quota, global guest budget, account quota, oversize body kept without its body, store error, database down, and any exception. The test "byte-identical … on every drop path" checks status, headers and body. Live probes gave the same bytes for a real key, an unknown key, a 1-character key, a deep path, `HEAD` and the method `PURGEX`. No cookie and no session are set.
- **Timing.** The body is read and hashed in full whatever the key, so the read cost does not depend on the key. What remains is the difference between a stored and a dropped request (one transaction and the insert). It tells someone who already holds a key about that key's quota or owner state, and it cannot be used to discover keys (71-bit keys). This is the accepted residual R-3 / TM-01. Malformed keys skip the cache and the database, but the key format is public, so this reveals nothing. Local timings for known and unknown keys were inside the noise.
- **429 and 413.** Both are keyed only on the client address or the body size, are identical for every key (tests cover this), and come before or independently of key resolution. The limiter never keys on the capture key.
- **Method and target.** The stored method is `REQUEST_METHOD`, never `_method` or `X-HTTP-Method-Override`. The raw target is stored as the server gave it, with NUL replaced.
- **Trusted-proxy chain (TM-05, SV-2).** Inside the image, the Caddyfile strips every spelling of `X-WT-Verified`, `X-WT-Edge` and the forwarding headers before anything else runs. It sets `X-WT-Verified: 1` only when the edge secret matches and is a 64-hex value. `source_ip` is the right-most valid `X-Forwarded-For` entry only on a trusted request, and the peer otherwise. `X-WT-Edge` and `X-WT-Verified` are never stored, whoever sends them. `public_origin` comes from configuration, and a trusted `X-Forwarded-Proto` only changes the scheme, and only when `CAPTURE_TRUST_FORWARDED_ORIGIN` is on. The host and port stay configured.
- **DL-2.** The cache holds a resolution for 3 s under a SHA-256 of the key (the key itself never reaches the store). It is evicted synchronously on account deletion, project deletion, claim and session end. Storage does not depend on the cached liveness:
  - Account path: the guarded quota `UPDATE` takes the project row lock, and only then is liveness read. The tombstone locks the user row and then the same project rows. The two orders cannot deadlock, and the result is linearizable: a capture either commits before the tombstone or is dropped after it.
  - Guest path: one guarded `UPDATE` refuses ended, claimed and expired sessions.
  - Tests cover a warm cache and a cache that was not evicted.
- **Integrity triggers.** The request-facts trigger refuses any change to the 19 fact columns. The budget trigger releases a session's bytes once, when it ends, is claimed or is deleted (and not again for an already-ended session). It runs under the same row lock as the ingest reservation, so neither order of the two can leak or double-count. Expired sessions are ended every 5 minutes by `RunIdentityMaintenance`, so their bytes come back. The quota checks are single guarded `UPDATE`s inside the insert transaction. A failed budget check rolls back the guest counter. The only insert path into `captures` is `Ingest::store`.
- **Untrusted content.** Header values and the content type go through `mb_scrub`, and NUL becomes U+FFFD. Nothing is rendered in this slice. The body is stored as `bytea`, never decoded, and the 5 MiB limit is backed by a `CHECK`.
- **Rotate route.** It is in the `web` group (CSRF through `PreventRequestForgery`, `verified-email`). The owner is checked through `Projects::owned`. A guest owner, any other user, an anonymous caller and a deleted project all get the same `404`. The old key is evicted after commit.
- **Retention purge.** It covers live account projects only. It deletes by `received_at` cutoff in batches of 1000, each in its own transaction, and gives back exactly the kept bytes (`body_size` where `body_kept`, which equals what was counted). It runs daily on `maintenance` without overlap. Bodies cascade with their captures.

## Findings

| ID | Severity | Area | Finding | Fix |
| -- | -------- | ---- | ------- | --- |
| S3-1 | Medium | Edge rate limit | The 600/min limit is per source address across all capture keys. Major providers deliver from small shared IP pools, so on hosted all tenants of a provider share one bucket. One busy or hostile tenant who makes a provider send to their own capture URLs can get every other tenant's deliveries from that provider answered `429`. | Keep 600/min per address for ordinary addresses. For documented provider egress ranges (a data file with tests, refreshed from the providers' published lists), replace the per-address bucket with a per-(address, SHA-256 of the presented key string) bucket at 600/min. It counts unknown and malformed keys the same way, so it reveals nothing about existence. A05: amend ADR-005 am. §4 from "never key on the capture key" to "never key on whether a capture key exists or on its state". A10: export a metric of 429s per bucket. |
| S3-2 | Medium | Header redaction | Only `Authorization`, `Proxy-Authorization` and `Cookie` are redacted. Some providers send the shared secret itself in a header: GitLab (`X-Gitlab-Token`), Telegram (`X-Telegram-Bot-Api-Secret-Token`) and Asana's handshake (`X-Hook-Secret`). Users also point their own systems at a capture URL with API-key headers. These are stored in clear on hosted, which breaks §7.6 "secrets never on hosted". Confirmed live: `X-Gitlab-Token` and `X-Api-Key` stored verbatim. | Extend `RequestFacts::REDACTED` to the list in Decision 3 below, with the same `[redacted · N bytes]` placeholder and the names listed in `redacted_headers`. Token-scheme verdicts become `not_checked` with a reason (A01/A05 to name it). Signature headers stay. Add the new names to the S-7 test dataset. |
| S3-3 | Medium | Multipart | With `enable_post_data_reading` On (the default), PHP parses `multipart/form-data` and `application/x-www-form-urlencoded` bodies, and writes uploaded parts to temporary files, before any application code runs. That is before the per-address limiter. For multipart, `php://input` is then empty, so the capture records 0 bytes and the SHA-256 of nothing (confirmed live). Two effects: hostile parser input and temporary-file churn on the public path ahead of the limiter, and a false record of evidence. | Decision 4 below: `enable_post_data_reading=Off` on the ingest runtime only. Until it lands, multipart captures are a known fidelity defect, and Prove must not treat their body as known. |
| S3-4 | Medium | Concurrency (TM-03) | The limiter counts requests, not open requests. The capture host allows a 30 s body read, and the capture and app hosts share one FrankenPHP thread pool. One address under its 600/min can hold a large share of the PHP threads with slow bodies, which stalls ingest and the app together. | A10, before hosted go-live: a per-address concurrent-connection cap on `in.` at the host proxy (suggested 20), a shorter body-read timeout for `in.` (suggested 15 s), and ingest on its own thread pool or instance (which also delivers S3-3). |
| S3-5 | Low | Trust mode | The app default `PROXY_TRUST_MODE=secret` trusts any request that carries `X-WT-Verified: 1`. Only the image Caddyfile strips that header. Any deployment without it (local lerd, `artisan serve`, a self-host that does not use the image) lets a client mark itself trusted and choose `source_ip`, the forwarded scheme and which headers are removed. Confirmed on lerd (`trusted: true` from a client header). Hosted and both Compose profiles are not affected. | Make the app default `none`. Have the image entrypoint export `PROXY_TRUST_MODE` explicitly to both Caddy and PHP, and refuse to start when they would differ. Log a warning at boot when the mode is `secret` and the process is not running under the image. |
| S3-6 | Low | Rotation | The store transaction re-reads owner liveness but not the capture URL state. A request resolved before a rotation (cached for up to 3 s, or resolved before a slow body read of up to 30 s) is still stored after the rotation. The same happens when a cache write races the eviction. Rotation is the owner's "stop this URL" control. | In the store transaction, after the project lock, read `capture_urls.state` for `urlId` and drop with reason `rotated` unless it is `active`. Add a test with a stale cached resolution and a rotated key. |
| S3-7 | Low | Quotas | The bytes quota and the guest budget count kept body bytes only. Headers and the raw target (up to the server's header limit, about 1 MB by default) and the metadata of oversized bodies are stored without being counted. | Count `strlen(headers JSON) + strlen(raw_target)` in the reserved bytes. Cap the request header block on `in.` (Caddy `max_header_size`, suggested 64 KiB). |
| S3-8 | Low | Guest budget | Guests have a count limit (100) but no per-session byte limit. One guest can hold 500 MiB, 10 % of the 5 GiB global budget, so a handful of sessions can stop guest capture for everyone for up to 24 h. Expired sessions give their bytes back to the budget, but their data stays on disk until the Retention slice purges guest data, so for now the budget does not bound disk use. | Add a per-session byte cap (suggested 50 MiB) to the guarded guest `UPDATE`. Gate hosted go-live on the guest-data purge (Retention slice). |
| S3-9 | Low | Claim | When a guest project is claimed, its kept bytes do not move into `projects.stored_body_bytes`. When retention later deletes those captures, `GREATEST(…, 0)` absorbs bytes that were never counted. A claimed project can therefore exceed the account bytes quota by up to about 500 MiB. | In the claim transaction, set the project's `stored_body_bytes` to the guest session's `stored_bytes`, and set `captures_today` and `captures_day` from the same day's count. |
| S3-10 | Low | Hot row | Every guest ingest transaction holds the single `instance_counters` row lock until commit, including the insert of a body of up to 5 MiB. Guest ingest is serialized instance-wide, and lock waits have no timeout. | Set `SET LOCAL lock_timeout` (suggested 2 s) in the ingest transaction. A timeout drops the request as `store_error`, with the same answer. Take the budget reservation as the last step before the insert. |
| S3-11 | Low | Fail open | While the limiter or cache store is down, each request calls `report()`, so a store outage turns every ingest request into an error report. That floods the logs and error tracking. | Add a short circuit breaker in the adapter: after a failure, skip the store for 10 s, report once per interval, and raise a metric or alert. |
| S3-12 | Low | Rotate route | No throttle. Each call inserts a `capture_urls` row. | Throttle per user (suggested 10 per hour), with the uniform throttle answer. |
| S3-13 | Info | Triggers | `capture_bodies` has no update guard, and the facts trigger does not cover `id`. Both triggers can be bypassed by the role that owns the tables, which is the app role. | Add a `BEFORE UPDATE` trigger on `capture_bodies` that refuses any update, and include `id` in the facts trigger. Consider a separate migration role (A10). |
| S3-14 | Info | Fidelity | Under PHP-FPM and FrankenPHP, both `CONTENT_TYPE`/`CONTENT_LENGTH` and `HTTP_CONTENT_TYPE`/`HTTP_CONTENT_LENGTH` are present, so the stored header list has `Content-Type` and `Content-Length` twice (seen live). This is not a security issue, but anything that counts or re-sends headers sees duplicates. | Skip the CGI variables when the `HTTP_` form is present. |
| S3-15 | Info | 413 shape | Caddy answers `413` with a text body and PHP answers with JSON. Both are independent of the key, so nothing leaks. | Align them on the JSON body for tidiness (A10). |
| S3-16 | Info | Forward | Nothing is rendered in this slice. When the stream (S-03) and the inspector land, text-only rendering (TM-06) is not enough on its own: C0/C1 control, bidi and format characters in headers, path and query must be shown escaped, as in TM-T41. The agent must also strip terminal control sequences before it prints any capture data. | A07 and the agent owner, with the TM-T6 canary corpus extended to those character classes. |

## Decisions on the open questions

1. **Rate-limit value.** 600 requests/min per client address (IPv4 /32, IPv6 /64) is **accepted** as the default, with S3-1's provider-range treatment before hosted launch. The `429 {"error":"rate_limited"}` with `Retry-After: 60` and the `413 {"error":"too_large"}` are **accepted**. They depend only on the address and the size, carry no key information, and match ADR-005 am. §4. Self-host keeps 600/min as the default, and a later instance setting may raise it.
2. **Fail-open.** **Accepted** for both the limiter and the key cache, under three conditions:
   - a backstop limit at the host proxy (S3-4, A10), so a store outage does not remove every limit;
   - an alert when the limiter or cache store fails;
   - S3-11's throttled reporting.

   Failing closed would let a Valkey outage stop capture, which ADR-004 rules out. Failing open costs only the per-address limit, while Postgres still enforces every quota.
3. **Redaction list.** Values stored as `[redacted · N bytes]`:
   - `Authorization`
   - `Proxy-Authorization`
   - `Cookie`
   - `X-Gitlab-Token`
   - `X-Telegram-Bot-Api-Secret-Token`
   - `X-Hook-Secret`
   - `X-Api-Key`
   - `Api-Key`
   - `X-Auth-Token`

   Matching is case-insensitive and on the exact name. Further names are added by A09 review only, never by pack data at runtime.

   **Signature headers are kept on purpose** (`Stripe-Signature`, `X-Hub-Signature-256`, `X-Shopify-Hmac-Sha256`, `Svix-Signature`/`Webhook-Signature`, `X-Twilio-Signature`, `X-Slack-Signature` and similar). They are HMAC outputs, not secrets, and the agent needs them for SIG-1. The residual risk is that anyone who can read a capture can try to guess a low-entropy signing secret offline. This is inherent in the providers' schemes. Share views must keep excluding signature headers through the SCR-3 allow-list (A07 to confirm when share views land).

   S-7 and ADR-005 am. §3 are extended accordingly (A05).
4. **Multipart.** **`enable_post_data_reading=Off` for ingest**, required before hosted go-live. It removes PHP's form and multipart parsing and its temporary-file writes from the public path, and it makes `php://input` carry every body byte for byte. It is a per-directory INI setting that cannot be changed at runtime. Turning it off globally would also empty `$_POST` and `$_FILES` for the app, so it belongs on the ingest runtime only: the separate capture-host instance or thread pool from S3-4. A10 chooses the mechanism; A06 adds a test that a multipart capture stores its exact bytes and hash. The app runtime keeps the default.

---

## Runtime and HAProxy (rev 2)

**Date:** 2026-11-15 · **Reviewer:** A09 · **Branch:** `phase5/foundation`
**Range read:** `364825d` (ingest service), `1c50937` (ops docs rev. 8), `be40efe` (HAProxy gate), `388c0bc` (ops docs rev. 9): `docker/haproxy.cfg`, `docker/Caddyfile`, `docker/Caddyfile.ingest`, `docker/caddy/trust-site-*.caddy`, `docker/entrypoint.sh`, `compose.yaml`, `compose.production.yaml`, the `ci.yml` HAProxy step, `compose-profile.md` §2.3 and §2.4, `contabo-deployment-plan.md` §4.2 and R-16, and `ci-cd.md` §4.2 (P-1 to P-11).
**Scope:** a static review of configuration. I ran nothing. I relied on A10's measured results in `compose-profile.md` §2.3 and §2.4 where they cover a point.

### Verdict

**OK WITH CHANGES.** The HAProxy gate closes S3-3 and most of S3-4 and decision 2. The trust chain is sound, and the gate's `429` and `413` keep the ingest invariant. One Medium finding is left: the residual below. It stops capture for everyone, so it must be closed before hosted go-live (G-7). It needs a config change, not a redesign. The Low findings are small config and policy fixes for A10. The policy fixes must be in the script before it is installed at C3.

### What is right

- **Placement and trust chain (point 1).** Keying on the forwarded address at the hop is sound. That is the only point where the address has already been resolved once, by one piece of code, in every trust mode. The key comes from the front's single `X-Forwarded-For`, and HAProxy uses it only when the request carries the front's `X-WT-Edge`. The front always overwrites `X-WT-Edge` from the secret file. The front strips the client's own spellings first in modes `secret` and `none`. In mode `ip`, ingest strips them at its own entry, and HAProxy reads only the hyphen form. The front also always overwrites `X-Forwarded-For`:
  - with the host proxy's value on a verified request, where the host proxy overwrites, not appends (`contabo-deployment-plan.md` §4.1);
  - with `{remote_host}` when the request is unverified or the mode is `none`;
  - with `{client_ip}` under `trusted_proxies_strict` in mode `ip`.

  So a client cannot choose its key without the edge secret. `req.hdr_ip(…,-1)` reads the same right-most entry that the app reads. An unsigned caller, which can only be a container on `internal`, is keyed on its own address. It also reaches ingest unverified, so it gains nothing. `haproxy` and `ingest` publish no port and sit only on `internal`, so nothing outside the Compose project can reach them. There is no stats socket and no runtime API, so the secret loaded as an ACL pattern cannot be read back.
- **Ingest invariant for `429` and `413` (point 2).** Both answers are decided from the address, the counters or `Content-Length`. They come before any part of the path is looked at, so they are the same for every key. The `429` reproduces the app's `429` (status, `Content-Type`, `Cache-Control`, `Retry-After`, body). A10 compared it header by header. HAProxy adds no request header: there is no `forwardfor`, no `set-header` and no `Via`. The `X-WT-Edge` it passes on is stripped by ingest before PHP runs. HAProxy writes header names in lowercase to ingest. That changes nothing stored, because Go and the CGI conversion already discard the client's header-name case. `track-sc0` sits on `http-request`, so the cap counts requests even over the front's pooled connections, as A10 measured.
- **Limits (point 3).** A cap of 20 in flight with a backstop of 1200 per minute, keyed per IPv4 /32 and IPv6 /64, matches the app limiter's keying. It leaves room for provider fan-in (S3-1). The backstop is twice the app's limit, so it acts only while the app limiter fails open (decision 2). Small slow bodies are held in HAProxy and never reach PHP.
- **No traffic log.** HAProxy keeps no traffic log (RD-1). The capture key never reaches HAProxy's output.
- **Config delivery (point 4).** The config comes out of the signed app digest and goes through a one-shot copy. That avoids a bind mount, a top-level `configs` and a third image to sign. The one-shot runs with no capability, no network and a read-only root, and the real HAProxy mounts the volume read-only. The label carrying the app digest forces a reload on every upgrade. HAProxy runs as its image's non-root user, and `group_add: 33` only lets it read the one group-33 file mounted into it, the secret. With the policy pins in S3-21, I accept this design.
- **Front header handling (point 6).** On the hop, `compression off` is the right fix for Go's added `Accept-Encoding`. The two-handler `Via` pattern keeps the client's own `Via` exactly, or sends none. On proxied capture answers, `-Server` and `-Via` are applied as deferred deletes, so the answer looks the same whether PHP, ingest's Caddy or HAProxy produced it. `expose_php = Off`, so no `X-Powered-By` header is sent.

### Findings

| ID | Severity | Area | Finding | Fix |
| -- | -------- | ---- | ------- | --- |
| S3-17 | Medium | Concurrency (residual of S3-4) | A body larger than HAProxy's buffer, or a chunked body that has not finished, reaches ingest before it is complete. It then holds a PHP thread for up to 15 s. With a cap of 20 and 4 ingest threads, one address can keep all 4 threads busy at the cost of about 32 KiB per request every 15 s. That stops capture for every user (the app stays up). | Decision below: keep the general cap at 20, add a capped class for incomplete bodies, raise `INGEST_NUM_THREADS` to 8. Gate G-7. |
| S3-18 | Low | Availability on deploy | `lb_try_duration` on the front only retries when the dial to `haproxy` fails. While `ingest` is recreated, HAProxy answers its own `503`: the DNS name disappears, the server goes to MAINT, and nothing tunes `retries` or `retry-on`. The §2.3 statement "held across an ingest restart" no longer holds with HAProxy in the path, and rev. 9 did not measure it again. | In `resolvers docker`, set `hold nx`, `hold refused`, `hold timeout` and `hold obsolete` to about 30 s. Add `retries 20`, `retry-on conn-failure empty-response` and `option redispatch` to `backend ingest`. Measure again: a capture sent during an ingest recreate gets `200`. If any `5xx` remains, it gets a fixed body (S3-19). |
| S3-19 | Low | Answer shape (point 2) | HAProxy's own `400`, `408`, `500`, `502`, `503` and `504` are its default HTML pages. HAProxy decides them before or apart from the key, so they cannot reveal a key. They still differ from what the front or ingest gives for the same condition. For example, a slow small body gets HAProxy's HTML `408`, while a slow large body gets ingest's answer. That depends on body size, not on the key. One case can depend on the key: `timeout server 60s`. Under a database stall, the stored path (transaction and locks) can pass 60 s while a dropped key answers at once, so HAProxy's `504` versus `200` would reveal the key's state. | Add an `http-errors` section, or `errorfile`, giving every status HAProxy can produce a fixed JSON body and the ingest headers. Make the `408` byte-identical to what ingest gives for a body it cuts at 15 s. Set `timeout server` above ingest's worst case (15 s body + PHP `max_execution_time` + S3-10's `lock_timeout`), so the app's own deadline always answers first. Add these answers to conformance check (1)'s header-by-header comparison. |
| S3-20 | Low | Hosted headers (point 6) | The shared host Caddy is also a Go `reverse_proxy`. Its transport adds `Accept-Encoding: gzip` to any capture without one, and it appends `Via: 1.1 Caddy`. The front's fix cannot undo this, because it runs after the host. "Host Caddy unchanged" therefore means hosted captures show two headers the sender never sent. This is a fidelity problem (TM-01), not a leak. `Server: Caddy` from the host is on every hosted answer, `429` from HAProxy included, so it is uniform and accepted. | Make the change in the Webhook Trap `in.` vhost on the host Caddy only. It does not touch the global options or other apps. Add `transport http { compression off }` and the same `@via` two-handler pattern (`contabo-deployment-plan.md` §4.2, `Via` row). The PO installs it at C5. Run conformance check (7) at C8 through the real host proxy, with no `Accept-Encoding` and no `Via` sent. |
| S3-21 | Low | Policy draft (point 5) | The P-3 rule "`user` only as `0:0` on `haproxy-config`" would refuse `engine` (`10001:10001`) and `backup` (`postgres`). The draft does not pin what makes the root one-shot safe. A uid 0 container owns every `root:33 0440` secret and every root-owned file in any volume it is given. So a signed-but-wrong Compose file could mount `secrets`, `storage` or `ledger` into it and still pass. Per-service networks and secrets are also unchecked. | Change P-3: `user` absent except `engine` `10001:10001`, `backup` `postgres`, `haproxy-config` `0:0`. Add the pins below, each with a TM-T146 fixture. **haproxy-config:** `entrypoint` exactly the `cp` line; `volumes` exactly `[haproxy_config]`; no `secrets`, `env_file`, `environment` or `command`; `read_only: true`; `network_mode: none`. **Volume `haproxy_config`:** read-write only on `haproxy-config`, read-only only on `haproxy`, mounted nowhere else. **Secrets:** per-service allow-list; `haproxy` only `edge_proxy_secret`; `engine` and `haproxy-config` none. **Networks:** `ingest` and `haproxy` exactly `[internal]`. **haproxy:** no `env_file`; `environment` only `WT_CAPTURE_MAX_CONN` and `WT_CAPTURE_MAX_RATE`. **read_only:** `true` on `ingest`, `haproxy` and `haproxy-config`. **Routing:** `ingest` has `PROXY_TRUST_MODE=secret` and `WT_ROLE=ingest`; `app` has `INGEST_MODE=proxy` and `INGEST_UPSTREAM=haproxy:8080`, so the gate cannot be bypassed silently. |
| S3-22 | Low | Secret spread (point 4) | `reverb`, `horizon`, `scheduler` and `migrate` mount the edge secret only because the entrypoint checks it whenever the mode is `secret`. None of them reads it. Whoever holds the secret can forge verified hops: choose `source_ip` and the scheme, and rotate `X-Forwarded-For` to escape every per-address limit (HAProxy's and the app's). `reverb` is the second process that faces the internet. | In the entrypoint, check the secret only where it is used: the HTTP front (`app`) and `ingest`, selected by `WT_ROLE`. Remove `edge_proxy_secret` from `reverb`, `horizon`, `scheduler` and `migrate` in both profiles, and enforce that with the S3-21 per-service secrets list. `root:33 0440` stays as it is for `app`, `ingest` and `haproxy`. |
| S3-23 | Info | Secret file shape | The entrypoint's `grep -Eq '^[0-9a-f]{64}$'` passes when any one line matches. HAProxy loads every line of the file as an accepted pattern, while Caddy's `{file.…}` reads the whole file. HAProxy does not check the file itself. An empty or different secret makes it key every signed hop on `app`'s address, one bucket of 20 for everyone, which fails closed for availability. | Check the whole file: exactly 64 lowercase hex characters, with an optional final newline. Conformance check (1) already catches a wrong key in `haproxy`. |
| S3-24 | Info | HAProxy sizing | `maxconn 1024` × two 32 KiB buffers (64 MiB), plus a 200k-entry table (about 20 MiB by estimate), plus the process, is close to the 96m limit. An OOM kill of `haproxy` stops capture. `maxconn 1024` is also the instance-wide ceiling on captures in flight (about 52 addresses at the cap). That is a distributed denial of service, which per-address limits do not address, and I accept it. `WT_CAPTURE_MAX_CONN` set to an empty string expands to `""` (the `-` form only applies when the variable is unset), so HAProxy refuses to start and takes `app` down with it. | Use `size 50k`, which is ample with a 120 s expiry, or a 128m limit. A10 measures the table memory once. Use the `:-` form (`${WT_CAPTURE_MAX_CONN:-20}`) if the pinned HAProxy supports it; otherwise the entrypoint docs say "unset, never empty". |

### Decision on the residual (S3-17)

**Not lowering the cap, not raising the threads alone, and not accepting as is.**

- **Lowering the cap below the thread count does not work.** With a cap of 3, one address still holds 3 of 4 threads, and two addresses hold all of them. It would also refuse ordinary fan-in from a shared provider address (S3-1), where many deliveries are legitimately in flight at once.
- **Raising the threads alone does not work.** The threads would have to exceed the cap (more than 20), which the 384m ingest budget does not allow once bodies are read.
- **Accepting it as is does not work.** It leaves "one address stops all capture", the exact outcome S3-4 was raised for.

The problem is narrower than "per address". Only a request whose body is incomplete when HAProxy hands it on can hold a thread for its body time. Complete bodies, which are most webhooks, hold a thread for milliseconds. So A10 gates that class on its own:

1. **Classify the request after `wait-for-body`.** A request is *partial* when it has no `Content-Length` (chunked), or when `req.body_len` is less than the declared length. To compare them, store `req.body_len` in a variable and use `req.hdr_val(content-length),sub(txn.blen) gt 0`.
2. **Per-address cap for partial requests: 2 in flight** (`WT_CAPTURE_MAX_STREAM`). Track them with a second counter (`track-sc1` on a second table keyed the same way). Over the cap, the request gets the same `429`.
3. **Global ceiling on partial requests: `INGEST_NUM_THREADS − 2`.** Send partial requests to a second backend, `ingest_stream`, on the same server. Answer the same `429` when `be_conn(ingest_stream)` reaches the ceiling, not a queue `503`. This keeps at least 2 ingest threads free for complete bodies whatever any number of addresses do.
4. **`INGEST_NUM_THREADS` 8.** A thread waiting on a body costs little. A10 confirms the peak under 384m with 6 threads each reading a 5 MiB body, and otherwise raises the limit or lowers the threads to 6 with a ceiling of 4.
5. **The general cap stays at 20 and the backstop at 1200/min.**

**Result.**

- One address can hold at most 2 of 8 threads, for at most 15 s at a time.
- No combination of addresses can delay a complete-body capture.

**Residual I accept.** A handful of addresses together can fill the partial class. Large or chunked captures then get the `429` for everyone while it lasts, and providers retry `429`. The `429` depends on load only, never on the key, so the invariant holds.

**Before hosted go-live.** Conformance gains three checks:

- (9) 3 partial senders from one address: the 3rd gets the `429`.
- (10) Partial senders from many addresses up to the ceiling: a complete-body capture still answers at once.
- (11) A large body sent at full speed is unaffected.

R-16 is updated to this residual. A10 assigns the IDs with the other HAProxy checks.

### Answers to the six points, in short

1. **Placement and trust chain.** Sound. No spoofing without the edge secret, and no direct reach from outside the project. S3-22 limits who holds the secret.
2. **Ingest invariant.** Holds for `429` and `413`. HAProxy adds no stored header. HAProxy's other statuses and the `504` timing need S3-19.
3. **Limits.** 20 / 1200 accepted. Residual decided above (S3-17).
4. **Config delivery.** Accepted with the S3-21 pins. Secret mounts narrowed by S3-22.
5. **Policy draft.** Needs the S3-21 changes before the script is written.
6. **Hosted headers.** Front correct. The host vhost needs S3-20. `Server` is uniform.
