# Information architecture · Webhook Trap 2.0

**Status:** REVIEW_REQUIRED (P1 to P11 APPROVED, decision 35) · **Author:** A03 (UX/UI) · **Date:** 2026-11-14, revised 2026-11-14 for decision 35, for decisions 38–39, for decision 44 (rev. 3) and for A05 revision 5 (rev. 4) · **Phase:** 2 (Gate 2: PO approves navigation and principal UX)
**Concept:** B, Proving Ground · **Direction:** Timetable, Twice logo · **Constraints:** decision 32 (agent in MVP, guest pairing by one-time code bound to the session, hosted replays only to public destinations, server-side private replay only on self-host and off by default, no 1.x data migration; the 3 1.x accounts are imported, decision 41).
**Read with:** `navigation.md` (how people move), `accessibility-spec.md` (A-26, A-27), `golden-files.md` (A-23), `user-flows.md` (flows), `screen-specifications.md` (screens).
**Inputs:** `ux-foundations.md` (UF), `ux-timetable-v2.md` (TT), `ux-timetable.md` (TT1), `realistic-data.md` (RD), `provider-budgets.md` (PB), `fit-review.md`, `qa-timetable-v2.md`, A01's `core-experience.md` (CE), `vision.md`, `personas.md`, `open-questions.md` (OQ), A00's `concept-impact.md` (CIMP), A11's `testability-review.md` (TR), A01's `scenarios-and-judge.md` (SJ; authoritative for the judge, run status, exit codes and names).

This file replaces UF §2 (object model, routes, navigation model) where they differ. Every change to UF is marked **Revises UF**. The PO approved P1 to P11 (§10) with decision 35; the rest of the file is still reviewed at Gate 2.

User flows are in `user-flows.md` and screen specs in `screen-specifications.md`. A01's spec (SJ) has landed, so §11 now records how each dependency closed.

---

## 1. Principles for the structure

| # | Rule | Why |
| - | ---- | --- |
| IA1 | **One object, one name, one id format, on every surface.** The web, CLI, CI log, PR comment, JSON and MCP use the same nouns and the same ids (`#01`, `D3`, `F2`, `Run #3`, `run_01M75…`). | The QA/CI persona reads the PR, the integrator reads the web, the coding agent reads JSON. They must be able to talk about "F2 on D3" without translating. |
| IA2 | **The project is home.** Everything a user does happens inside one project: capture, prove, fix, save, test. Account-level things (agents, tokens, profile) are reached from the shell, not from a second home. | CE §2.5 lists four product screens that all belong to one handler under test. A dashboard of projects would be a page nobody needs in Horizon 1. |
| IA3 | **App URLs and the capture URL are different things.** The capture URL is a public write address you paste into a provider. App URLs are private read addresses. **Revises UF §2.2.** | See §5.1. Mixing them leaks data or breaks capture fidelity. |
| IA4 | **Evidence is addressable.** Every delivery, run delivery and finding has a URL, so U2 ("evidence is linkable") holds outside the app too: CLI, PR, MCP. | UF U2, VI principle 8. |
| IA5 | **Unknown and forbidden look the same.** A URL the viewer may not see answers exactly like one that doesn't exist. | Same rule as public ingest (TR A-15): never leak existence. |
| IA6 | **The repo is the source of truth for fixtures.** The hosted service shows fixture *metadata* and drift. File contents are read through the agent, never stored hosted unless the user uploads them. | VI principle 5, CE §2.7. |
| IA7 | **What the hosted service can't do is shown as a fact with its unlock**, never hidden or greyed out silently. | UF U7, TT §2.3 copy rule. |

---

## 2. Object model, as the user sees it

### 2.1 The objects

| Object | User-facing name | Id shown | Machine id | Belongs to | Lives where | Lifetime |
| ------ | ---------------- | -------- | ---------- | ---------- | ----------- | -------- |
| **Guest session** | "Guest session" | none (the meter is its identity) | `gst_…` (never shown) | the browser (cookie) | hosted / self-host | 24 h from URL creation (A-13, approved) |
| **Workspace** | not shown in MVP | — | `wsp_…` | exactly one principal: a user **or** a guest session (ADR-003, TR R61) | server | as long as its principal |
| **Project** | the project's name ("Brindle Coffee"; a guest's is "Guest session") | name | `prj_…` + a short `projectKey` for URLs | a workspace | server | until deleted; a guest's ends with the session |
| **Capture URL** | "Capture URL" | `webhooktrap.dev/c/Br7nDLk2Qm9x` | `captureKey` (12 chars) | a project (one per project in MVP, §10 P9) | server (ingest) | until rotated or the project ends |
| **Capture** (a received request) | "delivery" in UI copy, numbered `#01` | `#01`, `#02` … (per project, never reused) | `cap_…` | a project | server (bytes, headers, metadata) | project retention; guest: the session |
| **Group** | the related object's short id or name (`sub_1QH…4drI`, `Order #1042`) | object short id | group key from the provider pack (A-17) | a project | derived (a view, not stored by the user) | as long as its captures |
| **Event** | the provider event type and id (`invoice.paid`, `evt_…`) | event type | provider event id | a capture | inside the capture body | — |
| **Run** | "Run #3" | `Run #3` (per project) + `run_01M75F1M56RSH0CVKCTFX4FQWC` | `run_…` (ULID) | a project | server stores plan, observations, verdict and **provenance** (who ran it: agent name, CI token name or server runner, RUN-8); secrets never leave the machine; probe values only as **display values**: counts −9 999…9 999, codes `#a1f3c0` for everything else, opted-in `show:` values on the owner's view (PV-1 to PV-8). The run id grants nothing by itself (PO-A) | project retention |
| **Scenario** | "Once", "Twice", … (9 names in run order, RD §5; A-7) | name | `once`, `twice`, … | a provider pack; selected per run | pack data | versioned with the pack |
| **Run delivery** | "delivery", numbered `D1` | `D5` (per run), always with its source `D5 · from #01` | `run_…/D5` | a run | server (request sent without signature header values, response, timings, per reading `changed`, `matches_expected`, `flagged` and display values; real values, probe definitions and reset output stay local, PV-1, PV-8) | with its run |
| **Finding** | "F2 High · Duplicate delivery created a second order" | `F2` (per run) | finding `type` (SJ §5.2) + `fingerprint` (FND-2) + run id + number | a run | server | with its run |
| **Fixture set** | "Fixtures" (3 fixtures + `webhooktrap.yml`) | file path | path in the repo | a project (metadata) and the repo (content) | **the repo**; hosted keeps names, hashes, shapes, sync state | the repo's |
| **Agent** | its machine name (`maya-mbp`) | name + version | `agt_…` | a workspace (all its projects can use it) | the user's machine; hosted keeps name, version, target allow-list, secret **names** | until revoked; a guest's ends with the session |
| **Pairing code** | "one-time code" | `7KQ2M-XR9TD` (10 Crockford symbols, PO-F) | — | a guest session or a user | server, short-lived | until used or expired (TTL: A-12). The agent asks `[y/N]` after printing the server origin (PO-E). A guest has one agent at a time: pairing a second replaces the first once confirmed (A-13) |
| **Share link** | "Share link" | `webhooktrap.dev/s/q7Vf2LmX9pK4wT8nR3bZcD` | share token (≥ 128 bits, stored hashed) | a run | server | until sharing stops, a new link is created (rotation), the run ends, or a guest session is claimed or expires (PO-A) |
| **CI token** | "CI upload token" | name only (`github-actions`) | `tok_…` | a project | server (hashed) | until revoked |
| **Instance** (self-host only) | "This instance" | host name | — | the self-host admin | the operator's server | — |

