# Event catalogue · Webhook Trap 2.0

**Status:** Proposed, revision 6.3 (applies decision 50 and A08's spike report: ADR-004 amendment 1 confirmed with changes); revision 6.2 (applies decision 47: PO-L, PO-M, PO-N, PO-O decided; DL-4 and EC-1 designed); revision 6.1 (applies A09 §12.11: DL-1, DL-2, DL-3, TH-1, SE-1, ED-1; R-AE rejected); revision 6 (authentication events and the 1.x import, decisions 41 and 44; revision 4 applies A09's re-check rev 3; revision 5 adds the account-security events behind A03's S-17A/B, S-29..S-31; revision 6 adds account deletion, the password sign-in hold and invites) · **Author:** A05 · **Date:** 2026-11-14
**Security review:** A09 `project/security/threat-model.md` §9.9 (ACCEPT WITH CHANGES). Decisions 38 and 39 applied.
**Read with:** ADR-004 and its amendment 1 (direct Echo events for run timelines, Live Props for lists), ADR-007 (queues), `domain-model.md`.
**Probe data in events:** follows A09's C-3 review (PV-6): booleans and display values only, never raw values.

Two kinds of events:

- **Domain events** — PHP event objects dispatched after commit inside the monolith. Listeners in other modules react. Never sent to browsers as such.
- **Broadcast events** — what Reverb sends to subscribed clients. Either a **Live Prop trigger** (tells Inertia which prop to reload; no data) or a **direct event** (small payload applied client-side).

Rules for every broadcast (ADR-004 + amendment 1):

1. Dispatched after commit, on the `broadcasts` queue. Persistence never waits for Reverb.
2. Payload is identical for every authorised subscriber of the channel.
3. Never: bodies, header values, signature values, secrets, secret names, probe definitions, raw probe values, capture keys, emails.
4. Target size < 1 KiB.
5. A JSON Schema per broadcast event in `contracts/broadcast/`, with `additionalProperties: false`. A canary test (bodies, header values, secret names, emails, capture keys, raw probe values, token values) runs a full run and asserts none appears in any broadcast payload (TM-23).

---

## 1. Channels

| Channel | Who may subscribe | Authorised by | Carries |
| ------- | ----------------- | ------------- | ------- |
| `private-project.{projectId}` | the principal (`web` user or `guest` session) that owns the project's workspace | `ProjectPolicy::view` | capture stream, runs list, fixtures list, signature verdicts |
| `private-run.{runId}` | the owner principal of the run's project | `RunPolicy::view` (owner only; shared viewers do not subscribe) | run timeline direct events |
| `private-workspace.{workspaceId}` | the owner principal | `Workspace::isOwnedBy` | agent status, pairing completion |
| `private-agent.{agentId}` | that agent only (`agent` guard, valid access token, not revoked) | `AgentChannel`, via `/agent/v1/broadcasting/auth` only | job nudges, revocation |
| `private-session.{guestSessionId}` | that guest session | `guest` guard | session expiry warning, end |

Authorisation rules (TM-20, TM-22; ADR-004 amendment 1 §4):

1. **Two auth endpoints.** Browser channels authorise through `/broadcasting/auth` with the `web` and `guest` guards only. `private-agent.*` authorises through `/agent/v1/broadcasting/auth` with the `agent` guard only.
2. **Principal type first.** Each channel callback checks the principal type before ownership: `Agent` only for `private-agent.{its own id}`; `User`/`GuestSession` never for `private-agent.*`; `Agent` never for any browser channel, including `private-workspace` of its own workspace.
3. The server runner has no Reverb access (it long-polls, ADR-010 §12).
4. **Client events (whispers) are disabled explicitly.** `accept_client_events_from` is set to `none` in the Reverb app config. It is never left at Reverb's stock `members` (which relays whispers) or omitted (a missing key defaults to `all`). A config test fails on `members`, on `all` and on a missing key (C-4, TM-T22). With `none`, a `client-*` event gets `pusher:error 4301` and is not relayed.
5. **Per-principal connection cap and connection maximum lifetime are our code inside the Reverb process, not Reverb settings** (Reverb has neither; A08 spike C-1). Both rely on rule 6. Over the cap: error `4004` (the client must not reconnect), then close. Lifetime reached: error `4200` (the client reconnects, which re-authorises), then close. Values (config, not code): guest 10, user 20, agent 2 sockets; lifetime 1 h, checked every 30 s, so a missed terminating event lasts at most about 1 h 30 s. The lifetime counts from the socket's first subscribe. Limits also apply at Reverb (`REVERB_APP_MAX_CONNECTIONS` below the file-descriptor ceiling) and at the proxy (connections per address), because raw sockets that never subscribe still use file descriptors (A10, H-3).
6. **Both auth endpoints sign the principal into the subscription data (C-3).** The response is `{"auth":"<key>:<sig>","channel_data":"{\"user_id\":\"<type>:<id>\"}"}` with `<type>` one of `user`, `guest`, `agent`, on **private** channels too, and the signature covers `socket_id:channel:channel_data`. Reverb stores it per socket, which is how the cap, the lifetime and `terminate_connections` know whose sockets they are. The two endpoints are two dedicated controllers: Laravel's `Broadcast::routes()` and the `channels:` routing option are **not** used, because Laravel lists guards per channel and a request carrying two credentials would fall back to the other one. A denial is a bare `403`.
7. **Reverb image:** `ext-uv` or `ext-event` is required. Without it ReactPHP uses `stream_select` and the whole Reverb process dies at about 1,014 sockets, dropping every client (A10, H-3).

No public channels. Viewers of a shared run (`/s/{shareToken}`) that is still running get a 5 s Inertia poll, rate-limited per address (ADR-004 amendment 1 §5). Channel ids are entity UUIDs (not keys); knowing one grants nothing (ADR-004).

**Terminating events (TM-21).** When access ends, the server sends a terminating event on the affected channel; the client leaves all its channels and reloads. **Then the server closes the principal's sockets (C-2):** in the same `broadcasts` job, event first, it calls Reverb's `users/{principal}/terminate_connections` for the `<type>:<id>` signed in rule 6. This closes every socket of that principal, including one that never subscribed to the affected channel and would never leave on its own (measured: 7 to 9 ms). A client that reconnects is refused at auth. The connection lifetime (rule 5) stays as the backstop if the call does not reach Reverb. The same call follows `AccountDeleted` (the user's sockets) and `RecoveryCompleted` (the user's sockets).

| Domain event | Broadcast | Channel |
| ------------ | --------- | ------- |
| `AccountDeleted` | `agent.revoked` per agent (above); then `terminate_connections` for `user:{id}` | `private-agent.{id}` |
| `AccountRecoveryCompleted` | none; `terminate_connections` for `user:{id}` (sessions are already revoked) | — |
| `GuestSessionEnded` | `session.ended` (`reason: user \| expired`) | `private-session.{id}` |
| `GuestSessionClaimed` | `session.ended` (`reason: claimed`) | `private-session.{id}` |
| `AgentRevoked` | `agent.revoked` | `private-agent.{id}` |
| `RunUnshared` / share token rotated | none: share viewers never subscribe; their next poll is not found | — |

Residual R-2 (A09) shrinks: stale subscriptions last until the `terminate_connections` call (milliseconds) as long as it reaches Reverb, and otherwise until the lifetime bound.

On self-host, `instance_admin` does **not** gain subscription rights to other users' channels (IA: admin never sees users' data).

