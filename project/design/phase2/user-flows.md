# User flows · Webhook Trap 2.0

**Status:** REVIEW_REQUIRED · **Author:** A03 (UX/UI) · **Date:** 2026-11-14 (rev. 2: decisions 38–39, A01 rev. 2; rev. 3: decisions 41 and 44, account security; rev. 4: A05 revision 5, U-10 to U-19) · **Phase:** 2 (Gate 2)
**Binding inputs:** decision 35 (A-7, A-13, A-18, C-1 to C-4, P1 to P11 approved), A01 `scenarios-and-judge.md` (SJ, rule IDs such as `ONCE-4`), A11 `testability-review.md` §5 (TR tests, written `T22`).
**Read with:** `information-architecture.md` (IA), `navigation.md` (NAV), `accessibility-spec.md` (A11Y), `golden-files.md` (GF), `screen-specifications.md` (screens `S-nn`). Data and copy: `../realistic-data.md` / `.json` (RD), regenerated for decision 35. **Copy keys** are JSON paths into RD (`copy.run.findings` = `copy` › `run` › `findings`).

Every value in these flows comes from RD. Where a flow needs copy RD doesn't have, the string is written here and marked **NEW**; it moves into RD with the next regeneration.

---

## 0. How to read a flow

| Part | Meaning |
| ---- | ------- |
| **Entry** | Where the user starts and the IA entry point (`E1`…`E12`) |
| **Pre** | What must be true before step 1 |
| **Steps** | One row per user action or system event. *Screen* names the screen and state (`S-11 running`) |
| **Exit** | How the flow ends: success, and each way out |
| **AC** | Acceptance criteria. Each cites the TR §5 test it maps to (`T22`), an SJ rule, or says **new**: a test A11 should add to the skeleton |