### 2.2 How the objects relate

```
Principal ─── user  or  guest session (cookie, 24 h)
   │
   └── Workspace (exactly one per principal in MVP; teams later)
         ├── Agents ──────────── maya-mbp · 0.9.2 · targets allow-list · secret names
         │                       (paired by one-time code; usable by every project)
         └── Projects
               └── Project "Brindle Coffee"
                     ├── Capture URL ─── webhooktrap.dev/c/Br7nDLk2Qm9x  (public, write-only)
                     ├── Captures #01…#13 ─── grouped by related object (Groups, a view)
                     │     └── Capture: Verdict · Raw · Decoded   (holds one provider Event)
                     ├── Runs #1…#4 ─── each made from 1+ captures, via one agent or a server target
                     │     ├── Scenarios (selected from the pack)
                     │     ├── Run deliveries D1…D16  (each "from #01")
                     │     ├── Findings F1…F4  (each cites D-numbers)
                     │     └── Share link (optional)
                     ├── Fixture sets ─── metadata + drift; contents in the repo (read via agent)
                     ├── CI tokens
                     └── Settings (name, capture URL rotation, retention, budgets view)

Instance (self-host only) ─── admin: users, guests, limits, server replay, packs, health
```

### 2.3 Numbering rules (unchanged from UF, made strict)

1. **`#nn` is a capture**, numbered in arrival order within the project, at least 2 digits (`#01`, `#100`), never reused after deletion or retention.
2. **`Dn` is a run delivery**, numbered in send order within one run. A run delivery always shows its source: `D5 · from #01`.
3. **`Fn` is a finding**, numbered within one run: severity first, then the smallest offending D-number, then type name (FND-6). Info findings are numbered too but never counted in "N findings" (RUN-3). F-numbers are final only when the run ends; live views show the title without a number (FND-6).
4. **`Run #n` is per project**; `run_…` is global and is what URLs, CLI, CI and JSON print.
5. The two delivery numberings never appear unqualified in the same sentence. Where both appear (run header, inspector), the capture is always written with `#` and the run delivery with `D`.

### 2.4 The word "delivery" (P3, approved: option A)

The UI currently says "delivery" for both a captured request (`#01`, "13 deliveries") and a request a run sends (`D3`). The numbering already disambiguates, and providers use the same word for what lands on the capture URL (GitHub "Recent Deliveries"), so users already think of `#01` as a delivery.

- **Approved (A):** keep "delivery" in UI copy for both, with `#` and `D` as the disambiguators. Use **capture** only as the machine name (`captures[]` in JSON, `webhooktrap captures` if such a command exists) and in the section name *Capture*. Cost: none; the dataset copy stands.
- **Not chosen (B):** call stored items "captures" everywhere ("13 captures", "Waiting for the first capture"). Cleaner model, but rewrites RD §6 capture copy and every frame's header.

---

## 3. Access modes

| Mode | Who | How they get here | What they own |
| ---- | --- | ----------------- | ------------- |
| **Anonymous** | No session cookie | Landing, docs, a shared run link, a PR comment link, an expired link | nothing |
| **Guest** | Session cookie, no account | "Get a URL, no sign-up" on landing | one workspace → one project → one capture URL; 1 agent at a time, 100 stored captures, 10 runs, 24 h from URL creation (A-13, approved). A run counts once it gets past Once |
| **Authenticated** | Signed in (hosted or self-host) | Sign in, or sign in from a guest session (claims it) | one workspace → many projects, agents, tokens |
| **Self-host admin** | Signed-in user with the instance-admin role, self-host only | Account menu › Instance admin | the instance's settings; not other users' data |
| **Half signed in** (rev. 3) | First factor done, second step pending (password, GitHub or Google with 2FA on) | S-02 → S-27 | nothing: only `/two-factor`, `/account-recovery` and sign-out are reachable; a guest session stays a guest until S-27 succeeds |

Hosted has no admin area visible to users. Operator tooling for the hosted service is out of scope.

### 3.1 Capability matrix

| Capability | Anon | Guest | Auth (hosted) | Auth (self-host) | Admin |
| ---------- | :--: | :---: | :-----------: | :--------------: | :---: |
| See landing, docs, example run | ● | ● | ● | ● | ● |
| Open a shared run (read-only, `/s/{shareToken}`) | ● | ● | ● | ● | ● |
| Get a capture URL | create guest | has 1 | per project | per project | — |
| See captures live, Raw, Decoded, copy as JSON, download bytes | — | ● | ● | ● | — |
| Signature verdict | — | with agent | with agent | with agent, **or** instance secrets if enabled (Q4) | configures |
| Open a run draft (plan, scenarios, citations) | — | ● | ● | ● | — |
| Run against localhost / private hosts **via agent** | — | ● (quota) | ● | ● | — |
| Run against a **public URL from our servers** | — | — (accounts only, PO-B) | P6 (limited), while the kill switch is on (HD-10) | ● | — |
| Run against a **private URL from the server** | — | — | — | only if the admin enabled it **and** granted this user the permission (off by default, PO-G) | enables, allow-lists, grants |
| Save fixtures (written through the agent) | — | with agent | with agent | with agent | — |
| Share a run | — | ● (dies with the session or on claim; no Include probe values) | ● | ● | — |
| CI upload tokens, run history from CI | — | — | ● | ● | — |
| Several projects | — | — | ● | ● | — |
| Instance settings | — | — | — | — | ● |