---

## 2. Domain events

| Event | Module | Payload (internal) | Listeners |
| ----- | ------ | ------------------ | --------- |
| `GuestSessionStarted` | Identity | session id, workspace id, project id | Metrics |
| `GuestSessionClaimed` | Identity | session id, user id, moved project id or `discarded` | Agent (set `needs_reconfirm`, clear approvals), Sharing (void guest share tokens), Workspace, Metrics, Broadcast `session.ended` (`claimed`) |
| `GuestSessionEnded` | Identity | session id, reason `user` \| `expired` | Agent (revoke all), Retention (schedule delete), Broadcast `session.ended` |
| `GuestSessionExpiring` | Identity (scheduler, 1 h before) | session id | Broadcast `session.expiring` |
| `UserRegistered` | Identity | user id, method (`password` \| `github` \| `google` \| `passkey`) | Workspace (create workspace), Metrics |
| `UserSignedIn` | Identity | user id, session id (`ses_`), first factor, second factor or `none`, passkey? | Audit (security log shown to the user), Identity (claim, if a nonce is present; **a passkey sign-in or a passed 2FA challenge cancels an open recovery, RC-1**) |
| `SignInFailed` | Identity | reason code, hashed address; **never the email** | RateLimit, Metrics |
| `EmailVerified` | Identity | user id, via (`link` \| `reset` \| `oauth` \| `password_set_link`) | Identity (discard pre-verification credentials of imported accounts, domain §2.4) |
| `PasswordChanged` / `PasswordReset` | Identity | user id, session id of the actor (change only) | Notify email; Identity (revoke every other `user_sessions` row on change, every row on reset, domain §2.1) |
| `WebAuthnCredentialRegistered` | Identity | user id, key id, kind (`passkey` \| `security_key`), synced? | Notify email "Security key added"; Audit |
| `WebAuthnCredentialRemoved` | Identity | user id, key id, actor (`user` \| `recovery` \| `admin`) | Notify email; Audit |
| `WebAuthnCounterAnomaly` | Identity | user id, key id, cause (`counter` \| `user_handle_mismatch`) | flag the credential (`flag_count + 1`); Notify email; Audit (WA-1, WA-2) |
| `WebAuthnCredentialUnblocked` | Identity | user id, key id | Notify email `key-unblocked`; Audit (domain §2.2) |
| `IdentityLinked` | Identity | user id, provider (`github` \| `google`), via (`oauth_email_match` \| `settings`) | **Notify email "Sign-in method linked"**; Audit (WA-5, TM-124) |
| `IdentityUnlinked` | Identity | user id, provider | Notify email `method-unlinked`; Audit (domain §2.5) |
| `EmailChangeRequested` | Identity | user id, change request id; **never either address** | Notify email: verification link to the new address (or the taken-address text); to the old address `email-change-approve` with the approval link on accounts without a second factor (EC-1, revision 6.2) or the link-free notice `email-change-requested` on accounts with one; Audit (domain §2.6) |
| `EmailChangeConfirmed` | Identity | user id, change request id, side (`new` \| `old`); raised only when the request has `old_required`; never either address | Audit (security log `email_change_confirmed`); no email; the second half applies the change and raises `EmailChanged` (domain §2.6, EC-1, revision 6.2) |
| `EmailChanged` | Identity | user id, change request id (raised once, after both halves when `old_required`) | Notify email to the old address (new address masked) and to the new one; Identity (delete reset tokens, revoke other sessions); Audit |
| `SessionRevoked` | Identity | user id, session id, reason (`user` \| `signed_out` \| `password_changed` \| `password_reset` \| `recovery` \| `email_changed` \| `admin` \| `expired` \| `account_deleted`) | Audit (not for `signed_out`/`expired`/`account_deleted`; the deletion has its own row); no email (the triggering event already sends one) (domain §2.7). **Not raised** when the session was unknown, foreign or already ended (idempotent revoke, revision 6) |
| `PasswordSignInHeld` | Identity | user id (only when the account exists), until; never the email or address | Audit, security log `sign_in_held`; Metrics; no email (revision 6, domain §2.1 throttles) |
| `SecondFactorFailed` | Identity | user id, kind, count in window; never the code | Identity (per-account counter across challenges; at 20 per 24 h emit `SecondFactorHold`), Audit (WA-3) |
| `SecondFactorHold` | Identity | user id, until | Notify email (once per hold); Audit; Metrics (WA-3, TM-122) |
| `TotpEnabled` / `TotpDisabled` | Identity | user id | Notify email; Audit |
| `TwoFactorStateChanged` | Identity | user id, enabled | Identity (create or delete recovery codes) |
| `RecoveryCodesRegenerated` / `RecoveryCodeUsed` | Identity | user id, codes left | Notify email; Audit |
| `AccountRecoveryRequested` / `…Confirmed` / `…Extended` / `…Cancelled` / `…Completed` | Identity | user id, completes_at, `cancelled_by` (`user` \| `second_factor_signin` \| `declined` \| `link_expired`) on cancel | Notify email (at request, confirmation, 24 h, extension, cancellation, completion). `Extended` is raised when a password reset by email falls in the 7 days before the request or during the wait (wait becomes 7 days from the later event, RC-2). `UserSignedIn` with a completed second factor (**a passkey sign-in counts**, as does a passed 2FA challenge, or `/confirm-auth` with a factor) while a confirmed request is open raises `…Cancelled` (`second_factor_signin`, RC-1). `declined` and `link_expired` close an unconfirmed request without the 30-day cool-down and send no email. On completion: remove factors, clear the password, email the finish link, revoke sessions and personal tokens, **set every agent of the user's workspace to `needs_reconfirm` with approvals cleared (listener in Agent, as on claim; RC-4)**, mark CI tokens for review in the security log, start the 7-day lock (RC-3); Audit for every step (RC-6) |
| `AccountDeleted` | Identity | user id, actor (`user` \| `admin`), counts (projects, runs, agents, tokens); **never the address** | Raised after the tombstone commits (domain §2.10). Agent (already revoked in the transaction: broadcast `agent.revoked` to close sockets), Ingest (evict the capture-key cache entries of the owner's projects at once, DL-2), Retention (append `usr_` id and time to the off-database deletion ledger at once, idempotent; **not relied on:** the purge job appends too and does not delete the `users` row until the entry is durably written, DL-3a; then purge within 1 h), Metrics. The `account-deleted` email is **not** a listener: it is enqueued inside the transaction with the address copied into the mail job. Audit row `account.deleted` written in the transaction |
| `AccountDeletionRequested` | Identity | user id, deletion request id; **never the address**; raised only for accounts without a second factor (DL-4, revision 6.2) | Notify email `account-deletion-confirm` to the account address with the `/account-deletion/{token}` link (1 h); Audit (security log `account_deletion_requested`) |
| `AccountDeletionCancelled` | Identity | user id, deletion request id, reason (`user` \| `recovery` \| `replaced` \| `email_changed` \| `expired`) | Audit and security log `account_deletion_cancelled` for `user` and `recovery` only; no email (DL-4, revision 6.2). Raised by `DELETE /account/deletion`, by opening a recovery, by a replacing request, by an email change and by the hourly job |
| `UserInvited` | Identity | user id, actor (`admin` user id or `console`); never the address | Notify email `invite` with the password-set link (self-host, domain §11); Audit `user.invited` |
| `LegacyAccountImported` | Identity | user id, source `1x` (never email or hash) | Audit (actor: host console) |
| `LegacyAccountActivated` | Identity | user id, method (`reset` \| `oauth`) | Audit, Metrics (PO follow-up) |
| `ProjectCreated` | Workspace | project id, workspace id | Capture (create capture URL) |
| `ProjectDeleted` | Workspace | project id | Retention |
| `CaptureUrlRotated` | Capture | project id, old url id, new url id | Broadcast `project.capture_url` trigger |
| `CaptureReceived` | Capture | capture id, project id, seq | Capture (derivation job), Broadcast trigger, Agent (signature check request) |
| `CaptureDropped` | Capture | reason (`quota` \| `account_quota` \| `guest_budget` \| `oversize_not_kept` \| `unknown_key` \| `rotated` \| `expired` \| `store_error`), never the key | Metrics only |
| `CaptureDerived` | Capture | capture id, provider, event type, group key, shape verdict | Fixtures (drift), Broadcast trigger |
| `SignatureVerdictRecorded` | Capture | capture id, project id, state, reason, job id, agent id | Broadcast `capture.signature` |
| `CaptureFetchedByExecutor` | Capture | capture id, job id, agent or runner id | Capture access log (TM-35) |
| `PairingCodeIssued` | Agent | workspace id, expires_at | — |
| `AgentPaired` | Agent | agent id, workspace id, os, key fingerprint | Broadcast `pairing.completed` (state `awaiting_confirmation`); **Notify account owner by email "New agent paired"** (account workspaces only) |
| `AgentUnconfirmedExpired` | Agent (scheduler, every minute) | agent id, workspace id | Agent (revoke with reason `unconfirmed`, delete mirrors); Broadcast `pairing.completed` state `expired` to the owner's browser; no email (RE-1) |
| `RunnerSecretsRefetchBlocked` | Agent (runner API) | job id, runner id, reason (`second_fetch` \| `after_final` \| `not_accepted` \| `other_runner` \| `permission_revoked`) | Audit; Metrics; **alert** (RN-6, TM-133) |
| `AgentConfirmed` | Agent | agent id, replaced agent id? (guest) | Broadcast `agent.status`; Agent (revoke the replaced guest agent) |
| `AgentRevoked` | Agent | agent id, reason (`user` \| `replaced` \| `session_ended` \| `admin` \| `claimed_rekey` \| `unconfirmed` \| `account_deleted`) | Broadcast `agent.revoked` (to agent), `agent.status`; Proving (abort its running runs: `agent_lost`; for `account_deleted` the runs were already aborted with reason `account_deleted`) |
| `AgentConnected` / `AgentDisconnected` | Agent | agent id | Broadcast `agent.status`; Proving (job timers) |
| `AgentStateReported` | Agent | agent id, version, secret names changed?, targets changed? | Capture (re-check signatures when secrets changed, G3), Broadcast `agent.status` |
| `RunQueued` | Proving | run id, executor, agent id? | Agent (nudge on `private-agent`) or, for server runs, Queue (`ExpireServerJob` on `replay`, delayed to `job_expires_at`); the runner discovers the job by long-poll |
| `RunAccepted` | Proving | run id, agent id or runner id | Identity (guest accepted-run counter, §7.3), Broadcast `run.accepted` |
| `RunObservationsRecorded` | Proving | run id, seq range, delivery numbers, provisional findings changed? | Broadcast `run.delivery` / `run.finding` / `run.scenario` |
| `RunFinished` | Proving | run id, status, reason, exit code, counts, quota_counted | Identity (guest run counter), Broadcast `run.finished`, project trigger `runs` |
| `RunUploaded` | Proving | run id, source `cli` \| `ci` | Broadcast project trigger `runs` |
| `RunShared` / `RunUnshared` | Sharing | run id, options (never the token) | Audit (owner action log), project trigger `runs` |
| `FixturesReported` | Fixtures | project id, paths changed | Broadcast project trigger `fixtures` |
| `InstanceSettingChanged` | Instance | key, actor (never secret values) | Audit |
| `ServerReplayAllowlistChanged` | Instance | entry added/removed, actor | Audit |
| `PrivateReplayPermissionChanged` | Instance | user id, granted/revoked, actor | Audit (TM-91) |
| `InstanceSecretUsed` | Instance | secret id, run id, runner id | Audit (TM-73) |
| `RunnerConnected` / `RunnerRevoked` | Proving | runner id | Metrics; `/health` |

---

No authentication event is broadcast. A browser learns of a removed credential or a completed recovery only at its next request (sessions are revoked server-side).

## 3. Broadcast events

### 3.1 Live Prop triggers (lists)

Payload is empty except a scope hint; the client reloads the named prop.

| Event | Channel | Reloads prop | Throttle |
| ----- | ------- | ------------ | -------- |
| `project.captures` | project | `captures.latest` (bounded latest 50) and `meter` (guest) | coalesce 250 ms per project |
| `project.runs` | project | `runs` (Runs list) | coalesce 1 s |
| `project.fixtures` | project | `fixtures` | coalesce 1 s |
| `project.capture_url` | project | `captureUrl` | none |
| `workspace.agents` | workspace | `agents` | coalesce 1 s |

### 3.2 Direct events

All carry `{"v": 1, ...}` (broadcast schema version).

**`capture.signature`** — channel `project`
```json
{"v":1,"capture_seq":9,"state":"invalid","reason":"different_secret","checks_passed":6,"checks_total":7}
```

**`pairing.completed`** — channel `workspace`
```json
{"v":1,"agent_id":"agt_…","name":"maya-mbp","version":"0.9.2","os":"darwin",
 "key_fingerprint":"SHA256:ab12…","state":"awaiting_confirmation"}
```
The browser shows "Confirm in your terminal", then, on `agent.status` `connected`, the name, OS and fingerprint with **"Not your machine? Revoke"**.

**`agent.status`** — channel `workspace`
```json
{"v":1,"agent_id":"agt_…","state":"connected","version":"0.9.2","too_old":false,"needs_reconfirm":false,"replaced_agent_id":null}
```

**`run.accepted`** — channel `run`
```json
{"v":1,"run_id":"run_…","seq":1,"agent_id":"agt_…","delivery_count":16,"started_at":"2026-11-14T10:01:02.123Z"}
```

**`run.delivery`** — channel `run` (one per delivery phase change)
```json
{"v":1,"run_id":"run_…","seq":7,"d":3,"scenario":"once","phase":"responded",
 "status":200,"transport":null,"latency_ms":212,"over_budget":false,
 "state_changed":null,"flagged":null,"result":null}
```
- `phase`: `sent` · `responded` · `probed` · `judged` (A-12 wording).
- `state_changed`, `flagged`: booleans once probed; never values (PV-6). Display values load with the row via partial reload.
- `result` set at `judged` (§7.1 values).

**`run.scenario`** — channel `run`
```json
{"v":1,"run_id":"run_…","seq":12,"scenario":"twice","result":"finding","reason":null}
```

**`run.finding`** — channel `run`
```json
{"v":1,"run_id":"run_…","seq":13,"key":"duplicate_changed_state:invoice.paid","type":"duplicate_changed_state",
 "severity":"high","event_type":"invoice.paid","evidence":[{"d":4,"role":"reference"},{"d":5,"role":"offending"}]}
```
- No F-number while running (FND-6 note for A03): the client orders by severity then smallest offending `d`. `key` = merge key (FND-3) so later evidence updates the same entry.

**`run.finished`** — channel `run`
```json
{"v":1,"run_id":"run_…","seq":31,"status":"findings","reason":null,"exit_code":1,
 "counts":{"critical":1,"high":1,"medium":1,"info":1,"findings":3}}
```
Triggers a partial reload of the run page (final numbered findings from the server).

**`job.available`** — channel `agent`
```json
{"v":1,"job_id":"job_…"}
```
Nothing else. The agent pulls the job over HTTPS (ADR-010 §5).

**`agent.revoked`** — channel `agent`
```json
{"v":1,"reason":"replaced"}
```

**`session.expiring`** — channel `session`
```json
{"v":1,"expires_at":"2026-11-15T10:00:00Z"}
```

**`session.ended`** — channel `session` (terminating)
```json
{"v":1,"reason":"claimed"}
```
`reason`: `user` · `expired` · `claimed`. No email or account id in the payload.

### 3.3 Ordering and recovery (run timeline)

- `seq` is assigned by the server when the observation batch is committed; strictly increasing per run.
- Client keeps `last_seq`; on gap or reconnect → partial reload of `timeline` (returns `last_seq` **and `status`**); drop events with `seq ≤ last_seq`.
- **The gap reload is required, not a safety net (C-6).** With more than one `broadcasts` worker, 0.5 to 1.5 % of run events arrived behind a higher `seq` in A08's test, and a broadcast can be lost. Optionally the run-timeline broadcasts get their own single-worker queue to make gaps rare (ADR-007); correctness does not depend on it.
- **A finished run is also detected from a reload (C-5).** `run.finished` can arrive inside a timeline reload; the live event is then dropped as `seq ≤ last_seq` and a client that waits for it never does the final page reload. After every `timeline` reload the client checks the returned `status`: if the timeline contains `run.finished` or `status` is no longer `running`, it does the final page reload (§3.2 `run.finished`) and leaves the run channel. The reducer needs a test for exactly this case.
- **Owner-only props are absent, never lazy, for share viewers (ADR-004 am. 1 §5).** The shared page builds its own prop set (no `share`, `agent`, `pairing`, `targetHost`); `Inertia::optional()` on the shared page would return them to a partial reload (proven in the spike). `timeline` is served by partial reload to both access levels.
- Server-side runs (`executor = server_*`) emit the same events: the remote runner posts observations over `/runner/v1` into the same recording service (ADR-010 §12).

---

## 4. Agent protocol messages (summary)

The agent ↔ server protocol is HTTP, not broadcast, except the `job.available` nudge. Messages are listed in `contracts.md` §5. Event mapping:

| Agent message | Server domain event |
| ------------- | ------------------- |
| `pair` | `AgentPaired` |
| `confirm` | `AgentConfirmed` |
| capture fetch | `CaptureFetchedByExecutor` |
| `hello` / heartbeat | `AgentConnected`, `AgentStateReported` |
| `job.accept` | `RunAccepted` |
| `observations` | `RunObservationsRecorded` |
| `readings.replace` (PV-4) | `RunObservationsRecorded` (re-sent display values; client reloads timeline) |
| `run.final` | `RunFinished` |
| `signature.verdict` | `SignatureVerdictRecorded` |
| `fixtures.report` | `FixturesReported` |

---

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §1: principal-type checks, separate agent auth endpoint, client events disabled, connection cap | A09 §9.9, TM-20, TM-22, TM-24 |
| 2026-11-14 | 2 | §1: terminating events on session end, claim, agent revocation; unshare needs none | §9.9, TM-21 |
| 2026-11-14 | 2 | §3.2 `pairing.completed`: `os`, `key_fingerprint`, confirmation state; `session.ended` reason | §9.9, S-1; PO-E |
| 2026-11-14 | 2 | §2: `AgentPaired` → owner email; `AgentConfirmed`; claim listeners; accepted-run counter; audit events | §9.9, TM-12, TM-42, TM-73, TM-91, §7.3 |
| 2026-11-14 | 2 | Rules: closed schemas and canary across every broadcast | §9.9, TM-23 |
| 2026-11-14 | 2 | Server runs via the remote runner; no Reverb for the runner | HD-5 |
| 2026-11-14 | 3 | §2: authentication, WebAuthn, TOTP, recovery and 1.x import events; none broadcast; no email in payloads | decisions 41, 44 |
| 2026-11-14 | 4 | §2: `IdentityLinked` (email notice + audit) | WA-5, TM-124 |
| 2026-11-14 | 4 | §2: `WebAuthnCounterAnomaly` cause; `SecondFactorFailed`, `SecondFactorHold` | WA-1, WA-2, WA-3, TM-120..TM-122 |
| 2026-11-14 | 4 | §2: recovery events `…Extended`, cancel by second-factor sign-in, completion sets agents `needs_reconfirm`, 7-day lock, audit per step | RC-1..RC-6, TM-125, TM-126 |
| 2026-11-14 | 4 | §2: `LegacyHashDiscarded` removed; activation methods `reset`, `oauth` | PO-H |
| 2026-11-14 | 4 | §2: `AgentUnconfirmedExpired`, revoke reason `unconfirmed`; `RunnerSecretsRefetchBlocked` | RE-1, RN-6, TM-133 |
| 2026-11-14 | 5 | §2: `UserSignedIn` carries the session id; RC-1 cancel includes passkey sign-in | A09 §12.9 RC-1 |
| 2026-11-14 | 5 | §2: `WebAuthnCredentialUnblocked`, `IdentityUnlinked`, `EmailChangeRequested`, `EmailChanged`, `SessionRevoked`; password change revokes other sessions | A03 S-17A/B, S-31 |
| 2026-11-14 | 5 | §2: recovery `cancelled_by` adds `declined`, `link_expired`; completion clears the password and sends the finish link **[PO-J]** | A03 S-29 |
| 2026-11-14 | 6 | §2: `AccountDeleted` (email enqueued in the transaction, not by a listener), `UserInvited`, `PasswordSignInHeld`; `SessionRevoked` reason `account_deleted` and not raised on an idempotent revoke; `EmailVerified` via `password_set_link` | A03 O-SS-9 (a), (b), (e); domain §2.10, §11 |
| 2026-11-14 | 6.1 | §2: `AccountDeleted` listeners: Ingest evicts the key cache (DL-2); ledger append is idempotent and re-done by the purge job before the row is deleted (DL-3a) | A09 §12.11 DL-2, DL-3 |
| 2026-11-14 | 6.2 | §2: `EmailChangeConfirmed` (EC-1); `EmailChangeRequested` sends `email-change-approve` to the old address on accounts without a second factor; `EmailChanged` raised once after both halves | decision 47 PO-O, EC-1 |
| 2026-11-14 | 6.2 | §2: `AccountDeletionRequested` (email `account-deletion-confirm`) and `AccountDeletionCancelled` (DL-4); `AccountDeleted` unchanged | decision 47 PO-N, DL-4 |
| 2026-11-14 | 6.3 | §1 rule 4: `accept_client_events_from = none` set explicitly, config test fails on `members`, `all` and a missing key | decision 50; A08 C-4, TM-T22 |
| 2026-11-14 | 6.3 | §1 rules 5, 6, 7: per-principal cap (guest 10, user 20, agent 2) and max lifetime (1 h, 30 s check) are our code in the Reverb process, errors 4004 and 4200; both auth endpoints sign `channel_data.user_id = <type>:<id>`, two dedicated controllers, no `Broadcast::routes()`; Reverb image needs `ext-uv` or `ext-event` | decision 50; A08 C-1, C-3, C-7 |
| 2026-11-14 | 6.3 | §1 terminating events: `terminate_connections` for the principal after the event in the same job; added for `AccountDeleted` and `AccountRecoveryCompleted`; R-2 narrowed | decision 50; A08 C-2 |
| 2026-11-14 | 6.3 | §3.3: `timeline` returns `status`; finished run detected from a reload; gap reload required; owner-only props absent for share viewers | decision 50; A08 C-5, C-6, TM-14 |
