# A09 security review · Slice 1 (Identity and guest sessions)

**Date:** 2026-11-15 · **Reviewer:** A09 · **Branch:** `phase5/foundation`
**Commits read (code, not the author's report):** `35c39fe`, `55822d2`, `3255884`, `11661dd`.
**Read against:** threat-model §9–§12 (TM-*, TH-1, AR-1/AR-2, RC-1..RC-6, DL-1..DL-4, EC-1, WA-*), domain-model §2, schema §2/§7, contracts §3.4, decisions 44–47.
**Suite:** 192 tests pass. Six extra proof tests were written locally (they assert the secure behaviour and all six fail as predicted); they are kept outside the repository with the private notes, not committed.

Exploit detail and reproduction steps are in the private notes, not here.

## Verdict

**SHIP WITH FIXES.** One High finding (pre-account-takeover) must be fixed before any real sign-up is possible; the Medium ones before public sign-up opens. Nothing here is a design reversal.

## What is right

- **Hashing.** `hashing.driver` is hard-coded `argon2id` and tested against an env override; boot refuses without `PASSWORD_ARGON2ID`; 64 MiB / t=4 / p=1 defaults; rehash on login.
- **Uniform hash cost (TH-1c).** Unknown, password-less and tombstoned accounts burn an argon2id check against a cached dummy hash made with the current parameters.
- **Holds before hashing (TH-1a)** at `/sign-in/password`; per-email keys are HMACs of the normalised email, counted for unknown addresses; IPv6 keyed on /64; network stored as /24 and /48.
- **Session fixation.** Session id migrated at sign-in, at the start of the 2FA challenge, at claim and at password change; `__Host-wt_session` is Secure, HttpOnly, Lax, Path=/, no Domain; JSON serialisation.
- **Guest sessions.** 256-bit token, stored as SHA-256, cookie only (`__Host-wt_guest`, never URL or header), fixed 24 h, liveness checked on every lookup; `POST /new` never replaces a live session; claim is one conditional `UPDATE` on a single-use nonce, after any second factor, never from a reset or verification link in another browser.
- **OAuth.** Session-bound `state` (Socialite), PKCE on, purpose kept in the session, `link`/`confirm` bound to the account that started the flow, provider-subject match for confirmation, `prompt=login&max_age=0` for Google confirmation, unverified provider email never links or creates, notice and audit on every link (WA-5).
- **TOTP.** RFC 6238, ±1 step, last-used step stored and advanced by a conditional update (no replay, no race), secret encrypted at rest.
- **Recovery codes.** 10 × 60 bits, single use by conditional update, notice with count left.
- **Recent authentication and DL-1.** 10-min window in the server session with the method; purpose-bound 5-min `delete_account` confirmation, single use; second factor required for email change and deletion on 2FA accounts.
- **Sessions.** `user_sessions` checked on every authenticated request, cache evicted on revoke; idempotent revoke; revoke-others behind recent auth.
- **Email change.** EC-1 implemented as specified (both halves, same expiry, signed-in same account, masked address, RC-3 and uniqueness re-checked under a row lock).
- **Delayed recovery.** 72 h, RC-1 on sign-in and on `/confirm-auth` with TOTP, RC-2 extension, RC-3 lock incl. AR-1 (only identities linked before the request are shielded), RC-5 one open request and cool-down only after a confirmed cancel, password cleared at completion, finish link single use and never recent auth.
- **Deletion.** Tombstone in one transaction under a row lock with refusals re-checked; DL-2 uniform no-op on reset, resend, recovery, OAuth and sign-in; DL-4 link for accounts without 2FA, re-checked at the button; ledger append before the purge deletes the row (DL-3a).
- **Emailed links.** `GET` page / `POST` effect everywhere; tokens 256-bit and stored hashed; reset URL carries no email; `no-referrer` and `no-store` on link pages; intended URL kept in the session, never in `next`; `safeNext` refuses absolute and protocol-relative targets.
- **1.x import.** Lexical scanner decodes only allow-listed keys; hash fields are skipped as raw bytes, never decoded, stored or logged; checksum required; counts only on output.
- **No mass assignment from requests**; no token, code or address written to logs by the module.

## Findings

| # | Sev | Finding | Where |
| - | --- | ------- | ----- |
| F1 | **High** | **Pre-account-takeover.** An unverified account can sign in by password (the registration deviation only withholds the session at `POST /register`) and, unverified, enrol TOTP and link an OAuth identity (`/account/security/*` is `auth:web` + `recent-auth`, no verified check). The first proof of mailbox ownership by someone else then keeps what the registrant set: the verification link confirmed in another browser keeps the password and factors (`proven('link')` never discards); a password reset keeps factors and identities (`proven('reset')` discards only for 1.x imports); an OAuth email match clears password and factors but keeps linked identities and the registrant's live sessions. Domain §2.4 requires every credential set before verification to be discarded. Proven by tests F1a–F1c | `app/Modules/Identity/Application/EmailOwnership.php:28`; `Application/PasswordSignIn.php:57`; `routes/identity.php:74-89`; `Http/Controllers/VerifyEmailController.php:52` |
| F2 | Medium | **Throttles are check-then-act.** Holds and counters are read before the expensive check and incremented after it (sign-in: ~300 ms of argon2id between check and increment; 2FA: per-challenge attempts live in the session, last write wins, and the per-account WA-3 hold is read before verifying). Concurrent requests therefore exceed the 5 / 20 limits by roughly the server's concurrency per window. The 2FA endpoints have no per-address limit at all | `Application/PasswordSignIn.php:32-40`; `Application/SecondFactorChallenge.php:91-110`; `Http/Controllers/TwoFactorChallengeController.php` |
| F3 | Medium | **OAuth email-match links before the 2FA challenge.** On an account with 2FA, a sign-in by email match creates the `user_identities` row (with `linked_at` = now) before the challenge, so a mailbox holder adds a sign-in method without passing 2FA. If done before a recovery request, the identity predates the request and the RC-3 removal lock shields it for 7 days, reopening the AR-1 gap | `Application/OAuthFlows.php:122` |
| F4 | Low | **`XSRF-TOKEN` cookie has no `__Host-` prefix** (A06's report confirmed); TM-T4 says app cookies carry it. The value is encrypted, so tossing gives no CSRF bypass, only breakage; still a spec gap | Laravel `PreventRequestForgery` default; no override |
| F5 | Low | **TM-T11 is untested.** Laravel skips CSRF checks under unit tests; no test enables them, so "cross-site `POST /new` without token fails" is not verified | `tests/` |
| F6 | Low | **Common-password list fails open** when the file is missing; the bundled list has ~100 entries instead of ~100 000 | `Domain/PasswordPolicy.php:64` |
| F7 | Low | **`/confirm-auth` ignores the per-account sign-in hold.** Failures count toward the 20/h key (TH-1b) but a password is still verified during that hold, so a stolen cookie keeps its own budget (10 per 15 min) | `Http/Controllers/ConfirmAuthController.php:86` |
| F8 | Low | **Recovery codes hashed with unsalted, unkeyed SHA-256.** 60-bit codes are fine online; with a database dump the shared hash space allows multi-target offline search. Key the hash (HMAC with a key outside the database) or bind it to the user id | `Domain/RecoveryCodeGenerator.php:39` |
| F9 | Low | **`email_hmac` uses `APP_KEY`.** §12.11 R-Y requires its own key, not `APP_KEY`, kept out of the database and backups | `Application/AccountDeletion.php:163` |
| F10 | Low | **Argon2id cost has no floor.** `ARGON_MEMORY`/`ARGON_TIME` are env-overridable without a refusal below OWASP's 19 MiB / t=2 (domain §2.1 "never below") | `config/hashing.php:52` |
| F11 | Low | **Session payload unencrypted** (`SESSION_ENCRYPT` defaults false, database driver) while it holds the TOTP setup secret, fresh recovery codes (flash), the claim nonce and the challenge state | `config/session.php:50` |
| F12 | Low | **A recovery request link survives an email change** made between the request and the confirmation; it stays confirmable from the old mailbox | `Application/AccountRecovery.php:82` |
| F13 | Info | Tokens in queued mail payloads (`actionUrl`) reach `failed_jobs` on a mail failure; A10's 7-day purge covers it. Deletion ledger is in-database only until A10's DL-3b/c shipping lands (go-live gate, already tracked). Phase-1 cascade for tokens/agents/runs/shares and RC-4 are TODOs for later slices; they must land in the same transaction when those tables exist. Passwords are hashed as typed, not NFC-normalised (policy counts after NFC): a usability mismatch, not a weakness |

## Required fixes (before public sign-up)

1. **F1 (blocking).** Make "unverified" mean "no credentials of consequence":
   - an unverified account gets no session (password sign-in on an unverified account answers like registration: verify page, `PENDING_REGISTRATION` in that browser), **or** at minimum every `/account/security/*` mutation and identity linking refuses `email_unverified`;
   - the first proof of ownership that does not come from the registering browser (verification link elsewhere, reset, OAuth email match) discards the password, TOTP, WebAuthn credentials, recovery codes **and linked identities**, and revokes every `user_sessions` row;
   - add TM tests for the three paths (A11 to number).
2. **F2.** Count before verifying: atomic increment (cache `INCR`/`hit`) or a row lock on the user before the hash or code check, refuse when the post-increment count is over the limit; add a per-address limit to `/two-factor/*`; keep per-challenge attempts server-side outside the session (or lock the session for these routes).
3. **F3.** Defer the email-match link until the sign-in completes (after any second factor); when the account has 2FA, link only after the challenge succeeds.

## Recommended (before go-live)

F4 (rename the XSRF cookie to `__Host-XSRF-TOKEN` and read it in the client), F5 (a CSRF test with the middleware forced on, plus a `Sec-Fetch-Site: cross-site` case), F6 (fail closed at boot if the list is missing; ship the full list), F7–F12 as described.

## The two A06 deviations

**(a) Registration signs nobody in until the verification link is confirmed in the same browser: ACCEPT, on condition of F1.** Not signing in at `POST /register` is fine and makes the TM-12 claim on verification sound. On its own it is not a control: the same person can sign in by password a second later. The deviation becomes meaningful only together with F1 (no session, or no credential changes, before verification, and discard on any other first proof).

**(b) A recovery request locks email change and identity removal only once confirmed: ACCEPT.** An unconfirmed request is something anyone who knows the address can create; letting it lock the owner would be an unauthenticated denial of service, the same reasoning as RC-5's cool-down (§12.10 R-V). The confirmation already cancels pending email change and deletion requests. Condition: F12 (invalidate the request link on an email change).

## WebAuthn library

**Recommendation: `web-auth/webauthn-lib` 5.x, used directly, pinned to a minor.** It is MIT, actively maintained, the reference PHP implementation, and is the library under Laravel's first-party `laravel/passkeys` that the starter kit shipped (5.3.x was in the lock before Fortify was removed), so it is a starter-kit dependency, not a new supplier. Use it directly rather than through `laravel/passkeys` or `spatie/laravel-passkeys`: those are passkey-only and own their storage and flows, while we need security keys as second factor, our own table, challenges in the server session, WA-1's counter rule (replace the library's counter checker), WA-2's user-handle check and flagging, BE fixed at registration, and attestation `none` only. Configure: RP ID from config only; allowed origins exactly `APP_URL`'s origin; only the `none` attestation support registered; algorithms ES256, EdDSA, RS256; UV required for passkey sign-in. `lbuchs/WebAuthn` is smaller but single-maintainer and slower to follow the spec: not recommended.

## Common-password list (~100 000 entries)

**Source:** the 10-million-password corpus Mark Burnett published in 2015 for research, as redistributed in SecLists (`Passwords/Common-Credentials/`, MIT). Do **not** use RockYou or other raw breach dumps (no licence, personal data). HIBP Pwned Passwords (CC BY 4.0) is hash-only, so it cannot be filtered by length and most of its top entries are under 12 characters: keep it for the post-MVP online check.

**Build:** NFC-normalise, lower-case, keep entries of 12–128 code points, de-duplicate, take the most frequent until ~100 000 (a top-100k list filtered to 12+ leaves only a few thousand, so draw from the larger list). Ship with a provenance file (source URL, commit, SHA-256 of the output, licence text). Load it as a sorted file with binary search or a set built once, and fail closed. PO to confirm the licence position before the first public release (decision 45).