"P6 (limited)" = approved P6: only scenarios that need no re-signing (§5.4).

---

## 4. Sitemap per access mode

Paths use the URL scheme of §5. `●` = in MVP navigation. `○` = reachable but not in the main navigation. `◇` = out of this round (nav entry or link only).

### 4.1 Anonymous

```
/                           Landing ●
├── /docs/…                 Docs (separate site; linked) ◇
├── /s/{shareToken}         Shared run, read-only ○  (only while the owner shares it; else "not found")
├── /r/example              Example run (read-only Run #3, "Example run") ○  (P7)
├── /sign-in                Sign in ●  (email + password, GitHub, Google, passkey; email link dropped; accessibility-spec G14, WCAG 3.3.8)
├── /register               Create account ○  (S-24)
├── /verify-email           Check your inbox; /verify-email/{id}/{hash} link page with Confirm email ○  (S-25, S-32)
├── /forgot-password        Reset your password ○  (S-26)
├── /reset-password/{token} Choose a new password; "Set a password" for imported 1.x accounts ○  (S-26)
├── /two-factor             Two-step challenge, half signed in only ○  (S-27)
├── /account-recovery       Recover your account (hosted only; 404 on self-host) ○  (S-29)
│   ├── /{token}            Link page: start the wait or decline; waiting ○
│   └── /finish/{token}     Set a new password (24 h link) ○
├── /email-change/{token}   Confirm new email, or on an account without two-step sign-in also Confirm email change at the old address (link page; asks to sign in first) ○  (S-32, rev. 4, rev. 5; one route, A05 tells the two apart by token)
├── /account-deletion/{token}  Delete my account (link page, accounts without two-step sign-in; asks to sign in first) ○  (S-32, rev. 5, DL-4) [pending A05 route; A03 proposes this path]
├── /new  (POST)            Create a guest session → /p/{projectKey}
├── /expired                Guest session expired ○
└── /pair                   Pair from the terminal (CLI-initiated pairing) ◇  (P10: after the A05/A09 pairing model)
```

### 4.2 Guest

```
/p/{projectKey}                       Capture (home) ●
├── ?sel=01&tab=raw                   …with a capture selected
├── /d/01                             Capture detail, full page ○ (mobile, deep link)
├── /runs                             Runs ●
├── /runs/new?from=…                  Run draft ●  (via Prove)
│   → /r/{runId}                      Run (canonical, see §5.3) ●
│       /r/{runId}/D3 · /F2           …with a run delivery or finding open ○
├── /fixtures                         Fixtures ●  (needs the agent; explains why)
└── /agent                            Agent: pair, status, secret names ●
Shell: guest meter · Sign in to keep · End session now (deletes the data) ○
```

### 4.3 Authenticated

```
/projects                             Projects (list; only shown with 2+ projects, else redirect to the one) ○
/projects/new                         New project → waiting state of its Capture ●
/p/{projectKey}                       Capture (home) ●
├── /d/{n}                            Capture detail, full page ○
├── /runs                             Runs (all sources: web, CLI, CI) ●
├── /runs/new?from=…                  Run draft ●
├── /fixtures                         Fixtures ●
└── /settings                         Project settings ●
    ├── /settings/capture             Capture URL (copy, rotate), retention
    ├── /settings/ci                  CI upload tokens
    ├── /settings/budgets             Budgets by provider with source tags; read-only, shows a `budget:` override from `webhooktrap.yml` when the agent reports one (BUD-2)
    └── /settings/danger              Delete project
/r/{runId}                            Run (canonical) ●
/agents                               Agents (all in the workspace) ●
├── /agents/{name}                    One agent: status, version, targets, secret names, revoke ○
└── /agents/pair                      Pair a new agent (one-time code) ●
/account                              Account ●
├── /account/security                 Security: password and email, passkeys and security keys, authenticator app, recovery codes, linked accounts, where you're signed in, recent security activity (rev. 3; rev. 4 adds the last two and email change)
│   └── /account/security/review      After recovery: what changed, agents and CI tokens to check, security log ○ (until the lock ends)
├── /account/tokens                   Personal tokens (CLI, MCP)
├── /account/preferences              Shortcuts, announcements, motion
└── /account/delete                   Delete account (route confirmed by A05 revision 6; rev. 5: "Check your email" state on accounts without two-step sign-in)
/confirm-auth?next=                   Confirm it's you (recent authentication, 10 min) ○
Shell: recovery banner with "Cancel recovery" on every page while a recovery is open
```

### 4.4 Self-host admin (in addition to 4.3)

```
/admin                                Instance overview: version, health (database, queue, broadcast), storage ●
├── /admin/access                     Sign-up policy (open / invite / closed), guest mode on/off, guest limits
├── /admin/users                      Users (list, role, disable); never their data
├── /admin/limits                     Body size limit, retention, run delivery cap (A-21)
├── /admin/server-replay              Server-side replay: public on/off; **private destinations off by default**, allow-list of hosts/CIDRs, warning
├── /admin/secrets                    Instance signing secrets (optional, Q4): write-only fields, names shown after save ◇
├── /admin/agents                     All paired agents on the instance, revoke ◇
├── /admin/packs                      Installed provider packs and versions ◇
└── /admin/audit                      Audit log of admin actions ◇
```

**Revises UF U6** ("no screen has a field for a signing secret") for self-host only, **after the MVP**: `/admin/secrets` will have write-only fields because Q4 allows instance secrets on self-host. Hosted keeps U6 absolutely. MVP scope (P8, approved): Overview, Access, Limits, Server replay, and a minimal Users page (list, role, disable). Secrets, Agents, Packs and Audit are `◇`.

---

## 5. URL scheme

### 5.1 Why the app leaves `/c/{captureId}` (Revises UF §2.2)

UF used the capture URL itself as the project's home (`/c/{captureId}`). Three problems:

1. **Fidelity.** The capture URL must accept any method, including `GET`, and answer every request identically (TR R1, A-15). If a browser `GET` on the same path showed the app, either a provider's `GET` gets HTML, or the owner's browser visit gets captured as a delivery.
2. **Privacy.** The capture URL is pasted into provider dashboards, CI configs and screenshots. It must grant **write** only. Anyone who sees it must not be able to read the stream.
3. **Rotation.** Rotating a leaked capture URL must not change every bookmark, CLI link and PR comment.

