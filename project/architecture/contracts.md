# Contracts draft · Webhook Trap 2.0

**Status:** Proposed, revision 6.3 (applies decision 50 and A08's spike: broadcast auth response, `timeline.status`, shared props absent, long-poll cap); revision 6.2 (applies decision 47: PO-L, PO-M, PO-N, PO-O decided; DL-4 and EC-1 designed); revision 6.1 (applies A09 §12.11: DL-1, DL-2, DL-3, TH-1, SE-1, ED-1; R-AE rejected); revision 6 (authentication routes and WebAuthn, decision 44; C-7, C-8; A09 re-check rev 3 changes WA-1, WA-2, WA-4, RE-1, RN-3, RN-5, RN-6, RD-1; revision 5: A09 §12.9 follow-ups and the account-security routes A03 needs for S-17A/B, S-24..S-31; revision 6: A03's O-SS-9 — account deletion, throttle answers, password policy prop, idempotent session revoke, email-change link page, invites, security-log params) · **Author:** A05 · **Date:** 2026-11-14
**Scope:** (1) conventions, (2) public ingest, (3) Inertia pages and props per route of A03's IA, (4) HTTP JSON API (CLI, CI, MCP), (5) agent ↔ server protocol v1, (6) runner ↔ server protocol v1.
**Security review:** A09 `project/security/threat-model.md` §9.8 (ACCEPT WITH CHANGES). Decisions 38 and 39 applied.
**Pending Gate 2:** anything marked **[G2]** depends on screen details A03 has not specified yet. Prop *shapes* below are the server contract; prop *names* may be renamed to match A03's components without architectural change.
**Probe data:** follows A09's C-3 review (PV-1 to PV-14), adopted in decision 38.

---

## 1. Conventions

- Inertia props are camelCase TypeScript types generated from PHP DTOs (one source; A06 picks the generator). JSON API and agent/runner protocols are snake_case.
- Ids: external forms from `schema.md` §1.1.
- Times: RFC 3339 UTC with milliseconds (JSON-1).
- Not found and forbidden are the same response (IA5): Inertia renders `NotFound` with status 404; the JSON API returns `404 {"error":"not_found"}`. `project_key` and `run_id` are never capabilities (TM-13).
- JSON API errors: `{"error": "<code>", "message": "<human text>", "details": {...}}`. Validation errors: 422 with `details.fields`.
- Every Inertia page receives shared props (§3.1).
- Deferred props are used for anything over ~50 ms to compute (counts, shapes); live props per ADR-004; run timeline via direct events (ADR-004 amendment 1).
- **Prop sets are built per access level** (owner, shared, example). Owner-only props are absent, not lazy, for shared viewers; a partial reload naming an owner-only or unknown prop returns nothing for it (TM-14).
- **Untrusted content** (capture bodies, headers, paths, queries; agent, probe and fixture names; target paths) is rendered as text only; no HTML or Markdown rendering, no `dangerouslySetInnerHTML` (TM-06, TM-16). App-wide CSP without `unsafe-inline` scripts.
- **Cookies:** all app cookies use the `__Host-` prefix, `Secure`, `HttpOnly` (except the XSRF cookie the front end reads), `SameSite=Lax`, `Path=/`, no `Domain` (TM-10).

### 1.1 Token value formats (TM-60)

| Kind | Format | Read from |
| ---- | ------ | --------- |
| Personal | `wt_pat_` + 32 base62 + 6-char checksum | CLI config or `WEBHOOKTRAP_TOKEN`; MCP config |
| CI | `wt_ci_` + 32 base62 + 6-char checksum | `WEBHOOKTRAP_TOKEN` **only**; no flag (a token passed as an argument is refused) |
| Runner | `wt_rnr_` + 32 base62 + 6-char checksum | a file or environment variable on the runner host; never argv |

Shown once at creation, stored hashed with last 4, masked in all output including encoded forms. The checksum lets secret scanners detect leaks without a server call.

---

## 2. Public ingest

| Method | Host · Path | Auth | Response |
| ------ | ----------- | ---- | -------- |
| any (incl. `HEAD`, `OPTIONS`) | **capture host** (HD-7) · `/c/{captureKey}` and `/c/{captureKey}/{rest…}` | none | always `200`, `Content-Type: application/json`, `Cache-Control: no-store`, body `{"received":true}` (none for `HEAD`) |

Invariant cases, quotas and body limits: ADR-005 amendment 1 §4–§5. Only network-level `429` (per client address, never keyed on the capture key) and proxy `413` above the hard limit (default 25 MiB) may differ. CSRF, sessions and cookies are disabled on this route group; app routes are not served on the capture host. No CORS headers. Log scrubbing drops bodies on `/c/` (TM-82).

---

## 3. Inertia pages

### 3.1 Shared props (every page)

```ts
type Shared = {
  principal: { kind: 'anonymous' } | { kind: 'guest'; expiresAt: string; ended: boolean }
           | { kind: 'user'; id: string; name: string; email: string; emailVerified: boolean; isInstanceAdmin: boolean;
               twoFactor: boolean; recoveryPending?: { completesAt: string } };
  meter?: { expiresAt: string; captures: { used: number; limit: 100 }; runs: { used: number; limit: 10 } }; // guest only (A-13)
  instance: { kind: 'hosted' | 'selfhost'; name: string; guestMode: boolean; serverRuns: boolean };
  currentProject?: { key: string; name: string; captureUrl: string };
  agentPill?: { state: 'none' | 'awaiting_confirmation' | 'connected' | 'disconnected' | 'too_old'; name?: string; version?: string };
  flash?: { kind: 'info' | 'success' | 'warning' | 'error'; message: string; code?: string }; // `code` (revision 6) lets A03 pick the copy, e.g. 'session_already_ended', 'account_deleted', 'deletion_link_sent', 'email_change_waiting_old', 'email_change_waiting_new'
};
```

Shared props for `access: 'shared'` pages carry only `principal` and `instance`.

### 3.2 Pages

| Route (IA §5.2) | Page component | Who | Props (beyond shared) | Notes |
| --------------- | -------------- | --- | --------------------- | ----- |
| `GET /` | `Landing` | all | `returning?: {projectKey, captureCount, expiresAt}` (guest) · `lastProject?: {key, name}` (user) | |
| `POST /new` | — | anonymous | redirect `/p/{projectKey}` | **CSRF token required.** When a live guest cookie is present, redirects to the existing project and creates nothing (TM-11). Otherwise creates guest session + workspace + project + capture URL in one transaction. Per-address and global creation limits. Refused when guest mode is off (self-host) |
| `GET /p/{projectKey}` | `Capture/Stream` | owner | `captureUrl`, `captures.latest: CaptureRow[]` (**live**, 50), `captures.older` (paginated), `groups: GroupSummary[]` (deferred), `selected?: CaptureDetail` when `?sel=`, `view`, `filter`, `providers: ProviderTab[]` **[G2]** | query: `sel`, `tab`, `view`, `q`, `group` |
| `GET /p/{projectKey}/d/{n}` | `Capture/Detail` | owner | `capture: CaptureDetail`, `prev?`, `next?` | |
| `GET /p/{projectKey}/d/{n}/raw` | — (download) | owner | raw body bytes | Headers: `Content-Type: application/octet-stream`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox` (TM-06). 404 when `body_kept=false` |
| `GET /p/{projectKey}/runs` | `Runs/Index` | owner | `runs: RunRow[]` (**live**), `filters: {source, status}` | |
| `GET /p/{projectKey}/runs/new?from=01,03,04` or `?group=` | `Runs/Draft` | owner | `selection: CaptureRef[]`, `preview: PlanPreview` (from `webhooktrap plan --json`, rate-limited per principal), `targets: RunTarget[]`, `agent?: AgentSummary`, `pairing?: PairingCodeView`, `quota?: {runsLeft}` **[G2]** | `targets` lists only **declared** agent targets (TM-50); the server target only for accounts with server runs on (PO-B, HD-10) |
| `POST /p/{projectKey}/runs` | — | owner | body `{from:[seq], scenarios:[...], target:{kind:'agent',agentId,targetRef}\|{kind:'server',url}, failOn}` → redirect `/r/{runId}` | 422 on quota, cap, provider mix, undeclared target, guest server target, server runs disabled, server-run limits, B′ rules |
| `GET /p/{projectKey}/runs/{n}` | — | owner | redirect `/r/{runId}` | |
| `GET /r/{runId}` (+ `/D{n}`, `/F{n}`, `?compare=D2`) | `Run/Show` | **owner only** | `run: RunHeader`, `scenarios: ScenarioCell[]`, `timeline: {lastSeq, status: RunStatus, deliveries: RunDeliveryRow[]}` (revision 6.3: `status` lets the client see a finished run in a reload, event-catalogue §3.3), `findings: FindingView[]`, `focus?`, `access: 'owner'`, `share: ShareState` **[G2: inspector fields]** | `Referrer-Policy: no-referrer` |
| `GET /r/{runId}.json` | — | owner | run document, hosted profile (A-24) | `X-Content-Type-Options: nosniff`, `Content-Type: application/json` |
| `GET /s/{shareToken}` (+ `/D{n}`, `/F{n}`, `?compare=D2`) | `Run/Show` | anyone with the share token (PO-A, TM-15) | `run: RunHeader`, `scenarios`, `timeline`, `findings`, `focus?`, `access: 'shared'`, `sharedBy: string` | Scrubbed `CaptureDetail` (schema.md §6); no channel; 5 s poll while running, rate-limited per address (30 / min, then `429`). **Revision 6.3: this page has its own prop set; `share`, `agent`, `pairing` and `targetHost` are absent, never `Inertia::optional()`/lazy** (a lazy owner prop is returned by a partial reload; spike §5). A partial reload naming an owner-only or unknown prop, an `except` header, a wrong component or `X-Inertia-Reset` returns none of them. Contract test: seven request shapes, no marker value anywhere in the JSON. `timeline` is served by partial reload. `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex`. Unknown, rotated or deleted token → not found |
| `GET /s/{shareToken}.json` | — | same | run document, scrubbed for sharing | `nosniff`. **Share tokens never appear in logs (RD-1, TM-145):** the app's own logs record the route template (`/s/{token}`), never the value; the host and in-image proxies mask the path segment after `/s/` in access logs (note for A10; `ci-cd`/`compose-profile` own the proxy config); exceptions and traces scrub the segment |
| `GET /r/example` | `Run/Show` | all | static synthetic Run #3 (16 deliveries, C-4), `access:'example'` | synthetic probe readings only |
| `POST /r/{runId}/share` | — | owner | body `{bodiesHidden, includeProbeValues}` → `{shareUrl}` | Issues a **new** share token every time (reshare rotates; old links fail). Shows `sharedBy` (owner's display name) in the dialog first. `includeProbeValues` refused for guest runs (PV-7) |
| `DELETE /r/{runId}/share` | — | owner | — | Deletes the token; run returns to private |
| `GET /p/{projectKey}/fixtures` | `Fixtures/Index` | owner | `fixtures: FixtureRow[]` (**live**), `agentConnected` | |
| `GET /p/{projectKey}/agent` | `Agent/Guest` | guest | `project`, `agent: AgentDetail \| null` (**live**), `pairing: null` | built (A07 e2e notes); a user owner is redirected to `/agents` |
| `POST /pairing-codes` | — | guest or user | body `{replace?: agentId}` → `{code, expiresAt, command, replaces}` (JSON) | one live code per principal. `replace` (AC-3): the owner replaces one of its confirmed agents; `replaces: {agentId, name, keyFingerprint} \| null`; `422 unknown_agent` otherwise |
| `GET /p/{projectKey}/settings/{section}` | `Settings/*` | owner (guest: `danger` only) | section-specific | |
| `POST /p/{projectKey}/settings/capture/rotate` | — | user owner | → new `captureUrl` | |
| `POST /session/end` | — | guest | deletes session data → `/` with flash | |
| `GET /projects`, `GET/POST /projects/new` | `Projects/*` | user | `projects: ProjectRow[]` | |
| `GET /agents`, `/agents/{name}`, `/agents/pair` | `Agents/*` | user | `agents: AgentRow[]` (**live**), `agent: AgentDetail`, `pairing: null` | built (A07 e2e notes): `/agents/{agentId or name}`; `/agents/pair?replace=` |
| `DELETE /agents/{agentId}` | — | owner (user or guest) | revoke (`user`) | also the "Not your machine? Revoke" action. Built (AC-7): JSON → `204`; Inertia → `303` back, flash `{kind:'success', code:'agent_revoked', agentId}`; unknown, revoked or foreign → `404`. No recent authentication (revoking only removes access) |
| `GET /account/{section}` | `Account/*` | user | `security: SecurityView` (section `security`), `tokens: TokenRow[]`, `deletion: AccountDeletionView` (section `delete`, revision 6, §3.4) | Ceremony pages: `Permissions-Policy` allows `publickey-credentials-*` for `self` (§3.4) |
| `POST /account/tokens`, `DELETE /account/tokens/{id}` | — | user | body `{name, abilities: ('read'\|'run')[], expiresAt?}`; returns the token value **once** | `run` unchecked by default (TM-111) |
| `POST /p/{projectKey}/settings/ci/tokens`, `DELETE /p/{projectKey}/settings/ci/tokens/{id}` | — | user owner | returns CI token value once; delete revokes | revoke is also offered on the post-recovery review (§3.4) |
| `GET /admin/{section}` | `Admin/*` | instance admin, self-host | overview health, access, limits, server replay (allow-list, per-user B′ permission), users (minimal; status `active`\|`invited`\|`disabled`) — P8. Revision 6: `POST /admin/users/invites` (§3.4) | 404 on hosted. Admin granted only from the host (TM-19). Enabling B′ with open sign-up, or opening sign-up with B′ on, → 422 (PO-G) |
| `GET /sign-in`, `GET /register`, OAuth callbacks | `Auth/SignIn`, `Auth/Register` | anonymous / guest | `next`, `passkeysAvailable: boolean` | Methods: email + password, GitHub, Google, passkey (conditional UI). A guest sign-in **started from the guest session** creates a claim nonce; claim happens only when the sign-in completes in the same browser session, **after** any 2FA challenge (TM-12). OAuth `state` bound to the browser session. Session id regenerated at sign-in, after the 2FA challenge and at claim. After claim, flash names the account email. Full list: §3.4 |
| `GET /expired` | `Expired` | all | — | R59: no session data in props |
| `GET /pair?code=` | `Pair/Approve` | — | **post-MVP** (P10); needs its own A09 model (code phishing) before it ships | |

### 3.3 Core prop types

```ts
type CaptureRow = { seq: number; receivedAt: string; method: string; path: string;
  provider: string | null; eventType: string | null; groupKey: string | null;
  signature: SignatureChip; bodySize: number; bodyKept: boolean; shapeVerdict: 'new'|'matches'|'differs'|null };
type SignatureChip = { state: 'unsigned'|'unknown_scheme'|'invalid'|'not_checked'|'no_secret'|'valid'|'checking';
  reason?: string; checksPassed?: number; checksTotal?: 7; checkedBy?: string /* agent name, owner only */ };
