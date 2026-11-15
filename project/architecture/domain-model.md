# Domain model · Webhook Trap 2.0

**Status:** Proposed, revision 6.2 (applies decision 47: PO-L, PO-M, PO-N, PO-O decided; DL-4 and EC-1 designed); revision 6.1 (applies A09 §12.11: DL-1, DL-2, DL-3, TH-1, SE-1, ED-1; R-AE rejected); revision 6 (Phase 4, started before Gate 2 per decision 36) · **Author:** A05 · **Date:** 2026-11-14
**Security review:** A09 `project/security/threat-model.md` §9.6 (ACCEPT WITH CHANGES). Decisions 38 and 39 applied. Revision 3 applies decisions 41, 43 and 44. Revision 4 applies A09's re-check (rev 3) §12.5: WA-1..WA-5, RC-1..RC-6, PO-H option B, RE-1, RN-1. Revision 5 applies A09's rev 4 follow-ups (§12.9: RC-1 wording, ENG-2) and closes the account-security contract gaps A03 found (S-17A/B, S-24..S-31, FL-15..FL-23): §2.2 unblocking, §2.5–§2.9 new. Revision 6 answers A03's O-SS-9 (S-17 `delete`, S-17A/B, S-28, S-32, FL-24..FL-26): §2.10 account deletion, throttle values, argon2id only, R-10 recourse, invites. **PO decisions (decision 46):** PO-H option B, PO-I (no magic link) and PO-J (delayed email recovery with RC-1..RC-6; R-7 accepted) are decided; the former pending markers are removed.
**Read with:** ADR-001 (modular monolith), ADR-010 (agent and engine), amendments 0003/0004/0005/0006-amendment-1, `schema.md`, `event-catalogue.md`, `contracts.md`.
**Product rules:** A01 `scenarios-and-judge.md` (rule IDs cited). **IA:** A03 `information-architecture.md` (read-only input; may change before Gate 2).

---

## 1. Modules (bounded contexts)

ADR-001's initial domain list (Capture, Endpoint, Delivery, Replay, Workspace, Identity, Monitoring, Retention) predates concept B. Mapping, without editing ADR-001:

| ADR-001 domain | 2.0 module | Note |
| -------------- | ---------- | ---- |
| Identity | **Identity** | adds guest sessions as a guard, tokens |
| Workspace | **Workspace** | adds Project |
| Endpoint, Delivery, Capture | **Capture** | the capture URL is the old "endpoint"; a capture is the old "delivery" |
| Replay | **Proving** (server side) + engine (Go) | a replay is a run (ADR-006 amendment 1) |
| — | **Agent** | new (ADR-010) |
| — | **Packs** | new: provider data, read-only |
| — | **Fixtures** | new: metadata only |
| — | **Sharing** | new: visibility and scrubbed rendering |
| — | **Instance** | new: self-host admin |
| Retention | **Retention & Quotas** (cross-cutting maintenance) | |
| Monitoring | — | Horizon 2 (concept C); not in the MVP |

Infrastructure adapters (not domains): Persistence, Broadcasting (Reverb), Queue/Cache/RateLimit (a Redis-compatible server: Redis or Valkey, HD-8; behind Laravel contracts only, decision 32 §4), EngineProcess (calls the separate `engine` service over a Unix socket for network-free `plan` and `verify` only, ADR-010 §1; output treated as untrusted), RunnerApi (`/runner/v1` for the remote server runner, ADR-010 §12), Clock, Ids.

### 1.1 Context map

```
                 ┌──────────── Identity ────────────┐
                 │ User · GuestSession · tokens      │
                 └──────┬───────────────────────────┘
                        │ owns (exactly one principal)
                 ┌──────▼──────── Workspace ─────────┐
                 │ Workspace · Project               │
                 └──┬──────────┬──────────┬──────────┘
                    │          │          │
        ┌───────────▼──┐  ┌────▼─────┐  ┌─▼────────────┐
        │   Capture    │  │  Agent   │  │   Fixtures   │
        │ CaptureUrl   │  │ Agent    │  │ FixtureMeta  │
        │ Capture      │  │ Pairing  │  └──────────────┘
        └──────┬───────┘  └────┬─────┘
               │ selected by   │ executes (via jobs)
        ┌──────▼───────────────▼──────┐        ┌───────────┐
        │           Proving           │◀───────│   Packs   │ (read-only data, shared with engine)
        │ Run (+ scenarios, deliveries│        └───────────┘
        │  findings) · RunJob         │
        └──────┬──────────────────────┘
               │ published as
        ┌──────▼──────┐       ┌────────────┐
        │   Sharing   │       │  Instance  │ (self-host: access policy, limits, server replay, secrets, audit)
        └─────────────┘       └────────────┘

        Engine (Go, outside the monolith): Plan · Execute · Judge · Render · Guard · Signing · Scrub
        Published language between engine and server: run.schema.json + contracts/agent/v1/*
```

Relationships:

- **Identity → Workspace:** customer/supplier. Workspace asks Identity "who is the principal"; Identity never reads projects.
- **Capture → Proving:** Proving references captures by id and `body_sha256`; it never mutates a capture.
- **Agent ↔ Proving:** Proving creates a `RunJob`; Agent delivers it (nudge + pull) and receives observations, which it hands to Proving through an application service. Agent knows nothing about findings.
- **Runner ↔ Proving:** server-run jobs are pulled by the remote server runner over `/runner/v1` (ADR-010 §12). The runner is an executor like an agent, without a workspace; it hands observations to the same Proving service.
- **Engine ↔ server:** conformist on `run.schema.json`. The server never judges (ADR-010 §3).
- **Packs:** shared kernel of data files, versioned; both engine and server load the same files.

### 1.2 Module rules (enforced by Pest `arch()`)

1. A module touches another module's tables only through that module's application services or read models. No cross-module Eloquent relations except read-only ones declared in a module's `Contracts`.
2. Domain events cross modules; models do not.
3. No module uses the Redis facade or client directly; only `Infrastructure/Queue`, `Infrastructure/Cache`, `Infrastructure/RateLimit` may (decision 32 §4). The architecture test accepts either Redis or Valkey as the server (HD-8); no code depends on which one.
4. No module calls `now()`/`Carbon::now()` outside the Clock adapter (D-10).
5. Proving never formats a probe value into text (PV-6); it renders from structured display fields.
6. Finding prose is rendered by the server from finding `type` + pack version; agent-supplied prose is never stored for display (TM-16).

---

## 2. Identity

### Aggregates

**User** — id `usr_`, email (unique, verified), display name, password hash (optional: OAuth-only and passkey-only accounts have none), linked identities (GitHub, Google), WebAuthn credentials, TOTP factor, recovery codes (§2.1), roles (`instance_admin` on self-host only, granted from the host only, TM-19), permission `can_private_replay` (B′, self-host only, granted by an instance admin, PO-G), status (active, disabled), legacy import marker (§2.4), user handle (§2.2), second-factor failure counter and hold (§2.1), pending email change (§2.6), sign-in sessions (§2.7).

**GuestSession** — id `gst_`, token hash (SHA-256 of a 256-bit random cookie token, cookie `__Host-wt_guest`), created_at, expires_at = created_at + 24 h, ended_at, claimed_by_user, claimed_at, counters (stored captures, stored bytes, counted runs, accepted runs), pending claim nonce (hash + expiry, single use).

Invariants:
- `expires_at` never moves (A-13 (a)).
- A guest session is usable only while `now < expires_at` and `ended_at is null` and `claimed_at is null`. Every request checks this before any data access; expired sessions render `/expired` with nothing from the session (R59).
- A guest session owns exactly one workspace.
- A claim happens only when the sign-in was started from this guest session: the claim nonce is created at sign-in start and consumed when the sign-in completes **in the same browser session** (OAuth callback, password + second factor, or passkey assertion), after any second factor (TM-12). A password-reset or email-verification link opened in another browser never claims.
- A guest session has no password, no second factor and no WebAuthn credential; WebAuthn ceremonies require the `web` guard or an anonymous sign-in page (§2.1).
- The accepted-run ceiling (20) is counted by the server at `RunAccepted`, independent of the agent's report (A09 §7.3).

**PersonalToken** (`tok_` · kind `personal`) — user-owned bearer token for CLI/MCP; abilities `read` and `run` chosen at creation, `run` off by default (TM-111); hashed; last 4 shown; revocable.
**CiToken** (`tok_` · kind `ci`) — project-owned, write-only `runs:upload`; read from `WEBHOOKTRAP_TOKEN` only; optional expiry (TM-60, TM-61).
**RunnerToken** (`rnr_`) — instance-level identity of a server runner; issued on the host only; source CIDR **required on hosted** (optional on self-host, RN-1); revocable; rotated at every runner VM rebuild and at least every 90 days, two tokens may overlap during rotation (RN-2; ADR-010 §12.1).
Token values carry a kind prefix and a checksum (contracts.md §1.1).

### Guards

| Guard | Principal | Used by |
| ----- | --------- | ------- |
| `web` | User | browser sessions |
| `guest` | GuestSession | guest browser sessions (ADR-003 Phase 0 amendment) |
| `agent` | Agent | `/agent/v1/*` and `private-agent.*` channel (own broadcast auth endpoint) |
| `runner` | RunnerToken | `/runner/v1/*` only; no broadcast access |
| `ci` | CiToken | run uploads only |
| `api` | PersonalToken → User | `/api/v1/*` |

Policies receive `User | GuestSession` and check workspace ownership (one helper: `Workspace::isOwnedBy($principal)`). Channel and policy callbacks check the principal **type** before ownership; `Agent` and `RunnerToken` principals never pass a browser policy (TM-20).

### Lifecycle

`GuestSession: active → (claimed | ended | expired) → deleted` (deletion by the retention job; access stops at the state change, not at deletion).

### 2.1 Authentication methods and factors (decision 44)