So:

- **Capture URL (ingest), unchanged:** `https://webhooktrap.dev/c/{captureKey}`. Any method. Always the same response. This is what the UI shows in the URL chip (no frame changes).
- **App:** `https://webhooktrap.dev/p/{projectKey}/…`. `projectKey` is a separate random key (10 chars), never derived from the capture key.

### 5.2 Route table

| Path | Screen / state | Anon | Guest | Auth | Notes |
| ---- | -------------- | :--: | :---: | :--: | ----- |
| `/` | Landing | ● | ● (CTA "Open your capture") | ● (CTA "Open Brindle Coffee") | UF Screen 1 states |
| `/new` | Create guest session | POST | — | — | A form `POST` so it works without JS; never a `GET` that creates state. Redirects to `/p/{projectKey}` |
| `/c/{captureKey}` | **Ingest only** | — | — | — | Not an app page. Any method. A-15 response invariant |
| `/p/{projectKey}` | Capture stream | — | owner | member | Default selection per UF (newest group's newest capture) |
| `/p/{projectKey}?sel=01&tab=raw` | Capture with selection and tab | | ● | ● | `sel`, `tab` (`verdict` · `raw` · `decoded`), `view` (`grouped` · `flat`), `q` (filter), `group` (group key) |
| `/p/{projectKey}/d/{n}` | Capture detail, full page | | ● | ● | Canonical for a capture. Desktop deep link opens the stream with it selected; mobile shows the page |
| `/p/{projectKey}/runs` | Runs list | | ● | ● | `?source=web\|cli\|ci&status=…` |
| `/p/{projectKey}/runs/new?from=01,03,04` | Run draft | | ● | ● | Or `?group={groupKey}`. Draft state of the Run screen (UF §0.6) |
| `/p/{projectKey}/runs/{n}` | Alias | | ● | ● | Redirects to `/r/{runId}` |
| `/r/{runId}` | **Run (canonical)** | — | owner | member | Owner and members only; anyone else gets "not found" (PO-A). Printed by the CLI and, for private uploads, by CI and PR |
| `/s/{shareToken}` (+ `/D{n}`, `/F{n}`, `.json`) | **Shared run** | ● | ● | ● | Read-only; exists only while shared; rotates on "Create a new link"; `no-referrer`, `noindex` |
| `/r/{runId}/D{n}` | Run with a delivery inspected | | | | Inspector open on that delivery |
| `/r/{runId}/F{n}` | Run with a finding open | | | | Finding open, its first evidence delivery selected |
| `/r/{runId}?compare=D4` | Inspector compare on | | | | Optional |
| `/r/example` | Example run | ● | ● | ● | Read-only Run #3 labelled "Example run" (P7, approved) |
| `/p/{projectKey}/fixtures` | Fixtures | | ● | ● | |
| `/p/{projectKey}/settings/…` | Project settings | | partial | ● | Guest: only "End session now" |
| `/agents`, `/agents/{name}`, `/agents/pair` | Agents | | `/p/{projectKey}/agent` (one agent) | ● | Guests see one agent page inside the project |
| `/account/…` | Account | | | ● | |
| `/admin/…` | Instance admin | | | admin | Self-host only; 404 elsewhere |
| `/sign-in` | Sign in | ● | ● (claims session) | — | `?next=` keeps the deep link. Rev. 3: password, GitHub, Google, passkey |
| `/register` | Create account | ● | ● (claims after verify) | — | Uniform answer when the email is taken. Self-host: invite or closed |
| `/verify-email`, `/verify-email/{id}/{hash}` | Verify email; link page | link page: ● | link page: ● | unverified | Workspace pages redirect here until verified. Rev. 4: the link `GET` shows **Confirm email**; the `POST` verifies (A05 §2.9) |
| `/forgot-password`, `/reset-password/{token}` | Forgot, reset, 1.x "Set a password" | ● | ● | — | Uniform answer; reset revokes other sessions, never claims |
| `/two-factor` | Two-step challenge | half signed in | half signed in | — | Claim and `next` only after it |
| `/confirm-auth?next=` | Confirm it's you | | | ● | Before factor, password, email, `run`-token and admin changes |
| `/account-recovery`, `/account-recovery/{token}`, `/account-recovery/finish/{token}` | Account recovery: request, link page (start or decline), finish | ● | | ● (cancel) | Hosted only, 404 on self-host; request link 60 min, finish link 24 h; decided, decisions 46 (PO-J) |
| `/email-change/{token}` | Confirm new email (S-32) | asks to sign in | asks to sign in | same account | Rev. 4. 60 min, single use; the change happens only on the button, in a session of the account that asked. **Rev. 5 (EC-1):** on an account without two-step sign-in the old address gets its own link to this route and the email changes only after both buttons are pressed |
| `/account-deletion/{token}` | Delete my account (S-32) | asks to sign in | asks to sign in | same account | Rev. 5 (DL-4), accounts without two-step sign-in only. 60 min, single use; the deletion happens only on the button, in a session of the account that asked. **[pending A05: route name and the signed-in requirement; A03 proposes both as for email change]** |
| `/account/security`, `/account/security/review` | Security; after recovery | | | ● | Review: from recovery completion until the lock ends, then redirects to `/account/security` |
| `/account/delete` | Delete account | | | ● | Rev. 4 UX drawn; route confirmed by A05 revision 6 (`GET /account/delete`, U-20); rev. 5 adds the "Check your email" state (DL-4) |
| `/expired` | Guest session expired | ● | | | Also rendered in place at any URL of an expired session |

**Rules.**

1. **Selection and view state live in the query string**, location lives in the path. Only the path is a history step (see `navigation.md` §4).
2. **Ids in URLs are the ids users see**: `01` for `#01`, `D3`, `F2`. No internal ids in app URLs except the global `runId`.
3. **Unknown or forbidden → the same "not found" page** (IA5). The page offers Sign in when the viewer is anonymous, so an owner who lost their cookie can recover, without confirming that the page exists.
4. **Guest URLs need the session cookie.** Copying a guest's `/p/…` to another browser shows "not found", not the stream. To move to another machine, the guest signs in (claims) or shares a run.
5. **No personal data in URLs.** Filters (`q`) can contain event types or object ids but the app never puts payload values (emails, names) into a URL; filter by email is a search field that isn't written to the URL. (Privacy rule.)

### 5.3 Run URL and share URL (PO-A, decision 39)

Two URLs, because run ids appear in public CI logs and must not grant access:

| URL | Viewer | Renders | Actions |
| --- | ------ | ------- | ------- |
| `/r/{runId}` | Owner or project member | Full Run screen inside the project shell | Run again, Save as fixtures, Share, Copy as JSON |
| `/r/{runId}` | Anyone else, shared or not | "Not found" (+ Sign in) | — |
| `/s/{shareToken}` | Anyone with the link, while shared | Read-only run (RD `read_only` state), minimal shell (logo, Sign in), provenance line | "Sign in to run it again on your own agent" |
| `/s/{shareToken}` | Rotated, stopped or unknown token; guest run after expiry or claim | "Not found" (guest: with "Guest runs are kept for 24 hours") | Get a URL |

**Run visibility is private by default** (P2). Share is an explicit action that issues a share token (anyone with the link). **Create a new link** issues a new token and kills the old one; stopping and re-sharing does the same. A shared run shows request bodies only for fields not matched by the pack's scrub rules (SCR-1); matched fields show `[scrubbed]`. **Hide bodies** (off by default) removes every body. Headers of source captures follow the SCR-3 allow-list. Probe readings: names, counts and codes; values sent under `show:` only when the owner ticked **Include probe values** (accounts only, PV-7). **CI-uploaded runs are private** unless uploaded with `--share` (PO-C).

### 5.4 Server-side replay targets (P6, approved)

A run can target **"From Webhook Trap servers (public URLs only)"** on hosted, without an agent, limited to scenarios that need **no re-signing** (P6). It is for **accounts only** and only while the operator's kill switch is on (PO-B, HD-10, RUN-11); a guest sees the option disabled with "Server runs need an account. Sign in to use them." The server runner holds no secrets (U6), so by SIGN-2:

| Provider signing | Scenarios the server can run | n/a with reason |
| ---------------- | ---------------------------- | --------------- |
| Signed timestamp (Stripe, Standard Webhooks) | none: every valid delivery must be re-signed | Run disabled: "Stripe signs a timestamp, so a server run needs a secret. Use the agent." |
| No timestamp (GitHub, Shopify) | Once, Twice, Shuffled, Retry after error (Shopify), Tampered (original header, changed body; Shopify: `line_items[0].quantity 2 → 0`, TAMP-1 rule 2), Unsigned, Burst, Slow budget | Late: "GitHub signs no timestamp" / "Shopify signs no timestamp"; Retry after error on GitHub: "GitHub doesn't retry automatically" |
| Unsigned or unknown scheme | Once, Twice, Shuffled, Retry after error, Burst, Slow budget | Late, Tampered, Unsigned: n/a (SIGN-2) |

Until A01 cites the duplicate headers for GitHub and Shopify (DUP-1, DUP-2), Twice, Retry after error and a one-event Burst show `n/a (Not verified for GitHub yet)` for those providers, on the server and on the agent alike.

No probe, no reset (they run on the user's machine), so server runs are **Level 1 only**: duplicates and order end Inconclusive (`needs_probe`), and the run is order-dependent. Public URL means TGT-1/TGT-2 inverted: every resolved address must be public; loopback, private, link-local and metadata ranges are refused (ADR-005 SSRF rules). Self-host may also allow private destinations if the admin enabled them and granted the user the permission (off by default, PO-G). Every server run carries the provenance "Run by the Webhook Trap server runner".

---

## 6. Global and local navigation (summary)

Full model, mobile behaviour, breadcrumbs, back rules and wireframes are in `navigation.md`. The IA-level structure:

| Level | Contents | Where |
| ----- | -------- | ----- |
| **Global (shell)** | Mark → project home · project switcher (auth) or guest meter (guest) · section tabs **Capture · Runs · Fixtures · Agent** · capture URL chip · agent pill · command palette · help · account / Sign in | Top bar (desktop), top bar + bottom nav (mobile) |
| **Local: Capture** | Live indicator, view (grouped/flat), filter, selection, detail tabs Verdict · Raw · Decoded | Capture header, detail pane |
| **Local: Run** | Breadcrumb `Brindle Coffee › Runs › Run #3`, From line `From sub_1QH…4drI (#01 #03 #04)`, provenance line ("Run by maya-mbp from the web app", RUN-8), run switcher (history), scenario strip, lower-zone tabs Deliveries · Inspector | Run header, below the graph |
| **Local: Settings, Account, Admin** | A section list on the left of the content | These are forms, not live data; the "no sidebar" rule (UF §2.3) applies to the app's working screens |

Section tabs are the same for guests and signed-in users. A guest's Fixtures and Agent tabs work; they explain what needs the agent rather than hiding.

---

## 7. Entry points

| # | Entry | Lands on | State on arrival | Notes |
| - | ----- | -------- | ---------------- | ----- |
| E1 | **Landing CTA** "Get a URL, no sign-up" | `/p/{projectKey}` | Capture `waiting` (onboarding panel) | ≤ 400 ms "Creating your URL…"; failure inline (UF Screen 1) |
| E2 | **Landing, returning guest** | `/` with "Open your capture" | — | Shows URL, count, time left (UF) |
| E3 | **Landing, signed in** | `/` with "Open Brindle Coffee" | — | Last-used project |
| E4 | **`npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD`** (code from the browser) | stays on the page that showed the code | Draft step 1 shows "Confirm in your terminal", then Connected by itself after `y` | Decision 32: code bound to the session; PO-E, PO-F |
| E5 | **CLI link** from `webhooktrap prove` (`report  https://webhooktrap.dev/r/run_…`) | `/r/{runId}` | Owner: full run. Else per §5.3 | `webhooktrap show F1` prints `/r/{runId}/F1` |
| E6 | **CI log** (`webhooktrap test --upload`) | `/r/{runId}` (members); with `--share` also `/s/{shareToken}` | Members: full run; others: "not found" unless they use the share link | Uploads are private (PO-C). Without `--upload` there is no link: "Not uploaded: results stay in this log" (TT §1.4.5) |
| E7 | **PR comment** "Full report", finding and D-links | `/r/{runId}…` by default; `/s/{shareToken}`, `/s/{shareToken}/F2`, `/D3` when uploaded with `--share` | Same | Evidence links in Markdown are a change to TT §1.4.6, see `golden-files.md` §7 |
| E8 | **Share link** pasted in chat | `/s/{shareToken}` | Read-only, or "not found" | Mobile is common here (UF F5) |
| E9 | **"See an example run"** (guest, G1) | `/r/example` | Read-only Run #3, labelled "Example run" | P7, approved |
| E10 | **Docs / README** "Try it" | `/` or `/new` | — | Docs site out of scope |
| E11 | **MCP / JSON** | not a page | Tool results carry the same `/r/{runId}/F2` URLs (the caller is the owner); captured content is marked untrusted (A05 TM-110) | A-24 |
| E12 | **CLI-initiated pairing** (user starts in the terminal, no browser session) | `/pair?code=…` | Approve the agent into a new guest session or the signed-in workspace | **Not in MVP** (P10, approved): waits for A05/A09's pairing model (A-12). The MVP keeps the code shown in the browser (E4) |
| E13 | **1.x launch notice email** (`launch-1x`, 3 imported accounts) | `/forgot-password` | Forgot form; after the link, S-26 "Set a password" | Decision 41; PO-H; FL-22 |
| E14 | **Security emails** (S-31: key added, method linked, recovery started …) | `/account/security`, `/sign-in?next=/account/security`, or the recovery link | Security page; recovery banner while a recovery is open | FL-18 to FL-23, FL-25 |
| E15 | **Emailed links with a token** (verify, email change at the new and the old address, account deletion, reset, recovery request, recovery finish; rev. 4, rev. 5) | the link page for that link (S-32 table) | One button; nothing changes on opening; one "This link doesn't work" page for expired, used and unknown | A05 §2.9; FL-15, FL-21, FL-24, FL-26 |

---

## 8. Cross-surface consistency

### 8.1 Same things, same words

| Concept | Web | CLI (`webhooktrap prove`) | CI log (`webhooktrap test`) | PR comment | JSON / MCP |
| ------- | --- | ------------------ | -------------------- | ---------- | ---------- |
| Capture | `#01 · invoice.paid` | `events  #01 invoice.paid · …` | fixture file names | — | `captures[].seq = 1` |
| Run | `Run #3` + status | `Webhook Trap · Run #3 · …` | `Webhook Trap · webhooktrap test · …` (no run number unless uploaded) | `### Webhook Trap · 3 findings · check failed` | `run.id`, `run.number`, `run.status`, `run.exit_code` |
| Run delivery | `D5 · from #01` | `D5` row | `D5` row | `D5` in text, linked | `deliveries[].n = 5`, `event_ref`, `mode` |
| Finding | `▲ F2 High · Duplicate delivery…` | same (live: title without the F-number, FND-6) | same + `::error title=F2 High · …` | same | `findings[].id = "F2"`, `type`, `fingerprint`, `severity` |
| Scenario names | 9 names (RD §5) | same | same | same | `scenarios[].name = "twice"` + label |
| Run status | `Draft · Queued · Running` (no exit code) · `Couldn't start · Aborted · N findings · Inconclusive · Passed` (RUN-1, evaluated in that order) | status word in header line | summary line + exit code (EXIT-1) | heading; "check failed" exactly when exit ≠ 0 (EXIT-2) | `run.status` + `status_reason` + `exit_code` |
| Severity | word + shape (■ ▲ △ ○, ▬ pass) | same glyphs, ASCII `[C] [H] [M] [I] [-]` | same | same glyphs | `severity: "high"` |
| Budget | `10 s budget · Webhook Trap default` (BUD-1 label) | `10 000 ms (Webhook Trap default)` | same | same | `run.budget {ms, source, label}` |
| State reading | `Expected 1 · got 2` (codes `#a1f3c0` for non-count values, SS §0.5) | `1 → 2  ▲ F2 High, expected 1` (real values, local) | same (real values, local) | `orders.count 1 → 2 on D5, expected 1` (display values) | `state.readings[{name, changed, matches_expected, flagged, display{before, after, expected}}]`: real values locally, display values when uploaded (PV-1, PV-6, JSON-1) |
| Provenance | "Run by maya-mbp from the web app" | — (local) | "Uploaded from CI (token github-actions)" after upload | same, in the context line | `run.provenance{executor, source, agent_name, ci_token_name}` (RUN-8) |
| Next step | "Run again", Fix beat | `next  webhooktrap show F1` | `Failed: …` line | footer | — |

### 8.2 Rules

1. **Order is the same everywhere:** deliveries in D order grouped by scenario; findings in FND-6 order; scenarios in PLAN-1 order.
2. **A status word means the same thing everywhere,** and the exit code is one function of the status and the run definition (EXIT-1; exit 2 wins over exit 1, C-2). The web never shows "Passed" for a run that exits non-zero, and the CLI never prints "passed" for Inconclusive ("Inconclusive (not passed)"). A run started from the web stores the exit code it would have had with the defaults (EXIT-2).
3. **Every finding printed outside the web carries a way back:** a URL when the run is on the server, `webhooktrap show Fn` when it isn't.
4. **Copy is shared source.** UI strings, CLI lines and PR Markdown for the same fact come from one string table (RD + TT §1.3), so a copy fix lands on every surface at once. A11's copy linter (TR R44, R70) runs over all of them.
5. **Web shows absolute and relative time; CLI shows relative only** (absolute in `--verbose` and JSON). Both use the plain-space or thin-space thousands rule of their medium (UF §6, TT §1.4).

---

## 9. Empty, first-run and guest-expiry paths

### 9.1 First run

| Who | Path | First screen | What it says |
| --- | ---- | ------------ | ------------ |
| New visitor | E1 | Capture `waiting` | URL, provider steps, "Send a test request" (RD `waiting`) |
| Guest, first capture, no agent | — | Capture `receiving`, G1 | Signature `Not checked` with what *was* read; "Prove the handler that receives these" block (TT §2.1); "See an example run" (P7) |
| Guest presses Prove | — | Run draft, G2 | Pairing code live; Run disabled with reason |
| New account, no guest session | sign in | `/projects/new` → Capture `waiting` | Name the project (default: "My project") |
| Signs in **from** a guest session | sign in | the same `/p/{projectKey}` | Automatic claim (P11, approved). Toast: "Guest session moved to your account" + "Your deliveries, runs and agent are now in the project 'Stripe project'. The URLs didn't change." (RD `guest.claimed`) |
| Signs in from an **empty** guest session (0 captures, 0 runs, no agent) | sign in | own last project | The empty session is discarded, not claimed (P11, approved) |
| Agent pairs for the first time | E4 | where the code was shown | G3: Connected, signatures re-checked, toast with count |

### 9.2 Empty states per area

| Area | Condition | Content (final copy in `screen-specifications.md`) | Primary action |
| ---- | --------- | -------------------------------------------- | -------------- |
| Capture | 0 captures | RD `waiting` | Copy URL / Send a test request |
| Capture | filter matches nothing | RD `no_results` | Clear filter |
| Runs | 0 runs | "No runs yet. Prove a delivery to start one." + "See an example run ›" | Go to Capture |
| Fixtures | no agent | "Fixtures live in your repo. Pair an agent to read them here." + what hosted keeps (names, shapes, sync state) | Pair agent |
| Fixtures | agent, none saved | "No fixtures in brindle-app/webhooktrap/fixtures yet. Save a run as fixtures." | Open latest run |
| Agent | none paired | RD `not_installed` with the command | Copy command |
| Projects | auth, 0 projects | redirect to `/projects/new` | Create |
| Admin overview | fresh instance | checklist: sign-up policy, guest mode, server replay (off), limits | — |

### 9.3 Guest expiry path

| Moment | What happens | Where |
| ------ | ------------ | ----- |
| Normal | Meter: `expires in 23 h 49 m · 13/100 deliveries · 1/10 runs` | top bar |
| **1 h left** | Meter switches to its warning style with the word "Ends in 1 h"; one toast: "This session ends in 1 h. Save fixtures or sign in to keep it." | top bar, toast once |
| **Delivery quota reached** | RD `quota` banner; delivery 101 onward answered exactly like a stored one, not stored (A-13, A-15) | Capture |
| **Run quota reached** | Draft: Run disabled, "10 of 10 guest runs used. Sign in to keep running. Runs that couldn't start don't count." (A-13: a run counts once it gets past Once; an Aborted run counts if a scenario after Once completed) | Run draft |
| **Expiry during a run** | Proposed (still open, see end): the run is aborted with "Guest session ended", then everything is deleted. A run can't start with < 5 min left | Run |
| **Expired, any app URL of the session** | Whole screen replaced by RD `expired`; nothing from the session in HTML or props (TR R59) | any `/p/{projectKey}/…` |
| **Expired or claimed, shared run link** | "Not found" + "Guest runs are kept for 24 hours" (RD `errors.not_found_run`) | `/s/{shareToken}` |
| **Expired, agent still running** | Agent goes `Disconnected`; the CLI prints "Guest session ended. Pair again from a new URL: webhooktrap pair <code>." | terminal |
| **End session now** (guest action) | Confirm → delete everything → landing with "Session ended. Nothing was kept." | project settings |
| **Second agent paired** | The new agent replaces the old one; the old agent's terminal prints "Replaced by a new agent for this guest session." and the pill shows the new name; a toast says "Agent replaced" (A-13) | top bar, agent page |

---

## 10. Decisions (approved by the PO, decision 35)

All eleven were approved as recommended. The "Applied in" column says where each one now lives.

| # | Decision (approved) | Applied in |
| - | ------------------- | ---------- |
| **P1** | App URLs separate from the capture URL: `/p/{projectKey}` for the app, `/c/{captureKey}` only receives webhooks | §5.1, §5.2; every URL in `user-flows.md` and `screen-specifications.md` |
| **P2** | Runs private by default. Share = anyone with the link. Bodies shown only for fields not matched by scrub rules, with a **Hide bodies** switch. **Amended by PO-A** (decision 39): the link is a separate rotating token `/s/{shareToken}` | §5.3; flow FL-6; screen S-12 (share dialog) and S-13 |
| **P3** | Keep "delivery" for both `#01` and `D3` | §2.4; all copy |
| **P4** | Agents belong to the workspace; one agent serves every project; the project remembers its last agent | §2.1, `navigation.md` §1.3 |
| **P5** | Breadcrumb shows location; provenance on a "From" line. **Revises UF §2.3 and TT G2** | `navigation.md` §3; screen specs |
| **P6** | Hosted server-side replay to public URLs is an MVP run target, limited to scenarios that need no re-signing. **Narrowed by PO-B and HD-10** (decision 39): accounts only, behind a kill switch | §5.4; flow FL-9; screen S-10 target step |
| **P7** | Example run `/r/example` for guests and anonymous visitors | §5.2, E9; screen S-14 |
| **P8** | Self-host admin in MVP: Overview, Access, Limits, Server replay; minimal Users. Secrets, Agents, Packs, Audit later | §4.4; flow FL-10; screens S-19 to S-23 |
| **P9** | One capture URL per project in MVP | §2.1 |
| **P10** | CLI-initiated pairing (`/pair`) after the A05/A09 pairing model; MVP keeps the browser-shown code (10 symbols, `[y/N]` in the terminal, PO-E, PO-F) | §4.1, E12 |
| **P11** | Claim on sign-in is automatic; empty sessions are discarded. The claim voids guest share links and asks the agent to reconfirm in its terminal (PO-E) | §9.1; flow FL-7 |

---

## 11. Dependency table: how each A-item closed

A01's spec (SJ) and decision 35 closed every item that blocked screens. "Closed by" names the rule; "Open" lists what is still not final.

| Screen / state / surface | Item | Closed by | Still open |
| ------------------------ | ---- | --------- | ---------- |
| Run verdict line, status set, Runs list status, PR heading, CLI summary, exit codes | A-4 | RUN-1, RUN-3, EXIT-1, EXIT-2, C-2 | — |
| Run · baseline rejected and every Couldn't start reason | A-5 | ONCE-4, ONCE-6, RUN-1 row 1, RD `couldnt_start_*` | — |
| Delivery row "sent, no result", Aborted strip cell | A-5 | §1 "Completed delivery", §7.1, RUN-6 | — |
| Scenario strip, draft rows, landing grid, CLI block | A-1, A-7 | PLAN-1, ONCE-1, TWICE-1, SHUF-1, A-7 (9 scenarios) | — |
| Finding order, F-numbering, merged evidence | A-3 | FND-3 to FND-6 | — |
| Severity set, Late/Tampered rows, F1 copy | A-2, A-6 | SEV-1, §5.2, INV-1, TAMP-2 | — |
| Retry after error rows and F2/F3 evidence | A-8 | RETRY-1 to RETRY-6 | — |
| Burst rows, "not simultaneous" | A-9 | BURST-1 to BURST-4 | — |
| State reading, probe failed, probe unstable, reset failed, Passed line, F4 | A-10 | PRB-1 to PRB-12, §8.5, RUN-7 | `sql:` may drop to `cmd:` (A05, SJ C-4) |
| Budget band, tags, Slow budget, F3, Settings › Budgets | A-11 | BUD-1 to BUD-7 | — |
| Agent states, pairing TTL, version rule, timeline transport | A-12 | — | **Open** (A05/A09): code TTL, version range, event contract. Screens use RD values (`9:41`, "0.9 or later") |
| Guest meter, quotas, claim | A-13 | A-13 approved, P11 | Expiry during a run (end of file) |
| Raw tab header note | A-14 | — | **Open** (A05) |
| Body not kept, ingest invariants | A-15 | — | **Open** (A05): 5 MB vs 5 MiB |
| Loading and queued thresholds | A-16 | PERF-1 to PERF-8 | — |
| Group headers, `?group=`, shape chip, Fixtures drifted | A-17 | GRP-1 to GRP-5, SHP-1 to SHP-4 | — |
| Provider tabs, landing strip, Settings › Budgets rows | A-18 | A-18 approved | — |
| Invalid signature reasons | A-19 | SIG-1 to SIG-4 | — |
| Product names | A-20 | NAME-1 to NAME-5, C-1 (`webhooktrap` until Q12) | Brand (Q12) |
| Draft summary and the cap | A-21 | CAP-1, CAP-2 | — |
| Likely cause, framework label | A-22 | FND-8, FND-9, FND-10 | — |
| Copy as JSON, MCP | A-24 | §13 (content); schema is A05's | Schema file |
| Save as fixtures preview, share bodies (P2) | A-25 | SCR-1 to SCR-6 | — |
| Probe values shown on hosted screens | C-3 | PV-1 to PV-14 (decision 38); SS §0.5 | — |
| Share link, provenance, pairing confirmation, server-run access | PO-A to PO-G | decision 39; A01 RUN-8 to RUN-11 | Shopify citations (DUP-1, DUP-2) `[pending A01]` |

---

## 12. Changes in this revision (2026-11-14, decisions 35 and 38–39)

| # | Section | Change | Source |
| - | ------- | ------ | ------ |
| 1 | Header, §10 | P1 to P11 marked approved, with where each is applied | decision 35 |
| 2 | §2.1, §2.2, §2.3 | 9 scenarios; D1…D16; FND-6 numbering; probe display values (PRB-3); one guest agent at a time | A-7, C-4, C-3, A-13 |
| 3 | §3, §3.1, §9.3 | Guest quotas 24 h, 100, 10 runs, counting rule; second agent replaces the first | A-13 |
| 4 | §4.3, §4.4 | Budgets page shows `budget:` overrides (BUD-2); admin MVP scope per P8 | SJ BUD-2, P8 |
| 5 | §5.3, new §5.4 | Share rules per P2; server-side replay targets per P6 with the SIGN-2 table | P2, P6, SJ SIGN-2 |
| 6 | §7, §8 | `trap` → `webhooktrap`; status set and exit-code rule from RUN-1/EXIT-1; "3 findings"; JSON field names from SJ §13 | SJ §16.13, §16.6, A-24 |
| 7 | §9.1 | Claim automatic, empty sessions discarded | P11 |
| 8 | §11 | Dependency table now records how each item closed | SJ |
| 9 | §2 objects | Run gains provenance and PV display values; run delivery stores no signature value; pairing code 10 symbols; share link is its own token; CI token by name only | Decisions 38, 39; A01 RUN-8 |
| 10 | §4.1, §5.2, §5.3 | `/s/{shareToken}` added; `/r/{runId}` owner and members only; §5.3 rewritten (rotation, Include probe values, CI private unless `--share`) | PO-A, PO-C, PV-7; A05 R-D, G-9 |
| 11 | §3.1, §5.4 | Server runs accounts only behind the kill switch; private destinations need a per-user grant; Shopify Tampered path | PO-B, PO-G, HD-10, RUN-11 |
| 12 | §7, §8.1, §9.3, §10, §11 | E4, E6, E7, E8 and E11 updated; provenance row; claim voids share links; P2, P6, P10, P11 amendment notes; C-3 closed | Decisions 38, 39 |
| 13 | §4.1, §4.3, §5.2 | **Rev. 3.** Account-security routes: register, verify email, forgot and reset (1.x variant), two-step challenge, confirm-auth, account recovery, `/account/security` replacing `/account/sign-in`, review after recovery; "half signed in" access; email link dropped | Decision 44; A05 contracts §3.4; A09 §12.4 |
| 14 | §7 | E13 1.x launch notice, E14 security emails | Decision 41; FL-21, FL-22 |
| 15 | §4.1, §4.3, §5.2, §7 | **Rev. 4.** Link pages for every emailed link; `/email-change/{token}`; recovery link page and finish route; Security gains email change, sessions and activity; review route confirmed (window until the lock ends); `/account/delete`; E15 emailed links | A05 revision 5, U-10 to U-19 |
| 16 | §4.1, §4.3, §5.2, §7 | **Rev. 5.** Decisions 46 and 47: `[pending PO]` markers removed (PO-H, PO-I, PO-J decided); `/email-change/{token}` also serves the old address (EC-1); new `/account-deletion/{token}` (DL-4); `/account/delete` route confirmed; E15 lists both | Decisions 46, 47; A05 revision 6 |

---

## Open for the PO

| # | Question | Recommendation |
| - | -------- | -------------- |
| O-IA-1 | **Guest session expiry during a run.** A-13 fixes the 24 h clock but not what happens to a run in flight. | Refuse to start a run in the last 5 minutes (RD `guest.run_too_late`); if the clock still runs out mid-run, abort with "Guest session ended" and delete everything, as for any expiry. A run never extends the session. |
| O-IA-2 | **P6 on hosted for Stripe is empty.** Stripe and Standard Webhooks sign a timestamp, so a server run without a secret can't send anything (SIGN-2). The approved P6 target therefore only helps GitHub, Shopify and unknown providers. | Keep P6 as approved, show the target for every provider, and for timestamped providers disable it with its reason (§5.4) rather than hiding it (IA7). Revisit only if users ask for hosted Stripe runs; that would need instance secrets, which hosted forbids (U6). |