Personas (from `personas.md`): **Maya** (solo backend developer, the dataset's user), a **guest** (Maya before signing in), **Sam** (QA/CI owner reading PRs), **Ops** (self-host admin).

### 0.1 Flow map

```
FL-1 Guest first minute ──► FL-2 Pair agent ──► FL-3 First run ──► FL-4 Open finding ──► FL-5 Run again
        │                                         │   │   │                                   │
        │                                         │   │   └─► FL-14 Baseline rejected (exit 2) │
        │                                         │   └─────► FL-13 Target unreachable         │
        │                                         └─────────► FL-12 Agent lost mid-run         │
        ├──► FL-11 Quota reached (any time)                                                    │
        └──► FL-7 Sign in, session claimed ──► FL-6 Share a run ◄──────────────────────────────┘
FL-8 CI run (no browser)   FL-9 Server-side replay to a public URL   FL-10 Self-host admin

Account security (rev. 3, decision 44):
FL-15 Create account ──► FL-18 Add passkey or key ──► (codes once) ─┐      FL-19 Authenticator app
FL-16 Passkey sign-in                                                ├──► FL-20 Remove / turn off
FL-17 Password or OAuth + second step ──► FL-7 claim                 │      FL-23 Blocked key
        └──► FL-21 Lost every factor: recovery (72 h) ◄──────────────┘      FL-22 Returning 1.x user
S-17A (rev. 4): FL-24 Change email ──► S-32 link page    FL-25 Sign out other browsers    FL-26 Delete account (no 2FA: ──► S-32 link page)
```

---

## FL-1 · Guest first minute, no agent

**Persona:** guest. **Entry:** landing `/`, CTA **Get a URL, no sign-up** (E1). **Pre:** no session cookie; hosted instance with guest mode on.

| # | User does | System does | Screen | Copy keys |
| - | --------- | ----------- | ------ | --------- |
| 1 | Presses **Get a URL, no sign-up** | Form `POST /new`; button label becomes "Creating your URL…" (≤ 400 ms expected) | S-01 `creating` | S-01 copy |
| 2 | — | Creates guest session, workspace, project "Guest session", capture key `k7Qm2xVb9TfL`; redirects to `/p/{projectKey}` (replace) | S-03 `waiting` | `copy.capture.waiting` |
| 3 | Copies the capture URL (button or `c`) | Announces "Copied." | S-03 `waiting` | `copy.capture.waiting.providers.Stripe` |
| 4 | Pastes it into Stripe test mode and triggers a test checkout | Stripe sends #01 to #04 within 1.2 s; each row appears < 1 s after receipt (PERF-1); group `sub_1QH…4drI` forms | S-03 `receiving` | `copy.capture.receiving` |
| 5 | — | No agent, so every row is **Not checked**; the group header says `4 · signatures not checked`; shape **New shape (first capture)** | S-03, S-04 | `copy.signature.no_agent` |
| 6 | Opens #01 (already selected by default: newest group's newest delivery) | Verdict tab: what was read without a secret ("Stripe-Signature v1 header present, timestamp 0.2 s old.") then the no-agent helper; **Prove the handler that receives these** block | S-04 `no agent` | `copy.signature.no_agent` + S-04 copy |
| 7a | Presses **Prove invoice.paid** | → FL-2 | S-10 | — |
| 7b | Presses **See an example run ›** | Opens `/r/example` (P7) in the same tab (push) | S-14 | `copy.errors.example` |
| 7c | Reads Raw / Decoded, copies JSON, downloads bytes | All work without an agent (TT §2.3) | S-04 | — |

**Alternative at step 3:** **Send a test request** posts a sample JSON from the browser to the capture URL. It arrives as #01 with provider Unknown and signature Unsigned. **NEW** button helper: "Sends a small JSON body to your URL from this browser."

**Exit:** Prove (FL-2), example run (S-14), or the user leaves; the session stays until 24 h after URL creation (A-13). Returning to `/` shows **Open your capture** (S-01 `returning guest`).

**AC**
- AC-1.1 With JavaScript disabled, the CTA still creates a session and lands on S-03 `waiting` (TR R69, IA E1). **new**
- AC-1.2 Delivery visible within the arrival target, p95 < 1 s (T6, PERF-1).
- AC-1.3 The selected row never moves while #02 to #04 arrive; "N new" pill counts correctly when scrolled (T7).
- AC-1.4 Not checked shows the header facts that need no secret, and never a ✓ (T12).
- AC-1.5 Raw shows the exact bytes and the raw query string (T1, T2); `Copy as JSON` and `Download raw` work without an agent.
- AC-1.6 The capture URL answers an unknown or expired key exactly like a valid one (T5).
- AC-1.7 Scripted journey landing → first delivery visible < 60 s (PERF-7, R71).
- AC-1.8 Keyboard-only: landing → Get a URL → list → `p` (T82, A11Y §5.6).

---

## FL-2 · Pair the agent with the browser code

**Persona:** guest (or Maya signed in). **Entry:** Prove without an agent (S-10 step 1 expanded), the agent pill (`No agent` → popover **Pair an agent**), or Agent tab (S-06). Signed in: `/agents/pair` (S-08). **Pre:** no agent connected for this workspace. The code is always shown in the browser (P10).

| # | User does | System does | Screen | Copy keys |
| - | --------- | ----------- | ------ | --------- |
| 1 | — | Issues one-time code `7KQ2M-XR9TD` (10 symbols, PO-F), bound to the session (decision 32); countdown starts at the TTL (RD shows `9:41`; TTL value is A-12, A05/A09) | S-10 draft step 1 `not_installed` | `copy.agent.not_installed` |
| 2 | Copies `npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD` | Step 1 switches to `pairing`; pill shows `Pairing…` | S-10 step 1 `pairing` | `copy.agent.pairing` |
| 3 | Runs it in a terminal on the machine with the handler | The agent uses the code, then prints, in this order: `Server     https://webhooktrap.dev` (the origin from its own configuration), `Workspace  Guest session · expires 2026-11-12 13:55 UTC`, and "Join? Only continue if you created this code in your own browser just now. [y/N]" (PO-E, ADR-010 §4 rule 5). The browser switches by itself to **Confirm in your terminal**; the agent gets no jobs yet | S-10 step 1 `awaiting_confirmation`; terminal GF §15.6 | `copy.agent.awaiting_confirmation`, RD `agent.pairing_terminal` |
| 4 | Checks the server line and types `y` | Agent confirms; the browser updates by itself, no reload (< 30 s from paste to Connected, PERF-8, not counting the person's reading time) | — | — |
| 5 | — | Step 1 collapses to `◉ Connected · maya-mbp · agent 0.9.2 · target localhost:3000/webhooks/stripe`; pill `◉ maya-mbp`; the agent re-checks signatures of stored deliveries | S-10, shell | `copy.agent.connected` |
| 6 | — | Toast: "Signatures checked: 4 of 4 valid with STRIPE_WEBHOOK_SECRET on maya-mbp." with **View capture** (no last 4, PO-D) | toast | `copy.agent.signatures_checked` |
| 7 | Continues the draft | → FL-3 | S-10 | — |

**Branches**

| Condition | What the user sees | Copy keys |
| --------- | ------------------ | --------- |
| 1 minute left on the code | Countdown text gets "Expires soon"; announced once (A11Y §6.2) | `pairing.countdown` channel |
| Code expired | "Code 7KQ2M-XR9TD expired." + **Get a new code** (draft choices kept, G13, G15) | **NEW** string as written |
| Person types `N` (or anything but `y`) at the prompt | Terminal: "Not joined. This agent is not paired, and the code is used up."; browser: `copy.agent.pair_declined` + **Get a new code** | `copy.agent.pair_declined`, RD `agent.pairing_terminal_no` |
| Origin printed by the agent differs from the server's answer | The agent refuses before the prompt (A05 §5.1); browser stays on **Waiting for your agent…** until the code expires | — |
| Non-interactive terminal, guest workspace | Pairing fails: a guest workspace always asks (`--yes` is for account workspaces only) | GF `pair-non-tty-guest` |
| Agent older than the minimum | Step 1 warning, Run disabled | `copy.agent.too_old` |
| No Stripe secret on the agent | Step 1 shows **Needs secret** and the command; Run disabled (SIGN-2: Stripe signs a timestamp, so nothing can be sent) | `copy.run.needs_secret` |
| Guest pairs a second agent | The new agent replaces the old (A-13) when its `y` is received, not before; the old terminal is told; toast **Agent replaced** | `copy.agent.replaced` |
| On a phone | "Pair the agent from your computer" with the command, **Copy command** and **Copy link** (TT GM3); the 10-symbol code stays on one line | TT §2.2 GM3 |

**Exit:** Connected (→ FL-3), or the user leaves with the draft kept in the URL (`/runs/new?from=01,03,04`).

**AC**
- AC-2.1 The code is single-use, expires at its TTL and binds to the guest session (T66).
- AC-2.2 Paste → Connected < 30 s on a 50 Mbit/s link (PERF-8).
- AC-2.3 Agent version below minimum → "Agent update needed", Run disabled (T67).
- AC-2.4 The step turns Connected without a reload and without moving focus (A11Y §6.3 rule 3). **new**
- AC-2.5 Countdown announced at 1:00 and 0:00 only; Get a new code keeps the draft (AX-19).
- AC-2.6 A second guest agent replaces the first; the first gets `Disconnected` (A-13). **new**
- AC-2.7 No secret value, and no part of one (no last 4), crosses to the server in any pairing or check message (T18, PO-D).
- AC-2.8 The agent prints its own server origin before the workspace label and asks `[y/N]`; until `y`, every job, capture and verdict call answers `needs_reconfirm` (PO-E). **new**
- AC-2.9 Codes are 10 Crockford symbols, accepted with or without the hyphen and in any case (PO-F). **new**

---

## FL-3 · First run, Once → findings

**Persona:** Maya. **Entry:** S-10 draft with `◉ Connected`. **Pre:** RD group `sub_1QH…4drI`, events #01 #03 #04 selected by Prove on the group; `webhooktrap.yml` with probe `orders.count` and reset (RD §4).

| # | User does | System does | Screen | Copy keys |
| - | --------- | ----------- | ------ | --------- |
| 1 | Reads the draft | Steps: Agent ✓ · Target `http://localhost:3000/webhooks/stripe` (checked against the allow-list) · Events #01 #03 #04 · Scenarios: all 9 checked, **Once locked** (PLAN-2) · Verdict level: probe and reset from `webhooktrap.yml` · Summary "9 scenarios selected · 17 deliveries · about 50 s" | S-10 `ready` | `copy.run.draft` |
| 2 | Unchecks **Unsigned** | Summary updates live: "8 scenarios selected · 16 deliveries · about 45 s" | S-10 | `copy.run.draft_run3` |
| 3 | Presses **Run** (`⌘↵`) | First run on this agent and target → side-effects dialog (NAME-4) | S-12 `side-effects` | `copy.run.side_effects` |
| 4 | Presses **Run anyway** | Acknowledgement stored on the agent for (maya-mbp, localhost:3000). URL replaces `/runs/new` with `/r/run_01M75F1M56RSH0CVKCTFX4FQWC`; focus to the verdict line | S-11 | — |
| 5 | — | If the agent hasn't accepted within 300 ms: `Queued · Waiting for maya-mbp to accept the run…` (T69). First run of this `webhooktrap.yml` version or to this target: the agent shows the probe and reset commands, the target, and the PV-14 sentence with each entry tagged (`orders.count  count`) in its terminal and waits for `y`; the run shows `copy.run.queued_confirm` until then (PRB-11, A05 decline `needs_confirmation`). This closes O-FL-1 | S-11 `queued`; terminal GF §15.6 | `copy.run.queued`, `copy.run.queued_confirm` |
| 6 | — | Preflight: reset, then the probe stability check (PRB-8). Verdict line "Running · preparing: reset and probe check" (**NEW**) | S-11 `running` | — |
| 7 | — | Rows D1 to D16 fill in send order; each shows sent → response → state → result. Findings appear in the findings column **by title, without numbers** (FND-6): "▲ High · Duplicate delivery created a second order" after D5 | S-11 `running` | `copy.run.running` |
| 8 | Scrolls or selects something | Auto-follow stops; **Follow live** appears (T71); `.` freezes the view (G16) | S-11 | — |
| 9 | — | Run ends at 43.2 s. Findings renumber once, final: F1 Critical, F2 High, F3 Medium, F4 Info. Verdict "3 findings · 1 Critical · 1 High · 1 Medium · 1 Info". F1 opens with D12 selected; focus stays on the verdict line | S-11 `findings` | `copy.run.findings` |

**What the user sees per scenario** (RD §4.1, the strip): Once pass · Twice ▲ F2 High `expected 1 · got 2` · Shuffled pass `○ F4 Info · 0 → 1` · Late pass `400 · 41 ms` · Retry after error ▲ F2 High `△ F3 · 11 840 ms` · Tampered ■ F1 Critical `expected 4xx · got 200` · Burst pass `○ F4 Info · 0 → 1 (final)` · Slow budget △ F3 Medium `10 420 ms · +420`.

**Exit:** finished run (→ FL-4), or one of FL-12, FL-13, FL-14. The server stores exit code 1, the code the CLI would have returned with defaults (EXIT-2).

**AC**
- AC-3.1 Buggy reference handler at Level 1 + probe: findings are exactly `accepts_tampered` Critical, `duplicate_changed_state` High (Twice + Retry merged), `over_budget` Medium (Retry + Slow budget merged), `probe_gap` Info (T19 as amended by SJ §5.2 and FND-3; TR's "Late High" expectation applies only to a handler that accepts stale timestamps).
- AC-3.2 Twice's second delivery leaves ≥ 500 ms and ≤ 600 ms after the first **completes** (T23, TWICE-1, PLAN-3).
- AC-3.3 Shuffled sends reverse capture order: D6 #04, D7 #03, D8 #01 (SHUF-1; replaces T24's seeded permutation).
- AC-3.4 Late's timestamp is now − 360 s (T25); Tampered changes `data.object.amount_paid` and the diff shows on D12 (T27); Retry's attempt 2 is sent even after a 2xx (T28); Burst spread ≤ 20 ms (T30).
- AC-3.5 Merging and numbering: F2 evidence D4 D5 · D10 D11, F3 D10 · D16, F4 D6 · D15 (PRB-12a); order F1…F4 (T34, T35).
- AC-3.6 "3 findings" counts Critical + High + Medium only (RUN-3).
- AC-3.7 Rows appear in send order; findings insert by severity without reordering rows; no F-number shows before the run ends, on any surface (T70, FND-6, FND-6a).
- AC-3.12 The run page carries the provenance line "Run by maya-mbp from the web app" from Queued on, and F2's header reads "Level 2 · judged on maya-mbp" (RUN-8, RUN-9). **new**
- AC-3.13 No hosted page or prop of the run contains the probe's `sql:` text, the reset command or its output (PV-8; A09 T1 canary). **new**
- AC-3.8 Queued shows only after 300 ms (T69); run start p95 < 1 s (PERF-3); row update p95 < 500 ms (PERF-4).
- AC-3.9 The side-effects dialog appears once per (agent, target) and before the first run that sends a duplicate (T73).
- AC-3.10 The default run of 3 Stripe events completes in ≤ 60 s against the PERF-6 handler (PERF-6).
- AC-3.11 The CLI equivalent renders GF §15.2 byte for byte (T52).

---

## FL-4 · Open a finding and inspect deliveries

**Persona:** Maya, or Sam from a PR link. **Entry:** S-11 finished; or a deep link `/r/{runId}/F2` (E5, E7) or `/r/{runId}/D5`. **Pre:** Run #3 finished.

| # | User does | System does | Screen | Copy keys |
| - | --------- | ----------- | ------ | --------- |
| 1 | Presses `]` (or clicks F2) | F2 expands in place below F1 (order kept); F1 collapses; selection moves to D5, the first **offending** evidence (FND-5); inspector opens with **Compare with D4** on | S-11 `findings`, F2 open | `run.findings[1]` |
| 2 | Reads the State row | "Expected 1 · got 2", "before 1 → after 2 · D4 left it at 1"; caption "Same request, same response, a different state." (both 200, identical bytes) | S-11 inspector | `run.findings[1].caption` |
| 3 | Clicks evidence **D11** | Inspector on D11, compare with D10; no caption (D10 took 11 840 ms, so the responses differ in the way that matters) | S-11 inspector | — |
| 4 | Presses `]` → F3 | D10 selected: "11 840 ms, 1 840 over the 10 s budget · Webhook Trap default"; Likely cause (LC-4) | S-11 | `run.findings[2]` |
| 5 | Presses `[` twice → F1 | D12 selected: request diff `data.object.amount_paid 2400 → 0`; signing "signed for the original body, fresh timestamp" | S-11 | `run.findings[0]` |
| 6 | **Copy fix** / **Copy finding as JSON** | Copies the Express snippet / one `findings[]` element (SJ §13); "Copied." | S-11 | — |
| 7 | Selects the **Burst** strip cell | Grid scrolls to D13–D15; state shows `—` per row and the final reading `0 → 1 (final)` on the scenario row | S-11 | `run.scenario_results[6]` |

**Mobile 390:** verdict → findings (tap expands, evidence chips) → strip 2 × 4 → deliveries list → **D5** opens the inspector sheet (push, `/r/{runId}/D5`) with Request | Response tabs and the Compare switch (NAV §2).

**Exit:** the user fixes the handler (→ FL-5), saves fixtures (S-12 fixtures preview), or shares (→ FL-6).

**AC**
- AC-4.1 `/r/{runId}/F2` opens F2, selects D5 and turns Compare on against D4 (NAV §5 rule 1). **new**
- AC-4.2 Every value in the graph exists in the grid (AX-18, R65), 16 of 16 rows; Burst's final reading is on its scenario row.
- AC-4.3 Treegrid, grid, toolbar and findings list follow the ARIA patterns (T83).
- AC-4.4 Greyscale still separates Critical (■) from High (▲) by shape (T86).
- AC-4.5 Unknown D/F on a valid run keeps the run and shows "D22 isn't in this run (16 deliveries)" (NAV §5 rule 2). **new**
- AC-4.6 `Copy finding as JSON` equals the matching `findings[]` element of `--json` (T60).

---

## FL-5 · Run again

**Persona:** Maya after fixing F1 to F3. **Entry:** S-11 **Run again** (`⌘↵`), or **Edit scenarios** → S-10. **Pre:** agent connected; side effects already acknowledged for this target.

| # | User does | System does | Screen | Copy keys |
| - | --------- | ----------- | ------ | --------- |
| 1 | Presses **Edit scenarios** and re-checks **Unsigned** | Draft prefilled with Run #3's target, events and scenarios (G13): "9 scenarios selected · 17 deliveries · about 50 s" | S-10 | `copy.run.draft` |
| 2 | Presses **Run** | New run, **push** to `/r/{Run #4 id}` (back returns to Run #3). No side-effects dialog (already acknowledged) | S-11 `running` | — |
| 3 | — | Run #4 ends: "Passed · 1 Info"; the strip has 9 cells, all `▬ Pass`; Twice and Retry show `expected 1 · got 1` | S-11 `passed` | `copy.run.passed` |
| 4 | Reads the verdict | "9 of 9 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: subscription state, which no probe reads · side effects outside your database, such as emails." (RUN-7) | S-11 | `copy.run.passed` |
| 5 | Presses **Save as fixtures** | Preview through the agent (→ S-12 fixtures) | S-12 | `copy.fixtures.*` |

**Shortcut:** **Run again** without editing reuses Run #3's choices exactly (8 scenarios, 16 deliveries).

**Exit:** Passed, or new findings (back to FL-4). Exit code 0 stored (EXIT-1).

**AC**
- AC-5.1 Fixed reference handler: no finding ≥ Medium; status Passed with the RUN-7 "Not checked" line (T20).
- AC-5.2 Run again and Edit scenarios keep target, events and scenarios (G13). **new**
- AC-5.3 Run again is a new history entry; back returns to the previous run (NAV §4.1). **new**
- AC-5.4 For a guest, the run counts toward the 10 once it gets past Once (T77 as amended by A-13).

---

## FL-6 · Share a run

**Persona:** Maya; viewer Sam. **Entry:** S-11 **Share**. **Pre:** run finished (any final status). Runs are private by default (P2); `/r/{runId}` opens only for the owner and project members, whatever the share state (PO-A).

| # | User does | System does | Screen | Copy keys |
| - | --------- | ----------- | ------ | --------- |
| 1 | Presses **Share** | Share dialog: "Shared as Maya Lindqvist"; visibility **Only people in this project** (default) / **Anyone with the link** | S-12 `share` | `copy.share.title`, `.private`, `.link` |
| 2 | Chooses **Anyone with the link** | The server issues a share token; the dialog shows "People with the link see probe names and counts." (PV-14), **Hide bodies** (off), **Include probe values** only if an entry is under `show:` (Run #3: none), and the link `https://webhooktrap.dev/s/q7Vf2LmX9pK4wT8nR3bZcD` | S-12 | `copy.share.*` |
| 3 | Presses **Copy link** | "Share link copied. Anyone with the link can see this run." | S-12 | `copy.share.copied` (`ui.confirm`) |
| 4 | Sam opens the link (not signed in) | `/s/q7Vf…` renders the read-only run, minimal shell, "Shared by Maya Lindqvist", the provenance line; bodies with scrub-rule fields replaced by `[scrubbed]`; headers per the allow-list; probe readings as counts (codes for anything else) | S-13 | `copy.errors.read_only`, `copy.provenance.agent_web` |
| 5 | Sam presses **Sign in to run it again** | `/sign-in?next=/s/…`; after sign-in Sam still sees the read-only view (not a member) with "Run it on your own agent" pointing to Prove from his own captures | S-02 → S-13 | — |
| 6 | Maya presses **Create a new link** | A new token; the old link answers like an unknown one at once; toast "New link ready. The old link now shows Not found." | S-12 | `copy.share.rotated` |
| 7 | Maya turns sharing off | The token is deleted; the link answers exactly like an unknown run (IA5). Sharing again later issues a new token | S-12 | `copy.share.stop`, `copy.errors.not_found_run` |

**Include probe values (owner, account):** visible only when an entry was sent under `show:` (states sheet example `subscriptions.status`). Ticking it lets the share page receive those values; unticked, the share page receives codes (PV-7).

**Guest:** sharing works; no Include probe values tick; the link dies with the session (24 h from URL creation) **and on claim** (sign-in voids guest share tokens, A05 domain model).

**CI uploads** are private (PO-C): `webhooktrap test --upload` prints only the members' `/r/{runId}` link; `--upload --share` also prints `share  https://webhooktrap.dev/s/…` (GF §15.6). The PR comment links to the share URL only with `--share` (GF §7).

**AC**
- AC-6.1 A non-shared run, an unknown run, someone else's `/r/{runId}` and a rotated or stopped `/s/{token}` render the same "Not found" page, same status code and body length class (IA5, PO-A). **new**
- AC-6.2 Shared view never shows a value matched by the pack's scrub rules; with Hide bodies on, no body bytes are in the HTML or props. **new**
- AC-6.3 Shared view has no focusable control that does nothing (A11Y §9); mobile share view passes AX-13.
- AC-6.4 After guest expiry or claim the link shows "Not found" with "Guest runs are kept for 24 hours" (T75).
- AC-6.5 The share page and its JSON never contain a shown (`show:`) value unless the owner ticked Include probe values; a guest run never offers the tick (PV-7, A09 T8). **new**
- AC-6.6 Share pages send `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex`; the run id is never enough to open a run without membership (PO-A). **new**

---

## FL-7 · Sign in and claim a guest session

**Persona:** guest becoming Maya. **Entry:** guest meter **Sign in to keep**, or **Sign in** in the shell. **Pre:** guest session with deliveries or runs or an agent.

| # | User does | System does | Screen | Copy keys |
| - | --------- | ----------- | ------ | --------- |
| 1 | Presses **Sign in to keep** | `/sign-in?next=/p/{projectKey}` | S-02 | S-02 copy |
| 2 | Chooses GitHub, Google, a passkey, or email + password (rev. 3; email link dropped) | OAuth round trip or passkey ceremony; with 2FA on, password and OAuth go through S-27 first (FL-17); paste and password managers allowed, no puzzle (G14) | external or S-27 → S-02 | — |
| 3 | — | **Automatic claim** in one transaction (P11): project, deliveries, runs and the paired agent move to the user's workspace; guest share tokens are voided; the agent is set to `needs_reconfirm` and its target approvals are cleared; the guest cookie is invalidated | — | — |
| 4 | — | Lands on the **same** `/p/{projectKey}`; meter replaced by the project switcher "Stripe project"; toast `copy.guest.claimed` (mentions share links and the terminal); pill `maya-mbp · confirm in terminal` | S-03 | `copy.guest.claimed`, `copy.agent.needs_reconfirm` |
| 5 | Answers `y` in the agent's terminal (it printed the server, the new workspace "Maya Lindqvist (maya@brindlecoffee.example)" and `[y/N]`) | Agent back to Connected. The next run to `localhost:3000` asks for the target approval and the side-effects acknowledgement again (NAME-4, ADR-010 §6.3) | terminal GF §15.6; shell | RD `agent.reconfirm_terminal` |

**Branches:** empty guest session (0 deliveries, 0 runs, no agent) → discarded, user lands on their last project or `/projects/new` (P11). Already has projects → the claimed one is added and named "Stripe project" (rename in Settings). Sign-in fails → S-02 shows the error under the button, guest session untouched.

**AC**
- AC-7.1 Claim moves deliveries, runs and pairing in one transaction; the old guest token is rejected afterwards (T78).
- AC-7.2 App URLs and the capture URL are unchanged after the claim (IA §9.1); guest share links stop working and must be re-shared (A05). **new**
- AC-7.6 After a claim the agent runs nothing until the person confirms in its terminal; runs started meanwhile stay Queued with `copy.run.queued_confirm` (PO-E). **new**
- AC-7.3 An empty session is discarded, not claimed (P11). **new**
- AC-7.4 The workspace belongs to exactly one principal throughout (T79).
- AC-7.5 `?next=` restores the exact deep link, including `/F2` (NAV §4.1).
- AC-7.7 With 2FA on, the claim happens only after S-27 succeeds, in the same browser session; a reset or verification link opened elsewhere never claims (TM-12, A09 R-N). **new**

---

## FL-8 · CI run with exit codes

**Persona:** Sam. **Entry:** a CI job running `webhooktrap test --fail-on high` against the fixtures Maya saved (`webhooktrap/fixtures/stripe/…`, `webhooktrap.yml`). No browser, no agent pairing: the CLI sends from the CI machine to the service under test on loopback. **Pre:** `STRIPE_WEBHOOK_SECRET` in the CI environment.

| # | System does | Output | Ref |
| - | ----------- | ------ | --- |
| 1 | Validates the definition: flags, `webhooktrap.yml` schema, `budget:` (BUD-2), one provider (DEF-1), delivery cap (CAP-1), probe templates (PRB-4) | On error: one message, nothing sent, **exit 3** | GF `usage-error`, `config-error-budget`, `over-cap`, `two-providers` |
| 2 | Runs the plan; non-TTY, append-only; rows inside `::group::Deliveries · 16` when `GITHUB_ACTIONS=true` | GF §15.2 rows | T56, T57 |
| 3 | Prints the summary and one annotation per finding | GF §15.2 summary, §15.3 | T52, T57 |
| 4 | Prints the last line and exits | `Failed: 2 findings at High or above (F1 Critical, F2 High). F3 and F4 don't fail this job.` → **exit 1** | EXIT-1 |
| 5 | With `--upload` and a CI token (`github-actions`, read from `WEBHOOKTRAP_TOKEN` only) | Uploaded **privately** (PO-C): `report  https://webhooktrap.dev/r/…` (opens for project members only) and "Uploaded privately. Add --share to get a link anyone can open."; the run appears in Runs with source `CI · github-actions` and provenance "Uploaded from CI (token github-actions)"; the PR comment is edited in place | T58, GF §7, §15.6 |
| 5b | With `--upload --share` | Also prints `share   https://webhooktrap.dev/s/…`; the PR comment's links use the share URL | GF §7, §15.6 |

**Exit codes the job can end with (EXIT-1, top match wins):**

| Exit | When | Last line (GF) |
| ---- | ---- | -------------- |
| 3 | Run definition invalid; nothing sent | the error message |
| 2 | Couldn't start, Aborted, or any scenario `unreachable`, **even if findings were proven** (C-2) | `Incomplete: …` |
| 1 | A finding ≥ `--fail-on` (default `high`), or Inconclusive with `--fail-on-inconclusive` | `Failed: …` |
| 0 | Otherwise: Passed, Inconclusive without the flag, findings below `--fail-on` | `Passed …` / `Inconclusive (not passed) …` |

**AC**
- AC-8.1 Exit-code matrix 0/1/2/3 with `--fail-on` and `--fail-on-inconclusive` (T37; GF §11 cases).
- AC-8.2 A run that is both incomplete and has a Critical finding exits 2, and the log still prints the finding (C-2, GF `incomplete-with-findings`). **new**
- AC-8.3 PR comment heading says "check failed" exactly when the exit code isn't 0 (EXIT-2).
- AC-8.4 No line over 100 columns except workflow commands (T54); NO_COLOR and non-TTY remove ANSI (T55).
- AC-8.5 `webhooktrap test` from fixtures makes no non-loopback connection (T61).
- AC-8.6 `--json` validates against the schema; MCP `get_run` returns the same findings (T59, T60).
- AC-8.7 Without `--share` an uploaded run has no share token; nothing printed to the CI log opens the run for a non-member (PO-C). **new**
- AC-8.8 The CI log never prints the token value; Level 2 findings in the upload read "judged in CI" (RUN-9). **new**

---

## FL-9 · Server-side replay to a public URL (P6)

**Persona:** Maya, **signed in**, testing the staging deployment of the Shopify handler without an agent. **Entry:** Prove on group **Order #1042** (#05 `orders/create`, #06 `orders/paid`) → S-10. **Pre:** hosted; an account (guests always run through the agent, PO-B, RUN-11); the operator's kill switch for server runs is on (HD-10); target `https://staging.brindlecoffee.example/webhooks/shopify` (RD `examples.public_target`).

| # | User does | System does | Screen | Copy keys |
| - | --------- | ----------- | ------ | --------- |
| 1 | In **Target**, chooses **From Webhook Trap servers (public URLs only)** | Agent step becomes "Not needed for a server run"; Verdict level shows "Transport only: the probe runs on your machine" | S-10 `server target` | **NEW** strings as written |
| 2 | Enters the staging URL | Server resolves it; every address public → accepted. A private address (`http://10.0.4.12:3000/…`, RD `examples.private_target_refused`) is refused: "This address is private. Server runs only reach public addresses. Use the agent for private and local targets." (**NEW**) | S-10 | — |
| 3 | Reviews scenarios | Shopify signs no timestamp, so the server can send with the original signature (SIGN-2). **Late** `n/a · Shopify signs no timestamp`. **Twice** and **Retry after error** `n/a · Not verified for Shopify yet` until DUP-1 is cited (DUP-2). **Burst** runs: two different events, so no duplicate header is needed. Budget `5 s · Shopify limit`. Result: Once, Shuffled, Tampered, Unsigned, Burst, Slow budget; 2 + 2 + 1 + 1 + 2 + 1 = 9 deliveries. Row copy (what, why, Tampered `line_items[0].quantity 2 → 0`, budget) from RD `examples.shopify_server_draft`; Shopify citations `[pending A01]` | S-10 | `copy.scenario_reasons.na_*`, RD §5.1 |
| 4 | Presses **Run** | The server runner sends; Level 1 only; no reset, so the run is order-dependent; provenance "Run by the Webhook Trap server runner" | S-11 | `copy.run.order_dependent`, `copy.provenance.server_public` |
| 5 | — | Typical end: Inconclusive (`needs_probe`) or findings from status and latency only | S-11 | `copy.scenario_reasons.needs_probe` |

**Branch:** a Stripe or Standard Webhooks group with the server target → Run disabled: "Stripe signs a timestamp, so a server run needs a secret. Use the agent." (**NEW**; IA §5.4).

**AC (all new)**
- AC-9.1 The server target refuses loopback, private, link-local and metadata addresses after resolution, and pins the resolved address for the run (TGT-5 equivalent; ADR-005 SSRF rules).
- AC-9.2 Scenarios that need re-signing are `n/a` with their reason, never silently hidden (IA7).
- AC-9.3 A server run never shows a State column value; the verdict says Level 1.
- AC-9.4 Guests never get a server run: the option shows disabled with "Server runs need an account. Sign in to use them."; with the kill switch off, the option isn't offered and the API answers `server_runs_disabled` (PO-B, HD-10). Replaces the earlier guest rule.

---

## FL-10 · Self-host admin (P8)

**Persona:** Ops, a signed-in user with the instance-admin role on a self-hosted instance. **Entry:** account menu › **Instance admin** → `/admin`. **Pre:** self-host; `/admin` is 404 on hosted.

| # | User does | System does | Screen |
| - | --------- | ----------- | ------ |
| 1 | Opens **Instance admin** | Overview: version, health (database, queue, broadcast), storage; first-run checklist (sign-up policy, guest mode, server replay off, limits) | S-19 |
| 2 | **Access**: sets sign-up to **Invite only**, guest mode **Off** | Saved; summary at top "Saved. New sign-ups need an invite." (**NEW**) | S-20 |
| 3 | **Limits**: retention 30 days, body limit 5 MB, delivery cap per run 50 | Validates ranges; the cap can be lowered, not raised above 50 (CAP-1) | S-22 |
| 4 | **Server replay**: public **On**; private stays **Off** | Saved | S-23 |
| 5 | Turns private **On**, adds `10.0.4.0/24` and grants the permission to one user | Confirm dialog names the risk: "The server will send to these addresses on behalf of any user of this instance." → **Turn on**. Only users granted the permission get the private target (PO-G); grants are audited | S-23 |
| 6 | **Users**: disables one account | Confirm dialog naming the user → disabled; their data is never shown | S-21 |

Secrets, Agents, Packs and Audit are listed in the section nav as "Later" items and are not links (IA §4.4).

**AC (all new)**
- AC-10.1 `/admin/*` answers 404 on hosted and for non-admins (IA5).
- AC-10.2 Private destinations are off by default; turning them on needs the confirm dialog and an allow-list entry.
- AC-10.3 Admin never sees another user's deliveries or runs.
- AC-10.4 Forms: labelled fields, errors next to the field and summarised once (A11Y §9).

---

## FL-11 · Quota reached (A-13)

**Persona:** guest. **Pre:** guest session. Values: 24 h from URL creation, 100 stored deliveries, 10 runs, 1 agent.

| Quota | Trigger | What happens | Screen | Copy keys |
| ----- | ------- | ------------ | ------ | --------- |
| Deliveries | 100 stored | Persistent banner; delivery 101 onward is answered exactly like a stored one and not stored; meter `100/100` with "Limit reached" | S-03 `quota` | `copy.capture.quota` |
| Runs | 10 counted | Run disabled in the draft with the reason; Run again disabled on S-11 | S-10 `run quota` | `copy.guest.run_quota` |
| What counts | — | A run counts once it gets past Once. Couldn't start never counts (baseline rejected included). Aborted counts only if a scenario after Once completed | — | — |
| Time, 1 h left | 23 h elapsed | Meter word "Ends in 1 h"; one toast | shell | `copy.guest.ending_soon` |
| Time, last 5 min | 23 h 55 m | Run disabled: "Session ends in 4 min…" | S-10 | `copy.guest.run_too_late` (proposed, IA open O-IA-1) |
| Time, 24 h | expiry | Every app URL of the session renders the expired screen in place; no data in HTML or props | S-18 `expired` | `copy.capture.expired` |
| Agents | second pairing | New agent replaces the old | shell | `copy.agent.replaced` |

**Exit:** sign in (FL-7), save fixtures before expiry, or start a new URL.

**AC**
- AC-11.1 100th delivery stored; 101st answered identically and not stored; banner shows (T76).
- AC-11.2 The 11th counted run is refused with its copy; a Couldn't start run doesn't count (T77 amended: "4th run" → 11th; "accepted runs" → past Once).
- AC-11.3 Expiry at 24 h ± 1 s with a fake clock; the expired screen leaks nothing (T75, R59).
- AC-11.4 An Aborted run with no scenario after Once completed doesn't count (A-13). **new**

---

## FL-12 · Error: the agent disconnects mid-run

**Persona:** Maya. **Data:** RD Run #2. **Pre:** run started with 16 planned deliveries.

| # | System does | Screen | Copy keys |
| - | ----------- | ------ | --------- |
| 1 | D1 and D2 complete; D3 is sent; maya-mbp stops responding at 13:57:04.120 | S-11 `running` | — |
| 2 | One assertive announcement: "Run aborted: agent maya-mbp disconnected during D3." Banner on the run; pill `maya-mbp · offline` | S-11 `aborted` | `copy.agent.lost_mid_run` |
| 3 | Status **Aborted** (`agent_lost`); D1–D2 kept with results; D3 "sent, no result"; D4–D16 "not run"; Once cell `⫽ Stopped · during D3 of 16`, others `Not run` | S-11 `aborted` | `copy.run.aborted` |
| 4 | Actions: **Run again** (enabled when the agent is back); pill popover shows last seen and how to reconnect | S-11 | — |

**Variants:** user abort (`Shift X` → confirm "Stop this run? Completed deliveries and findings are kept." **NEW**) → Aborted `user_abort`; CLI Ctrl-C prints which deliveries completed (EXIT-3); reset failing before a later scenario → Aborted `reset_failed` (`copy.run.aborted_reset_failed`); 15-minute limit → `time_limit`. All exit 2; proven findings are kept and numbered (RUN-6).

**AC**
- AC-12.1 Agent killed during D3 → Aborted, D1–D2 kept, D3 "sent, no result", the rest "not run" (T38).
- AC-12.2 Exactly one `alert` announcement, only for the disconnect (AX-11, T87).
- AC-12.3 Exit 2 for every Aborted reason (EXIT-1, EXIT-3).
- AC-12.4 Partial findings proven before the stop are shown and numbered (RUN-6). **new**

---

## FL-13 · Error: target unreachable

**Persona:** Maya, app not running. **Pre:** agent connected.

| Case | When | Result | Exit | Copy keys |
| ---- | ---- | ------ | ---- | --------- |
| Not in the allow-list | Before anything is sent | Couldn't start `target_not_allowed`, with `webhooktrap targets add localhost:3000` | 2 | `copy.run.start_failed` |
| Refused at D1 | The agent retries the connect every 500 ms for 5 s (TGT-5), then stops | Couldn't start `target_unreachable`; nothing else sent; doesn't count for guests | 2 | `copy.run.couldnt_start_target_unreachable` |
| TLS error on `https://localhost` | Same as refused | Couldn't start, with the TLS helper | 2 | `copy.agent.unreachable_tls` |
| One or two unreachable mid-run | That delivery `unreachable`; its scenario `unreachable` unless it has a finding; the run continues | Final status as judged (findings or Inconclusive), **exit 2** because a scenario is unreachable | 2 | `copy.scenario_reasons.unreachable` |
| Three in a row | The run stops | Aborted `target_lost`; the rest "not run" | 2 | `copy.run.aborted_target_lost` |

Unreachable is never a finding (GEN-4). In the draft, if the agent reports the target down before Run, step 2 shows `copy.agent.unreachable_refused` and Run stays enabled (the dev server may start before Run is pressed).

**AC**
- AC-13.1 Target refused mid-run → delivery "Unreachable", run exit 2 (T39).
- AC-13.2 Target not in allow-list → Couldn't start with the fix command (T68).
- AC-13.3 A dev server restarting within 5 s is not reported unreachable (TGT-5). **new**
- AC-13.4 Three consecutive unreachable deliveries → Aborted `target_lost` (RUN-5). **new**

---

## FL-14 · Error: baseline rejected → exit 2

**Persona:** Maya with a wrong secret in the handler. **Pre:** agent connected, secret on the agent differs from the handler's.

| # | System does | Screen | Copy keys |
| - | ----------- | ------ | --------- |
| 1 | Preflight passes; D1 (#01 `invoice.paid`, correctly signed) gets **401** | S-11 `running` | — |
| 2 | The run stops at once; nothing else is sent (ONCE-4) | S-11 `couldn't start` | — |
| 3 | Verdict "Couldn't start the run"; reason "Your handler rejected a correctly signed delivery (HTTP 401). Check the secret on maya-mbp and the target path."; the D1 row shows 401; no strip, no findings (this is not a finding) | S-11 `couldn't start` | `copy.run.couldnt_start_baseline_rejected` |
| 4 | Actions: **Run again**, **Edit scenarios**; agent pill popover lists secret names to compare | S-11 | — |

**Contrast (ONCE-5):** if D1 got 2xx and a **later** Once delivery got 4xx (an event type the handler doesn't handle), the setup is proven, so it is a Medium `non_2xx_on_valid` finding and the run continues.

**AC**
- AC-14.1 Once 401 → run stops "baseline rejected", exit 2, no other scenario sent (T22).
- AC-14.2 It doesn't count toward guest quotas (A-13). **new**
- AC-14.3 A later Once 4xx after a 2xx D1 is `non_2xx_on_valid` Medium and the run continues (ONCE-5). **new**

---

## FL-15 · Create an account with email and password

**Persona:** Maya, new, on hosted. **Entry:** S-02 "Create an account", or landing Sign in. **Pre:** no session, or a guest session.

| # | User does | System does | Screen | Copy |
| - | --------- | ----------- | ------ | ---- |
| 1 | Fills Name, Email, Password; **Create account** | Creates the user unverified (or, if the email is taken, creates nothing and sends `account-exists`) | S-24 → S-25 | S-24 |
| 2 | — | Same "Check your inbox" page in both cases | S-25 check inbox | S-25 |
| 3 | Opens the link in the same browser; the link page (S-25) shows **Confirm email**; presses it | Opening changes nothing; the `POST` marks the email verified; a guest sign-up claims now (same browser session, A05) | S-25 link page → `next` or `/projects/new` | flash "Email confirmed." |
| 4 | — | Account menu shows "Turn on two-step sign-in" | S-00 | — |

**Branches:** link opened in another browser → S-25 "Email confirmed", no claim. Link expired → S-25 expired. GitHub or Google instead → FL-17 without step 3 (provider-verified email counts as verified).

**AC**
- AC-15.1 `/register` with a taken email returns the same page, status and timing class as a new one; only the email differs. **new** (A05 uniform answers)
- AC-15.2 Workspace pages are unreachable until the email is verified. **new**
- AC-15.3 A verification link opened in another browser never claims a guest session. **new** (A09 R-N)
- AC-15.4 Opening the verification link (`GET`) verifies nothing; only **Confirm email** (`POST`) does (A05 §2.9). **new**

---

## FL-16 · Sign in with a passkey

**Persona:** Maya, passkey "MacBook Touch ID". **Entry:** S-02. **Pre:** WebAuthn available on the browser and the instance.

| # | User does | System does | Screen |
| - | --------- | ----------- | ------ |
| 1 | Focuses Email; the browser offers "maya@brindlecoffee.example · passkey" in autofill (conditional UI), **or** presses **Sign in with a passkey** | `POST /webauthn/assert/options` (empty `allowCredentials`, UV required) | S-02 passkey waiting |
| 2 | Confirms with Touch ID | Checks origin, RP ID, UV, BE, counter rule, user handle = owner (WA-1, WA-2); signs in **and** satisfies 2FA; regenerates the session; claims a guest session started here | → `next` |
| 3 | — | If a recovery was waiting: cancelled (RC-1), flash S-29 #8, email `recovery-cancelled` | S-03 |

**Branches:** cancel / timeout → S-02 "Passkey sign-in was cancelled. Nothing changed." Unknown, blocked or mismatched credential → uniform "That passkey didn't work…", audit row, and for a counter anomaly the key is blocked and `key-blocked` emailed (FL-23). No WebAuthn → button hidden with its line.

**AC**
- AC-16.1 Passkey sign-in never shows S-27. **new**
- AC-16.2 The failure copy is identical for unknown, blocked, handle mismatch and counter refusal (TM-T120, TM-T121).
- AC-16.3 The email field carries `autocomplete="username webauthn"`; the passkey button is absent when `passkeysAvailable = false`. **new**

---

## FL-17 · Sign in with a password (or GitHub, Google) and a second step

**Persona:** Maya, 2FA on (YubiKey 5C NFC, authenticator app, 8 codes), as a guest with deliveries. **Entry:** guest meter **Sign in to keep** → S-02.

| # | User does | System does | Screen |
| - | --------- | ----------- | ------ |
| 1 | Email + password, **Sign in** (or GitHub / Google) | First factor OK; session is half signed in; claim nonce kept, nothing claimed yet | → S-27 |
| 2 | Sees the security-key panel first; **Use security key**; touches the key | Second-factor assertion (UP required); counter rule | S-27 waiting |
| 3 | — | Session regenerated; **then** the guest session is claimed (TM-12) and `next` restored | S-03 + `copy.guest.claimed` |

**Branches:** "Other ways" → authenticator code or recovery code (flash "Recovery code used. You have 7 left.", email `code-used`). 5 wrong attempts → back to S-02. 20 failures for the account in 24 h → S-27 paused, email `hold` (WA-3). Wrong password → uniform S-02 error. Forgot password → S-26 then S-27 (reset doesn't skip 2FA). "Recover your account" → FL-21.

**AC**
- AC-17.1 GitHub and Google sign-ins go through S-27 when the account has 2FA (A09 R-K). **new**
- AC-17.2 Claim happens only after S-27 succeeds, in the same browser session (TM-12). Extends T78.
- AC-17.3 The per-account counter trips across fresh sign-ins (TM-T122).
- AC-17.4 A used TOTP code is refused a second time with "That code was already used…". **new**

---

## FL-18 · Turn on two-step sign-in with a passkey or security key

**Persona:** Maya, password + GitHub, no second factor. **Entry:** account menu "Turn on two-step sign-in" → S-17A.

| # | User does | System does | Screen |
| - | --------- | ----------- | ------ |
| 1 | **Add a passkey** | Recent authentication older than 10 min → S-28 ("add a passkey"), then back | S-28 → S-17B `add` |
| 2 | **Continue**, confirms with Touch ID | `register/options` (`residentKey` required, UV required, `excludeCredentials`, attestation none) | S-17B waiting |
| 3 | Keeps the name "MacBook Touch ID", **Save** | Stores the credential; first second factor → returns 10 recovery codes once; email `key-added` | S-17B codes show once |
| 4 | **Download**, ticks "I've saved these codes", **Done** | 2FA is on | S-17A, toast |

Security key: same with **Add a security key** (any credential, second factor only). **AC**
- AC-18.1 Recovery codes are shown exactly once; reloading S-17A shows "10 of 10 left", never the codes. **new**
- AC-18.2 The 11th credential is refused and both Add buttons are disabled with the reason. **new**
- AC-18.3 Every added credential sends `key-added` (domain §2.2). **new**

---

## FL-19 · Set up an authenticator app

Entry S-17A **Set up** → (S-28) → S-17B `totp` scan → confirm with a code → codes show once if first factor → S-17A. Branches: wrong code, reused code, closing before confirm discards the secret. **AC-19.1** the `otpauth://` secret is never shown again after the dialog closes. **new**

---

## FL-20 · Remove a key, turn off two-step, keep a way in

| Case | Path | Result |
| ---- | ---- | ------ |
| Remove one of several | S-17A Remove → (S-28) → confirm | Row gone, `key-removed` email |
| Remove the last second factor | → "Remove … and turn off two-step sign-in?" | 2FA off, codes deleted, `2fa-off` email |
| Remove the last first factor (e.g. passkey-only account) | → refused dialog "You can't remove your only way to sign in" | Nothing changes (`422 last_sign_in_method`) |
| Disconnect GitHub during a recovery or its 7-day lock | Button disabled with the lock line | RC-3 |
| Disconnect GitHub (rev. 4) | S-17A Disconnect → S-28 ("disconnect GitHub") → dialog → **Disconnect** | Identity removed, `method-unlinked` email; browsers signed in with GitHub stay (FL-25 to sign them out) |
| Connect Google during the lock (rev. 4) | S-17A Connect → S-28 → Google → back | Allowed: the lock covers removal only (A05 §2.5, AR-1); `method-linked` email |
| Turn off the authenticator app when it is the last second factor (rev. 4) | S-17A Turn off → S-28 → "Turn off the authenticator app and two-step sign-in?" → **Turn off two-step sign-in** | 2FA off, codes deleted, `2fa-off` email |

**AC-20.1** the last first factor can't be removed (A05). **AC-20.2** removing the last second factor deletes the recovery codes and emails the user. **new** **AC-20.3** Connect works during a recovery and its lock; Disconnect is refused (`422 recovery_lock`) for identities linked before the request. **new** (A05 R-R, AR-1)

---

## FL-21 · Lost every factor: account recovery (hosted, PO-J)

**Persona:** Maya, phone and YubiKey lost, codes not found. **Pre:** knows her password.

| # | User does | System does | Screen / email |
| - | --------- | ----------- | -------------- |
| 1 | Signs in with the password; on S-27 chooses "Recover your account" | — | S-27 → S-29 #1 |
| 2 | **Send recovery link** | Uniform answer; email `recovery-link` if eligible (one open, 30-day cool-down, rate limits: RC-5) | S-29 #2 |
| 3 | Opens the link (the link page changes nothing), **Start the 72-hour wait** | Wait starts on the `POST` (`completes_at` = +72 h, or 7 days after a recent email reset: RC-2); audit (RC-6) | S-29 #3 → #4; `recovery-started` |
| 4 | — | 24 h later | `recovery-reminder` |
| 5 | — | At `completes_at` (A05 rev. 5): removes second factors, passkeys, codes **and the password**; revokes every browser session and personal token; sets every agent to `needs_reconfirm`, approvals cleared (RC-4); lock for 7 days (RC-3); emails the finish link (24 h) | `recovery-done`, `recovery-ready` |
| 6 | Opens the finish link, sets a new password (12–128), **Finish recovery** | Signs her in | S-29 #9 → S-30 |
| 7 | Adds a passkey, revokes nothing unknown, answers `y` in maya-mbp's terminal | — | S-30 → S-17A |

**Branches.** Someone else's request reaches Maya's inbox → she opens the link and presses "Cancel, I found my key" → S-29 3b "Recovery not started": closed as `declined`, no 30-day cool-down, no email (A05 RC-5 reading). Nobody opens the link → closed as `link_expired` after 60 min, no cool-down. The finish link lapses (24 h) → "Forgot password" works, since the account no longer has 2FA. Maya finds her YubiKey and signs in during the wait → recovery cancelled automatically (RC-1), flash and `recovery-cancelled`. Someone else started it → Maya sees the S-00 banner on any signed-in session and the `recovery-started` email, presses **Cancel recovery**, then changes her password. Password reset by email during the wait → `recovery-extended` and the banner shows the 7-day date. **Self-host:** S-27 says to ask the administrator; the admin runs the host command (domain §2.3), Maya gets a password-set link (S-26 form).

**AC**
- AC-21.1 Auto-cancel on a second-factor sign-in; reset extends the wait; email change refused while open; completion sets agents `needs_reconfirm` (TM-T125).
- AC-21.2 The request page answers identically for unknown emails, cool-down and an already open request. **new**
- AC-21.3 The banner appears on every signed-in page while a recovery is open and is not dismissable. **new**
- AC-21.4 Opening the recovery link alone does not start the wait (needs the button). **new** (A05 §2.9, confirmed)
- AC-21.5 "Cancel, I found my key" and an expired unconfirmed link start no cool-down; a cancel after confirmation does (30 days). **new** (A05 R-V)
- AC-21.6 Passing S-28 with a passkey, security key or authenticator code during the wait cancels the recovery and shows the S-28 flash. **new** (RC-1)
- AC-21.7 S-30 is reachable from completion until the lock ends, then redirects to S-17A; Revoke on a CI token removes it from that project. **new**

---

## FL-22 · Returning 1.x user (PO-H option B)

**Persona:** one of the 3 imported accounts (RD stand-ins: Tomás Okafor, Aiko Brandt, Lena Vos). **Entry E13:** the `launch-1x` email. **Pre:** account imported unverified, no password, no 2FA.

| Path | Steps | Result |
| ---- | ----- | ------ |
| A · Set a password (Tomás) | Email link → S-26 Forgot (email) → `reset` email → S-26 **Set a password** variant → saved | Email verified, `LegacyAccountActivated (reset)`, flash about 2FA not carrying over → S-15 empty projects |
| B · GitHub with the same verified email (Aiko) | S-02 **Continue with GitHub** | Provider says the email is verified → linked, email verified, `method-linked` email, `LegacyAccountActivated (oauth)`, same flash |
| C · Tries the password from 1.x | S-02 password | Uniform "Email or password is incorrect."; the footer line "Had an account before 2.0? Set a password to sign in." points to path A |
| D · Tries a passkey | S-02 passkey | Nothing to offer (no passkey can exist before the email is proven); the browser shows its own "no passkeys" sheet |
| E · Never comes back (Lena) | — | Account kept, unverified (decision 41) |

**AC**
- AC-22.1 The import never reads the hash fields; a 1.x password never signs in (domain §2.4). **new**
- AC-22.2 A passkey can't be added to an imported account before its email is proven. **new**
- AC-22.3 The "Set a password" title shows only after the reset link is opened, never on S-02 or S-26 Forgot (no enumeration). **new**

---

## FL-23 · A blocked key (counter anomaly)

Maya's "Old YubiKey" reports a use count not greater than the stored one at S-27 → refused with the uniform key message, the key is blocked, `key-blocked` is emailed, an audit row is written (WA-1). Maya signs in with the authenticator app, opens S-17A, sees **Blocked**, chooses **Review** → **Remove key** (recommended) or **Unblock** (after S-28 with another method; the blocked key is never offered there) → toast, `key-unblocked` email. The sign count is kept, so a copied key with a lower count is blocked again; a key blocked **twice** shows "This key was blocked twice. Remove it and add it again." and no Unblock (A05 §2.2, rev. 4). **AC-23.1** a blocked key is refused for sign-in and as a second factor until reviewed (TM-T120). **AC-23.2** Unblock keeps the stored sign count; replaying a lower count blocks the key again. **new** **AC-23.3** A key with `flag_count >= 2` shows no Unblock and the route answers `422 unblock_not_allowed`. **new**

---

## FL-24 · Change email (rev. 4, U-13)

**Persona:** Maya, 2FA on, moving to `maya@brindleroasters.example`. **Entry:** S-17A **Change email**. **Pre:** email verified; no recovery open or locked.

| # | User does | System does | Screen / email |
| - | --------- | ----------- | -------------- |
| 1 | **Change email** | Recent authentication older than 10 min, or set by the password only on a 2FA account → S-28 in second-factor mode ("change your email") | S-28 → S-17B `email` |
| 2 | Types the new address, **Send link** | Uniform answer whether free or taken; replaces any pending change | S-17B `email` sent; `email-change-verify` (or `email-change-taken`) to the new address, `email-change-requested` to the old one |
| 3 | — | S-17A shows "Changing to m***@b***.example …" with **Cancel change** | S-17A |
| 4 | Opens the link in a browser signed in to her account | Link page; nothing changes yet | S-32 Confirm new email |
| 5 | **Confirm new email** | In one transaction: re-checks the lock and the address, sets the email (verified), drops reset tokens, signs out every other browser | → S-17A flash; `email-changed` (old, masked), `email-changed-new` |

**Account without two-step sign-in (rev. 5, EC-1).** The old address must confirm too.

| # | User does | System does | Screen / email |
| - | --------- | ----------- | -------------- |
| 1 | **Change email** | Recent authentication (password, passkey or GitHub/Google; no second-factor mode here) → S-28 → S-17B `email` | S-28 → S-17B `email` |
| 2 | Types the new address, **Send link** | Uniform answer; replaces any pending change; two links, one per address | S-17B `email` "Check both inboxes"; `email-change-verify` (or `email-change-taken`) to the new address, `email-change-confirm-old` to the old one |
| 3 | — | S-17A pending line: "Waiting for both addresses" | S-17A |
| 4 | Opens each link in a browser signed in to her account (any order) and presses its button | Each link page checks its token without using it; each button records its half. The first press changes nothing yet | S-32 Confirm new email / Confirm email change → S-17A flash "Confirmed. Waiting for …" |
| 5 | Presses the second button | Same transaction as above, only when both halves are done within 60 minutes of the request | → S-17A flash; `email-changed` (old, masked), `email-changed-new` |

No "This wasn't me" link anywhere: an old-address email that was not asked for is ignored, and nothing changes without that address's button. An owner who has lost the old mailbox first adds a passkey or an authenticator app, then changes the email with the second factor (the 2FA flow above).

**Branches.** Link opened while signed out → S-32 "Sign in to confirm…" → S-02 (and S-27) → back to the link page. Signed in to another account → "Sign out and continue". Address taken since, lock started, newer request, expired → S-32 "This link doesn't work". **Cancel change** → toast, the link stops working. Recovery opened meanwhile → the pending change is cancelled (A05 §2.6).

**AC**
- AC-24.1 The answer to `POST /account/email` is the same for a free and a taken address; only the email to the new address differs. **new**
- AC-24.2 On a 2FA account a password-only recent authentication gets `second_factor_required` and S-28 offers no password. **new** (A05 R-S)
- AC-24.3 Opening the link changes nothing; the change needs the button in a session of the same account. **new**
- AC-24.4 After the change every other browser is signed out and old reset links fail. **new**
- AC-24.5 On an account without two-step sign-in the email changes only after both the new and the old address have pressed their button; one is never enough, in either order. **new** (EC-1)
- AC-24.6 Cancel change, a newer request, a recovery or the end of the 60 minutes drops both links together, and a half-confirmed change is dropped with them. **new**
- AC-24.7 No email offers a revert or "This wasn't me" link. **new** (R-AE rejected)

---

## FL-25 · Sign out other browsers (rev. 4, U-10)

**Persona:** Maya, who sees "Safari 18 on iPhone" in S-17A section 6 and no longer has that phone. **Entry:** S-17A, or the Security link in any security email.

| # | User does | System does | Screen |
| - | --------- | ----------- | ------ |
| 1 | **Sign out** on the iPhone row (or **Sign out all other browsers**) | Recent authentication → S-28 ("sign out a browser" / "sign out other browsers") | S-28 |
| 2 | Confirms in the dialog | Revokes the row(s); revocation is checked on every request, so that browser is signed out on its next page load; audit; no email | S-17B `signout` → S-17A toast |
| 3 | (Optional) changes her password | Signs out every other browser again | S-17B `password` |

**AC**
- AC-25.1 The current browser is never revoked from this section and has no Sign out button. **new**
- AC-25.2 A revoked session gets the sign-in page on its next request (cache ≤ 30 s, A05). **new**
- AC-25.3 Revoking needs recent authentication; without it the person goes to S-28 first. **new** (A05 R-X)

---

## FL-26 · Delete account (rev. 4, U-19; rev. 5, DL-4)

**Persona:** Maya, closing her account. **Entry:** S-17 Delete account. Deletion is immediate (PO-L) and A05 revision 6 serves this flow. Two paths, by whether the account has two-step sign-in.

| # | User does | System does | Screen |
| - | --------- | ----------- | ------ |
| 1 | Opens Delete account, reads what goes, ticks "I understand this can't be undone." | — | S-17 `delete` |
| 2 | **Delete account** | Fresh confirmation for this purpose (5 min, single use): S-28 ("delete your account"), second factor only on 2FA accounts | S-28 |
| 3 | — | **With two-step sign-in:** deletes the account and its data at once, revokes agents, tokens and sessions; emails `account-deleted` | → S-01 flash |

**Without two-step sign-in (rev. 5, DL-4, PO-N).** Step 3 is a link instead:

| # | User does | System does | Screen / email |
| - | --------- | ----------- | -------------- |
| 3 | — | Deletes nothing yet; sends a link to the account address (60 minutes, single use) | S-17 `delete` "Check your email"; `delete-confirm` email |
| 4 | Opens the link in a browser signed in to her account | Link page; nothing changes yet | S-32 Delete my account |
| 5 | **Delete my account** | Deletes the account and its data at once, as above; emails `account-deleted` | → S-01 flash |

**Branches.** Recovery open or locked → refused with the lock line. Self-host only administrator → refused. Link opened while signed out or in another account → S-32 sign-in states. Expired, used or replaced → S-32 "This link doesn't work". Not now → nothing is deleted and the link keeps working until it expires.

**AC**
- AC-26.1 Nothing is deleted until the box is ticked and recent authentication passes. **new**
- AC-26.3 On an account without two-step sign-in nothing is deleted until the emailed link's button is pressed in a session of the same account; opening the link changes nothing. **new** (DL-4)
- AC-26.4 On an account with two-step sign-in there is no email step: the existing fresh confirmation deletes. **new**
- AC-26.2 After deletion, the capture URLs answer like unknown URLs and share links show "not found". **new**

---

## 15. Traceability to TR §5

| TR tests | Covered in |
| -------- | ---------- |
| 1, 2, 5, 6, 7 | FL-1 |
| 12, 18 | FL-1, FL-2 |
| 19 to 30, 32 to 36 | FL-3 (judge unit tests stay A11's) |
| 22 | FL-14 |
| 37, 52 to 61 | FL-8, GF |
| 38 | FL-12 |
| 39, 68 | FL-13 |
| 60, 70, 83, 86 | FL-4 |
| 66, 67 | FL-2 |
| 69, 71, 73 | FL-3 |
| 75 to 79 | FL-7, FL-11 |
| 80 to 88 | A11Y §10, every flow |
| A09 TM-T120, TM-T121 | FL-16, FL-23 |
| A09 TM-T122 | FL-17 |
| A09 TM-T125 | FL-21 |
| new (account security) | AC-15.x to AC-26.x, AC-7.7 |

**TR tests that SJ or decision 35 changed** (A11 should update the skeleton):

| Test | Was | Now |
| ---- | --- | --- |
| T10 | Groups include the cross-provider Order #1042 | Order #1042 is Shopify only; Parcelbird forms `shp_1c263…ztsP` (GRP-5) |
| T19 | Late High in the buggy run | Expected set per SJ §5.2; Late is rejected by the reference buggy handler in RD |
| T24 | Same seed → same permutation | Reverse capture order (SHUF-1) |
| T29 | 4xx on attempt 1 → `non_2xx_on_retry` | GEN-2 `non_2xx_on_valid` Medium (no such type as `non_2xx_on_retry`, FND-1) |
| T40 | `trap rejudge` | `webhooktrap rejudge` (NAME-1, JSON-4) |
| T47 | Probe value never leaves the machine | Display values only: counts −9 999…9 999 as numbers, everything else as codes; `show:` values only on the owner's view (PV-1 to PV-8; A09 T1–T10) |
| T72 | Needs-secret offers "Run only Once and Slow budget" | Removed: a Stripe run can't start without a secret (SIGN-2) |
| T77 | 4th run refused; accepted runs count | 11th run refused; runs count once past Once (A-13) |

---

## 16. Changes

| # | Change | Source |
| - | ------ | ------ |
| 1 | New file: 14 flows with entry, steps, screens, copy keys, exits and acceptance criteria | Phase 2 task |
| 2 | FL-2: 10-symbol code, `@1` command, terminal prompt with the server origin first, "Confirm in your terminal" step, decline and non-TTY branches, toast without last 4; AC-2.8, AC-2.9 | PO-D, PO-E, PO-F; A01 §16 row 37 |
| 3 | FL-3: terminal confirmation of probe and reset (closes O-FL-1); Burst reading with F4; AC-3.12 provenance and "judged on", AC-3.13 no probe definitions on hosted pages | PRB-11, RUN-8, RUN-9, PV-8, PRB-12a |
| 4 | FL-6 rewritten: `/s/{token}`, rotation, Include probe values, guest and claim rules, CI privacy; AC-6.5, AC-6.6 | PO-A, PO-C, PV-7, PV-14 |
| 5 | FL-7: claim voids guest share links, agent reconfirmation in the terminal, approvals cleared; AC-7.2 changed, AC-7.6 | PO-E; A05 domain model |
| 6 | FL-8: CI token by name, uploads private, `--share`; AC-8.7, AC-8.8 | PO-C, RUN-8, RUN-9 |
| 7 | FL-9: accounts only, kill switch, provenance, Shopify copy from RD; AC-9.4 replaced | PO-B, HD-10, RUN-11; QA v3 M4 |
| 8 | FL-10: per-user permission for private destinations | PO-G |
| 9 | T47 restated under PV; O-FL-1 and O-FL-3 closed | Decision 38; A01 PRB-12a |
| 10 | **Rev. 3.** Flow map gains the account-security flows; FL-7 step 2 lists password, passkey and the second step, email link dropped; AC-7.7 | Decision 44; A05 contracts §3.4; A09 R-N |
| 11 | New FL-15 Create account, FL-16 Passkey sign-in, FL-17 Password or OAuth + second step, FL-18 Add passkey or key, FL-19 Authenticator app, FL-20 Remove / turn off / last method, FL-21 Account recovery (hosted, 72 h, RC-1 to RC-6), FL-22 Returning 1.x user, FL-23 Blocked key; traceability rows for TM-T120 to TM-T125 | Decisions 41, 44; A05 domain §2.1–2.4; A09 §12 |
| 12 | **Rev. 4.** FL-15 Confirm email button (AC-15.4); FL-20 disconnect, connect during the lock, last-factor authenticator (AC-20.3); FL-21 link page, decline and `link_expired` without cool-down, completion clears the password and sends the 24 h finish link, S-28 cancels (AC-21.4 confirmed, AC-21.5 to AC-21.7); FL-23 unblock route and "blocked twice" (AC-23.2, AC-23.3); new FL-24 Change email, FL-25 Sign out other browsers, FL-26 Delete account | A05 revision 5, U-10 to U-19 |
| 13 | **Rev. 5.** Decisions 46 and 47: `[pending PO]` markers removed; FL-24 gains the no-2FA path where the old address also confirms (EC-1, AC-24.5 to AC-24.7); FL-26 gains the no-2FA "Check your email" path (DL-4, AC-26.3, AC-26.4) and loses its A05 markers; O-FL-4 closed | PO-H, PO-I, PO-J, PO-L, PO-M, PO-N, PO-O; A05 revision 6 |

## Open for the PO

| # | Question | Recommendation |
| - | -------- | -------------- |
| ~~O-FL-1~~ | **Closed.** Probe and reset commands are confirmed in the agent's terminal (A05 decline `needs_confirmation`, ADR-010 §4); FL-3 step 5 and `copy.run.queued_confirm`. | — |
| O-FL-2 | **Reconnect copy after an agent is lost** (FL-12). The command depends on whether the agent keeps its pairing (A-12). | Show "Restart the agent in your terminal: webhooktrap agent" once A05 names the command; until then, RD's "Reconnect to run it again." |
| ~~O-FL-3~~ | **Closed** by A01 PRB-12a: F4 = D6 · D15; RD regenerated (change 26). | — |
| ~~O-FL-4~~ | **Closed, decision 46:** FL-21 (delayed email recovery, PO-J, R-7 accepted) and FL-22 (forced reset for 1.x, PO-H option B) are decided. | — |
