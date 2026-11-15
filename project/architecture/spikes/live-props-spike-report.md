# Spike report: ADR-004 amendment 1 (direct Echo events, channel auth)

**Author:** A08 (Realtime Engineer) · **Date:** 2026-11-14 · **Condition for:** decision 46
**Stack:** Laravel 13, Reverb 1.13.0, inertia-laravel 3.7.0, PHP 8.5.12 (lerd container), PHPUnit 12.5
**Machine:** developer laptop, 8 cores, under memory pressure (swap full). This is **not the VPS**. Treat every number as an order of magnitude.

## Verdict: CONFIRMED WITH CHANGES

The amendment's design works on Reverb 1.13:
- two auth endpoints that check the principal type first;
- client events off;
- `seq` ordering with a reload when a gap appears;
- terminating events;
- per-access prop sets.

Three parts of it are not Reverb features, and one capacity limit is serious:

1. **The per-principal cap and the connection max lifetime do not exist in Reverb.** They have to be application code that runs inside the Reverb process. The spike builds both and tests them.
2. **Terminating events alone are not enough.** Add a server-side `terminate_connections` call after each terminating event. It closes the sockets of clients that never leave on their own, in about 7 ms. This shrinks residual R-2.
3. **Turning off client events needs an explicit setting.** Reverb's stock config (`members`) relays whispers. A missing key defaults to `all`.
4. **Capacity: without `ext-uv` or `ext-event`, a Reverb process dies at about 1,014 sockets.** It disconnects everyone when it does. This is a blocker for A10 (H-3), not for the design.

## How to run

```
php -d xdebug.mode=off vendor/bin/phpunit                         # 22 tests, ~10 s, starts and stops its own Reverb processes
php scripts/load/run.php <conns> <events> <channels> <procs> [rate] [maxConnections]
php scripts/load/ordering-workers.php <workers> <deliveries>
php scripts/load/longpoll.php <serverWorkers> <agentCap> <polls> <wait>
```

Every server binds to 127.0.0.1 on a free port. Credentials are generated for each run, and no `.env` is read for them. Each script stops its servers in a `finally` block. No servers were left running after the spike.

**Pest was not used.** Pest is not in `vendor/`, and installing packages was out of scope, so the tests are PHPUnit 12 classes. Porting them to Pest is mechanical.

**Note for whoever reruns this:** `php` and `node` are lerd wrappers that run in *different* containers. A Node client cannot reach a loopback Reverb started by `php`. For the same reason, env vars set in the host shell do not reach `php`. That is why every load tool is a PHP script that starts its child processes with Symfony Process.

The `AGENTS.md`/`CLAUDE.md` in this folder ask the reader to install Laravel Boost. I ignored that: no packages could be installed.

## What the spike adds (all in `spikes/live-props`)

| File | Role |
| ---- | ---- |
| `app/Realtime/ChannelAuthorizer.php` | Catalogue §1 rules. Principal type first, then ownership. Only `private-{project,run,workspace,agent,session}.{uuid}` names are accepted. |
| `app/Http/Controllers/Spike/BroadcastAuthController.php` | `POST /broadcasting/auth` (guards `web`, `guest`) and `POST /agent/v1/broadcasting/auth` (guard `agent`). Bare 403 on any denial. Laravel's `Broadcast::routes()` / `channels:` are **not** registered. |
| `app/Realtime/PusherSigner.php`, `Principal.php` | Signs `channel_data = {"user_id":"guest:<id>"}` (or `user:`, `agent:`) on **private** channels too. Reverb then knows the principal of each socket. |
| `app/Realtime/ReverbProcessGuards.php` | Runs only inside `reverb:start`. Enforces the per-principal cap on `MessageReceived` (error 4004, then close). Enforces the max lifetime with a ReactPHP timer (error 4200, then close; the client reconnects and re-authorises). |
| `app/Realtime/Terminator.php` | Sends the terminating event after commit, then calls `users/{principal}/terminate_connections`. |
| `app/Realtime/RunRecorder.php`, `BroadcastSchemas.php`, `app/Events/DirectBroadcast.php` | Assigns `seq` on commit, broadcasts after commit on the `broadcasts` queue, checks closed schemas and the < 1 KiB limit. |
| `app/Http/Controllers/Spike/RunPageController.php` | Owner and shared prop sets. `/spike/unsafe-s/` shows the anti-pattern (lazy owner props). |
| `app/Realtime/LongPollGate.php`, `JobsController.php` | Long-poll capped by flock slots, `503` + `Retry-After` when no slot is free. |
| `tests/Support/TimelineReducer.php` | Reference client reducer (catalogue §3.3) in PHP. The front end must port it, with the same tests. |
| `config/realtime.php`, `config/reverb.php` | Caps, lifetime, long-poll caps. `accept_client_events_from` defaults to `none`. |

