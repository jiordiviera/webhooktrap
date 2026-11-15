# Screen specifications · Webhook Trap 2.0

**Status:** REVIEW_REQUIRED · **Author:** A03 (UX/UI) · **Date:** 2026-11-14 (rev. 2: decisions 38–39; rev. 3: decision 44 account security; rev. 4: A05 revision 5, U-10 to U-19) · **Phase:** 2 (Gate 2)
**For:** A04 (draws these in Claude Design, Timetable direction) and A07 (builds them).
**Binding inputs:** decision 35, A01 `scenarios-and-judge.md` (SJ), `information-architecture.md` (IA), `navigation.md` (NAV), `accessibility-spec.md` (A11Y), `golden-files.md` (GF), `user-flows.md` (FL). Data and copy: `../realistic-data.md` / `.json` (RD). Decisions 38 (PV-1 to PV-14) and 39 (PO-A to PO-G) are applied in rev. 2; the probe display rules are in §0.5 and the change table at the end lists every edit. Architecture contract: A05 `contracts.md` §3 (prop sets per access level). Visual grammar: Timetable (`../ux-timetable.md` TT1, `../ux-timetable-v2.md` TT): Archivo in widths and weights, Martian Mono for evidence, hairlines and position instead of boxes, colour only for finding, now, budget and pass, severity terminals ■ ▲ △ ○ and ▬ for pass.

---

## 0. Conventions

### 0.1 Rules for A04 and A07

1. **No invented data.** Every id, number, time and sentence comes from RD or from this file. Strings this file adds are marked **NEW** and go into RD at its next regeneration. If a frame needs something missing, ask A03.
2. **Copy is exact.** Quoted strings are final UI copy (sentence case, no exclamation marks). `{braces}` are values filled from data.
3. **Copy keys** are JSON paths into `realistic-data.json` (`copy.run.findings` → label "3 findings", helper "1 Critical · 1 High · 1 Medium · 1 Info").
4. **Numbers** in the UI use a thin space for thousands (`11 840 ms`, `2 294 B`); the CLI uses a plain space (GF §6.2).
5. **Budgets** always carry their source tag; the UI label is RD `providers[].budget_label` (`10 s budget · Webhook Trap default`, `5 s · Shopify limit`) (BUD-1, BUD-3).
6. **Status words** are fixed (RUN-1): Draft · Queued · Running · Couldn't start · Aborted · {N} findings · Inconclusive · Passed. "N" counts Critical + High + Medium only; Info is added as "· {k} Info" (RUN-3).
7. **F-numbers** appear only once a run has ended; live views show severity + title (FND-6).
8. **Every state listed must be drawn** (A04) and built (A07). "Partial" means part of the data loaded or part of the run completed.

### 0.2 Breakpoints and frame sizes

| Name | Width | Frame size | Layout |
| ---- | ----- | ---------- | ------ |
| Desktop | ≥ 1280 | **1440 × 900** | Full layouts below |
| Narrow desktop | 1024–1279 | — | Capture list 400; Run findings column 340, inspector as a right panel over findings (UF §8) |
| Tablet | 768–1023 | — | Capture detail as a 640 overlay; Run findings below the verdict (NAV §2) |
| Mobile | < 768 | **390 × 844** | Mobile patterns below; also the 320 px reflow pattern (A11Y G8) |

Gutters: desktop 24, mobile 16 (20 on landing, UF). Touch targets ≥ 44 px on mobile, ≥ 24 px on desktop (A11Y G7).

### 0.3 Shared components (used by many screens)

| Component | Spec home | Notes |
| --------- | --------- | ----- |
| Top bar, guest meter, agent pill, URL chip, command palette, shortcut sheet, toasts, announcer | S-00 | — |
| Severity terminal + word | A11Y §8.1 | Glyph never alone |
| Signature chip | S-04 §states | 7 states |
| Scenario strip, strip cell | S-11 | TT §1.1 pattern, values from RD |
| State reading | S-11 | TT §1.2 pattern; flagged rows per SJ §8.5; value display per §0.5 |
| Probe code (`#a1f3c0`) | §0.5 | Martian Mono, `ink`, dotted underline; tooltip and accessible name per §0.5 |
| Provenance line | S-11 | Who ran the run; on every run view, owner and shared (TM-17) |
| Finding entry + commentary | S-11 | TT §1.3 pattern |
| Pair panel (code + command + countdown + terminal confirmation) | S-08 | Used by S-06, S-10, S-08 |
| Ruled banner | S-00 | `role="status"`, or `alert` once for blocking errors |
| Confirm dialog | S-12 | Names the object; Esc cancels |

### 0.4 Screen index

| ID | Screen | URL | Access |
| -- | ------ | --- | ------ |
| S-00 | App shell | every app URL | guest, member |
| S-01 | Landing | `/` | all |
| S-02 | Sign in | `/sign-in` | anonymous, guest |
| S-03 | Capture stream | `/p/{projectKey}` | guest owner, member |
| S-04 | Delivery detail | pane of S-03; page `/p/{projectKey}/d/{n}` | guest owner, member |
| S-05 | Runs list | `/p/{projectKey}/runs` | guest owner, member |
| S-06 | Agent (guest) and Agents (signed in) | `/p/{projectKey}/agent`, `/agents` | guest owner, signed in |
| S-07 | Agent detail | `/agents/{name}` | signed in |
| S-08 | Pair a new agent | `/agents/pair` | signed in |
| S-09 | Fixtures | `/p/{projectKey}/fixtures` | guest owner, member |
| S-10 | Run draft | `/p/{projectKey}/runs/new?from=…` | guest owner, member |
| S-11 | Run | `/r/{runId}` (+ `/D{n}`, `/F{n}`) | owner, member only (anyone else: S-18 Not found) |
| S-12 | Run dialogs: Share, Save as fixtures, Side effects, Abort | over S-11 / S-10 | owner, member |
| S-13 | Shared run (read-only) | `/s/{shareToken}` (+ `/D{n}`, `/F{n}`) | anyone with the share link (PO-A) |
| S-14 | Example run | `/r/example` | all |
| S-15 | Projects, New project | `/projects`, `/projects/new` | signed in |
| S-16 | Project settings | `/p/{projectKey}/settings/…` | member; guest: End session only |
| S-17 | Account (rev. 4: `delete` page; rev. 5: "Check your email" state) | `/account/…` | signed in |
| S-17A | Account · Security (rev. 3) | `/account/security` | signed in |
| S-17B | Security dialogs: add passkey or key, rename, remove, review blocked key (unblock), authenticator app, recovery codes, password, linked accounts, change email, sign out browsers (rev. 4) | over S-17A | signed in, recent authentication |
| S-18 | System pages: expired, not found, server error, offline | any | all |
| S-19 | Admin · Overview | `/admin` | self-host admin |
| S-20 | Admin · Access | `/admin/access` | self-host admin |
| S-21 | Admin · Users | `/admin/users` | self-host admin |
| S-22 | Admin · Limits | `/admin/limits` | self-host admin |
| S-23 | Admin · Server replay | `/admin/server-replay` | self-host admin |
| S-24 | Create account | `/register` | anonymous, guest |
| S-25 | Verify email | `/verify-email` (check inbox), `/verify-email/{id}/{hash}` link page | signed in, unverified; link page: any |
| S-26 | Forgot and reset password (also "Set a password" for imported 1.x accounts) | `/forgot-password`, `/reset-password/{token}` | anonymous |
| S-27 | Two-step sign-in challenge | `/two-factor` | half signed in |
| S-28 | Confirm it's you (recent authentication) | `/confirm-auth?next=` | signed in |
| S-29 | Account recovery: request, check inbox, link page, declined, waiting, cancelled, finish | `/account-recovery`, `/account-recovery/{token}`, `/account-recovery/finish/{token}`; banner on S-00 | anonymous; signed in for cancel |
| S-30 | After recovery: security review | `/account/security/review` | signed in, from recovery completion until the lock ends |
| S-31 | Account emails | email, not a page | — |
| S-32 | Emailed-link pages: confirm new email, confirm email change (old address, rev. 5), delete my account (rev. 5), the shared "This link doesn't work" page, and the rule for every link page (rev. 4) | `/email-change/{token}`, `/account-deletion/{token}` and every emailed link | any; email change and deletion: signed in to the same account |

Terminal, CI log and PR comment frames are specified in GF §15 (text) and TT §2.4–2.6 (frames); they are not repeated here.

### 0.5 Probe values on hosted screens (decision 38, PV-1 to PV-14)

The judge runs on the agent. The server stores, per reading, `changed`, `matches_expected`, `flagged` and three **display values** (`before`, `after`, `expected`); it never receives a raw value, a probe definition or a reset command (PV-1, PV-8). Screens print display values only, exactly as received. A display value is one of:

| Kind | Form | Example | Comes from |
| ---- | ---- | ------- | ---------- |
| **Count** | `-9999` to `9999`, no separators, no decimals | `0`, `1`, `2` | any probe value that matches PV-2 |
| **Code** | `#` + 6 lowercase hex (12 after a collision, PV-4) | `#a1f3c0`, `#a1f3c0d27e45` | every other value (PV-2, PV-3) |
| **Shown value** | `show: number` (up to 12 digits, 4 decimals) or `show: value` (`[A-Za-z0-9_.:-]`, 32 chars) | `12500.75`, `past_due` | entries the owner opted in, that passed the personal-data check (PV-5) |
| **Refused** | `#invalid` | `#invalid` | a value the server refused (PV-11) |

**Who sees what** (the same rule on every surface that shows readings: strip line 3, deliveries grid State cell, inspector State reading, findings `measured`, verdict lines, live timeline):

