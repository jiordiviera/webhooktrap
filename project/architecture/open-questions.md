# Open questions and resolutions · Phase 4 architecture

**Status:** Proposed, revision 6.3 (applies decision 50: §8 closed with A08's spike results; G-6 closed); revision 6.2 (applies decision 47: PO-L, PO-M, PO-N, PO-O decided; DL-4 and EC-1 designed); revision 6.1 (applies A09 §12.11: DL-1, DL-2, DL-3, TH-1, SE-1, ED-1; R-AE rejected); revision 6 (decision 46 applied: PO-H, PO-I, PO-J decided, Phase 4 ADRs accepted; A03's O-SS-9 answered; decisions 41, 43, 44; A01 C-7, C-8; A09 re-check rev 3 applied; A09 §12.9 follow-ups and A03's account-security gaps applied) · **Author:** A05 · **Date:** 2026-11-14
**Security review:** A09 `project/security/threat-model.md` §9.10 (ACCEPT WITH CHANGES). Decisions 38 and 39 applied.
**Covers:** A-12, A-14, A-15 and the §4 determinism constraints (A11 testability review, owned by A05); items for A09, A10 and the PO; conflicts found between inputs; risks of starting before Gate 2 (decision 36).

---

## 1. A11 items owned by A05

| Item | Resolution | Where |
| ---- | ---------- | ----- |
| **A-12** Agent ↔ server protocol and run event stream | Protocol v1 specified: pairing, challenge-response auth, hello/heartbeat, jobs (nudge + pull), observations with per-run `seq`, PV-4 label widening, final document, signature verdicts, fixtures report. Versioned (`proto: 1`, `min_agent_version`, `schema_version`). JSON Schemas under `contracts/agent/v1/`, consumer-driven contract tests both sides. Timeline events carry `run_id`, `seq`, `d`, `phase` (sent / responded / probed / judged), booleans, never bodies, headers, values or secrets. | ADR-010 §4–§8; `contracts.md` §5; `event-catalogue.md` §3.2 |
| **A-14** Header fidelity under PHP-FPM | `header_fidelity` stored per capture (`exact` \| `sapi`); MVP is always `sapi`; headers stored as an ordered list as the SAPI reports them; Raw tab note. `exact` deferred (would need a separate ingest runtime). | ADR-005 amendment 1 §2 |
| **A-15** Ingest response invariants | One response for every case (`200 {"received":true}`); only network-level 429 (per address) and proxy 413 above the hard limit differ. Body kept up to **5 242 880 bytes inclusive (5 MiB)**. | ADR-005 amendment 1 §4–§5 |
| **§4 D-1 … D-12** | All adopted. Added D-3a: the probe label salt is separate from the seed and never uploaded (A09 PV-3). Engine constraints from A01 (PLAN-3 ±100 ms, BURST-2 pre-opened connections, TGT-5, PRB-6/7) recorded. | ADR-010 §10 |
| A01 constraint C-4 (`sql:` drivers) | Kept in MVP with pure-Go drivers. | ADR-010 §2 |
| Q3 (agent language) | Go. **PO-approved** (decision 44). | ADR-010 §2 |
| A01 **C-7** (probe-name grammar) | Aligned on PV-9/PRB-2: `^[a-z0-9_.]{1,48}$`. Contracts §5.11 had `[A-Za-z0-9_.:-]`; the stricter grammar wins, enforced by the agent (config refused) and the server (422). | contracts §5.11 |
| A01 **C-8** (server runs) | Confirmed: server runs are **Level 1 only** on hosted and self-host (no probe, no reset); hosted holds no signing secret, so SIGN-2 applies; self-host B′ may sign with instance secrets. Enforced by schema `CHECK` and runner API refusal. | ADR-010 §12.5; schema §6; contracts §6; domain §6 |

---

## 2. Items for A09 — resolved by the threat model

All S-items are resolved by A09 `project/security/threat-model.md` §6, applied in revision 2 of this package.

| # | Item | Resolution (A09 §6) | Applied in |
| - | ---- | ------------------- | ---------- |
| S-1 | Pairing code | 10 Crockford symbols (≈ 50 bits), 10 min, single use, HMAC-stored, per-address and global limits, per-code void at 5 | ADR-010 §4; schema §5; contracts §5.1 |
| S-2 | Pasted-code attack | Agent prints its own server origin, label, `[y/N]`; `--yes` for account workspaces only; remote jobs to declared targets only; approvals need a TTY; one local line per remote run; delayed verify jobs | ADR-010 §4, §6.3; ADR-003 am. §2 |
| S-3 | Agent key | Ed25519, one key per server origin, OS store or `0600` with permission check, unpair = rotation; domain-separated challenge | ADR-010 §4; contracts §5.2 |
| S-4 | Job integrity | Layers 1–4 plus origin pinning, no redirects, schema/size validation. Server-signed jobs **not adopted** | ADR-010 §6 |
| S-5 | Unattested verdicts | Level 1 consistency only; provenance on every view; server-rendered prose; pack-only citations | ADR-010 §3; contracts §5.7 |
| S-6 | Forwarded origin | A05 default accepted; `source_ip` via trusted-proxy chain only | ADR-005 am. §1 |
| S-7 | Header redaction | Three headers kept; placeholder `[redacted · N bytes]`, no hash | ADR-005 am. §3 |
| S-8 | Secret last-4 hint | Never on the server; local terminal only (PO-D) | ADR-010 §11; schema §5; contracts §5.3 |
| S-9 | Hosted server runs | A05 limits plus daily and per-destination limits; egress firewall **required**; accounts only (PO-B) | ADR-006 am. §2; ADR-010 §12 |
| S-10 | B′ access | Per-user permission; exclusive with open sign-up (PO-G) | ADR-006 am. §3; schema §2, §7 |
| S-11 | Engine in the server | No credentials; argv array, stdin/stdout; `plan`/`verify` without network; `serve-run` egress-filtered. Now a remote runner (HD-5) | ADR-010 §1, §12 |
| S-12 | Release signing | A09 §7.7 items 1–8 | ADR-010 §2 |
| S-13 | Share capability | Separate rotatable share token (PO-A, replaces PO-8) | domain §10; schema §6; contracts §3.2 |
| S-14 | Guest cookie and CSRF | `__Host-` prefix, no replacement of a live session, session id regenerated at claim, claim binding | ADR-003 am. §4–§5; contracts §3.2 |
| S-15 | C-3 implementation | Confirmed by A09; PV-1..PV-14 adopted (decision 38) | ADR-010 §11 |

### 2.1 For A09's re-check (new or changed design not in the reviewed drafts)

| # | Item | Where |
| - | ---- | ----- |
| R-A | **Server runner as a remote process (HD-5)**: runner token (`wt_rnr_`, host-issued, optional source CIDR), `/runner/v1` with its own guard, long-poll only, job-scoped capture fetch, version lock | ADR-010 §12; contracts §6; schema `runners` |
| R-B | **Instance secrets reach the remote runner per job** (`GET /runner/v1/jobs/{id}/secrets`, self-host B′ only, audited). Alternative considered: secrets configured on the runner container's environment by the operator (no API path, but no per-user restriction or audit). I chose the API path because TM-73 needs per-user restriction and per-use audit | ADR-010 §12.4; ADR-006 am. §3 |
| R-C | **Provenance on share views includes the agent or CI token name** (TM-17 "every run view") — previously hidden by scrub rule 5. Names are user-chosen labels, not secrets | schema §6 rules 5–6; contracts §3.3 |
| R-D | **Share URL shape** `/s/{shareToken}`; owner URL `/r/{runId}` stays canonical and requires auth. Needs A03 to update IA routes (G-3) | contracts §3.2 |
| R-E | **Pairing confirmation sequence**: agent row exists from `pair` with `needs_reconfirm=true`, guest replacement only at `/confirm` | ADR-010 §4; contracts §5.1, §5.3 |
| R-F | **Hosted run document has no prose fields**; findings carry `params` + pack version; text and citations rendered by the server | ADR-010 §3; schema `findings`; contracts §5.7 |
| R-G | Signed challenge byte encoding (length-prefixed fields) | contracts §5.2 |
| R-H | Free-string grammars (probe name narrowed to PV-9, C-7; credential name added) | contracts §5.11 |
| R-I | **WebAuthn relying party**: RP ID = app host exactly (not the registrable domain), from config; origin from `APP_URL`; capture host never a ceremony origin; `Permissions-Policy` denies ceremonies on `/s/…` share pages and raw downloads; JSON ceremony endpoints need session, CSRF and `Sec-Fetch-Site: same-origin` | domain §2.2; contracts §3.4 |
| R-J | **Ceremony policy**: passkey = discoverable + UV required; security key = UV discouraged, second factor only; attestation `none` (AAGUID display only); counter regression refused and the key flagged; BE change refused; synced passkeys allowed; challenge in session, single use, 5 min | domain §2.2; schema `webauthn_credentials` |
| R-K | **WebAuthn × TOTP × recovery codes**: 2FA = any second factor; challenge after every password and OAuth sign-in (OAuth does not bypass 2FA); passkey sign-in skips it; recovery codes created with the first second factor; last-first-factor rule; recent authentication (10 min) for factor changes | domain §2.1, §2.3 |
| R-L | **Account recovery when all factors are lost**: self-host host console; hosted delayed email recovery (72 h, cancel banner, second mail at 24 h, revokes sessions and personal tokens, keeps agents and CI tokens). Needs A09's view on the email-account-takeover residual (PO-J) | domain §2.3; contracts §3.4 |
| R-M | **1.x import** (decision 41, A09 to advise): option A verify-then-rehash vs **option B reset (recommended)**; pre-hijack rule (credentials set before email proof are discarded at first proof; OAuth links only on a provider-verified email); legacy hash deadline launch + 30 days under A | domain §2.4; schema `users`; event-catalogue §2 |
| R-N | **Claim with the new methods**: claim only after the 2FA challenge, in the same browser session; reset and verification links never claim; a guest who registers claims only after verifying the email in the same browser | ADR-003 am. §4 |
| R-O | **FrankenPHP**: `400` from Go for unparsable targets accepted as network-level (uniform across keys); own proxy headers removed from stored captures; plain HTTP on `in.` with scheme from trusted `X-Forwarded-Proto`; worker-mode static-state rule | ADR-005 am. §9 |
| R-P | **Server runs Level 1 only** (C-8): any residual where a runner could be steered into Level 2 or probe execution | ADR-010 §12.5 |

### 2.2 A09 outcomes for R-A to R-P (re-check rev 3, §12.3; applied in revision 4)

| # | Verdict | Required change and where applied |
| - | ------- | --------------------------------- |
| R-A Runner as remote process | Accept with changes | RN-1 source binding required on hosted, RN-2 rotation, RN-3 local mode ceiling and hosted refuses `allowlist`, RN-4 never the runner-API host or own networks, RN-5 capture fetch while accepted and not final, RN-6 secrets endpoint conditions, RN-7: ADR-010 §12.1, §12.4, §12.5; schema `runners`; contracts §6 |
| R-B Secrets via runner API | Accept (path); tightened by RN-6 | ADR-010 §12.4; contracts §6 |
| R-C Provenance names on share views | Accept; the share dialog previews the exact provenance line before sharing (as for "Shared by") | (UI copy, A03) |
| R-D `/s/{shareToken}` | Accept with RD-1: share tokens kept out of access logs | contracts §3.2 (app side done; proxy masking is A10's, `contabo-deployment-plan` §4.5) |
| R-E Pairing sequence | Accept with RE-1: unconfirmed agents revoked after 10 min; no mirrors or counts before first confirmation | ADR-010 §4; schema `agents`; contracts §5.3; event `AgentUnconfirmedExpired` |
| R-F No prose in hosted profile | Accept | — |
| R-G Signed challenge encoding | Accept; `expires_at` is signed as the exact string the server issued | contracts §5.2 (already so: the signed field is the server's string) |
| R-H Free-string grammars | Accept; credential names also strip bidi and tag characters | contracts §5.11 |
| R-I Relying party | Accept with WA-4 (`frame-ancestors 'none'`) | contracts §3.4; domain §2.2 |
| R-J Ceremony policy | Accept with WA-1, WA-2 (counter rule per spec, user handle match). UV required for passkey sign-in, UP for second factor, attestation `none`, BE fixed, synced passkeys allowed: agreed | domain §2.2; schema; contracts §3.4 |
| R-K WebAuthn × TOTP × recovery codes | Accept with WA-3 (per-account failure counter), WA-5 (`IdentityLinked`); recovery codes carry ≥ 50 bits each | domain §2.1; schema `second_factor_failures`; event-catalogue |
| R-L Account recovery | Accept with conditions (PO-J, RC-1..RC-6) | domain §2.3; schema; event-catalogue |
| R-M 1.x import | Option B (PO-H) | domain §2.4; schema `users` |
| R-N Claim with new methods | Accept (nonce consumed after the 2FA challenge in the same browser; reset and verification links never claim) | — |
| R-O FrankenPHP | Accept with SV-2 (A10: the host proxy proves itself to FrankenPHP) and the HSTS rule: `in.` without HSTS is fine only if `app.` or the apex sends no `includeSubDomains`/`preload`. `400` for unparsable targets accepted as network-level; worker-mode static-state rule agreed | ADR-005 am. 1 §9 (note); A10 |
| R-P Level 1 only | Accept with RN-7 (`serve-run` never reads probe/reset config); schema `CHECK`, closed runner schemas and `422 level2_not_allowed` close the steering path | ADR-010 §12.5 |

### 2.3 A09 rev 4 follow-ups (§12.9), applied in revision 5

| Item | Applied in |
| ---- | ---------- |
| RN-6: column making "one successful fetch" atomic | schema `run_jobs.secrets_fetched_at` + conditional update; ADR-010 §12.4 item 4; contracts §6 |
| RC-1: a passkey sign-in counts as completing a second factor | domain §2.3 RC-1; event-catalogue `UserSignedIn`, `AccountRecovery…Cancelled` |
| PO-H option label still said "passkey activation" | §4 PO-H row below |
| ENG-1 alignment: ADR-010 §1 described the engine inside the app container | ADR-010 §1 rules 1–7 (separate `engine` service, ENG-2), open item 5, Consequences; ADR-006 am. §3; schema `instance_secrets`; domain §1 |

### 2.4 For A09's re-check (new in revision 5)

| # | Item | Where |
| - | ---- | ----- |
| R-Q | **Key unblock**: owner may clear a flag after recent authentication with another method; sign count kept; refused after a second flag | domain §2.2; contracts §3.4 |
| R-R | **Connect/disconnect GitHub/Google** from settings: provider email need not match or be verified (the OAuth round trip proves the provider account); `identity_in_use` names the conflict to the person who just proved control; connecting is **not** blocked by the RC-3 lock (only removal is) | domain §2.5 |
| R-S | **Email change**: uniform answer, verification at the new address, notices to the old (no revert link), confirm only in a signed-in session of the same account, RC-3 refusal, second-factor recent authentication on 2FA accounts, reset tokens dropped and other sessions revoked | domain §2.6; schema `email_change_requests` |
| R-T | **OAuth re-authentication** for OAuth-only accounts (same subject; Google `prompt=login`; GitHub cannot force a login, so it proves only a live GitHub session) | domain §2.1; contracts `/confirm-auth/{provider}` |
| R-U | **Emailed links**: page on `GET`, change on `POST`, `GET` never consumes the token; lifetimes 60 min (verify, email change, reset, recovery request) and 24 h (recovery finish, self-host password-set) | domain §2.9; contracts §3.4 |
| R-V | **RC-5 reading**: only cancellations after confirmation start the 30-day cool-down; `declined`/`link_expired` do not (otherwise anyone knowing an email blocks its owner's recovery). **Completion clears the password** and sends a 24 h finish link. `/confirm-auth` passed with a factor also cancels an open recovery (RC-1) | domain §2.3; schema `account_recovery_requests` |
| R-W | **Password policy**: 12–128, bundled common-password list, no online breach check in the MVP; bcrypt fallback limited to 72 bytes; password change revokes other sessions | domain §2.1 |
| R-X | **Sign-in sessions** (`user_sessions`): truncated address (/24, /48) and UA label stored for the session list, security log and emails; revocation checked per request (cache ≤ 30 s); revoking other sessions needs recent authentication; 14 days idle / 30 days absolute (proposals) | domain §2.7; schema `user_sessions`, `audit_log` |

### 2.5 A09 rev 5 outcomes (§12.10), applied in revision 5.1

| # | Outcome | Where |
| - | ------- | ----- |
| AR-1 | **Applied.** An identity linked after the recovery was requested is not covered by the RC-3 removal lock (removable with recent authentication); its `IdentityLinked` notice says a recovery is open or recent | domain §2.3 RC-3, §2.5; contracts §3.4; schema `account_recovery_requests` |
| AR-2 | **Applied.** A password reset on a 2FA account still goes through the 2FA challenge; `reset_link` is never a second factor or recent authentication (TM-T151) | domain §2.1; contracts §3.4 reset row |
| R-10 | **Accepted residual, narrowed by EC-1 (revision 6.2).** On an account without a second factor, a change now needs the **old address's** confirming button as well (PO-O, decision 47), so the password and a session are not enough; what remains is an attacker who also holds the old mailbox, or an owner who lost the old mailbox and never enrolled a second factor. No revert link (R-AE rejected); 2FA accounts need a second factor | domain §2.6 |
| R-11 | **Accepted residual.** OAuth-only re-authentication through GitHub proves only a live GitHub session in that browser (GitHub cannot force a login); offered only to accounts with no other method | domain §2.1 |

### 2.6 For A09's re-check (new in revision 6, A03 O-SS-9)

| # | Item | Where |
| - | ---- | ----- |
| R-Y | **Account deletion**: `DELETE /account` after recent authentication, **second factor required on 2FA accounts**; **immediate** (tombstone in one transaction, purge ≤ 1 h), no grace period (PO-L); refusals `recovery_lock` (RC-3 extended to deletion, including the 7-day lock after completion), `last_instance_admin`, `second_factor_required`; **active runs aborted, not a refusal**; audit rows kept 1 year with the id as text, addresses masked and an `email_hmac` on `account.deleted`; `account-deleted` email enqueued in the transaction. **Residual R-12, accepted by the PO (PO-N, decision 47) and narrowed by DL-4 (revision 6.2):** on an account without a second factor the deletion also needs the account address's confirming link (`/account-deletion/{token}`, 1 h), so the password and a session alone do not delete; the residual is whoever holds both the password and the mailbox | domain §2.10; contracts §3.4; schema `users.deleted_at`, `audit_log` |
| R-Z | **Backups and deletion**: deleted data stays only in encrypted backups (≤ 7 days PITR WAL, ≤ 14 days off-server); a deletion ledger outside the DB (id and time only) is re-applied after any restore | domain §2.10; schema §8 |
| R-AA | **Throttle values**: pair 5 / 15 min; account 20 / h → password-only hold 15 min (passkey, OAuth and reset still work); address (/64) 30 failures / 15 min + 100 requests / h; `/confirm-auth` 10 / 15 min; per-email keys counted for unknown addresses; `sign_in_held` in the security log, no email | domain §2.1 "Throttle values" |
| R-AB | **argon2id only** (64 MiB, t = 4, p = 1; boot refuses without it); bcrypt fallback and the 72-byte limit withdrawn | domain §2.1 "Password hashing" |
| R-AC | **Idempotent session revoke**: unknown, foreign and ended ids get the same success-shaped answer, no audit; current session `422 current_session` | domain §2.7; contracts §3.4 |
| R-AD | **Email-change link page** states (`confirm`, `sign_in_required` without any address, `other_account` showing only the viewer's own address, `invalid` checked first); link kept as the session's intended URL, never in `next`; `currentPassword` dropped (recent authentication is the proof) | domain §2.6; contracts §3.4 |
| R-AE | **R-10 recourse** (revision 6.1: the revert-link proposal is **rejected** per A09 §12.11 and by the PO; revision 6.2: EC-1 is **adopted**, PO-O) (what the old address's owner can do): passkey or GitHub/Google still sign in; password-only owners have lost the account (R-10 as accepted); self-host console `set-email` restore (AR-2 applies to its password-set link; revision 6.1, SE-1: it also removes factors and identities added after the disputed change). **Revert link rejected (A09 §12.11):** a "This wasn't me" link would make the old mailbox an email-only undo for 7 days, so a former holder of that mailbox could take the account through the forced reset. Not adopted. **EC-1 (PO-O, decided):** both addresses confirm an email change on accounts without a second factor (`/email-change-old/{token}`, 60 min) | domain §2.6 |
| R-AF | **Invites (self-host)**: admin or host console creates an unverified user and emails `/set-password/{token}` (24 h, single use); uniform answer for existing addresses; `/register` has no invite token; the password-set link signs in only when the account has no second factor (AR-2) | domain §11, §2.9; contracts §3.4; schema `password_set_tokens` |
| R-AG | **Security-log codes and params** (exact allow-list, display params only, never `before`/`after`) | schema §7 |

### 2.7 A09 §12.11 changes applied (revision 6.1)

| # | Change | Where |
| - | ------ | ----- |
| DL-1 | **Applied.** `DELETE /account` accepts only a confirmation made through S-28 for `action=delete_account` in this session within 5 min, consumed by the `DELETE`; `422 confirmation_stale` | domain §2.1, §2.10; contracts §3.4; schema (session) |
| DL-2 | **Applied.** While tombstoned, reset, verification resend, recovery, OAuth (subject and verified email) and passkey sign-in are uniform no-ops; ingest's capture-key cache is evicted at once by the tombstone (and holds liveness a few seconds at most) | domain §2.10, §4 ingest; event-catalogue `AccountDeleted` |
| DL-3 (app side) | **Applied.** The purge job does not delete the `users` row until the deletion-ledger entry is durably written (idempotent, keyed on `usr_` id); the ledger is re-applied before web, workers and queues start. **A10:** off-server copy and retention of the ledger (≥ 14 days plus margin), startup ordering in the restore runbook | domain §2.10; schema §8; H-18 |
| TH-1 | **Applied.** No password verification during a pair or account hold; `/confirm-auth` failures count toward the account limits (password to 20 / h, second factors to WA-3); unknown, password-less and wrong-password cases take the same argon2id time | domain §2.1; contracts §3.4 |
| SE-1 | **Applied.** The self-host `set-email` restore removes factors and identities added after the disputed `email_change_requested` row (`--keep-factors` opts out); review page like S-30 | domain §2.6 |
| ED-1 | **Applied.** The "72 bytes / bcrypt" sentence is removed from contracts §3.4 "Password policy" | contracts §3.4 |
| R-AE | **Rejected** per A09 §12.11 (revert link); see R-AE above | domain §2.6 |
| DL-4 | **Applied (revision 6.2, PO-N).** On accounts without 2FA, `DELETE /account` sends `account-deletion-confirm` (`/account-deletion/{token}`, 1 h, single use, `GET` page / `POST` effect, signed-in same account); the deletion runs on the `POST`; 2FA accounts keep the immediate path with the 5-min second-factor confirmation (DL-1) | domain §2.10; contracts §3.4; schema `account_deletion_requests`; event-catalogue |
| EC-1 | **Applied (revision 6.2, PO-O).** On accounts without 2FA the old address also confirms an email change (`email-change-approve`, `/email-change-old/{token}`, 60 min with the same `expires_at`); either half first; revert link stays rejected | domain §2.6; contracts §3.4; schema `email_change_requests`; event-catalogue |

**A10 items (not A05's documents):** SV-1..SV-6 (shared VPS), CI-1..CI-4, ENG-1 (network-free sandbox for `plan`/`verify`) are A09's changes to `contabo-deployment-plan.md`, `ci-cd.md` and `compose-profile.md`; they gate go-live, not ADR acceptance. ENG-1 must be settled before instance-secret `verify` ships on self-host. Residual risks R-7 (recovery), R-8 (if SV-1 is refused) and R-9 (shared VPS neighbours) are recorded in A09 §12.6.

---

## 3. Items for A10 (hosting and operations)

A10's `operations/hosting-options.md` answered H-1 to H-9; the PO decided HD-1 to HD-10 (decision 39). What this package now assumes:

| # | Item | Architecture assumption |
| - | ---- | ----------------------- |
| H-1 | Hosting (HD-1, changed by the PO) | App, ingest, Reverb, workers, Postgres and Redis/Valkey on the **existing Contabo VPS shared with 1.x**. Nobody touches the VPS without separate PO authorisation; A10 plans coexistence on paper first |
| H-2 | Redis-compatible server (HD-8) | **Valkey or Redis**; the architecture test accepts either; private network only, no published port, password/ACL required (TM-80) |
| H-3 | Reverb | Client events disabled; per-principal connection cap; connection max lifetime; separate agent auth endpoint. Capacity measured by A08 |
| H-4 | Docker Compose | One profile; services as A10 §3.1, with `runner` pulling over `/runner/v1` and no route to Postgres, Redis or Reverb |
| H-5 | Engine packaging | In the app image; run as the separate `engine` service (`network_mode: none`, no credentials, Unix socket, `serve-sandbox`; ADR-010 §1, ENG-1/ENG-2) for `plan` and `verify`; `webhooktrap/runner` image from the same release pipeline; version lock enforced by protocol. Engine and emailed-link pages: mask link tokens in proxy logs like `/s/…` (contracts §3.4) |
| H-6 | Proxy | Raw request target preserved; trusted proxies; 25 MiB → uniform 413; edge limiter keyed by client address, never capture key; **separate capture host** (HD-7) |
| H-10 | **Runner VM (HD-5)** | Own small VM, own egress IP, inbound closed, **required** egress firewall (private, link-local, CGNAT, ULA, metadata, the app host's private addresses); reaches the app over public HTTPS only. Not on the shared VPS (decision 39) |
| H-11 | Backups (HD-4) | 7-day PITR, 14-day encrypted off-server copies, disclosed in the privacy notice |
| H-12 | Secrets the app needs | `PAIRING_CODE_KEY` (HMAC), app encryption key for instance secrets, runner token on the runner host only |
| H-13 | Error tracking (HD-9) | Body capture off; scrubbing for `Authorization`, cookies, `/c/` bodies, pairing body, token endpoints (TM-82) |
| H-7, H-8, H-9 | Reference env, Postgres version, agent distribution | Unchanged; distribution per ADR-010 §2 |
| H-14 | **WebAuthn configuration** (decision 44) | `WEBAUTHN_RP_ID` = `app.<domain>` fixed for the life of the service (changing it orphans every credential); `APP_URL` exact; self-host docs: WebAuthn needs HTTPS and a domain name |
| H-15 | **FrankenPHP template** (decision 43) | ADR-005 am. §9: conformance corpus is the go-live test; `CAPTURE_PROXY_HEADERS` lists what the host proxy and FrankenPHP add; no HSTS on `in.`; worker mode only after A06's worker-mode suites pass |
| H-17 | **Emailed links with tokens (revision 6, complete list for log scrubbing)** | `/verify-email/{id}/{hash}` (+ `expires`, `signature` query), `/email-change/{token}`, **`/email-change-old/{token}` (new, revision 6.2, EC-1)**, **`/account-deletion/{token}` (new, revision 6.2, DL-4)**, `/reset-password/{token}` (no `email` query), `/account-recovery/{token}`, `/account-recovery/finish/{token}`, `/set-password/{token}`. **No other emailed invite or sign-in link exists in the MVP.** Plus the non-emailed `/s/{shareToken}`. Mask in host and in-image proxy logs, app logs and error tracking (contracts §3.4). **A10, revision 6.2:** add both new path prefixes to the scrubbing rules and their tests; the prefixes are distinct (`/email-change-old/`, `/account-deletion/`), so a rule that masks only the last segment of `/email-change/` does not cover them |
| H-18 | **Account deletion and backups** (revision 6) | Deletion ledger (`usr_` id, time; no address) stored outside the database next to the backup repository; the restore runbook re-applies purges later than the restore point before traffic; privacy notice states purge ≤ 1 h and the 14-day backup tail; failed mail jobs purged after 7 days; alert if a purge is not done 1 h after deletion (domain §2.10). **Revision 6.1 (DL-3):** the purge job writes the ledger entry before deleting the row; **A10:** copy the ledger off-server, keep it at least as long as the oldest backup (14 days plus margin), re-apply it before app, workers and queues start |
| H-19 | **argon2id in the image** (revision 6) | PHP built with argon2 (sodium or libargon2); A08 measures p95 hash time on the shared VPS (target ≤ 300 ms at 64 MiB); memory headroom for concurrent sign-ins (bounded by the throttles) |
| H-16 | **1.x import run** (decision 41) | host console command, file path given at run time from outside the repo and image, before public sign-up opens; export deleted after the PO confirms. Transactional email provider needed for reset and verification links before launch |

---

## 4. PO decisions (decision 39) and values still to confirm

| # | Question | Outcome |
| - | -------- | ------- |
| PO-1 | Accept ADR-010 | **Approved with A09's changes** (Level 1 only re-checkable, provenance on every run). Applied |
| PO-2 | Accept ADR-003/004/005/006 amendment-1 drafts | **Approved with A09's changes**; ADR-004 still subject to A08's spike. Applied |
| PO-3 | Phase 0 amendments (ADR-006 private targets flag, ADR-007 Redis optional, ADR-008 minimal profile) | **Rejected.** Listed in `decisions/README-phase4-notes.md`; A00 updates the index and accepted files |
| PO-4 | Account retention captures 30 days, runs 90 days | **Approved** for the MVP |
| PO-5 | PV-1..PV-14 replace PRB-3 | **Settled** (decision 38) |
| PO-6 | Hosted server runs only for providers without a signed timestamp (GitHub, Shopify) | **Approved** |
| PO-7 | `exact` header fidelity out of the MVP | **Approved** |
| PO-8 | Share capability = run id | **Superseded by PO-A** |
| PO-9 | ADR-001 amendment pointing to `domain-model.md` §1 | **Approved**; A00 writes it |
| PO-A | Separate rotatable share token | **Approved.** Applied |
| PO-B | Hosted server runs for accounts only | **Approved.** Applied |
| PO-C | CI uploads private unless `--share` | **Approved.** Applied |
| PO-D | No secret last 4 on the server; toast becomes "STRIPE_WEBHOOK_SECRET on maya-mbp" | **Approved.** Applied; A03 updates FL-2 and SIG-3 copy |
| PO-E | `[y/N]` at pairing; local reconfirmation after claim | **Approved.** Applied; A03 updates FL-2 and FL-7 |
| PO-F | 10-symbol pairing codes | **Approved.** Applied; A03 updates visuals (`HXR-4K7` → `7KQ2M-XR9TD`) |
| PO-G | B′ per-user permission; impossible with open sign-up | **Approved.** Applied |
| HD-5 | Runner as a remote agent-like process on its own VM | **Approved in principle**; designed in ADR-010 §12; A09 re-check (R-A, R-B) |
| HD-7 | Separate capture host | Applied (ADR-005 am. §8) |
| HD-8 | Valkey accepted | Applied (domain §1.2 rule 3) |
| HD-10 | Server runs behind a kill switch | Applied (ADR-010 §12.3) |
| Q3 | Agent language | **Go, approved** (decision 44). Applied |
| **PO-H** | 1.x passwords: (A) verify the Adonis scrypt hash once and rehash, or (B) forced activation | **Decided: option B (decision 46).** The import never reads the hash fields; each user resets the password or signs in with GitHub or Google whose provider marks the email verified; a passkey works only once the email is proven (domain §2.4; see K-23). Export deleted after the PO confirms the import; launch notice text still needs the PO's approval (A03 S-31 `launch-1x`) |
| **PO-I** | Email-link (magic link) sign-in | **Decided: no magic link (decision 46).** Already absent from schema and contracts; A03 removes any remaining reference |
| **PO-J** | Hosted recovery when every factor and recovery code is lost | **Decided: delayed email recovery, 72 h, with A09's conditions RC-1..RC-6 (decision 46); residual R-7 accepted.** Applied in domain §2.3, schema `account_recovery_requests`, contracts §3.4, event-catalogue §2; the `[PO-J]` markers are removed |
| **PO-L** | **Account deletion: immediate, or with a grace period?** (revision 6) | **Decided: immediate (decision 47).** Purge ≤ 1 h, backups ≤ 14 days, behind a fresh `delete_account` confirmation (5 min, single use, DL-1; a second factor on 2FA accounts, plus the address link on accounts without one, PO-N). No grace period; the `pending_deletion` alternative is dropped. Residual R-12 accepted. Applied in domain §2.10 |
| **PO-M** | **Hosted support for someone who lost the account to an email change (R-10) or a deletion (R-12)** (revision 6) | **Decided (decision 47).** Hosted support never restores a deleted account and never restores an address. It confirms a deletion only by writing to the account address (using `email_hmac` internally, never answering a third party). Operator deletion happens only on verified legal orders. The support address stays in the `email-changed` and `account-deleted` emails. An operator restore path would need A09 to model it first, and is not offered |
| **PO-N** | **DL-4: email-confirmed deletion on accounts without a second factor** (revision 6.1, A09 §12.11 recommended) | **Decided: R-12 accepted, confirmation by link (decision 47).** On an account without 2FA, `DELETE /account` sends `account-deletion-confirm` to the account address (`/account-deletion/{token}`, **1 h**, single use, `GET` page / `POST` effect, signed-in same account) and deletes on that button. Route, event (`AccountDeletionRequested`), template and state set: domain §2.10, contracts §3.4, schema `account_deletion_requests`, event-catalogue. The 5-min fresh confirmation (DL-1) still applies to every account. New link path in H-17 for A10; S-17 `delete` step for A03 (U-27) |
| **PO-O** | **EC-1: old address also confirms an email change on accounts without a second factor** (revision 6.1, A09 §12.11 recommended) | **Decided: yes; the R-AE revert link stays rejected (decision 47).** On an account without 2FA, an email change needs a button press from `/email-change-old/{token}` (**60 min**, same `expires_at` as the new-address link) as well as the new-address link, either order. Applied in domain §2.6, contracts §3.4, schema `email_change_requests`, event-catalogue. New link path in H-17 for A10; A03 screens in U-28 |
| PO-K | Attestation `none` for the MVP (no authenticator allow-list) | A09 accepts (§12.4); no decision needed unless the PO wants enterprise key policies |

**Proposed numeric values** (the threat model names the controls; values are A05 proposals for A09/A10 to confirm at implementation, kept in configuration):

| Limit | Proposed value |
| ----- | -------------- |
| Account captures per project per day | 10 000 |
| Account stored body bytes per project | 1 GiB |
| Global guest storage budget | 5 GiB |
| Guest accepted-run ceiling | 20 per session |
| Server runs: concurrent per principal | 2 |
| Server runs: per principal per day | 20 |
| Server runs: deliveries per destination host per minute, all principals | 60 |
| Active agents per account workspace | 20 |
| Plan previews per principal | 30 per minute |
| Run document size | 2 MiB |

---

## 5. Conflicts found between inputs, and what I followed

| # | Conflict | Followed | Why |
| - | -------- | -------- | --- |
| K-1 | IA §3 and §9.3 show guest **3 runs**; decision 35 (A-13) approved **10 runs** | Decision 35 | PO decision is later and binding; A03 to update the meter copy |
| K-2 | IA object model says probe "digest value stays local" / server stores "state changed: yes/no"; decision 35 C-3 allows numeric values; A09's review narrows it to counts −9999..9999 + labels | A09 PV-1..14 (coordinator instruction) | C-3 was approved subject to A09's review |
| K-3 | A01 PRB-3 (up to 12 digits; `show: true`) vs A09 PV-2/PV-5 (4 digits; `show: number`/`show: value`) | A09 | Same as K-2 |
| K-4 | IA and RD use Run #3 with **14** deliveries; C-4 approved **16** | C-4 | `/r/example` fixture follows C-4 |
| K-5 | IA, TT and the prototype use `trap`, `trap.yml`, `X-Trap-*`; C-1 approved `webhooktrap` | C-1 | NAME-1..3 |
| K-6 | ADR-006 Phase 0 amendment (flag for all private ranges, `APP_ENV` check) vs decision 32 B′ (allow-list, never guests, off by default) | Decision 32 | Written as 0006-amendment-1, superseding the Phase 0 draft |
| K-7 | ADR-007/008 Phase 0 amendments (Redis optional, minimal profile) vs decision 32 §4 (Redis required) | Decision 32 | PO-3 |
| K-8 | ADR-004 "private endpoint-scoped channels" vs 2.0 project-scoped channel (P9, rotation) | Project-scoped | Rotation must not change the channel; ADR-004 amendment 1 §4 |
| K-9 | Prototype disables keep-alive and dials per request; BURST-2 requires connections opened before the barrier | BURST-2 | Spec bound; noted in ADR-010 §10 |
| K-10 | Prototype probe timeout 15 s, `--allow-host` accepts any host; spec PRB-1 (5 s default, 30 s max) and TGT-2 (exact `host:port`) | Spec | Prototype is evidence, not spec |
| K-11 | Prototype finding text embeds raw probe values (`eval.go`); PV-6 forbids it | PV-6 | Noted in ADR-010 §11 |
| K-12 | SIG-3 shows the secret's last 4 characters; R12 and concept-impact §4 say no secret material reaches the server | Never uploaded | **Resolved** by A09 S-8 and PO-D |
| K-13 | A11 R3 / A-15 wording "5 MB" vs A-15 "MB vs MiB must be decided" | 5 MiB inclusive | A05 owns A-15 |
| K-14 | A11's A-13 proposal ("counts when the agent accepts it") vs decision 35 ("counts once past Once") | Decision 35 | |
| K-15 | 1.x CLAUDE.md rule "sanitize authorization and cookie headers" vs ADR-005's raw fidelity | Redact three credential headers (S-7) | Confirmed by A09 S-7 (placeholder without hash) |
| K-16 | A03 visuals show `HXR-4K7` (6 symbols); PO-F approved 10 | PO-F | A03 updates visuals |
| K-17 | A03 FL-2 toast and SIG-3 show the last 4; PO-D removes them | PO-D | A03 updates copy |
| K-18 | ADR-006 am. 1 rev. 1 ran the engine in a `replay` queue worker; HD-5 moves it to a remote runner | HD-5 | ADR-007's `replay` queue keeps expiry and metrics only; A00 may note it in ADR-007 |
| K-20 | IA `/sign-in` lists GitHub, Google, email link; decision 44 lists email + password, GitHub, Google, 2FA, passkeys, security keys | Decision 44 | Email link dropped (PO-I, decided in decision 46); A03 updates sign-in and adds the security screens |
| K-21 | PV-9/PRB-2 probe name `[a-z0-9_.]` vs contracts §5.11 `[A-Za-z0-9_.:-]` (A01 C-7) | PV-9 | Stricter grammar; contracts aligned |
| K-22 | A01 RUN-11 reads server runs as Level 1 only; ADR-010 §12 did not say (A01 C-8) | RUN-11 | Confirmed in ADR-010 §12.5 |
| K-23 | Decision 46 says the 1.x users may sign in "with GitHub, Google or a passkey"; A09's R-M and domain §2.4 say a passkey is not a standalone activation method | Domain §2.4 | An imported account has no passkey until its email is proven, so a passkey sign-in is possible only after activation; no behaviour changes. A00 may clarify the decision wording |
| K-24 | A03 S-21 offers **Make admin** in the web admin and S-17 `delete` points the last admin to it; TM-19 and domain §11 grant instance admin **only from the host** | TM-19 | A03 replaces the action and the S-17 copy with the host console command (U-20) |
| K-25 | A03 S-24 shows the sign-up form "with an invite token in the URL"; revision 6 has no invite token on `/register` | Domain §11 | Invited people use `/set-password/{token}` (U-27) |
| K-19 | A10's hosting doc targets a new Hetzner VPS; decision 39 changed HD-1 to the shared Contabo VPS | Decision 39 | Architecture is host-agnostic except the runner VM, which stays separate |

---

## 6. Risks of starting before Gate 2 (decision 36, accepted by the PO)

| # | Risk | Likelihood | Impact | Mitigation |
| - | ---- | ---------- | ------ | ---------- |
| G-1 | Run screen fields change (inspector, compare, strip cell contents); `RunDeliveryRow` / `FindingView` props need rework | High | Low–Medium: prop shapes are derived from A-24, which is stable | Props marked **[G2]**; DTOs generated from one source, so a rename is mechanical |
| G-2 | Run draft flow changes (target picker, confirmations, "Confirm in your terminal" state) | Medium | Medium: touches `PlanPreview`, `RunTarget`, job decline reasons | Keep decline reasons as an open enum in v1 |
| G-3 | A03 changes routes (P1 `/p/` vs `/c/`, `/r/{runId}` canonical) | Low (P1–P11 approved) | High (URLs printed by CLI/PR) | Routes follow approved P-decisions only |
| G-4 | Copy and states still pending A01/A03 (Couldn't start variants, Aborted reasons) | Medium | Low: status/reason are codes in the schema; copy lives in the string table | Codes, not strings, in DB and protocol |
| G-5 | Guest meter or quota copy changes again | Low | Low | Values come from config, not code |
| G-6 | ~~A08's spike invalidates direct Echo events~~ Did not happen: confirmed with changes (decision 50), applied in revision 6.3 | — | — | — |
| G-7 | Proxy validation (decision 31) falls back to concept A | Unknown | High for Proving/Agent modules; low for Capture | Build order per decision 31: Identity, Workspace, Capture first; Agent and Proving after the go/no-go |
| G-8 | ~~A09's threat model changes pairing or job integrity~~ Happened; absorbed in revision 2 | — | — | — |
| G-9 | Share URLs move to `/s/{shareToken}` (PO-A); A03's IA lists `/r/{runId}` as the shared URL | High | Low now, high after launch | Change before any URL is printed; A03 updates IA |
| G-10 | Extra terminal steps (PO-E) change FL-2/FL-7 screens | High | Low | Agent states `awaiting_confirmation` and `needs_reconfirm` in props already |

---

## 7. Items for A03 (screens, decision 44)

| # | Screen or copy | Source |
| - | -------------- | ------ |
| U-1 | `/sign-in`: email + password, GitHub, Google, "Sign in with a passkey" (plus autofill); no email link (PO-I); uniform error copy | contracts §3.4 |
| U-2 | `/register`, verify-email, forgot and reset password | contracts §3.4 |
| U-3 | 2FA challenge: security key first when registered, TOTP, recovery code, "Lost access?" (recovery) | domain §2.1, §2.3 |
| U-4 | `/account/security`: password, linked accounts, passkeys and security keys list (name, synced or device-only, last used, flagged), add passkey, add security key, rename, remove; TOTP; recovery codes shown once; recent-authentication prompt | contracts §3.4 `SecurityView` |
| U-5 | Recovery: request, check-email, 72 h pending banner with "Cancel recovery" on every signed-in page | domain §2.3 |
| U-6 | Self-host on HTTP or an IP address: passkey and security-key options hidden with an explanation; `/admin/overview` shows the RP ID with its warning | contracts §3.4 |
| U-7 | 1.x launch notice and "set a password" landing for imported accounts (option B) | domain §2.4; PO-H |
| U-8 | Draft preview for a server target: "Level 1 only: the server can't read your database" | ADR-010 §12.5 |
| U-9 | Probe-name lint and error copy use `a–z 0–9 _ .` (C-7) | contracts §5.11 |
| U-10 | **New: "Where you're signed in"** section on S-17A (sessions are in the MVP): this browser first, browser label, network, signed in, last seen, method; **Sign out** per row and **Sign out all other browsers** (both via S-28); S-28 `{action}` gains "sign out other browsers" | domain §2.7; contracts `SessionRow`, `DELETE /account/sessions…` |
| U-11 | S-17B `blocked`: **Unblock** after S-28 with another method; hide Unblock when `canUnblock = false` (flagged twice) with "This key was blocked twice. Remove it and add it again."; copy for `flagCause = 'user_handle_mismatch'`; new email `key-unblocked` | domain §2.2; contracts unblock route |
| U-12 | S-17B `linked`: routes now exist; new email `method-unlinked`; Connect is not disabled by the recovery lock (only Disconnect is) | domain §2.5 |
| U-13 | **Change email dialog** (S-17A): field, uniform "We sent a link to {new}"; pending row with Cancel; refused-lock and "confirm with a passkey, key or authenticator code" states; new interstitial page "Confirm new email"; new emails `email-change-verify`, `email-change-requested` (old address, 2FA accounts), `email-change-approve` (old address, accounts without 2FA, EC-1), `email-changed` (old, new address masked); pending row shows which half is missing (`pendingEmail.awaiting`); see U-28 | domain §2.6; contracts §3.4 |
| U-14 | **Interstitials** for every emailed link: S-25 link-opened state gains a **Confirm email** button; S-29 #3 as drawn (the `GET` no longer starts the wait; "Cancel, I found my key" = decline); expired/used/unknown share one page | domain §2.9 |
| U-15 | Confirmed values: verification 60 min, reset 60 min, recovery request 60 min, **recovery finish 24 h**, email change 60 min; password minimum **12**, maximum 128; replace the breach message with "This password is too common. Choose another."; add "Don't use your email address as your password."; password change signs out other browsers (confirmed) | domain §2.1, §2.9 |
| U-16 | S-28 for OAuth-only accounts: **Continue with GitHub/Google** is valid; mismatch error "That isn't the GitHub account linked here."; on a 2FA account an email change offers only passkey, key or authenticator code | domain §2.1; contracts `/confirm-auth` |
| U-17 | S-30 data: `RecoveryReview` (agents, CI tokens across projects with Revoke, log); S-17A shows the review link while `recoveryReview` is set | contracts §3.4 |
| U-18 | Security log on S-17A (last 30 days) optional: the data is in `SecurityView.securityLog` | domain §2.8 |
| ~~U-19~~ | **Closed by revision 6**: account deletion is specified (domain §2.10, contracts §3.4); see U-20 | — |
| U-20 | **S-17 `delete`**: route `GET /account/delete` (`AccountDeletionView`), `DELETE /account {confirm: true}`; S-28 runs in **second-factor mode** on 2FA accounts (`action=delete_account`; A03 copy for the line, e.g. "Deleting your account can't be undone, so confirm with a passkey, security key or authenticator code."); refusals from `deletion.blocked` (`recovery_open` is normally unreachable, RC-1; `recovery_completed` with `until`; `last_instance_admin`: copy must point to the host console, not to S-21 Make admin, K-24); **active runs are aborted**: optional line "Runs in progress stop." when `activeRuns > 0`; optional backup line "Copies in our encrypted backups are deleted within 14 days." [PO-L]; done flash `code: 'account_deleted'`; `account-deleted` email confirmed (admin variant without browser and network). AC-26.1 and AC-26.2 hold | domain §2.10; contracts §3.4 |
| U-21 | **Throttle**: S-02, S-24, S-26, S-28 read `errors.form = 'throttled'` and `retryAt` for "Try again after {time} UTC"; values in domain §2.1 | contracts §3.4 |
| U-22 | **Security-log codes**: A03's RD `SECURITY_LOG_COPY` codes adopted as is; add copy for `sign_in_held` ("Password sign-in paused after too many wrong passwords"), `two_factor_disabled`, `email_change_cancelled`; render from `params` (schema §7 table), names as text | schema §7; contracts `SecurityLogEntry` |
| U-23 | **Password**: argon2id only, so remove the bcrypt helper and the 72-byte error variant; read limits from `passwordPolicy`; S-17B `password` has no current-password field (confirmed: never required) | contracts §3.4 |
| U-24 | **Sign out a browser**: already-ended → `flash.code = 'session_already_ended'` (A03's toast); all others with none left → `no_other_sessions` | contracts §3.4 |
| U-25 | **S-32 Confirm new email**: props `EmailChangeLinkPage`; signed-out state shows no address; other-account state shows `signedInAs`; "Sign out and continue" returns to the page after sign-in (session intended URL) | contracts §3.4 |
| U-26 | **`email-changed` copy** (old address): "If this wasn't you, sign in with a passkey, GitHub or Google if you set one up, then check Security." + hosted support line [PO-M] / self-host "Ask your administrator." | domain §2.6, R-10 |
| U-27 | **S-17 `delete` on accounts without 2FA (DL-4, revision 6.2)**: `AccountDeletionView.mode = 'email_link'`: the button reads "Send confirmation link" (not "Delete"); after the `DELETE`, state "We sent a link to {address}. It works for 1 hour." with Cancel (`DELETE /account/deletion`); new interstitial page `/account-deletion/{token}` (`AccountDeletionLinkPage`: `confirm` with the final "Delete my account" button, `sign_in_required`, `other_account`, `invalid`, plus `blocked` copy); new email `account-deletion-confirm`; flash code `deletion_link_sent`. On 2FA accounts S-17 is unchanged | domain §2.10; contracts §3.4 |
| U-28 | **Email change on accounts without 2FA (EC-1, revision 6.2)**: new interstitial `/email-change-old/{token}` (`EmailChangeOldLinkPage`: `confirm` with the new address masked, `sign_in_required`, `other_account`, `invalid`); `/email-change/{token}` `confirm` gets `needsOld` (copy: "We'll also ask your current address"); after the first half, flash codes `email_change_waiting_old` / `email_change_waiting_new`; S-17A pending row shows "Waiting for your current address / the new address"; new email `email-change-approve` (replaces `email-change-requested` on these accounts). Owner who lost the old mailbox: copy points to enrolling a passkey or authenticator first | domain §2.6; contracts §3.4 |
| U-27 | **Invites (self-host)**: S-21 gains **Invite** (email field) and status **Invited**; S-24 invite-token variant removed (invite-only shows the "need an invite" line only); new link page "Set your password" (`/set-password/{token}`, purposes invite / admin reset / set-email restore) in S-32 with its "This link doesn't work" variant; new email `invite` | domain §11; contracts §3.4 |

---

## 8. A08's ADR-004 spike: closed (decision 50)

Verdict **CONFIRMED WITH CHANGES** (`spikes/live-props/SPIKE-REPORT.md`, 22 tests, run twice, laptop, order of magnitude only). ADR-004 amendment 1 is Accepted; the changes are applied in revision 6.3 of the event catalogue and contracts and revision 3 of the amendment.

| Item | Result | Where applied |
| ---- | ------ | ------------- |
| Guest-guard channel auth on `private-run`; guard × channel matrix (TM-T20) | Pass: 216 cells (9 principals × 2 endpoints × 12 channels) as specified, including an agent token against its own `private-workspace` | catalogue §1 |
| Agent auth through `/agent/v1/broadcasting/auth` | Pass; response must carry signed `channel_data` (C-3) | contracts §5.8 |
| Client events rejected (TM-T22) | Pass with `none`; stock `members` relays whispers and a missing key means `all` (C-4) | catalogue §1 rule 4 |
| Ordering, 50 deliveries × 10 viewers; reconnect recovery | Pass; reordering is real with more than one worker, so the gap reload is required (C-6) | catalogue §3.3 |
| Terminating events (TM-T21) | Pass; add `terminate_connections` after the event, 7 to 9 ms (C-2) | catalogue §1 |
| Connection max lifetime and per-principal cap | Pass as **our code in the Reverb process**; Reverb has neither (C-1) | catalogue §1 rule 5 |
| Shared-run polling and per-access props | Pass; absent-not-lazy proven; 30 / min then `429` | contracts §3.2 |
| Finished run inside a reload | Finding: client must detect it from the reload; `timeline.status` (C-5) | catalogue §3.3, contracts §3.2 |
| Long-poll capacity (TM-40) | Uncapped holds one worker (4.3 s stall); cap with `503` + `Retry-After` works across processes (C-8) | contracts §5.4 |
| Capacity | 1,000 sockets, 50 runs × 10 viewers: p99 under 20 ms. **Reverb died at about 1,014 sockets without `ext-uv` or `ext-event`** (C-7, A10 H-3) | catalogue §1 rules 5, 7 |

G-6 (the spike invalidates direct Echo events) did not happen; the `seq` + reload design stays.

**Left for Phase 5 testing** (not covered by the spike): a real browser client; numbers on the VPS (with `ext-uv`/`ext-event`, TLS, limits at Reverb and proxy, argon2id p95); agent heartbeat cost at PERF-1; the capped long-poll on the production server stack (FPM or FrankenPHP). The TM-23 canary over a complete real run is A11's once real producers exist.

---

## 9. Phase 5 backlog (account, frozen scope)

Decision 47 froze the account scope: only the decisions it lists (PO-L, PO-M, PO-N, PO-O) are applied to the specs and boards. Any **new account case** is written here instead of opening another spec, board and review cycle. Nothing below is designed or scheduled; each row says what it is and where it came from. Items that are PO or A10 work already in progress are not repeated here.

| # | Item | Origin | State |
| - | ---- | ------ | ----- |
| B5-1 | **WebAuthn Signal API** to hide a deleted account's passkey from the browser (today the authenticator keeps it and a sign-in fails uniformly) | domain §2.10 cascade, Identity/WebAuthn row | Post-MVP, not designed |
| B5-2 | **Deletion grace period** (7-day `pending_deletion` with a cancel page) | PO-L | Decided against; kept only as a record so it is not re-proposed without a new PO decision |
| B5-3 | **Operator restore of an address or an account** on hosted | PO-M, R-10, R-12 | Not offered; would need A09 to model it first |
| B5-4 | **Revert link ("This wasn't me")** | R-AE, A09 §12.11 | Rejected twice (A09, PO-O); listed only so it is not re-proposed |
| B5-5 | **Residual R-10 after EC-1**: an owner without a second factor who lost the old mailbox must enrol a passkey, security key or TOTP before changing the address; no help is offered beyond that copy (U-28) | PO-O | Accepted; a guided "I lost my old mailbox" flow is not designed |
| B5-6 | **Residual R-12 after DL-4**: an attacker holding both the password and the mailbox of an account without a second factor can still delete it | PO-N | Accepted (decision 47) |
| B5-7 | **Self-host without outbound mail**: accounts without a second factor cannot confirm a deletion or an email change (no link can be sent); the host console commands `webhooktrap:user:delete` and `webhooktrap:user:set-email` are the only path | DL-4, EC-1, domain §11 | Not designed; documentation note for A10 and the self-host guide |
| B5-8 | **Unverified and imported accounts** cannot change their email (S-25 "Wrong address? Start again") but can delete, and the DL-4 link then goes to an address they have not proven (a 1.x import before activation has none that is verified) | domain §2.6, §2.10 | Accepted as is; revisit if 1.x users report it after launch |
| B5-9 | **Email-change and deletion link lifetimes** are configuration values (60 min, 1 h); extending the old-address window beyond the new-address link (for example 24 h) was considered and not chosen | EC-1, DL-4 | Open for tuning after launch data |

**Still open with the PO (decision 47), not account design:** the alert channel for "purge late" and similar alerts, and custody of the `age` identity for the deletion-ledger copy (A10's DL-3b).

---

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §2: S-1..S-14 resolved with pointers; S-15 confirmed; §2.1 items for A09's re-check | A09 §9.10, §6 |
| 2026-11-14 | 2 | §4: PO outcomes; PO-8 superseded by PO-A; PO-A..PO-G, HD items; proposed numeric limits | §9.10; decision 39 |
| 2026-11-14 | 2 | §5: K-12 resolved by S-8; K-15 confirmed; K-16..K-19 added | §9.10, S-7, S-8; PO-D, PO-F, HD-1, HD-5 |
| 2026-11-14 | 2 | §3: A10 items mapped to HD decisions; runner VM; Valkey | HD-1..HD-10 |
| 2026-11-14 | 2 | §6: G-8 closed; G-9, G-10 added; §7 A08 spike list | PO-A, PO-E; ADR-004 am. |
| 2026-11-14 | 3 | §1: Q3 approved; C-7 and C-8 resolved | decision 44; A01 C-7, C-8 |
| 2026-11-14 | 3 | §2.1: R-I to R-P for A09 (WebAuthn, factors, recovery, 1.x import, claim, FrankenPHP, Level 1 server runs) | decisions 41, 43, 44 |
| 2026-11-14 | 3 | §3: H-14 to H-16 | decisions 41, 43, 44 |
| 2026-11-14 | 3 | §4: PO-H (1.x passwords), PO-I (email link), PO-J (hosted recovery), PO-K (attestation) | decisions 41, 44 |
| 2026-11-14 | 3 | §5: K-20 to K-22; §7 new items for A03; former §7 renumbered §8 | decision 44; A01 C-7, C-8 |
| 2026-11-14 | 4 | §2.2 new: A09 verdicts and required changes for R-A..R-P with pointers; A10 items listed for the PO | A09 §12.3, §12.5 |
| 2026-11-14 | 4 | §4: PO-H, PO-I, PO-J, PO-K updated with A09's advice (§12.4); PO-H B recorded as a recommendation pending PO; R-7 stated with PO-J | A09 §12.4, §12.6 |
| 2026-11-14 | 5 | §2.3 new: A09 §12.9 follow-ups (RN-6 column, RC-1 passkey wording, PO-H label, ENG-1 alignment) with pointers | A09 §12.9 |
| 2026-11-14 | 5 | §2.4 new: R-Q to R-X for A09's re-check (unblock, connect/disconnect, email change, OAuth re-auth, emailed links, RC-5 reading and password clearing, password policy, sessions) | A03 S-17A/B, S-24..S-31 |
| 2026-11-14 | 5 | §4 PO-H option B label: reset link or provider-verified OAuth; passkey is not an activation method | A09 §12.9 |
| 2026-11-14 | 5 | §3 H-5: engine as a separate `engine` service; link-token masking for A10 | ENG-1, ENG-2 |
| 2026-11-14 | 5 | §7: U-10 to U-19 for A03 (sessions screen, unblock, linked, email change, interstitials, lifetimes and password values, OAuth re-auth, review data, account deletion gap) | A03 screen-specifications rev 3 |
| 2026-11-14 | 5.1 | §2.5 new: AR-1 and AR-2 applied; accepted residuals R-10 (email change on password-only accounts, no revert link) and R-11 (GitHub re-auth proves only a live GitHub session) | A09 §12.10 AR-1, AR-2, R-10, R-11 |
| 2026-11-14 | 6 | Decision 46 applied: PO-H (option B), PO-I (no magic link), PO-J (delayed recovery, R-7 accepted) recorded as decided; `[PO-H]`/`[PO-J]` markers removed from the package body (history rows unchanged); ADR-010 and amendments 0003/0005/0006 Accepted, 0004 Accepted conditional on A08's spike (status lines only) | decision 46 |
| 2026-11-14 | 6 | §2.6 new: R-Y to R-AG for A09 (deletion, backups, throttles, argon2id, idempotent revoke, link page, R-10 recourse and revert-link proposal, invites, log codes); residual R-12 proposed | A03 O-SS-9 |
| 2026-11-14 | 6 | §3: H-17 emailed-link path list, H-18 deletion ledger and privacy notice, H-19 argon2id in the image | A03 O-SS-9; A10 |
| 2026-11-14 | 6 | §4: PO-L (deletion immediate vs grace), PO-M (hosted support for R-10/R-12) | domain §2.10, §2.6 |
| 2026-11-14 | 6 | §5: K-23 (decision 46 passkey wording), K-24 (S-21 Make admin vs TM-19), K-25 (S-24 invite token) | decision 46; A03 S-21, S-24 |
| 2026-11-14 | 6 | §7: U-19 closed; U-20 to U-27 for A03 | A03 O-SS-9 |
| 2026-11-14 | 6.1 | §2.7 new: A09 §12.11 changes DL-1, DL-2, DL-3 (app side), TH-1, SE-1, ED-1 recorded as applied with pointers; R-AE revert link rejected (R-AE row rewritten) | A09 §12.11 |
| 2026-11-14 | 6.1 | §4: PO-N (DL-4, email-confirmed deletion without 2FA) and PO-O (EC-1, old address confirms an email change without 2FA) recorded as PO decisions pending | A09 §12.11 advice |
| 2026-11-14 | 6.1 | §3 H-18: ledger append by the purge job before the row is deleted; off-server copy, retention and restore ordering (before web, workers, queues) assigned to A10 | A09 §12.11 DL-3 |
| 2026-11-14 | 6.2 | §4: PO-L, PO-M, PO-N, PO-O stated as decided (decision 47); "pending" and "recommends" wording removed; §2.6 R-10, R-Y (R-12 accepted, narrowed), R-AE (EC-1 adopted) and §2.7 DL-4 and EC-1 rows rewritten as applied | decision 47 |
| 2026-11-14 | 6.2 | §3 H-17: `/email-change-old/{token}` and `/account-deletion/{token}` added to the emailed-link list for A10's log scrubbing, with a note on the distinct prefixes | decision 47 PO-N, PO-O |
| 2026-11-14 | 6.2 | §7: U-13 updated; U-27 (S-17 `delete` link flow, new interstitial and email) and U-28 (EC-1 interstitial, pending states, new email) for A03 | decision 47 |
| 2026-11-14 | 6.2 | §9 new: Phase 5 backlog (account, frozen scope), B5-1 to B5-9 | decision 47 |
| 2026-11-14 | 6.3 | Decision 50 applied: §8 closed with A08's spike results and the Phase 5 remainder; G-6 closed | decision 50 |