type CaptureDetail = CaptureRow & { rawTarget: string; rawQuery: string | null; headers: [string, string][];
  redactedHeaders: string[]; headerFidelity: 'exact'|'sapi'; publicOrigin: string; publicOriginSource: string;
  sourceIp: string; contentType: string | null; bodySha256: string;
  body?: { encoding: 'utf8'|'base64'; text: string; truncated: boolean };   // owner; scrubbed for shared views
  decoded?: unknown; shape?: [string, string][]; signatureChecks?: { check: string; passed: boolean }[];
  secretNames?: string[] /* owner only; e.g. "STRIPE_WEBHOOK_SECRET on maya-mbp", never a last-4 hint */ };
type Provenance = { executor: 'agent'|'ci'|'server_public'|'server_allowlist'; source: 'web'|'cli'|'ci';
  agentName?: string; ciTokenName?: string; runner?: true };   // shown on every run view, owner and shared (TM-17)
type RunHeader = { id: string; number: number; status: RunStatus; reason: string | null; exitCode: 0|1|2|3|null;
  provenance: Provenance; agent?: { name: string; version: string };
  provider: string; pack: { id: string; version: string }; budget: { ms: number; source: string; label: string };
  target: { scheme: string; host: string; port: number; path: string }; levels: (1|2)[];
  probeEntries: { name: string; displayMode: 'count'|'code'|'opted_number'|'opted_value' }[];
  counts: { critical: number; high: number; medium: number; info: number; findings: number };
  from: { groupKey?: string; seqs: number[] }; startedAt: string | null; finishedAt: string | null; durationMs: number | null;
  notChecked: string[]; orderDependent: boolean; visibility: 'private'|'link' };
type RunStatus = 'queued'|'running'|'couldnt_start'|'aborted'|'findings'|'inconclusive'|'passed';
type RunDeliveryRow = { d: number; scenario: string; sourceSeq: number | null; mode: string; offsetMs: number;
  signing: { kind: string; timestampOffsetS?: number }; tamper?: { path: string; from: string; to: string };
  status: number | null; transport: string | null; latencyMs: number | null; overBudget: boolean | null;
  response?: { contentType: string | null; bytes: number; sha256: string };
  state?: { readings: { name: string; changed: boolean|null; matchesExpected: boolean|null; flagged: boolean;
    display: { before: string; after: string; expected: string | null } }[]; failed: boolean; unsettled: boolean };
  result: 'pass'|'finding'|'inconclusive'|'unreachable'|'sent_no_result'|'not_run'|null };
// Prose fields are rendered by the server from type + pack version + params; never from agent text (TM-16).
type FindingView = { n: number; type: string; severity: 'critical'|'high'|'medium'|'info'; level: 1|2;
  title: string; eventType: string | null; scenarios: string[]; evidence: { d: number; role: 'offending'|'reference' }[];
  what: string; why: string; citation: { label: string; url: string } | null /* from the pack only */;
  fix: { text: string; framework: string | null; snippet: string | null /* rendered in a plain <pre> */ };
  likelyCause: { rule: string; text: string } | null; fingerprint: string; seenBefore: boolean };
type PairingCodeView = { code: string; expiresAt: string; command: string }; // "npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD"
type AgentSummary = { id: string; name: string; os: string; keyFingerprint: string;
  state: 'awaiting_confirmation'|'connected'|'disconnected'|'needs_reconfirm' };
type RunTarget = { kind: 'agent'; agentId: string; targetRef: string; display: string; declared: true; approvedOnAgent: boolean }
               | { kind: 'server_public' } | { kind: 'server_allowlist'; entries: string[] };   // B′: permitted users only, self-host
type ShareState = { visibility: 'private'|'link'; shareUrl?: string; sharedBy: string; bodiesHidden: boolean; includeProbeValues: boolean };
```

`display.*` values are PV-2/PV-5 strings (`"2"`, `"#a1f3c0"`). The UI never receives a raw probe value.

### 3.4 Authentication routes and WebAuthn ceremonies (decision 44, domain §2.1–§2.4)

All routes below are on the app host only, with CSRF, `__Host-` cookies and the throttles of domain §2.1 (values in its "Throttle values" table, revision 6). Answers never reveal whether an email has an account. **Throttled answer (revision 6):** a browser form returns to its page with `errors.form = 'throttled'` and the prop `retryAt: string` (RFC 3339, the latest end among the keys hit), which A03 prints as "Try again after 14:58 UTC"; a JSON endpoint answers `429 {"error":"throttled"}` with `Retry-After` (seconds). The per-email keys count unknown addresses too, so the throttled answer is the same whether or not an account exists. JSON endpoints (`→ JSON`) also require `Sec-Fetch-Site: same-origin`.

| Method · Path | Who | Request → Response | Notes |
| ------------- | --- | ------------------ | ----- |
| `POST /sign-in/password` | anonymous / guest | `{email, password}` → redirect (2FA challenge, verify-email or `next`) | uniform failure "Email or password is incorrect"; throttle per (email, address) |
| `POST /register` | anonymous / guest | `{name, email, password}` → verify-email page | refused on self-host when `signup_policy <> 'open'` (revision 6: `invite` behaves like `closed` here; invited people arrive through `/set-password/{token}`; there is no invite token on `/register`) |
| `GET /verify-email/{id}/{hash}` → page, `POST /verify-email/{id}/{hash}` | any | page with **Confirm email**; the `POST` marks verified | revision 5: the `GET` no longer verifies (emailed-link rule below); signed URL, 60 min, idempotent; proves ownership; discards pre-verification credentials of imported accounts (domain §2.4) |
| `POST /email/verification-notification` | user, unverified | → same page | resend; 60 s between sends, per-address throttle |
| `POST /forgot-password`, `GET /reset-password/{token}`, `POST /reset-password` | anonymous | uniform answer; the `GET` shows the form without consuming the token; the `POST` sets the password and verifies the email | never claims a guest session; **on a 2FA account the `POST` does not sign in: it leads to the 2FA challenge, and `reset_link` never counts as a second factor or as recent authentication (AR-2, TM-T151)**; **revokes every session** (`user_sessions`); token 60 min, single use; password policy below |
| `GET /two-factor` | half-signed-in | `TwoFactor/Challenge`, props `methods: ('security_key'\|'totp'\|'recovery_code')[]` | state lives in the session; 5 attempts, then back to sign-in |
| `POST /two-factor/totp`, `POST /two-factor/recovery` | half-signed-in | `{code}` → redirect | TOTP code accepted once; recovery code used emails the user. Every failed second-factor attempt (these two routes and `/webauthn/assert` as second factor) counts toward the **per-account counter across challenges**: 20 per 24 h → hold of 1 h on second-factor sign-in, answered `429`-equivalent uniform page, email notice (WA-3, TM-122) |
| `POST /webauthn/assert/options` | anonymous (passkey) or half-signed-in (second factor) | `{}` → JSON `PublicKeyCredentialRequestOptionsJSON` | passkey: empty `allowCredentials`, `userVerification:'required'`; second factor: the user's credentials, `'discouraged'`. Challenge stored in the session, single use, 5 min |
| `POST /webauthn/assert` | same | `{credential: AuthenticationResponseJSON}` → JSON `{redirect}` | checks origin = `APP_URL` origin, RP ID hash, UP, UV (passkey), BE unchanged, **counter rule per the WebAuthn specification (refused when either the stored or the received count is non-zero and the received one is not greater; only 0 and 0 passes, WA-1, TM-120)**, and, for discoverable sign-in, **`userHandle` equals the asserted credential owner's handle (WA-2, TM-121)**; a mismatch or a flagged credential gets the uniform sign-in failure and an audit row; passkey sign-in completes sign-in **and** 2FA |
| `POST /webauthn/register/options` | user, recent auth | `{kind: 'passkey'\|'security_key'}` → JSON `PublicKeyCredentialCreationOptionsJSON` | `attestation:'none'`; `excludeCredentials` = user's; `residentKey`/`userVerification` per domain §2.2; `user.id` = the user handle |
| `POST /webauthn/register` | user, recent auth | `{credential: RegistrationResponseJSON, name}` → `SecurityKeyRow` | name grammar §5.11; max 10; recovery codes returned **once** if this is the first second factor; email notice |
| `PATCH /account/security/keys/{keyId}` | user | `{name}` | rename |
| `POST /account/security/keys/{keyId}/unblock` | user, recent auth (by another method) | — → `SecurityKeyRow` | clears the flag, keeps the sign count; `422 unblock_not_allowed` when the key was flagged twice (`flag_count >= 2`) or is not flagged; email `key-unblocked`; audit (domain §2.2) |
| `DELETE /account/security/keys/{keyId}` | user, recent auth | — | refused (`422 last_sign_in_method`) if it is the last first factor; email notice; audit |
| `POST /account/security/totp`, `POST /account/security/totp/confirm`, `DELETE /account/security/totp` | user, recent auth | secret + `otpauth://` URI (shown once) → confirm with a code | |
| `POST /account/security/recovery-codes` | user, recent auth | → codes once | replaces the set |
| `GET /confirm-auth?next=&action=` | user | `Auth/ConfirmAuth`, props `methods: ('webauthn'\|'password'\|'totp'\|'github'\|'google')[]`, `secondFactorRequired: boolean`, `action`, `retryAt?` | `github`/`google` listed **only** for accounts with no password, no WebAuthn credential and no TOTP; `secondFactorRequired` is true for an email change **or an account deletion (`action=delete_account`, revision 6)** on a 2FA account (password not offered then) |
| `POST /confirm-auth` | user | password, TOTP or `{credential}` from `/webauthn/assert/options?purpose=confirm`, plus `action` | sets recent authentication (10 min) and records the method **and the purpose**; with `action=delete_account` it records a deletion confirmation valid 5 min, single use (DL-1, revision 6.1); a flagged credential is refused. A factor passed here cancels an open recovery (RC-1). **TH-1 (revision 6.1):** password failures count toward the per-account 20 / h sign-in key and TOTP, recovery-code and security-key failures toward the per-account second-factor counter (WA-3), in addition to the per-user 10 / 15 min key; during a hold the password is not verified and the throttled answer is returned whatever it is; an unknown-hash account takes the same hash time (domain §2.1) |
| `POST /confirm-auth/{provider}` → OAuth → callback | user (OAuth-only account) | redirect to the provider (Google: `prompt=login`, `max_age=0`), back to `next` | callback must return the **same provider subject** as the linked identity, else `422 reauth_mismatch`; `state` bound to the session with purpose `confirm` (domain §2.1) |
| `GET /account/security/identities/{provider}/connect` → OAuth → callback | user, recent auth | redirect; back to `/account/security` with flash | purpose `link`; `422 identity_in_use` when the subject belongs to another account; `IdentityLinked` (`settings`), email, audit (domain §2.5) |
| `DELETE /account/security/identities/{provider}` | user, recent auth | — | `422 last_sign_in_method` when last first factor; `422 recovery_lock` (RC-3, only for identities linked before the recovery request, AR-1); email `method-unlinked`; audit |
| `PUT /account/password` | user, recent auth | `{password, passwordConfirmation}` → `SecurityView` + flash | sets or changes; password policy below; **revokes every other session**, regenerates this one; email `password-changed`. **Revision 6: `currentPassword` is removed and never required**: recent authentication (S-28) is the proof, and asking again would add nothing for a passkey- or OAuth-only account; an extra field is ignored |
| `POST /account/email` | user (verified), recent auth **with a second factor on 2FA accounts** | `{email}` → uniform `{sentTo: email}` | `422 recovery_lock` (RC-3); `422 second_factor_required` when the recent authentication was password-only on a 2FA account; replaces a pending change (voids both links); emails new and old addresses (domain §2.6). **Revision 6.2 (EC-1):** on an account without a second factor the old address gets `email-change-approve` with `/email-change-old/{token}` instead of the plain notice |
| `GET /email-change/{token}` → page, `POST /email-change/{token}` | any on `GET`; `POST` only by the user signed in to the account that asked | `Auth/EmailChangeLink`, props `link: EmailChangeLinkPage` (revision 6, below); `POST` → `/account/security` + flash | 60 min, single use; re-checks RC-3 and uniqueness (conflict → `invalid`); revokes other sessions; `DELETE /account/email` cancels a pending change. **Revision 6.2 (EC-1):** when the request has `old_required` (no second factor), the `POST` only records the new-address half and answers `/account/security` + `flash code: 'email_change_waiting_old'`; the change applies on the second half, in either order. Signed out or signed in to another account: the link is stored as the session's **intended URL** (kept across "Sign out and continue", which is the normal `POST /sign-out` with `continue: true`) and sign-in returns to it; the token never appears in a `next` query parameter |
| `GET /email-change-old/{token}` → page, `POST /email-change-old/{token}` | any on `GET`; `POST` only by the user signed in to the account that asked | `Auth/EmailChangeOldLink`, props `link: EmailChangeOldLinkPage` (below); `POST` → `/account/security` + flash | **Revision 6.2, EC-1.** Only exists for requests with `old_required` (accounts without a second factor at request time). 60 min from the request (same `expires_at` as the new-address link), single use, `GET` consumes nothing. The `POST` records `old_confirmed_at`; if the new-address half is already recorded it applies the change (same transaction and checks as `/email-change/{token}`, domain §2.6) and answers `flash code: 'email_changed'`, else `flash code: 'email_change_waiting_new'`. Same intended-URL rule as the other link (never in `next`). Unknown, used, expired, replaced, cancelled, or a request without `old_required` → `invalid` |
| `DELETE /account/sessions/{sessionId}`, `DELETE /account/sessions` | user, recent auth | — → `SecurityView` | one other session, or all others; audit (domain §2.7). **Idempotent (revision 6):** an unknown id, another user's id, or a session already revoked or expired gets the same answer as a success — redirect to `/account/security` with a fresh `SecurityView` and `flash {kind:'info', code:'session_already_ended'}` — with no audit row and no event; `DELETE /account/sessions` with no other live session answers the same with `code:'no_other_sessions'`. The current session's id → `422 current_session` (use Sign out; the screen has no button for it) |
| `GET /account/delete` | user | `Account/Delete`, props `deletion: AccountDeletionView` (counts deferred) | revision 6, domain §2.10 |
| `DELETE /account` | user, a **fresh `delete_account` confirmation** from `/confirm-auth` in this session within 5 min, single use (DL-1, revision 6.1), **with a second factor on 2FA accounts** | `{confirm: true}` → **2FA account:** redirect `/` with `flash {kind:'success', code:'account_deleted'}` in a new anonymous session. **Account without 2FA (DL-4, revision 6.2): nothing is deleted;** creates an `account_deletion_requests` row, emails `account-deletion-confirm` and redirects to `/account/delete` with `flash {kind:'info', code:'deletion_link_sent'}` | revision 6, domain §2.10. Immediate: tombstone in one transaction (sessions incl. this one, tokens, agents revoked; active runs aborted; share tokens deleted; ingest stops storing), purge ≤ 1 h. `422 recovery_lock` (hosted; open recovery or until `lock_until`), `422 last_instance_admin` (self-host), `422 second_factor_required`, `422 confirmation_stale` (no `delete_account` confirmation, older than 5 min, or already used), `422 confirmation_required`. **Active runs are aborted, not a refusal.** While the account is tombstoned, reset, verification resend, recovery, OAuth and passkey sign-in answer as for an unknown account (DL-2, domain §2.10); the confirmation is consumed in the transaction. DL-4 (PO-N, decided) is the paragraph above and the two rows below. Email `account-deleted` (sent when the deletion happens); `AccountDeleted`; audit `account.deleted`. 404 for guests and anonymous |
| `GET /account-deletion/{token}` → page, `POST /account-deletion/{token}` | any on `GET`; `POST` only by the user signed in to the account that asked | `Auth/AccountDeletionLink`, props `link: AccountDeletionLinkPage` (below); `POST` → redirect `/` with `flash {kind:'success', code:'account_deleted'}` in a new anonymous session | **Revision 6.2, DL-4.** 1 h from the request, single use, `GET` consumes nothing, intended-URL rule as the other links. The `POST` re-checks `recovery_lock` and `last_instance_admin` and that the account still has **no** second factor (otherwise `invalid`), then runs the deletion of domain §2.10 (actor `user`); it asks for no new confirmation (DL-1 was spent on the request). Refusals at the `POST` show on the page (`blocked`) and the request stays pending until it expires |
| `DELETE /account/deletion` | user | → `/account/delete` + flash | **Revision 6.2, DL-4.** Cancels the pending deletion request (reason `user`); idempotent; the link then answers `invalid`. 404 for guests and anonymous |
| `GET /set-password/{token}` → page, `POST /set-password/{token}` `{password, passwordConfirmation}` | anonymous (self-host only) | `Auth/SetPassword`, props `link: {state: 'valid'; purpose: 'invite'\|'admin_reset'\|'set_email'; email: string; passwordPolicy: PasswordPolicy} \| {state: 'invalid'}`; `POST` sets the password, marks the email verified and signs in → `/`; **on an account that still has a second factor it leads to the 2FA challenge instead** (AR-2 applies: the link proves only the mailbox, is never a second factor or recent authentication) | revision 6, domain §2.9, §11. 24 h, single use; `GET` consumes nothing; a newer link voids the older; revokes every session; `EmailVerified` (`password_set_link`). Invites and admin resets have no second factor at this point; a set-email restore keeps the account's factors |
| `POST /admin/users/invites` | instance admin, recent auth, self-host | `{email}` → `/admin/users` + flash "Invited" | revision 6, domain §11. Uniform answer whether or not the address has an account (nothing sent for an existing one); creates an unverified user without a password and emails the password-set link (`invite`); `UserInvited`; audit `user.invited`. Resend = same route, new token |
| `GET /account/security/review` | user, between recovery completion and `lock_until` | `Account/SecurityReview`, props `review: RecoveryReview` | otherwise redirect to `/account/security` (domain §2.8) |
| `POST /account-recovery`, `GET /account-recovery/{token}` → page, `POST /account-recovery/{token}` (start the wait), `DELETE /account-recovery/{token}` (decline before start), `DELETE /account-recovery` (cancel), `GET /account-recovery/finish/{token}` → form, `POST /account-recovery/finish/{token}` `{password, passwordConfirmation}` | anonymous (request, link pages) / user (cancel) | uniform answer; the link page shows **Start the 72-hour wait** and does not start it on `GET` (revision 5); request link 60 min single use; decline closes the request as `declined` (no cool-down); finish link 24 h single use, signs in and redirects to `/account/security/review`; cancel from any signed-in session | hosted only, 72 h wait (PO-J) with conditions RC-1..RC-6 (domain §2.3): a second-factor sign-in cancels it, a password reset by email extends the wait to 7 days, no email change / `run` token / identity removal while open and for 7 days after (`422 recovery_lock`), 30-day cool-down after a cancelled request; 404 on self-host (host console instead) |