| Method | Kind | Proves | Notes |
| ------ | ---- | ------ | ----- |
| Email + password | first factor | knowledge | Laravel `Hash`, **argon2id only** (revision 6, "Password hashing" below). Email must be verified before first use of any workspace feature |
| GitHub, Google | first factor | the provider's account | Socialite. Linked by provider subject; an email match links only when the provider says the email is verified (pre-hijack rule, §2.4). Linking a new identity to an existing account emits `IdentityLinked`: email notice "Sign-in method linked" plus audit row (WA-5, TM-124). OAuth `state` is bound to the browser session and PKCE is used wherever the provider supports it |
| **Passkey** (WebAuthn, discoverable, user verification) | first **and** second factor | possession + local verification | passwordless sign-in; satisfies 2FA on its own |
| TOTP | second factor | possession | RFC 6238, 30 s, ±1 step, a code accepted at most once (last-used step stored) |
| **Security key** (WebAuthn, any credential) | second factor | possession | after password or OAuth |
| Recovery codes | second factor, fallback | possession of the printout | 10 codes, single use, hashed |
| Guest session | — | the cookie | no factors, no WebAuthn (§2) |
| Agent key, personal/CI/runner tokens | machine principals | key or token | unchanged (§2 Guards); never a way into the browser session |

**2FA state.** A user "has 2FA" when they hold at least one second factor: a TOTP factor or a WebAuthn credential. Then **every** first-factor sign-in (password, GitHub, Google) is followed by the 2FA challenge, where the user picks a security key (offered first when registered), a TOTP code or a recovery code. A passkey sign-in already carries user verification and skips the challenge. The browser session is regenerated after the challenge, and a claim (ADR-003 am. 1 §4) happens only after it.

**Recent authentication ("sudo").** Adding, removing or unblocking a factor, changing the password or email, connecting or disconnecting GitHub/Google, regenerating recovery codes, creating a token with `run`, revoking sessions, deleting the account and B′ admin actions require an authentication within the last **10 minutes** (password, passkey, security key or TOTP; Laravel's password-confirmation middleware extended to accept WebAuthn). Rules added in revision 5:
- **Password reset on a 2FA account (revision 5.1, A09 AR-2):** the reset link proves only control of the mailbox. After `POST /reset-password` on an account that has 2FA, the person is **not** signed in directly: the 2FA challenge follows. `reset_link` (`user_sessions.first_factor`) is a first factor only, **never** counts as a second factor, and **never** sets recent authentication. On an account without 2FA the reset may sign the person in, still without recent authentication. Test TM-T151.
- **Email change on a 2FA account** requires that the recent authentication used a **second factor** (passkey, security key or TOTP), not the password alone: the email is the recovery channel (§2.6). The session records which method set the recent-authentication time.
- **Account deletion (revision 6, DL-1 in 6.1)** does not ride on the general 10-minute window. It needs a **purpose-bound confirmation**: a `/confirm-auth` completed for `action=delete_account` in this session within the last **5 minutes**, consumed by the `DELETE` (single use; a second `DELETE` or one after a different recent authentication is refused). The session stores the purpose, the method and the time of the last confirmation; a confirmation made for another purpose, or earlier than the deletion screen, never counts. On a 2FA account the confirmation must have used a second factor (§2.10); on an account without 2FA the password (or the OAuth round trip for OAuth-only accounts) is enough **for the confirmation**, but the `DELETE` then only sends a confirmation link to the account address and the deletion happens on that link's button (DL-4, PO-N, decision 47; §2.10). Test TM-T162.
- **OAuth-only accounts** (no password, no WebAuthn credential, no TOTP) confirm with an **OAuth round trip** to a linked provider: the callback must return the **same provider subject** as the linked identity; Google is asked for a fresh login (`prompt=login`, `max_age=0`); GitHub has no forced re-login, so this proves a live GitHub session in that browser and nothing more. It is offered only to accounts with no other method; an account with any second factor confirms with it. (A09 to re-check the GitHub residual.)
- A **flagged** credential never satisfies recent authentication.

**Last-method rule.** A user always keeps at least one first factor (password, linked OAuth identity or **unflagged** passkey). Removing the last one (key removal, identity disconnect) is refused (`422 last_sign_in_method`).

**Password policy (all paths: register, reset, change, set after import, recovery finish).** At least **12 characters** and at most **128** (counted in Unicode code points after NFC normalisation); any printable characters including spaces; no composition rules; never truncated; refused when equal to the account email (case-insensitive) or found in a **bundled list of common and breached passwords** (about 100 000 entries shipped with the app; no network call, so self-host offline works and no hash prefix leaves the server). Hashing: see "Password hashing" below (argon2id only, no 72-byte limit). Error codes: `password_too_short`, `password_too_long`, `password_common`, `password_is_email`. An online breach check (k-anonymity range API) is post-MVP. **Changing** the password (signed in) revokes every other `user_sessions` row and regenerates the current session; personal tokens are kept (listed in the email notice). A **reset** by email revokes every session. Removing the last second factor disables 2FA and deletes the recovery codes, after recent authentication and with an email notice.

**Limits.** Sign-in throttled per (email, address) and per address (**IPv6 addresses keyed on their /64 prefix**, here and in every per-address limit, A09 §12.1); 2FA challenge 5 attempts per challenge then a fresh sign-in; **per-account second-factor failure counter across challenges (WA-3, TM-122):** failed TOTP, recovery-code and security-key attempts are counted per user over a rolling 24 h regardless of how many first-factor sign-ins started them; at 20 failures the account's second-factor sign-in is held for 1 hour (hold repeats while failures continue), the user is emailed once per hold (`SecondFactorHold`), and every failure is audited without the code value. A successful second factor does not reset the counter (it decays with the window), so a correct guess cannot be mixed with fresh sign-ins to restart it; WebAuthn option requests rate-limited per address; at most **10 WebAuthn credentials** and one TOTP factor per user. Sign-in, reset and "email taken" answers are uniform (no account enumeration).

**Password hashing (revision 6).** **argon2id only**, through Laravel `Hash` with the `argon2id` driver: memory 64 MiB, time cost 4, 1 thread (Laravel's defaults; never below OWASP's floor of 19 MiB / time 2). **Uniform hash cost (TH-1c, revision 6.1):** a sign-in or `/confirm-auth` password check for an unknown email, an account with no password hash and a known email with a wrong password all run the same argon2id verification (against a fixed dummy hash of the same parameters when there is no real hash), so the ~300 ms hash time does not enumerate accounts; the throttles run before any hash, and the dummy path counts in A08/A10's memory budget (concurrent hashes × 64 MiB inside the app container's `mem_limit`). Test TM-T160. A08 measures the p95 hash time on the VPS (target ≤ 300 ms); the cost may be raised in configuration, and `Hash::needsRehash` upgrades a stored hash at the next successful password sign-in. The app image ships PHP with argon2 support and the app **refuses to boot** when `PASSWORD_ARGON2ID` is not available (boot check; A06 test). There is **no bcrypt fallback**, so there is no 72-byte limit: every instance enforces 12 to 128 code points, and forms read the limits from the `passwordPolicy` prop (contracts §3.4). The earlier "72 bytes on bcrypt" variant is withdrawn.

**Throttle values (revision 6).** Configuration values; A09 to confirm. "Address" is the client address from the trusted-proxy chain, IPv4 /32 or **IPv6 /64**. Per-email keys use the normalised email (lower case, NFC) hashed with a server key and are counted **whether or not an account exists**, so a throttle answer never reveals one. Browser forms get the time to show (`retryAt`), JSON endpoints `429` with `Retry-After` (contracts §3.4).

**Revision 6.1 (A09 TH-1):** (a) while a pair or account hold is in force the submitted password is **not verified**: no hash runs and the answer is the throttled answer whatever the password, so the hold is not a correct-password oracle (TM-T159); (b) password failures at `/confirm-auth` count toward the same per-account 20 / h key as sign-in failures, and TOTP, recovery-code and security-key failures there count toward the per-account second-factor counter (WA-3), so a stolen session cookie does not get a separate budget; (c) uniform hash cost for unknown, password-less and wrong-password cases ("Password hashing" above, TM-T160).

| Limit | Key | Value | When hit |
| ----- | --- | ----- | -------- |
| Password sign-in failures, per pair | (email, address) | 5 failures in 15 min | that pair blocked 15 min; a success resets the pair |
| Password sign-in failures, per account | email | 20 failures in 1 h, from any address | **password** sign-in for that email held 15 min; passkey, GitHub, Google and "Forgot password" still work, so an attacker cannot lock the owner out; audit row and security-log `sign_in_held` once per hold when the account exists; no email (avoids mail bombing) |
| Sign-in failures, per address | address | 30 failures in 15 min across all emails; and 100 sign-in requests per hour | address blocked 15 min for every sign-in method |
| Passkey assertion options | address | 30 per minute | `429` |
| Second-factor challenge | challenge / account | 5 per challenge; 20 per account per 24 h → 1 h hold (WA-3, unchanged) | as §2.1 above |
| Recent authentication (`/confirm-auth`) | user | 10 failures in 15 min | prompt held 15 min; Cancel still works |
| Forgot password | email; address | 3 per hour per email (silently dropped, the answer stays uniform); 10 per hour per address | address limit is visible |
| Register | address | 10 per hour | visible |
| Verification resend | user | one per 60 s, 10 per day | visible |
| Email change requests | user | 5 per 24 h | visible |
| Account recovery requests (RC-5) | email; address | 3 per 24 h per email; 10 per 24 h per address | uniform answer |
| Admin invites (self-host) | admin | 50 per day | visible |

`Retry-After` and `retryAt` are the latest end among the keys that were hit.

### 2.2 WebAuthn credentials (passkeys and security keys)

**WebAuthnCredential** (entity of User) — id `key_`, credential id (bytes, unique), COSE public key, algorithm, sign count, transports, AAGUID (display only), `discoverable`, `uv_at_registration`, backup eligible (BE) and backup state (BS) flags, user-chosen name, created_at, last_used_at, flagged_at (counter anomaly). One implementation serves both uses:

- **Passkey** = `discoverable` and `uv_at_registration`: usable for passwordless sign-in **and** as a second factor.
- **Security key** = any other credential: usable as a second factor only.