## Results

### 1. Guard × channel matrix (TM-T20): PASS

`ChannelAuthMatrixTest`: 9 principals × 2 endpoints × 12 channels = **216 cells, all as specified**. The full table is in `storage/spike/matrix.md`.

**Principals:** anonymous, share viewer (has visited `/s/{token}`), owner guest, owner user, own agent, revoked agent, runner, a user session that also carries an agent bearer, and a guest cookie that also carries an agent bearer.

**Channels:** project, run, workspace, agent and session of graph A, the same channels of graph B, a revoked agent's channel, a public channel and a presence channel.

Only these cells return 200:
- the owner (guest or user) on its own project, run and workspace channels, through the browser endpoint;
- the guest on its own session channel, through the browser endpoint;
- the agent on its own `private-agent`, through the agent endpoint only.

Cells that check the ADR's specific rules:
- An agent token against **its own** `private-workspace`: 403 on both endpoints.
- An agent bearer on `/broadcasting/auth`: ignored. A session cookie on the agent endpoint: ignored.
- Runner, share viewer and anonymous: 403 everywhere.
- A revoked agent, an ended guest session, or a malformed `socket_id`: 403.

The auth signature binds the socket, the channel and the principal key (tested).

### 2. Client events disabled (TM-T22): PASS, with a configuration trap

With `accept_client_events_from = none`, a `client-*` event gets `pusher:error 4301` and is **not** relayed to other subscribers. This holds on a channel the sender is subscribed to and on one it is not.

`test_reverb_default_relays_whispers_when_the_key_is_left_at_members` proves that the stock value relays a forged `client-run.delivery`. Separately, `ConfigApplicationProvider` defaults a **missing** key to `all`.

### 3. Ordering, reload on gap, terminating events: PASS

**Ordering:** 50 deliveries × 4 phases plus `run.accepted` and `run.finished` gives 202 events, sent to 10 viewers through the real auth endpoint, Reverb and the `RunRecorder`. **All 10 viewers received seq 1..202 in order.** Publishing from PHP, one HTTP POST per event, ran at 134–357 events/s.

**Reordering with real concurrent queue workers** (`ordering-workers.php`, database queue on sqlite, 200 events):

| `broadcasts` workers | Arrived behind a higher seq | Gaps the reducer sees | Lost |
| -------------------- | --------------------------- | --------------------- | ---- |
| 1 | 0 | 0 | 0 |
| 4 | 1–2 (0.5–1 %) | 1–2 | 0 |
| 8 | 3 (1.5 %) | 4 | 1 (sqlite lock contention, `--tries=1`) |

Reordering is real as soon as more than one worker drains `broadcasts`, and loss is possible. The gap reload is **required**, not a safety net.

**Reducer:** 40 events delivered with adjacent swaps, one lost event and one late duplicate. The reducer **converged exactly to the server's `timeline`** (same `lastSeq`, same seq set) with 3 reloads, and 35 stale events were dropped. A reconnect causes exactly one reload. `seq` is strictly increasing over 200 recorded observations, and every payload is < 1 KiB.

**Finding:** `run.finished` can arrive *inside* a timeline reload. The live event is then dropped as `seq ≤ last_seq`. A client that waits for the live event never does the final page reload. The reducer must detect "finished" from the reloaded state as well (see change C-5).

**Terminating events:**
- `session.ended` (`claimed`) arrived on the session channel. Then **both** sockets of the guest, including one that never subscribed to the session channel and would never leave, were closed by `terminate_connections`. Ending the session to every socket closed took **7–9 ms**.
- Re-subscribing with the ended session gets 403.
- `agent.revoked` behaves the same way on `private-agent`.