**Headers.** Ceremony pages (`/sign-in`, `/two-factor`, `/confirm-auth`, `/account/security`) send `Permissions-Policy: publickey-credentials-get=(self), publickey-credentials-create=(self)`. Every other page, and **always** `/s/…` share pages and raw downloads, send `publickey-credentials-get=(), publickey-credentials-create=()`. **`Permissions-Policy` is defence in depth only**: it is not a boundary against same-origin script, which the CSP without `unsafe-inline` and text-only rendering exclude (TM-06, TM-16, TM-123). **Every app page, ceremony pages included, sends `Content-Security-Policy: frame-ancestors 'none'` and `X-Frame-Options: DENY`** (WA-4, TM-123); the share and raw routes already send their own stricter policies and keep `frame-ancestors 'none'`. The capture host serves no HTML.

**Emailed links (revision 5, domain §2.9).** No emailed link changes state on `GET`: verification, email change, reset and recovery links open a page that validates the token **without consuming it** (expired, used or unknown → one "This link doesn't work / has expired" page, identical for each cause) and a `POST` from that page, with CSRF, performs the change. These pages send `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, load no third-party resources, and their token segment is masked in app and proxy logs like `/s/…` (RD-1; note for A10). Lifetimes: verification 60 min, email change 60 min, reset 60 min, recovery request 60 min, recovery finish 24 h, self-host password-set link (admin reset, set-email restore, **invite**) 24 h.

**Every emailed link with a token (revision 6, complete list for A10's log scrubbing):** `/verify-email/{id}/{hash}` with its `expires` and `signature` query, `/email-change/{token}`, `/email-change-old/{token}` (revision 6.2, EC-1), `/account-deletion/{token}` (revision 6.2, DL-4), `/reset-password/{token}` (**no `email` query parameter**; the token alone finds the request), `/account-recovery/{token}`, `/account-recovery/finish/{token}`, `/set-password/{token}`. **No other emailed invite or sign-in link exists in the MVP**: self-host invites use `/set-password/{token}`. Mask the path segment (and, for `/verify-email/`, the `{hash}` segment and the whole query) in app, proxy and error-tracking logs. Tokens never travel in `next` (intended URLs live in the session). The only other token URL is `/s/{shareToken}` (not emailed).

**Password policy (domain §2.1).** 12 to 128 characters (code points after NFC), any printable characters, refused when equal to the account email or in the bundled common-password list. Validation errors in `details.fields.password`: `password_too_short`, `password_too_long`, `password_common`, `password_is_email`. No breach API call in the MVP, so A03's "known data breaches" message becomes "This password is too common. Choose another." **Revision 6: hashing is argon2id only** (domain §2.1; the app refuses to boot without it), so the bcrypt fallback and its 72-byte limit are withdrawn; the limit is 128 code points on every instance. Every page with a password field (register, reset, set-password, recovery finish, account security) receives `passwordPolicy: PasswordPolicy`, so forms never hard-code the numbers.

**Configuration.** `WEBAUTHN_RP_ID` (default: host of `APP_URL`), `WEBAUTHN_RP_NAME` (instance name). Expected origin is derived from `APP_URL`, never from request headers. Self-host on plain HTTP (other than `localhost`) or on an IP address cannot use WebAuthn: `passkeysAvailable = false` and the security-key options are hidden with an explanation.

```ts
type SecurityView = { hasPassword: boolean; email: string; emailVerified: boolean;
  pendingEmail?: { masked: string; expiresAt: string;                         // §2.6
                   awaiting: 'both' | 'old' | 'new' };                        // revision 6.2 (EC-1): 'new' only when the request has no `old_required`
  identities: { provider: 'github'|'google'; label: string; linkedAt: string }[];
  keys: SecurityKeyRow[]; totp: { enabled: boolean; since: string | null }; recoveryCodesLeft: number | null; twoFactor: boolean;
  recovery?: { completesAt: string };                                          // open request
  lock?: { until: string; reason: 'recovery_open'|'recovery_completed' };       // RC-3: email change, disconnect, `run` tokens refused
  recoveryReview?: { until: string };                                          // link to /account/security/review
  sessions: SessionRow[];                                                      // §2.7
  securityLog: SecurityLogEntry[];                                             // last 30 days, ≤ 50 rows
  passkeysAvailable: boolean; maxKeys: 10 };
type SecurityKeyRow = { id: string; name: string; kind: 'passkey'|'security_key'; synced: boolean | null;
  createdAt: string; lastUsedAt: string | null; flagged: boolean;
  flaggedAt?: string; flagCause?: 'counter'|'user_handle_mismatch'; canUnblock?: boolean };
type SessionRow = { id: string; current: boolean; browser: string /* ua label */; network: string /* "203.0.113.0/24" */;
  signedInAt: string; lastSeenAt: string; method: { first: 'password'|'github'|'google'|'passkey'|'reset_link'|'recovery_finish';
  second: 'security_key'|'totp'|'recovery_code'|'passkey'|null } };
type SecurityLogEntry = { at: string; action: SecurityLogCode /* schema §7 allow-list, revision 6 */; browser: string | null; network: string | null;
  params?: { how?: { first: SessionRow['method']['first']; second: SessionRow['method']['second'] }; keyName?: string;
    kind?: 'passkey'|'security_key'; cause?: 'counter'|'user_handle_mismatch'; masked?: string; reason?: 'user'|'recovery';
    provider?: 'github'|'google'; label?: string; browser?: string; network?: string; until?: string; completesAt?: string;
    by?: 'user'|'second_factor_signin'; tokenName?: string } };            // display params per code, schema §7 table
type SecurityLogCode = 'signed_in'|'sign_in_held'|'second_factor_hold'|'key_added'|'key_removed'|'key_blocked'|'key_unblocked'
  |'totp_enabled'|'totp_disabled'|'two_factor_disabled'|'recovery_codes_regenerated'|'password_set'|'password_changed'|'password_reset'
  |'email_change_requested'|'email_change_cancelled'|'email_changed'|'identity_linked'|'identity_unlinked'|'session_revoked'
  |'recovery_requested'|'recovery_confirmed'|'recovery_extended'|'recovery_cancelled'|'recovery_completed'|'run_token_created';
type PasswordPolicy = { minLength: 12; maxLength: 128; unit: 'code_points' };  // revision 6: argon2id only, no byte limit
type EmailChangeLinkPage =                                                       // revision 6, /email-change/{token}
  | { state: 'confirm'; newEmail: string; expiresAt: string;
      needsOld: boolean }                                                        // revision 6.2 (EC-1): true = the old address must confirm too; the button says so
                                                                                 // signed in to the account that asked
  | { state: 'sign_in_required' }                                                // signed out: no address in the props
  | { state: 'other_account'; signedInAs: string }                               // the viewer's own current email
  | { state: 'invalid' };   // expired, used, unknown, replaced or cancelled (checked first, in every session state);
                            // for the owner also: address taken since, recovery open or locked
type EmailChangeOldLinkPage =                                                    // revision 6.2 (EC-1), /email-change-old/{token}
  | { state: 'confirm'; newEmailMasked: string; expiresAt: string;               // signed in to the account that asked; new address masked, never in full
      newHalfDone: boolean }
  | { state: 'sign_in_required' }                                                // signed out: no address in the props
  | { state: 'other_account'; signedInAs: string }
  | { state: 'invalid' };   // expired, used, unknown, replaced, cancelled, or the request has no `old_required`
