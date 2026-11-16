# Threat model · Webhook Trap 2.0 Phase 4 architecture

**Author:** A09 (Security Engineer) · **Date:** 2026-11-14 · **Status:** DRAFT for PO decision (decision 37)
**Under review (all Proposed, by A05):** `architecture/decisions/0010-local-agent-and-scenario-engine.md`, `0003-amendment-1.md`, `0004-amendment-1.md`, `0005-amendment-1.md`, `0006-amendment-1.md`, `domain-model.md`, `schema.md`, `event-catalogue.md`, `contracts.md`, `open-questions.md`.
**Also read:** accepted ADR-003, ADR-004, ADR-006; decisions 32, 35, 36, 37; A09 C-3 review (PV-1 to PV-14); A01 `scenarios-and-judge.md` (TGT, NAME-4, PRB-11, SIG-3, SCR); A03 `user-flows.md` (FL-1, FL-2, FL-6 to FL-11); prototype `spikes/proving-cli` (`guard.go`, README "Known limits").
**Scope of this file:** design level. Attack paths and abuse specifics are kept in the private security folder, not in this repository.
**Re-check of A05 revisions 2 and 3, A10 operations drafts and decisions 38–44:** §12 below (2026-11-14), verifications of revisions 4, 5 and 6 in §12.9, §12.10 and §12.11. §12 supersedes §0 and §9 where they differ.

---

## 0. Summary

| Document | Verdict |
| -------- | ------- |
| ADR-010 Local agent and scenario engine | **ACCEPT WITH CHANGES** (14 changes, §9.1) |
| ADR-003 amendment 1 Guest pairing and claim | **ACCEPT WITH CHANGES** (6 changes, §9.2) |
| ADR-004 amendment 1 Direct Echo events | **ACCEPT WITH CHANGES** (5 changes, §9.3) |
| ADR-005 amendment 1 Raw target, origin, ingest | **ACCEPT WITH CHANGES** (6 changes, §9.4) |
| ADR-006 amendment 1 Server-side replay, B′ | **ACCEPT WITH CHANGES** (6 changes, §9.5) |
| `domain-model.md` | **ACCEPT WITH CHANGES** (§9.6) |
| `schema.md` | **ACCEPT WITH CHANGES** (§9.7) |
| `contracts.md` | **ACCEPT WITH CHANGES** (§9.8) |
| `event-catalogue.md` | **ACCEPT WITH CHANGES** (§9.9) |
| `open-questions.md` | **ACCEPT WITH CHANGES** (record S-1..S-14 resolutions, change PO-8, §9.10) |

No document is rejected. The architecture's core choices hold up: secrets stay on the developer's machine, the server never judges, jobs are pulled not pushed, the agent keeps authority over its targets, the ingest invariant hides key existence, and probe data follows PV-1..PV-14.

Where I disagree with A05's security conclusions:

1. **Per-target approval is not enough on its own against a pasted attacker code (S-2).** Loopback on any port is "local" under TGT-1, so a server-named job can reach any service listening on the developer's loopback, not just the handler. Jobs started from the browser, the API or MCP must be limited to targets the developer **declared locally**; approval comes on top (TM-50).
2. **Server-side `rejudge` of uploaded runs (ADR-010 §3) cannot work for Level 2.** PV-1/PV-8 keep raw probe values off the server, so the hosted copy cannot be rejudged. Only Level 1 consistency can be checked. Provenance must be shown instead (TM-17).
3. **Agent-authored text in the run document is an XSS and phishing surface on shared pages** (finding title, what, why, fix, snippet, citation URL). A05 treats the run document as data to validate for counts and display values only. The server must render finding prose from the finding type and the pack, and accept only pack citation URLs (TM-16).
4. **The agent can currently read every capture of its workspace and write a signature verdict on any of them.** Both must be scoped to jobs issued to that agent (TM-35, TM-36).
5. **Automatic claim (P11) opens a login-CSRF path that moves a guest's data into someone else's account,** and it lifts a guest agent's 24 h lifetime cap. Claim must be bound to a sign-in started from the same guest session, and a moved agent must reconfirm locally (TM-12, TM-42).
6. **The run id as share capability (PO-8) should not ship.** Run URLs are printed in CI logs and PR comments, and an unshare-then-reshare restores access to everyone who ever had the link. A separate rotatable share token is cheap now and expensive after URLs are in the wild (TM-15). This needs a PO decision.
7. **The 6-character pairing code is too short** for a code that can be guessed by anyone against all live codes at once. Per-code failure counters do not help against that. Use 10 symbols; the command is copied, not typed (TM-30).
8. **B′ on "authenticated" users is not enough.** It must be a per-user permission, impossible to enable while sign-up is open, and instance signing secrets must follow the same permission (TM-72, TM-73).

---

## 1. Method

- Assets (§2), actors (§3), trust boundaries (§4), then STRIDE per boundary (§5).
- Likelihood (L) and impact (I): **Low / Medium / High**, plus **Critical** impact for infrastructure compromise. Likelihood assumes the controls already in A05's drafts; impact assumes they fail.
- Each threat names the **control**, where it **lands** (ADR, schema, contract, implementation, test, ops), and a **verification test**. Tests are numbered TM-Tn and collected in §10 for A11.
- Existing A09 rules (PV-1..PV-14, decisions 29, 30, 33) are referenced, not repeated.

---

## 2. Assets

| # | Asset | Where | Why it matters |
| - | ----- | ----- | -------------- |
| A1 | Provider signing secrets | agent env / keychain; CI secrets; self-host `instance_secrets` | Forge valid webhooks to the developer's real handlers |
| A2 | Agent private key | dev machine | Impersonate the agent: read captures, write verdicts, receive jobs |
| A3 | Captured requests (bodies, headers, query) | Postgres, agent memory during runs | Customer data from providers (names, emails, amounts), sometimes credentials |
| A4 | Guest session token, browser session, OAuth identity | browser cookie; DB hash | Full access to a workspace |
| A5 | Pairing codes | browser, DB hash | Joins an agent to a workspace |
| A6 | Personal and CI tokens | user / CI secrets; DB hash | API read and run (personal), run upload (CI) |
| A7 | Run records and findings | Postgres, share pages, PR comments | Integrity of verdicts; display values (PV) |
| A8 | Developer's local network and loopback services | dev machine | Anything listening on loopback or allow-listed hosts |
| A9 | Server egress (hosted IPs) and internal network | hosting provider | SSRF to metadata/internal services; reputation of our IPs |
| A10 | Self-host private network | operator's network | B′ reach |
| A11 | Agent binary and npm wrapper | release channels | Code execution on every developer machine and CI runner |
| A12 | Reverb channels | Reverb | Live data stream to the right principal only |
| A13 | Redis / queue | internal | Job injection into PHP workers |
| A14 | Instance settings and audit log | self-host DB | Policy integrity |
| A15 | Availability (ingest, run pipeline, storage) | all | Free tier abuse, storage exhaustion |

## 3. Actors

| Actor | Capability | Trusted for |
| ----- | ---------- | ----------- |
| Guest | Creates sessions freely (rate-limited), 1 project, 1 agent, 10 runs, server runs (if P6 stays open to guests) | own session only |
| Account owner | Projects, agents, tokens, shares, uploads | own workspace; their verdicts are **claims**, not attestations |
| Share-link viewer | Anonymous or signed-in non-member with a link | nothing |
| Malicious webhook sender | Knows a capture URL; sends any method, headers, body ≤ 25 MiB | nothing |
| Malicious handler / target | Answers deliveries; for server runs, any public host | nothing |
| Attacker holding a pairing code | Either guessed a victim's code, or gives their own code to a victim | nothing |
| Compromised or malicious server | Hosted breach, or a hostile self-host instance an agent paired with | not trusted with secrets or with the developer's network |
| Self-host admin | Instance settings, DB and host access | the instance; not users' secrets on their machines |
| CI runner and its log readers | Runs the engine; logs may be public | own job only |
| Supply-chain attacker | Targets npm, GitHub releases, install script, dependencies | nothing |

## 4. Trust boundaries

```
                         Internet
   provider / anyone ──TB-1──▶ /c/{key} ingest ─┐
   browser (owner, guest, viewer) ──TB-2──▶ Laravel app (Inertia, policies) ──TB-8──▶ Postgres / Redis queue
   browser ──TB-3──▶ Reverb (private channels)                     │
                                                                    ├──TB-7──▶ engine process (plan, verify, serve-run)
   agent on dev machine ──TB-4──▶ /agent/v1 + private-agent.*       │              └──▶ public internet (P6) / allow-listed private (B′)
        └──TB-5──▶ loopback handler, allow-listed hosts, probes, local secrets
   CI runner ──TB-6──▶ /api/v1/runs (upload) ; CI ──▶ loopback service under test
   API / MCP clients ──TB-11──▶ /api/v1
   self-host admin ──TB-9──▶ /admin, instance settings, host
   release pipeline ──TB-10──▶ npm, GitHub releases, Homebrew, install script ──▶ dev machines, CI
```

| TB | Boundary | Crossing data |
| -- | -------- | ------------- |
| TB-1 | Internet → capture ingest | arbitrary HTTP requests |
| TB-2 | Browser → Laravel | sessions, forms, Inertia props, share views |
| TB-3 | Browser → Reverb | channel auth, broadcast events |
| TB-4 | Agent → server | pairing, challenge-response, hello, jobs, capture fetch, observations, verdicts |
| TB-5 | Agent → local environment | deliveries to the handler, probes, reset, secrets |
| TB-6 | CI → server, CI → target | run uploads, CI token |
| TB-7 | Laravel → engine process → network | argv/stdin, NDJSON, outbound deliveries (SSRF surface) |
| TB-8 | App → Redis / Postgres / Reverb backend | queue payloads, data |
| TB-9 | Self-host admin → instance | policy, allow-list, secrets |
| TB-10 | Release channels → user machines | binaries, npm packages |
| TB-11 | API / MCP → server | personal-token reads, run starts |

---

## 5. Threats per boundary (STRIDE)

Legend: S spoofing, T tampering, R repudiation, I information disclosure, D denial of service, E elevation of privilege.