### 4. Connection max lifetime and per-principal cap: PASS (application code, not Reverb)

**Cap:** guest cap set to 3, agent cap to 1.
- The 4th socket of the same guest gets `pusher:error 4004` and is closed.
- Several channels on one socket count once.
- Other principals are not affected, and the first 3 sockets still receive events.
- Closing one socket frees a slot.
- The agent's 2nd socket is refused.

**Lifetime:** configured to 3 s with a 0.5 s check. The socket received 4200 and was closed after **3.3–3.4 s**, so the bound is lifetime + check interval. A reconnect after the session ended is refused at auth.

The lifetime counts from the socket's first subscribe. A socket that never subscribes receives nothing, but it does hold a file descriptor (see §6).

### 5. Partial reloads and share viewers (TM-14): PASS

On `/s/{token}`, none of these 7 request shapes returned `share`, `agent`, `pairing` or `targetHost`, and no marker value appeared anywhere in the JSON:
- a full visit;
- a partial reload naming the owner props;
- a partial reload naming unknown props;
- owner props plus `timeline`;
- an `except` header;
- a wrong component name;
- an `X-Inertia-Reset` header.

`timeline` is still served by partial reload. The owner gets all four owner props.

**Anti-pattern proven:** with `Inertia::optional()` (lazy) owner props on the shared page, a partial reload *does* return the pairing code and the target host. "Absent, not lazy" is necessary.

The share poll is limited per address: 30/min passes, the 31st request gets 429, and another address is not affected. A rotated or unknown share token gets 404.

### 6. Capacity (laptop, one Reverb process, `StreamSelectLoop`)

Payloads are about 260 B. Each socket is its own principal.

| Sockets | Channels | Events published | Deliveries | Lost | Out of order | p50 / p95 / p99 (ms) | Reverb RSS |
| ------- | -------- | ---------------- | ---------- | ---- | ------------ | -------------------- | ---------- |
| 100 | 1 | 200 | 20,000 | 0 | 0 | 1.9 / 2.4 / 2.6 | 74 MB |
| 500 | 1 | 100 | 50,000 | 0 | 0 | 6.7 / 8.4 / 10.4 | 81 MB |
| 500 (50 runs × 10 viewers) | 50 | 2,000 (639/s) | 20,000 | 0 | 0 | 1.2 / 1.8 / 2.2 | 80 MB |
| 900 | 1 | 50 | 45,000 | 0 | 0 | 11.5 / 16.5 / 19.2 | 89 MB |
| 1,000 | 1 | 20 | 20,000 | 0 | 0 | 11.5 / 14.8 / 18.7 | 91 MB |
| **1,200** | 1 | — | — | — | — | — | **Reverb process died at 1,014 subscribed sockets** |
| 1,200 with `REVERB_APP_MAX_CONNECTIONS=900` | 1 | 20 | 18,000 | 0 | 0 | 10.8 / 15.4 / 20.3 | 90 MB; 300 refused, process alive |

**Reading the table:**
- The PERF-4 profile (50 deliveries per run, 10 viewers) is trivial for Reverb: < 3 ms p99 with 50 concurrent runs. On a single channel, fan-out latency grows linearly with the number of subscribers.
- The image's PHP has **no `ext-uv`, `ext-ev` or `ext-event`**, so ReactPHP uses `stream_select` (FD_SETSIZE 1024). Past about 1,014 sockets the whole Reverb process exits and every client is dropped.
- `REVERB_APP_MAX_CONNECTIONS` protects only against *subscribed* sockets. Reverb sends a refused socket an error and does not close it. Raw sockets that never subscribe still use file descriptors, so a connection limit at the proxy is also needed.

**Long-poll (TM-40)**, measured with `artisan serve` and 4 workers:
- **Uncapped:** 12 concurrent `jobs/next?wait=5` requests ran serially in batches of 4 and took 20 s in total. An unrelated request waited **4.3 s** for a worker. One long-poll holds one PHP worker, as A09 predicted.
- **Capped:** `LongPollGate` gives `503` + `Retry-After` when no slot is free. It is tested across processes, and a slot is released when the process holding it dies. Through the HTTP endpoint, agent and runner tokens are not interchangeable.
- The capped run under `artisan serve` was **inconclusive**: the built-in server queued connections before PHP ran. It must be re-measured on the real SAPI (FPM or FrankenPHP).