type AccountDeletionLinkPage =                                                   // revision 6.2 (DL-4), /account-deletion/{token}
  | { state: 'confirm'; expiresAt: string;                                       // signed in to the account that asked
      blocked?: { reason: 'recovery_open'|'recovery_completed'|'last_instance_admin'; until?: string } }
  | { state: 'sign_in_required' }
  | { state: 'other_account'; signedInAs: string }
  | { state: 'invalid' };   // expired, used, unknown, replaced, cancelled, or a second factor was added since
type AccountDeletionView = {                                                     // revision 6, /account/delete
  mode: 'immediate' | 'email_link';                                              // revision 6.2: 'immediate' on 2FA accounts, 'email_link' without a second factor (DL-4)
  pending?: { expiresAt: string };                                               // email_link mode: a link was sent, Cancel available
  email: string;
  projects: { name: string }[]; runs: number; agents: { name: string }[];        // deferred
  ciTokens: number; personalTokens: number; activeRuns: number; pendingEmail: boolean;
  blocked?: { reason: 'recovery_open'|'recovery_completed'|'last_instance_admin'; until?: string } };  // body replaced, no checkbox
type RecoveryReview = { completedAt: string; lockUntil: string;                // PO-J, decision 46
  agents: { id: string; name: string; needsReconfirm: boolean }[];
  ciTokens: { id: string; name: string; projectKey: string; projectName: string; lastUsedAt: string | null }[];
  log: SecurityLogEntry[] };
```

---

## 4. HTTP JSON API (`/api/v1`)

Auth: `Authorization: Bearer wt_pat_…` or `wt_ci_…`. Rate-limited per token. Log scrubbing drops `Authorization` and token endpoint bodies (TM-82).

| Method | Path | Token · ability | Purpose |
| ------ | ---- | --------------- | ------- |
| `POST` | `/api/v1/runs` | CI (`runs:upload`) or personal (`run`) | Upload a finished run document (hosted profile, A-24, ≤ 2 MiB). Server validates schema + PV-11 + Level 1 consistency, allocates `number`, returns `{run_id, number, url, share_url?}`. **Private** unless the body sets `"share": true` (CLI `--share`, PO-C). `run_id` must be a valid engine-created UUIDv7; **first write wins**: same hash → `200` existing run, different hash → `409 {"error":"run_exists"}` (TM-64). Per-token rate limit (TM-61) |
| `GET` | `/api/v1/projects/{projectKey}/captures?limit&before` | personal · `read` | MCP `list_events` |
| `GET` | `/api/v1/projects/{projectKey}/captures/{seq}` | personal · `read` | MCP `get_event` (owner view; body included) |
| `POST` | `/api/v1/projects/{projectKey}/plan` | personal · `read` | MCP `plan_run` (network-free engine `plan`; rate-limited) |
| `POST` | `/api/v1/projects/{projectKey}/runs` | personal · **`run`** | MCP `run_scenarios`. Agent target: only a **declared** target; refused until a person acknowledged NAME-4 and approved the target in the agent's terminal (JSON-3, TM-110). Server target: accounts only, kill switch on |
| `GET` | `/api/v1/runs/{runId}` | personal · `read` | MCP `get_run` (run document) |
| `GET` | `/api/v1/runs/{runId}/findings/{n}` | personal · `read` | MCP `get_finding` |

OpenAPI 3.1 spec generated from these (ADR-002). Responses follow the A-24 objects; probe values in hosted form only (JSON-1, PV-6). **MCP responses mark capture content as untrusted data** (a wrapper field `untrusted_content: true` and the content in a separate field), so instructions inside captured bodies are not presented as tool guidance (TM-110).

---

## 5. Agent ↔ server protocol, v1

Base: `/agent/v1`. TLS required on hosted; the agent pins the origin recorded at pairing and follows **no redirects** (S-4). Every request and response body carries `"proto": 1`. JSON Schemas: `contracts/agent/v1/<message>.schema.json`. Security-relevant objects are closed (`additionalProperties: false`); elsewhere unknown fields are ignored. Every endpoint has a payload size limit.

### 5.1 Pairing

`POST /agent/v1/pair` (no auth; per-address limit 10 failures / 10 min then 1 h block; global breaker; S-1)
```json
{"proto":1,"code":"7KQ2MXR9TD","public_key":"<base64 ed25519>","name":"maya-mbp",
 "version":"0.9.2","os":"darwin","arch":"arm64","proto_min":1,"proto_max":1}
```
`code`: 10 Crockford base32 symbols, case-insensitive, hyphen optional.
→ `201`
```json
{"proto":1,"agent_id":"agt_…","name":"maya-mbp","replaces":null,
 "workspace":{"label":"Guest session · expires 2026-11-15T10:00:00Z","guest":true},
 "server":{"origin":"https://webhooktrap.dev","name":"webhooktrap.dev","kind":"hosted","min_agent_version":"0.9.0"},
 "probe_display_policy":"strict","guest":true,"needs_reconfirm":true}
```
- `name` (AC-3): the name the server kept. A name already used by a live agent of the workspace gets the first free suffix (`maya-mbp-2`); a code issued with `replace` takes the replaced agent's name. The agent shows and stores this name.
- `replaces` (AC-3): `{agent_id, name, key_fingerprint}` of the agent this pairing revokes at confirmation (the owner issued the code with `replace`), else `null`; same shape as the `pairing.completed` broadcast. The client shows it in the `[y/N]` prompt.
- `server.kind` (A09 decision 6): `hosted` or `selfhost`; a server running with `APP_ENV=local` reports `selfhost`. The same `server: {kind}` is in the hello answer (§5.3). The client refuses an `--insecure-dev-origin` (plain http://) origin when the kind is `hosted`.
- `server.origin` is informational; the agent prints the origin **it** connected to (its own configuration) before the label, and refuses if they differ.
- The agent requires the local `[y/N]` (ADR-010 §4 rule 5) **before** it sends its first hello. On `N` it calls `DELETE /agent/v1/agent`.

Errors: `400 {"error":"invalid_code"}` for unknown, expired, used or voided codes, and for a code whose workspace owner is no longer live (tombstoned account, DL-2) (one code for all); `426 {"error":"agent_too_old","min_agent_version":"0.9.0"}` (also for `0.0.0-dev` builds, see "Agent review fixes"); `429` with `Retry-After`.

### 5.2 Authentication (S-3, TM-33)

`POST /agent/v1/auth/challenge` `{"proto":1,"agent_id":"agt_…"}` → `{"nonce":"<base64 32 bytes>","expires_at":"…"}` (60 s, single use, bound to `agent_id`)

`POST /agent/v1/auth/token` `{"proto":1,"agent_id":"agt_…","nonce":"…","signature":"<base64 ed25519>"}` → `{"access_token":"…","expires_at":"…","channel":"private-agent.{agentId}","broadcast":{"host":"…","key":"…","auth_endpoint":"/agent/v1/broadcasting/auth"}}`

Signed message (bytes, each field length-prefixed with a 2-byte big-endian length):
```
"webhooktrap-agent-auth/v1" ‖ server_origin ‖ agent_id ‖ nonce (raw 32 bytes) ‖ expires_at (RFC 3339)
```
`server_origin` is the origin pinned at pairing (scheme + host + port, lowercase, default port omitted). A signature made for one origin never verifies at another; a reused nonce fails.

All later calls: `Authorization: Bearer <access_token>` (15 min, stored hashed, revocation checked per request). A revoked agent gets `401 {"error":"revoked","reason":"replaced"}`. An agent whose workspace owner is not live (tombstoned or disabled account, guest session over) gets `401 {"error":"unauthenticated"}` on every request, token issuance included (DL-2).

### 5.3 Confirmation, hello and heartbeat

`POST /agent/v1/confirm` `{"proto":1}` — sent after the local `y` at pairing or after a claim reconfirmation. Clears `needs_reconfirm`; for a guest workspace, revokes the previous agent in the same transaction. Until then every job, capture and verdict endpoint answers `403 {"error":"needs_reconfirm"}`. **Expiry (RE-1, R-E):** an agent not confirmed within 10 minutes of `pair` is revoked server-side (`401 {"error":"revoked","reason":"unconfirmed"}` on its next call; it must pair again). Before first confirmation only `confirm`, the auth endpoints and `DELETE /agent/v1/agent` succeed; `hello` is refused (`403 needs_reconfirm`), so the server stores no mirrors (secret names, targets, probe hash) and returns no `pending_jobs` or `signature_checks_pending`.

`POST /agent/v1/hello` (on connect, then every 20 s)
```json
{"proto":1,"version":"0.9.2","proto_min":1,"proto_max":1,
 "secrets":[{"provider":"stripe","env_name":"STRIPE_WEBHOOK_SECRET"}],
 "targets":[{"scheme":"http","host":"localhost","port":3000,"kind":"local","declared":true,"approved":true}],
 "probe_config_hash":"<hex or null>","busy_run_id":null}
```
→
```json
{"proto":1,"server":{"kind":"hosted"},"guest":true,"needs_reconfirm":false,"workspace":{"label":"…"},"probe_display_policy":"strict",
 "min_agent_version":"0.9.0","deprecation":null,"pending_jobs":1,"signature_checks_pending":4}
```
- `secrets[]` holds names only; the object is closed and **`hint_last4` is forbidden** (S-8, PO-D). `env_name` must match `^[A-Z_][A-Z0-9_]{0,63}$` (TM-39).
- The server rejects a hello whose body contains any field outside the schema. A hello is accepted only after the first confirmation (RE-1); the `hello` response carries job and check counts only for confirmed agents.
- `targets[].host` is shown to the owner only, never on share views.
- When `needs_reconfirm` is true (after a claim), the agent shows the new label and asks `[y/N]` before calling `/confirm`. All server strings are sanitised and length-capped before printing (TM-41).

### 5.4 Jobs

`GET /agent/v1/jobs/next?wait=25` → `204`, `503` with `Retry-After` above the long-poll cap (TM-40; revision 6.3: the cap is a set of cross-process slots, a slot is freed when the holding process dies, and it is sized so FPM or FrankenPHP workers stay free for HTTP traffic: agents at most 50 % of workers, the runner 2. Uncapped, one long-poll held one PHP worker and an unrelated request waited 4.3 s in the spike. Agent and runner tokens are not interchangeable), or
```json
{"proto":1,"job_id":"job_…","kind":"run","run_id":"run_…","run_number":3,"remote":true,
 "requester":{"workspace_label":"…"},"project_key":"Br7nQ2xk9A",
 "selection":[{"capture_id":"cap_…","seq":1,"body_sha256":"…"}],
 "scenarios":["once","twice","shuffled","late","retry","tampered","unsigned","burst","slow_budget"],
 "target_ref":"http://localhost:3000/webhooks/stripe","pack":{"id":"stripe","version":"1.0.0"},
 "fail_on":"high","seed":"1234567890","delivery_count":16,"expires_at":"…"}
```
or, for signature checks:
```json
{"proto":1,"job_id":"job_…","kind":"verify","captures":[{"capture_id":"cap_…","body_sha256":"…"}],"expires_at":"…"}
```
- Only jobs issued to the authenticated agent are returned.
- `GET /agent/v1/jobs/{jobId}/captures/{captureId}/request` → headers + raw body for a capture **referenced by that unexpired job issued to this agent**; anything else → `404` (TM-35). Each fetch is logged per agent. Response body ≤ 5 MiB; the agent verifies `body_sha256`, header count and JSON depth limits.
- `POST /agent/v1/jobs/{jobId}/accept` → `200` once; `409` if already accepted or expired; `404` if not this agent's job. The agent accepts a **remote** run job only after its local checks: target **declared** and approved for (server origin, workspace, scheme, host, port), NAME-4 acknowledged, probe config confirmed, and — for a first approval — an interactive TTY (ADR-010 §6.3). Before the first delivery it prints one line: requester workspace, run number, target, delivery count. Otherwise:
- `POST /agent/v1/jobs/{jobId}/decline` `{"reason":"target_not_allowed" | "needs_confirmation" | "needs_reconfirm" | "busy" | "agent_too_old"}` → run becomes Couldn't start with that reason (RUN-1), or stays Queued for `needs_confirmation` / `needs_reconfirm` until the person confirms in the terminal (UI shows "Confirm in your terminal") **[G2: copy]**.

### 5.5 Observations

`POST /agent/v1/runs/{runId}/observations` (idempotent on `seq`; run must be assigned to this agent and Running, TM-37)
```json
{"proto":1,"seq":7,"plan":null,
 "deliveries":[{"n":3,"phase":"responded","scenario":"once","event_ref":"cap_…","mode":"normal","offset_ms":312,
   "signing":{"kind":"resigned"},"status":200,"transport":null,"latency_ms":212,"over_budget":false,
   "response":{"content_type":"application/json","body_sha256":"…","body_bytes":17},
   "state":{"readings":[{"name":"orders.count","changed":true,"matches_expected":null,"flagged":false,
     "display":{"before":"0","after":"1","expected":null}}],"failed":false,"unsettled":false},
   "result":null,"completed":false}],
 "scenarios":[],"findings_provisional":[{"key":"…","type":"…","severity":"high","event_type":"…","evidence":[…]}]}
```
- First batch (`seq` 1) carries `plan` (hosted profile) and `plan_hash`, `probe_entries`, `label_bits`, `probe_display_policy` as applied.
- `seq` ≤ delivery count × 4 phases + scenario and finding updates (cap enforced); batch size capped.
- **Server validation (PV-11):** every `display` value must match PV-2 (`^-?(0|[1-9][0-9]{0,3})$`, `^#[0-9a-f]{6}$`, or `^#[0-9a-f]{12}$` when `label_bits = 48`); PV-5 forms (`^-?[0-9]{1,12}(\.[0-9]{1,4})?$`, `^[A-Za-z0-9_.:-]{1,32}$`) only for owner (non-guest) runs and entries declared `opted_*`. Anything else is stored as `#invalid`, counted, never logged.
- Responses: `202 {"seq":7}`; `409 {"error":"run_final"}` after final; `404` for another agent's run.

