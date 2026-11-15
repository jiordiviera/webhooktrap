# ADR-003 Amendment 1: Guest Agent Pairing and Session Claim

**Status:** Accepted (decision 46). Text as of revision 3 (sign-in methods of decision 44)
**Amends:** ADR-003 Hybrid Anonymous and Authenticated Access (Accepted). The accepted file is not edited.
**Date:** 2026-11-14 · **Author:** A05
**Source:** decision 32 §2 (guest agent pairing with a one-time code bound to the session), decision 35 (A-13 guest quotas, P4, P10, P11), decision 39 (PO-2 with A09's changes, PO-B, PO-E, PO-F), ADR-010.
**Security review:** A09 `project/security/threat-model.md` §9.2 (ACCEPT WITH CHANGES).

## Context

ADR-003 lets guests capture and inspect without an account. Concept B needs guests to run scenarios, which needs an agent (ADR-010). An agent is a long-lived identity on a user's machine; a guest session is a 24-hour browser cookie. The two must be linked without an account, and the link must die with the session.

The Phase 0 amendment (guests as an auth guard) is assumed here: a `GuestSession` is an `Authenticatable` principal under the `guest` guard.

## Decision

### 1. Guest workspace shape

A guest session owns exactly one workspace, which holds exactly one project with one capture URL (IA §3, P9). Unchanged invariant: a workspace belongs to exactly one principal (database `CHECK`).

### 2. Guest pairing

- The guest's browser requests a pairing code. Code format, TTL, storage and attempt limits follow ADR-010 §4 rule 1: 10 symbols `XXXXX-XXXXX` (≈ 50 bits), 10 minutes, single use, HMAC-stored, voided when a new one is issued. The code is bound to the **guest session id and its workspace**.
- **Guest workspaces always require the agent-side `[y/N]`** (ADR-010 §4 rule 5). `--yes` is ignored for guest workspaces, and a non-TTY agent cannot join one.
- At most **one active agent** per guest workspace. Confirming a second agent **revokes the first** (A-13 "a second agent replaces the first"). Replacement happens at confirmation, not at `pair`, so answering `N` never costs the guest their working agent. The revoked agent's tokens fail at its next request; it shows "Disconnected: replaced by another agent".
- The agent's lifetime is capped by the session: at session expiry or "End session now", every agent of the workspace is revoked and its tokens invalidated.
- A guest's agent receives the guest flag at hello (PV-5) and therefore ignores probe `show:` opt-ins.

### 3. Guest quotas (A-13, approved in decision 35)

| Quota | Value | Rule |
| ----- | ----- | ---- |
| Lifetime | 24 h from capture URL (session) creation | One clock; expiry is absolute, not sliding |
| Stored captures | 100 | Capture 101 onward is answered exactly like a stored one and not stored (ADR-005 amendment 1 §4) |
| Runs | 10 | A run counts once it gets past Once (status is not Couldn't start). An Aborted run counts if at least one scenario after Once completed |
| Accepted runs (hard ceiling) | 20 | Counted by the server at `RunAccepted`, independent of the agent's "got past Once" claim (A09 §7.3, R-6) |
| Agents | 1 active | Second confirmed pairing replaces the first |
| Run start | refused with < 5 min left (IA §9.3, proposed) | Copy owned by A03 |
| Hosted server-side runs | never (PO-B) | Guests use the agent; ADR-006 amendment 1 §2 |
| Server-side private replay | never | ADR-006 amendment 1 §3 |

Quota counters are checked and incremented atomically in PostgreSQL (schema.md §3). A **global guest storage budget** (total stored guest body bytes) applies on top; when it is reached, guest captures are answered identically and dropped (ADR-005 amendment 1 §5).

### 4. Claim on sign-in (P11)

When a guest signs in (new or existing account):

1. **At sign-in start**, from the guest session, the server creates a single-use **claim nonce** stored server-side with the guest session and binds it to the browser session; for OAuth, the `state` is bound too.
2. Authenticate the account with any method of decision 44 (password, GitHub, Google, passkey), **including the 2FA challenge** when the account has a second factor. **Claim only if the sign-in was started from this guest session**: the nonce is checked when the sign-in completes in the same browser session, and consumed. Otherwise the user is signed in **without** claiming. Email-verification and password-reset links never claim (TM-12). A new account registered from a guest session claims only after its email is verified in the same browser session.
3. If the guest workspace is **empty** (0 captures, 0 runs, no agent), discard it.
4. Otherwise, in one transaction: move the guest **project** (captures, runs, findings, fixtures metadata, share state) into the user's workspace; move the active agent to the user's workspace and mark it **`needs_reconfirm`**: its mirrored target approvals and NAME-4 acknowledgements are cleared, it declines jobs with `needs_reconfirm` until a person confirms locally, and the next hello shows the new workspace label. Its guest flag clears **only after** that local confirmation (TM-42). Apply the account's retention and quotas from now on; delete the guest workspace.
5. Revoke the guest session and its token hash; the old cookie becomes invalid; **regenerate the Laravel session id**. Send the terminating `session.ended` event (reason `claimed`) on the guest's channels (ADR-004 amendment 1 §6).
6. After claim, the toast names the account email the data moved into.
7. The project key and the capture key do not change (IA §9.1: "URL and capture URL unchanged").

"Eligible resources" (R60) are exactly the list in step 4. Pairing codes not yet used are voided, not moved. Share tokens of shared guest runs are voided at claim; the owner reshares if needed (PO-A).

Probe display values are already in display form and move unchanged (A09 C-3 review §2.4).

### 5. Isolation and session hardening (S-14, TM-10, TM-11)

- Guest URLs need the session cookie; any other viewer gets "not found" (IA §5.2 rule 4, IA5).
- Guest token: 256 bits, stored hashed. Cookie `__Host-wt_guest`: `Secure`, `HttpOnly`, `SameSite=Lax`, `Path=/`, no `Domain`. The token is never accepted from a URL or a header. All app cookies use the `__Host-` prefix (the Laravel session cookie and the XSRF cookie included; the front end is configured with the prefixed XSRF name).
- `POST /new` requires the CSRF token. When a **live** guest cookie is present, it redirects to the existing project instead of creating a new session, so a cross-site post can never replace a victim's live session.
- Per-address and global limits on `POST /new`; an adaptive challenge only under active abuse (no puzzle by default, G14).
- Guests cannot create CI tokens, personal tokens, or more projects.
- A guest's shared run dies with the session.
- Guests have no password, second factor or WebAuthn credential; WebAuthn ceremonies are refused for the `guest` guard (domain-model §2).
- Abuse of free capture URLs as a drop box (TM-07): guest retention 24 h, an abuse report route and a takedown runbook **[A10]**, per-address guest creation limits.

## Consequences

- The `Agent` aggregate references a workspace, never a guest session directly, so claim moves it with one foreign-key update plus the `needs_reconfirm` flag.
- Guest expiry becomes a cascade: session → workspace → project → captures, runs, agents (schema.md §8).
- Claim is a single transaction across Identity, Workspace and Agent modules; it is the one sanctioned cross-module write and goes through an application service, not model events.
- FL-2 and FL-7 gain one terminal step each (PO-E); A03 owns the copy.

## Open items

- ~~Code entropy, TTL and attempt limits; agent-side join confirmation~~ Resolved by A09 threat model (S-1, S-2).
- ~~Re-confirmation when the guest session is older than N hours~~ Superseded by claim binding (step 2) and `needs_reconfirm` (step 4).

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §2: pairing values per ADR-010; guest always `[y/N]`, no `--yes`; replacement at confirmation | A09 §9.2-1, S-1, S-2; PO-E, PO-F |
| 2026-11-14 | 2 | §4 steps 1–2: claim bound to a sign-in started from the guest session (single-use nonce) | §9.2-2, TM-12 |
| 2026-11-14 | 2 | §4 step 4: moved agent `needs_reconfirm`, approvals cleared, guest flag clears after confirmation | §9.2-3, TM-42; PO-E |
| 2026-11-14 | 2 | §4 step 5: regenerate session id; terminating event; step 6 toast names the account | §9.2-4, TM-10, TM-12, TM-21 |
| 2026-11-14 | 2 | §5: `__Host-` cookies, CSRF and no replacement on `POST /new`, creation limits; §3 global guest budget and accepted-run ceiling; no hosted server runs for guests | §9.2-5, TM-02, TM-10, TM-11, §7.3; PO-B |
| 2026-11-14 | 2 | Open items closed | §9.2-6 |
| 2026-11-14 | 3 | §4 steps 1–2: claim after any sign-in method of decision 44 and after 2FA; reset and verification links never claim; §5 guests have no WebAuthn | decision 44 |
| 2026-11-14 | 3 | Status: Accepted (decision 46); no content change. The accepted ADR file and the ADR index are A00's | decision 46 |