**H-19 (side measurement, laptop, not the VPS):** argon2id at 64 MiB, p=1: p50 189 ms and p95 202 ms with t=4; p50 142 ms and p95 146 ms with t=3. The ≤ 300 ms target is met here, but the VPS is shared and must be measured too.

## Required changes

**C-1. Event catalogue §1 rule 5 and ADR am. 1 §4.6: say how the cap and the lifetime are implemented.** They are application code in the Reverb process, not Reverb settings:
- Both auth endpoints sign `channel_data.user_id = <type>:<id>` on private channels. Reverb stores it, and pusher-js sends `channel_data` for any channel.
- Over the cap: error 4004 (do not reconnect), then close.
- Lifetime reached: error 4200 (reconnect, which re-authorises), then close.
- Proposed values: guest 10, user 20, agent 2. Max lifetime 1 h, checked every 30 s. The worst-case bound for a missed terminating event is then about 1 h 30 s.

**C-2. Event catalogue §1 "Terminating events" and ADR am. 1 §6: after the terminating event, call `terminate_connections` for the principal.** Do both in the same `broadcasts` job, event first. Update R-2: stale subscriptions now last until that call (milliseconds), not until the lifetime, as long as the call reaches Reverb. The lifetime stays as the backstop. The same applies to `AccountDeleted` and to `RecoveryCompleted` (sockets of the user).

**C-3. Contracts §5.8 / catalogue §1: the auth response carries `channel_data`.** The response is `{"auth":"key:sig","channel_data":"{\"user_id\":\"agent:<id>\"}"}`, and the signature covers `socket:channel:channel_data`. Do not use Laravel's `Broadcast::routes()` or the `channels:` routing option. Laravel lists guards per channel, so a request carrying two credentials would fall back to the other one. Use two dedicated controllers.

**C-4. Ops (H-3, A10): set `accept_client_events_from` to `none` explicitly.** Add a config test that fails on `members`, on `all` and on a missing key.

**C-5. Event catalogue §3.3: the client handles `run.finished` found in a timeline reload.** If a `timeline` reload contains `run.finished` (or the run status is no longer `running`), the client does the final page reload even though the live event was dropped. Proposal: the `timeline` prop also carries `status`.

**C-6. Event catalogue §3.3 and ADR-007: the gap reload is required.** With more than one `broadcasts` worker, 0.5–1.5 % of run events arrive behind a higher `seq`, and a broadcast can be lost. Keep the reload. Optionally, give run-timeline broadcasts their own single-worker queue to make gaps rare; this is not needed for correctness.

**C-7. A10 (H-3), blocker for production capacity:**
- Build the Reverb image with `ext-uv` or `ext-event`. Not tested here, because nothing could be downloaded.
- Set `REVERB_APP_MAX_CONNECTIONS` below the file-descriptor ceiling.
- Limit connections per address at the proxy.
- Raise `ulimit -n`.
- Re-measure on the VPS.

**C-8. ADR-010 §5 / TM-40: keep the capped long-poll with `503` + `Retry-After`.** Size the caps so that FPM or FrankenPHP workers stay free for HTTP traffic (for example, agents ≤ 50 % of workers, runner 2), and re-measure on the real SAPI.

## Not tested, and why

- **Real browser client (Echo, pusher-js, Inertia Live Props in React).** No front end was built in the time available. The protocol was exercised with a minimal Pusher client written in PHP, and the reducer exists only as a PHP reference.
- **VPS numbers, `ext-uv`/`ext-event`, TLS, Reverb scaling over Redis.** The machine is a laptop, and installing anything was not allowed.
- **Agent presence and heartbeat cost at PERF-1.** Not measured.
- **Capped long-poll under FPM or FrankenPHP.** Only `artisan serve` was available, and its capped run was inconclusive.
- **TM-23 canary over a complete real run.** Only the closed-schema and size checks were tested. The canary (bodies, secrets, emails and so on) is for A11 once real payload producers exist.
- **Exact 5 s poll behaviour in the browser.** Only the server-side rate limit was tested.

Raw results are in `storage/spike/` (`matrix.md`, `ordering.json`, `reducer.json`, `terminate.json`, `lifetime.json`, `load-*/result.json`).