### 5.6 Label widening (PV-4)

`POST /agent/v1/runs/{runId}/readings.replace` (idempotent on `seq`)
```json
{"proto":1,"seq":19,"label_bits":48,
 "readings":[{"n":3,"name":"subscriptions.status","display":{"before":"#a1f3c0d2e4b6","after":"#9c0254aa11bc","expected":null}}]}
```
Replaces display values of every listed reading; outcome booleans are unchanged. Triggers a timeline reload for viewers.

### 5.7 Final document

`POST /agent/v1/runs/{runId}/final` — the full run document (A-24, `profile: "hosted"`, `schema_version`, ≤ 2 MiB). The server:
- validates the schema (the hosted profile has **no prose fields**: findings carry `type`, severity, level, event type, scenarios, evidence, `likely_cause_rule`, `framework`, `params`, `measured`);
- recomputes `counts` from `findings[]`, checks `exit_code` against status and `fail_on` (EXIT-1), and checks **Level 1 consistency only** (S-5);
- renders finding prose from `type` + pack version + `params`; selects `citation` from the pack's list for that type (any URL in the document is ignored) (TM-16);
- sets status and provenance; increments the guest run counter if `quota_counted` conditions hold.

**First write wins:** a repeat with the same document hash → `200`; a different hash → `409 {"error":"run_final"}` (TM-37).

### 5.8 Other messages

| Message | Endpoint | Body | Rule |
| ------- | -------- | ---- | ---- |
| Signature verdict | `POST /agent/v1/jobs/{jobId}/captures/{captureId}/signature` | `{"proto":1,"state":"invalid","reason":"body_re_encoded","variant":"compact_json","checks":[{"check":"content_length","passed":true},…],"secret_names":["STRIPE_WEBHOOK_SECRET"]}` | Only for a capture in a **verify job issued to this agent**, once per (job, capture); otherwise `404`/`409` (TM-36) |
| Fixtures report | `POST /agent/v1/projects/{projectKey}/fixtures` | `{"proto":1,"repo":"brindle-app","fixtures":[{"path":"webhooktrap/fixtures/stripe/invoice.paid.json","provider":"stripe","event_type":"invoice.paid","api_version":"2025-…","body_sha256":"…","shape":[["data.object.id","string"],…]}]}` | paths under `webhooktrap/fixtures/` only |
| Self-unpair | `DELETE /agent/v1/agent` | — | the agent deletes its local key for that origin |
| Broadcast auth | `POST /agent/v1/broadcasting/auth` | Pusher auth body (`socket_id`, `channel_name`) | `agent` guard only; only `private-agent.{own id}` (TM-20). **Revision 6.3 (C-3):** answers `200 {"auth":"<key>:<sig>","channel_data":"{\"user_id\":\"agent:<id>\"}"}`; the signature covers `socket_id:channel:channel_data`. Any denial, a revoked agent or a malformed `socket_id` is a bare `403`. A session cookie on this endpoint is ignored. Browser counterpart `POST /broadcasting/auth` (`web`, `guest` guards only) signs `user:<id>` or `guest:<id>` the same way and ignores an agent bearer. Two dedicated controllers; Laravel's `Broadcast::routes()` is not registered. After `agent.revoked` the server calls `terminate_connections` for `agent:<id>` (event-catalogue §1) |

### 5.9 What may never appear in any agent or runner message

Enforced by closed schemas and by contract tests (A11 T1/T4/T10 canaries):

signing secret values (any encoding, any part, including a last-4 hint) · signature header values · request or response bodies (except the capture fetch, server → executor) · probe definitions, `database:`, resolved templates, stderr, reset output · probe label salt · raw probe values · settle/stability/async intermediate readings · local file paths outside `webhooktrap/fixtures/` · finding prose in the hosted profile · **token values and pairing codes** (outside the pairing request and the `Authorization` header).

### 5.10 Versioning

- `proto` major integer; additive changes within v1.
- The run document has its own `schema_version` (semver); the server accepts current and previous minor.
- `min_agent_version` is returned at pair, hello and `426` errors; the UI's "version too old" state reads it (R55).

### 5.11 Free-string grammars (TM-16)

| Field | Rule |
| ----- | ---- |
| Agent name | `^[A-Za-z0-9._-]{1,48}$` |
| Probe name (`as:`) | `^[a-z0-9_.]{1,48}$` (PV-9, PRB-2; C-7: the stricter grammar wins). The agent refuses a config outside it; the server refuses the run document (422) |
| WebAuthn credential name | 1–48 characters, no control, bidi or Unicode tag characters (credential names appear in emails, R-H), rendered as text |
| Fixture path | `^webhooktrap/fixtures/[A-Za-z0-9_./-]{1,200}\.json$`, no `..` |
| Target path | ≤ 512 bytes, printable ASCII, rendered as text |
| Repo name | `^[A-Za-z0-9._-]{1,100}$` |

Values outside the grammar are refused (422), never stored.

---

## 6. Runner ↔ server protocol, v1 (HD-5, ADR-010 §12)

Base: `/runner/v1`, guard `runner`. Auth: `Authorization: Bearer wt_rnr_…` on every call; **source CIDR check required on hosted** (token without `allowed_cidrs` is never issued there, RN-1; a request from another address gets the same `401` as an unknown token), optional on self-host. The runner's own network ceiling is local configuration, not part of any message (below, RN-3). No Reverb access. TLS required; no redirects. Same `proto`, schemas and size limits as §5 unless stated.