### TB-1 Capture ingest

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-01 | I | Key existence or state (rotated, expired, over quota, oversized) inferred from the response | L | M | A-15 invariant as written: same status, headers and body for every case; only per-address 429 and proxy 413 differ. Timing difference between stored and dropped requests is accepted: keys are 71 bits, so a timing oracle only confirms a key the attacker already has | ADR-005 am. §4 (as is) | TM-T1 |
| TM-02 | D | Storage exhaustion through one known capture URL. Guests stop at 100 captures; **accounts have no capture quota** in the drafts | M | M | Per-project quota for accounts (captures per day and stored bytes), exceeded requests answered identically and dropped; global guest storage budget; both counted in metrics | ADR-005 am. §5, schema §3, impl. | TM-T2 |
| TM-03 | D | Large or slow bodies (hashing up to 25 MiB, slow upload) tie up workers | M | M | Proxy body limit 25 MiB, header/body read timeouts, per-address concurrent connection limit at the edge, ingest workers sized separately from UI | ops (A10 H-6), ADR-005 am. | TM-T3 |
| TM-04 | I | A signed-in or guest user who opens their capture URL in the browser sends their own session cookies, which get captured | M | M | Redact `Cookie` (as drafted); app cookies use the `__Host-` prefix; serving ingest from a separate host than the app is recommended to A10 so app cookies never reach ingest | ADR-005 am. §3, A10 | TM-T4 |
| TM-05 | S/T | Spoofed forwarding headers change `public_origin` or `source_ip` | M | L | `public_origin` from configuration (as drafted, S-6). `source_ip` taken only through the trusted-proxy chain; forwarded values stored as observed facts and labelled | ADR-005 am. §1, impl. | TM-T5 |
| TM-06 | E | Attacker-controlled body, headers, path or query rendered as markup in the app (stored XSS) or sniffed as HTML from the raw download | H | H | Bodies and headers rendered as text only (no HTML, Markdown or rich preview); raw download served `application/octet-stream`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox`; app-wide CSP without `unsafe-inline` scripts | contracts §3.2, impl., ADR-005 am. | TM-T6 |
| TM-07 | — (abuse) | Free capture URLs used as a drop box by third parties | M | M | Guest retention 24 h, abuse report route and takedown runbook, per-address guest creation limits | ops, ADR-003 am. | — |

### TB-2 Browser → Laravel

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-10 | S | Guest token theft or fixation | L | H | 256-bit token, stored hashed (as drafted); cookie `__Host-` prefix, `Secure`, `HttpOnly`, `SameSite=Lax`, `Path=/`, no `Domain`; never accepted from a URL or header; Laravel session id regenerated on claim and sign-in | ADR-003 am., impl. | TM-T10 |
| TM-11 | T | Cross-site `POST /new` replaces a victim's live guest session (they lose access) or creates sessions in bulk | M | L | CSRF token on `POST /new`; when a live guest cookie is present, redirect to the existing project instead of creating; per-address and global creation limits | contracts §3.2, ADR-003 am. | TM-T11 |
| TM-12 | E | **Claim into the wrong account.** A victim's browser is made to complete a sign-in for an account the attacker controls; automatic claim (P11) then moves the victim's guest project, captures and agent to the attacker | M | H | Claim only when the sign-in was **started from that guest session**: a single-use claim nonce stored server-side with the guest session at sign-in start, checked at the OAuth callback or the email-link landing in the same browser. OAuth `state` bound to the browser session. An email link opened elsewhere signs in without claiming. After claim, the toast names the account email | ADR-003 am. §4, impl. | TM-T12 |
| TM-13 | I | IDOR on `/p/{projectKey}/…`, `/r/{runId}`, `/r/{runId}.json`, raw download | M | H | Policies on every route (owner, or share capability for run views); not found and forbidden identical (IA5, as drafted); `project_key` is never a capability | contracts §1, impl. | TM-T13 |
| TM-14 | I | A share viewer asks Inertia for a partial reload of owner-only props (`share`, unscrubbed `selected`, `agent`, `pairing`) | M | H | Controllers build the prop set per access level; owner-only props are absent, not merely lazy, for `access: 'shared'`; partial-reload requests for unknown props return nothing | contracts §3.2, impl. | TM-T14 |
| TM-15 | I | **Share links leak and cannot be revoked properly.** Run ids are printed in CI logs and PR comments; the URL leaks via `Referer` to citation sites; unsharing then resharing restores the same URL for everyone who had it | H | M | Separate share token (≥ 128 bits, stored hashed) in the share URL; the run id alone never grants access; resharing issues a new token; `Referrer-Policy: no-referrer` on run pages, `strict-origin-when-cross-origin` elsewhere; share pages `noindex`. **PO decision** (PO-A) | ADR (new short amendment or contracts), schema §6, contracts §3.2 | TM-T15 |
| TM-16 | E/S | **Agent- or CI-authored text rendered to viewers.** The run document carries finding `title/what/why/fix.snippet`, `citation.url`, agent and probe names, fixture paths. A hostile owner shares a run whose text phishes or scripts viewers on the product's origin | M | H | Server renders finding prose from `type` + pack version + structured display fields; it does not store agent prose for display (or stores it only for the CLI round trip). `citation.url` must match the pack's citation list for that type. Free strings (names, paths) bounded and escaped; snippets shown in a plain `<pre>`; no `dangerouslySetInnerHTML`; `/r/{runId}.json` served with `nosniff` | ADR-010 §3/§11, contracts §5.7, schema §6, impl. | TM-T16 |
| TM-17 | R | Forged verdicts presented as authoritative (shared runs, PR comments) | M | M | UI never says "verified" (as drafted); every run view shows provenance: executor, agent name or CI token name, source. The server checks schema, counts, exit code and Level 1 consistency only; no Level 2 rejudge claim | ADR-010 §3, contracts §3.3 `RunHeader` | TM-T17 |
| TM-18 | D | Draft preview spawns one engine process per request | M | M | Rate limit `plan` per principal; process concurrency cap; timeout 5 s; memory limit; cache by (selection hashes, scenarios, pack) | ADR-010 §1, impl. | TM-T18 |
| TM-19 | E | Self-host: the first account on an internet-exposed fresh instance becomes admin | M | H (self-host) | Instance admin granted only from the host (console command or one-time setup token printed to the server log); never "first sign-up is admin" | ADR-008 note / ADR-006 am. §3, impl. | TM-T19 |

### TB-3 Browser → Reverb

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-20 | E | Guard confusion: an agent token authorises a browser channel (`private-workspace` of its workspace) or a browser session authorises `private-agent.*` | M | H | Two auth endpoints (browser guards; agent guard). Each channel callback checks the principal **type** first: `Agent` only for `private-agent.{its id}`; `User`/`GuestSession` never for `private-agent.*` | ADR-004 am. §4, event-catalogue §1 | TM-T20 |
| TM-21 | I | Stale subscriptions keep receiving events after session end, claim, agent revocation or unshare (Pusher protocol does not re-authorise) | H | L | Payloads are minimal by design (rule 3), so the residual is low. On revocation the server sends a terminating event and the client closes; Reverb connection max lifetime set (A08 to measure). Accepted residual | ADR-004 am., event-catalogue | TM-T21 |
| TM-22 | T | Client events (whispers) let one subscriber inject fake timeline events for other subscribers | L | M | Client events disabled for the Reverb app | ADR-004 am., ops | TM-T22 |
| TM-23 | I | Sensitive data in broadcast payloads | L | H | Rule 3 (as drafted) + JSON Schemas with `additionalProperties: false` + canary tests (PV T1 extended to every event) | event-catalogue §3, test | TM-T23 |
| TM-24 | D | Socket floods (guests, agents) | M | M | Per-principal connection cap; agent long-poll fallback; A08 capacity spike | ops, A08 | — |

### TB-4 Agent → server

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-30 | S | **Pairing code guessed** (S-1): an attacker's agent joins a victim's workspace and reads captures | M | H | §6 S-1 | ADR-010 §4, contracts §5.1, schema §5 | TM-T30 |
| TM-31 | S/E | **Victim runs an attacker's code** (S-2): the victim's agent joins the attacker's workspace; the attacker drives deliveries re-signed with the victim's secrets to the victim's machine | M | H | §6 S-2 | ADR-010 §4/§6, ADR-003 am. | TM-T31 |
| TM-32 | S | Agent private key stolen (S-3) | L | H | §6 S-3 | ADR-010 §4 | TM-T32 |
| TM-33 | S | Challenge relay: a hostile server the agent also paired with relays another server's nonce and obtains a valid signature | L | H | One key pair per server origin; the signed message is domain-separated and includes the server origin and agent id (§6 S-3) | ADR-010 §4, contracts §5.2 | TM-T33 |
| TM-34 | T | Jobs altered in transit or by a TLS-terminating proxy (S-4) | L | H | §6 S-4 | ADR-010 §6 | TM-T34 |
| TM-35 | I | **Over-broad capture fetch.** `GET /agent/v1/captures/{id}/request` serves any capture of the agent's workspace, so one rogue or stolen agent reads every captured body | M | H | Fetch allowed only for captures referenced by an unexpired job (run or verify) **issued to that agent**; access logged per agent | contracts §5.4, domain §5 | TM-T35 |
| TM-36 | T | **Forged signature verdicts.** Any agent of the workspace can mark any capture `valid` | M | H | Verdict accepted only for captures in a verify job issued to that agent, once per job; the UI names the agent that checked | contracts §5.8, schema `sig_checked_by_agent_id`, domain §4 | TM-T36 |
| TM-37 | T/E | Cross-agent writes: an agent posts observations, final documents, accepts or declines jobs for runs not assigned to it | M | H | Every `/agent/v1/runs/{runId}/…` and `/jobs/{jobId}/…` checks `run.agent_id`/`job.agent_id` = authenticated agent and run state; the final document is first-write-wins (same hash → 200, different → 409) | contracts §5.4–5.7 | TM-T37 |
| TM-38 | T | Hostile display values | — | — | PV-11 (as drafted) | — | PV T9 |
| TM-39 | I | Secret material in agent messages (S-8) | L | H | §6 S-8; closed schemas; `env_name` grammar `^[A-Z_][A-Z0-9_]{0,63}$`; hello `targets[]` hosts shown to the owner only, never on share views | contracts §5.3, schema §5 | TM-T39 |
| TM-40 | D | Long-poll `jobs/next?wait=25` holds a PHP worker per connected agent; observation floods | M | M | Long-poll served by a non-blocking path or capped concurrency with 503 + backoff; observation size and `seq` caps (≤ deliveries × phases); payload size limits on all agent endpoints | ADR-010 §5, A10 | TM-T40 |
| TM-41 | S | A hostile server puts terminal control or bidi characters in the workspace label or deprecation text to fake the confirmation prompt | M | M | Agent prints the **server origin it connected to** (not server-provided) first; all server strings pass the prototype's display sanitiser (control, bidi, Unicode tag characters stripped, decision 33) and are length-capped | ADR-010 §4.5 | TM-T41 |
| TM-42 | E | Claim lifts the 24 h cap of a guest agent: an agent paired through S-2 into an attacker's guest workspace becomes a permanent member of the attacker's account | L | H | On claim the agent is moved but marked `needs_reconfirm`: target approvals cleared, next hello shows the new label, the agent declines jobs (`needs_confirmation`) until a local confirmation | ADR-003 am. §4, schema §5 | TM-T42 |
| TM-43 | D | Unbounded agents per account workspace | L | L | Cap active agents per workspace (e.g. 20) | schema §5 | — |

### TB-5 Agent → local environment

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-50 | E | **Server-named loopback ports.** TGT-1 treats every loopback port as local, so a job can name a local service that is not the handler | M | H | Remotely started jobs (browser, API, MCP) may target only entries **declared locally** (the target in `webhooktrap.yml` or `webhooktrap targets add`); undeclared targets are declined `target_not_allowed`. Local CLI runs keep TGT-1 | ADR-010 §6.3, §7 | TM-T50 |
| TM-51 | T | Captured headers re-sent to the handler (forwarding, host, hop-by-hop) bypass handler-side checks | M | M | Re-send header allow-list per pack: content type, provider event headers, signature header (as the scenario sets), `Webhooktrap-*`; `Host` and `Content-Length` recomputed; never redacted placeholders | ADR-010 §7, packs | TM-T51 |
| TM-52 | E | A cloned repository's `webhooktrap.yml` runs commands through `cmd:` probes or reset | M | H | PRB-11 confirmation shows every command in full before the first run (as specified); `--yes` never covers a changed file hash; CI: §7.8 | spec PRB-11, ADR-010 §9 | TM-T52 |
| TM-53 | I | Secrets reach probe processes or output | L | H | Strip secret variables from probe env; mask encoded forms ≥ 16 chars (ADR-010 §9, as drafted) | ADR-010 §9 | PV T10 |
| TM-54 | E | The agent exposes a local listener reachable from web pages | L | H | Invariant: the agent opens no listening socket in the MVP | ADR-010 §5 | TM-T54 |
| TM-55 | T | An allow-listed hostname resolves to link-local/metadata or changes between check and dial (the prototype dials allow-listed names unpinned) | L | M | Allow-listed names are resolved and pinned like any other (TGT-5); link-local and metadata refused even when allow-listed | ADR-010 §7 | TM-T55 |
| TM-56 | E | `--yes` on a long-running agent pre-approves remote targets and NAME-4 | M | H | `--yes` applies to locally started runs only; approvals for remotely started jobs need an interactive TTY or an explicit `webhooktrap targets approve`; a non-TTY agent declines `needs_confirmation` | ADR-010 §6.3 | TM-T56 |

### TB-6 CI

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-60 | I | CI token printed in logs or visible in process listings | M | M | Token read from an environment variable only (no flag); masked in all output; token format with a distinctive prefix and checksum so secret scanners detect leaks | ADR-010 §9, contracts §4 | TM-T60 |
| TM-61 | T | Stolen CI token used to upload forged or bulk runs | M | L | Write-only `runs:upload` (as drafted); document size cap; per-token rate limit; provenance shows the token name; revocation; optional expiry | contracts §4, schema §2 | TM-T61 |
| TM-62 | E | Poisoned pipeline: a fork's pull request changes probes or fixtures and they run with the repository's secrets | M | H | Docs: run `webhooktrap test` with secrets only on trusted events; engine warns and refuses `cmd:` probes when it detects a privileged fork-PR context unless `--allow-untrusted-config` | ADR-010 §9, docs | TM-T62 |
| TM-63 | I | Public CI logs print run URLs | H | M | Share token (TM-15); CI uploads private by default whatever the project default, unless `--share` (**PO decision** PO-C) | ADR-010 §9, contracts §4 | TM-T63 |
| TM-64 | T | Re-upload with the same `run_id` overwrites a run | L | M | First write wins; same document hash → 200; different → 409; `run_id` must be a valid UUIDv7 created by the engine | contracts §4, schema §1.1 | TM-T64 |

### TB-7 Engine process and server-side runs (SSRF)

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-70 | E | **SSRF from hosted server runs** (P6) to metadata, the private network or the app's own services | M | Critical | §7.1 | ADR-006 am. §2, ADR-010 §7, A10 | TM-T70 |
| TM-71 | — (abuse) | Server runs used as an anonymous POST relay to third parties, with attacker-chosen bodies | H | M | §7.1 limits; identifying User-Agent and abuse page; guests: **PO decision** PO-B | ADR-006 am. §2 | TM-T71 |
| TM-72 | E | **B′: any account reaches the private network** (S-10) | M | H | §7.2 | ADR-006 am. §3, schema §7 | TM-T72 |
| TM-73 | E | B′ with instance secrets lets any B′ user send validly signed webhooks to internal handlers | M | H | Instance secrets usable only by users holding the B′ permission; each secret optionally bound to allow-list entries; every use audited | ADR-006 am. §3 | TM-T73 |
| TM-74 | E | Engine invocation: argument injection, or a parser bug on an untrusted body, inside a process with credentials (S-11) | L | H | §6 S-11 | ADR-010 §1, A10 | TM-T74 |
| TM-75 | I | Instance secrets exposed through process environment, argv, queue payloads or logs | L | H | Passed to the engine on stdin or an inherited file descriptor; never in queue payloads or `ps`; encrypted at rest (as drafted) | ADR-006 am. §3 | TM-T75 |
| TM-76 | D | A malicious target stalls (slow headers, slow body, hang) and holds runner slots | M | M | 30 s per request (as drafted), response cap, per-run wall clock (CAP-2), per-principal concurrency, runner pool separate from other queues | ADR-006 am., ADR-010 §7 | TM-T76 |

### TB-8 Internal services

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-80 | E | Redis reachable from outside the app network: queue job injection leads to code execution in PHP workers | L hosted / M self-host | Critical | Redis on a private network only, no published port in Compose, password/ACL required (Compose generates one), TLS when not on the same host; Reverb scaling channel on the same protected Redis | A10 H-2/H-4, ADR-008 | TM-T80 |
| TM-81 | I | Backups hold bodies and display values | — | — | A10 (C-3 §2.6, as drafted) | A10 | — |
| TM-82 | I | Pairing codes, tokens, bodies or display values in application logs or error reporting | M | M | Log scrubbing for `Authorization`, cookies, request bodies on `/c/`, `/agent/v1/pair` body, token endpoints; error reporter configured with body capture off | impl., A10 | TM-T82 |

### TB-9 Self-host admin

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-90 | E | Admin relaxes probe display | — | — | Accepted: agent applies the stricter rule (PV-12) | — | PV T13 |
| TM-91 | R | Unattributed policy changes | L | M | Append-only audit (as drafted), including B′ permission grants and secret use | schema §7 | TM-T91 |
| TM-92 | E | Over-broad allow-list | M | H | §7.2 | ADR-006 am. §3 | TM-T72 |

### TB-10 Distribution

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-100 | T | Compromised or impersonating release (npm, GitHub release, Homebrew, install script), typosquat | M | Critical | §7.7 | ADR-010 §2, A10 H-9 | TM-T100 |
| TM-101 | T | Dependency compromise (Go modules, Composer, npm front end) | M | H | Locked dependencies (`go.sum`, `composer.lock`, lockfile), `govulncheck`/`composer audit` in CI, review of new dependencies, pinned Actions by commit | impl., CI | TM-T101 |

### TB-11 API and MCP

| ID | STRIDE | Threat | L | I | Control | Lands in | Test |
| -- | ------ | ------ | - | - | ------- | -------- | ---- |
| TM-110 | E | Instructions hidden in captured bodies steer an MCP client into starting runs | M | M | `run_scenarios` refused until NAME-4 acknowledged on the agent (as drafted); remote jobs limited to locally declared targets (TM-50); MCP responses mark capture content as untrusted data | contracts §4 | TM-T110 |
| TM-111 | E | A personal token used for reads can also start runs | M | M | Personal token abilities `read` and `run` chosen at creation; `run` off by default | schema §2, contracts §4 | TM-T111 |

---

## 6. S-1 to S-14: decisions

**S-1 Pairing code strength.** A05 default (6 symbols, 30 bits) is rejected. A guessing attacker targets *all* live codes at once, so the per-code failure counter barely helps; per-address and global limits do the work. The code is copied as a command, so length costs little.
- Alphabet: Crockford base32 without `I L O U`; **10 symbols, ≈ 50 bits**, shown as `XXXXX-XXXXX`, case-insensitive, hyphen optional on input.
- TTL 10 minutes; single use; one live code per principal (as drafted).
- Failed submissions: per source address (10 per 10 minutes, then 1 hour block), global failure rate alarm and circuit breaker, per-code void after 5 failures kept as a secondary control.
- Stored as HMAC-SHA-256 with a server key (not a bare SHA-256), looked up by hash; one generic `invalid_code` error (as drafted).
- On completion, the browser shows agent name, OS and key fingerprint with **"Not your machine? Revoke"**; account owners also get an email "New agent paired" (detective control).
- Rejected alternative: browser approval of every pairing. Stronger, but adds a step the flows avoid; 50 bits makes guessing impractical without it.

**S-2 Pasted attacker code.** Single use + 10 minutes already limits this to live, targeted social engineering. The guest label "Guest session · expires…" does not identify *whose* session, so a label alone is weak. Required:
1. The agent prints the **server origin** it connected to, then the sanitised workspace label, and asks **"Join? Only continue if you created this code in your own browser just now. [y/N]"**. A `--yes` flag is allowed for scripted pairing on account workspaces only; never for guest workspaces.
2. Remotely started jobs may target only locally declared targets (TM-50), with per-target approval keyed on (server origin, workspace, scheme, host, port) and the path shown (TM-56).
3. Every remotely started run prints one local line before sending: requester workspace, run number, target, delivery count.
4. Verify jobs for captures of a workspace the agent joined less than 10 minutes ago run only after the first local confirmation (so a just-joined agent cannot be used as a silent signature oracle).
5. Claim does not carry approvals across (TM-42).

**S-3 Agent key.** Ed25519, generated locally, **one key pair per server origin** (so one compromised or hostile server cannot reuse an agent's identity elsewhere). Storage: macOS Keychain, Windows Credential Manager/DPAPI, Linux Secret Service when available; otherwise a file `0600` in a `0700` directory (Windows: ACL to the current user), and the agent refuses to load a key file readable by others. Challenge: server nonce 32 bytes, single use, 60 s, bound to `agent_id`; the agent signs `"webhooktrap-agent-auth/v1" ‖ server origin ‖ agent_id ‖ nonce ‖ expires_at`. Access tokens 15 minutes, stored hashed, revocation checked on every request (as drafted). Rotation in the MVP = unpair and re-pair (`webhooktrap agent unpair` deletes the local key and calls `DELETE /agent/v1/agent`). Key never printed, never in run files.

**S-4 Job integrity / signed jobs.** Layers 1–4 are sufficient for the MVP, with additions: the agent pins the server origin recorded at pairing, follows no redirects on its API calls, uses system roots with no insecure option on hosted, and validates every job and capture against schemas and size limits (body ≤ 5 MiB, header count and JSON depth bounded). **Layer 5 (server-signed jobs) is not adopted**: a compromised server holds the signing key, so it only helps against a TLS-terminating proxy on self-host, which is the operator's own trust choice. Record it as rejected for the MVP with this rationale.

**S-5 Unattested verdicts.** Agreed that verdicts are claims. Change ADR-010 §3: server-side `rejudge` is limited to Level 1 consistency (status, latency, budget, counts, exit code) because the hosted profile has no raw probe values (PV-1, PV-8). Provenance on every run view (TM-17). Server-rendered finding prose and pack-only citation URLs (TM-16).

**S-6 Forwarded origin.** A05's default is accepted as written (configured origin; forwarded only behind trusted proxies with explicit setting; source recorded). Add: `source_ip` through the trusted-proxy chain only (TM-05).

**S-7 Header redaction.** Keep the three headers (`Authorization`, `Proxy-Authorization`, `Cookie`); keep signature headers. Change the placeholder: **drop the `sha256:xxxxxxxx` fragment**. An unkeyed truncated hash of a low-entropy credential (Basic auth) lets anyone who sees the capture confirm a guess offline. Use `[redacted · N bytes]`, or a keyed HMAC with a per-project key if A01 needs equality across captures. Share views keep the SCR-3 header allow-list.

**S-8 Secret last-4 hint.** **Not allowed on the server.** Four characters of a user-chosen secret (GitHub, Standard Webhooks) are a meaningful share of its entropy, and it contradicts R12. Remove `hint_last4` from `agent_secret_names` and from the hello schema (schema forbids it). The agent may print the last 4 locally in its own terminal. A03's FL-2 toast and SIG-3 become "STRIPE_WEBHOOK_SECRET on maya-mbp". **PO decision** (PO-D) because it changes approved copy.

**S-9 Hosted server runs.** See §7.1. A05's defaults (2 concurrent per principal, 50 deliveries) kept, plus per-destination and daily limits and a required egress firewall.

**S-10 B′ access.** "Authenticated" is not enough. See §7.2: per-user permission, hard block while sign-up is open.

**S-11 Engine in the server.** The engine process holds **no credentials**: Laravel passes inputs on stdin and reads NDJSON from stdout, and writes to the database itself. Invocation by argument array, never a shell. Containers: non-root, read-only root filesystem, no new privileges, CPU/memory/time limits. `plan` and `verify` run with **no network** (separate network namespace or seccomp profile); `serve-run` runs in the `replay` worker on a network segment with egress filtering and no route to Postgres, Redis or Reverb. The "API token" in A05's default is unnecessary and should be removed.

**S-12 Release signing.** See §7.7.

**S-13 Share capability.** Recommend a separate share token now (TM-15). **PO decision** (PO-A); this reverses A05's PO-8 recommendation.

**S-14 Guest cookie and CSRF.** A05's default accepted, plus `__Host-` prefix, no replacement of a live session by `POST /new`, session id regeneration at claim, and claim binding (TM-10 to TM-12).

---

## 7. Cross-cutting topics

### 7.1 SSRF and abuse for hosted public server runs (P6)

Required controls (details of bypass classes in the private report):
1. One guard implementation in the engine, `public` mode: resolve once, refuse if **any** resolved address is outside public unicast, pin, re-check the peer address at dial; no proxy; no redirects; TLS verification on.
2. The refused set covers every special-purpose IPv4 and IPv6 registry range, including IPv4-embedded IPv6 forms and transition prefixes, CGNAT, benchmarking, multicast, broadcast, and every cloud metadata address and name the hosting provider exposes. The set is data with its own tests, reviewed by A09 at implementation.
3. Ports: 80, 443 and 1024–65535 only.
4. Outbound headers from the re-send allow-list (TM-51); no `Authorization` or `Cookie` ever (they are redacted at capture anyway); an identifying `User-Agent` with a link to an abuse page.
5. Limits: 2 concurrent runs per principal, a daily server-run cap per principal, a per-destination-host rate limit across all principals, response read cap, 30 s per request, CAP-2 wall clock.
6. **Egress firewall on the `replay` worker is required, not optional** (A05 lists it as defence in depth for A10): deny private, link-local and metadata at the network layer, and a dedicated egress IP separate from the app's IP.
7. Guests: **PO decision** (PO-B). Recommendation: accounts only at launch; guests keep the agent path. If guests stay, a lower daily cap per guest and per client address.

### 7.2 Self-host B′

1. Off by default (as drafted).
2. A **per-user permission** granted by an instance admin, in addition to authentication; guests never.
3. B′ **cannot be turned on while sign-up is open** (hard rule, not a warning), and opening sign-up while B′ is on is refused.
4. Allow-list entries must name a port or a port range; CIDR breadth capped (IPv4 /16, IPv6 /48); entries overlapping the instance's own services (app, Postgres, Redis, Reverb, proxy) or metadata and link-local are refused at save.
5. The `replay` worker sits on a network without a route to the backend services, so a mistaken entry cannot reach them (A10).
6. Instance secrets follow the B′ permission and are audited per use (TM-73).

### 7.3 Guest session abuse and quotas (A-13)

Quotas as approved (24 h absolute, 100 captures, 10 runs, 1 agent) are sound. Add:
- per-address and global limits on `POST /new`, with an adaptive challenge only under active abuse (no puzzle by default, G14);
- a global guest storage budget (TM-02);
- quota counters checked atomically in Postgres (as drafted); the "got past Once" signal comes from the agent, so a hostile agent can under-report and avoid counting runs. Accepted: the server also counts runs by `RunAccepted` with a higher hard ceiling (e.g. 20 accepted runs per guest session) that does not depend on the agent's claim.

### 7.4 Echo channel authorisation

Covered by TM-20 to TM-24. The channel list in `event-catalogue.md` §1 is correct in principle; add explicit principal-type checks, separate auth endpoints, client events off, and negative tests for every guard × channel pair.

### 7.5 Capture ingest

Covered by TM-01 to TM-07. The A-15 invariant is right and must not gain per-key rate limits (they would leak existence).

### 7.6 Secrets never on hosted

Paths reviewed: hello (`secrets[]` names only, closed schema), observations and final document (hosted profile, PV-8), signature verdicts (names only), fixtures report (shape only), Echo events, run document text (TM-16), PR comments (rendered locally from display values), error reporting (TM-82), probe stderr (never uploaded). The only gap found is the SIG-3 last-4 hint (S-8). Self-host instance secrets are a separate, opt-in case (TM-73, TM-75).

### 7.7 Supply chain of the agent binary and npm wrapper

1. npm: per-platform packages as `optionalDependencies` (no install-time download, no `postinstall` script), so npm's lockfile integrity covers the binary; published from CI with **npm provenance** and trusted publishing (no long-lived npm token); 2FA on the org; reserve `webhooktrap`, `@webhooktrap/*` and obvious typosquats.
2. GitHub releases: reproducible builds (`-trimpath`, pinned toolchain), `SHA256SUMS` signed (Sigstore keyless from the release workflow, or a minisign key held offline), SLSA provenance attestation.
3. Install script: HTTPS only, verifies checksum and signature before moving the binary into place; documents a manual verification path.
4. Homebrew tap: formula pins version and SHA-256.
5. macOS notarisation and Windows code signing (A10).
6. The pairing command in the UI pins a major version (`npx @webhooktrap/agent@1 pair …`).
7. **No self-update in the MVP.** "Agent too old" points to the user's install channel.
8. CI docs pin the engine version and checksum.

### 7.8 CI token handling

Covered by TM-60 to TM-64. Token values get distinct prefixes per kind (personal vs CI) with a checksum, are shown once, stored hashed with last 4 for display (as drafted), and are read from `WEBHOOKTRAP_TOKEN` only.

---

## 8. Residual risks accepted

| # | Risk | Why accepted |
| - | ---- | ------------ |
| R-1 | A compromised hosted server can send jobs to paired agents: payloads re-signed with developers' secrets, delivered to targets the developer declared and approved | This is the product's function; local target authority bounds it; signed jobs would not help (S-4) |
| R-2 | Stale Reverb subscriptions receive minimal payloads until disconnect | Payloads carry no sensitive data (TM-21) |
| R-3 | Ingest timing difference between stored and dropped requests | Keys are 71 bits; no enumeration (TM-01) |
| R-4 | Owners can forge their own runs | Runs are claims with provenance (S-5) |
| R-5 | Self-host admins can read their database | They operate the instance |
| R-6 | A hostile agent can under-report "got past Once" | Bounded by the accepted-run ceiling (§7.3) |

---

## 9. Verdict per document, with exact changes

### 9.1 ADR-010 — ACCEPT WITH CHANGES

1. §4 rule 1: code = 10 symbols Crockford base32 (≈ 50 bits), `XXXXX-XXXXX`, TTL 10 min, single use; per-address and global failure limits; per-code void after 5 failures as secondary; stored as keyed HMAC (S-1).
2. §4 rule 5: agent-side confirmation **required**: print server origin, then sanitised label, then `[y/N]`; `--yes` allowed only for account workspaces (S-2, TM-41).
3. §4 rule 3: Ed25519, one key pair per server origin, OS store or `0600` file with permission check, unpair = rotation (S-3).
4. §4 rule 4: domain-separated signed challenge including server origin and agent id; single-use nonce (TM-33).
5. §4: browser shows agent name, OS and key fingerprint after pairing with "Revoke"; account email "New agent paired".
6. §5: add "the agent opens no listening socket" (TM-54); long-poll capacity note for A10 (TM-40).
7. §6.3: remotely started jobs (browser, API, MCP) may target only locally declared targets; approvals keyed on (server origin, workspace, scheme, host, port), path shown; approvals need a TTY or `targets approve`; `--yes` never covers remote jobs; one local line printed per remote run (TM-50, TM-56).
8. §6: add origin pinning, no redirects, schema and size validation of jobs and captures; replace "Optional fifth layer for A09 to decide" with "Server-signed jobs: not adopted for the MVP" and the S-4 rationale.
9. §3: replace the `rejudge` consistency check with "Level 1 consistency only"; add provenance display; finding prose rendered by the server from type + pack; citation URLs from packs only (S-5, TM-16, TM-17).
10. §1 and open item 5: engine process holds no credentials; argv array, stdin/stdout; `plan`/`verify` without network; `serve-run` in the egress-filtered `replay` network; resource limits; plan previews rate-limited (S-11, TM-18, TM-74).
11. §7: the `public` mode refused set as a tested data table (§7.1 items 1–3); allow-listed names in `local` mode resolved and pinned, link-local and metadata always refused (TM-55); re-send header allow-list (TM-51).
12. §2 distribution: §7.7 items 1–8 replace "Release signing and verification … are [A09]".
13. §9: CI token from environment only; distinct token prefixes with checksum; fork-PR privileged-context warning and `cmd:` refusal; CI uploads private unless `--share` (subject to PO-C) (TM-60, TM-62, TM-63).
14. §11 table "Secret names" row: "env var name only; the last-4 hint is never uploaded" (S-8). Open items 1, 2, 3, 7 closed by this threat model.

### 9.2 ADR-003 amendment 1 — ACCEPT WITH CHANGES

1. §2: pairing values per ADR-010 change 1; guest workspaces always require the agent-side `[y/N]` (no `--yes`).
2. §4 step 2: replace "Validate that the request carries the live guest session cookie" with "Validate that the sign-in was started from this guest session (single-use claim nonce stored with the session at sign-in start, checked at the OAuth callback or email-link landing in the same browser). Otherwise sign in without claiming." (TM-12)
3. §4 step 4: the moved agent is marked `needs_reconfirm`: its target and NAME-4 approvals are cleared, and it declines jobs until confirmed locally; the guest flag clears only after confirmation (TM-42).
4. §4 step 5: add "regenerate the Laravel session id".
5. §5: add cookie attributes (`__Host-`, `Secure`, `HttpOnly`, `SameSite=Lax`), `POST /new` CSRF, no replacement of a live session, per-address/global creation limits, global guest storage budget, accepted-run ceiling (§7.3) (TM-10, TM-11).
6. Open items: replace both with "Resolved by A09 threat model"; the "re-confirmation after N hours" question is superseded by change 2.

### 9.3 ADR-004 amendment 1 — ACCEPT WITH CHANGES

1. §4: two broadcast auth endpoints (browser guards; agent guard); every channel callback checks principal type first (TM-20).
2. §4: client events disabled on the Reverb app (TM-22).
3. §3 or new §6: on session end, claim, agent revocation and unshare, the server sends a terminating event; residual stale-subscription risk accepted because payloads are minimal (TM-21).
4. §4: shared-run polling is rate-limited per address; Inertia partial reloads never return owner-only props to shared viewers (TM-14).
5. Consequences / spike list: add negative tests for every guard × channel pair, including an agent token against `private-workspace` of its own workspace (TM-T20).

### 9.4 ADR-005 amendment 1 — ACCEPT WITH CHANGES

1. §3: placeholder becomes `[redacted · N bytes]` (no unkeyed hash); list confirmed as the three headers (S-7).
2. §1: `source_ip` from the trusted-proxy chain only (TM-05). S-6 otherwise accepted.
3. New §7 "Rendering untrusted capture data": text-only rendering; raw download headers (`application/octet-stream`, attachment, `nosniff`, `CSP: sandbox`) (TM-06).
4. §5: per-project quota for accounts (captures per day, stored bytes) and global guest storage budget, both dropped identically (TM-02).
5. Consequences: recommend to A10 a separate host for ingest so app cookies never reach it; `__Host-` cookies regardless (TM-04).
6. §4: add "the edge rate limiter must never key on the capture key" (keeps the invariant).

### 9.5 ADR-006 amendment 1 — ACCEPT WITH CHANGES

1. §2: refused set as a tested data table; ports 80, 443, 1024–65535; outbound header allow-list; identifying `User-Agent` and abuse page (§7.1).
2. §2: limits: 2 concurrent per principal, daily cap per principal, per-destination-host rate limit across principals.
3. §2 "Available to guests and accounts": replace with the PO-B outcome (recommendation: accounts only at launch).
4. §3: per-user B′ permission; B′ and open sign-up mutually exclusive; port required; CIDR breadth caps; refuse overlaps with instance services and metadata (§7.2).
5. §3: instance secrets restricted to B′-permitted users, passed by stdin/fd, audited per use (TM-73, TM-75).
6. Open items: egress firewall moves from "defence in depth" to **required** (A10); the per-user permission question is answered (yes); rate limits answered by change 2.

### 9.6 `domain-model.md` — ACCEPT WITH CHANGES

- §4 Capture invariants: "The signature verdict is written only by the agent a verify job was issued to, once per job" (TM-36).
- §5 Agent: invariants "an agent reads only captures referenced by an unexpired job issued to it" (TM-35); `needs_reconfirm` state after claim (TM-42); cap on active agents per workspace (TM-43); revoked reasons aligned with schema (`claimed_rekey` is in schema but not here).
- §6 Proving: "server checks Level 1 consistency only"; provenance attribute (executor + agent or CI token name) on Run (S-5).
- §10 Sharing: share token (if PO-A); "Shared by" shows the owner's chosen display name, which the owner sees in the share dialog.
- §11 Instance: B′ permission on users; sign-up/B′ exclusivity; admin bootstrap from the host only (TM-19).

### 9.7 `schema.md` — ACCEPT WITH CHANGES

- `agent_secret_names.hint_last4`: remove (S-8).
- `pairing_codes.code_hash`: HMAC with a server key; code length per S-1.
- `agents`: add `needs_reconfirm boolean NOT NULL DEFAULT false`; partial unique/count guard for the per-workspace cap.
- `agent_targets`: add `declared boolean` (declared locally vs merely reported) for display; still display-only.
- `runs`: add `share_token_hash bytea UNIQUE NULL` and `share_token_created_at` (if PO-A); add `document` size `CHECK` (e.g. ≤ 2 MiB); add `accepted_count` support for the guest accepted-run ceiling (or a counter on `guest_sessions`).
- `findings`: `title/what/why/fix` become server-rendered (store `type`, structured fields, pack version); `citation` validated against pack data.
- `personal_tokens.abilities`: enumerate `read`, `run`; CI `{runs:upload}` (as drafted). Token value formats with kind prefix and checksum (documented here, not stored).
- `users`: add `can_private_replay boolean NOT NULL DEFAULT false` (self-host).
- `instance_settings`: constraint or service rule: `server_replay_private` implies `signup_policy <> 'open'`.
- `captures.headers` placeholder format per S-7.
- `projects`: add account capture quota columns or reference instance limits (TM-02).

### 9.8 `contracts.md` — ACCEPT WITH CHANGES

- §3.2 `POST /new`: CSRF; redirect to the live session if one exists.
- §3.2 `/r/{runId}`: access via share token (if PO-A); `Referrer-Policy: no-referrer`; `noindex`; partial reloads gated by access; `/r/{runId}.json` with `nosniff`.
- §3.2 raw download headers (TM-06).
- §3.2 share endpoint returns the share URL (token) and rotates it on re-share.
- §3.3 `RunHeader`: add `provenance: { executor, agentName?, ciTokenName?, source }`.
- §3.3 `PairingCodeView.command`: pinned major version.
- §4: `run_scenarios` needs the personal token `run` ability; CI token from `WEBHOOKTRAP_TOKEN`; upload size cap; first-write-wins on `run_id`.
- §5.1: code format; response adds `server.origin`; the agent requires local confirmation before it sends its first hello.
- §5.2: signed message format (S-3).
- §5.3: `hint_last4` forbidden by schema; `env_name` grammar.
- §5.4: capture fetch only for job-referenced captures of a job issued to this agent; job and capture size limits; decline reasons add `needs_reconfirm`.
- §5.7: first-write-wins; server renders finding prose; `citation.url` validated.
- §5.8: signature verdict only within a verify job issued to this agent.
- §5.9: add "token values, pairing codes".
- §3.2 `GET /pair?code=` (post-MVP device flow): needs its own A09 model before it ships (code phishing).

### 9.9 `event-catalogue.md` — ACCEPT WITH CHANGES

- §1: add principal-type checks and the separate agent auth endpoint; state "client events disabled".
- §1: on `GuestSessionEnded`, `GuestSessionClaimed`, `AgentRevoked`, `RunUnshared`: terminating event to the affected channel.
- §3.2 `pairing.completed`: add `os` and `key_fingerprint` (for the "Not your machine? Revoke" control).
- §2: add `AgentPaired` listener "Notify account owner by email" (account workspaces only).
- All schemas `additionalProperties: false`; canary test extended to every broadcast (TM-T23).

### 9.10 `open-questions.md` — ACCEPT WITH CHANGES

- §2: mark S-1..S-14 resolved with a pointer to this file (§6); S-15 confirmed (PV-1..PV-14 reflected).
- §4 PO-8: change the recommendation to "separate share token" (PO-A).
- §4: add PO-A..PO-F below.
- §5 K-12: resolved by S-8 (not uploaded).

---

## 10. Verification tests (for A11)

| Test | Checks |
| ---- | ------ |
| TM-T1 | Ingest response byte-identical (status, headers, body) for valid, unknown, rotated, expired, over-quota, oversized keys and DB down; `HEAD` same without body |
| TM-T2 | Account project over its daily/bytes quota: request answered identically, not stored, metric incremented; global guest budget likewise |
| TM-T3 | Slow body upload is cut at the read timeout; 25 MiB + 1 byte gets the uniform 413 |
| TM-T4 | A request to the capture URL carrying app cookies stores `Cookie` redacted; app cookies carry the `__Host-` prefix |
| TM-T5 | Spoofed forwarding headers from an untrusted peer change neither `public_origin` nor `source_ip` |
| TM-T6 | Markup canary corpus in body, headers, path and query: rendered inert in every view; raw download headers present |
| TM-T10 | Guest cookie attributes; a token in a URL or header is ignored; session id changes at claim |
| TM-T11 | Cross-site `POST /new` without CSRF token fails; with a live session, `POST /new` returns the existing project |
| TM-T12 | Sign-in completed in a browser that did not start it from the guest session does not claim; started from the session, it claims; the nonce is single use |
| TM-T13 | Each owner route as other user, other guest, anonymous: identical not-found |
| TM-T14 | Shared view: partial reload requesting each owner-only prop returns nothing of it |
| TM-T15 | Run id alone grants nothing; share token works; unshare then reshare: old token fails; run page sends `Referrer-Policy: no-referrer` |
| TM-T16 | Uploaded run with hostile text in every free string and a non-pack citation URL: rendered inert, citation dropped, prose comes from the server templates |
| TM-T17 | Every run view shows provenance; no "verified" wording anywhere (string table check) |
| TM-T18 | Plan preview above the per-principal rate returns 429; engine process killed at timeout |
| TM-T19 | Fresh self-host: first sign-up is not admin; admin only via host command |
| TM-T20 | Matrix: {web user, guest, agent token, anonymous} × {project, run, workspace, agent, session} channels; only the intended cells authorise |
| TM-T21 | After session end, claim, revoke, unshare: client receives the terminating event; later events on that socket carry nothing beyond the schema |
| TM-T22 | A client event sent on any private channel is rejected |
| TM-T23 | Canary values (bodies, header values, secret names, emails, capture keys, raw probe values) absent from every broadcast payload across a full run |
| TM-T30 | Code format and entropy; per-address block after 10 failures; global breaker trips; used, expired, unknown codes give the same error |
| TM-T31 | Pairing prints server origin first and requires `y`; guest workspace ignores `--yes`; a remote job to an undeclared loopback port is declined `target_not_allowed` |
| TM-T32 | Key file with group/world permissions refused; key never in logs or run files; unpair deletes the key |
| TM-T33 | A signature produced for server A's challenge does not verify at server B; nonce reuse fails |
| TM-T34 | Agent refuses a redirect on API calls, a different origin, an invalid certificate on hosted, a job failing schema or size limits |
| TM-T35 | Agent fetch of a capture not referenced by its job: not found; of another workspace: not found |
| TM-T36 | Verdict post for a capture outside a verify job issued to that agent: refused; second post for the same job: refused |
| TM-T37 | Agent B posts observations/final/accept for agent A's run: refused; final re-post with different hash: 409 |
| TM-T39 | Hello with any extra field (including `hint_last4`) is rejected; bad `env_name` rejected |
| TM-T40 | Concurrent long-polls above the cap get 503 with retry; observation `seq` above the cap refused |
| TM-T41 | Server label containing control, bidi and tag characters prints sanitised; origin line unaffected |
| TM-T42 | After claim the agent declines jobs until local confirmation; approvals are empty |
| TM-T50 | Same as TM-T31 last clause, plus a declared target works after approval |
| TM-T51 | Captured forwarding/host/hop-by-hop headers are not present in deliveries |
| TM-T52 | A changed `webhooktrap.yml` hash re-prompts even with `--yes` from an earlier run |
| TM-T54 | Agent process has no listening socket (platform check in CI) |
| TM-T55 | Allow-listed name resolving to link-local or metadata refused; rebinding between check and dial refused |
| TM-T56 | Non-TTY agent with `--yes` declines a first remote job `needs_confirmation` |
| TM-T60 | Token passed as a flag is refused; token value never in output (incl. encoded forms) |
| TM-T61 | Oversized document refused; per-token rate limit; revoked token refused |
| TM-T62 | Engine in a simulated privileged fork-PR context refuses `cmd:` probes without the explicit flag |
| TM-T63 | CI upload creates a private run unless `--share` (per PO-C) |
| TM-T64 | Same `run_id`, same hash → 200; different hash → 409; malformed `run_id` refused |
| TM-T70 | Guard table tests for every refused range and encoding; dial-time re-check; no redirects; worker egress blocked at network level (ops test) |
| TM-T71 | Daily and per-destination limits enforced; User-Agent present; guest access per PO-B |
| TM-T72 | B′ without permission refused; enabling B′ with open sign-up refused; overlapping/broad/portless entries refused |
| TM-T73 | Instance secrets unusable without the permission; each use audited |
| TM-T74 | Engine invoked with hostile selection values: no shell interpretation; `plan` has no network; process limits enforced |
| TM-T75 | Instance secret canary absent from env of child processes, `ps`, queue payloads, logs |
| TM-T76 | Slow-drip target ends at 30 s; run ends at CAP-2; concurrency cap holds |
| TM-T80 | Compose: Redis has no published port and requires auth (ops test) |
| TM-T82 | Canary pairing code, token, body: absent from logs and error reports |
| TM-T91 | B′ permission grant, allow-list change, secret use each write one audit row |
| TM-T100 | Release pipeline: checksum and signature verified by the install script and documented manual path; npm package has provenance and no install scripts |
| TM-T101 | CI fails on known-vulnerable dependencies; Actions pinned by commit |
| TM-T110 | MCP `run_scenarios` before NAME-4 acknowledgement refused; to an undeclared target declined |
| TM-T111 | Personal token without `run` ability cannot start runs |

---

## 11. Items needing a PO decision

| # | Question | A09 recommendation |
| - | -------- | ------------------ |
| PO-A | Share capability: run id (A05 PO-8) or separate rotatable share token | **Share token now.** Changing it after URLs appear in CI logs and PR comments breaks links (A05's own G-3 risk) |
| PO-B | Hosted server-side runs (P6) for guests (AC-9.4) | **Accounts only at launch**; guests use the agent. If kept, lower caps per guest and per address |
| PO-C | CI uploads follow the project default visibility (P2) | **Always private unless `--share`**, because CI logs are often public |
| PO-D | SIG-3 / FL-2 toast shows the secret's last 4 characters | **Remove from the server**; show env var name and agent; last 4 only in the local terminal |
| PO-E | Agent-side `[y/N]` at pairing and local reconfirmation after claim (adds one terminal step to FL-2 and FL-7) | **Accept**; needed against pasted codes and claim escalation |
| PO-F | Pairing code length 10 symbols (A03 visuals show `HXR-4K7`) | **Accept**; the command is copied, not typed |
| PO-G | B′ needs a per-user permission and is impossible with open sign-up | **Accept** |

---

## 12. Re-check (rev 3)

**Date:** 2026-11-14 · **Read:** A05 revisions 2 and 3 (ADR-010 incl. §12 and §12.5, amendments 0003–0006 incl. 0005 §9, `domain-model.md` §2.1–2.4, `schema.md`, `contracts.md` §3.4 and §6, `event-catalogue.md`, `open-questions.md` §2.1 and §4, `decisions/README-phase4-notes.md`); A10 `contabo-deployment-plan.md` rev. 2, `compose-profile.md`, `ci-cd.md`, `vps-facts.md`; decisions 38–44. The designs were read, not the change logs. Sensitive detail: private companion P-15 to P-22.

### 12.1 First-pass changes (§9): applied?

All §9.1–§9.10 changes are present in the designs, checked line by line against the text (not the change logs). Two small gaps:

| Gap | Where | Fix |
| --- | ----- | --- |
| Per-address pairing and sign-in limits do not say how IPv6 is keyed | ADR-010 §4 rule 1; domain §2.1 "Limits" | Key IPv6 limits on the /64 (S-1) |
| Unconfirmed agents (`needs_reconfirm` from `pair`) have no expiry | ADR-010 §4; schema `agents` | See RE-1 |

### 12.2 New threats (WebAuthn and identity, runner, shared VPS, CI/CD)

| ID | Threat | Control (design level) |
| -- | ------ | ---------------------- |
| TM-120 | A cloned authenticator goes unnoticed because the counter rule accepts a reported count of 0 after a non-zero stored count | Use the WebAuthn rule: refuse when **either** count is non-zero and the received count is not greater than the stored one (WA-1) |
| TM-121 | Discoverable sign-in accepts a credential whose user handle does not belong to that credential's owner | Check that `userHandle` matches the credential's user; refuse flagged credentials (WA-2) |
| TM-122 | Online guessing of TOTP or recovery codes across many first-factor sign-ins (the limit is 5 per challenge only) | Per-account second-factor failure counter across challenges, with an email notice and a temporary hold (WA-3) |
| TM-123 | Same-origin script or framing reaches the ceremony pages; `Permissions-Policy` alone is not a boundary against same-origin script | The boundary stays CSP without `unsafe-inline` and text-only rendering (TM-06, TM-16); add `frame-ancestors 'none'` app-wide (WA-4) |
| TM-124 | A new OAuth identity is linked silently by email match | Email notice "Sign-in method linked" + audit; OAuth `state` bound to the session and PKCE where the provider supports it (WA-5) |
| TM-125 | Delayed email recovery is, by construction, email-only account takeover after the wait: a password reset by email gets past the first factor, then recovery removes the second | §12.4 PO-J conditions (RC-1..RC-6) |
| TM-126 | After an account takeover (recovery or otherwise), remote runs to targets the agent already approved, signed with the developer's real secrets | Recovery completion sets every agent of the workspace to `needs_reconfirm` and clears approvals, as claim does (RC-4) |
| TM-130 | Stolen runner token pulls and accepts server-run jobs (captures of any account's server runs) | Source binding **required** on hosted; runner API path allowed only from the runner IP at the host proxy; rotation procedure (RN-1, RN-2) |
| TM-131 | The runner trusts the job's `guard`/`allowlist` fields; a server bug or compromise widens the runner's network reach | Mode ceiling configured locally on the runner; hosted runner refuses `allowlist` jobs (RN-3) |
| TM-132 | Server runs aimed at the app itself, at Compose services reachable from the runner network, or using the runner IP as a trusted source | Runner never dials the runner-API host's addresses or its own networks' service addresses; the app grants the runner IP nothing beyond the token (RN-4) |
| TM-133 | Per-job secrets endpoint fetched more than once, after the run ends, by a runner that did not accept the job, or for secrets unrelated to the target | RN-6 |
| TM-134 | `serve-run` picks up a probe/reset configuration present on disk or in its environment (Level 2 by the back door, R-P) | RN-7 |
| TM-140 | Any local process on the shared VPS can reconfigure the host proxy through its admin API and intercept `app.`/`in.`/`ws.` traffic | SV-1 |
| TM-141 | Any local process on the shared VPS can reach `127.0.0.1:${APP_PORT}` directly, appear as the trusted proxy, and spoof forwarded headers (`source_ip`, scheme, per-address limits) | SV-2 |
| TM-142 | Membership of the `docker` group or sudo on the shared box equals reading every 2.0 secret | SV-3 |
| TM-143 | 2.0 inherits the security of its weakest neighbour (published neighbour databases and apps, a root 1.x process, host services reachable from containers) | SV-4, SV-5 |
| TM-144 | A neighbour's backup job copies 2.0's secrets or volumes off the box | SV-6 |
| TM-145 | Share tokens in paths are written to access logs | RD-1 |
| TM-146 | The Compose file comes from the image and runs as root: anything merged to `main` can gain the host | CI-2 |
| TM-147 | Image verification happens only on the GitHub runner; the box pulls whatever digest it is told | CI-1 |
| TM-148 | Release and publish credentials reachable from non-release workflows; cache poisoning into release jobs | CI-3, CI-4 |
| TM-149 | The network-free sandbox for `plan`/`verify` (ADR-010 §1 rule 2) cannot be built inside the hardened app container (`cap_drop: ALL`, `no-new-privileges`), and the app container holds `/run/secrets/*` readable by the engine's user | ENG-1 |

### 12.3 R-A to R-P

| # | Verdict | Notes / required change |
| - | ------- | ----------------------- |
| R-A Runner as remote process | **Accept with changes** | RN-1..RN-7 below |
| R-B Secrets via runner API | **Accept** the API path (per-user restriction and per-use audit need it); RN-6 tightens it |
| R-C Provenance names on share views | **Accept**: names are labels, often machine names. The share dialog previews the exact provenance line before sharing (as for "Shared by") |
| R-D `/s/{shareToken}` | **Accept** with RD-1: access logs for `app.` mask the `/s/…` path segment (A10 §4.5 currently logs `$uri`, which holds the token) |
| R-E Pairing sequence | **Accept** with RE-1: unconfirmed agents are revoked (`unconfirmed`) 10 minutes after `pair`; before the first confirmation the server stores no mirrors (secret names, targets) and returns no job or check counts |
| R-F No prose in hosted profile | **Accept** |
| R-G Signed challenge encoding | **Accept**; `expires_at` is signed as the exact string the server issued |
| R-H Free-string grammars | **Accept**; credential names also strip bidi and tag characters (they appear in emails) |
| R-I Relying party | **Accept** with WA-4 |
| R-J Ceremony policy | **Accept** with WA-1, WA-2. UV required for passkey sign-in, UP for second factor, attestation `none`, BE fixed, synced passkeys allowed: all agreed |
| R-K WebAuthn × TOTP × recovery codes | **Accept** with WA-3, WA-5. OAuth does not bypass 2FA: confirmed in domain §2.1 and contracts §3.4. Recovery codes: state ≥ 50 bits each |
| R-L Account recovery | **Accept with conditions** (§12.4 PO-J) |
| R-M 1.x import | **Option B** (§12.4 PO-H) |
| R-N Claim with new methods | **Accept**: nonce consumed only after the 2FA challenge in the same browser session; reset and verification links never claim |
| R-O FrankenPHP | **Accept** with SV-2 and: `in.` without HSTS is fine only if no HSTS header on `app.` or the apex carries `includeSubDomains`/`preload`. The `400` for unparsable targets is acceptable as network-level (uniform across keys). Worker-mode static-state rule agreed |
| R-P Level 1 only | **Accept** with RN-7. Schema `CHECK`, closed runner schemas and `422 level2_not_allowed` close the steering path |

### 12.4 PO decisions

**PO-H (1.x passwords): B, forced reset.** Option A brings a hand-written scrypt verifier and three offline-crackable hashes into a new codebase to save three users one email, and it proves nothing about email ownership. Under B: the import command does not read the hash fields at all; the export is deleted after the PO confirms the import; "passkey" is not a standalone activation method (a passkey can only be enrolled after the email is proven by reset or provider-verified OAuth), so domain §2.4 should say so. Drop the `legacy_password_hash*` columns and `LegacyHashDiscarded`.

**PO-I (magic links): drop.** Fewer email-only paths into an account.

**PO-J (hosted recovery when all factors are lost): delayed email recovery, with conditions.** Manual review by a solo operator is a social-engineering target with no better evidence than email. Delayed recovery is the usual trade-off, but its residual must be stated: an attacker who controls the mailbox for longer than the wait, while the user does not sign in, takes over the account (R-7). Conditions:
- RC-1 Any sign-in that completes a second factor during the wait cancels the recovery automatically (the user evidently still has a factor), with an email.
- RC-2 If the password was reset by email in the 7 days before the request or during the wait, the wait becomes 7 days from the later event.
- RC-3 While a recovery is open and for 7 days after completion: no email change, no new personal token with `run`, no removal of linked identities.
- RC-4 Completion also sets every agent in the user's workspace to `needs_reconfirm` with approvals cleared, and lists CI tokens for review.
- RC-5 One open request per user (as drafted) and a 30-day cool-down after a cancelled request; requests rate-limited per address and per account; uniform answers.
- RC-6 Audit rows for every step; the security log shown to the user after recovery lists the recovery.

**PO-K (attestation `none`): accept.**

### 12.5 Required changes

**A05 (architecture package)**

| ID | Document | Change |
| -- | -------- | ------ |
| RE-1 | ADR-010 §4; schema `agents` (revoked reason `unconfirmed`); contracts §5.3 | Unconfirmed agents expire after 10 min; no mirrors or counts before first confirmation |
| — | ADR-010 §4 rule 1; domain §2.1 | IPv6 limits keyed on /64 |
| RN-1 | ADR-010 §12.1; schema `runners`; contracts §6 | Source binding **required** on hosted (`allowed_cidrs` not null when hosted); optional on self-host |
| RN-2 | ADR-010 §12.1 | Runner-token rotation: at every runner VM rebuild and at least every 90 days; overlap of two tokens allowed during rotation |
| RN-3 | ADR-010 §12, contracts §6 | Runner's widest guard mode set in its local config; hosted runner refuses `allowlist` jobs; the job's allow-list can only narrow, never widen, the local ceiling |
| RN-4 | ADR-010 §7, §12 | `public` and `allowlist` modes always refuse the resolved addresses of the runner-API origin and, on self-host, of the runner's Compose networks; the app gives the runner IP no trust (no trusted-proxy entry, no limit exemption) |
| RN-5 | contracts §6 | Capture fetch only while the job is accepted by this runner and the run is not final |
| RN-6 | ADR-010 §12.4; contracts §6 | Secrets endpoint: only after accept by this runner, run Running, executor `server_allowlist`; initiator's B′ permission re-checked at fetch; only secrets for the run's provider bound to the matched entry (or unbound); one successful fetch per job (a second → `409` + alert); held in memory only, core dumps disabled |
| RN-7 | ADR-010 §12.5 | `serve-run` never reads `webhooktrap.yml` or probe/reset settings from disk or environment; probe and reset executors unreachable in that mode; test |
| WA-1 | domain §2.2; schema `webauthn_credentials`; contracts §3.4 | Counter rule per the WebAuthn specification (TM-120) |
| WA-2 | domain §2.2; contracts §3.4 | `userHandle` must match the credential's user; flagged credentials refused |
| WA-3 | domain §2.1 | Per-account second-factor failure counter across challenges (e.g. 20 per 24 h → hold + email) |
| WA-4 | contracts §3.4 | `frame-ancestors 'none'` (or `X-Frame-Options: DENY`) on every app page; state that `Permissions-Policy` is defence in depth |
| WA-5 | domain §2.1; event-catalogue §2 | `IdentityLinked` event → email notice + audit; OAuth `state` + PKCE |
| RC-1..6 | domain §2.3; schema `account_recovery_requests`; event-catalogue §2 | PO-J conditions |
| PO-H | domain §2.4; schema `users`; event-catalogue | Option B as above |
| RD-1 | contracts §3.2 (note for A10) | Share tokens never logged |
| — | open-questions §2.1, §4 | Record R-A..R-P outcomes and PO-H/I/J/K advice from §12.3–§12.4 |

**A10 (operations)** — not A05's documents; listed so the PO sees the whole picture.

| ID | Change |
| -- | ------ |
| SV-1 | Host proxy admin API restricted on the shared VPS (local socket with restricted permissions, or disabled with file-based reloads). Host change, affects neighbours: **PO decision**; if refused, record as accepted residual R-8 |
| SV-2 | The host proxy proves itself to FrankenPHP (a secret header set by the proxy, checked and stripped by the in-image Caddyfile); without it, forwarded headers are not trusted. Plan §4.1 `TRUSTED_PROXIES` alone does not distinguish the proxy from any other local process |
| SV-3 | Gate C3 on F-8: only the PO's admin account in `docker` and sudo |
| SV-4 | Before 2.0 holds user data: neighbour services published on all interfaces (vps-facts) closed or accepted by the PO in writing; recorded as residual R-9 |
| SV-5 | No 2.0 secret is written to the box (C3) until the 1.x processes are stopped (C1 complete); today 1.x runs as root |
| SV-6 | Confirm no neighbour backup job or snapshot routine copies `/opt/apps/webhooktrap` or Docker volumes off the box |
| CI-1 | `deploy.sh` verifies the cosign signature and provenance itself (pinned identity), with the image repository hard-coded; resolve the mismatch between plan §3.3 (one argument) and ci-cd §4 (SHA + two digests) |
| CI-2 | `deploy.sh` checks the extracted Compose file against a policy before `up`: no `privileged`, no `cap_add`, no host network/PID/IPC, no Docker socket, no bind mounts outside `$APP_DIR`, ports only on `127.0.0.1`, fixed project name and `cgroup_parent` |
| CI-3 | `HOMEBREW_TAP_TOKEN` and Apple keys in an environment restricted to protected `agent-v*` tags; npm trusted publishing bound to the workflow **and** an environment with the PO as reviewer |
| CI-4 | No `pull_request_target` or `workflow_run` over untrusted code; no dependency caches in container-push, release and publish jobs; `persist-credentials: false` everywhere; Dependabot PRs never auto-merged |
| ENG-1 | Pick a concrete sandbox for `plan`/`verify`: recommended a separate `engine` service with `network_mode: none`, no secrets mounted and no database variables, called over a local socket; else Landlock network rules in the engine. Must be settled before instance-secret `verify` ships on self-host |

### 12.6 Residual risks added

| # | Risk | Why accepted |
| - | ---- | ------------ |
| R-7 | Mailbox control outlasting the recovery wait while the user stays away takes over a hosted account | Usual trade-off for 2FA recovery; RC-1..RC-6 narrow it; PO-J |
| R-8 | (if SV-1 refused) Local processes on the shared VPS can reconfigure the host proxy | Shared VPS (decision 41); PO to accept explicitly |
| R-9 | 2.0 on the shared VPS is only as safe as its neighbours and the host | Decision 41 (no reinstall, shared host); SV-3..SV-6 reduce it |

### 12.7 Verdicts (rev 3)

| Document | Verdict |
| -------- | ------- |
| ADR-010 (rev 3) | **ACCEPT WITH CHANGES** (RE-1, IPv6 /64, RN-1..RN-7) |
| ADR-003 amendment 1 (rev 3) | **ACCEPT** |
| ADR-004 amendment 1 (rev 2) | **ACCEPT** (A08 spike still required) |
| ADR-005 amendment 1 (rev 3) | **ACCEPT WITH CHANGES** (§9: SV-2 dependency stated; HSTS `includeSubDomains` rule) |
| ADR-006 amendment 1 (rev 2) | **ACCEPT** (runner points covered in ADR-010) |
| `domain-model.md` | **ACCEPT WITH CHANGES** (WA-1..WA-5, RC-1..RC-6, PO-H B) |
| `schema.md` | **ACCEPT WITH CHANGES** (RE-1, RN-1, WA-1, RC columns, drop option-A columns) |
| `contracts.md` | **ACCEPT WITH CHANGES** (§3.4 WA-1/2/4; §6 RN-3/5/6) |
| `event-catalogue.md` | **ACCEPT WITH CHANGES** (`IdentityLinked`, recovery → agents `needs_reconfirm`, `AgentUnconfirmedExpired`) |
| `open-questions.md` | **ACCEPT WITH CHANGES** (record §12.3–§12.4) |
| `decisions/README-phase4-notes.md` | **ACCEPT** |
| A10 `contabo-deployment-plan.md`, `ci-cd.md`, `compose-profile.md` | **ACCEPT WITH CHANGES** (SV-1..SV-6, CI-1..CI-4, ENG-1); gates on go-live, not on ADR acceptance |

**Blocking ADR acceptance:** none of the above is a design rejection. ADR-010 and the identity sections can be accepted once A05 applies the A05 rows of §12.5 and the PO decides PO-H and PO-J. SV-1..SV-6 and CI-1..CI-2 block go-live (C3/C6), not acceptance.

### 12.8 Tests added (for A11)

| Test | Checks |
| ---- | ------ |
| TM-T120 | Stored count 57, received 0 → refused and flagged; both 0 → accepted |
| TM-T121 | Discoverable assertion with another user's handle → refused |
| TM-T122 | Second-factor failures across fresh sign-ins trip the per-account hold |
| TM-T125 | Recovery: auto-cancel on 2FA sign-in; reset extends the wait; email change refused while open; completion sets agents `needs_reconfirm` |
| TM-T130 | Runner token from an unbound address → refused on hosted |
| TM-T131 | Hosted runner given an `allowlist` job → refuses |
| TM-T132 | Server run to the runner-API host → refused by the guard |
| TM-T133 | Second secrets fetch, fetch after final, fetch by another runner → refused and audited |
| TM-T134 | `serve-run` with a `webhooktrap.yml` in its working directory executes no probe |
| TM-T141 | Direct local request to the app port with forged forwarded headers → forwarded values ignored |
| TM-T145 | `/s/{token}` absent from access logs |
| TM-T146 | Compose policy check rejects a privileged service, a host bind mount and a socket mount |

### 12.9 Verification (rev 4)

**Date:** 2026-11-14 · **Read (designs, not change logs):** A05 revision 4 (ADR-010, 0005-amendment-1, domain-model §2.1–2.4, schema, contracts §3.2/§3.4/§5.3/§6, event-catalogue §2, open-questions §2.2/§4); A10 revision 3 (`contabo-deployment-plan.md`, `compose-profile.md`, `ci-cd.md`, `1x-decommission-runbook.md`, `vps-facts.md`). A10's Caddy, cosign and policy text was checked by reading only, not by running tools. Detail: private companion P-23 to P-26.

**A05 rows of §12.5**

| Item | Status | Evidence |
| ---- | ------ | -------- |
| RE-1 | APPLIED | ADR-010 §4 rule 5; schema `agents` (`unconfirmed`, `CHECK`, expiry job); contracts §5.3; event `AgentUnconfirmedExpired` |
| IPv6 /64 | APPLIED | ADR-010 §4 rule 1 (package-wide); domain §2.1 Limits; RC-5 |
| RN-1 | APPLIED (A05 part) | ADR-010 §12.1; schema `runners` (`binding_required` + `CHECK`); contracts §6. Host-proxy path restriction: see A10 below |
| RN-2 | APPLIED | ADR-010 §12.1; schema `runners.issued_at` |
| RN-3, RN-4, RN-5, RN-7 | APPLIED | ADR-010 §12.1, §12.5; contracts §6 rows and "Local ceiling" |
| RN-6 | APPLIED (minor gap) | ADR-010 §12.4; contracts §6; event `RunnerSecretsRefetchBlocked`. Schema has no column that makes "one successful fetch" atomic: add `run_jobs.secrets_fetched_at`, set by a conditional update |
| WA-1, WA-2 | APPLIED | domain §2.2 Counters / User handle; schema `webauthn_credentials`; contracts §3.4 `/webauthn/assert` |
| WA-3 | APPLIED | domain §2.1; schema `second_factor_failures`, `users.second_factor_hold_until`; contracts §3.4; events `SecondFactorFailed`/`Hold` |
| WA-4 | APPLIED | contracts §3.4 Headers; domain §2.2 RP |
| WA-5 | APPLIED | domain §2.1; event `IdentityLinked` |
| RC-1..RC-6 | APPLIED | domain §2.3; schema `account_recovery_requests`; contracts §3.4; event-catalogue §2. Clarify that a passkey sign-in counts as "completes a second factor" for RC-1 |
| PO-H (B) | APPLIED (pending PO) | domain §2.4; schema `users` CHECK; no hash columns or `LegacyHashDiscarded` left. Editorial: open-questions §4 PO-H option label still says "passkey activation" |
| RD-1 (A05 part) | APPLIED | contracts §3.2 |
| §12.3 R-O (ADR-005 am. §9) | APPLIED | items 4 (SV-2 dependency, TM-T141) and 5 (HSTS rule) |
| open-questions | APPLIED | §2.2, §4 |
| ENG-1 alignment | PARTIAL (editorial) | ADR-010 §1 rules 1–3 and open item 5 still describe the engine "inside the app container" (namespace or seccomp, stdin/argv); align with the `engine` service of `compose-profile.md` §2 |

**A10 rows of §12.5 and A10 notes from A05's documents**

| Item | Status | Evidence / required change |
| ---- | ------ | -------------------------- |
| SV-1 | APPLIED (PO decision) | plan §3.5, gate G-5. Under O3 the edge secret of SV-2 is also readable through the admin API (plan §3.4 residual) |
| SV-2 | **WRONG as written** | plan §3.4; compose-profile §2.1. (1) The `trust-site-*` snippet is a top-level `route`, which Caddy orders **after** `handle`; every request ends in a `handle` block, so the check and the stripping never run. Put the snippet inside an outer `route { … }` that wraps the `handle` blocks (order is then as written). (2) Fail-closed: the app must refuse to start if the edge secret is missing, unreadable or not 64 hex characters (a root `0600` Compose file secret is not readable by a non-root FrankenPHP user; an empty placeholder must never match). (3) Strip header-name variants that PHP maps to the same variable (underscore spellings of `X-WT-Verified` and `X-Forwarded-*`), or compare the edge secret in PHP with `hash_equals`. TM-T141 extended accordingly |
| SV-3..SV-6 | APPLIED | plan §8.1 G-1..G-4, §8.2 A-1..A-6; runbook §M |
| CI-1 | APPLIED (corrections) | ci-cd §4, plan §3.3; one grammar. Fix: copy `.env.deploy` to `.previous` only after steps 2 and 5 pass; prove the on-box attestation command against the format `attest-build-provenance` actually pushes (new test TM-T147: unsigned digest, other workflow, other SHA, missing attestation → refused) |
| CI-2 | **PARTIAL** | ci-cd §4.2. Add: top-level `volumes` with `driver` or `driver_opts` refused (a local-driver bind of `/` is a named volume); top-level `configs` and `secrets` with `file` only from `$APP_DIR/secrets` (configs today unchecked); `oom_score_adj` ≥ 0; never print the resolved config (it inlines `.env.production`). The YAML in compose-profile §2 must carry `mem_limit`/`pids_limit` everywhere or it fails P-8 |
| CI-3 | APPLIED | ci-cd §1.4, §5, §8. Note: notarisation runs in `build`, which declares no environment; move it or the keys if signing is chosen |
| CI-4 | APPLIED | ci-cd §1.4. State `cache: false` on `setup-go`/`setup-node` in release and push jobs (setup-go caches by default) |
| ENG-1 | APPLIED | compose-profile §2 `engine` (`network_mode: none`, no secrets, socket), P-3. New ENG-2 below |
| RD-1 (A10 part) | APPLIED, scrubbing incomplete | plan §4.5, compose-profile §2.1. Add to both log filters: capture paths on `in.` in **error** logs (access logs are already off), and the `X-Wt-Edge` request header |
| RN-1 host-proxy restriction | **MISSING** | ADR-010 §12.1 [A10]: `app.` vhost answers `/runner/v1/*` only from the runner VM's address (Caddy `remote_ip`), independent of forwarded headers |
| HSTS rule | **MISSING** | ADR-005 am. §9 item 5: plan §4.1 must say no `includeSubDomains`/`preload` on `app.`, `ws.` or the apex, with a header test |

**New (from the fixes):** **ENG-2** the sandbox server handles one call per short-lived child (or holds no state across calls), enforces the 5 s / memory / concurrency limits itself, never logs inputs, drops the instance secret after each `verify`, and exposes only `plan`/`verify` (no probe/reset code path, no config file read, as RN-7). App validates its output as untrusted.

**Verdicts (rev 4).** A05 package: **ACCEPT** (ADR-010, all amendments, domain-model, schema, contracts, event-catalogue, open-questions), with the RN-6 column, RC-1 wording and ENG-1 alignment as editorial follow-ups. ADR-010 and amendments can be accepted as soon as the PO decides PO-H and PO-J (and confirms PO-I). A10 `contabo-deployment-plan.md`, `compose-profile.md`, `ci-cd.md`: **ACCEPT WITH CHANGES** (SV-2 fix, CI-2 rules, RN-1 vhost rule, HSTS rule, log filters, CI-1 order and TM-T147, ENG-2); `1x-decommission-runbook.md`, `vps-facts.md`: **ACCEPT**. A10 items gate go-live (G-6 also requires TM-T141 and TM-T147), not ADR acceptance.

**Tests added:** TM-T141 extended (underscore spellings; empty `X-WT-Edge`; missing secret file → app does not start); TM-T147 on-box verification refusals; TM-T146 extended (volume `driver_opts` bind, `configs` file outside `$APP_DIR`); TM-T148 `/runner/v1` from another address → refused at the host proxy; TM-T149 engine: no outbound socket, no state across calls, no file read in its working directory.

### 12.10 Verification (rev 5)

**Date:** 2026-11-14 · **Read (designs, not change logs):** A05 revision 5 (ADR-010 §1 and §12.4, 0006-amendment-1 §3, domain-model §1 and §2–§2.9, schema `run_jobs`, `account_recovery_requests`, `email_change_requests`, `user_sessions`, `audit_log` and the security log, contracts §3.4 and §6, open-questions §2.3, §2.4, §4); A10 revision 4 (`contabo-deployment-plan.md` §3.4, §4.1, §4.5, §8.1; `compose-profile.md` §2, §2.1, §2.2; `ci-cd.md` §1.4, §4–§4.3). Caddy, cosign and policy text judged by reading only; no tool was run. Detail: private companion P-27 to P-30.

**A05: §12.9 editorial follow-ups**

| Item | Status | Evidence |
| ---- | ------ | -------- |
| RN-6 `secrets_fetched_at` | APPLIED, correct | schema `run_jobs` + conditional `UPDATE … RETURNING` (runner_id set at accept, run `running`, executor `server_allowlist`, `secrets_fetched_at IS NULL`); lost response fails closed; ADR-010 §12.4 item 4; contracts §6 |
| RC-1 passkey = second factor | APPLIED | domain §2.3 RC-1. **Confirmed:** a factor passed at `/confirm-auth` in an existing session is the same evidence and also cancels; password-only confirmation does not |
| PO-H label | APPLIED | open-questions §4 PO-H: "a passkey is not an activation method" |
| ENG-1/ENG-2 alignment | APPLIED | ADR-010 §1 rules 1–7, open item 5, Consequences; 0006 am. §3; schema `instance_secrets`; domain §1 |

**A05: new account features (R-Q..R-X and related)**

| # | Verdict | Notes / required change |
| - | ------- | ----------------------- |
| R-Q Key unblock | **Accept** | Sign count kept, second flag final, another method required, flagged key never satisfies recent auth or counts as a factor. Test TM-T155 |
| R-R Connect / disconnect | **Accept with change AR-1** | Provider email need not match: agreed. `identity_in_use` names only what the person just proved: agreed. Connecting during RC-3 creates a gap: whoever completed a recovery can link their own provider and the RC-3 removal lock then shields it for 7 days against the mailbox owner who resets the password. **AR-1:** an identity linked after the recovery was requested is **not** covered by the RC-3 removal lock (removable with recent auth), and its `IdentityLinked` notice says a recovery is open/recent. (Refusing connect during the lock is an acceptable alternative.) |
| R-S Email change | **Accept**; residual R-10 | No revert link is right for 2FA accounts (a revert from the old mailbox would be an email-only undo of a factor-backed action). On password-only accounts, anyone holding the password and a fresh session can move the account; the same person could already change the password, so this is inherent to password-only accounts (R-10). Recommended: a password reset or change cancels a pending email change |
| R-T OAuth-only re-auth | **Accept**; residual R-11 | GitHub proves only a live GitHub session in that browser: acceptable because a stolen Webhook Trap cookie used elsewhere does not carry it. Recommended: offer Google first when both are linked; prompt OAuth-only accounts to add a passkey |
| R-U Emailed links | **Accept** | `GET` page / `POST` effect, no consumption on `GET`, identical error page, `no-referrer`, `no-store`: correct. Lifetimes 60 min / 24 h accepted. Token-path masking is an A10 item (RD-2 below). **AR-2:** the reset `POST` on a **2FA account** must not yield a signed-in session without the 2FA challenge; `reset_link` (schema `user_sessions.first_factor`) is never a second factor and never sets recent authentication. State it in domain §2.1 and the contracts reset row. Test TM-T151 |
| R-V RC-5 reading, password clearing | **Accept** | Only post-confirmation cancellations start the cool-down: correct (otherwise anyone who knows an address could lock its owner out). Clearing the password at completion is right: it removes any password set by a mailbox holder during the wait (RC-2) and the finish link goes to the same root of trust. `/confirm-auth` with a factor cancelling: confirmed (above). Test TM-T154 |
| R-W Password policy | **Accept** | 12–128 code points, NFC, no truncation, bundled list, bcrypt 72-byte cap stated. A06 records argon2id parameters (at least OWASP's minimum) in the test that asserts the algorithm |
| R-X Sessions | **Accept** | /24 and /48 prefixes and a UA label only: proportionate. Revoke-others behind recent auth: correct. Recommended: the ≤ 30 s liveness cache lives in Valkey (or is skipped), never in worker-process memory (FrankenPHP worker mode), and the revoking request deletes the entry. Test TM-T156 |
| `email_change_requests` | **Accept** | One pending, uniform answer, RC-3 re-check and uniqueness in the confirming transaction, cancelled by recovery |
| Security log | **Accept** | Allow-list read model; no `before`/`after`, codes or other people's emails |

**A10: §12.9 items**

| Item | Status | Evidence / required change |
| ---- | ------ | -------------------------- |
| SV-2 order | APPLIED, correct | compose-profile §2.1: one outer `route` wrapping the trust snippet and the `handle` blocks; citation to Caddy's "Directive order" (`handle` and `route` in one group sorted `handle` first; `route` contents keep written order) is accurate; `caddy adapt` order check in TM-T141 |
| SV-2 start refusal | APPLIED | entrypoint check as runtime user (exists, readable, non-empty, 64 hex); `root:<gid> 0440`; `header_regexp` second guard; `PROXY_TRUST_MODE` defaults to `secret` |
| SV-2 underscore stripping | **PARTIAL (SV-2a)** | Underscore spellings of `X-Forwarded-*` and `X-Real-IP` are removed **only for unverified requests**. The host proxy overwrites the hyphen forms but forwards `X_Forwarded_For` untouched (Caddy, unlike nginx, does not drop underscore names), so on a verified request PHP receives two values for `HTTP_X_FORWARDED_FOR`. **Fix:** strip every underscore spelling, plus `Forwarded`, `X-Real-IP` and `X-Forwarded-Port`, **unconditionally**; Laravel trusts only the headers the host proxy sets. TM-T141 gains the case through the real Caddy proxy with the right secret (CI nginx would hide it) |
| TM-T141 | APPLIED | extended as asked; add the SV-2a case |
| CI-2 P-5 / P-10 / P-11 | APPLIED | ci-cd §4.2; YAML carries limits on all eight services |
| RN-1 `remote_ip` + TM-T148 | **APPLIED with gap (RN-1a)** | Peer address, no `client_ip`, IPv4 literal only, 404: correct. The `path /runner/v1*` matcher misses `/index.php/runner/v1/…`, which `php_server` splits into `PATH_INFO` and Laravel routes to the runner API. **Fix:** match the segment anywhere (`path_regexp (?i)/runner/v1(/|$)`), or have the image Caddyfile refuse any path with a `.php/` segment; add both spellings to TM-T148 |
| HSTS + TM-T150 | APPLIED | plan §4.1 rule and header test; **TM-T150 assigned** to that test |
| CI-1 `.previous` + TM-T147 | APPLIED, one slip (CI-1a) | `.previous` written only at step 6; TM-T147 covers unsigned, other workflow, other SHA, missing attestation, state unchanged. Slip: the extracted Compose file is **installed at step 5**, so a failure at step 7 (backup) or 8 (migrate) leaves the new file beside the old `.env.deploy` although step 7 says "nothing changed". Install it at step 6 with the env files, or restore it on any later failure |
| Log redaction | **PARTIAL (RD-2)** | Image filter redacts the whole URI and `X-Wt-Edge`: good. The host-proxy `app.` access log skips only `/s/`. Emailed-link tokens are in the path (`/reset-password/{token}`, `/email-change/{token}`, recovery confirm and finish; contracts §3.4 note for A10). **Fix:** skip or redact those paths, and `/index.php/s/…`, in `app.` access and error logs; TM-T145 adds canaries for each |
| ENG-2 | APPLIED | compose-profile §2 notes; ADR-010 §1 rules 3–7; TM-T149 |

**Residual risks added**

| # | Risk | Why accepted |
| - | ---- | ------------ |
| R-10 | On a password-only account, a holder of the password and a fresh session can change the email with no revert path | Inherent to single-factor accounts; notice to the old address; 2FA accounts need a second factor |
| R-11 | OAuth-only re-authentication through GitHub proves a live GitHub session in that browser, not a fresh login | GitHub offers no forced re-login; offered only to accounts with no other method |

**Tests added:** TM-T141 (SV-2a: `X_Forwarded_For` through the real Caddy proxy with the right secret → ignored); TM-T145 (reset, email-change and recovery token canaries; `/index.php/s/<canary>`); TM-T148 (`/index.php/runner/v1/x`, `/index.php%2Frunner/v1/x`); **TM-T150** HSTS header test (plan §4.1); **TM-T151** reset on a 2FA account → 2FA challenge, no session, no recent auth; **TM-T152** identity linked during an open recovery or the lock is removable (AR-1); **TM-T153** opening any emailed link with `GET` (scanner) consumes nothing and changes nothing; **TM-T154** `declined`/`link_expired` → no cool-down, post-confirmation cancel → 30 days, completion clears the password and revokes all sessions and tokens; **TM-T155** unblock keeps the count (lower count re-flags), second flag → `unblock_not_allowed`, flagged key refused at `/confirm-auth`; **TM-T156** revoked session refused within 30 s on every worker; revoke-others without recent auth → refused.

**Verdicts (rev 5).** A05 package (ADR-010, amendments 0003–0006, domain-model, schema, contracts, event-catalogue, open-questions): **ACCEPT**, with AR-1 and AR-2 as two short A05 edits that need no PO decision. A10 `contabo-deployment-plan.md`, `compose-profile.md`, `ci-cd.md`: **ACCEPT WITH CHANGES** (SV-2a, RN-1a, RD-2, CI-1a); `1x-decommission-runbook.md`, `vps-facts.md`: **ACCEPT** (unchanged). A10's changes gate go-live (G-6 also requires the extended TM-T141, TM-T145, TM-T148 and TM-T150), not ADR acceptance.

**Readiness.** From the security side the Phase 4 package is ready for PO acceptance, pending only PO-H, PO-I and PO-J (with R-7) and the go-live gates G-1..G-6, on one condition: A05 applies AR-1 and AR-2 (wording only, no design reversal). No further A09 review round is needed for them; A09 checks them, and A10's four fixes, at the G-6 sign-off.

### 12.11 Verification (rev 6)

**Date:** 2026-11-14 · **Read (designs, not change logs):** A05 revision 6: domain-model §2.1 (recent authentication, password hashing, throttle values), §2.4, §2.6, §2.7, §2.9, §2.10, §4 ingest, §11; schema `users`, `user_sessions`, `password_set_tokens`, `audit_log` and the security-log allow-list (§7), §8 retention and backups; contracts §3.4 (`/confirm-auth`, `PUT /account/password`, `/email-change`, sessions, `DELETE /account`, `/set-password`, password policy); event-catalogue `AccountDeleted`, `SessionRevoked`; open-questions §2.6 R-Y..R-AG, §4 PO-H/PO-L/PO-M, §5 K-23; decisions 44–46. No tool was run. Detail: private companion P-31 to P-35.

**R-Y..R-AG**

| # | Verdict | Notes / required change |
| - | ------- | ----------------------- |
| R-Y Deletion: who, refusals | **Accept with change DL-1** | Second factor on 2FA accounts, `recovery_lock` (RC-3 extended, including the 7-day lock), `last_instance_admin`, runs aborted rather than refused, everything re-checked in the transaction under a row lock: correct. **DL-1 (required):** deletion is the one irreversible action, so it must not ride on a recent-authentication window obtained earlier in the session. `DELETE /account` accepts only a confirmation made through S-28 **for `action=delete_account`** in this session within the last 5 min, consumed by the `DELETE` (one use). A session cookie copied during the 10-minute window then cannot delete without a factor. (Same rule recommended, not required, for an email change on any account) |
| R-Y Tombstone and purge | **Accept with change DL-2** | One transaction, current session included, share tokens deleted, agents revoked, email enqueued with the address copied into the job: correct. **DL-2 (required):** during the ≤ 1 h tombstone, every path keyed on the address or a linked identity is a uniform no-op: "Forgot password", verification resend, recovery request, OAuth sign-in by provider subject or by verified email (no new account, no link, the uniform sign-in failure), passkey assertion; and ingest's key cache (domain §4 "resolve key (cache)") holds owner liveness for at most a few seconds or is evicted by the tombstone, so "ingest stores nothing" holds at once. Test TM-T157 |
| R-Y Audit retention | **Accept**, notes | 1 year, ids as text, masking before the FK sets NULL (schema order is right). Mask **every** email-shaped value in the user's rows (actor or subject), including old and new addresses from past changes, not only the current address. `email_hmac` uses its own key (not `APP_KEY`, not stored in the database or its backups); see PO-M below for how support may use it |
| R-Z Backups and ledger | **Accept with change DL-3 (A05 + A10)** | 7-day PITR and 14-day off-server tail, never restored for one account, ledger re-applied before traffic: correct. **DL-3 (required):** (a) the ledger append is not a fire-and-forget listener: the purge job must not delete the `users` row until the ledger entry for it is durably written (idempotent append keyed on `usr_` id), so a lost listener cannot leave a deletion out of the ledger; (b) the ledger is copied to the **off-server** backup location too and kept at least as long as the oldest backup (≥ 14 days + margin), otherwise a restore after losing the VPS resurrects accounts; (c) the restore runbook re-applies the ledger **before the app, workers and queues start** (restored rows hold live sessions, tokens and agents). Test TM-T158 (restore drill) |
| R-AA Throttles | **Accept with change TH-1** | Values are proportionate; per-email keys counted for unknown addresses; the account hold leaves passkey, OAuth and reset working: correct. **TH-1 (required):** (a) during a pair or account hold the password is **not verified** and the answer is the throttled answer whatever the password (no correct-password oracle during the hold); (b) password failures at `/confirm-auth` count toward the per-account 20 / h key, and TOTP, recovery-code and security-key failures there count toward WA-3, so a stolen cookie does not get a separate budget; (c) an unknown email, an account with no password and a correct email with a wrong password take the same argon2id cost (verify against a fixed dummy hash), otherwise the ~300 ms hash time enumerates accounts despite the uniform text. Residual: 30 failures / 15 min per IPv4 address can block a shared NAT for 15 min (accepted; A10 adds an instance-wide failed-sign-in rate alert). Tests TM-T159, TM-T160 |
| R-AB argon2id only | **Accept**; A10/A08 note | 64 MiB, t = 4, p = 1 is above OWASP's floor; boot refusal is right; bcrypt and the 72-byte limit gone. **A08/A10:** size the number of concurrent password hashes (FrankenPHP workers × 64 MiB, plus the dummy-hash path of TH-1c) inside the app container's `mem_limit`, so a burst of sign-ins cannot OOM the shared VPS; the throttles run before any hash. **Editorial ED-1:** contracts §3.4 "Password policy" still says "72 bytes if the image falls back to bcrypt" in its first sentence; remove it |
| R-AC Idempotent revoke | **Accept** | Same answer for unknown, foreign and ended ids, nothing written; `current_session` 422 reveals nothing (it is the caller's own). Session ids are random; no audit needed for a no-op |
| R-AD Email-change link page | **Accept** | `invalid` checked first, `sign_in_required` shows no address, `other_account` shows only the viewer's own address, intended URL in the session (survives regeneration after the 2FA challenge; A06 test), never in `next`. Dropping `currentPassword` is right: recent authentication is the proof and DL-1 covers the irreversible case. Recommended (repeat of §12.10): a password reset by email cancels a pending email change |
| R-AE Revert link | **Reject the revert link**; see advice below | |
| R-AF Invites / `/set-password` | **Accept with change SE-1** | Hashed token, 24 h, single use, `GET` consumes nothing, newer voids older, AR-2 applied, sessions revoked, uniform "Invited", no token on `/register`: correct. **SE-1 (required):** the `set_email` restore (host console, after a disputed change) keeps the account's factors, but whoever moved the account may have added a passkey, a TOTP or a linked identity of their own. The console command lists factors and identities added since the disputed `email_change_requested` row and removes them by default (`--keep-factors` to opt out), and the restored owner lands on a review like S-30. Test TM-T161 |
| R-AG Security-log codes | **Accept**; recommendation SL-1 | 26 codes, display params only, never `before`/`after`, other people's emails or token values: correct. **SL-1 (recommended):** a self-host administrator's console actions **on the user's own account** (set-email restore, admin factor reset, disable) should appear in that user's log (e.g. `email_changed` with `by: admin`, a new `factors_reset_by_admin`); today "every admin action never appears" hides them from the person they affect |

**K-23 vs decision 46 (PO-H).** No conflict in behaviour. 1.x had no passkeys and an imported account cannot enrol one before its email is proven, so "a passkey" in decision 46 is reachable only after activation by reset or provider-verified OAuth. Domain §2.4 stands (it is the pre-hijack rule). **A00:** annotate decision 46 ("a passkey, once the account is activated"); **A03:** the `launch-1x` notice must offer only "set a password" and GitHub/Google with the same address.

**Advice for the PO**

- **R-12 (password-only deletion).** Accept, narrowed by **DL-4 (recommended):** on an account without a second factor, `DELETE /account` sends a confirmation link to the account address (60 min, interstitial, `POST` on the button, §2.9) and deletes on that button; the residual then needs the password **and** the mailbox, which already owns a password-only account through "Forgot password". Without DL-4, R-12 is still acceptable (same class as R-10) but irreversible. If DL-4 is adopted, its token path joins the list for A10's log scrubbing.
- **R-AE ("This wasn't me" revert link).** Do not adopt. It turns the old mailbox into an email-only undo for 7 days, which is exactly the window after someone leaves a compromised or former-employer mailbox, and the revert then hands the account to that mailbox through the forced reset. Prefer **EC-1 (recommended):** on an account without a second factor, an email change also needs a button press from a link sent to the **old** address (both addresses confirm); a person who lost the old mailbox enrols a passkey or TOTP first and then changes with the second factor. EC-1 removes R-10 for owners who still hold their mailbox. If the PO declines EC-1, keep R-10 unchanged.
- **PO-L (immediate vs grace).** **Immediate**, as A05 recommends, with DL-1 (and DL-4 if adopted). A grace period adds no protection, since whoever can delete can also cancel, and it creates a dormant account that can be signed into while capture URLs and data sit in between.
- **PO-M (hosted support).** Adopt A05's line: no restore of addresses, accounts or factors by the operator, and no single-account restore from backups. Support may (1) point to the self-serve routes (passkey or provider sign-in, delayed recovery); (2) say whether an account with an address existed or was deleted **only by writing to that address**, never to another channel or address, so `email_hmac` is not an enumeration oracle; (3) process an erasure request from someone who lost access by sending them through recovery and then deletion (≤ 72 h + 7-day lock, inside a one-month legal deadline). Operator deletion with the console command is for verified legal orders only, never on an emailed request alone. Any operator restore path needs an A09 model first.

**Residual risks**

| # | Risk | Status |
| - | ---- | ------ |
| R-12 | A holder of the password and a fresh session deletes a password-only account irreversibly (with DL-4: password and mailbox) | Proposed for PO acceptance with PO-L; `account-deleted` email to the owner |
| R-13 | Deleted data remains in encrypted backups for up to 14 days; a restore within that time depends on the ledger (DL-3) | Accepted; disclosed in the privacy notice |
| R-14 (residual; not the R-14 Twice check of decision 32) | A shared IPv4 address can be blocked from signing in for 15 min by others' failures | Accepted; per-address limit needed against spraying |

**Tests added:** **TM-T157** during the tombstone: reset, verification resend, recovery request, OAuth (subject and verified email) and passkey are uniform no-ops; a capture to the deleted owner's key within 1 s of the `DELETE` stores nothing; **TM-T158** restore drill: restore to before a deletion, ledger re-applied before any worker or web process starts, the account's sessions, tokens and agents refused; ledger also present off-server; a crash between commit and listener still yields a ledger entry before the row is purged; **TM-T159** during a hold, the correct password gets the throttled answer and no hash runs; `/confirm-auth` failures move the account counters; **TM-T160** sign-in timing: unknown email, password-less account and wrong password within the same latency band; **TM-T161** `set-email` restore removes factors and identities added after the disputed change; **TM-T162** `DELETE /account` with a recent authentication from before the deletion screen, or reused once, is refused (DL-1).

**Verdicts (rev 6).** A05 revision 6 (domain-model, schema, contracts, event-catalogue, open-questions §2.6): **ACCEPT WITH CHANGES**: DL-1, DL-2, DL-3 (with A10), TH-1, SE-1, ED-1 are short A05 edits with no design reversal; DL-4, EC-1, SL-1 are recommendations for the PO (PO-L, R-12, R-AE). A10: ledger off-server copy and retention (DL-3b), restore order (DL-3c), failed-sign-in alert, argon2id memory budget with A08. ADR acceptance (decision 46) is unaffected; the required items gate the identity build (A06) and G-6.