| Value | Owner, account (`/r/…`) | Share viewer (`/s/…`), Include probe values **off** (default) | Share viewer, **on** | Guest owner | Guest run's share viewer | Example run (`/r/example`) |
| ----- | ----------------------- | ------------------------------------------------------------- | -------------------- | ----------- | ------------------------ | -------------------------- |
| Count | number | number | number | number | number | number (synthetic) |
| Code | code | code | code | code | code | — (Run #3 has counts only) |
| Shown value | value + tag `shown` | **code** | value | never exists: `show:` is ignored for guests (PV-5) | never exists | — |
| Refused | `#invalid` | `#invalid` | `#invalid` | `#invalid` | `#invalid` | — |

The server sends shown values to a share page only when the owner ticked **Include probe values** (A05 `ShareState.includeProbeValues`); a share page never receives them otherwise, so "code" in the share columns is what the server sends, not a mask. Self-host follows the stricter of the instance setting and the entry (PV-12): with `probe_display: opt_in_disabled`, shown values become codes for everyone.

**Rendering rules.**
1. **Counts** render as numbers with the UI thousands rule (`1 250`), never with a unit the server doesn't know.
2. **Codes** render in Martian Mono, `ink`, with a 1 px dotted underline in `ink-muted`; never coloured, never truncated (12-hex codes wrap the line rather than lose characters). Hover and focus show the tooltip `copy.probe_display.code_tooltip`: "Code for a value that stays on your machine. Same code = same value within this run." Accessible name: "code a1f3c0" (each character spoken by the screen reader's character mode; A11Y §3).
3. **Shown values** render like counts (plain Martian Mono). On the owner's inspector the entry carries the tag `shown (show: value)` in `ink-muted`.
4. **`#invalid`** renders as a code without underline, with tooltip `copy.probe_display.invalid` "The server refused this value, so it isn't shown." A flagged row with `#invalid` stays flagged: the verdict comes from the agent (PV-1).
5. **Probe names** (`as:`) are always shown, to everyone who can see the run; they are user-chosen text the owner was warned about (PV-9).
6. **Never on a hosted page:** a probe's `sql:`, `cmd:` or `url:` text, `database:`, the reset command or its output, stderr, settle/stability intermediate readings. Where a screen needs to say a probe or reset exists, it names the entry and says where it ran: "reset before every scenario, run on maya-mbp" (`copy.run.checks_hosted`). Templates from the provider pack (the `needs_probe` and F4 fix snippets) are allowed and are labelled "Example" (pack templates are not your config). Local surfaces (CLI, local run files) show real values and commands (GF).
7. **Notes.** Where a code first appears on a page (inspector State row, or the first strip cell with a code), the tooltip sentence is also printed once under it in `ink-muted`: "Code for a value that stays on your machine. Same code = same value within this run." (PV-14, exact). No note on a page with counts only.

Data: Run #3 uses counts only (`orders.count`). Code-valued readings are drawn from RD `examples.code_reading` (§5.2 of RD), on the states sheet only, never mixed into Run #3 frames.

---

## S-00 · App shell

**Purpose:** the same frame on every working screen: where am I, which project, is the agent there, how do I copy my URL.
**URL:** all app URLs. Landing, share view and bare pages use reduced shells (NAV §1.2).

### Layout regions

| Region | Desktop 1440 | Mobile 390 |
| ------ | ------------ | ---------- |
| Top bar | 48 px: Mark · project switcher **or** guest meter · section tabs Capture · Runs · Fixtures · Agent · `⋯` · capture URL chip · agent pill · `⌘K` · `?` · account / **Sign in** | 52 px: Mark · project name or compact meter · agent pill (glyph + short word) · `⋯` |
| Bottom nav | — | 56 px: Capture · Runs · Agent · More (`nav[aria-label="Main"]`) |
| Toast area | bottom left, max 2 | top, max 1 |
| Announcer | hidden, two regions (A11Y §6.1) | same |

### Components and copy

| Component | Copy | Data |
| --------- | ---- | ---- |
| Guest meter (normal) | "Guest session · expires in 23 h 49 m · 13/100 deliveries · 1/10 runs · Sign in to keep" | `copy.guest.meter` |
| Guest meter (1 h left) | adds the word "Ends in 1 h" before the time | `copy.guest.ending_soon` |
| Guest meter (quota) | "100/100 deliveries · Limit reached" or "10/10 runs · Limit reached" | `copy.capture.quota`, `copy.guest.run_quota` |
| Compact meter (mobile) | "Guest · 23 h 49 m · 13/100 · 1/10 runs" | — |
| Project switcher | "Brindle Coffee ▾"; menu: projects, "New project" | `project.name` |
| URL chip | `webhooktrap.dev/c/Br7nDLk2Qm9x` + copy button "Copy capture URL" | `project.capture_url` |
| Agent pill | "No agent" · "Pairing…" · "Confirm in your terminal" · "maya-mbp" · "maya-mbp · confirm in terminal" (after a claim) · "maya-mbp · target unreachable" · "maya-mbp · offline" · "maya-mbp · update needed" | UF §5.3, `copy.agent.*` (`awaiting_confirmation`, `needs_reconfirm`) |
| Agent pill popover (connected) | "Connected · maya-mbp" / "Target localhost:3000/webhooks/stripe · agent 0.9.2" / "Secrets (names only, never values)": `STRIPE_WEBHOOK_SECRET`, `GITHUB_WEBHOOK_SECRET`, `SHOPIFY_API_SECRET`, "Parcelbird: not set" / link "Agent page". No last-4 hint, ever (PO-D) | `agent.secrets` |
| Agent pill popover (confirm in terminal) | "Confirm in your terminal" + `copy.agent.awaiting_confirmation` (pairing) or `copy.agent.needs_reconfirm` (after a claim) | `copy.agent.*` |
| Agent pill popover (no agent) | "No agent connected" + helper + **Pair an agent** | `copy.agent.not_installed` |
| `⋯` menu | Settings · Help · Docs (guest: Settings › End session now · Help · Docs) | — |
| Account menu | Account · Instance admin (self-host admin only) · Sign out | — |
| Command palette | placeholder "Search deliveries, runs and actions" (**NEW**); groups: Deliveries, Runs, Actions ("Prove selected", "Copy capture URL", "Pair agent", "Toggle grouped view", "Go to Runs") | UF §3 |

### States

| State | Shell shows |
| ----- | ----------- |
| Loading | Top bar renders from the session immediately; tabs and chip are real, pill shows "Checking agent…" (**NEW**) for at most 300 ms |
| Offline | Ruled banner under the top bar: `copy.errors.offline` |
| Server error on a background call | Error toast, persistent: `copy.errors.server` |
| Guest, agent replaced | Toast `copy.agent.replaced` |
| Signed in after claim | Toast `copy.guest.claimed` (says share links were turned off and the agent asks to confirm); pill `maya-mbp · confirm in terminal` until the person answers `y` |
| **Account recovery pending** (rev. 3, U-5, `principal.recoveryPending`) | Ruled banner under the top bar on **every** signed-in page, above any other banner, not dismissable: "Someone asked to recover this account on {requested_label}. If it wasn't you, cancel it now. Otherwise your passkeys, keys and authenticator app are removed on {completes_label}." + **Cancel recovery** (S-29 cancel dialog). RC-2 variant ends "…removed on {extended completes_label}. The wait is 7 days because the password was reset by email." Data: RD `account_security.recovery`. `role="status"`, announced once per page load (A11Y §9) |
| **Email not verified** (rev. 3) | Workspace pages redirect to S-25; the shell is not drawn |
| **Two-step sign-in off, nudge** (rev. 3) | Not a banner: one line in the account menu, "Turn on two-step sign-in", linking to S-17A. No nag on working screens |

### Keyboard and accessibility

Global map: NAV §6, A11Y §5.2. Landmarks: A11Y G1. Section tabs are links with `aria-current="page"`. Meter is text, not a progressbar; its accessible sentence is in A11Y §3. Skip link "Skip to content" first.

---

## S-01 · Landing

**Purpose:** turn a visitor with a webhook problem into someone with a live capture URL, and plant "one delivery isn't a test".
**URL:** `/`. **Shell:** landing header (NAV §1.2).

### Layout regions (order fixed by UF Screen 1)

| # | Section | Content | Priority |
| - | ------- | ------- | -------- |
| 1 | Hero | H1 "Your webhook handler works. Once." · subline (A02, not in RD) · **Get a URL, no sign-up** · `npx @webhooktrap/agent` copy chip · trust line "Open source · self-hostable · secrets stay on your machine" | P1, above the fold at 1440 × 900 and 390 × 844 |
| 2 | Demo strip | `invoice.paid` delivered once (200, `orders.count 1`), again (200, `orders.count 2`), then "Duplicate delivery created a second order" with "Stripe may deliver the same event more than once." | P1 |
| 3 | Every way a webhook can arrive | The **9** MVP scenarios, RD §5 order, each: name, one-line what, one-line why, citation. Then "Sequence packs and test commands: later." (**NEW**) | P2 |
| 4 | Real traffic, not fixtures you wrote | capture → scrub → commit → CI, with `webhooktrap/fixtures/stripe/invoice.paid.json` and the scrub rules from RD `run.fixtures.scrub` | P2 |
| 5 | Secrets stay on your machine | plain statements; licence; `docker compose up`. Probe line, exact (PV-14): "Probe values stay on your machine. Only small counts and codes reach the server." The phrase "never leaves the machine" is not used about probe values | P2 |
| 6 | Works with your coding agent | F2 as JSON: one `findings[]` element from RD (`id`, `type`, `severity`, `title`, `evidence`, `fix.text`) | P2 |
| 7 | Providers | "Stripe · GitHub · Shopify · Standard Webhooks" then "Coming: Twilio, Slack". Names only, **no budgets** (A-18) | P3 |
| 8 | Footer CTA | "Prove your first handler in 5 minutes." + CTA · Docs · GitHub · Self-host · Status · Privacy | P2 |

### States

| State | What changes | Copy |
| ----- | ------------ | ---- |
| First visit | as above | — |
| Creating | CTA label "Creating your URL…", disabled, ≤ 400 ms | UF |
| Create failed | Inline under the button: "Couldn't create a URL. Try again." Button re-enabled. Never a modal | UF |
| Guest mode off (self-host) | CTA becomes **Sign in to get a URL**; helper "Guest URLs are off on this instance." (**NEW**) | — |
| Returning guest | CTA **Open your capture** + `webhooktrap.dev/c/k7Qm2xVb9TfL · 13 deliveries · expires in 23 h 49 m`; text link "Get a new URL" | RD `guest` |
| Signed in | Header and hero CTA **Open Brindle Coffee** | — |
| Reduced motion | Demo shows its final frame; **Play** replays on request | A11Y §7 |
| No JavaScript | Hero, demo final frame and form CTA work server-side | IA E1 |

### Responsive

1440: content max 1200, hero ≤ 760 tall. 390: stacked hero, full-width CTA 48 tall, sticky bottom CTA bar after the hero (52), hidden near the footer CTA; scenario list 1 column.

### Keyboard and accessibility

No shortcuts. One `h1`; each section `h2`. Demo text alternative (visible): "Delivery 1 of invoice.paid: 200, orders.count 1. Delivery 2, same event: 200, orders.count 2. Finding: Duplicate delivery created a second order." Pause/Play button if it animates longer than 5 s.

---

## S-02 · Sign in

**Purpose:** sign in with whatever the person already has (password, GitHub, Google or a passkey); claim a guest session (FL-7). **Rev. 3** (decision 44, A05 contracts §3.4, U-1): the email sign-in link is gone and email + password and passkeys are in.: the email link is dropped on A09's advice (§12.4); if the PO keeps it, the rev. 2 "Email me a sign-in link" block returns under the password form.
**URL:** `/sign-in?next={path}`. **Shell:** bare. **Ceremony page** (WebAuthn allowed by `Permissions-Policy`, A05 §3.4). **Flows:** FL-7, FL-16, FL-17, FL-22.

### Layout (desktop 1440: one centred column, max 440, top at 120; mobile 390: full width, 16 gutters, top at 32)

| # | Region | Content and copy (**NEW** unless noted) |
| - | ------ | ------------------------------- |
| 1 | Heading | `h1` "Sign in to Webhook Trap" |
| 2 | Guest note (guest only) | "Your guest session and its deliveries move to your account." (rev. 2) |
| 3 | Providers | **Continue with GitHub** · **Continue with Google** (full-width, 44 tall, provider mark at left in `ink`, no brand colours) |
| 4 | Passkey | **Sign in with a passkey** (secondary, full width, key glyph). Helper under it in `ink-muted`: "Use Touch ID, Windows Hello, your phone or a security key that holds a passkey." Hidden when `passkeysAvailable = false` (see states) |
| 5 | Divider | hairline with "or use your email" |
| 6 | Password form | Field "Email" (`type=email`, `autocomplete="username webauthn"` so the browser can offer passkeys in the autofill list: conditional UI) · Field "Password" (`autocomplete="current-password"`, **Show** toggle) with link "Forgot password?" right-aligned on the label row · **Sign in** (primary) |
| 7 | Footer lines | "New to Webhook Trap? Create an account" (link to S-24) · "Had an account before 2.0? Set a password to sign in." (link to S-26 forgot; shown on hosted during the launch period, to everyone, so it reveals nothing; **[PO approves text]**, PO-H, FL-22) |

Order is fixed: providers and passkey first because they need no typing (A11Y G14). Desktop and mobile use the same order.

### States

| State | What changes | Copy (**NEW** unless noted) |
| ----- | ------------ | ---- |
| Default | as above | — |
| Signing in (password) | **Sign in** label "Signing in…", disabled, fields read-only, ≤ 2 s typical | — |
| Wrong email or password (also: unknown email, imported 1.x account without a password, disabled account) | Error summary above the form (`role="alert"`, once); password field cleared and focused; email kept | "Email or password is incorrect." (A05 §3.4, exact, uniform) |
| Throttled (per email + address, per address; IPv6 on /64) | Error summary; **Sign in** stays enabled. Rev. 4: the time is the server's `Retry-After` added to now and rounded up to the minute; no duration is printed, because A05 gives the sign-in throttle no number (domain §2.1) **[pending A05 value]**. Example from RD `account_security.maya.sign_in_throttled_until` | "Too many attempts. Try again after 14:58 UTC." (replaces the "Wait {n} minutes" value A04 had to supply) |
| Field errors (client) | Next to the field | Email: "Enter an email address, such as maya@brindlecoffee.example." (rev. 2) · Password: "Enter your password." |
| Passkey: waiting | Button label "Waiting for your passkey…", spinner glyph, disabled; the browser or OS sheet is on top | — |
| Passkey: cancelled or timed out (5 min) | Inline under the passkey button (`role="status"`) | "Passkey sign-in was cancelled. Nothing changed." |
| Passkey: not recognised, flagged, user-handle mismatch, counter refused | Inline under the passkey button (`role="alert"`). **Uniform:** the page never says which (WA-1, WA-2, TM-120, TM-121) | "That passkey didn't work. Try another passkey or another way to sign in." |
| Passkey: no passkey on this device | Nothing from us: the browser shows its own "no passkeys" sheet; after cancel, the cancelled state | — |
| Browser without WebAuthn (`window.PublicKeyCredential` missing) | Passkey button hidden; one line in its place in `ink-muted` | "This browser can't use passkeys. Use your email, GitHub or Google." |
| Instance can't use passkeys (`passkeysAvailable = false`: self-host on plain HTTP other than `localhost`, or on an IP address; U-6) | Passkey button hidden; line in its place | "Passkeys need this instance to run on HTTPS with a domain name. Ask your administrator." |
| OAuth cancelled | Inline under the providers | "Sign-in was cancelled. Nothing changed." (rev. 2) |
| OAuth error | Inline under the providers | "GitHub didn't let us sign you in. Try again, or use another method." (rev. 2; "Google" for Google) |
| OAuth email not verified by the provider, and an account exists with that email | **Uniform** with a new sign-up: the provider identity is not linked by email (pre-hijack rule, domain §2.4); the person lands on S-25 for a new account with that email | — (see S-25) |
| Two-step sign-in needed | Redirect to S-27 (password, GitHub or Google with 2FA on). Passkey sign-ins never go there | — |
| Email not verified | Redirect to S-25 `check inbox` | — |
| Recovery pending on this account | After sign-in (and S-27), the S-00 recovery banner shows; a sign-in that completed a second factor cancels the recovery (RC-1) and shows the S-29 `cancelled by sign-in` flash | see S-29 |
| Invite only (self-host) | "Create an account" line replaced | "Sign-ups on this instance need an invite. Ask your administrator." (rev. 2) |
| Sign-ups closed (self-host) | "Create an account" line removed | — |
| Signed in already | Redirect to `next` or the last project | — |

### Responsive, keyboard, accessibility

Same column at 390 (16 gutters, buttons 48 tall, the "Forgot password?" link moves under the password field). Paste allowed in every field; password managers allowed (`autocomplete` as above); no CAPTCHA (A11Y G14). Focus to `h1` on load; Tab order = visual order. Errors next to the field and summarised once (`role="alert"`). The passkey ceremony never moves focus; on return, focus goes back to the passkey button and the result line is announced. A11Y §9 "Sign in" row.

---

## S-03 · Capture stream

**Purpose:** the project's home: every delivery as it arrives, judged, grouped by the object it concerns, with Prove everywhere.
**URL:** `/p/{projectKey}` (`?sel=01&tab=raw&view=flat&q=…&group=…`). **Data:** RD §2, 13 deliveries in 7 groups.

### Layout regions (desktop 1440)

| Region | Size | Content |
| ------ | ---- | ------- |
| Capture header | 56 | "Arrivals" · live indicator (Live / Paused / Catching up / Reconnecting) · counts "13 deliveries · 7 groups · last 14:06:39" · Grouped│Flat · Filter |
| List | 480 (resizable 400–640) | treegrid: group rows and delivery rows |
| Detail pane | rest (≈ 960) | S-04 for the selected row |

**Group row:** short id or name (`sub_1QH…4drI`), provider, subtitle, count, spread, worst signature state, **Prove**. **Delivery row:** `13:58:04.212` · provider · event type · size `2 294 B` · signature (word or glyph + name) · shape flag only when not "matches" · Prove on hover, focus or selection.

**Group order** (most recent activity first, RD times): Ungrouped (#13) · `shp_1c263…ztsP` (#08 #12) · `sub_1QG…qS1C` (#11) · Order #1043 (#10) · `brindle/brindle-app` (#07 #09) · Order #1042 (#05 #06) · `sub_1QH…4drI` (#01–#04). Deliveries inside a group oldest first.

**Default selection:** newest group's newest delivery (#13). A04's main frame uses `?sel=01` (#01 selected), as in UF §9.

**Prove rules:** group Prove selects the group's events and all 9 scenarios; single-delivery Prove marks Shuffled `n/a · Needs 2+ related events` (Burst then sends two copies, BURST-1). A group with two providers can't exist (GRP-5), so DEF-1 refusals only come from manual selection in Flat view.

### States

| State | List | Detail | Header | Copy keys |
| ----- | ---- | ------ | ------ | --------- |
| **Waiting** (0 deliveries; guest meter `copy.guest.meter_waiting` "… 0/100 deliveries · 0/10 runs") | Onboarding panel, single column max 720: URL large + **Copy URL**, provider tabs **Stripe · GitHub · Shopify · Standard Webhooks · curl**, "Waiting…" with the live mark, **Send a test request** | hidden | Live | `copy.capture.waiting` (+ `.providers.*`) |
| **Loading** | 8 skeleton rows at real widths, no shimmer | Verdict tab skeleton | "Loading deliveries…" | `copy.capture.loading` |
| **Receiving** | as specified; guest meter after #01–#04: `copy.guest.meter_first_four` "… 4/100 deliveries · 0/10 runs"; the full stream uses `copy.guest.meter` (13/100 · 1/10) | S-04 | Live | `copy.capture.receiving` |
| **New while scrolled** | pill at top of list "3 new deliveries" (`n`) | unchanged | Live | `copy.capture.new_pill` |
| **Paused** | frozen; pill "4 new while paused · Resume" | unchanged | Paused | UF |
| **Catching up** | inserts without motion, cleared after 4 s | unchanged | Catching up | `copy.capture.paused` |
| **Stream lost** | banner above list | unchanged | Reconnecting | `copy.capture.stream_lost` |
| **Guest quota** | persistent banner (not dismissible); list usable; Prove works while runs remain | unchanged | meter warning | `copy.capture.quota` |
| **Filter: no results** | message + **Clear filter** | last selection kept | — | `copy.capture.no_results` |
| **Body not kept** (partial) | row shows size and "body not kept" | S-04 explains | — | `copy.capture.body_not_kept` |
| **Server error** | error block + **Retry** + ref | — | — | `copy.errors.server` |
| **Offline** | banner | — | Offline | `copy.errors.offline` |
| **Expired** (guest) | whole screen replaced by S-18 `expired` | — | — | `copy.capture.expired` |
| **Guest, no agent** | group header "4 · signatures not checked"; rows glyph only | S-04 `no agent` + Prove block | — | `copy.signature.no_agent` |

**Send a test request** helper (**NEW**): "Sends a small JSON body to your URL from this browser."

### Responsive

**390:** single-column list; sticky group headers; tap pushes `/p/{projectKey}/d/{n}` (S-04 page); URL chip becomes **Copy URL** in the capture header; Pause and Filter in the header `⋯`; Prove on group headers and the detail page.

### Keyboard (A11Y §5.3)

`↑ ↓ ← → Home End Enter` in the treegrid · `j k` rows · `p` Prove row · `Shift P` Prove group · `1 2 3` tabs · `[ ]` previous/next in group · `/` filter · `n` newest · `.` pause · `v` grouped/flat · `y` copy raw body · `w` wrap · `Esc` back to list, then clear.

### Accessibility

Treegrid, one tab stop, roving tabindex; new rows never steal focus or move the selected row; arrivals announced ≤ 1 per 5 s (`capture.arrivals`). Group rows: "Order #1042, Shopify, 2 deliveries". Row signature in words.

---

## S-04 · Delivery detail

**Purpose:** is this delivery right? Exact evidence, and Prove.
**URL:** pane of S-03; page `/p/{projectKey}/d/{n}` (canonical; mobile and deep links).

### Layout regions

| Region | Content |
| ------ | ------- |
| Header (72–96) | provider · event type (large) · `#01 · 13:58:04.212 · 0.2 s after Stripe created it · 2 294 B · in sub_1QH…4drI (4)` · **Prove** · `⋯` (Copy body · Copy as curl · Download raw · Copy delivery id · Copy as JSON; Delete for members, with confirm) |
| Path strip | `created ·──\ received   ingest 56 ms` (TT G1) |
| Verdict lines | signature chip + reason · shape chip · group link |
| Prove block (guest, no run yet) | "Prove the handler that receives these" + **Prove invoice.paid** + "See an example run ›" + Without / With the agent table (TT §2.1 G1 copy) |
| Tabs | Verdict · Raw · Decoded |
| Footer | ‹ previous in group · next in group › |

**Verdict tab:** 1. Signature block (state, scheme, who checked, detail; invalid: **Reason** + evidence + **Fix** + **Re-check**) · 2. Shape (matches / new / differs with paths) · 3. Provider metadata (event id, API version, livemode, `X-GitHub-Delivery` / `X-Shopify-Webhook-Id` / `webhook-id`) · 4. Related deliveries in the group.
**Raw tab:** request line with raw query, headers in received order with index numbers (table: #, name, value), body exact bytes with byte-offset gutter; wrap off (desktop), on (mobile); **Copy body** · **Download raw** · **Copy as curl**; `Authorization`, `Proxy-Authorization` and `Cookie` show `[redacted · N bytes]` (e.g. `[redacted · 41 bytes]`), with no hash or prefix (A05 S-7); the row keeps its index.
**Decoded tab:** RD `decoded_summary` lines, then a JSON tree; ids as copyable chips; timestamps with human dates.

### Signature states (all drawn)

| State | Label | Helper / detail | Example | Copy keys |
| ----- | ----- | --------------- | ------- | --------- |
| Valid | Signature valid | "Checked on maya-mbp with STRIPE_WEBHOOK_SECRET." (SIG-3, exact) + detail | #01 | `copy.signature.valid`, `deliveries[0].signature` |
| Invalid | Signature invalid | Reason "Signed with a different secret"; detail lists the **7 checks** in SIG-2 order, and no expected or received digest (SIG-3); Fix + **Re-check** | #09 | `copy.signature.invalid`, `deliveries[8].signature` |
| No secret | No secret | Parcelbird helper; fix command `webhooktrap secrets set parcelbird` | #08, #12 | `copy.signature.no_secret` |
| Unknown scheme | Unknown scheme | "There is a signature-like header (X-Signature)…" + **Define scheme** link | copy only | `copy.signature.unknown` |
| Unsigned | Unsigned | "No signature header. Nothing to check." | #13 | `copy.signature.unsigned` |
| Not checked | Not checked | First the facts read without a secret, then "Secrets live on your machine. Connect the agent to check signatures." | guest #01 | `copy.signature.no_agent` |
| Checking | Checking… | shown only after 300 ms (PERF-2) | — | — |

The 7 checks render as a list with ✓/✗ glyph + word: "Content-Length matches · valid UTF-8 · no BOM · no re-encoding detected · no signed timestamp (GitHub) · header well-formed · scheme known".

### Shape states

| State | Label | Example |
| ----- | ----- | ------- |
| Matches | Shape matches baseline | #01 "invoice.paid · API 2024-06-20 · 41 fields, same as 6 earlier captures" |
| New | New shape (first capture) | #05 |
| Differs | Shape differs from baseline | #11 "New field cancellation_details.feedback not in the saved fixture for customer.subscription.updated" |
| No baseline | No baseline | #13 |

### Other states

| State | Behaviour | Copy keys |
| ----- | --------- | --------- |
| Loading | header from list data at once; tabs skeleton ≤ 300 ms | — |
| Body not kept | Raw explains with SHA-256; Decoded disabled with reason; Prove disabled: "Can't replay without the body" | `copy.capture.body_not_kept` |
| Binary | hex + ASCII columns; Decoded disabled | `copy.capture.binary` |
| Not found | full pane message + back to stream | `copy.errors.not_found` |
| Prove disabled (two providers in a manual selection) | `copy.run.draft_two_providers` | — |

### Responsive

**390:** full page; header compresses to provider + event + chips; **Prove** is a sticky bottom button (52) when an agent is connected, a secondary button opening "Pair the agent from your computer" (TT GM3) when not; Raw scrolls inside its block only.

### Keyboard and accessibility

`1 2 3` tabs · `p` Prove · `y` copy body · `w` wrap · `[ ]` group navigation · `Esc` back. The invalid reason is the first focusable text after the chip and is read when the delivery opens. Raw body is a focusable region "Raw body, 2 294 bytes".

---

## S-05 · Runs list

**Purpose:** every run of this project from every source, with its outcome.
**URL:** `/p/{projectKey}/runs?source=web|cli|ci&status=…`. **Data:** RD `run_history`.

### Layout

Header: "Runs" · filters **Source: All ▾** (Web · CLI · CI) · **Status: All ▾** (Couldn't start · Aborted · Findings · Inconclusive · Passed). Grid columns:

| Run | Status | Source | From | Started | Length | CI exit |
| --- | ------ | ------ | ---- | ------- | ------ | ------- |
| #4 | ▬ Passed · 1 Info | Web · maya-mbp | sub_1QH…4drI | 14:32:02 | 23.4 s | 0 |
| #3 | ■ 3 findings | Web · maya-mbp | sub_1QH…4drI | 14:09:02 | 43.2 s | 1 |
| #2 | ⫽ Aborted · agent lost | Web · maya-mbp | sub_1QH…4drI | 13:57:02 | 2 of 16 | 2 |
| #1 | — Inconclusive | Web · maya-mbp | #01 | 13:28:02 | 1.4 s | 0 |

**Source** is the provenance in short form (RUN-8): `Web · maya-mbp`, `CLI · maya-mbp`, `CI · github-actions` (token name only), `Server runner`. The full sentence is on the run (S-11). The worst severity's glyph precedes "N findings" and is decorative (the word carries it). "CI exit" is the code the run would have had with defaults (EXIT-2); header tooltip "The exit code a CI job would get with the default --fail-on high." (**NEW**). Aborted rows show the reason in words (`agent lost`, `target lost`, `reset failed`, `time limit`, `stopped`). Couldn't start rows show `⊘ Couldn't start · {reason}` with the `tt-g-blocked` glyph.

### States

| State | Copy |
| ----- | ---- |
| Empty | "No runs yet. Prove a delivery to start one." + **See an example run ›** + **Go to Capture** |
| Filter empty | "No runs match these filters." (**NEW**) + **Clear filters** |
| Loading | 4 skeleton rows |
| Running row | status "┃ Running · D11 of 16", updates per scenario |
| Error | `copy.errors.server` with Retry |

### Responsive, keyboard, accessibility

390: list rows of two lines (`#3 · ■ 3 findings` / `Web · 14:09:02 · 43.2 s`). Keys: grid `↑ ↓ Enter`, `j k`, `/` filter. Grid with column headers; status words in every row.

---

## S-06 · Agent (guest) and Agents (signed in)

**Purpose:** see the agent that runs deliveries on your machine; pair one.
**URL:** guest `/p/{projectKey}/agent` (one agent); signed in `/agents`.

### Layout

Signed in: header "Agents" + **Pair a new agent** (→ S-08). One row per agent: glyph · name · state word · `agent 0.9.2` · `targets localhost:3000` · `secrets: 3 names` · last seen. State words add **Confirm in your terminal** (`awaiting_confirmation`, `needs_reconfirm`). Row opens S-07.
Guest: the single agent's details inline (as S-07, without Revoke: guest agents end with the session), or the pair panel (S-08 component) when none.

### States

| State | Content | Copy keys |
| ----- | ------- | --------- |
| None | pair panel: command, code, countdown | `copy.agent.not_installed`, `copy.agent.pairing` |
| Connected | row as above | `copy.agent.connected` |
| Target unreachable | row state word + helper | `copy.agent.unreachable_refused` |
| Offline | "maya-mbp · offline · last seen 13:57:04" | UF §5.3 |
| Update needed | update command | `copy.agent.too_old` |
| Confirm in your terminal (pairing) | row word + `copy.agent.awaiting_confirmation`; the agent gets no jobs yet | `copy.agent.awaiting_confirmation` |
| Confirm in your terminal (after a claim) | row word + `copy.agent.needs_reconfirm`; Run disabled with that reason | `copy.agent.needs_reconfirm` |
| Pairing declined | pair panel returns with `copy.agent.pair_declined` and **Get a new code** | `copy.agent.pair_declined` |
| Replaced (guest) | toast + new row; the replacement takes effect only when the new agent is confirmed in its terminal (A05 §5.1) | `copy.agent.replaced` |

### Responsive, keyboard, accessibility

390: rows stack. No screen keys. The pairing code reads character by character (A11Y §3). Secret names are text; the line "Names only, never values." is always visible.

---

## S-07 · Agent detail

**URL:** `/agents/{name}`. **Breadcrumb:** `Agents › maya-mbp`.

### Sections

| Section | Content |
| ------- | ------- |
| Status | `◉ Connected · since 13:55:53` · `agent 0.9.2` · `macOS 15.4 (arm64)` · repo `~/code/brindle-app` |
| Targets (allow-list) | `localhost:3000`; helper: "The agent only sends to targets on this list. Add one on your machine: webhooktrap targets add <host:port>. The server can't add targets." (**NEW**, TGT-2) |
| Secrets | table Provider · Name · State: Stripe `STRIPE_WEBHOOK_SECRET` set · GitHub `GITHUB_WEBHOOK_SECRET` set · Shopify `SHOPIFY_API_SECRET` set · Parcelbird "not set" + `webhooktrap secrets set parcelbird`. Line: "Names only, never values." No hint column: the server holds no part of a secret (PO-D) |
| Used by | projects that ran with this agent (P4): "Brindle Coffee" |
| Revoke | **Revoke agent** → confirm "Revoke maya-mbp? It disconnects now and must be paired again." (**NEW**) |

States: offline, update needed, unreachable (as S-06). 390: sections stack. Keys: none. Revoke dialog names the agent.

---

## S-08 · Pair a new agent

**URL:** `/agents/pair` (signed in); the same **pair panel** appears in S-06 (guest) and S-10 step 1.

### Pair panel

| Element | Content |
| ------- | ------- |
| Intro | "The agent runs on your machine, sends deliveries to localhost and keeps your signing secrets there." (`copy.agent.not_installed`) |
| Code | `7KQ2M-XR9TD` (10 symbols, PO-F) in Martian Mono 32 in a ruled frame, hyphen after the fifth symbol; "expires in 9:41". At 390 the code drops to Martian Mono 24 and stays on one line |
| Command | `npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD` + **Copy command** |
| Status | "Waiting for your agent…" / "Run the command in a terminal. Code 7KQ2M-XR9TD expires in 9:41." |
| What the terminal will ask (**NEW**, always visible under the command) | "The agent shows the server address first, then asks you to confirm. Only type y if it shows https://webhooktrap.dev." |
| Facts | "Sends deliveries only to targets you allow · keeps signing secrets on your machine (we see names, never values) · open source, MIT" |

### States

| State | Content | Copy |
| ----- | ------- | ---- |
| Not installed | command + code | `copy.agent.not_installed` |
| Pairing (command copied) | status line live | `copy.agent.pairing` |
| Confirm in your terminal | the agent has used the code and printed the server, the workspace and `[y/N]` (FL-2); panel shows "Confirm in your terminal" + `copy.agent.awaiting_confirmation`; countdown removed; no jobs reach the agent yet | `copy.agent.awaiting_confirmation` |
| Declined (N in the terminal) | panel resets with `copy.agent.pair_declined` + **Get a new code** | `copy.agent.pair_declined` |
| 1 minute left | "Expires soon" word added; announced once | A11Y §6.2 |
| Expired | "Code 7KQ2M-XR9TD expired." + **Get a new code** (**NEW**) | — |
| Connected (after `y`) | `◉ Connected · maya-mbp · agent 0.9.2` + **Done** (signed in) / step collapses (draft) | `copy.agent.connected` |
| Mobile | "Pair the agent from your computer" variant with **Copy command** and **Copy link** (TT GM3) | TT §2.2 |

Keyboard: none beyond Tab. Code readable character by character ("7 K Q 2 M, X R 9 T D", RD `agent.pairing_code_spoken`); countdown not a live region except at 1:00 and 0:00. The change to "Confirm in your terminal" is announced once (polite).

**Terminal side** (drawn in the pairing terminal frame, text in GF §15.6): the agent prints `Server     https://webhooktrap.dev` (the origin from its own configuration), then `Workspace  Guest session · expires 2026-11-12 13:55 UTC`, then "Join? Only continue if you created this code in your own browser just now. [y/N]". The same prompt is asked again after a claim (RD `agent.reconfirm_terminal`).

---

## S-09 · Fixtures

**Purpose:** fixture sets in the repo, their sync state and drift.
**URL:** `/p/{projectKey}/fixtures`. **Data:** RD `run.fixtures`, `copy.fixtures`.

### Layout

Header "Fixtures" · `repo: ~/code/brindle-app via ◉ maya-mbp`. Grid: Set · Files · Scenarios · State · Last CI run.
Row: `webhooktrap/fixtures/stripe` · `3 + webhooktrap.yml` · `8` · state · `—`. Opening a row lists the files (read through the agent) and the drift detail.

### States

| State | Content | Copy keys |
| ----- | ------- | --------- |
| No agent | "Fixtures live in your repo. Pair an agent to read them here." + what hosted keeps (names, shapes, sync state) + **Pair agent** | IA §9.2 |
| None saved | "No fixtures in brindle-app/webhooktrap/fixtures yet. Save a run as fixtures." + **Open latest run** | IA §9.2 |
| Not saved (from a run) | `copy.fixtures.not_saved` | — |
| Saved · in sync | `copy.fixtures.saved` | — |
| Shape drifted | word "Drifted" on the tab badge; row helper with the path | `copy.fixtures.drifted` |
| Agent offline | names and states from the last sync, with "Last read through maya-mbp at 14:12." (**NEW**) | — |

390: rows stack. Keys: grid `↑ ↓ Enter`, `j k`.

---

## S-10 · Run draft

**Purpose:** confirm what will be sent, to where, and why; then Run.
**URL:** `/p/{projectKey}/runs/new?from=01,03,04` or `?group={key}`. **Breadcrumb:** `Brindle Coffee › Runs › New run`; From line `From sub_1QH…4drI · #01 #03 #04`.

### Layout (desktop)

Left: setup form 1000 wide, steps as fieldsets. Right (440): ghost graph (one glyph-graph per selected scenario) and, for guests, "Guest · runs left 9 of 10".

| Step | Content (satisfied steps collapse to one line) |
| ---- | ----------------------------------------------- |
| 1 Agent | pair panel (S-08), "Confirm in your terminal" (S-08 state), or `◉ Connected · maya-mbp · agent 0.9.2 · target localhost:3000/webhooks/stripe` |
| 2 Target | text field `http://localhost:3000/webhooks/stripe`; option **From Webhook Trap servers (public URLs only)** (P6), for **accounts only** and only while the operator's kill switch is on (PO-B, HD-10, RUN-11); helper "Checked against maya-mbp's allow-list when the run starts." (**NEW**) |
| 3 Events | `#01 invoice.paid · #03 payment_intent.succeeded · #04 customer.subscription.updated` (removable chips); one provider only (DEF-1) |
| 4 Scenarios | 9 rows in PLAN-1 order (below) |
| 5 Verdict level | "Transport (always)" · "State probe: orders.count `count`" (one line per entry, tagged `count`, `code`, `shown (show: value)` or `shown (show: number)`, from the agent's report, PV-14) · "Reset: before every scenario, run on maya-mbp" · the PV-14 sentence, exact: "Probe values stay on your machine. The web app shows counts up to 9 999 as numbers and other values as short codes such as #a1f3c0, which only show whether a value changed." · helper "The probe and reset commands are shown and confirmed in maya-mbp's terminal." (**NEW**). The probe's `sql:` text and the reset command are **never** shown here (PV-8) |
| 6 Warnings | only when relevant: side effects (NAME-4), needs secret, no reset |
| 7 Summary | "{k} scenarios selected · {n} deliveries · about {s} s" + **Run** (`⌘↵`) |

**Scenario rows** (n = 3 events; each row: checkbox · name · what · why + citation · deliveries · flag):

| Scenario | Deliveries | Default | Flag or disabled reason |
| -------- | ---------- | ------- | ----------------------- |
| Once | 3 | checked, **locked** | "Always runs: it sets the baseline." (**NEW**, PLAN-2) |
| Twice | 2 | checked | — |
| Shuffled | 3 | checked | with 1 event: `n/a · Needs 2+ related events` |
| Late | 1 | checked | GitHub, Shopify, unknown: `n/a · {Provider} signs no timestamp` |
| Retry after error | 2 | checked | GitHub: `n/a · GitHub doesn't retry automatically` |
| Tampered | 1 | checked | non-JSON body: `n/a · Body isn't JSON` |
| Unsigned | 1 | checked | unknown scheme: `n/a · Nothing to remove` (**NEW**) |
| Burst | 3 | checked | — (1 event: sends 2 copies) |
| Slow budget | 1 | checked | — |

What/why/citation text: RD `scenarios[]`. Deliveries: RD `scenarios[].deliveries_rule` evaluated with n. **On an n/a row the why line and citation are not shown** (the row keeps name, what and the n/a reason, ghosted).

**Server-target draft, Shopify (FL-9, Order #1042, n = 2)** — fills the `[Shopify why: A03 copy]`, `[citation: A03]` and mutation holes on the server-draft board. Copy from RD `examples.shopify_server_draft`:

| Scenario | What | Why | Citation | Deliveries | Flag |
| -------- | ---- | --- | -------- | ---------- | ---- |
| Once | Every selected event once, in capture order. Sets the baseline. | Every provider | — | 2 | locked |
| Twice | The first event again, 500 ms after the first delivery completes: same body, same X-Shopify-Webhook-Id | (n/a: not shown) | — | — | `n/a · Not verified for Shopify yet` |
| Shuffled | Related events in reverse order | Shopify doesn't guarantee that webhooks arrive in the order they were created. | `[pending A01]` | 2 | — |
| Late | Valid signature with an old timestamp | (n/a: not shown) | — | — | `n/a · Shopify signs no timestamp` |
| Retry after error | The first event again, 2 000 ms after the first attempt completes, as a retry | (n/a: not shown) | — | — | `n/a · Not verified for Shopify yet` |
| Tampered | One field changed in the body (line_items[0].quantity 2 → 0), sent with the original signature header | A signature only protects you if a failed check stops the request. | `[pending A01]` | 1 | — |
| Unsigned | The first event with X-Shopify-Hmac-Sha256 removed | A handler that checks the signature only when the header is present accepts anything. | `[pending A01]` | 1 | — |
| Burst | Every selected event, released together (≤ 20 ms apart) | Shopify can send related webhooks, such as orders/create and orders/paid, within a second of each other. | `[pending A01]` | 2 | — |
| Slow budget | One more delivery after the others, measured against the budget: 5 s · Shopify limit | Shopify counts a response slower than 5 s as failed and retries it. | `Shopify docs` + page `[pending A01]` | 1 | — |

Summary "6 scenarios selected · 9 deliveries"; budget `5 s · Shopify limit` everywhere on the board (not the Stripe 10 s label). The Tampered mutation follows TAMP-1 rule 2 because the Shopify pack has no `tamper_paths` yet: first numeric field named like `quantity` → `line_items[0].quantity` 2 → 0. `[pending A01]`: if the Shopify pack gets `tamper_paths`, rule 1 replaces this line. Citations stay bracketed until A01 verifies the Shopify pages (DUP-1, DUP-2); A04 draws them as `[pending A01]` in `ink-muted`, never Stripe's citations.

### States

| State | Content | Run button | Copy keys |
| ----- | ------- | ---------- | --------- |
| No agent | step 1 expanded | disabled: "Pair an agent to run against localhost" | `copy.agent.not_installed` |
| Pairing | step 1 live | disabled (same reason) | `copy.agent.pairing` |
| Ready (default) | summary "9 scenarios selected · 17 deliveries · about 50 s" | enabled | `copy.run.draft` |
| Run #3's selection | Unsigned unchecked: "8 scenarios selected · 16 deliveries · about 45 s" | enabled | `copy.run.draft_run3` |
| Needs secret | step 1 shows **Needs secret** + `webhooktrap secrets set stripe` | disabled | `copy.run.needs_secret` |
| Update needed | step 1 warning | disabled | `copy.agent.too_old` |
| Target down (agent reports) | step 2 helper | enabled | `copy.agent.unreachable_refused` |
| Over the cap | summary in warning style | disabled | `copy.run.draft_over_cap` |
| Two providers | step 3 error | disabled | `copy.run.draft_two_providers` |
| No probe | step 5 "Add a probe" with the pack's yaml template under the label "Example" (not read from your file); Level 2 scenarios show "Transport only" | enabled | `copy.run.inconclusive` (helper) |
| No reset | step 6 order-dependent warning | enabled | `copy.run.order_dependent` |
| Side effects (first run on this agent and target) | step 6 warning; Run opens S-12 `side-effects` | enabled | `copy.run.side_effects` |
| Confirm in your terminal | step 1 shows the S-08 state; after a claim, `copy.agent.needs_reconfirm` | disabled: "Confirm maya-mbp in its terminal first" (**NEW**) | `copy.agent.awaiting_confirmation`, `needs_reconfirm` |
| Server target (P6, account, kill switch on) | step 1 "Not needed for a server run"; step 5 "Transport only: the probe runs on your machine"; scenarios n/a per IA §5.4. Shopify rows (FL-9): RD `examples.shopify_server_draft` (below) | per provider | **NEW** strings in FL-9 |
| Server target, guest | the option is shown disabled with "Server runs need an account. Sign in to use them." (**NEW**, IA7: shown with its reason, not hidden) | — | — |
| Server target, kill switch off | the option is not offered (A05 `targets` omits it; API answers `server_runs_disabled`) | — | — |
| Server target, private address | step 2 error: "This address is private. Server runs only reach public addresses. Use the agent for private and local targets." | disabled | **NEW** |
| Server target, Stripe | "Stripe signs a timestamp, so a server run needs a secret. Use the agent." | disabled | **NEW** |
| Guest run quota | summary replaced | disabled | `copy.guest.run_quota` |
| Guest, last 5 min | summary replaced | disabled | `copy.guest.run_too_late` |
| Unsaved changes, leaving | browser confirm only if target or scenarios changed | — | NAV §4.2 |

### Responsive

**390:** one scroll; ghost graph hidden; sticky **Run** bar (52) above the bottom nav. Without an agent, step 1 is the "Pair from your computer" variant.

### Keyboard and accessibility

Tab through fieldsets; checkboxes Space; `⌘↵` Run. Disabled reasons are adjacent text referenced by `aria-describedby`. Legends carry each step's status ("Agent: connected, maya-mbp").

---

## S-11 · Run

**Purpose:** what did the handler do with each delivery, which findings, and what to fix.
**URL:** `/r/{runId}`, `/r/{runId}/D{n}`, `/r/{runId}/F{n}`, `?compare=D4`. **Owner and members only** (PO-A): anyone else gets S-18 Not found, and a share link is a separate `/s/{shareToken}` URL (S-13). **Breadcrumb:** `Brindle Coffee › Runs › Run #3 ▾`; From line `From sub_1QH…4drI · #01 #03 #04 · localhost:3000/webhooks/stripe`; **provenance line** under it (RUN-8, TM-17, exact copy from RD `copy.provenance`): "Run by maya-mbp from the web app" · "Run by maya-mbp from the CLI" · "Uploaded from CI (token github-actions)" · "Run by the Webhook Trap server runner", in `ink-muted`, with an info tooltip `copy.provenance.agent_web.helper` "Results come from the machine that ran them. Webhook Trap re-checks status codes and timings only." Shown in every state, on owner and shared views. No surface says "verified" about a run or finding. **Data:** RD §4 (Run #3), §4.6 (others).

### Layout regions (desktop 1440 × 900)

| Region | Height / width | Content |
| ------ | -------------- | ------- |
| Run header | 56 | breadcrumb + run switcher · actions **Run again** (`⌘↵`) · **Save as fixtures** · **Share** · `⋯` (Copy as JSON, Edit scenarios, Abort while running) |
| Verdict line | 48 | status word · counts · meta "8 scenarios · 16 deliveries · 43.2 s · CI exit 1" |
| Scenario strip | 64; 1000 wide | one cell per scenario **in the run** (8 for Run #3 at 125 px, 9 for Run #4 at 111 px) |
| Delivery graph | 264 | TT stations and lines; budget band; brackets |
| Lower zone | rest (≈ 420) | tabs **Deliveries** · **Inspector · D{n}** |
| Findings column | 440 | findings list, one open |

### Verdict line copy per status

| Status | Line | Meta | Copy keys |
| ------ | ---- | ---- | --------- |
| Queued | "Queued · Waiting for maya-mbp to accept the run…" | — | `copy.run.queued` |
| Queued, confirm in terminal (first run of a `webhooktrap.yml` version, new target, or after a claim) | "Queued · Confirm in maya-mbp's terminal: it shows the probe and reset commands from webhooktrap.yml and this target before it runs them." | — | `copy.run.queued_confirm` |
| Running, preflight | "Running · preparing: reset and probe check" (**NEW**) | elapsed | — |
| Running | "Running · D11 of 16 · Retry after error · 25.3 s elapsed" | live counts by severity, no numbers | `copy.run.running` |
| Findings | "3 findings · 1 Critical · 1 High · 1 Medium · 1 Info" | "8 scenarios · 16 deliveries · 43.2 s · CI exit 1" | `copy.run.findings` |
| Passed | "Passed · 1 Info" + the RUN-7 sentence on a second line | "9 scenarios · 17 deliveries · 23.4 s · CI exit 0" | `copy.run.passed` |
| Inconclusive | "Inconclusive" + reason sentence | "· CI exit 0 (1 with --fail-on-inconclusive)" | `copy.run.inconclusive` |
| Aborted | "Aborted" + reason sentence | "· CI exit 2" | `copy.run.aborted`, `aborted_*` |
| Couldn't start | "Couldn't start the run" + reason sentence | "· CI exit 2" | `copy.run.start_failed`, `couldnt_start_*` |
| Order-dependent (flag, any status) | second line: "Order-dependent: no reset ran between scenarios." | — | `copy.run.order_dependent` |

### Scenario strip (Run #3, RD §4.1)

Cell: line 1 icon + name; line 2 terminal + result; line 3 reading (`run.scenario_results[].reading`).

| # | Line 1 | Line 2 | Line 3 | Accessible name |
| - | ------ | ------ | ------ | --------------- |
| 1 | Once | ▬ Pass | `0 → 1` | Once: pass. D1 to D3, orders.count 0 to 1. |
| 2 | Twice | ▲ F2 High | `expected 1 · got 2` | Twice: finding F2, High. D4, D5. orders.count expected 1, got 2. |
| 3 | Shuffled | ▬ Pass | `○ F4 Info · 0 → 1` | Shuffled: pass, with F4, Info. D6 to D8, in reverse order. |
| 4 | Late | ▬ Pass | `400 · 41 ms` | Late: pass. D9 rejected with 400 in 41 ms. |
| 5 | Retry after error | ▲ F2 High | `△ F3 · 11 840 ms` | Retry after error: findings F2, High, and F3, Medium. D10, D11. |
| 6 | Tampered | ■ F1 Critical | `expected 4xx · got 200` | Tampered: finding F1, Critical. D12 returned 200, expected a 4xx. |
| 7 | Burst | ▬ Pass | `○ F4 Info · 0 → 1 (final)` | Burst: pass, with F4, Info. D13 to D15, released within 3 ms, orders.count 0 to 1 at the end. |
| 8 | Slow budget | △ F3 Medium | `10 420 ms · +420` | Slow budget: finding F3, Medium. D16 took 10 420 ms, 420 over the 10 s budget. |

**Cell states** (all drawn): pass · finding by severity · multi-finding (worst on line 2, next on line 3) · pass with Info · `— Inconclusive` + reason (line 3 from `copy.scenario_reasons.*`) · `Unreachable` (`tt-g-blocked`) · `n/a` + reason (ghosted) · `┃ Running` · `Waiting` · `⫽ Stopped` (+ "during D3 of 16") · `Not run` · selected · hover.

| Run state | Strip |
| --------- | ----- |
| Running at D11 | cells 1–4 final; cell 5 `┃ Running`, line 3 `△ Medium · D11 sent`; cells 6–8 `Waiting` |
| Passed (#4) | 9 cells `▬ Pass`; Twice and Retry line 3 `expected 1 · got 1`; Shuffled `○ F1 Info · 0 → 1`; Burst `○ F1 Info · 0 → 1 (final)` |
| Code-valued probe (states sheet, RD `examples.code_reading`) | flagged cell line 3 `got #9c0254` (the full "expected #a1f3c0 · got #9c0254" is in the accessible name, O-SS-3 rule); owner with `show: value`: `got past_due` |
| Aborted (#2) | cell 1 `⫽ Stopped · during D3 of 16`; cells 2–8 `Not run` |
| Inconclusive (#1) | 2 cells: Once `▬ Pass · transport only`; Twice `— Inconclusive · needs a probe` |
| Couldn't start | strip hidden |
| Draft, Queued | hidden / all `Waiting` |

### Deliveries grid

Columns: D · +ms · Scenario · Event (`= D4′` on a duplicate, `t − 360 s` on Late, `body changed` on Tampered) · Status · Latency (+ "over" marker) · State · Result. Rows grouped by scenario (scenario row as `rowheader`). Data: RD §4.2 (16 rows).

| Row kind | State cell | Result cell |
| -------- | ---------- | ----------- |
| Pass | `0 → 1` | `▬ pass` |
| Flagged (D5, D11, D12) | terminal + `1 → 2 · expected 1` | `▲ F2 High` (after the run; `▲ High` while running, FND-6a) |
| Flagged, code-valued (states sheet) | terminal + `#a1f3c0 → #9c0254 · expected #a1f3c0` (codes per §0.5) | as judged |
| Over budget (D10, D16) | `0 → 1` | `△ F3 Medium · +1 840 over` |
| Late (D9) | `0 → 0` | `▬ pass (rejected)`; latency never judged against the budget |
| Burst (D13–D15) | `—` | `▬ pass`; scenario row shows `0 → 1 (final) · released within 3 ms` |
| Sent, no result | `—` | "sent, no result" |
| Not run | — | "not run" (ghosted, ⫽) |
| Unreachable | — | `Unreachable` (`tt-g-blocked`) |
| Probe failed | "Not measured: probe failed" | as judged at Level 1 |
| Unsettled | `0 → 1 · unsettled` | `— Inconclusive` |

### State reading (inspector State row, TT §1.2)

Flagged exactly on SJ §8.5 rows. Values per §0.5 (who sees counts, codes, shown values). D5: "State after D5 · orders.count" / "Expected 1 · got 2" / "before 1 → after 2 · D4 left it at 1" / caption "Same request, same response, a different state." only with Compare on and identical status and bytes (D5 vs D4: yes; D11 vs D10: no). D12: "Expected 0 · got 1", no bracket. Unchanged (Run #4 duplicate): "Expected 1 · got 1", "unchanged, as expected". Not measured (#1): "—" + "Not measured. Add a probe to see your state." Not reached (#2 D3): "—" + "Not measured: the agent was lost during D3." Probe failed: "—" + "Not measured: probe failed." (**NEW**; the error text stays in the agent's terminal, PV-8). Unsettled: "0 → 1 · unsettled" + `copy.scenario_reasons.unsettled`.

**Code-valued reading** (states sheet only, RD `examples.code_reading`, never on Run #3 frames):

| Variant | Line 1 | Line 2 | Line 3 / tag |
| ------- | ------ | ------ | ------------ |
| Owner, default | "State after D4 · subscriptions.status" + tag `code` | "Expected #a1f3c0 · got #9c0254" | "before #a1f3c0 → after #9c0254" · note under it: "Code for a value that stays on your machine. Same code = same value within this run." |
| Owner, `show: value` | same + tag `shown (show: value)` | "Expected active · got past_due" | "before active → after past_due" (no code note) |
| Share viewer, Include probe values off | same, no tag | "Expected #a1f3c0 · got #9c0254" | as owner default |
| Unchanged | same | "Expected #a1f3c0 · got #a1f3c0" | "unchanged, as expected" |
| Widened (PV-4) | same | "Expected #a1f3c0d27e45 · got #9c0254b1f08a" | wraps; never truncated |
| Refused (PV-11) | same | "Expected #a1f3c0 · got #invalid" | tooltip `copy.probe_display.invalid` |

Each code carries the §0.5 tooltip; the probe name is shown to every viewer.

### Inspector

Request (left) | Response (right), then State full width. Request: `POST http://localhost:3000/webhooks/stripe` · size · sent `14:09:06.838 (+4 720 ms)` · signing ("re-signed (same event id)") · `Stripe-Signature` row reads "signed on maya-mbp · value not stored" (**NEW**; the server never stores signature values the engine sent, A05 schema §1) · headers in sent order including `Webhooktrap-Run`, `Webhooktrap-Delivery`, `Webhooktrap-Scenario`, `Webhooktrap-Dry-Run: 1` · body "Exact bytes of capture #01", or for D12 the diff `data.object.amount_paid 2400 → 0`. Response: status, latency against the budget as text ("196 ms of the 10 s budget · Webhook Trap default"), headers, body `{"received":true}`. **Compare with D4** switch (`d`). **Share view:** the source-capture header rows show only the SCR-3 allow-list (`Content-Type`, `User-Agent`, pack event-id and topic headers) plus the `Webhooktrap-*` headers; every other row reads "hidden in shared views" (**NEW**); a non-loopback target host reads `private host` (A05 schema §6 rule 5).

### Findings column

Entries in FND-6 order, F1 open by default; collapsed: glyph + word + id, title, 2 lines. Open: meta line (`▲ High · F2 · Level 2 · judged on maya-mbp · evidence D4 D5 · D10 D11`, offending D-refs bold; Level 1 findings read `Level 1 · transport`; CI runs read `judged in CI`, RUN-9), caption, beats *What happened* / *Why it matters* + citation / *Fix* with framework label "Express (detected from X-Powered-By)" and snippet, **Copy fix**, **Copy finding as JSON**. Likely cause line shows its text only (rule id in the JSON). Info entries carry "Info · not counted" (**NEW**). Copy: RD `run.findings[]` (F4 evidence **D6 · D15**, scenarios Shuffled and Burst, PRB-12a). Finding prose is rendered by the server from type + pack + params (A05 TM-16); a Fix snippet that suggests probe lines is the pack's template, shown under "Example", never the owner's `webhooktrap.yml` (PV-8).

**Optional, PROPOSED (P3):** "Since Run #3: 3 fixed · F1 Info still there" on Run #4, from finding fingerprints (FND-2). Drawn as one ruled line under the verdict; open item O-SS-2.

### States (all drawn)

| State | Graph / grid | Findings | Actions |
| ----- | ------------ | -------- | ------- |
| Queued | planned rows ghosted | "Findings appear here as the run goes." | Cancel |
| Running (D11 of 16) | rows fill in; now line; pending ghosted | titles without numbers, by severity | Abort (`Shift X`), Freeze view (`.`) |
| Findings (#3) | 16 rows | F1–F4, F1 open | Run again · Save as fixtures · Share |
| Passed (#4) | 17 rows, all pass | F1 Info only, open; else "No findings." + **What was checked**: `copy.run.checks_hosted` "Level 1 transport · Level 2 probe orders.count (count) · reset before every scenario, run on maya-mbp" (no reset command, PV-8) | Run again · **Save as fixtures** (emphasised) |
| Inconclusive (#1) | 3 rows, state `—` | Info `needs_probe` with the pack's yaml template, labelled "Example" | Copy yaml · Run again |
| Aborted (#2) | D1–D2, D3 "sent, no result", D4–D16 "not run" | partial findings, numbered (RUN-6) | Run again (when agent is back) |
| Aborted with findings | completed rows kept | proven findings, numbered, heading "Partial findings" (**NEW**). Draw `aborted_reset_failed`: D1–D11 kept with F2 and F3 numbered; banner `copy.run.aborted_reset_failed` (hosted wording, no command) | Run again |
| Couldn't start | only rows that were sent (baseline rejected: D1 with 401); no strip, no graph. `reset_failed` uses `copy.run.couldnt_start_reset_failed` (hosted wording: names maya-mbp, never the command or its output) | none | Run again · Edit scenarios · copy fix command when there is one (`target_not_allowed`, `needs_secret`, `agent_too_old` only) |
| Deep link to unknown D/F | run loads; inline note "D22 isn't in this run (16 deliveries)" | — | — |
| Agent offline, finished run (was "Owner offline") | full run from the server; ruled banner `copy.agent.offline_finished_run`: "maya-mbp is offline. This run is complete and shown as the server stored it. Run again and Save as fixtures need maya-mbp. Last seen 14:31:07." (`role="status"`). A running run whose agent drops is Aborted `agent_lost` instead | unchanged | Run again and Save as fixtures disabled with the banner as their reason; Share and Copy as JSON work |

### Responsive

**390 (report-first):** verdict → findings (tap expands) → strip as 2 columns (4 rows, or 5 for 9 cells; cell 179 × 44; line 1 glyph + name left, result right; line 2 reading) → deliveries list (compact two-line rows) → inspector full-screen sheet (push `/r/{runId}/D5`) with Request | Response tabs and the Compare switch. Sticky bar: **Run again** / **Save as fixtures**. Graph hidden by default; "Graph" toggle shows it.

### Keyboard (A11Y §5.4)

Strip `← → Home End Enter` · grid `↑ ↓ PgUp PgDn Enter` · findings `↑ ↓ Enter` · `j k` deliveries · `[ ]` findings · `t` grid · `f` findings · `d` compare · `.` freeze · `n` follow live · `s` Save as fixtures preview · `e` copy JSON · `⌘↵` Run again · `Shift X` abort · `Esc` inspector → Deliveries → clear.

### Accessibility

Figure + `desc` (A11Y §4) with Run #3's summary; deliveries grid is the source of truth; verdict line is the focus target after Run; one `alert` only for agent disconnect; F1 open by default doesn't take focus. Provenance line is plain text after the From line. Codes follow §0.5 rule 2.

---

## S-12 · Run dialogs

All are modal dialogs (focus trapped, Esc cancels, focus returns to the invoker).

### S-12 `share`

Rewritten for PO-A (separate, rotating share token), PV-7 and PV-14. The run page `/r/{runId}` never becomes public; sharing creates a link `/s/{shareToken}`.

| Element | Copy (**NEW** unless noted) | Shown when |
| ------- | --------------------------- | ---------- |
| Title | "Share Run #3" | always |
| Shared as (first line, A05: `sharedBy` shown first) | "Shared as Maya Lindqvist" (guest: "Shared as Guest") | always |
| Visibility (radio group) | **Only people in this project** (default) · helper "The run page needs a sign-in." / **Anyone with the link** | always |
| What the link shows (under the radio, when **Anyone with the link** is selected) | "People with the link see probe names and counts." (PV-14, exact) + "Other values show as codes, and signing secrets never leave your machine." | link on |
| Hide bodies (switch, off) | "Hide request and response bodies" · helper "Fields your provider pack marks as personal are always hidden." | link on |
| Include probe values (checkbox, off) | "Include probe values" · helper "Also shows the values you marked show: in webhooktrap.yml: subscriptions.status." (lists every opted-in entry by name) | link on, **account owner only**, and only when the run has an entry shown under `show:`. Never for guests (PV-7). Run #3 has none, so its frame has no tick; draw the tick on the states sheet |
| Link | `https://webhooktrap.dev/s/q7Vf2LmX9pK4wT8nR3bZcD` + **Copy link** → `ui.confirm` "Share link copied. Anyone with the link can see this run." | link on |
| New link | text button **Create a new link** · helper "The current link stops working at once." → link becomes `…/s/Hc3mW8tZ1qN5rB7xK2vYpL`, toast "New link ready. The old link now shows Not found." | link on |
| Guest note | "This link stops working when your guest session ends or when you sign in." (claim voids guest share links) | guest only |
| Turning sharing off | **Stop sharing** → "The link now shows Not found." Sharing again later creates a new link | link on |

States to draw: private (default, member run); link on (member run, no tick); link on with the Include probe values tick (states sheet, entry `subscriptions.status`); link on, guest (no tick, guest note); after Create a new link. The guest note never appears on a member run (QA m15).

### S-12 `fixtures` (Save as fixtures preview)

Files the agent will write (RD `run.fixtures.files`): `webhooktrap/fixtures/stripe/invoice.paid.json` · `payment_intent.succeeded.json` · `customer.subscription.updated.json` · `webhooktrap.yml`. Scrub list before → after (before values shown only because they are read through the agent, SCR-6): `customer_email → customer1@example.test` · `customer_name → Test Customer 1` · `hosted_invoice_url → https://invoice.stripe.test/redacted`. Note "Fixtures are re-signed when they run, so signatures aren't stored." (**NEW**, SCR-5). Button **Write 4 files** (**NEW**). States: writing, written (toast `ui.confirm` "Fixtures written: 3 files and webhooktrap.yml."), blocked by a residual match: "Can't save: 1 value still looks personal. data.object.customer_phone matches a phone number. Add it to scrub rules, or keep it with scrub: keep in webhooktrap.yml." (**NEW**, SCR-4), no agent (disabled with reason).

### S-12 `side-effects`

Title `copy.run.side_effects.label` "This run repeats events"; body `copy.run.side_effects.helper`; buttons **Run anyway** (primary) · **Cancel** · link "How to honour Webhooktrap-Dry-Run". Shown once per (agent, target) (NAME-4).

### S-12 `abort`

"Stop this run? Completed deliveries and findings are kept." (**NEW**) · **Stop run** · **Keep running**.

---

## S-13 · Shared run (read-only)

**URL:** `/s/{shareToken}`, `/s/{shareToken}/D{n}`, `/s/{shareToken}/F{n}` (PO-A). Not indexed, no referrer. **Shell:** share view (Mark · "Shared run" · Sign in).
**Content:** S-11 finished layout with: header "Run #3 · 3 findings" + "Shared by Maya Lindqvist" (`copy.errors.read_only`); the **provenance line** as on S-11 ("Run by maya-mbp from the web app"); no Run again, Save, Share, Abort; `⌘↵`, `Shift X`, `s` inert; **Sign in to run it again** link. Bodies: fields matched by scrub rules show `[scrubbed]`; with Hide bodies on, "Body hidden by the owner." (**NEW**) in place of every body. Headers per the S-11 share-view rule. Probe values per §0.5 share columns: counts and codes; shown values only when the owner ticked Include probe values. Not on this page: capture URL, agent secret names, agent target list, non-loopback target host (shown `private host`).
**While the run is still running:** the page polls every 5 s; findings appear by severity and title without numbers (FND-6a).
**States:** shared (default); not shared, unknown, rotated or deleted token → S-18 `not found` (IA5); guest expired or guest claimed → S-18 `not found` + "Guest runs are kept for 24 hours" (`copy.errors.not_found_run`); Include probe values on (states sheet, code-valued reading shows `past_due`).
**390:** report-first, as S-11. Keys as S-11 minus the inert ones.

---

## S-14 · Example run

**URL:** `/r/example`. Same as S-13 with Run #3's data (counts only, synthetic readings, A09 §2.4), the provenance line "Run by maya-mbp from the web app", label "Example run" instead of "Shared by…", helper `copy.errors.example`, and CTA **Get a URL** (anonymous) / **Prove your own** (guest, → Capture). No Sign in prompt inside the content. Not counted anywhere, never modified.

---

## S-15 · Projects and New project

`/projects`: shown only with 2+ projects (else redirect to the one). Rows: name · capture URL · deliveries · last activity · last run status. **New project**.
`/projects/new`: field "Project name" (default "My project"), **Create** → S-03 `waiting`. Errors next to the field ("Enter a name." **NEW**). 390: single column.

---

## S-16 · Project settings

**URL:** `/p/{projectKey}/settings/{capture|ci|budgets|danger}`. Left section list (desktop), list page then pages (mobile).

| Page | Content | States and copy |
| ---- | ------- | --------------- |
| Capture URL | URL + copy · **Rotate URL…** (confirm: "Rotate the capture URL? Providers that use the old URL stop reaching this project. Update them first." **NEW**) · Retention select (30 days) | rotated toast "New capture URL ready. The old one now answers like an unknown URL." (**NEW**) |
| CI tokens | table Name · Created · Last used: `github-actions` (name only, A01 RUN-8) · **New token** (shows the value once: "Copy it now. It can't be shown again." **NEW**; plus "Set it as WEBHOOKTRAP_TOKEN in your CI. Uploaded runs are private; add --share for a link." **NEW**, PO-C) · Revoke (confirm) | empty: "No CI tokens. Create one to upload runs from CI." (**NEW**) |
| Budgets | read-only table from RD `providers`: provider · budget label · source · tolerance; a `budget:` override from `webhooktrap.yml` shows as "{n} ms · webhooktrap.yml" when the agent reports it (BUD-2); Twilio, Slack listed as "Coming", no budget | — |
| Danger | **Delete project** (confirm names the project, typed name not required) | — |
| Guest | only **End session now**: "End this session? Every delivery, run and share link is deleted now." → landing with `copy.guest.ended` | — |

Keys: none. Forms per A11Y §9.

---

## S-17 · Account

**URL:** `/account/{security|tokens|preferences|delete}`. **Shell:** signed-in shell without section tabs; "‹ Back to Brindle Coffee"; left list (desktop 1440, 200 wide): Security · Personal tokens · Preferences · Delete account. Mobile 390: the list is a page, each item opens its page with "‹ Account". **Rev. 3:** "Sign-in methods" is replaced by **Security** (S-17A), which holds sign-in methods, two-step sign-in and recovery codes (contracts §3.4 `SecurityView`).

| Page | Content |
| ---- | ------- |
| Security | S-17A |
| Personal tokens | for CLI and MCP: name, created, last used (name only, as CI tokens); New token (shown once); Revoke. Ticking **run** sends the person through S-28 first (recent authentication, domain §2.1). During a recovery and 7 days after (RC-3), **run** is disabled with the S-17A lock line for its `lock.reason` (rev. 4, below) |
| Preferences | **Single-key shortcuts** (switch, on) · **Announce live updates**: All / Results only / Off · **Reduce motion**: System / On / Off (A11Y §5.1, §6.3, §7) |
| Delete account | **S-17 `delete`** below (rev. 4, U-19; rev. 5, DL-4) |

### S-17 `delete` · Delete account (rev. 4, U-19; rev. 5, DL-4)

**URL:** `/account/delete` (A05 revision 6: `GET /account/delete`, `DELETE /account`; deletion is immediate, PO-L). Data: RD `account_security.account_deletion`. **Flow:** FL-26. **Rev. 5 (DL-4, decision 47):** an account **without two-step sign-in** deletes only after a link sent to the account address is opened and its button pressed; an account **with** two-step sign-in keeps the fresh confirmation (S-28 in second-factor mode) and deletes at once, with no email step.

| Region | Content (**NEW**) |
| ------ | ------- |
| Heading | `h1` "Delete account" |
| What goes | "This deletes your account and everything in it, now:" then a list built from the data: "2 projects (Brindle Coffee, Stripe project), with their captures, runs, fixtures and share links" · "4 runs" · "1 agent (maya-mbp). It's signed out and can't run anything until it's paired again" · "2 CI tokens and 1 personal token" |
| What else | "Capture URLs stop working: providers that send to them get the same answer as an unknown URL. Share links stop working. We email maya@brindlecoffee.example when it's done." |
| Confirm | Checkbox "I understand this can't be undone." · **Delete account** (destructive; `aria-disabled` until ticked, reason adjacent: "Tick the box to continue.") |

| State | What changes | Copy (**NEW**) |
| ----- | ------------ | ---- |
| Default | as above | — |
| Recent authentication needed | **Delete account** goes to S-28 ("delete your account"), back here with the box still ticked. On a 2FA account S-28 runs in second-factor mode (A05 U-20; line under the intro: "Deleting your account can't be undone, so confirm with a passkey, security key or authenticator code.") | — |
| Deleting (2FA account) | Button "Deleting your account…", disabled | — |
| **Sending the link** (no 2FA, rev. 5) | After S-28, the page posts `DELETE /account`; button "Sending…", disabled | — |
| **Check your email** (no 2FA, rev. 5, DL-4) | Page body replaced; nothing is deleted yet. `h1` stays "Delete account". Heading `h2` "Check your email"; the address is the account's own, so it is shown in full | "We sent a link to maya@brindlecoffee.example. Your account is deleted when you open it and press Delete my account. The link works for 60 minutes and once. Until then, nothing changes." · **Back to Security** (→ S-17A) · link "Start over" (returns to the form; a new request replaces the link, **[pending A05: confirm that a new request replaces the earlier link]**). Reloading the page within the hour shows this state again **[pending A05: needs a pending-deletion prop on `AccountDeletionView`; without it the form shows again and a new request replaces the link]** |
| **Link expired** (no 2FA) | Form again; no extra copy (the link page says why, S-32) | — |
| Done | Signed out everywhere, landing (S-01) with a ruled flash. On a no-2FA account this happens after the link page's button (S-32 Delete my account), not here | "Your account was deleted. We sent a confirmation to maya@brindlecoffee.example." |
| Recovery open or locked (RC-3, `recovery_lock`) | Refused (A05 revision 6): page body replaced by the lock line, no checkbox | Open: "You can't delete your account while account recovery is open." Completed: "You can't delete your account until 21 Nov 2026, 09:20 UTC, 7 days after the account recovery." |
| Last administrator (self-host, `last_instance_admin`) | Body replaced, no checkbox | "You're this instance's only administrator. Make someone else an administrator first." + link "Users" (S-21) |
| Pending email change | Deleted with the account; no extra copy | — |
| Error | Ruled banner `copy.errors.server` with Retry | rev. 2 key |

390: same order, full-width button. Keyboard and accessibility: A11Y §9 "Delete account".

---

## S-17A · Account · Security

**Purpose:** see every way into the account at a glance, add or remove one, see where it's signed in and what changed, and keep a way back in. **URL:** `/account/security`. **Ceremony page.** **Data:** RD `account_security.maya` (A05 `SecurityView`, contracts §3.4 revision 5). **Flows:** FL-18 to FL-21, FL-23, FL-24, FL-25.

### Layout (desktop 1440: content column 760 right of the account list; mobile 390: one column, 16 gutters)

`h1` "Security". Under it, one summary line in `ink-muted` built from the data: "Two-step sign-in is on. You can sign in with a password, GitHub and 1 passkey." (**NEW**; off: "Two-step sign-in is off. You can sign in with a password and GitHub."). Then **seven** sections (rev. 4: 6 and 7 are new), each an `h2` over a hairline-ruled list (Timetable: rules, no cards).

| # | Section (`h2`) | Rows | Actions |
| - | -------------- | ---- | ------- |
| 1 | **Password and email** (rev. 4: was "Password") | "Password · Set" or "No password. You sign in with GitHub or a passkey." · Email row: `maya@brindlecoffee.example` · "Verified". Pending change (`pendingEmail`): second line in `ink` "Changing to m***@b***.example. We sent a link there; it works until 14:56 UTC." (**NEW**). **Account without two-step sign-in (rev. 5, EC-1)**, three lines instead, same position, each ending "Links work until 14:56 UTC.": none confirmed → "Changing to m***@b***.example. **Waiting for both addresses:** the new one and maya@brindlecoffee.example." · new address done → "Changing to m***@b***.example. **Waiting for your current address**, maya@brindlecoffee.example. The new address is confirmed." · current address done → "Changing to m***@b***.example. **Waiting for the new address.** Your current address is confirmed." The "done" word is plain text with a `✓` glyph, never colour alone (A11Y) | **Change password** / **Set a password** (S-17B `password`) · **Change email** (S-17B `email`) · pending only: **Cancel change** (no dialog: toast "Email change cancelled. The link no longer works." **NEW**) |
| 2 | **Passkeys and security keys** | Table: Name · Type · Sync · Added · Last used · actions. Rows from RD: "MacBook Touch ID · Passkey · Synced · 9 Nov 2026 · Today, 13:52 UTC" and "YubiKey 5C NFC · Security key · This device only · 10 Nov 2026 · 10 Nov 2026". Under the table in `ink-muted`: "Passkeys sign you in without a password and count as two-step sign-in. Security keys are a second step after your password, GitHub or Google." | Row: **Rename** · **Remove** (S-17B). Section: **Add a passkey** · **Add a security key** (S-17B `add`) |
| 3 | **Authenticator app** | "On since 9 Nov 2026" or "Off" | **Set up** (S-17B `totp`) / **Turn off** |
| 4 | **Recovery codes** | "8 of 10 left. Each code works once, if you lose your passkeys, keys and phone." · empty until a first second factor exists: "You get recovery codes when you turn on two-step sign-in." | **Generate new codes** (S-17B `codes`) |
| 5 | **Linked accounts** | "GitHub · maya-l · linked 2 Nov 2026" · "Google · Not linked" | **Disconnect** / **Connect** (S-17B `linked`; both after S-28) |
| 6 | **Where you're signed in** (rev. 4, U-10) | Table: Browser · Network · Signed in · Last seen · How · actions, from `sessions` (RD `account_security.maya.sessions`). **This browser first**, then others by last seen. Row 1: "Safari 18 on macOS" + the word **This browser** (semibold `ink`) · `198.51.100.0/24` · "11 Nov 2026, 13:52 UTC" · "Active now" · "Passkey". Row 2: "Firefox 133 on Windows" · `203.0.113.0/24` · "10 Nov 2026, 08:52 UTC" · "10 Nov 2026, 17:31 UTC" · "Password, then authenticator app". Row 3: "Safari 18 on iPhone" · `2001:db8:4a1c::/48` · "4 Nov 2026, 19:05 UTC" · "9 Nov 2026, 07:40 UTC" · "GitHub". Networks in Martian Mono. Under the table in `ink-muted`: "Networks are shown approximately. Personal tokens, CI tokens and agents aren't listed here: they have their own pages." (**NEW**) | Rows 2 and 3: **Sign out** (S-17B `signout`). This browser: no button; its actions cell reads "Use Sign out in the account menu" in `ink-muted`. Section: **Sign out all other browsers** (S-17B `signout`) |
| 7 | **Recent security activity** (rev. 4, U-18) | Table: When · What · Browser · Network, from `securityLog` (RD `account_security.maya.security_log`, 8 rows, newest first: "11 Nov 2026, 13:52 UTC · Signed in with a passkey (MacBook Touch ID) · Safari 18 on macOS · 198.51.100.0/24" …). Under it: "Showing the last 30 days." (**NEW**). No paging (≤ 50 rows, A05). "What" copy per action code: RD `SECURITY_LOG_COPY` (generator), codes **[pending A05 exact codes]** | None (read-only) |

Two-step status is not a switch: it is on when a second factor exists (domain §2.1). Type and Sync are words, never only icons. Sync values: "Synced" (BS = 1), "This device only" (BS = 0), "—" when unknown (`synced: null`). "How" values (RD `method_labels`): "{first}, then {second}" ("Password, then security key", "GitHub, then authenticator app", "Google, then recovery code"); a passkey sign-in is "Passkey" alone (it is both steps); `reset_link` "Password reset link"; `recovery_finish` "Account recovery".

**Review link** (`recoveryReview` set): a ruled note above section 1: "Your account was recovered on 14 Nov 2026, 09:20 UTC. Check what changed:" + link **Review your account** (S-30) + "Available until 21 Nov 2026, 09:20 UTC." (**NEW**, U-17).

### States (all drawn)

| State | What changes | Copy (**NEW**) |
| ----- | ------------ | ---- |
| Loading | Section headings real, rows as 1-line skeleton rules, ≤ 300 ms | — |
| No second factor | Summary "Two-step sign-in is off…"; section 3 "Off"; section 4 empty line; a ruled note above section 2: "Add a passkey, a security key or an authenticator app to turn on two-step sign-in." | as written |
| No keys | Section 2 table replaced by "No passkeys or security keys yet." | as written |
| Key never used | Last used "Never used" (RD `Pixel 8`) | — |
| **Key blocked**, counter (WA-1) | Row shows the word **Blocked** in semibold after the name, a second line in `ink`: "Blocked on {date}: its use counter went backwards, which can mean the key was copied. It can't be used until you review it." Actions on that row: **Review** (S-17B `blocked`) · Remove. No colour beyond `ink`: severity colours stay reserved for findings | RD `Old YubiKey.flag_note` |
| **Key blocked**, user handle (rev. 4, U-11, `flagCause = 'user_handle_mismatch'`) | As above with the second line from RD `Old YubiKey.flag_note_user_handle` + "It can't be used until you review it." | "Blocked on 11 Nov 2026, 11:44 UTC: it sent sign-in details that don't belong to your account, which can mean it was tampered with." |
| **Key blocked twice** (rev. 4, `canUnblock = false`, RD `Spare YubiKey`) | **Blocked** + its line + a third line: "This key was blocked twice. Remove it and add it again." Actions: **Review** · Remove (the dialog has no Unblock) | A05 exact |
| Limit reached (10 credentials) | Both Add buttons disabled; reason next to them (`aria-describedby`). Blocked keys count (A05) | "You have 10 passkeys and security keys, the most an account can hold. Remove one to add another." |
| Browser without WebAuthn | Both Add buttons disabled with reason; existing rows and Remove still work | "This browser can't create passkeys or use security keys. Try a current Chrome, Edge, Firefox or Safari." |
| Instance can't use WebAuthn (U-6, self-host on HTTP or an IP) | Section 2 replaced by its heading and one paragraph; nothing else | "Passkeys and security keys need this instance to run on HTTPS with a domain name, such as hooks.ferrymarket.example. Ask your administrator. The authenticator app works without it." |
| Few recovery codes (≤ 2 left) | Ruled note in section 4 | "Only {n} recovery codes left. Generate a new set and store it somewhere safe." |
| Recovery codes all used (0) | Same note | "No recovery codes left. Generate a new set now." |
| **Email change pending** (rev. 4, U-13) | Section 1 second line and **Cancel change** (layout above) | as above |
| **Email change pending, both addresses** (rev. 5, EC-1; account without two-step sign-in) | Three pending lines of section 1 (none, new done, current done); **Cancel change** drops both links and any half-confirmed state. RD `pending_email.new_confirmed`, `old_confirmed` | as above |
| **Recovery open** (RC-3, `lock.reason = 'recovery_open'`) | S-00 banner; **Change email** disabled with reason; **Disconnect** disabled with reason on identities linked before the request (A05 AR-1); **Connect** stays enabled (U-12) | "You can't change your email or disconnect GitHub while account recovery is open." |
| **Recovery completed, locked** (`lock.reason = 'recovery_completed'`) | Same controls; the review note shows | "You can't change your email or disconnect GitHub until 21 Nov 2026, 09:20 UTC, 7 days after the account recovery." (rev. 3 wording with RD `lock_until_full_label`) |
| **Only this browser** (rev. 4) | Section 6 table has the current row only; **Sign out all other browsers** hidden; line under the table | "You're not signed in anywhere else." |
| **No recent activity** (rev. 4) | Section 7 table replaced by one line | "Nothing in the last 30 days." (RD `security_log_empty`) |
| Saved (after any dialog) | Toast; the changed row gets a 2 s `paper-2` rule highlight (none under reduced motion) | per S-17B |
| Error loading | Ruled banner `copy.errors.server` with Retry | rev. 2 key |

### Responsive

390: sections stack; the key table becomes a list: line 1 name (+ **Blocked**), line 2 "Passkey · Synced", line 3 "Added 9 Nov 2026 · Last used today, 13:52 UTC", a `⋯` menu button (44 × 44, label "Actions for MacBook Touch ID") with Rename / Remove. Add buttons full width, stacked. Rev. 4: the sessions table becomes a list: line 1 browser (+ **This browser**), line 2 network in Martian Mono, line 3 "Signed in 10 Nov 2026, 08:52 UTC · Last seen 10 Nov 2026, 17:31 UTC", line 4 "How: Password, then authenticator app", **Sign out** full width under it (44 tall). The activity table becomes a list: line 1 What, line 2 When · Browser · Network in `ink-muted`.

### Keyboard and accessibility

No L2 keys (A11Y §5.5). The key, sessions and activity tables are real `table`s with column headers; Actions are buttons whose names include the object ("Remove YubiKey 5C NFC", "Sign out Firefox 133 on Windows"). After a dialog closes, focus returns to the control that opened it, or, after a removal or sign-out, to the section heading. See A11Y §9 "Security".

---

## S-17B · Security dialogs

All are modal dialogs over S-17A (desktop 520 wide; mobile 390 full-screen sheet with a "Close" button top left). Every action here needs **recent authentication** (10 min, domain §2.1), except Rename and Cancel change: if it is older, the action first goes to S-28 and comes back to the same dialog with the person's choices kept (A11Y G13). Each completed change sends an email (S-31) and shows a toast; **signing out browsers sends no email** (A05 event-catalogue `SessionRevoked`).

### `add` · Add a passkey / Add a security key

| Step | Content (**NEW**) |
| ---- | ------- |
| 1 Explain | Title "Add a passkey" / "Add a security key". Passkey body: "Your browser or phone will ask you to confirm with your fingerprint, face, PIN or screen lock. The passkey is saved on that device or in your password manager." Security key body: "Insert your key or hold it near your phone, then touch it when it blinks." **Continue** · Cancel |
| 2 Waiting | "Follow the steps from your browser." Continue becomes "Waiting…" (disabled). Cancel stays |
| 3 Name | Field "Name" prefilled from the AAGUID default or "Passkey 11 Nov 2026" / "Security key 11 Nov 2026"; helper "1 to 48 characters. Only you see it, and it appears in emails about this key." **Save** |
| 4a Done | Toast "Passkey added: MacBook Touch ID." / "Security key added: YubiKey 5C NFC." |
| 4b Done, first second factor | Dialog moves to the `codes` show-once step (recovery codes come back once, A05 §3.4) with the title "Two-step sign-in is on. Save your recovery codes." |

| Error state | Copy (**NEW**) |
| ----------- | ---- |
| Cancelled or timed out (5 min) | "Nothing was added. You can try again." + **Try again** |
| Already registered (`excludeCredentials` match) | "This passkey or key is already on your account." |
| Not allowed by the browser (no authenticator, wrong origin) | "Your browser couldn't create a passkey here. Try another browser or device." |
| Name invalid (empty, > 48, control or bidi characters) | Next to the field: "Use 1 to 48 letters, numbers, spaces or punctuation." |
| Limit reached meanwhile | "You already have 10 passkeys and security keys. Remove one first." |

### `rename`

Field "Name" (current value selected) · **Save** · Cancel. Same field error. No recent authentication needed (A05: `PATCH` without sudo). Toast "Renamed to {name}."

### `remove`

| Case | Dialog (**NEW**) |
| ---- | ------ |
| Normal | Title "Remove {name}?" · "You can't use it to sign in to Webhook Trap any more. We'll email you about it." **Remove** (destructive) · Cancel → toast "{name} removed." |
| Last second factor | Title "Remove {name} and turn off two-step sign-in?" · "It's your last second step. Removing it turns off two-step sign-in and deletes your recovery codes." **Remove and turn off** · Cancel |
| Last way to sign in (`422 last_sign_in_method`) | No destructive button. Title "You can't remove your only way to sign in" · "Add a password, a passkey or a linked account first." **Add a passkey** · Close |

### `blocked` · Review a blocked key (rev. 4: route exists, `POST /account/security/keys/{keyId}/unblock`, U-11)

Title "Review {name}". Body by `flagCause` (**NEW**):

| Cause | Body |
| ----- | ---- |
| `counter` (RD `Old YubiKey`) | "On 11 Nov 2026, 11:44 UTC this key was refused because its use counter went backwards. That can happen when a key is copied. If you still have the key and nobody else could have used it, you can unblock it. If you're not sure, remove it and add it again." |
| `user_handle_mismatch` | "On 11 Nov 2026, 11:44 UTC this key was refused because it sent sign-in details that don't belong to your account. That can happen when a key is tampered with. Remove it and add it again unless you're sure what happened." |

Buttons: **Remove key** (primary, recommended; goes to `remove`) · **Unblock** (secondary) · Cancel. Under **Unblock**, in `ink-muted`: "You'll confirm it's you with another passkey, key, authenticator code or your password first. If this key is blocked again, it can't be unblocked." (**NEW**)

| State | What happens | Copy (**NEW**) |
| ----- | ------------ | ---- |
| Unblock | S-28 ("unblock a key"); S-28 never offers the blocked key (a flagged credential never counts, domain §2.1). Back here, the request runs | Toast "{name} unblocked. If it's blocked again, you'll need to remove it." Email `key-unblocked` |
| **Blocked twice** (`canUnblock = false`, RD `Spare YubiKey`) | **Unblock** and its helper are not rendered; a line after the body; buttons **Remove key** · Cancel | "This key was blocked twice. Remove it and add it again." (A05 exact) |
| `422 unblock_not_allowed` (flagged again meanwhile) | Unblock removed, line shown in the dialog (`role="alert"`) | "This key can't be unblocked any more. Remove it and add it again." |

### `totp` · Set up an authenticator app

| Step | Content (**NEW**) |
| ---- | ------- |
| 1 Scan | "Scan this code with an authenticator app, such as 1Password, Google Authenticator or Authy." QR (200 × 200, `img` with alt "QR code for your authenticator app. Use the setup key below if you can't scan it.") · "Can't scan? Enter this setup key:" `JJUK 4KVX 5LV4 MKW2 YCBR FQQ6 4NEG T2UW` in Martian Mono with **Copy** |
| 2 Confirm | Field "6-digit code from the app" (`inputmode="numeric"`, `autocomplete="one-time-code"`, one field, spaces ignored) · **Turn on** |
| Done | Toast "Authenticator app is on." First second factor → `codes` show-once step |

Errors: "That code didn't match. Check the time on your phone and enter the newest code." · "That code was already used. Wait for the next one." · Secret shown once: closing before step 2 discards it ("Nothing was saved.").

**Turn off** (when on; after S-28 "turn off the authenticator app") (rev. 4: exact copy, replaces the values A04 had to supply):

| Case | Dialog (**NEW**) |
| ---- | ------ |
| Normal (another second factor remains) | Title "Turn off the authenticator app?" · "You can set it up again in Security. We'll email you about it." **Turn off** · Cancel → toast "Authenticator app turned off." Email `totp-off` |
| Last second factor | Title "Turn off the authenticator app and two-step sign-in?" · "It's your last second step. Turning it off also turns off two-step sign-in and deletes your recovery codes." **Turn off two-step sign-in** (destructive) · Cancel → toast "Two-step sign-in is off." Email `2fa-off` (not `totp-off` as well) |

### `codes` · Recovery codes

| Step | Content (**NEW**) |
| ---- | ------- |
| Show once | Title "Your recovery codes" (or the 4b title). "Each code works once. Keep them somewhere other than your phone or password manager, such as a printout. They're shown only now." 10 codes in a 2 × 5 grid, Martian Mono 15, from RD (`x7vc2-y4bnq` …) · **Download** (`webhooktrap-recovery-codes.txt`) · **Copy** · **Print** · checkbox "I've saved these codes" · **Done** (enabled once ticked) |
| Regenerate | Confirm first: "Generate new recovery codes? Your 8 unused codes stop working now." **Generate** · Cancel → Show once |

Closing the show-once step without ticking asks "Close without saving the codes? You can generate new ones later, but these won't be shown again." **Close anyway** · Go back.

### `password` · Set or change password (rev. 4, U-15: values confirmed by A05)

Fields "New password" (`autocomplete="new-password"`, Show toggle) · "Confirm new password". No "current password" field: S-28 has just proved the person (A05 `currentPassword?` is optional; **[A05 to confirm it is never required after recent authentication]**). Helper "12 to 128 characters. A long phrase is easiest to remember." On an image that falls back to bcrypt (A05 domain §2.1) the helper is "At least 12 characters and at most 72 bytes. Accented letters and symbols take more than one byte." **[pending A05 prop: the page needs to know the limit; see contract needs]**. **Save**. Toast "Password changed. Other browsers were signed out." (set: "Password set. Other browsers were signed out."; A05: a password change revokes every other session, confirmed). Email `password-changed`.

| Error (`details.fields.password`) | Copy next to the field (**NEW**) |
| ----- | ---- |
| `password_too_short` | "Use at least 12 characters." |
| `password_too_long` | "Use 128 characters or fewer." (bcrypt: "Use 72 bytes or fewer. Accented letters and symbols count as more than one.") |
| `password_common` | "This password is too common. Choose another." (A05 wording; replaces the rev. 3 breach message) |
| `password_is_email` | "Don't use your email address as your password." |
| confirm differs (client) | "The passwords don't match." |

### `linked` · Connect or disconnect GitHub / Google (rev. 4: routes exist, U-12)

| Action | Content (**NEW**) |
| ------ | ------- |
| Connect | S-28 ("connect Google") → the provider → back to S-17A with toast "Google connected as {label}." Email `method-linked`. The provider's email may differ from the account email; nothing is said about it (A05 §2.5). **Not blocked by the recovery lock** |
| Connect: cancelled at the provider | Toast "Nothing changed." |
| Connect: `422 identity_in_use` | Ruled banner on S-17A (`role="alert"`): "That Google account is already linked to another Webhook Trap account." (GitHub: same with GitHub) |
| Disconnect | S-28 ("disconnect GitHub") → dialog: title "Disconnect GitHub?" · "You won't be able to sign in with GitHub any more. Your projects and runs stay. Browsers already signed in with GitHub stay signed in; you can sign them out under Where you're signed in." **Disconnect** · Cancel → toast "GitHub disconnected." Email `method-unlinked` |
| Disconnect: last way to sign in (`422 last_sign_in_method`) | As `remove` "You can't remove your only way to sign in" |
| Disconnect: recovery lock (`422 recovery_lock`) | Button disabled on S-17A with the lock line (S-17A states). An identity connected **after** the recovery request can still be disconnected (A05 AR-1) |

### `email` · Change email (rev. 4, U-13; `POST /account/email`, `DELETE /account/email`)

Opened from **Change email** (S-17A section 1). Needs S-28 first ("change your email"); on an account with two-step sign-in, S-28 runs in **second-factor mode** (no password, S-28). **Rev. 5 (EC-1, decision 47):** the dialog has two variants. **2FA account:** only the new address confirms, as below. **Account without two-step sign-in:** the new **and** the old address each confirm, so steps 1 and 2 change (rows marked *no 2FA*); every other row is shared. No "This wasn't me" or revert link in either variant.

| Step / state | Content (**NEW**) |
| ------------ | ------- |
| 1 Form | Title "Change your email" · line "Now: maya@brindlecoffee.example" · field "New email" (`type=email`, `autocomplete="email"`) · helper "We'll send a link to the new address. Open it within 60 minutes in a browser where you're signed in to this account." · **Send link** · Cancel. With a pending change, one more line above the field: "This replaces your pending change to m***@b***.example." |
| 1 Form, *no 2FA* | Same as 1, helper replaced: "We'll send a link to the new address and one to your current address. Open both within 60 minutes in a browser where you're signed in to this account. Your email changes after both are confirmed." |
| 2 Sent, *no 2FA* (**uniform**) | Title "Check both inboxes" · "We sent a link to maya@brindleroasters.example and one to maya@brindlecoffee.example. Your email changes when you've opened both and pressed the button on each. Nothing changes until then." · **Done**. S-17A then shows "Waiting for both addresses". Same answer whether the new address is free or taken **[pending A05: whether the old address still gets its link when the new address is taken; A03 asks for yes, so the answer stays uniform]** |
| 2 Sent, 2FA account (**uniform**: the address is free or already has an account) | Title "Check the new inbox" · "We sent a link to maya@brindleroasters.example. Your email changes when you open it and press Confirm new email. We've also told maya@brindlecoffee.example about this." · **Done**. S-17A then shows the pending line |
| Sending | **Send link** "Sending…", disabled |
| Field: invalid | "Enter an email address, such as maya@brindlecoffee.example." |
| Field: same as now (client check against `SecurityView.email`) | "That's already your email." |
| `422 second_factor_required` (recent authentication was password-only on a 2FA account) | Body replaced: "To change your email, confirm it's you with a passkey, security key or authenticator code. Your password alone isn't enough, because your email is how you'd recover this account." **Confirm it's you** (→ S-28 second-factor mode, back to step 1 with the address kept) · Cancel |
| `422 recovery_lock` | Body replaced by the S-17A lock line for `lock.reason`; **Close** only (normally unreachable: the button is disabled) |

What the emails say: `email-change-verify` or `email-change-taken` to the new address; to the old one at once, `email-change-requested` (2FA account: a notice, no link) or `email-change-confirm-old` (no 2FA: a link) (S-31). The links open S-32. **Cancel change** on S-17A, a newer request, a recovery or 60 minutes drops both links together.

### `signout` · Sign out a browser / all other browsers (rev. 4, U-10; `DELETE /account/sessions/{id}`, `DELETE /account/sessions`)

Both need S-28 first ("sign out a browser" / "sign out other browsers"), so a stolen session can't evict the owner without a factor (A05 §2.7).

| Case | Dialog (**NEW**) |
| ---- | ------ |
| One browser | Title "Sign out Firefox 133 on Windows?" · "That browser is signed out the next time it loads a page. Nothing else changes." **Sign out** · Cancel → toast "Signed out Firefox 133 on Windows." The row leaves the table |
| All other browsers | Title "Sign out 2 other browsers?" · "Every browser except this one is signed out the next time it loads a page. If you think someone else signed in, change your password too." **Sign out other browsers** · Cancel → toast "Signed out 2 other browsers." Table shows the "Only this browser" state |
| Already signed out (the session ended since the page loaded) | No error dialog: the list reloads → toast "That browser was already signed out." **[A05: answer for an unknown or already revoked session id]** |

No email is sent in any case (A05).

---

## S-18 · System pages

Bare shell, focus to `h1`, one primary action.

| Page | `h1` | Body | Action | Copy keys |
| ---- | ---- | ---- | ------ | --------- |
| Expired guest URL | "This guest URL expired" | `copy.capture.expired.helper` | **Get a new URL** · Sign in | `copy.capture.expired` |
| Not found | "Not found" | "This page doesn't exist or isn't shared with you." (**NEW**); runs and share links: `copy.errors.not_found_run`. Same page for an unknown run id, someone else's `/r/{runId}`, and a rotated, stopped or unknown `/s/{token}` | Sign in (anonymous) / Go home | — |
| Delivery not found | `copy.errors.not_found.label` | `copy.errors.not_found.helper` | Back to the stream | — |
| Server error | `copy.errors.server.label` | helper with ref `7F3K-21QX` | Retry | — |
| Offline | banner, not a page | `copy.errors.offline` | — | — |
| Session ended | "Session ended" | "Nothing was kept." | **Get a URL** | `copy.guest.ended` |

Nothing from an expired or forbidden resource is in the HTML or props (R59, IA5).

---

## S-19 to S-23 · Self-host admin (P8)

**URL:** `/admin/…`; 404 on hosted and for non-admins. **Shell:** signed-in shell without section tabs; "‹ Back to Brindle Coffee"; breadcrumb `Instance › {page}`. Left list: Overview · Access · Users · Limits · Server replay, then "Later: Secrets · Agents · Packs · Audit" as plain text (not links).

| ID | Page | Fields and content | Copy (**NEW**) |
| -- | ---- | ------------------ | ---- |
| S-19 | Overview | Version · health rows (Database, Queue, Broadcast: "OK" / "Down since {time}") · storage used · checklist (sign-up policy, guest mode, server replay off, limits) · **Passkeys** row (rev. 3, U-6): "Relying party ID `hooks.ferrymarket.example`" with the note "Passkeys and security keys are tied to this name. If you change the instance's address, every passkey and security key stops working and people sign in another way." When WebAuthn can't run (plain HTTP other than `localhost`, or an IP such as `http://192.168.1.40:8080`): "Off: passkeys and security keys need HTTPS and a domain name." | checklist item done: "Done"; health down: "Down since 14:02. Runs and live updates may be delayed." |
| S-20 | Access | Sign-up policy radio: Open / Invite only / Closed · Guest mode switch · guest limits (defaults 24 h, 100 deliveries, 10 runs; read-only note "Values from the product defaults.") | saved: "Saved. New sign-ups need an invite." |
| S-21 | Users | table Name · Email · Role · Status · Last seen; actions Make admin / Disable (confirm naming the user) | disable confirm: "Disable {name}? They can't sign in until you enable them again. Their data is kept." |
| S-22 | Limits | Body size limit (MB) · Retention (days) · Delivery cap per run (1–50) | cap error: "The cap can't be above 50 deliveries per run." |
| S-23 | Server replay | Public destinations Off/On · Private destinations **Off by default**/On · allow-list (hosts or CIDRs, e.g. `10.0.4.0/24`) · **Users allowed to send to private destinations** (per-user permission, PO-G; none by default; grants and revocations are audited) · warning | warning: "The server will send to these addresses on behalf of any user of this instance." Turn-on confirm: **Turn on** / Cancel |

States per page: loading skeleton, saved (inline summary), field errors, server error. 390: section list then page. Keys: none; forms per A11Y §9.

---

## S-24 · Create account

**Purpose:** an account in one screen, without saying whether an email is taken. **URL:** `/register?next=`. **Shell:** bare. **Flow:** FL-15. Desktop 1440: centred column, max 440; mobile 390: full width, 16 gutters.

Layout: `h1` "Create your account" · guest note (guest only, as S-02) · **Continue with GitHub** · **Continue with Google** · divider "or use your email" · fields "Name" (`autocomplete="name"`), "Email" (`autocomplete="email"`), "Password" (`autocomplete="new-password"`, Show toggle, helper "12 to 128 characters. A long phrase is easiest to remember." (rev. 4: A05 confirmed 12–128; bcrypt variant as S-17B `password`)) · **Create account** · "Already have an account? Sign in". No passkey button: a passkey is added after the email is verified (domain §2.4 rule, applied to everyone). All copy **NEW**.

| State | Copy (**NEW**) |
| ----- | ---- |
| Creating | Button "Creating your account…", disabled |
| Field errors | "Enter your name." · "Enter an email address, such as maya@brindlecoffee.example." · password errors as S-17B `password` ("Use at least 12 characters." · "Use 128 characters or fewer." · "This password is too common. Choose another." · "Don't use your email address as your password.") |
| Email already has an account | **Uniform:** goes to S-25 `check inbox` exactly as a new account would. The email sent is different (S-31 `account-exists`) |
| Throttled | "Too many attempts. Try again after 14:58 UTC." (as S-02, rev. 4) |
| Invite only (self-host) | Form replaced: "Sign-ups on this instance need an invite. Ask your administrator." With an invite token in the URL: the form shows with the email fixed |
| Closed (self-host) | "Sign-ups on this instance are closed." + Sign in |

Keyboard and accessibility as S-02.

---

## S-25 · Verify email

**URL:** `/verify-email` (check inbox) and the signed link `/verify-email/{id}/{hash}` (rev. 4: a **link page**, S-32 rules: `GET` shows the page, `POST` verifies; A05 domain §2.9). **Shell:** bare. **Flows:** FL-15, FL-22.

| State | `h1` | Body and actions (**NEW**) |
| ----- | ---- | ---- |
| Check inbox | "Check your inbox" | "We sent a link to {email}. Open it on any device to confirm your address. It works for 60 minutes." (A05 confirmed) · **Send it again** (disabled 60 s after each send: "Sent. You can send another in {s} s.") · "Wrong address? Start again" (signs out, back to S-24) |
| **Link page** (rev. 4, U-14) | "Confirm your email" | "Press the button to confirm maya@brindlecoffee.example for your Webhook Trap account." **Confirm email**. Opening the page changes nothing (mail scanners open links) |
| Confirmed, same browser | — | Redirect to `next` or `/projects/new` with flash "Email confirmed." |
| Confirmed, another browser | "Email confirmed" | "You can close this tab and go back to the one where you signed up, or sign in here." **Sign in**. Never claims a guest session (A05) |
| Already confirmed (the link is idempotent, A05) | as the two rows above | — |
| Link doesn't work (expired, unknown; **one page for every cause**, A05 §2.9) | S-32 `doesn't work`, verify variant | rev. 3's separate "expired" and "invalid" pages are merged |

---

## S-26 · Forgot and reset password

**URL:** `/forgot-password`, `/reset-password/{token}?email=`. **Shell:** bare. **Flows:** FL-17 branch, FL-21 (RC-2), FL-22 (1.x). Rev. 4: the reset page is already a link page in S-32's sense: the `GET` shows the form without using the token; **Save password** (`POST`) uses it (A05 contracts §3.4).

| Page / state | Content (**NEW**) |
| ------------ | ------- |
| Forgot | `h1` "Reset your password" · "Enter your account's email. We'll send a link to set a new password." · field "Email" · **Send link** · "Back to sign in" |
| Sent (uniform, account or not) | `h1` "Check your inbox" · "If an account uses {email}, we sent it a link to set a new password. The link works for 60 minutes." · **Back to sign in** |
| Reset form | `h1` "Choose a new password" · email shown as text · fields "New password", "Confirm new password" with helper "12 to 128 characters. A long phrase is easiest to remember." (bcrypt variant as S-17B `password`) · **Save password** |
| **Reset form, imported 1.x account** (`legacy_source = '1x'`, no password; the link proved the email, so naming it is safe) | `h1` "Set a password" · "Welcome back. Webhook Trap 2.0 is a new app: your account came across, your 1.x inboxes and events didn't." **[PO approves text]** · same fields · **Set password** |
| Saved | Signed in; **every** other browser session revoked (A05 §3.4, confirmed). If 2FA is on → S-27 first. Flash "Password saved. Other browsers were signed out." 1.x variant flash: "You're in. Two-step sign-in from 1.x didn't carry over: turn it on in Security." with link |
| Saved while a recovery is open (RC-2) | Flash adds: "Because your password was reset by email, account recovery now waits until {extended completes_label}." |
| Link doesn't work (expired, used, replaced by a newer reset or by an email change; one page) | S-32 `doesn't work`, reset variant · **Send a new link** |
| Field errors | As S-17B `password` (`password_too_short`, `password_too_long`, `password_common`, `password_is_email`) + "The passwords don't match." |

A reset link opened in another browser signs in there but never claims a guest session (A05).

---

## S-27 · Two-step sign-in challenge

**Purpose:** the second step after a password, GitHub or Google sign-in when the account has 2FA (domain §2.1; U-3). **URL:** `/two-factor` (half signed in: the session knows the user, nothing else is reachable). **Shell:** bare with "Cancel and sign out" top right. **Ceremony page.** **Flows:** FL-17, FL-21, FL-23. Desktop: column max 440; mobile 390: full width.

### Layout

`h1` "Confirm it's you" · line "Signed in as maya@brindlecoffee.example. One more step." · **method panel** (one at a time) · "Other ways" list of the remaining methods as links · footer "Lost access to all of these? Recover your account" (hosted) or "Lost access to all of these? Ask your instance administrator." (self-host). Guest claims and `next` happen only after this page (A05).

Methods are offered from A05 `methods`, in this order: **security key or passkey** (first when the account has any), **authenticator app**, **recovery code**.

| Method panel | Content (**NEW**) |
| ------------ | ------- |
| Security key or passkey | "Use your security key or passkey." **Use security key** (starts the ceremony; it does not start by itself, so screen-reader users hear the page first) · while waiting: "Touch your key or confirm on your device…" |
| Authenticator app | Field "6-digit code" (`inputmode="numeric"`, `autocomplete="one-time-code"`, paste allowed, spaces ignored) · **Verify** |
| Recovery code | Field "Recovery code" (helper "Like x7vc2-y4bnq. Each code works once.", hyphen optional, case ignored) · **Verify** |

### States (all drawn)

| State | Copy (**NEW**) |
| ----- | ---- |
| Key cancelled or timed out | "Nothing happened. Try again, or use another way below." |
| Key not recognised, blocked or counter refused (uniform, WA-1) | "That key didn't work. If it keeps failing, check your email: we may have blocked it to protect your account. Use another way below." |
| Wrong TOTP code | "That code didn't work. Enter the newest code from your app." + "{n} attempts left" when ≤ 2 |
| TOTP code already used | "That code was already used. Wait for the next one." |
| Wrong recovery code | "That recovery code didn't work. Check it, or try another one." |
| Recovery code accepted | Next page flash: "Recovery code used. You have 7 left." (≤ 2: adds "Generate new codes in Security.") |
| 5 attempts used on this challenge | Back to S-02 with: "Too many attempts. Sign in again to try again." |
| **Paused** (per-account hold: 20 failures in 24 h, WA-3) | Page body replaced: `h2` "Two-step sign-in is paused" · "There were too many wrong codes or keys for this account. Try again after 15:12 UTC. We emailed the account owner." · **Back to sign in**. Same page for every method; the time comes from RD `second_factor_hold_until` |
| Browser without WebAuthn | Security-key method hidden; line "This browser can't use security keys. Use your authenticator app or a recovery code." If keys are the only method: that line plus the recovery-code method and the recovery link |
| Only recovery codes left as a method | Panel opens on Recovery code |
| Recovery pending (account) | After success: S-29 `cancelled by sign-in` flash (RC-1) |
| Session expired while on the page | Back to S-02: "That took too long. Sign in again." |

### Keyboard and accessibility

Focus to `h1` on load. "Other ways" are links that swap the panel and move focus to the panel's heading (`h2` "Authenticator app" etc.). The one-time-code field is one input, never six boxes. Errors next to the field and summarised once. Paused state announced as `role="alert"` once.

---

## S-28 · Confirm it's you (recent authentication)

**Purpose:** a fresh proof before sensitive changes (domain §2.1, 10 minutes). **URL:** `/confirm-auth?next={path}&action={action}` (A05 props `methods`, `secondFactorRequired`, `action`). **Shell:** bare, column max 440. **Ceremony page.**

`h1` "Confirm it's you" · "You're about to {action}. Confirm it's you first. You won't be asked again for 10 minutes." where `{action}` is one of (**NEW**; rev. 4 additions marked +): "add a passkey", "add a security key", "remove a key", "+ unblock a key", "set up an authenticator app", "+ turn off the authenticator app", "turn off two-step sign-in", "generate new recovery codes", "change your password", "+ change your email", "+ connect GitHub" / "+ connect Google", "+ disconnect GitHub" / "+ disconnect Google" (replace rev. 3's "connect or disconnect an account"), "+ sign out a browser", "+ sign out other browsers", "create a token that can start runs", "delete your account", "change an instance setting".

Methods (from `methods`, best first; a **flagged** key is never offered or accepted, A05):

| Method | Shown when | Content (**NEW**) |
| ------ | ---------- | ------- |
| Passkey or security key | the account has an unflagged WebAuthn credential | **Use passkey or security key** |
| Password | `password` in `methods` and not `secondFactorRequired` | "Password" field + **Confirm** |
| Authenticator code | `totp` in `methods` | "Authenticator code" field + **Confirm** |
| **GitHub / Google** (rev. 4, U-16) | only for accounts with no password, no WebAuthn credential and no TOTP (A05); one button per linked provider | **Continue with GitHub** with, under it in `ink-muted`, "If you're signed in to GitHub in this browser, GitHub may confirm without asking." · **Continue with Google** with "Google will ask you to sign in again." |

**Second-factor mode** (rev. 4, `secondFactorRequired = true`: an email change on an account with two-step sign-in): the password method is not shown; a line under the intro: "Your email is how you'd recover this account, so confirm with a passkey, security key or authenticator code." (**NEW**)

Cancel → back to `next` unchanged.

| State | Copy (**NEW**) |
| ----- | ---- |
| Wrong password | "That password is incorrect." |
| Key states | As S-27 |
| GitHub / Google returned another account (`422 reauth_mismatch`) | "That isn't the GitHub account linked here. Sign in to GitHub as maya-l, then try again." (Google: "That isn't the Google account linked here. Choose {label} when Google asks.") |
| GitHub / Google cancelled | "Nothing changed. Try again when you're ready." |
| Success | → `next` with the dialog the person had open, choices kept |
| Success with a second factor while a recovery is open (RC-1, A05) | `next` plus the flash "You confirmed it's you with a second step, so account recovery was cancelled. If you didn't ask for it, change your password." |

---

## S-29 · Account recovery (hosted, PO-J)

**Purpose:** a slow, visible way back in when every second factor and every recovery code is lost (domain §2.3, RC-1 to RC-6; U-5). Designed as A09 recommends (delayed email recovery, 72 hours); if the PO picks operator review instead, S-29 shrinks to the self-host page with "Write to support@…". **URLs:** `/account-recovery`, `/account-recovery/{token}` (link page, S-32 rules), `/account-recovery/finish/{token}`; the banner is on S-00; cancel is `DELETE /account-recovery`, decline `DELETE /account-recovery/{token}`. **Shell:** bare. Self-host: 404, and S-27 shows the administrator line instead. Data: RD `account_security.recovery`. **Flow:** FL-21.

| # | Page / state | `h1` | Body and actions (**NEW**) |
| - | ------------ | ---- | -------------------------- |
| 1 | Request (from S-27 "Recover your account") | "Recover your account" | "Use this only if you've lost every passkey, security key, authenticator app and recovery code. Here's what happens:" ordered list: "1. We email a link to your account's address. 2. After you open it and press Start, there's a 72-hour wait. We email you again after 24 hours. 3. If you sign in with a second step during the wait, recovery stops. 4. After the wait, your passkeys, security keys, authenticator app, recovery codes and password are removed, every browser and personal token is signed out, and we email you a link to set a new password." · field "Email" (prefilled and read-only when coming from S-27) · **Send recovery link** · "Back" |
| 2 | Sent (uniform: no account, cool-down, already open, throttled) | "Check your inbox" | "If this address belongs to an account that can be recovered, we sent it a link. It works for 60 minutes. Open it and press Start to begin the 72-hour wait." |
| 3 | **Link page** (rev. 4, U-14: opening it changes nothing; A05 confirmed) | "Start account recovery?" | "Your account maya@brindlecoffee.example will be recoverable on {completes_label}, 72 hours after you press Start. Until then you can't sign in without a second step." **Start the 72-hour wait** · "Cancel, I found my key" (a button: `DELETE`, closes the request as `declined`) |
| 3b | **Declined** (rev. 4) | "Recovery not started" | "Nothing changed. You can close this tab." (RD `recovery.decline`). No email; no 30-day cool-down (A05 RC-5 reading) |
| 4 | Waiting | "Recovery started" | "Your account becomes recoverable on 14 Nov 2026, 09:20 UTC. We'll email you then with a link to set a new password. If you find a passkey, key or code before then, just sign in: recovery stops by itself." RC-2 variant: "…on 18 Nov 2026, 09:20 UTC. The wait is 7 days because the password was reset by email on 8 Nov 2026, 18:40 UTC." |
| 5 | Link opened again, still waiting | "Recovery is waiting" | Same as 4 with the time left: "2 days 19 hours left." |
| 6 | Banner on signed-in pages | — | S-00 `Account recovery pending` + **Cancel recovery** |
| 7 | Cancel dialog | "Cancel account recovery?" | "If you didn't ask for this, cancel it and change your password: someone may know it." **Cancel recovery** · Keep it. Done → flash "Recovery cancelled. We emailed you." + link "Change password" |
| 8 | Cancelled by a sign-in or by S-28 with a second step (RC-1) | flash | "You signed in with a second step, so account recovery was cancelled. If you didn't ask for it, change your password." (S-28 variant there) |
| 9 | **Finish** (link in the `recovery-ready` email; works 24 hours, A05 confirmed) | "Set a new password" | "The wait is over. Set a password to finish recovering your account. This link works until 15 Nov 2026, 09:20 UTC." Fields and errors as S-26 (12 to 128 characters) · **Finish recovery** → signed in → S-30 |
| 10 | Link doesn't work (expired, used, cancelled, declined; one page) | S-32 `doesn't work` | Request-link variant; finish-link variant adds the Forgot-password route |

States per page: submitting, field errors (as S-26), server error (`copy.errors.server`). Mobile 390: same pages, full width; the ordered list stays a list.

---

## S-30 · After recovery: security review

**URL:** `/account/security/review` (A05 contracts §3.4, page `Account/SecurityReview`, props `review: RecoveryReview`). Available from completion until `lockUntil` (7 days), then the URL redirects to S-17A (A05). Shown right after S-29 #9; linked from S-17A while `recoveryReview` is set. Data: RD `account_security.recovery` and `.recovery.review`. **Flow:** FL-21 step 7.

`h1` "Your account was recovered" · line "On 14 Nov 2026, 09:20 UTC. Here's what changed and what to check now." (**NEW**, from `completedAt`). Four sections:

| Section (`h2`) | Content (**NEW**) |
| -------------- | ------- |
| Turn two-step sign-in back on | "Your passkeys, keys, authenticator app and recovery codes were removed." **Add a passkey** · **Set up an authenticator app** |
| Check your agents | From `agents`. "These agents won't run anything until you confirm them in their terminal. Approved targets were cleared." Row: "maya-mbp · confirm in its terminal" (`needsReconfirm`) + link **Agent page** (S-07). Empty: "No agents in your workspace." |
| Check your CI tokens | From `ciTokens` (all your projects). "These tokens can still upload runs. Revoke any you don't recognise." Table Token · Project · Last used · action: "github-actions · Brindle Coffee · 10 Nov 2026" and "github-actions · Stripe project · Never used", each with **Revoke** (accessible name "Revoke github-actions in Stripe project"). Empty: "No CI tokens." |
| What happened | Table When · What · Browser · Network from `log` (RD `recovery.review.log`, 5 rows, newest first). Under it: "Until 21 Nov 2026, 09:20 UTC you can't change your email, disconnect GitHub or create tokens that start runs." |

**Revoke** (rev. 4: exact copy, replaces the verb A04 had to supply; same dialog for S-16 CI tokens): confirm dialog title "Revoke github-actions in Stripe project?" · "CI jobs that use it can't upload runs to Stripe project any more. This can't be undone." **Revoke token** (destructive) · Cancel → toast "github-actions revoked in Stripe project." (RD `review.revoked_toast`). The row stays, its button replaced by the word **Revoked** in `ink-muted`, so the list doesn't jump. Error: ruled banner `copy.errors.server`. Route: `DELETE /p/{projectKey}/settings/ci/tokens/{id}` (A05 §3.2), no recent authentication.

**Done** → S-17A. 390: sections stack; the token table becomes a list (name, project, last used, full-width **Revoke**).

---

## S-31 · Account emails

Plain text first, with a minimal HTML version (wordmark, one column 560, Archivo, `ink` on `paper`, one link button). Sender "Webhook Trap <account@webhooktrap.dev>" (placeholder domain, C-1). Every email ends with the footer "You get this email because it's about the security of your Webhook Trap account. It can't be turned off." Names of keys are printed as plain text with bidi and control characters stripped (R-H). Times in UTC. `{browser}` and `{network}` are the session's label and truncated network (A05 `user_sessions`). All copy **NEW**; `{…}` from RD `account_security`. No email contains a code, a token value, or a link that signs the person in, except the links named below. **Every link with a token opens a link page (S-32) and changes nothing until its button is pressed** (A05 §2.9); notices (`account-exists`, `key-*`, …) link to `/sign-in` or `/account/security` and carry no token.

| Key | Trigger (A05 event) | Subject | Body |
| --- | ------------------- | ------- | ---- |
| `verify` | Sign-up (rev. 4: email changes use `email-change-verify`) | Confirm your email for Webhook Trap | "Open this link to confirm {email}: {link}. It works for 60 minutes. If you didn't create an account, ignore this email." |
| `account-exists` | Sign-up with a taken email | Someone tried to sign up with your email | "Someone tried to create a Webhook Trap account with {email}. You already have one. Sign in: {link}. Forgot your password? {reset link}. If it wasn't you, you can ignore this." |
| `reset` | Forgot password | Reset your Webhook Trap password | "Open this link to set a new password: {link}. It works for 60 minutes and once. If you didn't ask, ignore this email: your password stays the same." |
| `password-changed` | `PasswordChanged` / `PasswordReset` | Your password was changed | "Your Webhook Trap password was changed on {time} from {browser} ({network}). Other browsers were signed out. If this wasn't you, reset your password now: {reset link}." Reset variant: "…was reset on {time} from {browser} ({network}). Every other browser was signed out." Personal tokens are kept and listed by name: "Your personal tokens still work: {names}." |
| `method-linked` | `IdentityLinked` (WA-5) | GitHub was linked to your account | "GitHub account {label} can now sign in to your Webhook Trap account ({email}). Linked on {time} from {browser}. If this wasn't you, disconnect it in Security: {link}, and change your password." (Google: same with Google) |
| `method-unlinked` (rev. 4, U-12) | `IdentityUnlinked` | GitHub was disconnected from your account | "GitHub account {label} can no longer sign in to your Webhook Trap account. Disconnected on {time} from {browser} ({network}). If this wasn't you, sign in and check Security: {link}, and change your password." (Google: same) |
| `key-added` | WebAuthn registered | A passkey was added to your account | "{name} ({type}) was added on {time} from {browser}. If this wasn't you, remove it in Security: {link}." Security-key subject: "A security key was added to your account" |
| `key-removed` | WebAuthn removed | A passkey was removed from your account | "{name} was removed on {time}. If this wasn't you, sign in and check Security: {link}." |
| `key-blocked` | Counter anomaly or handle mismatch (WA-1, WA-2) | We blocked a key on your account | Counter: "We refused {name} on {time} because its use counter went backwards. That can mean the key was copied." User handle (rev. 4): "We refused {name} on {time} because it sent sign-in details that don't belong to your account. That can mean it was tampered with." Both: "It stays blocked until you review it in Security: {link}. If you didn't try to sign in at that time, change your password." |
| `key-unblocked` (rev. 4, U-11) | `WebAuthnCredentialUnblocked` | A blocked key was unblocked | "{name} was unblocked on {time} from {browser} ({network}) and works again. If it's blocked again, it can't be unblocked. If this wasn't you, remove the key in Security: {link}, and change your password." |
| `totp-on` / `totp-off` | TOTP enrolled / removed | Authenticator app turned on / turned off | "An authenticator app was turned on for your account on {time}." / "…turned off on {time}." + "If this wasn't you…" line with Security link. When it was the last second factor, `2fa-off` is sent instead |
| `2fa-off` | Last second factor removed | Two-step sign-in was turned off | "Two-step sign-in was turned off on {time} and your recovery codes were deleted. If this wasn't you, sign in and turn it back on: {link}." |
| `codes-new` | Recovery codes regenerated | New recovery codes were created | "New recovery codes were created on {time}. The old ones no longer work." |
| `code-used` | Recovery code used | A recovery code was used to sign in | "A recovery code was used to sign in on {time} from {browser}. You have {n} left. If this wasn't you, change your password and generate new codes: {link}." |
| `hold` | `SecondFactorHold` (WA-3) | Too many wrong codes for your account | "There were 20 wrong codes or keys for your account in 24 hours, so two-step sign-in is paused until {time}. Someone may know your password: change it now: {reset link}." |
| `email-change-verify` (rev. 4, U-13) | `EmailChangeRequested`, new address free | Confirm your new email for Webhook Trap | "Someone signed in to Webhook Trap asked to use {new email} for their account. If it was you, open this link in a browser where you're signed in to that account and press Confirm new email: {link}. It works for 60 minutes and once. If it wasn't you, ignore this email: nothing changes." |
| `email-change-taken` (rev. 4; A05 "account-exists-style") | `EmailChangeRequested`, new address already has an account | Someone tried to use your email on another account | "Someone signed in to another Webhook Trap account asked to use {new email}. This address already has an account, so nothing changed. Sign in to your account: {sign-in link}. If you don't know why this happened, you can ignore this." |
| `email-change-confirm-old` (rev. 5, EC-1) | `EmailChangeRequested`, to the **old** address at once, account **without** two-step sign-in (replaces `email-change-requested` there) | Confirm the change of your Webhook Trap email | "On {time}, from {browser} ({network}), someone signed in to your Webhook Trap account asked to change its email to {masked new email}. If it was you, open this link in a browser where you're signed in to the account and press Confirm email change: {link}. The email changes only when this link and the one sent to the new address have both been confirmed, within 60 minutes. It works once. If it wasn't you, ignore this email: nothing changes without it. Change your password now: {sign-in link}. If you can't sign in, start account recovery from the sign-in page." No cancel and no "This wasn't me" link. Link opens S-32 `Confirm email change` |
| `email-change-requested` (rev. 4; rev. 5: **2FA accounts only**) | `EmailChangeRequested`, to the **old** address at once | Someone asked to change your account's email | "On {time}, from {browser} ({network}), someone signed in to your Webhook Trap account asked to change its email to {masked new email}. Nothing changes until the link sent to that address is opened. If this wasn't you, sign in and change your password now: {sign-in link}. If you can't sign in, start account recovery from the sign-in page." No cancel link (A05) |
| `email-changed` (rev. 4) | `EmailChanged`, to the **old** address | Your account's email was changed | "On {time}, from {browser} ({network}), your Webhook Trap account's email was changed to {masked new email}, for example m***@b***.example. Other browsers were signed out, and this address no longer gets account emails. If this wasn't you, sign in now and check Security: {sign-in link}." **[A05/A09: what an owner who has lost the account email can do next; no route exists]** |
| `email-changed-new` (rev. 4) | `EmailChanged`, to the **new** address | This is now your Webhook Trap email | "{new email} is now the email for your Webhook Trap account. Sign-in, recovery and security emails come here." |
| `recovery-link` | Recovery requested | Recover your Webhook Trap account | "Someone asked to recover your account. If it was you, open this link and press Start to begin a 72-hour wait: {link}. It works for 60 minutes. If it wasn't you, ignore this email: opening the link alone changes nothing." |
| `recovery-not-started` | Request during cool-down or with one open (RC-5) | Account recovery couldn't start | "Someone asked to recover your account, but a recovery {is already waiting / was cancelled on {date}}. You can ask again after {cooldown_until_label}." |
| `recovery-started` | Link confirmed | Account recovery started: 72 hours | "Account recovery started on {confirmed_label}. On {completes_label} your passkeys, keys, authenticator app, recovery codes and password will be removed and every browser signed out. **If this wasn't you, cancel it now:** sign in with any passkey, key or code (that cancels it), or use Cancel recovery on any signed-in page: {link}." |
| `recovery-reminder` | 24 h after start | Account recovery finishes in 48 hours | Same as `recovery-started` with "in 48 hours". |
| `recovery-extended` | RC-2 | Account recovery now waits 7 days | "Because your password was reset by email on {password_reset_label}, account recovery now finishes on {extended completes_label}. If this wasn't you, cancel it: {link}." |
| `recovery-cancelled` | `AccountRecoveryCancelled` (by user or by sign-in; not for `declined` or `link_expired`, A05) | Account recovery was cancelled | By user: "Account recovery was cancelled on {time} from {browser}." By sign-in: "You signed in with a second step on {time}, so account recovery was cancelled." Both: "If you didn't ask for recovery, change your password: someone may know it." |
| `recovery-ready` | Wait over (completion) | Finish recovering your account | "The wait is over. Set a new password to finish: {link}. It works for 24 hours and once. After that, use Forgot password on the sign-in page: your account no longer has two-step sign-in." (A05 confirmed 24 h) |
| `recovery-done` | Recovery completed | Your account was recovered | "Your account was recovered on {time}. We removed your passkeys, keys, authenticator app, recovery codes and password, signed out every browser and personal token, and asked maya-mbp to confirm in its terminal. Turn two-step sign-in back on: {link}." |
| `delete-confirm` (rev. 5, DL-4) | `DELETE /account` on an account **without** two-step sign-in, to the account address at once | Confirm deleting your Webhook Trap account | "On {time}, from {browser} ({network}), someone signed in to your Webhook Trap account asked to delete it, with its projects, runs, agents and tokens. If it was you, open this link in a browser where you're signed in to the account and press Delete my account: {link}. It works for 60 minutes and once. Nothing is deleted until you press it. If it wasn't you, ignore this email and change your password now: {sign-in link}." Link opens S-32 `Delete my account`. A new request replaces the earlier link **[pending A05: confirm]** |
| `account-deleted` (rev. 4, U-19; A05 revision 6: enqueued in the deletion transaction, the address copied into the job) | Account deleted | Your Webhook Trap account was deleted | "Your Webhook Trap account ({email}) was deleted on {time} from {browser} ({network}), with its projects, runs, agents and tokens. This can't be undone. If this wasn't you, someone had access to your account: change the password of this email account." |
| `launch-1x` | Launch, once, 3 imported accounts (decision 41) **[PO approves text and sending]** | Webhook Trap 2.0 is here: set a password to sign in | "Hello {name}, you had a Webhook Trap account before. Webhook Trap 2.0 is now live, and your account came across. Your 1.x inboxes and events didn't: they stay archived. To sign in, set a password: {forgot-password link}. Or sign in with GitHub or Google using {email}. Two-step sign-in from 1.x didn't carry over; you can turn it on again in Security. — {PO name}, Webhook Trap" |

Signing out browsers sends no email (A05). **32 templates**, counting `totp-on` and `totp-off` as one (rev. 3: 22; rev. 4 adds `method-unlinked`, `key-unblocked`, `email-change-verify`, `email-change-taken`, `email-change-requested`, `email-changed`, `email-changed-new`, `account-deleted`; rev. 5 adds `email-change-confirm-old` and `delete-confirm`). The 1.x `launch-1x` text is still approved by the PO (decision 41).

Boards: one frame per email family (S31Emails), plain-text and HTML for `key-added`, `recovery-started`, `email-change-requested`, `email-change-confirm-old`, `delete-confirm`, `launch-1x`; the others as plain-text rows.

---

## S-32 · Emailed-link pages (rev. 4, U-14)

**Rule (A05 domain §2.9, contracts §3.4).** No emailed link changes anything when it is opened: mail scanners and link previews open links. Every link with a token (verify, email change, reset, recovery request, recovery finish, self-host password-set) lands on a **link page** that checks the token without using it and shows **one primary button**; the change happens only when that button sends the form (`POST`, CSRF). Pages never submit themselves by script. Bare shell, column max 440, focus to `h1`, `noindex`, no third-party resources; A05 sends `Referrer-Policy: no-referrer` and `Cache-Control: no-store`. Expired, used and unknown tokens get **one page** per link type, identical for each cause.

| Link | Link page | Button | Spec |
| ---- | --------- | ------ | ---- |
| Verify (60 min) | "Confirm your email" | **Confirm email** | S-25 |
| Email change, new address (60 min) | "Confirm your new email" | **Confirm new email** | below |
| Email change, **old address** (60 min, rev. 5, EC-1, no 2FA) | "Confirm the email change" | **Confirm email change** | below |
| Account deletion (60 min, rev. 5, DL-4, no 2FA) | "Delete your account?" | **Delete my account** | below |
| Reset (60 min) | "Choose a new password" (the form) | **Save password** | S-26 |
| Recovery request (60 min) | "Start account recovery?" | **Start the 72-hour wait** (+ "Cancel, I found my key") | S-29 #3 |
| Recovery finish (24 h) | "Set a new password" (the form) | **Finish recovery** | S-29 #9 |

### Confirm new email (`/email-change/{token}`, U-13)

Data: RD `account_security.maya.pending_email`. **Flow:** FL-24.

| State | `h1` | Body and actions (**NEW**) |
| ----- | ---- | ---- |
| Signed in to the account that asked | "Confirm your new email" | **2FA account:** "Make maya@brindleroasters.example the email for your Webhook Trap account? Sign-in, recovery and security emails will go there, and your other browsers will be signed out." **Confirm new email** · link "Not now" (to S-17A; changes nothing; the link keeps working until 14:56 UTC). **No 2FA (rev. 5):** the same body plus "The email changes once your current address has confirmed too." when the other half is open, or "Your current address has already confirmed, so this finishes the change." when it is done |
| Confirming | — | Button "Confirming…", disabled |
| Confirmed | — | → S-17A with flash "Your email is now maya@brindleroasters.example. Other browsers were signed out." **No 2FA, other half still open (rev. 5):** flash "Confirmed. Waiting for the link sent to maya@brindlecoffee.example." and the S-17A pending line updates |
| Not signed in | "Sign in to confirm your new email" (old-address page: "Sign in to confirm the email change") | "For your safety, your email changes only in a browser where you're signed in to the account that asked for it." **Sign in** (→ S-02 with `next` = this link page; after S-27 if needed, back here) |
| Signed in to another account | "Sign in to confirm your new email" | "You're signed in as {other email}. Sign out, then sign in to the account that asked for this change." **Sign out and continue** (signs out, → S-02 with `next` = this page) **[A05: link-page props for "signed in, other account"]** |
| Address taken since, recovery lock, replaced by a newer request, expired or used | S-32 `doesn't work` | email-change variant (A05: conflicts answer "This link doesn't work") |
| **This half already confirmed** (rev. 5, no 2FA; link reopened) | "You already confirmed this" | "The change finishes when {other address} is confirmed too. Links work until 14:56 UTC." **Go to Security**. No button |

### Confirm email change (old address, `/email-change/{token}`, rev. 5, EC-1)

Same route, shell and rules as above; A05 tells the two halves apart by token. Data: RD `account_security.maya.pending_email`. **Flow:** FL-24. Only accounts without two-step sign-in get this link; no "This wasn't me" link exists.

| State | `h1` | Body and actions (**NEW**) |
| ----- | ---- | ------------------------- |
| Signed in to the account that asked | "Confirm the email change" | "Change your Webhook Trap email from maya@brindlecoffee.example to m***@b***.example? Both addresses must confirm. This one is the current address. Sign-in, recovery and security emails will go to the new address, and your other browsers will be signed out." **Confirm email change** · link "Not now" (to S-17A; the link works until 14:56 UTC) |
| Confirming | — | Button "Confirming…", disabled |
| Confirmed, other half open | — | → S-17A with flash "Confirmed. Waiting for the link sent to m***@b***.example." |
| Confirmed, other half done | — | The change happens: → S-17A with flash "Your email is now maya@brindleroasters.example. Other browsers were signed out." |
| Not signed in · signed in to another account | "Sign in to confirm the email change" | As the new-address page, same two actions |
| This half already confirmed | "You already confirmed this" | As above |
| Expired, used, replaced, cancelled, recovery lock | S-32 `doesn't work` | email-change variant |

### Delete my account (`/account-deletion/{token}`, rev. 5, DL-4) **[pending A05: route name]**

Same shell and rules. Only accounts without two-step sign-in get this link (an account with two-step sign-in deletes from S-17 `delete` after the second factor). Data: RD `account_security.account_deletion`. **Flow:** FL-26.

| State | `h1` | Body and actions (**NEW**) |
| ----- | ---- | ------------------------- |
| Signed in to the account that asked | "Delete your account?" | "This deletes the Webhook Trap account for maya@brindlecoffee.example and everything in it, now: 2 projects, 4 runs, 1 agent, 3 tokens. It can't be undone." **Delete my account** (destructive; the only button) · link "Not now" (to S-17; changes nothing; the link keeps working until 14:56 UTC). The counts come from the same summary as S-17 `delete` |
| Deleting | — | Button "Deleting your account…", disabled |
| Deleted | — | → S-01 with the ruled flash "Your account was deleted. We sent a confirmation to maya@brindlecoffee.example." |
| Not signed in | "Sign in to delete your account" | "For your safety, an account is deleted only in a browser where you're signed in to it." **Sign in** (→ S-02 with `next` = this page) **[pending A05: confirm the signed-in requirement]** |
| Signed in to another account | "Sign in to delete your account" | "You're signed in as {other email}. Sign out, then sign in to the account that asked for this." **Sign out and continue** |
| Recovery open or locked, last administrator (self-host), expired, used, replaced | S-32 `doesn't work`, deletion variant | below |

### `doesn't work` · One page for every bad link

`h1` "This link doesn't work" · body by link type (**NEW**), the same for expired, used and unknown:

| Link type | Body | Action |
| --------- | ---- | ------ |
| Verify | "It may have expired or been used. Confirmation links work for 60 minutes. Sign in to get a new one." | **Sign in** |
| Email change (new or old address, rev. 5) | "It may have expired, been used or been replaced by a newer request. Links to confirm an email change work for 60 minutes. Ask again in Security." | **Go to Security** (signed in) / **Sign in** |
| Account deletion (rev. 5) | "It may have expired, been used or been replaced by a newer request. Links to delete an account work for 60 minutes. Nothing was deleted. Ask again from Delete account." | **Go to Delete account** (signed in) / **Sign in** |
| Reset | "It may have expired or been used. Reset links work for 60 minutes and once." | **Send a new link** |
| Recovery request | "It may have expired, been used, or the recovery was cancelled. Recovery links work for 60 minutes. Sign in, or start again." | **Sign in** · "Start again" |
| Recovery finish | "It may have expired or been used. Finish links work for 24 hours. Your account no longer has two-step sign-in, so you can set a password with Forgot password." | **Forgot password** |

390: same column, full width. Keyboard and accessibility: A11Y §9 "Link pages".

---

## Changes

| # | Change | Source |
| - | ------ | ------ |
| 1 | New file: 24 screens (S-00 to S-23) with regions, components, data, states, responsive rules, keys, accessibility and copy | Phase 2 task |
| 2 | New §0.5: probe display rules (count, code, shown value, `#invalid`), who sees what (owner, share viewer with and without Include probe values, guest, example), rendering, tooltip and note, the never-on-hosted list | Decision 38 (PV-1 to PV-8, PV-11, PV-12, PV-14); QA v3 M10 |
| 3 | S-11 State reading: the PRB-3 note replaced; code-valued variants (owner default, `show: value`, share viewer, unchanged, widened, refused); strip and grid rows for codes | PV-2 to PV-7; QA v3 M10, M12 (f) |
| 4 | Hosted pages never show probe definitions, the reset command or its output: S-10 step 5, S-11 Passed "What was checked", Couldn't start and Aborted `reset_failed`, F4 and `needs_probe` snippets labelled "Example" (pack templates) | PV-8; QA v3 M11 |
| 5 | S-12 `share` rewritten: "Shared as", private by default, PV-14 line, Include probe values (owner, account, opted entries only), `/s/{token}` link, Create a new link (rotation), guest note including claim | PO-A, PV-7, PV-14; QA v3 M10 (b, c), m15 |
| 6 | S-13 moves to `/s/{shareToken}`: provenance line, header allow-list, probe display, polling, not-found cases; S-11 is owner and members only; S-18 Not found covers rotated tokens | PO-A; A05 contracts §3.2, schema §6; QA v3 m17 |
| 7 | Provenance line on every run view (S-11, S-13, S-14) with RUN-8 copy; Runs list Source in short form; Level 2 finding header "judged on maya-mbp"; no "verified" | A01 RUN-8, RUN-9; TM-17 |
| 8 | Pairing: 10-symbol code `7KQ2M-XR9TD`, `@webhooktrap/agent@1`; "Confirm in your terminal" state, declined state, reconfirm after claim (S-00 pill, S-06, S-08, S-10) | PO-E, PO-F; A01 §16 row 37 |
| 9 | Secret last-4 hints removed (S-00 popover, S-04 Valid, S-07 table); no digests in the #09 detail; CI and personal tokens by name only | PO-D; A01 SIG-3, RUN-8 |
| 10 | Credential headers show `[redacted · N bytes]` (three headers); run inspector says the signature value isn't stored | A05 S-7, schema §1 |
| 11 | S-10 server target: accounts only, kill switch, guest disabled reason; Shopify server-draft copy (what, why, Tampered mutation, budget), citations `[pending A01]`; n/a rows hide why and citation | PO-B, HD-10, RUN-11; QA v3 M4 |
| 12 | "Owner offline" defined as "Agent offline, finished run" with copy and disabled actions; Queued "Confirm in maya-mbp's terminal" state | QA v3 M12 (d); A05 contracts §5.4 (needs_confirmation) |
| 13 | Guest meter values for the waiting and first-four moments; F4 evidence D6 · D15 and the Burst strip cell gains F4 Info | QA v3 M7; A01 PRB-12a |
| 14 | S-23: per-user permission for private destinations | PO-G |
| 15 | **Rev. 3.** S-02 rewritten: email + password, GitHub, Google, passkey (with autofill), uniform errors, unsupported-browser and HTTP-instance states, 1.x line; email link removed | Decision 44; A05 contracts §3.4, U-1; A09 §12.4 |
| 16 | S-00: recovery-pending banner on every signed-in page, unverified redirect, 2FA nudge in the account menu | U-5; A05 shared props `recoveryPending`; RC-1 |
| 17 | S-17 Account restructured: Security replaces Sign-in methods; `run` tokens need recent authentication and are locked during recovery | U-4; domain §2.1; RC-3 |
| 18 | New S-17A Security (password, passkeys and security keys with sync and last use, blocked keys, authenticator app, recovery codes, linked accounts) and S-17B dialogs (add, rename, remove incl. last-factor rules, review blocked key, TOTP, recovery codes once, password, linked accounts) | U-4; domain §2.1–2.3; WA-1, WA-2; contracts `SecurityView` |
| 19 | New S-24 Create account, S-25 Verify email, S-26 Forgot and reset password (with the 1.x "Set a password" variant), S-27 Two-step challenge (key first, TOTP, recovery code, paused hold), S-28 Confirm it's you | U-2, U-3, U-7; WA-3; PO-H B |
| 20 | New S-29 Account recovery (request, uniform answer, confirm, 72 h wait, RC-2 extension, cancel, cancelled by sign-in, finish) and S-30 security review after recovery (agents to reconfirm, CI tokens to review, security log) | U-5; domain §2.3 RC-1 to RC-6; A09 TM-125, TM-126 |
| 21 | New S-31: 22 account email templates including "Sign-in method linked", recovery started, reminder, extended, cancelled, completed, and the 1.x launch notice **[PO approves text]** | WA-5; domain §2.3, §2.4; decision 41 |
| 22 | S-19 Overview: relying-party ID row with its warning, and the off state on HTTP or an IP | U-6; domain §2.2 |
| 23 | RD gains `account_security` (fixtures for all of the above); generator stays reproducible | RD §7, change 27 |
| 24 | **Rev. 4.** S-17A: section 1 becomes "Password and email" with the pending-change line and Cancel change; new section 6 "Where you're signed in" (this browser first, browser, network, signed in, last seen, how; Sign out one, Sign out all other browsers); new section 7 "Recent security activity" (30 days, ≤ 50 rows); review link while `recoveryReview` is set; states for user-handle and twice-blocked keys, lock open vs completed (Connect stays enabled), only this browser, no activity | U-10, U-11, U-12, U-13, U-17, U-18; A05 domain §2.5–2.8, contracts §3.4 `SecurityView`, `SessionRow`, `SecurityLogEntry` |
| 25 | S-17B: new `email` (form, uniform sent, second factor required, lock) and `signout` (one, all others, already signed out; no email); `blocked` uses the unblock route, cause-specific copy, "blocked twice" without Unblock, `unblock_not_allowed`; `linked` connect and disconnect with routes, `identity_in_use`, `method-unlinked`, lock on disconnect only (AR-1); `password` with A05's policy and error codes | U-10 to U-13, U-15; A05 domain §2.1, §2.2, §2.5, §2.6 |
| 26 | Every emailed link opens a link page with one button (new S-32 rule); S-25 gains **Confirm email**; S-29 #3 drops its pending marker and gains 3b Declined; new S-32 "Confirm new email" (signed in, signed out, other account) and one "This link doesn't work" page per link type for expired, used and unknown | U-14; A05 domain §2.9, contracts §3.4 emailed links |
| 27 | Confirmed values in copy: links 60 min (verify, email change, reset, recovery request) and 24 h (recovery finish); password 12–128 (72 bytes on bcrypt), "This password is too common. Choose another." replaces the breach message, "Don't use your email address as your password." added; password change signs out other browsers (pending marker removed) | U-15 |
| 28 | S-28: rev. 4 actions, method table, OAuth-only GitHub/Google with `reauth_mismatch` and cancel copy, second-factor mode for email change, recovery-cancelled flash (RC-1) | U-16; A05 contracts `/confirm-auth` |
| 29 | S-30 rebuilt on `RecoveryReview`: agents, CI tokens across projects with Revoke, log, lock line from `lockUntil`, availability window; route no longer pending (PO-J still is) | U-17 |
| 30 | S-17 `delete` page specified with checkbox gating, recovery-lock and last-admin states, done flash, `account-deleted` email, all **[pending A05]**; contract needs listed in "Open for the PO" O-SS-9 | U-19 |
| 31 | S-31: 8 new templates (`method-unlinked`, `key-unblocked`, `email-change-verify`, `email-change-taken`, `email-change-requested`, `email-changed`, `email-changed-new`, `account-deleted`); `key-blocked` user-handle variant; `verify` sign-up only; recovery copy says opening a link changes nothing and the password is removed at completion; `recovery-ready` 24 h confirmed | U-11 to U-15, U-19; A05 domain §2.3 |
| 32 | Three values A04 had to supply are now specified: S-02/S-24 throttled "Too many attempts. Try again after 14:58 UTC." (from `Retry-After`; A05 has no throttle number); S-17B `totp` turn-off titles, bodies, buttons and toasts (normal and last second factor); S-30 token row **Revoke** → dialog **Revoke token** → toast and **Revoked** row word | A04 board notes; A05 domain §2.1 |
| 33 | RD change 28: sessions, security log, pending email, throttle example, policy and lifetimes, Spare YubiKey blocked twice, Old YubiKey user-handle note, review data, deletion summary; generator reproducible, earlier values unchanged | RD §7 |
| 34 | **Rev. 5.** Decisions 46 and 47: every `[pending PO]` marker removed (PO-H option B, PO-I, PO-J decided; PO-L, PO-M, PO-N, PO-O decided); O-SS-4 and O-SS-6 closed; `[pending A05]` on S-17 `delete` removed where A05 revision 6 gives the value (route, second factor on 2FA accounts, recovery lock, last administrator, `account-deleted` email) | Decisions 46, 47; A05 U-20 |
| 35 | **DL-4.** S-17 `delete`: on accounts without two-step sign-in, **Delete account** (after S-28) sends a link to the account address and the page shows "Check your email" (states: sending, check your email, expired); new email `delete-confirm`; new S-32 page "Delete your account?" with the single button **Delete my account** (signed in, deleting, deleted, signed out, other account) and a deletion variant of "This link doesn't work". Accounts with two-step sign-in keep the fresh-confirmation flow. Link lifetime **60 min**, A05 open-questions R-U | Decision 47 PO-N; A05 R-U |
| 36 | **EC-1.** S-17B `email` gains the no-2FA variant ("Check both inboxes"); S-17A pending line becomes "Waiting for both addresses" / "Waiting for your current address" / "Waiting for the new address" with `✓` on the done half; new email `email-change-confirm-old` and S-32 page "Confirm the email change" (**Confirm email change**) plus "You already confirmed this"; `email-change-requested` stays for 2FA accounts only; flashes "Confirmed. Waiting for …"; "doesn't work" copy covers both addresses. No "This wasn't me" link | Decision 47 PO-O; R-AE rejected |
| 37 | RD fixtures needed for rev. 5 boards (A04 adds to `account_security`, values are in the copy above): `pending_email.new_confirmed`, `old_confirmed` (three states), a no-2FA variant of `account_deletion` with `link_sent_to`, `expires_at` 14:56 UTC | RD §7 |

## Boards for A04 (redraw list, rev. 2)

| Board | What changes | Spec |
| ----- | ------------ | ---- |
| `S00Shell` | Pill states "Confirm in your terminal" and "maya-mbp · confirm in terminal"; connected popover without last-4 hints; claim toast | S-00 |
| `S01Landing`, `S01LandingDark`, `S01LandingMobile`, `S01LandingMobileDark` | Section 5 probe line (PV-14 landing string) | S-01 |
| `S03CaptureWaiting`, `S03CaptureMobileWaiting`, `S03CaptureGuestFirst` | Guest meter `0/100 · 0/10 runs` and `4/100 · 0/10 runs` | S-03, RD `copy.guest.meter_*` |
| `S04Detail09`, `S04Detail09Dark`, `S04Detail08`, `S04DetailRaw`, `S04DetailStates`, `S04Mobile09`, `S04MobileRaw` | Valid helper without last 4; #09 detail without digests; Raw credential rows `[redacted · N bytes]` | S-04 |
| `S05Runs`, `S05RunsMobile` | Source column in RUN-8 short form; CI row by token name only | S-05 |
| `S06AgentGuest`, `S06Agents`, `S07AgentDetail`, `S08Pair`, `S08PairMobile` (all six pair-panel states + new "Confirm in your terminal" and "Declined") | 10-symbol code, `@1` command, terminal hint line, secrets table without hints | S-06 to S-08 |
| `S10Draft`, `S10DraftMobile`, `S10DraftNoAgent`, `S10DraftStates` | Step 5 without reset command, with the PV-14 sentence and entry tags; "No probe" template labelled Example; Confirm-in-terminal state; server target guest-disabled state | S-10 |
| `S10DraftServer` | Fill every `[Shopify why: A03 copy]`, `[citation: A03]` and mutation hole from the S-10 Shopify table; n = 2 counts; `5 s · Shopify limit`; n/a rows without why/citation | S-10, RD §5.1 |
| `S11RunFindings`, `S11RunFindingsDark`, `S11RunMobile`, `S11RunMobileDark`, `S11InspectorMobile`, `P2Graph` (F4 evidence highlight D6 · D15) | Provenance line; Burst cell `○ F4 Info · 0 → 1 (final)`; F4 evidence D6 · D15; finding meta "judged on maya-mbp"; inspector `Stripe-Signature` row "signed on maya-mbp · value not stored" | S-11 |
| `S11RunRunning`, `S11RunAborted`, `S11RunInconclusive`, `S11RunPassed`, `S11RunCouldntStart` | Provenance line; Passed "What was checked" hosted copy; `reset_failed` hosted wording; Inconclusive yaml labelled Example; Queued confirm variant | S-11 |
| `StatesSheet2` | Replace the "Owner offline … [copy: A03]" frame with "Agent offline, finished run"; add the code-valued State reading set (6 variants), code strip cell, code grid row, `#invalid`; Aborted-with-findings (`reset_failed`); share-dialog tick variant; still-undrawn S-11 states from QA M12 (preflight verdict, Probe failed row, Unsettled row) | S-11, §0.5 |
| `S12Dialogs` (Share) | Full rewrite: five share states, `/s/` link, Include probe values tick on the states variant, guest note only on the guest variant | S-12 |
| `S13SharedRun` | `/s/` URL in the address shown, provenance line, header allow-list, probe note removed ("Probe values are display values." deleted) | S-13 |
| `S14ExampleRun` | Provenance line; Burst cell and F4 evidence as S-11 | S-14 |
| `S16SettingsPages`, `S17Account` | Token tables without last 4; CI token creation helper with `--share` | S-16, S-17 |
| `S23AdminServerReplay` | Per-user permission list | S-23 |
| `GF152CliSummary`, `GF152CliSummaryDark`, `GF153CiTail`, `GF154Verdicts` | F4 evidence `D6 D15`; `report` line plus `share` line variant; CI tail `uploaded privately` line; Run #2/#1 blocks as GF §15.4 | GF §15 |
| `GF155PrComment`, `GF155PrCommentDark` | Links: `/r/{runId}` (private, members) by default, `/s/{token}` with `--share`; footer line for private uploads; Burst row `Pass · F4 Info` | GF §7, §15.5 |
| new `GF156Pairing` (p2-outside) | Pairing transcript (y, N, reconfirm after claim), PV-14 config confirmation, first-hosted-run line, lint warnings | GF §15.6 |

## Boards for A04 (account security, rev. 3, decision 44)

Every board: light first, then dark for the boards marked ◐; desktop 1440 × 900 and mobile 390 × 844 unless the board says otherwise. Data from RD §7 `account_security` and the copy above. Self-host variants use `hooks.ferrymarket.example`.

| # | Board | Frames | Spec |
| - | ----- | ------ | ---- |
| 1 | `S02SignIn` ◐ | Default (anonymous), guest note, conditional-UI autofill open on the email field, passkey waiting, desktop + mobile | S-02 |
| 2 | `S02SignInStates` | Wrong email or password, throttled, field errors, passkey cancelled, passkey didn't work, browser without WebAuthn, instance on HTTP (self-host), OAuth cancelled / error, invite only | S-02 |
| 3 | `S24Register` | Default, creating, field errors, invite only, closed; mobile default | S-24 |
| 4 | `S25VerifyEmail` | Check inbox (with resend countdown), confirmed in another browser, expired | S-25 |
| 5 | `S26Password` | Forgot, sent, reset form, **1.x Set a password**, saved flash (normal, 1.x, RC-2), expired; mobile reset form | S-26 |
| 6 | `S27TwoFactor` ◐ | Security key panel (idle, waiting), authenticator code, recovery code, other-ways list; desktop + mobile | S-27 |
| 7 | `S27TwoFactorStates` | Key cancelled, key didn't work, wrong code with 2 attempts left, code reused, wrong recovery code, 5 attempts used, **paused**, browser without WebAuthn, self-host footer | S-27 |
| 8 | `S28ConfirmAuth` | Passkey + password + code methods; wrong password; desktop + mobile | S-28 |
| 9 | `S17ASecurity` ◐ | Maya's state (password, GitHub, MacBook Touch ID, YubiKey 5C NFC, authenticator on, 8 codes); desktop + mobile list | S-17A |
| 10 | `S17ASecurityStates` | No second factor, no keys, Pixel 8 never used, **Old YubiKey blocked**, 10-key limit, browser without WebAuthn, instance on HTTP, 2 codes left, 0 codes, recovery lock | S-17A |
| 11 | `S17BAddKey` | Passkey: explain, waiting, name, done; security key: explain, name; errors (cancelled, already registered, not allowed, name invalid, limit); mobile sheet | S-17B |
| 12 | `S17BRemoveRename` | Rename; remove normal; remove last second factor; refused last way to sign in; review blocked key | S-17B |
| 13 | `S17BTotp` | Scan (QR + setup key), confirm, wrong code, reused code, turn off | S-17B |
| 14 | `S17BRecoveryCodes` | Show once (first enrol title), regenerate confirm, close-without-saving confirm; mobile sheet | S-17B |
| 15 | `S17BPasswordLinked` | Set / change password, errors; connect Google, disconnect GitHub, already linked elsewhere, locked during recovery | S-17B |
| 16 | `S29Recovery` | Request, sent, confirm, waiting (72 h), waiting extended (7 days), link reopened, ready (set password), link doesn't work; mobile request + waiting | S-29 |
| 17 | `S00RecoveryBanner` ◐ | Capture stream with the recovery banner (72 h and RC-2 variants), cancel dialog, cancelled flash, cancelled-by-sign-in flash; mobile | S-00, S-29 |
| 18 | `S30RecoveryReview` | Review page with agent, CI token and security log; mobile | S-30 |
| 19 | `S31Emails` | HTML: `key-added`, `recovery-started`, `launch-1x`; plain-text sheet for the other 19 | S-31 |
| 20 | `S19AdminOverviewRp` | RP ID row (on, with warning) and off state (HTTP / IP) | S-19 |
| 21 | `S17Account` (redraw) | Account list with Security first; tokens page with `run` locked state | S-17 |

**21 boards** (4 dark variants: boards 1, 6, 9, 17).

## Open for the PO

| # | Question | Recommendation |
| - | -------- | -------------- |
| O-SS-1 | **Show the CI exit code on web runs?** EXIT-2 makes the UI store it; showing "CI exit 1" in the verdict meta and the Runs list helps Sam but adds a number Maya may not need. | Show it, in `ink-muted`, in the verdict meta and as the last Runs column. It is the one fact that ties the web to CI. |
| O-SS-2 | **"Since Run #3: 3 fixed" line** (S-11) built from finding fingerprints (FND-2). Not in any approved scope. | Keep it PROPOSED and out of the MVP frames; A04 draws it on one variant only. Decide after the proxy validation. |
| O-SS-3 | **Strip with 9 cells at 111 px.** Line 3 readings such as `expected 4xx · got 200` fit at 11 px Martian Mono, but `△ F3 · 11 840 ms` is tight. | A04 checks the 9-cell Run #4 frame first; if a reading overflows, line 3 shortens to the value and the full text stays in the accessible name. No PO action unless it fails. |
| ~~O-SS-4~~ | **Moot:** PO-I decided, no email link (decision 46). | — |
| ~~O-SS-6~~ | **Closed, decision 46:** PO-H option B, PO-I and PO-J are decided as A09 advised. | — |
| O-SS-7 | **Launch notice and 1.x copy** (`launch-1x`, S-26 1.x variant, S-02 footer line) says inboxes and events didn't come across. | PO approves the text and the sending (decision 41). |
| ~~O-SS-8~~ | **Closed by A05 revision 5:** unblock, connect/disconnect, email change, review page and data, `/confirm-auth` for OAuth-only accounts, link pages, sessions, password values and lifetimes are in contracts §3.4 and drawn in rev. 4. Left for A05: O-SS-9 and the small items there. | — |
| O-SS-9 | **Contract needs found in rev. 4 (for A05). Rev. 5: (a) is answered by A05 revision 6 (U-20) and DL-4 (decision 47); (h) is answered by decision 47 (EC-1: the old address confirms; no revert link); the rest stay.** (a) **Account deletion** (U-19): route and method (`DELETE /account`?), recent authentication with a second factor on 2FA accounts or not, immediate or with a grace period, `422` codes for the recovery lock and the last self-host admin, the cascade (projects, captures, runs, share tokens, fixtures, agents revoked, CI and personal tokens, sessions, pending email change, recovery rows), what `audit_log` keeps, event `AccountDeleted` and the `account-deleted` email sent before the row goes, and when the address can be used again. (b) Sign-in throttle numbers (attempts, window, block) so S-02 can be checked; the copy needs only `Retry-After`. (c) Security-log **action codes** (schema §7 allow-list names them in prose only); A03 proposes the codes in RD `SECURITY_LOG_COPY`. (d) A prop telling password forms the limit (`passwordMax: {unit: 'chars'\|'bytes', value}`) for the bcrypt fallback. (e) Answer of `DELETE /account/sessions/{id}` for an unknown or already revoked session (A03 wants a fresh `SecurityView`, not an error). (f) Link-page props for `/email-change/{token}`: signed out, signed in to the same account, signed in to another account. (g) `currentPassword` never required after recent authentication. (h) What the old address's owner can do after `email-changed` if the change wasn't theirs (no route; A09 to weigh in). | A05 adds or rejects each in revision 6; A03 updates S-17, S-17A/B, S-02, S-32 |
| O-SS-5 | **Guest sees the server target disabled rather than hidden.** A05's contract omits it from `targets` for guests; IA7 says don't hide a capability silently. | Show it disabled with "Server runs need an account. Sign in to use them." The UI renders it without a prop; no contract change. Hidden when the kill switch is off, because that is an operator state, not a user limit. |

## Boards for A04 (account security, rev. 4, A05 revision 5)

Same rules as the rev. 3 list: light first, dark for boards marked ◐; desktop 1440 × 900 and mobile 390 × 844 unless the board says otherwise; data from RD §7 `account_security` (change 28) and the copy above; nothing invented: if a frame needs a value that isn't here, ask A03. Redraws keep their board names.

| # | Board | New or redraw | Frames | Spec |
| - | ----- | ------------- | ------ | ---- |
| 1 | `S17ASecurity` ◐ | redraw | Seven sections: "Password and email", keys, authenticator, codes, linked, **Where you're signed in** (3 sessions, this browser first), **Recent security activity** (8 rows); desktop + mobile lists | S-17A |
| 2 | `S17ASecurityStates` | redraw | Add: email change pending, key blocked by user handle, **Spare YubiKey blocked twice**, recovery open (Connect enabled, Disconnect and Change email disabled), recovery completed with the review note, only this browser, no recent activity | S-17A |
| 3 | `S17BEmail` | new | Form, form replacing a pending change, sending, sent (uniform), invalid, same as now, second factor required, lock; mobile sheet | S-17B `email` |
| 4 | `S17BSignOut` | new | Sign out one browser, sign out 2 other browsers, toasts, already-signed-out toast; mobile sheet | S-17B `signout` |
| 5 | `S17BRemoveRename` | redraw | Review blocked key: counter, user handle, blocked twice (no Unblock), unblocked toast, `unblock_not_allowed` | S-17B `blocked` |
| 6 | `S17BTotp` | redraw | Turn off, normal and last second factor, with the exact titles, buttons and toasts | S-17B `totp` |
| 7 | `S17BPasswordLinked` | redraw | Password helper 12–128 and bcrypt variant; errors too short, too long, too common, is email, mismatch; set and change toasts; connect Google (toast), `identity_in_use` banner, disconnect GitHub dialog, Disconnect disabled during the lock with Connect enabled | S-17B `password`, `linked` |
| 8 | `S28ConfirmAuth` | redraw | New action strings (sample: "change your email", "sign out other browsers"); second-factor mode (no password); OAuth-only GitHub and Google with their notes; `reauth_mismatch`; cancelled; recovery-cancelled flash; desktop + mobile | S-28 |
| 9 | `S25VerifyEmail` | redraw | Check inbox (60 minutes), **link page with Confirm email**, confirmed in another browser, link doesn't work | S-25 |
| 10 | `S26Password` | redraw | Reset form helper and new errors; link doesn't work (reset variant); keep the 1.x and RC-2 frames | S-26 |
| 11 | `S29Recovery` | redraw | Sent with 60 minutes, link page without the pending marker, **3b Declined**, finish with the 24 h line, link doesn't work (request and finish variants) | S-29 |
| 12 | `S30RecoveryReview` | redraw | Header with completion time, agents, **two CI tokens across projects**, Revoke dialog, **Revoked** row, What happened (5 rows), lock line; mobile | S-30 |
| 13 | `S32LinkPages` | new | Confirm new email (signed in, confirming, signed out, other account), confirmed flash on S-17A, "This link doesn't work" × 5 link types; mobile confirm | S-32 |
| 14 | `S17DeleteAccount` | new | Page (unticked, ticked), deleting, done flash on landing, recovery lock (open, completed), last admin (self-host); mobile | S-17 `delete` |
| 15 | `S31Emails` | redraw | HTML for `email-change-requested` added; plain-text rows for the 8 new templates and the `key-blocked` user-handle variant | S-31 |
| 16 | `S02SignInStates` | redraw | Throttled frame: "Too many attempts. Try again after 14:58 UTC." | S-02 |
| 17 | `S24Register` | redraw | Helper 12–128; field errors with the A05 password errors; throttled frame as S-02 | S-24 |

**17 boards** (4 new, 13 redraws; 1 dark variant: board 1). `S17Account` keeps its rev. 3 frames; its tokens page lock line now follows `lock.reason` (board 2 copy).

## Boards for A04 (account scope, rev. 5, decisions 46 and 47)

New and changed boards only; the rev. 4 list above still stands for every board not named here. Same rules: light first, desktop 1440 × 900 and mobile 390 × 844 unless the board says otherwise; data from RD §7 `account_security` and the copy above; if a frame needs a value that isn't here, ask A03. Account scope is frozen (decision 47): no other account board changes.

| # | Board | New or redraw | Frames | Spec |
| - | ----- | ------------- | ------ | ---- |
| 1 | `S17BEmail` | redraw | Add the **no-2FA** variant: form with the two-address helper, sent "Check both inboxes" (desktop and mobile sheet), form replacing a pending change. Keep the 2FA frames from rev. 4 | S-17B `email` |
| 2 | `S17ASecurityStates` | redraw | Replace the single "email change pending" frame with four: 2FA (one address), no 2FA none confirmed ("Waiting for both addresses"), new address done, current address done (`✓` glyph, not colour alone) | S-17A section 1 |
| 3 | `S32LinkPages` | redraw | Add: **Confirm the email change** (old address: signed in, confirming, both-done success flash, half-done flash "Confirmed. Waiting for …", signed out, other account, "You already confirmed this"); new-address page with the no-2FA lines; **Delete your account?** (signed in, deleting, signed out, other account); "This link doesn't work" × 6 types (email change covers both addresses; adds account deletion); mobile Confirm email change and Delete your account? | S-32 |
| 4 | `S17DeleteAccount` | redraw | Add **Check your email** (desktop and mobile), sending, and S-28 in second-factor mode for the delete line; keep unticked, ticked, deleting, done flash, recovery lock (open, completed), last admin. The earlier "new, pending A05" label no longer applies | S-17 `delete` |
| 5 | `S31Emails` | redraw | HTML for `email-change-confirm-old` and `delete-confirm`; plain-text rows for the two new templates (32 templates in all) | S-31 |

**5 boards** (0 new, 5 redraws, no dark variants). Still pending A05 and shown in the frames only as the copy above: the deletion link's route name and signed-in requirement, whether a new deletion request replaces the earlier link, whether the page remembers the sent link on reload, and whether the old address still gets its link when the new address is taken.