| Method | Path | Purpose | Rule |
| ------ | ---- | ------- | ---- |
| `POST` | `/runner/v1/hello` | version, `proto_min/max`; heartbeat every 20 s | version must equal the app's, else `426` |
| `GET` | `/runner/v1/jobs/next?wait=25` | next server-run job (§5.4 run shape, plus `"guard":"public"\|"allowlist"` (a request that can only **narrow** the runner's local ceiling, RN-3), `"allowlist":[{host|cidr, port_from, port_to}]` for B′, and `"limits":{request_timeout_ms, response_cap_bytes}`) | `204` when none or the kill switch is off; long-poll cap → `503` |
| `POST` | `/runner/v1/jobs/{jobId}/accept` | take the job | assigns `runner_id`; `409` if taken or expired |
| `GET` | `/runner/v1/jobs/{jobId}/captures/{captureId}/request` | capture for the job | only captures referenced by that job, **accepted by this runner and while the run is not final**; otherwise `403` and audit (RN-5, TM-35) |
| `GET` | `/runner/v1/jobs/{jobId}/secrets` | **self-host B′ only**: instance secrets for this job | `404` on hosted and for jobs without a B′-permitted initiator. Allowed only after accept **by this runner**, run **Running**, executor `server_allowlist`; the initiator's B′ permission is **re-checked at fetch** (`403` + run aborted if gone); response holds only secrets for the run's provider bound to the matched allow-list entry, or unbound ones; **one successful fetch per job**, claimed atomically by setting `run_jobs.secrets_fetched_at` in a conditional update before the response is built (schema §6; A09 §12.9) (a second → `409` + alert `RunnerSecretsRefetchBlocked`; after final, by another runner or unaccepted → `403`; a claimed fetch whose response is lost is not retried: the job fails `secrets_unavailable`); `Cache-Control: no-store`; audited per call incl. refusals; never logged (RN-6, TM-73, TM-133) |
| `POST` | `/runner/v1/runs/{runId}/observations` | §5.5 | run assigned to this runner |
| `POST` | `/runner/v1/runs/{runId}/readings.replace` | §5.6 | same |
| `POST` | `/runner/v1/runs/{runId}/final` | §5.7 | same; first write wins |

The runner never receives verify jobs, never pairs and cannot read anything not referenced by a job it accepted.

**Local ceiling (RN-3, TM-131).** The runner's widest guard mode is configured on the runner (hosted: `public` only). A job whose `guard` is wider than the ceiling, or whose `allowlist` contains entries not in the runner's local allow-list, is refused by the runner (reported `failed: guard_exceeds_ceiling`); the hosted runner refuses every `allowlist` job. The job's fields can narrow, never widen. The runner also never dials the runner-API origin's resolved addresses or its own networks (ADR-010 §12.1, RN-4). `serve-run` reads no probe/reset configuration from disk or environment (RN-7, ADR-010 §12.5).

**Level 1 only (C-8, ADR-010 §12.5).** A runner job carries no probe or reset configuration; the runner's observations and final document must have `levels: [1]`, no `probe_entries`, no readings and no `label_bits`. Anything else is refused (`422 level2_not_allowed`), and `readings.replace` returns `404` for server runs. On hosted, `jobs/next` never carries secrets, so timestamped providers stay **Needs secret** (SIGN-2). The per-principal, daily and per-destination limits are applied by the app before a job becomes available (ADR-010 §12.3).

---

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §3.2 `POST /new`: CSRF, redirect to the live session | A09 §9.8, TM-11 |
| 2026-11-14 | 2 | §3.2 share: `/s/{shareToken}`, rotate on reshare, owner-only `/r/{runId}`, `no-referrer`, `noindex`, `nosniff` on JSON, per-access props | §9.8, TM-14, TM-15; PO-A |
| 2026-11-14 | 2 | §3.2 raw download headers; §1 text-only rendering, CSP, `__Host-` cookies | §9.8, TM-06, TM-10 |
| 2026-11-14 | 2 | §3.3 `RunHeader.provenance`; server-rendered prose; `PairingCodeView.command` pins `@1`; secret names without last 4 | §9.8, TM-16, TM-17, §7.7; PO-D |
| 2026-11-14 | 2 | §4: `run` ability; CI token from `WEBHOOKTRAP_TOKEN`; size cap; first-write-wins; CI private unless share; MCP untrusted content | §9.8, TM-60..TM-64, TM-110, TM-111; PO-C |
| 2026-11-14 | 2 | §5.1: 10-symbol code; `server.origin`; local confirmation before first hello | §9.8, S-1, S-2; PO-E, PO-F |
| 2026-11-14 | 2 | §5.2: signed message format | §9.8, S-3, TM-33 |
| 2026-11-14 | 2 | §5.3: `hint_last4` forbidden; `env_name` grammar; `/confirm`; `needs_reconfirm` | §9.8, S-8, TM-39, TM-42 |
| 2026-11-14 | 2 | §5.4: job-scoped capture fetch; size limits; decline `needs_reconfirm`; declared targets; local run line | §9.8, TM-35, TM-50, TM-56 |
| 2026-11-14 | 2 | §5.7: first-write-wins; server renders prose; pack citations; Level 1 only | §9.8, TM-16, TM-37, S-5 |
| 2026-11-14 | 2 | §5.8: verdict only within a verify job issued to this agent; agent broadcast auth endpoint | §9.8, TM-36, TM-20 |
| 2026-11-14 | 2 | §5.9: token values and pairing codes added | §9.8, TM-82 |
| 2026-11-14 | 2 | §3.2 `GET /pair?code=` needs its own A09 model | §9.8 |
| 2026-11-14 | 2 | §2 capture host; §1.1 token formats; §5.11 free-string grammars; claim nonce on sign-in; admin B′ rules | HD-7; TM-12, TM-16, TM-60, TM-72; PO-G |
| 2026-11-14 | 2 | §6 new: runner protocol | HD-5, HD-10; PO-B |
| 2026-11-14 | 3 | §3.1 `principal.user` gains `emailVerified`, `twoFactor`, `recoveryPending` | decision 44 |
| 2026-11-14 | 3 | §3.2 sign-in row: password, OAuth, passkey; claim after 2FA, same browser; email-link landing removed | decision 44; PO-I |
| 2026-11-14 | 3 | §3.4 new: authentication routes, WebAuthn ceremonies, `Permissions-Policy` per page, RP configuration, `SecurityView` | decision 44 |
| 2026-11-14 | 3 | §5.11 probe name aligned to PV-9 `^[a-z0-9_.]{1,48}$`; credential name grammar | A01 C-7 |
| 2026-11-14 | 3 | §6 runner: Level 1 only, refusal codes | A01 C-8 |
| 2026-11-14 | 4 | §3.2 share routes: tokens never logged (app logs the route template; proxies mask `/s/…`; note for A10) | RD-1, TM-145 |
| 2026-11-14 | 4 | §3.4: counter rule per spec; `userHandle` must match, flagged refused | WA-1, WA-2, TM-120, TM-121 |
| 2026-11-14 | 4 | §3.4: per-account second-factor failure counter and hold | WA-3, TM-122 |
| 2026-11-14 | 4 | §3.4 headers: `frame-ancestors 'none'` + `X-Frame-Options: DENY` on every app page; `Permissions-Policy` as defence in depth | WA-4, TM-123 |
| 2026-11-14 | 4 | §3.4 recovery route: RC-1..RC-6 conditions and `422 recovery_lock` | RC-1..RC-6, TM-125 |
| 2026-11-14 | 4 | §5.11: credential names strip bidi and tag characters | R-H |
| 2026-11-14 | 4 | §5.3: unconfirmed agents expire at 10 min; no hello or mirrors before first confirmation | RE-1, R-E |
| 2026-11-14 | 4 | §6: source CIDR required on hosted; local ceiling, hosted refuses `allowlist`; capture fetch only while accepted and not final; secrets endpoint conditions (accept, Running, B′ re-check, target-bound, single fetch) | RN-1, RN-3, RN-5, RN-6, TM-130, TM-131, TM-133 |
| 2026-11-14 | 5 | §6 secrets: single fetch claimed atomically via `run_jobs.secrets_fetched_at`; lost response fails the job | A09 §12.9 RN-6 |
| 2026-11-14 | 5 | §3.4 `/confirm-auth`: factor passed here cancels an open recovery (passkey counts); `GET` page props; OAuth re-auth for OAuth-only accounts; second factor required for email change on 2FA accounts | A09 §12.9 RC-1; A03 S-28 |
| 2026-11-14 | 5 | §3.4 new routes: key unblock; connect/disconnect GitHub/Google; set/change password (revokes other sessions); email change request/confirm/cancel; sessions list and revoke; post-recovery review | A03 S-17A/B, S-30, FL-20, FL-23 |
| 2026-11-14 | 5 | §3.4 emailed links: page on `GET`, change on `POST`, token not consumed by `GET`, lifetimes; verify, reset and recovery routes split accordingly; recovery decline and finish routes **[PO-J]** | A03 S-25, S-26, S-29, AC-21.4 |
| 2026-11-14 | 5 | §3.4 password policy and error codes; `SecurityView` gains email, pending email, lock, sessions, security log, review link; `SecurityKeyRow` flag fields; new `SessionRow`, `SecurityLogEntry`, `RecoveryReview` | A03 S-17A, S-30 |
| 2026-11-14 | 5 | §3.2 CI token revoke route | A03 S-30 |
| 2026-11-14 | 5.1 | §3.4 reset row: 2FA accounts still get the 2FA challenge, `reset_link` is no second factor or recent authentication (AR-2); identity disconnect: `recovery_lock` only for identities linked before the request (AR-1) | A09 §12.10 AR-1, AR-2 |
| 2026-11-14 | 6 | §3.4 new: `GET /account/delete`, `DELETE /account` (second factor on 2FA accounts, immediate, refusals, active runs aborted), `AccountDeletionView`; `/confirm-auth` `secondFactorRequired` for `delete_account` | A03 S-17 `delete`, FL-26, O-SS-9 (a); domain §2.10 |
| 2026-11-14 | 6 | §3.4: throttled answer (`errors.form = 'throttled'` + `retryAt`, JSON `429` + `Retry-After`); values in domain §2.1 | O-SS-9 (b) |
| 2026-11-14 | 6 | §3.4: `SecurityLogEntry.action` typed as `SecurityLogCode` with display `params` | O-SS-9 (c); schema §7 |
| 2026-11-14 | 6 | §3.4: argon2id only, 72-byte variant withdrawn; `PasswordPolicy` prop on every password form; `currentPassword` removed from `PUT /account/password` | O-SS-9 (d), (g) |
| 2026-11-14 | 6 | §3.4: idempotent session revoke (`session_already_ended`, `no_other_sessions`, `422 current_session`); §3.1 `flash.code` | O-SS-9 (e) |
| 2026-11-14 | 6 | §3.4: `EmailChangeLinkPage` (confirm, sign-in required, other account, invalid); intended URL in the session, never `next` | O-SS-9 (f) |
| 2026-11-14 | 6 | §3.4: invites through `POST /admin/users/invites` and `/set-password/{token}`; no invite token on `/register`; complete list of emailed token links for A10 | A03 S-20, S-21, S-24; A10 log scrubbing |
| 2026-11-14 | 6.1 | §3.4: `DELETE /account` needs a fresh `delete_account` confirmation (5 min, single use, `422 confirmation_stale`); `POST /confirm-auth` records the purpose; tombstoned accounts are uniform no-ops on reset, resend, recovery, OAuth, passkey | A09 §12.11 DL-1, DL-2 |
| 2026-11-14 | 6.1 | §3.4: `/confirm-auth` failures count toward account limits, no password verification during a hold, uniform hash time for unknown and password-less accounts | A09 §12.11 TH-1 |
| 2026-11-14 | 6.1 | §3.4 "Password policy": leftover "72 bytes / bcrypt" sentence removed | A09 §12.11 ED-1 |
| 2026-11-14 | 6.2 | §3.4: `DELETE /account` on accounts without 2FA sends a confirmation link instead of deleting (DL-4); new `GET/POST /account-deletion/{token}` (1 h) and `DELETE /account/deletion`; `AccountDeletionView.mode` and `pending`; `AccountDeletionLinkPage`; flash code `deletion_link_sent` | decision 47 PO-N, DL-4 |
| 2026-11-14 | 6.2 | §3.4: EC-1: new `GET/POST /email-change-old/{token}` (60 min, same expiry as the new-address link); `EmailChangeOldLinkPage`; `EmailChangeLinkPage.confirm.needsOld`; `SecurityView.pendingEmail.awaiting`; flash codes `email_change_waiting_old`, `email_change_waiting_new`; R-AE revert link stays rejected | decision 47 PO-O, EC-1 |
| 2026-11-14 | 6.2 | §3.4: emailed-token list for A10's log scrubbing gains `/email-change-old/{token}` and `/account-deletion/{token}` | decision 47 |
| 2026-11-14 | 6.3 | §5.8 broadcast auth: response carries `channel_data` (`<type>:<id>`) and the signature covers it; bare `403`; two dedicated controllers, no `Broadcast::routes()` | decision 50; A08 C-3 |
| 2026-11-14 | 6.3 | §3.2: `Run/Show` `timeline` gains `status`; `/s/{shareToken}` prop set has no owner-only props (absent, never lazy) with a seven-shape contract test | decision 50; A08 C-5, TM-14 |
| 2026-11-14 | 6.3 | §5.4 long-poll cap: `503` + `Retry-After` kept, slot design and sizing stated (agents at most 50 % of workers, runner 2) | decision 50; A08 C-8, TM-40 |

---

## Slice 2 notes (workspace and project ownership, A06, 2026-11-16)

Names chosen where §3 is silent, for A07. Shapes are the server contract.

**Routes** (all owner-checked; anyone else and a deleted project get the uniform not-found of §1; a guest or an anonymous visitor on an account-only route such as `/projects` is redirected to `/sign-in`, S-02 guest note; corrected after A11 F-17):

| Route | Who | Answer |
| ----- | --- | ------ |
| `GET /projects` | user | `Projects/Index` `{projects: ProjectRow[]}` with 2+ live projects; 1 → redirect `/p/{key}`; 0 → redirect `/projects/new` (S-15) |
| `GET /projects/new` | user | `Projects/New` `{defaultName: 'My project'}` |
| `POST /projects/new` | user | `{name}` → redirect `/p/{key}`; 422 `errors.name` ("Enter a name.", "Use 80 characters or fewer.") |
| `GET /p/{projectKey}/settings` | owner | redirect to `/settings/capture` (guest: `/settings/danger`) |
| `GET /p/{projectKey}/settings/{capture\|ci\|budgets\|danger}` | owner; guest `danger` only | `Settings/Capture`, `Settings/Ci`, `Settings/Budgets`, `Settings/Danger` with `ProjectSettingsProps`; `capture` adds `retentionOptions` |
| `PATCH /p/{projectKey}/settings/name` | user owner | `{name}` → back, `flash {kind:'success', code:'project_renamed', message: name}` |
| `PATCH /p/{projectKey}/settings/retention` | user owner | `{captureRetentionDays, runRetentionDays}` (values from `retentionOptions`) → back, `flash.code 'retention_saved'`; 422 per field |
| `DELETE /p/{projectKey}` | user owner | soft delete → redirect `/projects`, `flash {kind:'success', code:'project_deleted', message: name}`; hard delete after 24 h |

**Props**

```ts
// Shared (§3.1). currentProject: on /p/{projectKey}/… for the owner only. captureUrl is null until the Capture slice.
currentProject?: { key: string; name: string; captureUrl: string | null };
projectSwitcher?: { key: string; name: string }[];   // signed-in users only; live projects by name (S-00 switcher)

type ProjectRow = { key: string; name: string; captureUrl: string | null;
  deliveries: number | null; lastActivityAt: string | null; lastRunStatus: RunStatus | null;   // null until Capture/Proving
  createdAt: string };
type ProjectSettingsProps = { section: 'capture'|'ci'|'budgets'|'danger'; sections: ('capture'|'ci'|'budgets'|'danger')[];  // guest: ['danger']
  project: { key: string; name: string; isGuest: boolean; captureUrl: string | null;
    captureRetentionDays: number; runRetentionDays: number; defaultVisibility: 'private'|'link' } };
type RetentionOptions = { captureDays: number[]; runDays: number[] };   // Settings/Capture only; today [1,7,14,30] and [7,30,90]
```

**Decisions**

- Rename lives on the `capture` settings page (S-16 lists no page for the name; IA §3 puts the name in Settings). A03 may move it.
- `currentProject.captureUrl` is `string | null` (not `string`) until the Capture slice provisions capture URLs through Workspace's `ProjectCaptureUrls` contract, inside the project's creation transaction (`POST /new` and `POST /projects/new`).
- No undo route for a deleted project in the MVP: the 24 h soft delete keeps the data recoverable by an operator only.
- `AccountDeletionView.projects` is filled (live projects, by name); the other counts wait for their slices.

## Slice 3 notes (capture URLs and HTTP ingestion, A06, 2026-11-17)

Names chosen where §2 and §3 are silent, for A07. Shapes are the server contract.

**Routes**

| Route | Who | Answer |
| ----- | --- | ------ |
| any method · `/c/{captureKey}`, `/c/{captureKey}/{rest…}` | anyone | §2. Served by a global middleware ahead of the router (`ServeCaptureHost`), so every method token reaches ingest and the method stored is `REQUEST_METHOD`, never `_method` or `X-HTTP-Method-Override`. Always `200`, `Content-Type: application/json`, `Cache-Control: no-store, private` (Symfony appends `private`; identical for every case), body `{"received":true}`, none for `HEAD`. Network-level only: `429 {"error":"rate_limited"}` + `Retry-After: 60` per client address (600 / min, A09 decision 1); from a documented provider egress range (`capture.edge_provider_ranges`) the bucket is (address, SHA-256 of the key segment as sent), counted alike for unknown and malformed keys (A09 S3-1), `413 {"error":"too_large"}` above 25 MiB (declared `Content-Length` or bytes read). No cookie, session, CSRF or CORS header. With `CAPTURE_HOST` set: the capture host answers anything but `/c/…` with a bare `404`, and the app host does not serve `/c/…` |
| `POST /p/{projectKey}/settings/capture/rotate` | user owner | → back (the settings page), `flash {kind:'success', code:'capture_url_rotated', message: <new capture URL>}`; the page's `project.captureUrl` and `currentProject.captureUrl` carry the new URL. Guest owner, anyone else, deleted project: the uniform not-found. Throttled per user, 10 / hour (A09 S3-12): back with `errors.form = 'throttled'` (JSON: `429 {"error":"throttled"}` + `Retry-After`). Wayfinder name `project.settings.rotateCaptureUrl` |

**Props** (slice 2 shapes, now filled)

```ts
currentProject?: { key: string; name: string; captureUrl: string | null };   // null only if a project has no active URL (none should)
type ProjectRow = { …; captureUrl: string | null; deliveries: number /* stored captures */; lastActivityAt: string | null /* newest capture */; lastRunStatus: null; … };
type ProjectSettingsProps = { …; project: { …; captureUrl: string | null } };
// Capture/Stream (GET /p/{projectKey}): `captureUrl: string | null` added beside `project: {key, name}`; captures, groups and providers come with S-03.
```

The capture URL is `CAPTURE_PUBLIC_ORIGIN` (else `APP_URL`) + `/c/` + the 12-character key. Types stay `string | null` so A07 needs no change; the null-state copy is now unreachable for live projects (every project gets a URL in its creation transaction, and the migration backfilled existing ones).

**Decisions**

- `forwarded` (jsonb) holds `{trusted, observed: [name, value][], removed: string[]}`: the forwarding headers as received and the names of our own proxy headers removed from `headers` (§9.4). `X-WT-Edge` and `X-WT-Verified` are never stored, whoever sent them.
- Trust: `PROXY_TRUST_MODE=secret` trusts forwarded headers only with `X-WT-Verified: 1` (set by the image Caddyfile); `ip` trusts a peer in `TRUSTED_PROXIES` and walks `X-Forwarded-For` right to left past trusted addresses; `none` never trusts. App default `none` (A09 S3-5); the image sets `secret` explicitly (Dockerfile ENV, exported by the entrypoint to Caddy and PHP alike) and `compose.production.yaml` sets it on the app services.
- `public_origin`: `CAPTURE_PUBLIC_ORIGIN` (`configured`); with a trusted proxy and `CAPTURE_TRUST_FORWARDED_ORIGIN=true`, the scheme from `X-Forwarded-Proto` and the configured host (`forwarded_trusted`); unconfigured: the request's own scheme and `Host` (`direct`).
- Header values that are not valid UTF-8 (obs-text) are stored with U+FFFD in place of the bad bytes (`jsonb` holds UTF-8 only). Redacted values count the original bytes. Redacted names (A09 decision 3, exact, case-insensitive): `Authorization`, `Proxy-Authorization`, `Cookie`, `X-Gitlab-Token`, `X-Telegram-Bot-Api-Secret-Token`, `X-Hook-Secret`, `X-Api-Key`, `Api-Key`, `X-Auth-Token`; signature headers are kept.
- Quota bytes are `captures.counted_bytes` (A09 S3-7): the kept body plus the variable-length request facts stored with it (headers JSON, raw target, path, query, public origin, forwarding facts, content type), so a body over 5 MiB still counts its metadata. Guest sessions also stop at 50 MiB of counted bytes (A09 S3-8, value awaiting the PO). The global guest budget (`instance_counters.guest_stored_bytes`, 5 GiB) gets a session's bytes back through a database trigger when the session ends, is claimed or is deleted; at claim, each project's `stored_body_bytes` becomes the sum of its captures' `counted_bytes` and `captures_today` today's count (A09 S3-9).
- The store transaction re-reads the capture URL `FOR SHARE` after the quota locks and drops with `rotated` unless it is still `active` (A09 S3-6), and sets `lock_timeout` (2 s, A09 S3-10); a timeout drops as `store_error`, same answer. `capture_bodies` refuses updates and the facts trigger covers `id` and `counted_bytes` (A09 S3-13).
- Account retention: `PurgeExpiredCaptures`, daily at 03:15 on `maintenance`, deletes account captures past `capture_retention_days` and gives their `counted_bytes` back to the project quota. Guest expiry and run retention stay with the Retention slice.
- Not built here: the derivation job (provider detection needs Packs), signature verdicts (agent verify jobs), broadcasts, the capture stream S-03 and the inspector. The columns exist; `CaptureReceived` is raised after commit with no listener yet.

## Slice 4 notes (capture stream, delivery detail, live updates, A06, 2026-11-18)

Names chosen where §3 is silent, for A07. Shapes are the server contract.

**Routes** (all owner-checked: another user, another guest, an anonymous visitor, a deleted project and an unknown capture number get the uniform not-found of §1; an unverified user is redirected to `/verify-email`; `n` is the capture number `seq`, digits only, leading zeros allowed)

| Route | Who | Answer |
| ----- | --- | ------ |
| `GET /p/{projectKey}` | owner (user or guest) | `Capture/Stream` (props below). Wayfinder: `project.show`, action `CaptureStreamController.index` (the route moved from `GuestWorkspaceController.show`). A guest whose session has just expired is still redirected to `/expired` |
| `GET /p/{projectKey}/d/{n}` | owner | `Capture/Detail` `{project, capture: CaptureDetail, prev: number \| null, next: number \| null, tab}`. Wayfinder `project.delivery` |
| `GET /p/{projectKey}/d/{n}/raw` | owner | the exact kept bytes. `Content-Type: application/octet-stream`, `Content-Disposition: attachment; filename="delivery-NN.bin"`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: sandbox; default-src 'none'; frame-ancestors 'none'`, `Cache-Control: no-store, private`, `Referrer-Policy: no-referrer`, `Cross-Origin-Resource-Policy: same-origin`. Not found when the body was not kept. Link to it with a plain `<a download>`, never an Inertia visit. Wayfinder `project.delivery.raw` |
| `POST /broadcasting/auth` | `web` user (verified) or `guest` | Pusher auth for `private-project.{projectUuid}` (owner of the live project) and `private-session.{guestSessionUuid}` (that guest). Body `socket_id`, `channel_name` (form or JSON). `200 {"auth":"<key>:<sig>","channel_data":"{\"user_id\":\"user:<uuid>\"}"}` (`guest:<uuid>` for guests), the signature over `socket_id:channel:channel_data` (§5.8, decision 50 C-3). Anything else, including public, presence, encrypted, `private-agent.*` and `private-workspace.*` (not built yet), a malformed socket id and missing Reverb credentials: bare `403`, empty body. CSRF applies (web group): the Echo authorizer must send the `X-XSRF-TOKEN` header read from `__Host-XSRF-TOKEN`. Laravel's `Broadcast::routes()` is not registered |

**Query state on `/p/{projectKey}`** (IA §5.2): `sel` (`01`, `#01` or `1`), `tab` (`verdict` · `raw` · `decoded`, default `verdict`), `view` (`grouped` · `flat`), `q`, `group` (a group key, or `ungrouped`), and `before` (a seq, for older pages only). Malformed values fall back to the defaults; nothing answers 422. `q` (max 200 characters) matches event type, provider, path and method (case-insensitive substring), provider event id and object id (exact), and `#n`/`n` (the seq). It never searches bodies or header values: `q` is in the URL (IA §5.2 rule 5).

**Props**

```ts
// Capture/Stream
type StreamProps = {
  project: { key: string; name: string; isGuest: boolean };
  captureUrl: string | null;                                   // live: project.capture_url
  query: { sel: number | null; tab: 'verdict'|'raw'|'decoded'; view: 'grouped'|'flat'; q: string | null; group: string | null };
  captures: {
    latest: CaptureRow[];                                      // live: project.captures; newest first, 50, filtered by q/group
    stats: { total: number; matching: number; lastReceivedAt: string | null; hasOlder: boolean };   // live: project.captures
    older?: { rows: CaptureRow[]; nextBefore: number | null };  // optional: partial reload `only: ['captures.older']` with `?before=<smallest seq shown>`; append; stop at nextBefore null
  };
  groups?: GroupSummary[];                                     // deferred + live: project.captures
  selected: CaptureDetail | null;                              // `?sel=`, else the newest capture; null when none or unknown; not live (new rows never move the selection)
  providers: { id: 'stripe'|'github'|'shopify'|'standard_webhooks'|'curl'; name: string }[];   // Waiting tabs; copy stays client-side
  meter?: GuestMeter;                                          // guest projects: overrides the shared prop, live: project.captures
};
type GroupSummary = { key: string | null /* null = Ungrouped */; provider: string | null; count: number;
  firstSeq: number; lastSeq: number; firstAt: string; lastAt: string; worstSignature: SignatureChip['state'] };   // most recent activity first, max 200
type GuestMeter = { expiresAt: string; secondsLeft: number; captures: { used: number; limit: 100 }; runs: { used: number; limit: 10 } };   // shared prop, `secondsLeft` added

// CaptureRow and CaptureDetail as §3.3, with:
type CaptureRow = { …§3.3 };   // signature: { state: 'not_checked' } until the agent verify jobs; checksPassed/checksTotal only when checks exist
type CaptureDetail = CaptureRow & { id: string /* cap_… */; rawTarget; rawQuery; headers: [string, string][]; redactedHeaders: string[];
  headerFidelity; publicOrigin; publicOriginSource; sourceIp; contentType; bodySha256: string /* hex */;
  body?: { encoding: 'utf8'|'base64'; text: string; truncated: boolean; shownBytes: number; escaped: boolean };   // absent when bodyKept is false
  hiddenCharacters: boolean;                                   // something in this detail was escaped (below)
  signatureChecks?: { check: string; passed: boolean }[] };   // `decoded`, `shape`, `secretNames` absent until Packs and Agent
```

TypeScript twins: `resources/js/types/capture.ts`.

**Live updates** (ADR-004 amendment 1, Inertia v3 Live Props)

- The page JSON carries `liveProps` (Inertia's own metadata): `captures.latest`, `captures.stats`, `groups` and, for guests, `meter` listen for `project.captures` on the private channel `project.{projectUuid}` with `throttle: 250`; `captureUrl` listens for `project.capture_url`. Inertia's client subscribes and reloads exactly those props by partial reload. It needs a live transport (Echo + Reverb), see the package proposal below.
- Server side: `CaptureReceived` (after the ingest commit) and `CaptureUrlRotated` queue one `LiveTrigger` job after commit on the `broadcasts` queue; it broadcasts `{"v":1}` and nothing else. Triggers are coalesced per (channel, event) while one waits in the queue. A dropped capture raises no `CaptureReceived`, so it sends nothing. Failing to queue is reported and swallowed: the capture is stored regardless.

**Decisions**

- **Grouping seam.** Provider detection and GRP-2..4 need Packs. Until the derivation job fills `group_key`, every capture is ungrouped, `groups` is one summary with `key: null`, and `query.view` defaults to `flat`. The default becomes `grouped` by itself as soon as any capture of the project has a group key; `?view=` always wins. Group labels (GRP-3) and providers are the raw derived values until Packs supplies display names.
- **Hidden characters (A09 S3-16).** The server escapes C0 and C1 controls, DEL, bidi and other format characters (`\p{Cf}`: zero-width, BOM, tag characters, soft hyphen) and line/paragraph separators as the visible text `\u{XXXX}` in the method, path, raw target, raw query, header names and values, public origin, content type, event type and body text. Bodies keep tab, line feed and carriage return. A literal `\u{…}` typed by a sender looks the same; `hiddenCharacters` and `body.escaped` say whether anything was replaced. Exact bytes: the raw download. Render every value as text only.
- **Body limits.** Text bodies (valid UTF-8 without NUL) are sent up to 256 KiB, cut on a character boundary; binary bodies (anything else) up to 64 KiB as base64 for the hex view. `truncated` and `shownBytes` say what was cut; `bodySize` is the full size. Config `capture.inspector.*`.
- **Prev/next** step through captures with the same group key; ungrouped captures step through the other ungrouped ones.
- **Older pages** use a seq cursor (`before`) instead of page numbers, so live inserts never shift a page.
- `selected` defaults to the newest capture, which in grouped view is also the newest group's newest delivery (S-03).
- `private-session.{id}` auth is built with this slice; `session.ended`/`session.expiring` broadcasts and `terminate_connections` are still to do (Identity live work).

## Packs slice notes (capture derivation, A06, 2026-11-19)

Shapes are the server contract; TypeScript twins in `resources/js/types/capture.ts`.

**Pack files.** `packs/manifest.json` (pack set version, detection order, one pinned version per pack) and `packs/<id>/<version>.json`, format 1, schema `packs/_schema/pack.schema.json`. Format 1 carries detection, sender name, `event` (type, id, API version locations), `grouping` (GRP-1..4) and `budget`. The engine's draft `agent/pack` (signing, DUP-1, tamper, PRB-4 `objects`, scrub, citations, text) is to be merged into the same files; `budget` and `event` already use its field names. `_schema` starts with `_` so a directory listing of providers skips it.

**Props added**

```ts
type CaptureRow = { …; providerName: string | null; groupLabel: string | null };   // display text
type GroupSummary = { …; label: string | null; kind: string | null; providerName: string | null };
type CaptureDetail = CaptureRow & { …;
  decoded?: { provider; providerName; eventType; providerEventId; apiVersion; objectIds: string[];
              group: { key: string; label: string; kind: string | null } | null; pack: string };   // absent until derived
  shape?: [string, string][] };   // SHP-1, JSON bodies only
// providers: now every pack in detection order, then curl (same ids and shape as before)
```

**Decisions**

- `provider` is the pack id, or **null** when no pack matched (schema.md says `unknown`; null keeps "Unknown" a client label and `?q=` free of a magic word). `derived_with_pack` is `none@<set version>` then.
- `provider_name` names the sender on a scheme pack: Standard Webhooks takes the User-Agent product (`Parcelbird-Webhooks/2.1` → `Parcelbird`), generic HTTP clients excepted.
- Group keys follow RD: `grp_<provider or sender slug>_<template>` (`grp_stripe_sub_…`, `grp_shopify_order_5873201946743`, `grp_github_brindle/brindle-app`, `grp_parcelbird_shp_…`). Merging (GRP-2) re-keys earlier rows; the winner is the best anchor by GRP-3 rank, then key, so arrival order does not matter. Labels prefer a display name (`Order #1042`).
- Provider event ids come from headers where the provider sends them (`X-GitHub-Delivery`, `X-Shopify-Webhook-Id`, `webhook-id`, DUP-1); RD lists null for GitHub and Shopify.
- Shape baselines (SHP-2) are per project, not per workspace, and read the 500 most recent earlier captures; saved fixtures join with the Fixtures slice.
- Derivation runs on `default` (`DeriveCapture`), raises `CaptureDerived` (with `projectId`) and sends `project.captures` again. `captures:rederive [--all] [--provider=] [--project=] [--now]` re-derives stale captures, one batched job per project on `maintenance`; scheduled hourly.

## Agent connection notes (agent ↔ server, runs stored server-side, A06, 2026-11-20)

Decision 64, server side. Schemas: `contracts/agent/v1/*.schema.json` (JSON Schema 2020-12, shared with A08's client; the server validates every request body against them with a closed-object subset validator). TypeScript twins: `resources/js/types/run.ts`.

**Routes (agent, outside the web group, bearer `wt_agt_` + 40 base62, 15 min)**: §5.1–§5.8 as written, plus `POST /agent/v1/jobs/{jobId}/abort {"proto":1,"reason":"capture_verification_failed"|"target_unreachable"|"interrupted"|"internal_error"}` (an accepted job the agent cannot finish → run Aborted with that reason; 409 if not accepted or already finished). Every success body carries `"proto": 1`; errors are `{"proto":1,"error":<code>,…}`. Codes beyond §5: `422 schema` (+ `detail` path), `409 agent_limit`, `409 key_in_use`, `426 proto_unsupported`, `401 invalid_challenge | invalid_signature | unauthenticated`, `409 job_unavailable`, `409 not_running`, `422 seq_out_of_range | run_mismatch | provider_mismatch | fail_on_mismatch | counts_mismatch | severity_mismatch | unknown_delivery | level1_inconsistent | exit_code_mismatch | unknown_pack`, `413 too_large`, `503 busy` + `Retry-After`.

**Routes (web)**: `POST /pairing-codes` → `201 {code, expiresAt, command}` (one live code per principal; 10 per 10 min). `GET /r/{runId}` (+ `/D{n}`, `/F{n}`) → `Run/Show`, the `/r/example` shape plus `access: 'owner'`, `share`, `live: {channel: 'run.<uuid>'} | null` (null once final) and `findings[].provisional` while Running; `timeline` is a closure prop for partial reloads. `GET /p/{projectKey}/runs` → `Runs/Index` `{project, runs: RunRow[] (live on project.runs, 1 s), filters}`. `GET /p/{projectKey}/runs/new?from=01,03` or `?group=` → `Runs/Draft` `{project, selection: CaptureRef[], preview: PlanPreview | null, previewError: string | null, refusals, targets: RunTarget[], agent, agents, pairing: null, scenarios, failOn}`; `preview`/`previewError` are lazy props (partial reloads of the agent props never plan). `POST /p/{projectKey}/runs` `{from, scenarios?, target: {kind: 'agent', agentId, targetRef}, failOn?}` → redirect `/r/{runId}`; a refusal → back with `errors.run = <code>` (JSON clients: `422 {"error": …}`). `GET /p/{projectKey}/runs/{n}` → redirect. Channels added: `private-run.{uuid}` and `private-workspace.{uuid}` (owner), `private-agent.{uuid}` through `/agent/v1/broadcasting/auth`.

**Decisions**

- Server-side plan (EngineProcess slice 1, 2026-11-17): the draft's `preview` is the engine sandbox's `plan` over every scenario (`RunPlanner` → `Engine` port → `serve-sandbox` socket), validated field by field (S-11, TM-74): `PlanPreview {deliveryCount 0..50, planHash (hex), needsSecret, pack {id, version}, budget {ms, source}, scenarios [{name, na: code|null, deliveries}], events [{ref: capture public id, eventType, primary}]}`; the page sums the chosen scenarios. Captures go in as the agent plans them: ref = capture public id, order = seq, headers in received order minus the redacted ones, raw body; `has_secret` false, no project budget. No preview while the selection is refused (`previewError: null`); otherwise a failure gives `preview: null` + `previewError` (`engine_unavailable`, `timeout`, `busy`, `bad_reply`, `frame_too_large`, `rate_limited`, `not_plannable`, or an engine code matching `^[a-z0-9_]{1,64}$`). TM-18: engine calls are limited to `engine.plan_per_minute` (20) per principal; answers (previews and non-transient codes) are cached 10 min by capture refs, orders, body SHA-256, header-list SHA-256, scenarios, pack id+version, has_secret and budget.
- `delivery_count` at run creation comes from that plan (the cached draft preview summed over the chosen scenarios, else a fresh plan of the chosen ones); when the engine cannot answer it stays 0 and the job carries `null` as before. The agent still plans and reports it in observations `seq 1`, which overwrites it.
- `RunTarget.targetRef` is the declared origin (`http://localhost:3000`); the POST may add a path (≤ 512 printable ASCII). Scheme, host and port must match a target the agent declared (TM-50).
- Agent names are unique among *confirmed* agents of a workspace, keys among *live* agents. ~~A re-paired machine replaces its older same-name agent at confirmation~~: withdrawn by the agent review (AC-3), see below.
- Provisional findings live in `runs.provisional_findings` (keyed by merge key) until the final document; final findings are rows numbered in document order (FND-6). First-write-wins hashes the raw request body.
- Hosted document: findings carry no prose and no citation (the schema refuses them, TM-16); `not_checked` is `[{code, object_types?}]`, worded by the server; `params.budget_label` is accepted, never shown. Display values outside PV-11 are stored as `#invalid`.
- Final checks: run id, provider and `fail_on` equal the job's; counts recomputed; severity per SEV-1; evidence names real deliveries; Level 1 types agree with offending statuses; exit code per EXIT-1.
- Claim moves agents (Workspace calls `WorkspaceMoveParticipant` before deleting the guest workspace): `needs_reconfirm`, approvals cleared. An *empty* guest workspace is discarded with its agents.
- Long-poll slots are PostgreSQL session advisory locks (`LongPollSlots`), sized by `LONGPOLL_AGENT_SLOTS` (default 4); per-agent and per-workspace caps since AC-5, below.
- New secret `AGENT_PAIRING_HMAC_KEY` (`_FILE` in containers; A10 adds it to the entrypoint list). Pairing fails closed without it.
- Not built: `readings.replace`, verify jobs and signature verdicts, fixtures report, `agentPill`, account `/agents` pages, guest `/p/{key}/agent`, `terminate_connections` on revoke, share pages, CI upload, server runner.

## Agent review fixes (A09 agent connection review, server side, A06, 2026-11-20)

Fixes for `project/security/agent-connection-review.md` AC-3 to AC-8, AC-10, AC-12, AC-14 and decisions 3 and 6. Migration `2026_11_20_000002_agent_review_fixes`.

- **AC-3, re-pairing.** No silent same-name replacement. At `pair`, a name used by any live agent of the workspace gets the first free suffix (`maya-mbp-2`, base shortened to stay within 48 characters; case-insensitive); the pair answer carries the kept `name`. A replacement happens only when the owner asks for it: `POST /pairing-codes {replace: "agt_…"}` (a live, confirmed agent of the principal's workspace, else `422 unknown_agent`) binds the code to that agent (`pairing_codes.replaces_agent_id`); the agent paired with it takes the replaced agent's name and revokes it (`replaced`) in its confirmation transaction. The code answer carries `replaces: {agentId, name, keyFingerprint} | null`, and the `pairing.completed` broadcast `replaces: {agent_id, name, key_fingerprint} | null`, so the browser shows both fingerprints. A guest workspace still keeps one confirmed agent (A-13). A name taken meanwhile by another confirmed agent is suffixed at confirmation.
- **AC-4, account deletion.** `AgentAccountDeletion` and `ProvingAccountDeletion` are `identity.deletion-participants`. Tombstone (same transaction): live agents revoked `account_deleted` (their open runs end Aborted `account_deleted` through `AgentRevoked`), pairing codes and access tokens deleted, every open run of the account's projects Aborted `account_deleted` with its jobs completed. Agent auth (token and every bearer request) refuses an agent whose workspace owner is not live (`401 unauthenticated`); `pair` answers `invalid_code` for such a workspace. Purge: agents, codes and runs deleted (FK cascades cover the rest). The `AccountDeleted` listener stays as a no-op backstop.
- **AC-5, long-poll fairness.** `jobs/next` takes three slots: one per agent (a second concurrent poll of the same agent answers `204` at once), at most 2 per workspace (`agent.longpoll.per_workspace`), and the global pool, whose size is `min(LONGPOLL_AGENT_SLOTS, workers / 2, workers − 2)` (at least 1) with `workers` = `LONGPOLL_HTTP_WORKERS` (default 8, the app's FrankenPHP `num_threads`). A full workspace or global pool answers `503 busy` + `Retry-After`. Per-key locks use the single-bigint advisory lock space (SHA-256 of the key), which never conflicts with the shared pools.
- **AC-6, final document integrity.** New `422` codes: `delivery_count_mismatch` (deliveries ≠ the planned count from observations `seq 1`, or a repeated `n`), `observation_mismatch` (a delivery stored as completed differs: not completed, status, transport, latency beyond `agent.final.latency_tolerance_ms` = 5, probe failure or a reading's `changed`), `level_mismatch` (level not allowed for the type, `FinalDocument::LEVELS`; `accepts_*` at level 2 on a 2xx), `level1_missing` (for `findings`/`inconclusive`/`passed` documents: a completed, reachable delivery implies a Level 1 finding with it as offending evidence that is absent — valid mode: `server_error` on 5xx or timeout/reset, `non_2xx_on_valid` on 3xx/4xx except D1 with `baseline_rejected`, `over_budget` (or `cold_start` for D1) when observed over budget or latency > `run.budget.ms`; `late`/`tampered`/`unsigned`: `accepts_*` on 2xx, `error_on_invalid` on 5xx or timeout/reset). `finished_at` is clamped to `[started_at, now]`.
- **AC-7, detective control.** `AgentPaired` (now with `name` and `network`, the source address cut to /24 or /48) queues the Identity mail `agent-paired` to a user owner: params `machine`, `os`, `key_fingerprint`, `time`, `network`; action URL `/agents/{agentId}` (the agent page, which has Revoke; A03 owns the copy). Guests get nothing. Browser revoke: `DELETE /agents/{agentId}`, §3.2.
- **AC-8.** A `needs_confirmation` / `needs_reconfirm` decline sets `run_jobs.offer_after` = now + 30 s (`agent.jobs.decline_hold_seconds`); `jobs/next` skips the job until then. `hello` still counts it in `pending_jobs`.
- **AC-10.** `jobs/next` cleans `requester.workspace_label` with `TerminalText::clean`, as `pair` and `hello` do.
- **AC-12.** Observation batches: `seq` ≤ min(`max_batches` 220, planned × 4 + 20) (50 planned while unknown); total body bytes per run ≤ 8 MiB (`runs.observation_bytes`, `413 observations_exceeded`); provisional findings ≤ 50 distinct keys per run (`422 too_many_provisional`) and severity per SEV-1 (`422 severity_mismatch`).
- **AC-14.** Default `AGENT_PAIR_COMMAND`: `webhooktrap pair {code} --server {origin}`.
- **Decision 3, dev builds.** `0.0.0-dev` gets `426 agent_too_old` at `pair` and `hello`, unless `AGENT_ALLOW_DEV_BUILDS=true` **and** `APP_ENV` is `local` or `testing`; each accepted dev handshake logs the warning `Agent dev build accepted`. Release builds below `min_agent_version` are refused whatever the switch.
- **Decision 6.** `server.kind` (`hosted` | `selfhost`) in the pair answer (existing field) and, new, `server: {kind}` in the hello answer (`hello.response.schema.json`), the shape A08's client reads (f71c7a5). `APP_ENV=local` reports `selfhost`, so a local http:// dev server can be paired; hosted and production self-host report their real kind.
- **Abort reasons.** `abort.request.schema.json` accepts `capture_verification_failed`, `target_unreachable`, `interrupted` and `internal_error` (the client sends the first, third and fourth).
- **For A08.** The pair answer's `name` is the name the server kept (suffixed on a collision, or the replaced agent's name); the client currently stores its own requested name and should store and print this one.
- **For A07 (UI, not built here).** Props: `PairingCodeView.replaces` (above, `resources/js/types/run.ts`). The pairing toast shows `key_fingerprint` and, when `replaces` is set, the replaced agent's name and fingerprint, with "Not your machine? Revoke" → `DELETE /agents/{agentId}`. The `/agents` pages (`AgentRow`/`AgentDetail`, still to be served) carry the same Revoke; `/agents/{agentId}` is the link in the email.
- **For A10.** `AGENT_PAIRING_HMAC_KEY` must be in the entrypoint's `_FILE` list. New optional names: `LONGPOLL_HTTP_WORKERS` (set it with the app's `num_threads`), `AGENT_ALLOW_DEV_BUILDS` (never set on hosted or production self-host). Session advisory locks need a session-pooled connection.

## A07 end-to-end notes (agent pages, run props, A06, 2026-11-20)

Gaps found in A07's browser run (UI commit 9d1ab71). TypeScript: `resources/js/types/run.ts` (`AgentPill`, `AgentRow`, `AgentDetail`, `Agents*Props`, `AgentGuestProps`, `RunHeader.deliveryCount`, `RunShowProps.project`).

- **`agentPill` (§3.1)** is shared on every page for a user or guest principal (null when anonymous), about its own workspace only: `{state: 'none'}` without a live agent, else the most useful live agent (connected, then awaiting confirmation or reconfirmation, then disconnected) as `{state, name, version}`; `too_old` when its build is below `min_agent_version`. Lazy; never creates a workspace.
- **Agent pages (§3.2).** `GET /agents` → `Agents/Index {agents: AgentRow[] (live: private-workspace.{id}, event workspace.agents, 1 s), pairing: null}`; `GET /agents/pair[?replace=agt_…]` → `Agents/Pair {agents, pairing: null, replace: agentId | null}`; `GET /agents/{agentId or name}` → `Agents/Show {agent: AgentDetail}` (the id is tried first, so an agent named `pair` is reached by id); `GET /p/{projectKey}/agent` → `Agent/Guest {project, agent: AgentDetail | null (live), pairing: null}` for a guest owner, a user owner is redirected to `/agents`. Users only for `/agents*`; anyone else, a foreign or revoked agent: 404. `pairing` is null on every page by design: the page asks `POST /pairing-codes` (optionally with `replace`), so a reload or prefetch never rotates a code already copied. Revoke: `DELETE /agents/{agentId}` (§3.2). `AgentRow = AgentSummary & {tooOld, pairedAt, lastSeenAt, revokeUrl}`; `AgentDetail = AgentRow & {arch, targets[], secretNames: {provider, envName}[]}` (names only, S-8).
- **Per-delivery `result`.** The final document now writes each delivery's verdict (`result`, `phase: 'judged'`, judged readings) also onto rows already completed by observations; their measured facts (status, transport, latency, response, mode, signing, …) stay as observed (checked equal, AC-6). The database trigger allows exactly that once: a completed row's measured columns never change, and once `phase = 'judged'` the row is fully immutable (migration `2026_11_20_000003_run_delivery_verdicts`). `finding` (the offending finding number) was already derived from the stored findings.
- **`run.deliveryCount`**: planned deliveries (`runs.delivery_count`: the job's count or the agent's plan from observations `seq 1`), `null` while unknown.
- **`project: {key, name, isGuest}`** on owner `Run/Show` (users and guests), for the "Runs" and "Run again" links. Not on share views.
- **Agent API server faults.** An unexpected exception in `/agent/v1/*` is reported and answered `500 {"proto":1,"error":"internal_error"}` with `Retry-After` and `Cache-Control: no-store`, never a framework body. A07's "server answered 500" was `jobs/next` querying `run_jobs.offer_after` before the local database had migration `2026_11_20_000002` (laravel.log, 19:14:54–19:15:12 UTC); it ended with `php artisan migrate`. After pulling backend commits, run the migrations before the agent connects.
- **Target approval mirror.** The approval is mirrored from `hello` (`targets[].approved`), which the agent sends at connect and every 20 s, and nothing refreshed the live agent pages when it changed, so a page loaded after the terminal `y` kept "not yet". Now accepting a browser run marks that run's target approved at once (the agent approves before it accepts, TM-56; `RunAccepted` carries the target), and a `hello` that changes the targets or approvals, or reconnects the agent, triggers `workspace.agents`. The first approval time is kept.
- **Re-paired machines (per spec, no change).** `/agents` lists live agents; revoked ones drop off. Re-pairing without `replace` creates a second agent (`maya-mbp-2`, AC-3) and leaves the old one live, so it shows as disconnected until the owner revokes it (Revoke on its row) or re-pairs with `replace`. Suggested for A08: when `pair` runs on an origin where the client already holds an agent, unpair the old one (`DELETE /agent/v1/agent`) or offer `replace`.