**Relying party.**
- **RP ID = the app host exactly** (`app.<domain>` hosted; the instance host on self-host), from configuration (`WEBAUTHN_RP_ID`, defaulting to the host of `APP_URL`), never from the `Host` or `X-Forwarded-*` headers. Not the registrable domain: a narrower RP ID keeps any other subdomain from asserting. Expected origin: exactly `APP_URL`'s origin. Every app page, ceremony pages included, is sent with `frame-ancestors 'none'` (and `X-Frame-Options: DENY`), so no origin can frame them (WA-4, contracts.md §3.4).
- The **capture host** (`in.`, HD-7) and `ws.` are never ceremony origins: the capture host serves no HTML and sets no cookies.
- **Share pages `/s/…`** and raw downloads are on the app origin but show untrusted content. They send `Permissions-Policy: publickey-credentials-get=(), publickey-credentials-create=()` as **defence in depth only**: `Permissions-Policy` is not a boundary against same-origin script, which stays excluded by the CSP without `unsafe-inline` and text-only rendering (TM-06, TM-16, TM-123); ceremonies run only on `/sign-in`, the 2FA challenge, the recent-authentication prompt and `/account/security`. The ceremony JSON endpoints require the session, CSRF and `Sec-Fetch-Site: same-origin`.
- Changing the RP ID orphans every credential. The value is shown on `/admin/overview` (self-host) with that warning; hosted keeps it fixed.

**Ceremony parameters.**

| | Register passkey | Register security key | Passkey sign-in | Second-factor assertion |
| - | ---------------- | --------------------- | --------------- | ----------------------- |
| `residentKey` | `required` | `discouraged` | — | — |
| `userVerification` | `required` | `discouraged` | `required` (server rejects UV=0) | `discouraged` (UP required) |
| `allowCredentials` | — | — | empty (discoverable; conditional UI allowed) | the user's credentials |
| `excludeCredentials` | the user's credentials | same | — | — |
| `attestation` | **`none`** | **`none`** | — | — |
| Algorithms | ES256, EdDSA, RS256 | same | | |
| Timeout | 5 min | 5 min | 5 min | 5 min |

- **Challenge:** 32 random bytes, stored in the server session with the ceremony type and the user (or none for discoverable sign-in), single use, expires with the timeout. Never in a cache shared across requests or in process memory (FrankenPHP worker mode, ADR-005 am. 1 §9).
- **User handle:** a random 32-byte value per user, not the user id or email; stored on the user. On discoverable sign-in the assertion's `userHandle` **must equal the handle of the user that owns the asserted credential**; a missing or different handle is refused with the uniform sign-in failure, the credential is flagged and an audit row is written (WA-2, TM-121). Flagged credentials are refused for sign-in and as second factor until the owner reviews and clears the flag.
- **Attestation `none` for the MVP:** no attestation statements are verified or stored, no metadata service is maintained, and no authenticator model is required or refused. The AAGUID, when the browser reports it, only picks a default name ("iCloud Keychain", "YubiKey 5"). `indirect`/`direct` and an authenticator allow-list are post-MVP (enterprise self-host); a self-host admin cannot require attestation in v1.
- **Counters (WA-1, TM-120):** stored after each assertion, following the WebAuthn rule: if **either** the stored or the received count is non-zero and the received count is **not greater** than the stored one, the assertion is **refused**, the credential is flagged, the user is emailed and an audit row is written. Only when **both** are 0 (synced passkeys that report no counter) is the assertion accepted without a counter check. A stored non-zero count followed by a received 0 is therefore refused.
- **Backup flags:** BE is fixed at registration; an assertion whose BE differs is refused. BS is updated on each use and shown ("Synced" or "This device only"). Synced passkeys are allowed.
- **Naming and revoking:** names are 1–48 printable characters, rendered as text (TM-16); default from the AAGUID or "Passkey"/"Security key" + date. Removing a credential needs recent authentication, deletes the row (no soft delete), emails the user and writes an audit row; it is refused when it is the last first factor.
- **Registration emails:** every new credential, TOTP enrolment and removal sends a notice to the account email.
- **Unblocking a flagged credential (A03 S-17B `blocked`, FL-23).** The owner may **unblock** a flagged credential from `/account/security` after recent authentication with **another** method. Unblocking clears `flagged_at`/`flag_cause`, **keeps the stored sign count** (so a cloned key with a lower count is refused and flagged again), keeps `flag_count`, emails the user (`key-unblocked`), audits and emits `WebAuthnCredentialUnblocked`. A credential flagged **twice** (`flag_count >= 2`) cannot be unblocked: remove and re-register it (`422 unblock_not_allowed`). The screen recommends removal; unblocking is the secondary action. Flagged credentials count toward the 10-credential limit and do not count as a first or second factor for the last-method rule or the 2FA state.

### 2.3 Recovery codes and account recovery

