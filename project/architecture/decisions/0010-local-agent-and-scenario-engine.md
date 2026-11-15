# ADR-010: Local Agent and Client-Side Scenario Engine

**Status:** Accepted (decision 46; A09 verdict §12.10). Text as of revision 5. §2 language choice approved earlier (decision 44)
**Date:** 2026-11-14
**Author:** A05 (Software Architect)
**Decision source:** decision 32 §1 (agent in the MVP, new ADR, Q3 decided here), decision 35 (A-7, A-13, C-1 to C-4, P4, P6, P10), decision 36 (Phase 4 starts before Gate 2), decision 38 (PV-1 to PV-14 adopted), decision 39 (PO-1 with A09's changes, PO-A to PO-G, HD-1 to HD-10).
**Security review:** A09 `project/security/threat-model.md` (verdict ACCEPT WITH CHANGES, §9.1). Threat ids (TM-nn, S-n) are cited where a rule comes from it.
**Authority on judge rules:** A01 `spec/scenarios-and-judge.md` (rule IDs quoted below). This ADR fixes the mechanics; it never loosens a product bound.

Items marked **[A10]** depend on hosting and packaging.

---

## Context

Concept B "Proving Ground" sends scenario deliveries (Once, Twice, Shuffled, Late, Retry after error, Tampered, Unsigned, Burst, Slow budget) to a developer's handler and judges the result. That requires:

- millisecond timing (500 ms Twice gap, 2 000 ms retry gap, ≤ 20 ms Burst spread, PLAN-3, BURST-2);
- reaching `localhost` on the developer's machine;
- re-signing payloads with provider secrets that never reach the hosted server (concept-impact §4, R12);
- running offline in CI with exit codes (EXIT-1);
- reproducible verdicts (A11 §4, D-1 to D-12).

A Laravel queue job on the hosted server can do none of the first four. ADR-002 keeps Laravel for the server; this ADR adds a second runtime and states where each stage runs.

The Go prototype (`spikes/proving-cli`, decision 28) is evidence of what is buildable and of the security choices that held up in A09 reviews (decisions 29, 30, 33). It is never merged.

## Decision

### 1. One engine, one binary, three roles

The scenario engine is written once and shipped as one binary, `webhooktrap` (C-1). It runs in three roles:

| Role | Where | Network guard mode | Secrets from | Started by | Talks to the server with |
| ---- | ----- | ------------------ | ------------ | ---------- | ------------------------ |
| **Agent** (`webhooktrap agent`, `webhooktrap prove`) | developer machine | `local`: loopback plus locally declared exact `host:port` entries (TGT-1, TGT-2) | agent environment / OS keychain | a person, paired to a workspace | agent key → `/agent/v1` |
| **CI** (`webhooktrap test`) | CI runner | `local` | CI secret variables | CI config; no server needed | CI token → `/api/v1/runs` (upload only) |
| **Server runner** (`webhooktrap serve-run`) | its **own VM** on hosted (HD-5); a `runner` container on self-host | `public` on hosted (ADR-006 am. 1 §2); `allowlist` on self-host with B′ (ADR-006 am. 1 §3) | none on hosted; on self-host, instance secrets fetched per job (§12.4) | long-running process that **pulls** server-run jobs | runner token → `/runner/v1` |

The server runner is a remote, agent-like process (§12). It holds **no database, Redis or Reverb credentials** (S-11, HD-5).

The app also needs two pure, **network-free** operations of the engine:

- `plan` for the run draft preview (delivery count, `n/a` reasons, CAP-1 refusal) so the draft and the executor can never disagree;
- `verify` on self-host when instance secrets are configured (SIG-1 checks).

It does **not** run them inside the app container. They run in a separate **`engine` service** (A10 `compose-profile.md` §2, ENG-1): the same signed image, entry point `webhooktrap serve-sandbox --socket=/run/engine/engine.sock` (name confirmed here; health check `webhooktrap sandbox-ping`), reached by the app over a **Unix socket** in a volume shared only by `app` and `engine`. The hardened app container (`cap_drop: ALL`, `no-new-privileges`, holding `/run/secrets/*`) cannot build a network-free sandbox inside itself; a separate service can. Same design on hosted and self-host.

**Engine invocation rules (S-11, TM-18, TM-74, ENG-1, ENG-2):**

1. **Isolation.** The `engine` service has `network_mode: none` (loopback only), runs as a non-root user with a read-only root filesystem and a small `tmpfs`, and holds **no credentials**: no env file, no secrets, no database or Valkey variables, no storage volume. Its only shared resource is the socket volume (socket mode `0660`, group shared with the app). Policy P-3 allows `network_mode: none` on this service only (A10).
2. **Calls.** One request per connection: a length-prefixed JSON frame `{op: 'plan'|'verify', input, secret?}`; the reply is NDJSON. The engine exposes **only `plan`, `verify` and `ping`**: no `execute`, probe, reset or file-reading code path is reachable from `serve-sandbox` (one entry point, as RN-7 for `serve-run`), and it reads no configuration file from its working directory. Laravel never invokes a shell; it writes to the database itself.
3. **Stateless and bounded (ENG-2).** Each call runs in a **fresh short-lived child** (the server re-executes itself per call) or, if A08's spike shows that is too slow, in a handler that keeps **no state across calls**; either way TM-T149 asserts no state survives a call. The engine enforces the limits itself, independent of the caller: **5 s wall clock per call, memory cap per call, a fixed concurrency cap** (excess calls refused, not queued without bound), input size cap. Container limits (A10: 128–192 MiB, 0.25 CPU, `pids_limit` 128) are the outer bound.
4. **Secrets (self-host `verify` only).** The one instance secret a `verify` call needs travels in that call's frame over the socket, lives in the child's memory only, and is **dropped when the call ends** (the child exits, or the buffer is zeroed); never in argv, environment, queue payloads, files or logs (TM-75). Hosted has no instance secrets, so hosted `verify` with secrets does not exist.
5. **No input logging (ENG-2).** The engine logs call counts, durations, op and refusal codes only; never inputs, outputs, secrets or selections.
6. **Output is untrusted.** The app validates every reply against its JSON Schema and size limit before use, maps errors to fixed codes, and never renders engine text as HTML (as for agent output, TM-16).
7. Plan previews are rate-limited per principal, capped in concurrency on the app side as well, and cached by (selection hashes, scenarios, pack version).

Fallback if A08's spike rejects the socket design: the same rules with Landlock network rules inside the engine (kernel with Landlock ABI ≥ 4; unverified on the VPS, A10). Instance-secret `verify` ships on self-host only after one of the two is settled (ENG-1).

The judge is **never** reimplemented in PHP. Laravel stores, projects and serves what the engine produced.

### 2. Language and distribution: Go (Q3)

| Option | For | Against |
| ------ | --- | ------- |
| **Go ★** | Single static binary for macOS, Linux, Windows (amd64/arm64) from one toolchain; precise monotonic timers and a controllable HTTP transport (barrier release, pre-opened connections, pinned dial); pure-Go drivers for Postgres, MySQL and SQLite (`sql:` probes, PRB-2); the prototype already implements guard, signing for four providers, tamper and run files, and passed three A09 rounds | Second language in the repo; contributors need Go; npm distribution needs a wrapper package |
| TypeScript on Node | npm-native (`npx`), shared types with the React front end, official provider SDKs available in-process | Needs a Node runtime on the machine and in CI images; timer jitter under load makes the 20 ms Burst bound harder; `sql:` needs native drivers; prototype evidence would be thrown away |
| Rust | Best timing and binary size | Slowest to build for a small team; no prototype evidence |
| PHP (reuse Laravel) | One language | Needs PHP on every developer machine; weak fit for a long-running agent and concurrent barrier sends |

**Decision: Go. Approved by the PO in decision 44** (Q3 closed). C-4 is resolved here: `sql:` probes stay in the MVP using pure-Go drivers (no cgo). If binary size or licence review rejects one driver, that database falls back to `cmd:` and the spec's fallback applies.

**Distribution and supply chain (A09 §7.7, TM-100, TM-101):**

1. **npm:** `@webhooktrap/agent` with per-platform packages as `optionalDependencies`. No install-time download, no `postinstall` script, so npm's lockfile integrity covers the binary. Published from CI with npm provenance and trusted publishing (no long-lived npm token); 2FA on the org; `webhooktrap`, `@webhooktrap/*` and obvious typosquats reserved **[A10, HD-3]**.
2. **GitHub releases:** reproducible builds (`-trimpath`, pinned toolchain); `SHA256SUMS` signed (Sigstore keyless from the release workflow, or a minisign key held offline); SLSA provenance attestation.
3. **Install script:** HTTPS only; verifies checksum and signature before moving the binary into place; a manual verification path is documented.
4. **Homebrew tap:** formula pins version and SHA-256.
5. macOS notarisation and Windows code signing **[A10]**.
6. The pairing command shown in the UI pins a major version: `npx @webhooktrap/agent@1 pair XXXXX-XXXXX`.
7. **No self-update in the MVP.** "Agent too old" points to the user's install channel.
8. CI docs pin the engine version and its checksum.
9. Locked dependencies (`go.sum`, `composer.lock`, front-end lockfile), `govulncheck` and `composer audit` in CI, review of new dependencies, GitHub Actions pinned by commit.

The runner image (`webhooktrap/runner`) comes from the same release pipeline as the app image, so the engine version always matches (§8).

### 3. Where each stage runs (D-1)

```
plan(selection, scenarios, pack, seed, config) → Plan          pure    engine
execute(Plan, ports) → Observations                             impure  engine (agent / CI / server runner)
judge(Plan, Observations) → Verdict                             pure    engine
render(Verdict, profile) → text | JSON | PR Markdown            pure    engine (CLI) ; server renders web from structured fields
```

- The **agent** (or CI, or server runner) runs all three stages and uploads the run document (A-24 shape, `run.schema.json`, hosted profile) plus incremental observations.
- The **server never judges.** It validates uploads against the schema, stores them, broadcasts timeline events and renders pages.
- `webhooktrap rejudge run.json` recomputes the verdict byte-identically from Plan + Observations (JSON-4) **on the local run file**. The hosted copy holds no raw probe values (PV-1, PV-8), so it cannot be rejudged at Level 2.

**What the server checks on an uploaded or agent-posted run (S-5, TM-17):** schema conformance; counts recomputed from `findings[]`; `exit_code` consistent with status and `fail_on` (EXIT-1); **Level 1 consistency only** (status codes, latency against budget, over-budget flags, scenario results that depend only on Level 1 observations). Nothing more is claimed.

**Provenance on every run view (TM-17):** executor (`agent`, `ci`, `server_public`, `server_allowlist`), source (`web`, `cli`, `ci`), and the agent name, CI token name or "Webhook Trap server runner". The UI never says "verified" (string-table check).

**Finding text is rendered by the server (TM-16).** The hosted profile of the run document carries, per finding, only `type`, severity, level, event type, scenarios, evidence references, `likely_cause.rule` and structured `params` holding display values. The server renders title, what, why, fix, snippet and likely-cause text from the finding `type` and the **pack version** named in the run. `citation` is chosen by the server from the pack's citation list for that type; any agent-supplied URL is ignored. The hosted profile has no field for agent prose. Free strings that remain (agent name, probe names, fixture paths, target path) have grammars and length caps (contracts.md §5.11), are escaped, and snippets render in a plain `<pre>`. The local CLI render keeps its own prose.

### 4. Pairing (P10: browser-shown code in the MVP)

```
Browser (owner)                 Server                                   Agent (terminal)
  │ POST pairing code ─────────▶│ issue code, bind to principal + workspace
  │◀── code 7KQ2M-XR9TD ────────│
  │                             │◀── POST /agent/v1/pair {code, public key,
  │                             │        name, version, os/arch, proto range}
  │                             │ verify code (HMAC lookup): unexpired, unused, workspace live
  │                             │ create Agent(workspace, public key, needs_reconfirm=true); mark code used
  │◀ Echo pairing.completed ────│──▶ {agent_id, server origin echo, workspace label, guest flag}
  │ "Confirm in your terminal"  │       agent prints the origin IT connected to, then label,
  │                             │       then "Join? … [y/N]"
  │                             │◀── POST /agent/v1/confirm (authenticated)   on "y"
  │                             │     guest: revoke the previous agent now (A-13: replace)
  │◀ Echo agent.status ─────────│
  │ "Connected · maya-mbp · macOS · SHA256:ab12…  Not your machine? Revoke"
```

Rules:

1. **Code (S-1, TM-30).** Crockford base32 without `I L O U`, **10 symbols (≈ 50 bits)**, shown as `XXXXX-XXXXX`, case-insensitive, hyphen optional on input. Single use; TTL **10 minutes** (the protocol carries `expires_at`; the UI never hard-codes it). One live code per principal; issuing a new one voids the old. Stored as **HMAC-SHA-256 with a server key** (not a bare SHA-256) and looked up by that HMAC. Failed submissions: per source address 10 per 10 minutes, then a 1-hour block (**IPv6 addresses are keyed on their /64 prefix, A09 §12.1; the same keying applies to every per-address limit in this package**); a global failure-rate alarm and circuit breaker; a per-code void after 5 failures as a secondary control. One generic `invalid_code` error for unknown, used, expired and voided codes.
2. **Binding.** The code is bound to the principal that created it (user or guest session, ADR-003 amendment 1) and to that principal's workspace. The agent joins the **workspace**, not a project (P4).
3. **Agent identity (S-3, TM-32).** Ed25519, generated locally, **one key pair per server origin**, so one hostile or compromised server cannot reuse an agent's identity elsewhere. Storage: macOS Keychain, Windows Credential Manager/DPAPI, Linux Secret Service when available; otherwise a file `0600` in a `0700` directory (Windows: ACL to the current user). The agent **refuses to load a key file readable by others**. The key is never printed and never in run files. Rotation in the MVP = unpair and re-pair: `webhooktrap agent unpair` deletes the local key and calls `DELETE /agent/v1/agent`. The server stores no reusable agent secret.
4. **Agent authentication (TM-33).** Each connection starts with a challenge-response. The server issues a 32-byte nonce, single use, valid 60 s, bound to `agent_id`. The agent signs the domain-separated message `"webhooktrap-agent-auth/v1" ‖ server origin ‖ agent_id ‖ nonce ‖ expires_at` (exact encoding in contracts.md §5.2). The server origin is the one pinned at pairing, so a signature made for server A never verifies at server B. Access tokens last 15 minutes, are stored hashed, and revocation is checked on every request.
5. **Confirmation on the agent side (S-2, TM-31, TM-41; PO-E).** Required. After `pair`, the agent prints, in this order: the **server origin it connected to** (from its own configuration, never from the server's response), then the sanitised workspace label, then *"Join? Only continue if you created this code in your own browser just now. [y/N]"*. Every server-provided string passes the display sanitiser (control, bidi and Unicode tag characters stripped, decision 33) and is length-capped. Until the person answers `y`, the agent is `needs_reconfirm`: it receives no jobs and cannot fetch captures. On `N` the agent unpairs. **Expiry (RE-1, R-E):** an agent that is still unconfirmed 10 minutes after `pair` is revoked by the server with reason `unconfirmed` (event `AgentUnconfirmedExpired`). Before the first confirmation the server stores no mirrors from the agent (secret names, declared targets) and returns no job or check counts to it: the only calls that succeed are `confirm` and `DELETE /agent/v1/agent`. `--yes` may skip the prompt only for **account** workspaces; a guest workspace always prompts, and a non-TTY agent pairing into a guest workspace fails.
6. **Detective controls.** After confirmation the browser shows agent name, OS and key fingerprint with **"Not your machine? Revoke"**. Account owners also get an email "New agent paired" (event-catalogue.md §2).
7. **Per-workspace cap.** At most 20 active agents per account workspace (TM-43); one per guest workspace (A-13).
8. **CLI-initiated pairing (post-MVP, P10).** Reverse flow with `/pair?code=…` (device-authorisation pattern). It needs its own A09 model (code phishing) before it ships.

### 5. Transport between agent and server

- **Agent → server:** HTTPS JSON API under `/agent/v1/…` (contracts.md §5). Observation uploads are idempotent (keyed by `run_id` + `seq`); the final document is first-write-wins (§6).
- **Server → agent:** a **nudge** on the agent's private broadcast channel (`private-agent.{agentId}`, Reverb) that says only "job available" with a job id. The agent then **pulls** the job over HTTPS. No job content travels over the broadcast bus.
- **Fallback:** when websockets are blocked, the agent long-polls `GET /agent/v1/jobs/next?wait=25`. Correctness never depends on the websocket; it only lowers latency (PERF-3). Long-poll is served by a non-blocking path or under a concurrency cap that answers `503` with `Retry-After` (TM-40) **[A10, A08]**. Observation size and `seq` are capped (≤ deliveries × phases); every agent endpoint has a payload size limit.
- **Heartbeat:** the agent reports state every 20 s; the server marks it `disconnected` after 60 s without a heartbeat or socket presence.
- **No listening socket (TM-54).** The agent opens no listening socket in the MVP. CI checks this per platform.

### 6. Job integrity (S-4, TM-34)

A job is what the server asks an agent to do. Integrity rests on four independent layers:

1. **Authenticated pull over TLS.** Jobs are fetched, never pushed. The agent pins the server origin recorded at pairing, **follows no redirects** on API calls, and uses system roots with no insecure option on hosted. Self-host over plain HTTP is allowed only when the server URL is loopback or the user passes an explicit flag with a warning.
2. **Content addressing and validation.** A job references captures by id **and** `body_sha256`; the agent fetches bodies separately and refuses a mismatch. Every job and capture is validated against its schema and size limits (body ≤ 5 MiB, bounded header count and JSON depth) before use.
3. **Local authority over targets.** See §6.3 below.
4. **No secrets in agent jobs.** Agent jobs never carry signing secrets, probe commands or reset commands. Probe and reset come only from the local `webhooktrap.yml`, confirmed once per (agent, file hash) (PRB-11); `--yes` never covers a changed file hash (TM-52). A job may reference a probe configuration only by the hash the agent reported.

**Server-signed jobs: not adopted for the MVP.** A compromised server holds the signing key, so signed jobs only help against a TLS-terminating proxy on self-host, which is the operator's own trust choice (S-4).

#### 6.3 Remotely started jobs (S-2, TM-50, TM-56, TM-110)

A job is **remotely started** when the run was started from the browser, the JSON API or MCP. Locally started runs (`webhooktrap prove` in the terminal) keep TGT-1.

1. A remotely started job may target only a **locally declared** target: the target in `webhooktrap.yml`, or an entry added with `webhooktrap targets add`. Loopback ports that were never declared are declined `target_not_allowed`, even though TGT-1 calls them local.
2. On top, a **per-target approval** is recorded on the agent, keyed on (server origin, workspace, scheme, host, port), with the path shown. It is combined with the NAME-4 side-effect acknowledgement.
3. Approval needs an **interactive TTY** prompt or an explicit `webhooktrap targets approve`. `--yes` applies to locally started runs only and **never** pre-approves remote targets or NAME-4. A non-TTY agent declines a first remote job with `needs_confirmation`.
4. Every remotely started run prints one local line before the first delivery: requester workspace, run number, target, delivery count.
5. Verify jobs for captures of a workspace the agent joined less than 10 minutes ago run only after the first local confirmation of a remote run (so a just-joined agent is not a silent signature oracle).
6. The server can neither add to the declared list nor approve a target. A claim clears approvals (ADR-003 amendment 1 §4).
7. Agents fetch only captures referenced by an unexpired job **issued to them**, and post signature verdicts only within a verify job issued to them, once per job (TM-35, TM-36). Every `/agent/v1/runs/{runId}/…` and `/jobs/{jobId}/…` call checks that the run or job belongs to the authenticated agent and is in the right state (TM-37).

### 7. Allowed targets

| Role | Allowed | Refused always |
| ---- | ------- | -------------- |
| Agent / CI, locally started | Loopback by resolved address (TGT-1); locally declared exact `host:port` entries (`webhooktrap targets add`, TGT-2); a warning on every run for non-local entries | `0.0.0.0`, `::`, mixed resolution, provider domains (prototype deny list), credentials in URL, redirects (TGT-4); link-local and metadata addresses even when declared (TM-55) |
| Agent, remotely started | **Declared** targets only, each approved (§6.3) | as above, plus undeclared loopback ports |
| Server runner, hosted | Public unicast addresses only, ports 80, 443, 1024–65535 | the refused set below; any host resolving to one of these |
| Server runner, self-host, B′ off | Same as hosted | Same |
| Server runner, self-host, B′ on | Hosted list **plus** operator allow-list entries (host/CIDR + required port or range), only for users holding the B′ permission | Metadata and link-local always; guests always; the instance's own services (ADR-006 am. 1 §3) |

**`public` mode refused set (A09 §7.1, TM-70).** Resolve once; refuse if **any** resolved address falls outside public unicast; pin; re-check the peer address at dial; no proxy; no redirects; TLS verification on. The refused set is a **data table with its own tests**, covering every special-purpose IPv4 and IPv6 registry range, IPv4-embedded IPv6 forms and transition prefixes, CGNAT, benchmarking, multicast, broadcast, and every cloud metadata address and name the hosting provider exposes. A09 reviews the table at implementation.

**`local` mode.** Declared hostnames are resolved and pinned like any other (TGT-5); link-local and metadata are refused even when declared (TM-55).

**Re-send header allow-list (TM-51).** Deliveries carry only: content type, the provider's event headers named in the pack, the signature header as the scenario sets it, and `Webhooktrap-*`. `Host` and `Content-Length` are recomputed. Captured forwarding, host and hop-by-hop headers, and redacted placeholders, are never re-sent. Server-runner deliveries add an identifying `User-Agent` with a link to the abuse page.

All roles: resolve once at run start and pin the addresses for every delivery (TGT-5); re-check at dial; 30 s request timeout; response bodies read up to a cap and hashed. One guard implementation with three modes; the prototype's `Guard` (check, then re-check at dial) is the reference.

### 8. Protocol versioning (A-12)

- **Protocol major:** integer `proto` (starts at `1`). Every message carries it. Within a major, changes are additive only; receivers ignore unknown fields **except** in objects marked closed (`additionalProperties: false`), where unknown fields are rejected (secret-leak guard).
- **Negotiation:** at hello, the agent (or runner) sends `proto_min`, `proto_max` and its semver; the server answers with the chosen `proto` or `agent_too_old` with `min_agent_version` (R55: "needs 0.9 or later" comes from this field, never from copy). A runner with a version different from the app's is refused.
- **Schemas:** every message and the run document have a JSON Schema under `contracts/agent/v1/`, `contracts/runner/v1/` and `contracts/run/v1/run.schema.json`, in the repository. Server (Pest) and engine (Go tests) each validate the other's fixtures in CI (consumer-driven contract, A-12).
- **Run document:** `schema_version` (semver) independent of `proto`; the server accepts the current and previous minor.
- **Deprecation:** the server supports the current and previous protocol major for at least one minor release cycle and announces removal in the hello response (`deprecation` field, sanitised by the agent).

### 9. Offline CI mode

- `webhooktrap test` reads fixtures from `webhooktrap/fixtures/…` and `webhooktrap.yml`, runs plan → execute → judge locally and exits per EXIT-1. No server, no account, no network beyond the target.
- `--upload` sends the finished run document to the project with a **project CI token** (write-only: upload runs, read nothing). Without `--upload`, nothing leaves the runner (TT §1.4.5).
- **CI token handling (TM-60, TM-61).** Read from the environment variable `WEBHOOKTRAP_TOKEN` only; there is no flag, and passing a token as an argument is refused. Masked in all output, including encoded forms. Token values carry a kind prefix and a checksum so secret scanners detect leaks (contracts.md §1.1). Per-token rate limit, document size cap, revocation, optional expiry; provenance shows the token name.
- **Visibility (TM-63, PO-C).** CI uploads are **private** whatever the project default, unless the command passes `--share`, which creates a share token (contracts.md §3.2) and prints the share URL.
- **Re-uploads (TM-64).** `run_id` must be a valid UUIDv7 created by the engine. First write wins: same document hash → `200` with the existing run; different hash → `409`.
- **Untrusted pipeline context (TM-62).** The engine detects privileged fork-pull-request contexts it knows (for example GitHub Actions `pull_request_target`), warns, and refuses `cmd:` probes unless `--allow-untrusted-config` is passed. Docs: run `webhooktrap test` with secrets only on trusted events.
- CI never pairs. CI tokens are not agent identities and cannot receive jobs.
- The engine strips every known secret variable from the environment of probe and reset commands (prototype behaviour, TM-53) and masks secrets in output, including encoded forms for secrets ≥ 16 characters (SCR-4).

### 10. Determinism constraints (A11 §4)

| ID | Constraint | How it is enforced |
| -- | ---------- | ------------------ |
| D-1 | Three pure stages; Plan and Observations serialised in the run file; `rejudge` is byte-identical | Package boundaries: `plan` and `judge` packages import no I/O packages (Go arch test) |
| D-2 | Two clocks behind one `Clock` port: wall (signature timestamps, Late offset) and monotonic (latency, offsets). No `time.Now()` outside the adapter | Lint rule (forbidigo) + arch test |
| D-3 | One PRNG seeded per run (`seed` uint64, printed in `--verbose`, stored in the run file, accepted as `--seed`) for ids, jitter and any later permutation. Same seed + fixtures + scripted responses ⇒ same Plan, byte for byte | Golden plan tests |
| D-3a | The probe label salt is a **separate** 128-bit value from the OS CSPRNG, new for every run, never derived from the seed, never uploaded or logged (PV-3) | A09 tests T3, T4 |
| D-4 | All waits (gaps, Burst barrier, settle polling, async check, connect retry) go through a `Scheduler` port; unit tests use a virtual scheduler | Arch test |
| D-5 | Timing assertions use margins; boundaries tested only in `judge` with synthetic observations; Burst asserted from the recorded dispatch spread | Test strategy (A11) |
| D-6 | `Transport` port with fault injection (status, latency, refused, TLS error, reset mid-body, 3xx, hang) | Fake transport in unit tests; real transport once per fault against a loopback server |
| D-7 | `Probe` port (sql, cmd, url) and `Reset` port, scriptable in tests | Fake probe |
| D-8 | Provider packs are versioned data files with a JSON Schema; the judge reads packs only; a synthetic `test` pack covers every branch. Packs also hold the finding text templates and citations the server renders (§3) | Arch test: no provider name literals in `judge` |
| D-9 | CI contract job feeds re-signed, Late, Tampered and Unsigned requests to each provider's official verifier | Separate CI job (Node, plus PHP where relevant) |
| D-10 | Server: fake clock for expiry and quotas, fake broadcaster, raw-socket ingest tests, contract tests on agent and runner schemas | Pest |
| D-11 | Renderer is pure over (Verdict, terminal profile); golden files per profile | Golden tests |
| D-12 | Reference handlers (Express, Laravel) with buggy/fixed flags form the core regression suite | A11 |

Additional engine constraints taken from A01:

- **PLAN-3 gaps:** each gap ≥ stated value and ≤ stated value + 100 ms on the monotonic clock.
- **BURST-2:** connections opened before the barrier; spread ≤ 20 ms on loopback, recorded by the agent. *This differs from the prototype, which disables keep-alive and dials per request.*
- **TGT-5:** connect-only retry every 500 ms for up to 5 s before a delivery is `unreachable`.
- **PRB-6/7:** probe polling every 250 ms ± 25 ms via the scheduler.
- **CAP-1/CAP-2:** 50 deliveries, 15 minutes wall time per run.
- **PERF-5:** engine overhead p95 ≤ 50 ms per delivery.

### 11. What the server stores from a run (C-3, R12)

*Follows A09's C-3 review, PV-1 to PV-14, adopted by the PO in decision 38.*

| Data | Server (hosted profile) | Agent run file (local profile) |
| ---- | ----------------------- | ------------------------------ |
| Plan (ordered deliveries, scenario, source capture, signing kind, mutation path, expected-state rule) | yes | yes |
| Signing secrets, secret values in any form | **never** | never (env/keychain only) |
| Signature header values sent | never | never (JSON-2) |
| Bodies sent | never (referenced by capture id + SHA-256) | by SHA-256 |
| Response bodies | content type, size, SHA-256 only | same; raw only with `--keep-responses` locally |
| Probe outcomes per reading and entry | `changed`, `matches_expected`, `flagged`, `failed`, `unsettled` (PV-1) | same, plus raw values |
| Probe display values | only PV-2 forms: small count `−9999…9999`, or a `#` + 6-hex label (12-hex after a collision, PV-4); PV-5 opt-in forms only for owner runs (PV-5, PV-11) | real values (sanitised, 200 chars) |
| Probe label salt | **never** (PV-3, PV-8) | yes |
| Probe definitions, `database:`, resolved template text, stderr, reset command and output, settle/stability/async intermediate readings | **never** (PV-8); only the outcomes `async_write`, `probe_unstable`, `reset_failed` | `webhooktrap.yml` hash; values as configured |
| Probe names (`as:`) | yes, max 48 chars, grammar-checked, disclosed as visible (PV-9) | yes |
| Finding prose (title, what, why, fix, snippet, likely-cause text, citation) | **not uploaded**; rendered by the server from `type` + pack version (TM-16) | yes, rendered locally |
| Secret names | env var name only; **the last-4 hint is never uploaded** (S-8, PO-D). The agent may print the last 4 in its own terminal | — |

Mechanics required by A09's C-3 review:

1. **One choke point (PV-6).** The engine converts every reading to its display form in a single, unit-tested function **before** serialisation. Finding `measured`, scenario `reading`, timeline lines, PR comments and MCP output are rendered from structured fields that already hold display values. The prototype's `eval.go` pattern must not be carried over.
2. **Policy at pairing (PV-12, TM-90).** The server advertises `probe_display_policy` (`strict` | `opt_in_allowed` | `opt_in_disabled`) in the pairing response and every hello. Hosted is compiled to `strict` with owner opt-in; it is never read from configuration. The agent applies the **stricter** of the server policy and its local config.
3. **Guest flag (PV-5).** The hello response marks guest sessions; the agent then ignores `show:` with a local note.
4. **Collision switch (PV-4).** If two distinct values in a run share a 6-hex label, the agent switches the whole run to 12-hex labels and re-sends readings already uploaded (`readings.replace`, contracts.md §5.6).
5. **Server validation (PV-11, TM-38).** Every display value is validated against the PV-2 grammar (and PV-5 only for owner runs whose header declares the entry opted in). A non-conforming value is stored as `#invalid`, counted in a metric, never logged.
6. **Two schema profiles.** `run.schema.json` has a `profile: local | hosted` discriminator; the hosted profile has no field able to hold a raw value, probe definition, salt, stderr or finding prose.

### 12. Server runner as a remote process (HD-5)

#### 12.1 Shape

`webhooktrap serve-run` is a long-running process that behaves like an agent with a narrower role:

| | Agent | Server runner |
| - | ----- | ------------- |
| Identity | Ed25519 key per server origin, challenge-response | **runner token** (`wt_rnr_…`), issued on the host only (`php artisan webhooktrap:runner:issue`), stored hashed, revocable, **source CIDR binding required on hosted** (RN-1), optional on self-host |
| Base path and guard | `/agent/v1`, `agent` guard | `/runner/v1`, `runner` guard (separate guard; no shared middleware stack with agents or browsers) |
| Jobs it may pull | jobs issued to that agent in its workspace | jobs with `executor ∈ {server_public, server_allowlist}`, any workspace, assigned to it at accept |
| Nudge | Reverb `private-agent.{id}` + long-poll fallback | **long-poll only** (no Reverb credentials or socket) |
| Captures it may fetch | captures referenced by an unexpired job issued to it | same rule |
| Secrets | local env / keychain | none on hosted; self-host B′ only: instance secrets fetched per job (§12.4) |
| Credentials it holds | its private key | the runner token, read from a file or environment variable on its VM, never argv; no DB, Redis or Reverb credentials |

**Source binding and rotation (RN-1, RN-2; TM-130).**

- On hosted the runner token **must** carry `allowed_cidrs` (not null; the issue command refuses to create a hosted token without it). A request from any other address is refused with the same response as an unknown token. In addition the host proxy allows the `/runner/v1` path **only from the runner VM's egress address** [A10], so a stolen token is useless from elsewhere. On self-host the binding is optional; the runner network's CIDR is recommended.
- Rotation: at every rebuild of the runner VM and at least every 90 days. Two tokens may be live for the same runner during a rotation window (the old one is revoked by the operator when the new one is confirmed in use); the issue command shows token age and warns past 90 days.

**Mode ceiling set locally (RN-3; TM-131).** The runner's **widest** network mode is set in its **local configuration** (hosted: `public`; self-host: `public` or `allowlist` plus its local allow-list file), never by the server. A job carries a requested `guard` and, for `allowlist`, entries; the job can only **narrow** what the local configuration allows, never widen it: a job asking for a mode wider than the ceiling, or for allow-list entries absent from the local allow-list, is refused (`failed: guard_exceeds_ceiling`) before any delivery. The **hosted runner refuses every `allowlist` job**, whatever the server says. Test: TM-T131.

**Never the app's own addresses (RN-4; TM-132).** In `public` and `allowlist` modes the runner **always refuses**, in addition to the §7 refused set: the resolved addresses of the runner-API origin it was configured with (re-resolved at each job and at dial), and, on self-host, the addresses of the runner's own Compose networks and the services on them. The refusal holds even for a declared allow-list entry. On the app side the runner's source address receives **no trust**: it is not in `TRUSTED_PROXIES`, it has no limit exemption, and it is not treated as internal for any check; the token is the only thing the runner presents. Test: TM-T132.

Message schemas for jobs, observations, `readings.replace` and the final document are **the same** as the agent's (contracts.md §5); only the base path, the auth and the job selection differ. Observations go through the same recording service, so server runs emit the same timeline events (event-catalogue.md §3.3).

#### 12.2 Flow

```
browser ─ POST /p/{key}/runs {target: server} ─▶ app: checks (account only, kill switch, quotas, limits §12.3)
                                                    create Run(Queued, executor=server_public) + RunJob(pool=runner)
runner ─ GET /runner/v1/jobs/next?wait=25 ────────▶ app: oldest eligible job; 204 if none or kill switch off
runner ─ POST /runner/v1/jobs/{id}/accept ────────▶ app: assign job.runner_id; Run → Running
runner ─ GET /runner/v1/captures/{id}/request ────▶ app: only captures referenced by that job
runner ─ POST /runner/v1/runs/{id}/observations ──▶ app: same recording service as agents
runner ─ POST /runner/v1/runs/{id}/final ─────────▶ app: first write wins; Level 1 checks; RunFinished
```

The Laravel `replay` queue no longer runs the engine. A `replay` job (ADR-007 queue kept) only expires jobs that no runner accepted (`job_expires_at`, then Aborted `runner_unavailable`) and records metrics.

#### 12.3 Placement and limits

- **Hosted (decision 39):** the app runs on the shared Contabo VPS (HD-1); the runner runs on **its own small VM** (HD-5), with its own egress IP, inbound closed, and a **required** egress firewall denying private, link-local, CGNAT, ULA, metadata and the app host's private addresses at the network layer (A09 §7.1 item 6) **[A10]**. It reaches the app only over the public HTTPS runner API. Running it beside 1.x production would defeat the firewall, which is why it does not share the VPS.
- **Self-host:** a `runner` container on its own Compose network with a route to the app's runner API and outbound only, no route to Postgres, Redis or Reverb (A09 §7.2 item 5) **[A10]**.
- **Container hardening:** non-root, read-only root filesystem, `no-new-privileges`, all capabilities dropped, CPU, memory and pid limits.
- **Limits enforced by the app at queue and at job issue (A09 §7.1 item 5, TM-71, TM-76):** 2 concurrent server runs per principal; a daily server-run cap per principal; a per-destination-host delivery rate across all principals; 50 deliveries (CAP-1); 15 minutes (CAP-2); 30 s per request and a response read cap enforced by the engine. Proposed values are in `open-questions.md` §4.
- **Kill switch (HD-10).** `server_replay_public` (hosted: environment, not UI). When off, the run form hides the server target, the API answers `422 server_runs_disabled`, and `jobs/next` returns `204`. Revoking the runner token stops a runner at its next request.
- **Who may start server runs (PO-B).** Hosted: **accounts only** at launch; guests use the agent. B′: users with the B′ permission only.

#### 12.4 Instance secrets on self-host (B′ with signing)

Instance secrets stay in the app's encrypted store. The runner fetches them with `GET /runner/v1/jobs/{jobId}/secrets` under these conditions (RN-6, TM-133), all checked by the app on the call:

1. The job was **accepted by this runner** (`job.runner_id` equals the caller) and the run is **Running** (not Queued, not final); executor is `server_allowlist`.
2. The initiator's **B′ permission is re-checked at fetch time** (not only at queue time): revoked or lapsed permission → `403` and the run is aborted.
3. The response contains **only the secrets for the run's provider that are bound to the allow-list entry the target matched**, or unbound secrets for that provider; nothing for other providers or other entries (**target-bound secrets**).
4. **One successful fetch per job**, enforced atomically: the app claims the fetch by setting `run_jobs.secrets_fetched_at` with a conditional update (`… WHERE secrets_fetched_at IS NULL AND runner_id = caller AND run Running`) before it builds the response, so two concurrent calls cannot both succeed (schema §6, A09 §12.9). A claimed fetch whose response is lost is not retried; the job fails `secrets_unavailable`. A second request returns `409` and raises an alert event (`RunnerSecretsRefetchBlocked`); a request after the run is final, by another runner, or without accept is refused and audited.
5. The response is `Cache-Control: no-store`, never logged, and every fetch, success or refusal, writes an audit row (TM-73). The runner holds the values **in memory only** for the job, with core dumps disabled for the process (`RLIMIT_CORE=0`, `PR_SET_DUMPABLE=0` or the platform equivalent) and no swap-backed buffers where the platform allows.

On hosted this endpoint does not exist (404): hosted holds no signing secrets.

#### 12.5 Server runs are Level 1 only (A01 C-8, RUN-11)

Confirmed. A server run has **no Level 2**, on hosted and on self-host:

- The runner has no route to the user's database or machine and no probe or reset configuration: a runner job carries none, and the runner refuses to execute a probe or reset section if one appears.
- **`serve-run` never reads probe or reset configuration from anywhere (RN-7, TM-134, R-P).** The mode does not read `webhooktrap.yml` (or any file in its working directory), nor probe/reset settings from environment variables or flags; the probe and reset executors are **not compiled into the code path** reachable from `serve-run` (one entry point, no shared loader that could pick up a local file). A test starts `serve-run` with a `webhooktrap.yml` containing probes in its working directory and with probe variables in its environment, and asserts that none executes (TM-T134).
- The run is created with `levels = {1}`; the schema forbids anything else for `executor <> 'agent'` (schema.md §6), and the runner API refuses Level 2 content (contracts.md §6, `422 level2_not_allowed`).
- Findings are Level 1 types only; scenarios that need state end `inconclusive` (`needs_probe`), and RUN-7 lists "state, because there is no probe".
- **Signing on hosted:** no secret (§12.4). Captured signature headers are re-sent unchanged where the pack allows it; anything that needs a fresh signature shows **Needs secret** (SIGN-2). This is why hosted server runs are offered only for providers without a signed timestamp (PO-6).
- **Signing on self-host B′:** instance secrets may sign (§12.4); the run is still Level 1 only.
- The draft preview shows "Level 1 only: the server can't read your database" for a server target (A03 copy).

## Consequences

Positive:

- One engine for agent, CI and server-side runs: the draft preview, a CI run and a browser run can never judge differently.
- The hosted server never touches developer networks or secrets; localhost works for hosted and self-hosted users alike.
- The server runner holds no backend credentials and can be moved, firewalled or killed independently of ingest.
- Verdicts are reproducible from the local run file, which makes golden tests and bug reports cheap.

Negative:

- A second codebase and release channel (Go), with its own CI, signing and supported-platform matrix.
- The app image carries the engine binary, run as a separate network-less `engine` service for `plan` and `verify` (one more Compose service and a socket protocol to version); the runner is a second image and, on hosted, a second VM to operate.
- Agent-authored verdicts are not attested; shared runs and CI uploads are claims by their owner, shown with provenance.
- Two scrubbing implementations (engine for fixtures, server for share views), kept in step by shared rule data and a shared test corpus (schema.md §6).
- Pairing and remote runs each add one terminal step (PO-E).

## Open items

| # | Item | Owner |
| - | ---- | ----- |
| 1 | ~~Pairing code length, TTL, attempt limits; join confirmation~~ Resolved by A09 S-1, S-2 | — |
| 2 | ~~Agent key storage; release signing~~ Resolved by A09 S-3, §7.7. Notarisation and code signing accounts | A10 |
| 3 | ~~Server-signed jobs~~ Not adopted (S-4) | — |
| 4 | ~~PRB-3 label scheme~~ PV-1 to PV-14 adopted (decision 38) | — |
| 5 | ~~Network-free sandbox for `plan`/`verify` in the app container~~ Separate `engine` service (§1, ENG-1, ENG-2); socket protocol and per-call child vs stateless handler confirmed in A08's spike; Landlock fallback. Runner VM and egress firewall on hosted; runner network on self-host | A10, A08 |
| 6 | Agent heartbeat and presence costs on Reverb at PERF-1 load; long-poll capacity for agents and runner | A08 spike |
| 7 | ~~Secret last-4 hint~~ Never uploaded (S-8, PO-D) | — |
| 8 | Runner design (§12): runner token, `/runner/v1`, per-job secret fetch on self-host | A09 re-check |
| 9 | ~~Server runs Level 1 only (A01 C-8)~~ Confirmed in §12.5 | — |
| 10 | ~~Q3 agent language~~ Go, PO-approved (decision 44) | — |

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §4 rule 1: 10-symbol code, HMAC storage, limits | A09 §9.1-1, S-1, TM-30; PO-F |
| 2026-11-14 | 2 | §4 rule 5: required `[y/N]` with server origin first; `--yes` account workspaces only; guest replacement at confirmation | §9.1-2, S-2, TM-31, TM-41; PO-E |
| 2026-11-14 | 2 | §4 rule 3: Ed25519, one key per server origin, storage and permission check, unpair = rotation | §9.1-3, S-3, TM-32 |
| 2026-11-14 | 2 | §4 rule 4: domain-separated challenge with server origin and agent id | §9.1-4, TM-33 |
| 2026-11-14 | 2 | §4 rules 6–7: fingerprint + "Revoke", "New agent paired" email, agent cap | §9.1-5, TM-43 |
| 2026-11-14 | 2 | §5: no listening socket; long-poll capacity; payload caps | §9.1-6, TM-40, TM-54 |
| 2026-11-14 | 2 | §6.3: remote jobs to declared targets only; approvals keyed on origin/workspace/target; TTY or `targets approve`; local line per run; verify after first confirmation; job-scoped capture fetch and verdicts | §9.1-7, TM-35, TM-36, TM-37, TM-50, TM-56, TM-110 |
| 2026-11-14 | 2 | §6: origin pinning, no redirects, schema/size validation; layer 5 not adopted | §9.1-8, S-4, TM-34 |
| 2026-11-14 | 2 | §3: Level 1 consistency only; provenance; server-rendered finding prose; pack-only citations | §9.1-9, S-5, TM-16, TM-17; PO-1 |
| 2026-11-14 | 2 | §1: engine without credentials, argv array, stdin/stdout, no network for `plan`/`verify`, limits, preview rate limit | §9.1-10, S-11, TM-18, TM-74, TM-75 |
| 2026-11-14 | 2 | §7: refused-set data table, ports, `local`-mode pinning, header allow-list, User-Agent | §9.1-11, §7.1, TM-51, TM-55, TM-70 |
| 2026-11-14 | 2 | §2: supply-chain items 1–9 replace "[A09]" | §9.1-12, §7.7, TM-100, TM-101 |
| 2026-11-14 | 2 | §9: CI token from env only, prefixes and checksum, fork-PR guard, private uploads unless `--share`, first-write-wins | §9.1-13, TM-60..TM-64; PO-C |
| 2026-11-14 | 2 | §11: last-4 hint never uploaded; finding prose not uploaded; open items 1, 2, 3, 7 closed | §9.1-14, S-8; PO-D |
| 2026-11-14 | 2 | §1, §12: server runner as a remote agent-like process on its own VM, runner token, no backend credentials, limits, kill switch, accounts only | HD-5, HD-10, decision 39; PO-B; TM-71, TM-76 |
| 2026-11-14 | 3 | Status and §2: Go approved by the PO; Q3 closed | decision 44 |
| 2026-11-14 | 3 | §12.5 new: server runs Level 1 only on hosted and self-host; signing rules per profile; open items 9–10 | A01 C-8, RUN-11; PO-6 |
| 2026-11-14 | 4 | §4 rule 1 (and package-wide): IPv6 per-address limits keyed on /64 | A09 §12.1 gap, S-1 |
| 2026-11-14 | 4 | §4 rule 5: unconfirmed agents revoked 10 min after `pair` (`unconfirmed`); no mirrors or counts before first confirmation | RE-1, R-E |
| 2026-11-14 | 4 | §12.1: runner source binding required on hosted; host proxy allows `/runner/v1` only from the runner IP; rotation at rebuild and ≤ 90 days with two-token overlap | RN-1, RN-2, TM-130 |
| 2026-11-14 | 4 | §12.1: widest guard mode set in the runner's local config, jobs only narrow; hosted runner refuses `allowlist` jobs | RN-3, TM-131 |
| 2026-11-14 | 4 | §12.1: runner never dials runner-API origin or own-network addresses; app grants the runner IP no trust | RN-4, TM-132 |
| 2026-11-14 | 4 | §12.4: secrets endpoint only after accept by this runner, run Running, B′ re-check at fetch, target-bound secrets, single fetch (`409` + alert), memory only, no core dumps | RN-6, TM-133 |
| 2026-11-14 | 4 | §12.5: `serve-run` reads no probe/reset config from disk or environment; executors unreachable; test | RN-7, TM-134, R-P |
| 2026-11-14 | 5 | §1: `plan`/`verify` run in A10's separate `engine` service (`network_mode: none`, no credentials, Unix socket, `serve-sandbox`); rules 1–7 rewritten with ENG-2 (stateless per call, self-enforced limits, only `plan`/`verify`/`ping`, secret dropped after each `verify`, no input logging, output untrusted); Landlock fallback; open item 5 updated; Consequences aligned | A09 §12.9 ENG-1, ENG-2; A10 compose-profile §2 |
| 2026-11-14 | 5 | §12.4 item 4: single secrets fetch claimed atomically via `run_jobs.secrets_fetched_at` | A09 §12.9 RN-6 |
| 2026-11-14 | 5 | Status: Accepted (decision 46); no content change. The accepted ADR file and the ADR index are A00's | decision 46 |