- Recovery codes are generated when the **first** second factor is enrolled (TOTP or WebAuthn), shown once, stored hashed, single use. Regenerating replaces the whole set. Using one emails the user and shows the count left.
- **All factors lost (no recovery code):**
  - **Self-host:** the instance admin runs a host console command that removes the user's second factors and passkeys and sends a password-set link; audited (same "from the host only" rule as TM-19).
  - **Hosted:** **delayed email recovery** (PO-J, **decided by the PO in decision 46** with A09's conditions, §12.4; residual R-7 accepted). The user requests recovery on the 2FA challenge; a link is sent to the account email; after confirming it a **72-hour wait** starts, during which every sign-in of the account sees a banner with "Cancel recovery" and a second email is sent at 24 h. At the end, the second factors, passkeys and recovery codes are removed, all browser sessions and personal tokens are revoked, the user sets a new password and is asked to re-enrol 2FA. Operator manual review is **not** used (a solo operator is a social-engineering target with no better evidence than email). Conditions (RC-1..RC-6, TM-125, TM-126):
    - **RC-1** Any sign-in that **completes a second factor** during the wait cancels the recovery automatically (the user evidently still has a factor); email sent; event `AccountRecoveryCancelled` with `by = second_factor_signin`. **A passkey sign-in counts as completing a second factor** (it satisfies 2FA on its own, §2.1), as does a 2FA challenge passed with a security key, TOTP or recovery code. A password-only or OAuth-only sign-in does not (an account with an open recovery has 2FA, so those sign-ins stop at the challenge anyway). A recent-authentication prompt (`/confirm-auth`) passed with a passkey, security key or TOTP in an existing session has the same effect (same evidence; A09 to confirm). (A09 §12.9.)
    - **RC-2** If the password was **reset by email** in the 7 days before the request or during the wait, the wait is extended to **7 days from the later** of those events (`completes_at` is recomputed).
    - **RC-3** While a recovery is open and for **7 days after completion**: no email change, no new personal token with `run`, no removal of linked identities (refused with an explanation; the guard reads `account_recovery_requests`). **Revision 5.1 (A09 AR-1):** the removal lock covers only identities linked **before** the recovery was requested (`user_identities.linked_at` earlier than the request's `requested_at`). An identity linked after that moment is **not shielded**: it is removable with recent authentication, so whoever completed a recovery cannot link their own provider and keep it for 7 days. Its `IdentityLinked` notice says that a recovery is open or recent, and the post-recovery review (§2.8) lists it.
    - **RC-4** Completion sets **every agent in the user's workspace to `needs_reconfirm`** with approvals cleared (as a claim does, TM-42) and lists the user's CI tokens for review in the post-recovery security log. Agents are therefore not left able to receive remote runs signed with the developer's real secrets after a takeover (TM-126).
    - **RC-5** One open request per user; a **30-day cool-down** after a cancelled request; requests rate-limited per address (IPv6 on /64) and per account; answers uniform whether or not the account exists.
    - **RC-6** An audit row for every step (request, confirm, extend, cancel, complete) and the security log shown to the user after recovery lists the recovery (§2.8).
    - **Links and steps (revision 5, A03 S-29):** the request link works for **60 min** and opens an interstitial page; the wait starts only when the person presses the button on it (§2.9), and "Cancel, I found my key" closes the unconfirmed request as `declined`. An unconfirmed request is closed as `link_expired` after 60 min. Only a cancellation **after confirmation** (`user` or `second_factor_signin`) starts the 30-day cool-down of RC-5, so a request started by someone else and never confirmed cannot lock the owner out. At `completes_at` the completion job removes the factors and recovery codes, **clears the password**, revokes every session and personal token, applies RC-4 and RC-3 and emails a **finish link** (24 h, single use) to set a new password; if it lapses, "Forgot password" works (the account no longer has 2FA). Finishing signs the person in and lands on the post-recovery review (§2.8). (A09 to re-check the RC-5 reading and the password clearing.)
    - **Residual risk R-7:** an attacker who controls the mailbox for longer than the wait, while the user does not sign in, takes over the account. Accepted as the usual trade-off for 2FA recovery; RC-1..RC-6 narrow it (A09 §12.6).
- **AccountRecoveryRequest** — user, requested_at, confirmed_at, completes_at (recomputed on extension, RC-2), extended_at, cancelled_at + cancelled_by (`user | second_factor_signin`), completed_at; at most one open per user; cool-down of 30 days after a cancellation (RC-5).

### 2.4 One-off import of the 1.x accounts (decision 41)

Scope: the **3 accounts** in `users.json` of the local export (none verified, no OAuth links, 2FA secrets and tokens not exported). Inboxes and events are **not** imported (decision 41: events stay archived only).

**Import.** A host console command (`webhooktrap:import-1x <path> [--dry-run]`) reads the file from a path given on the command line, outside the repository and outside the image; checks it against the export's `SHA256SUMS`; creates each user with email, display name and 1.x creation date, `email_verified_at = NULL`, no second factor, `password_hash = NULL`, and marks it `legacy_source = '1x'`. **The command does not read the password-hash fields of the export at all** (PO-H option B, A09 §12.4): the hash values are never parsed into memory, never stored and never logged; a test feeds an export whose hash fields are malformed and asserts the import succeeds identically. An email that already exists in v2 is skipped and reported. The command prints counts only (no emails, no hashes), writes one audit row per user and dispatches `LegacyAccountImported`. It runs **before** public sign-up opens. After the PO confirms the import, the export folder is deleted (export README).

**Password: forced reset (option B, A09's advice; decided by the PO, decision 46).** Option A (verify the 1.x scrypt hash once, then rehash) is **not adopted**: it would bring a hand-written scrypt verifier and three offline-crackable hashes into a new codebase to save three users one email, and it proves nothing about email ownership. The `legacy_password_hash*` columns and `LegacyHashDiscarded` are dropped from the design.

- An imported account has `password_hash = NULL`. First access: the "Forgot password" link (proves the email) or GitHub/Google sign-in with that verified email; activation marks the email verified. The launch notice tells the three users to "set a password" or sign in with GitHub or Google using the same address (decision 41).
- **A passkey is not a standalone activation method:** a passkey can be enrolled only after the email has been proven (reset link, or provider-verified OAuth); an imported unverified account cannot start from a passkey. Decision 46 lists "a passkey" among the ways back in; that holds after activation (an imported account has no passkey until its email is proven), so the rule stands. Recorded as conflict K-23 for A00/the PO.

**Rules.**
- **Pre-hijack rule:** an imported account is unverified. When ownership of its email is first proven (reset link, verification link, or an OAuth sign-in whose provider marks that email verified), any credential set before verification is discarded and the email is marked verified.
- Imported accounts that are never activated are kept (decision 41); they follow normal account rules. `legacy_source` stays for audit; it grants nothing.
- `LegacyAccountActivated` records the method (`reset`, `oauth`) for the PO's follow-up.
- The password set on activation follows the password policy (§2.1).

### 2.5 Linked identities: connect and disconnect (A03 S-17B `linked`)

- **Connect** (signed in, recent authentication): OAuth round trip with `state` bound to the session and a `link` purpose. The provider subject must not be linked to another account; if it is, the answer is `identity_in_use` ("That GitHub account is already linked to another Webhook Trap account"). Naming that is acceptable: the person has just proved control of that provider account. The provider's email may differ from the account email and need not be verified (linking from settings proves the provider account, not an email). One identity per provider per user. Emits `IdentityLinked` (`via = settings`): email notice and audit (WA-5). Not blocked by RC-3 (A09's RC-3 covers removal only). **AR-1 (revision 5.1):** an identity linked while a recovery is open, or within its 7-day lock, carries `linked_at` after the request and is therefore **outside** the RC-3 removal lock; the `IdentityLinked` email says a recovery is open or recent.
- **Disconnect** (recent authentication): refused when it would remove the last first factor (`422 last_sign_in_method`) and, for an identity linked **before** the recovery was requested, while a recovery is open or locked (`422 recovery_lock`, RC-3; identities linked after the request are removable, AR-1). Deletes the row; open sessions that signed in with that provider stay (the person may revoke them, §2.7). Emits `IdentityUnlinked`: email notice `method-unlinked` and audit.
- Signing in with a provider identity is unchanged (§2.1, pre-hijack rule §2.4).

### 2.6 Email change (A03 S-17A "Change email")

- Requested from `/account/security` with recent authentication; on a 2FA account the recent authentication must have used a second factor (§2.1). Refused while a recovery is open or within its 7-day lock (`422 recovery_lock`, RC-3); opening a recovery cancels a pending change.
- The answer is **uniform** whether the new address is free or taken: "We sent a link to {new}". If free, the new address gets `email-change-verify` (60 min, single use); if taken, it gets `account-exists`-style text instead and nothing can complete. The **old** address gets a notice at once: on an account **with** a second factor, `email-change-requested` (no link; it says to sign in and change the password, or start recovery, if this wasn't you); on an account **without** one, `email-change-approve`, which carries the approval link of EC-1 below.
- Confirmation: the link opens an interstitial (§2.9); the change happens on the button press, **only in a browser signed in to the same account** (any device; otherwise the page asks to sign in first and returns). The link page has four states (contracts §3.4 `EmailChangeLinkPage`): `confirm` (signed in to the account that asked), `sign_in_required` (signed out; no address shown), `other_account` (signed in to another account; "Sign out and continue"), `invalid`. While the person signs in, the link is kept as the session's intended URL, **never** in a `next` query parameter, so the token does not reach sign-in URLs or their logs. In one transaction: re-check RC-3 and that the address is still free, set `email` and `email_verified_at = now()`, delete outstanding password-reset tokens, revoke every other session. The old address gets `email-changed` (new address shown masked, e.g. `m***@b***.example`), the new one a confirmation. Events `EmailChangeRequested`, `EmailChanged`; audit both.
- **Both addresses confirm when the account has no second factor (EC-1, PO-O, decision 47).** `email_change_requests.old_required` is fixed at request time: true when the account has no second factor then. When true: (a) the old address gets `email-change-approve` with the link `/email-change-old/{token}`, **60 min, single use**, the same `expires_at` as the new-address link, so both halves must be pressed within 60 minutes of the request; (b) each link opens the §2.9 interstitial, and each button is a `POST` with CSRF, accepted **only from a browser signed in to the account that asked**, with the same four page states as the new-address link (`confirm`, `sign_in_required`, `other_account`, `invalid`; the old-address page shows the new address masked, `m***@b***.example`, never in full); (c) the first press, in either order, records its half (`new_confirmed_at` or `old_confirmed_at`), changes nothing and answers with a flash telling the person which half is missing; the second press applies the change in the single transaction above (RC-3 and uniqueness re-checked then). A mailbox alone completes nothing and a session alone completes nothing: whoever holds the password needs the old mailbox, whoever holds the old mailbox needs the password and a session. Pending states shown on `/account/security` (`pendingEmail.awaiting`): `both`, `old`, `new`; closed as `expired`, `replaced`, `recovery` or `user` (`DELETE /account/email` voids both links; opening a recovery cancels both). A **taken** new address never gets a usable link, so the change cannot complete (the answer stays uniform); the old-address press just records its half until expiry. Enrolling a second factor after the request does not relax the request. On accounts **with** a second factor nothing else changes: the change needs a second-factor recent authentication (§2.1) and the one new-address link. An owner without a second factor who has lost the old mailbox first enrols a passkey, security key or TOTP, then changes the address with it. Events `EmailChangeConfirmed` (side `new` or `old`), `EmailChanged`; audit both. **The R-AE revert link stays rejected.**
- One pending change per user; a new request replaces it. Unverified accounts (including imported 1.x accounts before activation) cannot change their email; they use "Wrong address? Start again" (S-25).
- **The old address after a change its owner did not make (revision 6; accepted residual R-10).** The old address gets `email-changed` (new address masked). The change touches no first factor, so the owner can still: (1) sign in with a **passkey** (discoverable, no email typed) or **GitHub/Google** (matched by provider subject), then sign out the other browsers, change the email back (with a second factor when 2FA is on) and change the password; (2) on a **2FA account** the change needed a second factor (§2.1), so a change the owner did not make means a factor was compromised: the same steps apply, plus removing that factor. (3) A **password-only** owner with no passkey and no linked provider cannot sign in any more (password sign-in uses the email, and the new one is masked) and cannot start recovery (it would go to the new address): the account belongs to whoever held the password. This is R-10, accepted by A09; the MVP has **no revert link and no operator restore** (PO-M, decision 47: hosted support never restores an account or an address). **With EC-1 (above), R-10 now reaches only accounts where the attacker also holds the old mailbox, or accounts the owner left single-factor after losing that mailbox.** The notice tells the hosted owner to contact support (what support may do is PO-M, decided: it only writes to the address; below) and the self-host owner to ask the instance administrator, who can restore the address from the host console (`webhooktrap:user:set-email <usr_id> <email>`, audited, revokes every session and sends a password-set link to the restored address, §2.9). **Revision 6.1 (SE-1):** whoever moved the account may have added a passkey, a TOTP factor or a linked identity of their own, so the command **lists the factors and linked identities created since the disputed `email_change_requested` audit row and removes them by default** (`--keep-factors` opts out); recovery codes are regenerated when a factor was removed; the restored owner lands on a review page like S-30 (what was removed, what remains). When no factor remains after the removal the account is password-set-only again; when the remaining factors include a second factor, the password-set link leads to the 2FA challenge (AR-2). Test TM-T161. A time-limited "This wasn't me" revert link for password-only accounts (open-questions R-AE) is **rejected** per A09 §12.11 and by the PO (PO-O, decision 47: the old mailbox would become an email-only undo for 7 days). EC-1, **adopted** (PO-O), is the bullet above: on an account without a second factor, the old address must also confirm the change.

### 2.7 Sign-in sessions (MVP; A03 to add the screen)

**UserSession** (entity of User, `ses_`) — one per signed-in browser: created_at, last_seen_at, idle and absolute expiry (14 days idle, 30 days absolute; proposals in config), first and second factor used, coarse User-Agent label, address truncated to /24 (IPv4) or /48 (IPv6), revoked_at + reason.

- Shown on `/account/security` ("Where you're signed in"): this browser first, then others by last seen. **Sign out** one, or **Sign out all other browsers**, after recent authentication (so a stolen session cannot evict the owner without a factor).
- Every revocation path acts on these rows: password change (others), password reset (all), email change (others), recovery completion (all), account deletion (all, reason `account_deleted`, §2.10), user, admin (self-host, host console).
- Revoking a session that is unknown, belongs to someone else, or has already ended (revoked or expired) is **idempotent**: the answer is the same as a successful revocation of an already-ended session ("That browser was already signed out"), nothing is written and no event is raised; the current session cannot be revoked here (`422 current_session`, use Sign out). The answer is identical for another user's session id, so ids reveal nothing. The check runs on every authenticated request, so revocation does not depend on the session driver.
- Personal tokens, CI tokens and agents are not sessions; they keep their own pages.
- Events `SessionRevoked` (reason); sign-in itself is `UserSignedIn` with the session id. No session event is broadcast.

Why in the MVP: three flows already promise "other browsers were signed out" (password change, reset, recovery), A03's emails print `{browser}` and `{network}`, and the post-recovery review needs the data; a small table makes those promises testable and gives a person who suspects a takeover a direct remedy.

### 2.8 Security log and the post-recovery review

- **Security log**: a per-user read model over `audit_log` (schema §7) restricted to an allow-list of action codes; shows what, when, coarse browser and network. Shown on the post-recovery review page; also available as a section of `/account/security` (last 30 days, 50 rows).
- **Post-recovery review** (`/account/security/review`, A03 S-30): available to the recovered user from completion until `lock_until` (7 days), then redirects to `/account/security`. Lists: factors removed (re-enrol actions), agents set to `needs_reconfirm` (RC-4), the user's CI tokens across projects with last use and a revoke action, the security log entries of the recovery, and the RC-3 lock end. Read-only except the revoke actions.

### 2.9 Emailed links: interstitial and lifetimes

**No emailed link changes state on `GET`.** Mail scanners and link previewers open links; opening must never confirm a recovery, change an email, verify an address or consume a single-use token. Every emailed link lands on a page that checks the token without consuming it (expired or used → the error page) and shows one button; the change happens on a `POST` from that page with CSRF. The pages send `Referrer-Policy: no-referrer`, load no third-party resources, and the token path segment is masked in logs (as share tokens, RD-1).

| Link | Lifetime | Use | Notes |
| ---- | -------- | --- | ----- |
| Email verification (sign-up) | 60 min | idempotent (signed URL) | resend allowed every 60 s; never claims a guest session in another browser |
| Email change confirmation (new address) | 60 min | single use | needs a signed-in session of the same account |
| Email change approval (**old** address, accounts without a second factor, EC-1) `/email-change-old/{token}` | 60 min (same `expires_at` as the new-address link) | single use | needs a signed-in session of the same account; the change applies when both halves are pressed |
| Account deletion confirmation (accounts without a second factor, DL-4) `/account-deletion/{token}` | 1 h | single use | needs a signed-in session of the same account; deletes on the button |
| Password reset | 60 min | single use | invalidated by a newer reset request and by an email change |
| Account recovery request (confirm) | 60 min | single use | starts the 72 h wait on the button |
| Account recovery finish (set password) | 24 h | single use | sent at completion; "Forgot password" afterwards |
| Self-host password-set link: admin reset (host console, §2.3), set-email restore (§2.6) and **invite** (§11) (`/set-password/{token}`) | 24 h | single use | proves the mailbox: setting the password marks the email verified; a newer link for the same user invalidates the older |
| Sign-in links in notices (`account-exists`, `key-*`, `account-deleted`, …) | — | — | plain links to `/sign-in` or `/account/security`; they carry no token |

**Complete list of emailed links that carry a token (revision 6, for A10's log scrubbing):** `/verify-email/{id}/{hash}?expires=…&signature=…`, `/email-change/{token}`, `/email-change-old/{token}` (revision 6.2, EC-1), `/account-deletion/{token}` (revision 6.2, DL-4), `/reset-password/{token}` (**no `email` query parameter**: the token alone finds the request, unlike Laravel's default reset URL), `/account-recovery/{token}`, `/account-recovery/finish/{token}`, `/set-password/{token}`. There is no other emailed invite or sign-in link in the MVP. The one non-emailed token URL is `/s/{shareToken}`. No token is ever carried in a `next` parameter (intended URLs live in the session).

### 2.10 Account deletion (revision 6; A03 S-17 `delete`, FL-26)

**Who.** A signed-in user deletes their own account (verified or not, imported or not) from `/account/delete`: tick "I understand this can't be undone", then `DELETE /account` (contracts §3.4). It needs a **fresh confirmation for this purpose (DL-1, revision 6.1)**: S-28 completed with `action=delete_account` in this session within the last **5 minutes**, consumed by the `DELETE` (single use). A recent authentication obtained earlier in the session, even a few seconds before, does not count, so a session cookie copied during the 10-minute window cannot delete without the factor. On a **2FA account the confirmation must have used a second factor** (passkey, security key or TOTP; S-28 runs with `secondFactorRequired`), as for an email change, because deletion is the most destructive action an account has. An account **without** a second factor also needs a confirming link sent to its address (DL-4, PO-N, decision 47; below). OAuth-only accounts confirm with the OAuth round trip (R-11 applies). The host console command `webhooktrap:user:delete <usr_id>` runs the same service for an operator (self-host administrator; hosted operator for a verified legal request), actor `admin`, audited, same refusals. **Guests** have no account: their way out is "End session now" (`POST /session/end`), which already deletes the session's data; `DELETE /account` answers 404 to a guest or anonymous principal. A guest session the user claimed earlier is already part of the user's workspace and goes with it.

**Immediate, no grace period (decided, PO-L, decision 47).** The account stops working at once and its data is purged within 1 hour. Reasons: A03's copy promises "now" and "can't be undone"; a grace period needs a dormant account that can be signed into to undo the deletion, so whoever could delete could also undo, while capture URLs, data and the address sit in an ambiguous state; the protection against a hostile deletion is the second-factor re-authentication, not a delay. **Residual R-12 (accepted, PO-N, decision 47):** on an account without a second factor, whoever holds **both** the password (a fresh session) **and** the mailbox can delete the account and its data irreversibly (DL-4 narrowed it from the password alone); the `account-deleted` email tells the owner. The PO decided against a grace period; the 7-day `pending_deletion` design was dropped and is not kept.
**Accounts without a second factor: the account address confirms too (DL-4, PO-N, decision 47).** On such an account the `DELETE` (after the fresh `delete_account` confirmation: password, or the OAuth round trip, consumed as in DL-1) does **not** delete. It creates an `account_deletion_requests` row (one pending per user; a new request replaces the older) and sends `account-deletion-confirm` to the account address (the current address, never a pending new one) with the link `/account-deletion/{token}`, **1 hour**, single use. The answer is a redirect to `/account/delete` with `flash {code: 'deletion_link_sent'}`; the screen then shows the pending state with a Cancel button (`DELETE /account/deletion`). The link follows §2.9: `GET` shows a page and consumes nothing; the deletion happens on the button's `POST`, with CSRF, **only from a browser signed in to the account that asked**, with the same four page states as the email-change link (`confirm`, `sign_in_required`, `other_account`, `invalid`). A stolen session alone cannot delete (no mailbox) and a mailbox alone cannot (no session). The `POST` re-checks the refusals below (`recovery_lock`, `last_instance_admin`) and that the account **still has no second factor**: an account that enrolled one since gets `invalid`, and its owner uses the immediate path with the factor. The `POST` then runs phase 1 below in the same service, actor `user`. There is **no second fresh confirmation at the link**: the DL-1 confirmation was made at the request. A request closes as `used`, `expired` (hourly job), `replaced`, `user` (Cancel), `recovery` (opening a recovery cancels it) or `email_changed`. **Accounts with a second factor are unchanged:** the fresh 5-minute second-factor confirmation (DL-1) is their gate and the `DELETE` deletes at once, with no link. Self-host: same rule (the instance must send mail, as for invites); the console command is the administrator's own action and needs no link. Test ids are A11's to assign.

**Refusals** (checked before the confirmation and again inside the deletion transaction):

| Answer | When | Note |
| ------ | ---- | ---- |
| `422 recovery_lock` | hosted: a recovery is open, or `now() < lock_until` after a completed recovery (RC-3 extended to deletion) | stops whoever completed a recovery from destroying the account and its evidence within the 7-day review window. The **open** case is normally unreachable: passing S-28 with a second factor cancels the open recovery first (RC-1), and a password-only confirmation on a 2FA account is refused with `second_factor_required` |
| `422 last_instance_admin` | self-host: the user is the only instance administrator who is neither disabled nor deleted | administrators are granted only from the host (TM-19); the console command refuses too unless `--allow-no-admin` |
| `422 second_factor_required` | 2FA account, confirmation without a second factor | S-28 in second-factor mode |
| `422 confirmation_stale` | no confirmation for `delete_account` in this session, older than 5 min, or already consumed (DL-1) | S-28 for `action=delete_account`; the `DELETE` consumes the confirmation inside the transaction under the row lock |
| `422 confirmation_required` | `confirm` is not `true` | the screen prevents it |

**Not refusals:** active runs, pending email change, unverified email, tokens, agents. **Queued and Running runs are aborted** (status `aborted`, reason `account_deleted`; their jobs expire at once; agents are revoked; the runner's next call for such a job gets the job-gone answer of ADR-010 §12 and stops). Refusing would leave the person waiting on runs they cannot always stop (a dead agent, a CI upload), and nothing of the run survives anyway. A self-host B′ server run that already fetched an instance secret finishes its in-flight delivery; its result is dropped.

**Phase 1: tombstone (one transaction, on the request).** Lock the `users` row and re-check the refusals; set `users.deleted_at` (sign-in now fails uniformly and every guard treats the principal as gone); revoke every `user_sessions` row with reason `account_deleted`, **including the current one** (the response ends the browser session and redirects to `/` with the flash in a fresh anonymous session); revoke personal tokens and the CI tokens of the user's projects; revoke every agent of the workspace (reason `account_deleted`) and delete its pairing codes; abort Queued/Running runs and expire their jobs; **delete every share token** (`/s/…` answers not found at once); cancel a pending email change (both links) and close any pending account-deletion request (the one just used included), and delete password-reset tokens for the address; write the `account.deleted` audit row; enqueue the `account-deleted` email with the address, browser label and network **copied into the mail job** (the event carries no address). After commit: `AccountDeleted`, and `agent.revoked` closes agent sockets. From this moment the projects' owner is not live, so ingest stores nothing and answers exactly as for an unknown capture key (§4 invariant), and every project, run and agent page is not found. **DL-2 (revision 6.1): the tombstone is a uniform no-op for every path keyed on the address or on a linked identity, until the purge.** "Forgot password", verification resend, recovery request, OAuth sign-in (by provider subject or by verified email: no new account, no link, the uniform sign-in failure) and passkey assertion all behave as for an unknown account and write nothing visible (no email, no state, no audit row beyond the throttle counters). Ingest's capture-key cache holds owner liveness for at most a few seconds **and** the tombstone transaction evicts the keys of the deleted owner's projects immediately (after commit, the `AccountDeleted` listener in Ingest), so a capture sent right after the `DELETE` stores nothing. Test TM-T157.

**Phase 2: purge (maintenance job, within 1 hour, in batches).** **DL-3a (revision 6.1): the job does not delete the `users` row until the deletion-ledger entry for it is durably written.** The append is idempotent, keyed on the `usr_` id, and is made by the purge job itself before the identity tree and the `users` row are deleted (the `AccountDeleted` listener may also append, at once, but is not relied on): a lost listener or a crash between commit and listener still leaves a ledger entry. A ledger write that fails keeps the row and raises the purge-late alert. Test TM-T158. Then hard-delete the workspace tree (projects, including soft-deleted ones → capture URLs, captures, bodies, capture access log, runs and their children, fixture metadata, CI tokens; agents and their children), then the identity tree (identities, WebAuthn credentials, TOTP, recovery codes, second-factor failures, recovery requests, email change requests, account deletion requests, sessions, personal tokens, password-set tokens), then the `users` row. After the purge the address is free: a new sign-up with it is a new user (new `usr_` id, new WebAuthn user handle, nothing inherited, no recovery cool-down inherited). Until then a sign-up with that address gets the normal uniform answer, and no `account-exists` email is sent to a tombstoned account.

**Cascade per module.**

| Module | Data | Phase 1 (at once) | Phase 2 (purge) |
| ------ | ---- | ----------------- | --------------- |
| Identity | sign-in sessions | all revoked (`account_deleted`) | deleted |
| Identity | personal tokens | revoked | deleted |
| Identity | WebAuthn credentials | unusable | deleted. The authenticator keeps its passkey (the server cannot reach it; a browser may still offer it, and the sign-in fails uniformly). Calling the WebAuthn Signal API to hide it is post-MVP |
| Identity | TOTP, recovery codes, second-factor failures | unusable | deleted |
| Identity | linked GitHub/Google identities | unusable | deleted; the provider account can then be linked to another account |
| Identity | pending email change, pending deletion request, reset tokens | cancelled / closed / deleted | deleted |
| Identity | recovery requests | open ones refused above | deleted |
| Identity | guest sessions it claimed | — | `claimed_by_user_id` set NULL (existing FK) |
| Workspace | workspace, projects | owner not live: every page not found | deleted |
| Capture | capture URLs, captures, bodies, access log | ingest stores nothing, same answer as an unknown key | deleted. Keys are not kept: `project_key`/`capture_key` non-reuse holds by randomness (≈ 59 and 71 bits), not by a stored list |
| Proving | runs, scenarios, deliveries, findings, readings, jobs, event inbox | active runs aborted, jobs expired | deleted |
| Sharing | share tokens | deleted | — |
| Agent | agents, mirrors, access tokens, pairing codes | agents revoked (`account_deleted`), sockets closed, codes deleted | deleted. The agent machine keeps its key pair; its next connection is refused and it reports that it is not paired |
| Fixtures | fixture metadata | — | deleted |
| Projects' CI tokens | | revoked | deleted |
| Instance (self-host) | admin flag, B′ permission | gone with the user | allow-list entries and instance secrets the user created stay, `created_by` set NULL |
| Audit | `audit_log` rows by or about the user | kept | **kept for the audit retention (1 year)**: `actor_user_id`/`subject_user_id` become NULL, `subject` keeps the `usr_` id as text, email addresses inside `before`/`after` are replaced by their masked form (`m***@b***.example`); the `account.deleted` row stores `email_hmac` (HMAC-SHA-256 with a server key), never the address, so support can answer "was an account with this address deleted?" |
| Security log | read model | — | nothing selects it any more |
| Rate-limit keys, caches | Redis/Valkey | — | expire on their own (≤ 24 h) |
| Outbound mail | `account-deleted` job | sent once | job payload gone after sending; failed-job rows purged after 7 days (A10) |

**Backups (HD-4): what "deleted" means.** From phase 1 nothing of the account is reachable through any product surface, API, agent or share link; after phase 2 (≤ 1 h) nothing remains in the live database. Copies remain only inside the encrypted backups: the pgBackRest point-in-time window keeps base backups and **WAL for 7 days**, so for 7 days a point-in-time restore to a moment before the deletion would contain the account; the encrypted off-server copies keep it for at most **14 days**. Backups are never read, queried or restored for a single account. **Restore rule:** each deletion appends `(usr_ id, deleted_at)` (no address) to a **deletion ledger kept outside the database**, next to the backup repository (A10), and the restore runbook re-applies the purge for every ledger entry later than the restore point **before the app, the workers and the queues start** (restored rows hold live sessions, tokens and agents). The off-server copy of the ledger, its retention (at least as long as the oldest backup, 14 days plus margin) and the startup ordering are A10's (DL-3b, DL-3c); the app side is DL-3a above. After 14 days no copy remains anywhere. The privacy notice states the 1-hour purge and the 14-day backup tail (A10, PO).

**Event and email.** `AccountDeletionRequested` {user id, request id; never the address} on accounts without a second factor: email `account-deletion-confirm` (DL-4); `AccountDeletionCancelled` {user id, request id, reason}. `AccountDeleted` {user id, actor `user | admin`, counts}; never the address. Email `account-deleted` to the account's address at deletion time (not to a pending new address), with the request's browser label and truncated network; an admin deletion uses a variant without browser and network.

---

## 3. Workspace

**Workspace** — id `wsp_`, exactly one of `user_id` / `guest_session_id` (DB `CHECK`, R61). One per principal in the MVP.

**Project** — id `prj_`, `project_key` (10 chars, base62 random, not derived from anything), name, workspace, settings (capture retention, run retention, default run visibility = private, budgets view), `last_agent_id`, counters `next_capture_seq`, `next_run_number`, daily capture count and stored bytes for the account quota (TM-02).

Invariants:
- Guest workspace: exactly one project (name "Guest session").
- `project_key` is unique and never reused.
- Capture `seq` and run `number` are allocated from the project's counters under a row lock: never reused after deletion or retention (IA §2.3).
- Account projects have a capture quota (captures per day, stored bytes); over quota, ingest answers identically and stores nothing (ADR-005 amendment 1 §5).
- `project_key` is never a capability: every route checks the owner policy (TM-13).

---

## 4. Capture

**CaptureUrl** — `capture_key` (12 chars base62, ~71 bits), project, state `active | rotated`, rotated_at. One active per project (P9). Rotating creates a new active key; the old key keeps answering the ingest invariant but stores nothing.

**Capture** (aggregate root) — id `cap_`, project, `seq`, received_at (server clock, microseconds), request facts (method, raw_target, path, raw_query, ordered headers with credential values as `[redacted · N bytes]`, header_fidelity, public_origin + source, forwarded facts, source IP from the trusted-proxy chain, content type, body size, body SHA-256, body_kept), derived facts (provider, provider event id, event type, API version, object ids, group key, shape), signature verdict (state, reason, checks, checked_by agent, checked_by job, checked_at).
**CaptureBody** — the raw bytes, 1:1, separate storage.

Invariants:
- Request facts are immutable after insert. Only derived facts and the signature verdict change.
- `body_sha256` is always set, even when `body_kept = false`.
- A capture is stored only when the capture URL is active, the project's owner is live, and the guest quota, the global guest budget or the account project quota allows; otherwise the request is answered identically and dropped (ADR-005 amendment 1 §4–§5).
- The signature verdict is written only by **the agent a verify job was issued to, once per job** (TM-36), or by the self-host engine with instance secrets (SIG-1); hosted servers never compute it. The UI names the agent that checked.
- Capture content is untrusted: rendered as text only, raw download with safe headers (ADR-005 amendment 1 §7).

Derivations (provider detection, event type, object ids, group key GRP-1..4, shape SHP-1) run in a queued job after insert, from pack data. They are pure functions of (headers, body, pack version) and are re-runnable when a pack updates.

Ingest pipeline: edge rate limit → resolve key (cache; **owner liveness cached at most a few seconds and evicted by the deletion tombstone, DL-2**) → read body up to hard limit, hash → if storable, insert capture + body in one transaction → after commit: dispatch `CaptureReceived` → derivation job → broadcast. Persistence never depends on Reverb (ADR-004).

---

## 5. Agent

**Agent** (aggregate root) — id `agt_`, workspace, name (machine name, unique per workspace among active agents), public key (Ed25519, one per server origin on the agent side) + fingerprint, version, OS/arch, proto, state (`connected | disconnected | revoked`), `needs_reconfirm` (true at pairing until the local `[y/N]`, and again after a claim, TM-42), confirmed_at, last_seen_at, paired_at, revoked_at + reason (`user | replaced | session_ended | admin | claimed_rekey | unconfirmed | account_deleted`), guest slot flag.
Mirrors reported by the agent (display only, the server cannot change them): secret **names** per provider (no last-4 hint, S-8/PO-D), declared targets and their approvals, probe config hash. Target hosts are shown to the owner only, never on share views.

**PairingCode** — code HMAC (10 symbols, keyed server-side, S-1), principal, workspace, expires_at, used_at, failed attempts. One live code per principal.

**AgentAccessToken** — hash, agent, expires_at (15 min).

Invariants:
- Guest workspace: at most one non-revoked confirmed agent (partial unique index + replacement in the confirmation transaction). Account workspace: at most 20 active agents (TM-43).
- An agent with `needs_reconfirm` receives no jobs and fetches no captures. **An agent still unconfirmed 10 minutes after `pair` is revoked with reason `unconfirmed`** (RE-1); before its first confirmation the server stores no mirrors (secret names, targets, probe hash) and returns no job or check counts.
- **An agent reads only captures referenced by an unexpired job issued to it** (TM-35); access is logged per agent.
- An agent writes observations, final documents, accepts and declines only for runs and jobs assigned to it (TM-37).
- An agent never holds or sends secret values; the server rejects any agent state payload containing a field outside the schema.
- Revocation is immediate for new requests; open sockets are closed via `agent.revoked`.

Lifecycle: `paired (needs_reconfirm) → confirmed → connected ⇄ disconnected → revoked` (`paired → revoked(unconfirmed)` after 10 min without confirmation). Claim (ADR-003 amendment 1) moves the agent to the user's workspace and sets `needs_reconfirm` again (approvals cleared, guest flag cleared only after confirmation).

**Runner** (instance-level, not in a workspace) — id `rnr_`, name, token hash, last 4, allowed source CIDRs (required on hosted, RN-1), token issued_at (rotation, RN-2), version, last_seen_at, revoked_at. Pulls server-run jobs only (ADR-010 §12). Same capture-fetch rule as agents: only captures of jobs issued to it.

---

## 6. Proving (server side of runs)

The engine owns the rules; this module owns storage, quotas, job dispatch and projection.

**Run** (aggregate root) — id `run_` (UUIDv7; engine-created for CLI/CI uploads), project, `number`, source (`web | cli | ci`), executor (`agent | server_public | server_allowlist`), agent or runner (if any), initiator (user, guest session, CI token), **provenance** (executor + source + agent name, CI token name or "Webhook Trap server runner", shown on every view, TM-17), `remote` flag (started from browser, API or MCP; ADR-010 §6.3), selection (capture refs + SHA-256, provider, ≤ 5 events, DEF-1), scenarios, target (display parts; for agent runs, a target the agent approved), pack id + version, seed, plan hash, plan (hosted profile), status + reason + exit code, fail_on, counts, quota_counted flag, visibility, timestamps, `last_seq`, final run document (hosted profile).
Children (inside the aggregate, same transaction boundary):
- **RunScenario** — position, name, result (SCN-1), reason, reading text (display-only, PV-6).
- **RunDelivery** — `n`, scenario, source capture, mode, offset, signing kind, tamper (path + display from/to), status/transport, latency, over_budget, response hash/size/type, state (per-entry outcomes + display values), result, completed. Written once when completed; immutable afterwards (ADR-006 `ReplayAttempt` rule).
- **Finding** — `n` (final at run end, FND-6), type (§5.2), fingerprint, severity, level, event type, scenarios, evidence `[{d, role}]`, likely-cause rule id, structured `params` and `measured` (display values). Title, what, why, fix, snippet, likely-cause text and citation are **rendered by the server** from `type` + the run's pack version; nothing agent-authored is displayed (TM-16).

**RunJob** — the instruction for an executor: run id, job id, kind (`run | verify`), assignee (an agent, or the runner pool), selection refs, scenarios, target ref, pack ref, fail_on, seed, expires_at. Created at Queued; accepted once; assigned to a runner at accept for server runs. Captures it references are the only ones its executor may fetch.

Run lifecycle (server view):

```
Draft ──(Run pressed / CLI start / CI upload)──▶ Queued ──(agent accepted)──▶ Running ──▶ final
  │                                                │                                      │
  │ (CI upload arrives already final) ─────────────┼──────────────────────────────────────┤
  │                                                └─(job expired, agent lost)──▶ Aborted (agent_lost)
final ∈ { Couldn't start, Aborted, N findings, Inconclusive, Passed }   (RUN-1)
```

Invariants:
- Status, reason, counts and exit code come from the engine's verdict; the server checks **Level 1 consistency only** (schema validation, count recompute from `findings[]`, exit code vs status and `fail_on`, Level 1 observations vs Level 1 results). No Level 2 rejudge is possible or claimed (S-5).
- The final document is first-write-wins: same hash → no-op, different hash → refused (TM-37, TM-64).
- `number` allocated at Queued (or at upload for CLI/CI runs); drafts are not numbered.
- A run counts toward the guest quota (A-13) when the engine reports it got past Once; the counter is incremented once, idempotently (`quota_counted`). Independently, every `RunAccepted` increments the guest's accepted-run counter (ceiling 20).
- Observations arrive with a strictly increasing per-run `seq`; duplicates are ignored, gaps are accepted (the final document is authoritative).
- Display values in deliveries, scenarios and findings pass the PV-2/PV-5 validator (PV-11) before storage.
- **Server runs are Level 1 only** (C-8, ADR-010 §12.5): no probe, no reset, `levels = {1}`; on hosted no signing secret, so SIGN-2 applies. A server-run final document with Level 2 content is refused.
- Server-side runs require an account initiator (PO-B) and the kill switch on (HD-10); server-side private targets additionally require the B′ permission and B′ enabled (ADR-006 amendment 1, PO-G).
- Remote agent runs target only targets the agent declared locally (ADR-010 §6.3).

Draft is a page state, not a stored row: the draft preview calls `webhooktrap plan --json` (network-free, rate-limited per principal, cached) with the selection and returns delivery count, `n/a` reasons and refusals (CAP-1, DEF-1, SIGN-2 "Needs secret").

---

## 7. Judge (engine, Go)

Not a server module. Listed so ownership is explicit:

| Concept | Owner | Server sees |
| ------- | ----- | ----------- |
| Plan, scenario rules, gaps, caps, signing, tamper | engine `plan` | stored plan (hosted profile) |
| Transport, guard, probe, reset, scheduler, clock | engine `execute` | observations |
| Findings, severity, merge, numbering, run status, exit code, likely cause | engine `judge` | verdict |
| Text, JSON, PR Markdown | engine `render` | run document; web renders its own HTML and finding prose from structured fields + pack templates |
| Signature diagnosis on captures (SIG-1) | engine `verify` | verdict per capture |
| Fixture scrubbing (SCR) | engine `scrub` | fixture metadata only |

---

## 8. Packs

Versioned data files (`packs/<provider>/<version>.json`, JSON Schema, D-8): budget + source tag, signing scheme, tolerance, signature headers, duplicate/retry header rules (DUP-1, with verification status; unverified values don't ship, DUP-2), `tamper_paths`, `objects`, `link_paths`, anchor priority, `status_events`, scrub field paths, id patterns, citations per finding type, finding text templates and fix snippets per framework (rendered by the server, TM-16), re-send header allow-list (TM-51). MVP: Stripe, GitHub, Shopify, Standard Webhooks (A-18); Twilio and Slack listed as "coming" with no budget.

The server loads the same files (draft display, citations, budgets settings page, scrub-on-share). The engine embeds them. A run records `pack {id, version}`.

---

## 9. Fixtures

**FixtureMeta** — project, repo path (`webhooktrap/fixtures/<provider>/<event type>.json`), provider, event type, API version, body SHA-256, shape, `signature: resign`, sync state (`in_sync | drifted | missing`), last seen by agent, at. Contents never stored hosted (IA6) unless a future upload feature is approved.

Drift (SHP-4) is computed server-side from new captures' shapes against the fixture's shape.

---

## 10. Sharing

**Run visibility** — attribute of Run: `private` (default, P2) | `link`. Share options: `bodies_hidden` (bool), `include_probe_values` (owner opt-in, PV-7; unavailable for guest runs). CI uploads are private unless `--share` (PO-C).

**ShareToken** (PO-A, TM-15) — a separate random token (≥ 128 bits, stored hashed) is the share capability; the run id alone never grants access. Sharing issues a token; unsharing deletes it; **resharing issues a new token**, so old links stop working. Claim voids guest share tokens.

**Shared by** — the owner's chosen display name, which the owner sees in the share dialog before sharing.

Rendering rules for a shared view:
- Bodies of source captures: shown only for JSON fields not matched by scrub rules (pack field paths + SCR-1 value patterns); secret-prefix matches always hidden; `bodies_hidden` hides them all (P2).
- Probe display: names, small counts, labels; `show:` values only with the opt-in (PV-7).
- No capture URL, no secret names, no target host unless loopback. Provenance (executor, source, agent or CI token name) is shown (TM-17).
- Finding prose comes from server templates; free strings are escaped and bounded (TM-16).
- Share pages send `Referrer-Policy: no-referrer` and `noindex`; partial reloads never return owner-only props (TM-14).
- Guest shared runs die with the session.

Scrubbing for share views is a render-time transform in PHP over stored data; nothing scrubbed is stored. The rule data comes from packs; a shared corpus of fixtures keeps the PHP and Go scrubbers consistent (contract test).

---

## 11. Instance (self-host)

**InstanceSettings** — sign-up policy (open | invite | closed), guest mode on/off, guest limits, global guest storage budget, account capture quotas, body kept limit, retention defaults, run delivery cap (≤ 50, A-21), server replay (public on/off = kill switch; private destinations off by default + allow-list), server-run limits, `probe_display` policy (PV-12), instance secrets (encrypted, write-only, optionally bound to allow-list entries).
**AuditEntry** — actor, action, before/after (secret values never), at. Includes B′ permission grants, allow-list changes and every instance-secret use (TM-91).

Invariants:
- **B′ and open sign-up are mutually exclusive**: `server_replay_private` implies `signup_policy <> 'open'`, enforced on both sides (PO-G).
- B′ runs and instance secrets require the user's `can_private_replay` permission.
- Allow-list entries name a port or range; CIDR breadth ≤ IPv4 /16, IPv6 /48; no overlap with instance services, metadata or link-local.
- Instance admin is granted only from the host (console command or one-time setup token), never by first sign-up (TM-19).
- **Invite-only sign-up (revision 6).** With `signup_policy = 'invite'`, `/register` is refused exactly as with `closed`; there is no invite token on `/register`. An instance administrator invites from `/admin/users` (`POST /admin/users/invites {email}`, recent authentication) or the host console (`webhooktrap:user:invite <email>`): the service creates the user unverified and without a password, and emails a **password-set link** (`/set-password/{token}`, 24 h, single use, §2.9). Setting the password proves the mailbox, marks the email verified and signs the person in. Inviting an address that already has an account sends nothing and answers the same ("Invited"). Resending issues a new token and voids the old one. An invited user who never sets a password is listed in Users with status Invited and can be disabled or deleted (host console). This is the **only** invite path in the MVP and its link is the password-set link; no other emailed invite link exists.

Hosted: none of these are editable through the UI; the values are compiled or env-configured.

---

## 12. Retention and quotas (cross-cutting)

| Data | Guest | Account (default, PO-4 approved) | Mechanism |
| ---- | ----- | -------------------------------- | --------- |
| Everything of a guest session | 24 h from creation; access stops at expiry; hard delete within 15 min after | — | `maintenance` queue job every minute: delete expired workspaces (cascade) |
| Captures + bodies | session | 30 days per project (configurable) | daily job deletes by `received_at`; bodies with them |
| Runs, deliveries, findings, readings | session | 90 days | daily job; deletion removes readings (PV-13) |
| Pairing codes | 10 min | 10 min | deleted after 24 h |
| Agent access tokens | 15 min | 15 min | deleted after expiry |
| Revoked agents | with session | 90 days after revocation | |
| Audit log | — | 1 year (self-host configurable) | rows about a deleted account keep the `usr_` id as text, addresses masked (§2.10) |
| Deleted accounts | — | live DB: purged ≤ 1 h after deletion; backups: ≤ 7 days (PITR WAL), ≤ 14 days (off-server copies) | §2.10; deletion ledger outside the DB re-applied after any restore |

Probe display values never enter analytics, telemetry, error reporting or info-level logs (PV-13).

Rate limits live in Redis/Valkey through the RateLimit adapter; guest storage, run, accepted-run and account capture quota counters are authoritative in PostgreSQL (atomic update with a `WHERE counter < limit` guard).

---

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §4: verdict only by the agent a verify job was issued to, once per job; untrusted rendering | A09 §9.6, TM-36, TM-06 |
| 2026-11-14 | 2 | §5: job-scoped capture fetch; `needs_reconfirm`; 20-agent cap; `claimed_rekey` reason aligned; no last-4; Runner entity | §9.6, TM-35, TM-37, TM-42, TM-43, S-8; HD-5 |
| 2026-11-14 | 2 | §6: Level 1 consistency only; provenance; server-rendered prose; first-write-wins; accepted-run counter; server runs accounts only | §9.6, S-5, TM-16, TM-17, §7.3; PO-B |
| 2026-11-14 | 2 | §10: share token, rotation on reshare, "Shared by" display name, CI private unless `--share` | §9.6, TM-15; PO-A, PO-C |
| 2026-11-14 | 2 | §11: B′ permission, exclusivity with open sign-up, allow-list rules, admin bootstrap from host | §9.6, TM-19, TM-72; PO-G |
| 2026-11-14 | 2 | §2–§3: claim nonce, token abilities and prefixes, guard list, account capture quota | TM-02, TM-12, TM-20, TM-60, TM-111 |
| 2026-11-14 | 2 | §1, §12: Redis or Valkey; retention 30/90 approved | HD-8; PO-4 |
| 2026-11-14 | 3 | §2: User gains password, WebAuthn, TOTP, recovery codes, legacy marker; claim after the second factor, in the same browser; guests have no WebAuthn | decision 44 |
| 2026-11-14 | 3 | §2.1 new: methods and factors, 2FA state, recent authentication, last-method rule, limits | decision 44 |
| 2026-11-14 | 3 | §2.2 new: WebAuthn credentials, RP ID = app host, capture host and share pages excluded, ceremony parameters, attestation `none`, counters, backup flags, naming and revoking | decision 44 |
| 2026-11-14 | 3 | §2.3 new: recovery codes; recovery when all factors are lost (self-host console; hosted delayed email, PO-J) | decision 44 |
| 2026-11-14 | 3 | §2.4 new: one-off import of the 3 1.x accounts; options A/B, B recommended (PO-H); pre-hijack rule; legacy hash deadline | decision 41 |
| 2026-11-14 | 3 | §6: server runs Level 1 only | A01 C-8 |
| 2026-11-14 | 4 | §2.1 and package-wide: IPv6 per-address limits keyed on /64 | A09 §12.1 gap, S-1 |
| 2026-11-14 | 4 | §2.1: per-account second-factor failure counter across challenges (20 per 24 h, hold 1 h, email) | WA-3, TM-122 |
| 2026-11-14 | 4 | §2.1: `IdentityLinked` email notice and audit; OAuth `state` bound to the session, PKCE | WA-5, TM-124 |
| 2026-11-14 | 4 | §2.2: counter rule per spec (either count non-zero); `userHandle` must match credential owner, flagged credentials refused; `frame-ancestors 'none'`; `Permissions-Policy` stated as defence in depth | WA-1, WA-2, WA-4, TM-120, TM-121, TM-123 |
| 2026-11-14 | 4 | §2.3: recovery conditions RC-1..RC-6, request columns, residual R-7 | RC-1..RC-6, PO-J, TM-125, TM-126, R-7 |
| 2026-11-14 | 4 | §2.4: import never reads hash fields; option B stated as recommendation pending PO; option A and legacy columns dropped; passkey not a standalone activation | PO-H, R-M |
| 2026-11-14 | 4 | §5: unconfirmed agents expire at 10 min (`unconfirmed`), no mirrors or counts before confirmation; runner CIDR required on hosted, rotation | RE-1, RN-1, RN-2 |
| 2026-11-14 | 5 | §1: EngineProcess calls the separate `engine` service over a socket; output untrusted | A09 §12.9 ENG-1/ENG-2; A10 compose-profile §2 |
| 2026-11-14 | 5 | §2.3 RC-1: a passkey sign-in counts as completing a second factor; `/confirm-auth` with a factor too | A09 §12.9 RC-1 |
| 2026-11-14 | 5 | §2.1: recent authentication covers unblock, connect/disconnect, sessions, account deletion; email change on 2FA accounts needs a second factor; OAuth re-auth for OAuth-only accounts; flagged keys never confirm | A03 S-28; A09 to re-check |
| 2026-11-14 | 5 | §2.1: password policy (12–128, common-password list, no network call; bcrypt 72-byte limit); password change revokes other sessions | A03 S-17B `password`, S-24 |
| 2026-11-14 | 5 | §2.2: unblocking a flagged credential (another method, sign count kept, refused after a second flag) | A03 S-17B `blocked`, FL-23 |
| 2026-11-14 | 5 | §2.3: interstitial before the wait starts, `declined`/`link_expired` without cool-down, password cleared at completion, finish link 24 h **[PO-J]** | A03 S-29, AC-21.4; RC-5 reading for A09 |
| 2026-11-14 | 5 | §2.5–§2.9 new: connect/disconnect identities; email change; sign-in sessions in the MVP; security log and post-recovery review **[PO-J]**; emailed-link interstitial and lifetimes | A03 S-17A/B, S-25, S-26, S-29, S-30, S-31 |
| 2026-11-14 | 5.1 | §2.5 and §2.3 RC-3: removal lock covers only identities linked before the recovery request; later links are removable and the notice says a recovery is open or recent | A09 §12.10 AR-1 |
| 2026-11-14 | 5.1 | §2.1: password reset on a 2FA account still goes through the 2FA challenge; `reset_link` is never a second factor nor recent authentication | A09 §12.10 AR-2 |
| 2026-11-14 | 6 | §2.10 new: account deletion (who, second-factor re-authentication on 2FA accounts, immediate with purge ≤ 1 h, refusals `recovery_lock`/`last_instance_admin`/`second_factor_required`, active runs aborted not refused, tombstone and purge phases, cascade per module, audit retention with masked addresses and `email_hmac`, backups and the deletion ledger, event and email, guests); residual R-12 | A03 S-17 `delete`, FL-26, O-SS-9 (a); PO-L; A09 to re-check |
| 2026-11-14 | 6 | §2.1: throttle values per pair, account, address (/64), with per-email keys counted for unknown addresses; argon2id only, parameters, boot check; bcrypt fallback and 72-byte limit withdrawn | O-SS-9 (b), (d) |
| 2026-11-14 | 6 | §2.6: link-page states, intended URL kept in the session; what the old address's owner can do after an unwanted change (R-10), self-host console restore, revert link proposed to A09 | O-SS-9 (f), (h); R-10 |
| 2026-11-14 | 6 | §2.7: idempotent revocation of unknown, foreign or ended sessions; `account_deleted` reason | O-SS-9 (e) |
| 2026-11-14 | 6 | §2.9: password-set link covers invites and set-email restore; complete list of emailed token links for A10 | A03 S-24, S-32; A10 log scrubbing |
| 2026-11-14 | 6 | §5: agent revoke reason `account_deleted`; §11: invite-only sign-up through admin invites and the password-set link; §12: deleted-account retention row | O-SS-9 (a); A03 S-20, S-21, S-24 |
| 2026-11-14 | 6.1 | §2.1 and §2.10: account deletion needs a purpose-bound `delete_account` confirmation within 5 min, single use, `422 confirmation_stale`; DL-4 (email-confirmed deletion without 2FA) recorded as PO decision pending | A09 §12.11 DL-1, DL-4 |
| 2026-11-14 | 6.1 | §2.10: tombstone is a uniform no-op for reset, resend, recovery, OAuth, passkey; ingest key cache evicted at once (DL-2); purge job does not delete the row before the ledger entry is durable, ledger re-applied before web, workers and queues (DL-3 app side) | A09 §12.11 DL-2, DL-3 |
| 2026-11-14 | 6.1 | §2.1: no password check during a hold; `/confirm-auth` failures count toward account limits; uniform hash cost for unknown, password-less and wrong-password cases | A09 §12.11 TH-1 |
| 2026-11-14 | 6.1 | §2.6: self-host `set-email` restore removes factors and identities added after the disputed change (`--keep-factors` opts out); revert link R-AE rejected; EC-1 recorded as PO decision pending | A09 §12.11 SE-1, R-AE, EC-1 |
| 2026-11-14 | 6.2 | §2.10: PO-L (immediate, no grace period) and PO-M (hosted support never restores; writes to the address only) stated as decided; R-12 accepted; DL-4 designed: on accounts without a second factor `DELETE /account` sends `account-deletion-confirm` (`/account-deletion/{token}`, 1 h, single use, `GET` page / `POST` effect, signed-in same account), re-checks refusals and "still no second factor", cancel via `DELETE /account/deletion`; 2FA accounts keep the 5-min second-factor confirmation (DL-1) | decision 47 PO-L, PO-M, PO-N, DL-4 |
| 2026-11-14 | 6.2 | §2.6: EC-1 designed: on accounts without a second factor the old address must also confirm an email change (`email-change-approve`, `/email-change-old/{token}`, 60 min with the same `expires_at`, `POST` from the signed-in account, either order, states `both`/`old`/`new`); R-10 narrowed; revert link R-AE stays rejected (PO-O) | decision 47 PO-O, EC-1 |
| 2026-11-14 | 6.2 | §2.9: lifetime rows and the emailed-token list gain `/email-change-old/{token}` and `/account-deletion/{token}` (for A10's log scrubbing) | decision 47 |
