# Decisions Log — Webhook Trap 2.0

Architecture-level decisions live in `project/architecture/decisions/`; this
log records every decision, including product and process ones, with a
pointer to the ADR where one exists.

| #  | Date       | Decision | Author | Context | Alternatives | Result |
| -- | ---------- | -------- | ------ | ------- | ------------ | ------ |
| 1  | 2026-11-11 | Rebuild on Laravel + Inertia React + Reverb + PostgreSQL + Redis as a modular monolith | Product owner | Orchestration brief, "Confirmed decisions" | Keep AdonisJS + Next.js (1.x) | **Confirmed** — ADR-001, 002, 004, 007, 009 |
| 2  | 2026-11-11 | Anonymous + authenticated access via a `GuestSession` principal | Product owner | Brief | Accounts only | **Confirmed** — ADR-003 |
| 3  | 2026-11-11 | Visual identity explored independently; 1.x terracotta direction not carried over by default | Product owner | Brief | Keep `DESIGN.md` | **Confirmed** — ADR-002 consequence updated |
| 4  | 2026-11-11 | Rewrite happens on branch `rewrite/v2` cut from `main` | A00 | ADR-009 step 3; `main` is what production runs | Work on `redesign-warm-workbench` | **Done** |
| 5  | 2026-11-11 | Model guest sessions as a dedicated auth guard so channel and policy checks receive a principal | A00 | Laravel rejects private-channel auth with no resolved user | Ad hoc cookie checks in each channel callback | **Proposed** — ADR-003 amendment, to be validated by A08/A09 in Phase 4 |
| 6  | 2026-11-11 | Keep an opt-in for replay to loopback/RFC1918 on self-hosted instances | A00 | README's headline use case; 1.x flag `ALLOW_INSECURE_REPLAY_TARGETS` | Block until the local agent ships | **Proposed** — ADR-006 amendment; needs PO + A09 |
| 7  | 2026-11-11 | Allow self-hosters to run without Redis (database queue/cache) | A00 | 1.x promises few moving parts | Redis mandatory everywhere | **Proposed** — ADR-007/008 amendments; needs PO |
| 8  | 2026-11-11 | 2.0 keeps accepting 1.x ingest URLs `/i/{id}` and `/hooks/{id}` | A00 | Live providers point at those URLs | Break URLs at launch | **Proposed** — ADR-009 amendment; needs PO |
| 10 | 2026-11-11 | Authorise Phase 1 exploration (A01 ∥ A02) in separate worktrees; no final positioning or identity decision and no merge before PO approval; Gate 0 still subject to documentary review | Product owner | Phase 0 report | Wait for full Gate 0 | **Approved** — worktrees `phase1/a01-product`, `phase1/a02-brand` |
| 11 | 2026-11-11 | Authorise a local 1.x security hotfix for P-01 – P-04 on a branch from `main`; no push, PR, deploy or public disclosure; private security report | Product owner | Phase 0 findings | Defer fixes | **Approved** — `hotfix/security-2026-11` |
| 12 | 2026-11-11 | Keep `spikes/live-props/` and `webhooktrap-spike.test` as Phase 4 experimental assets; no merge into production architecture without compatibility and quality review | Product owner | Paused spike | Delete | **Approved** |
| 13 | 2026-11-11 | `docs/` stays the Mintlify site; internal 2.0 documents move to `project/`; vulnerability details stay outside the repository | Product owner | Blocker B-03 | `docs/` split | **Done** |
| 14 | 2026-11-11 | Evaluate (not decide) the three Proposed amendments | Product owner | ADR review | Accept / reject | **Done** — `project/architecture/amendment-evaluation.md` |
| 15 | 2026-11-11 | **Change of direction:** 2.0 is designed as a new product, not a rebuild of 1.x. The 33 1.x features are not a scope baseline; 1.x screens, flows, components, colours and brand are not references. 1.x stays a historical reference for the original problem and technical constraints only | Product owner | Phase 1 review: PO not satisfied with the design proposals | Keep/defer/drop the 1.x features; iterate on Field Recorder / Printout / Postmark | **Approved** |
| 16 | 2026-11-11 | Phase 1 A01 and A02 deliverables marked SUPERSEDED (kept, not deleted); A01 restarts from problem research; A02 and A03 restart with entirely new art directions and UX | Product owner | Decision 15 | Revise the existing drafts | **Done** — banners + `SUPERSEDED.md` in `phase1/a01-product`, `phase1/a02-brand` |
| 17 | 2026-11-11 | Phase 1b sequencing: A01 first (research, concepts, positioning, core experience narrative); then A03 (UX exploration) ∥ A02 (art directions) both built from A01's concepts; then A00 assesses impact on the ADRs and writes the recommendation | A00 | PO: "design must come from the new product concept"; brief: no simultaneous launch of all agents | A01 ∥ A02 ∥ A03 from the start | **Done** |
| 18 | 2026-11-11 | Confirmed technical decisions (ADR-001 – 009) stay in force; A00 re-examines any the new concept calls into question and reports with justification instead of editing them | A00 | PO §4 | Reopen all ADRs now | **Done** — impact review planned at the end of Phase 1b |
| 19 | 2026-11-11 | Visual exploration must be produced in Claude Design (Design System + Design canvas artifacts), not only as Markdown/HTML files: ≥3 radically different directions, each with full visual system (identity, logo, colours, type, iconography, components, layout principles) and concrete screens (landing, main app, received-webhook detail, replay + responses) in desktop + mobile, light + dark, realistic data, empty/loading/error states. Workflow: A02 territories → A03 UX per territory → A04 materialises in Claude Design → A01 checks fit with the vision → A00 final review. Blockers reported precisely, never claimed as done | Product owner | Mandatory instruction | Markdown descriptions; standalone HTML mock-ups | **Approved** |
| 20 | 2026-11-11 | Extend the local 1.x hotfix to P-11 and P-12, tighten P-03/P-04 security tests; DNS rebinding must be closed before final validation | Product owner | Hotfix report | Defer | **Approved** — A09 working, still local only |
| 21 | 2026-11-11 | Fix P-17 only in the local hotfix; P-16 deferred until an audit of the Caddy → Next → API trust chain and one shared fix for both 2FA routes | Product owner | A11 extension verification | Fix both now | **Approved** — P-17 committed `ebe24c0`, A11 verifying |
| 22 | 2026-11-11 | Model selection policy: A00 picks the model per task by complexity, creativity, context size, criticality, cost; critical tasks never downgraded; independent reviews never reuse the author's conclusions; model, level, reason and changes recorded | Product owner | Mandatory instruction | One model for everything | **Approved** — `model-policy.md`; limitation logged: no design-specialised model documented, creative work stays on Opus 5.5 |
| 23 | 2026-11-11 | Lead visual direction: **I · Timetable**, developed with the recommended borrowings (Crash Lab scenario cells + "Expected 1 · got 2" gauge; Notation copy voice + compact CLI/CI/PR text format). Notation kept as fallback; Crash Lab dropped as a whole direction | Product owner | `phase-1b-recommendation.md` | Notation; Crash Lab | **Approved** — iteration started; not a final approval of the identity |
| 24 | 2026-11-14 | **Q1 confirmed: concept B "Proving Ground" defines 2.0** — the test suite for webhook handlers; A (debugger) is its first minute, C (production monitoring) is Horizon 2 | Product owner | A01 `concepts.md`, `phase-1b-recommendation.md` | A Workbench, C Flight Recorder | **Approved** — follow-ups in `concept-impact.md` (agent in MVP, new ADR, ADR-003/004/005 amendments) still need their own PO approval |
| 25 | 2026-11-14 | Explore 3–4 genuinely different logo concepts within Timetable before choosing (instead of refining the trap-points mark only); built in a dedicated Claude Design canvas | Product owner | Identity not finalised (single mark, live-text wordmark, no favicon set or guidelines) | Refine the current mark | **Approved** — A02 working |
| 26 | 2026-11-14 | Logo: **Twice** (two parallel delivery paths across two station rails; the second lands on the red conflict square) | Product owner | `logo-exploration.md`, canvas https://claude.ai/artifact/D579LRxyW9S96UXqmZQPCQ | Points, Second pass, Proves | **Approved** — integration by A04 in Timetable v2; visual/legal resemblance check (rail operators, Hookdeck, Svix, NS) still required before public use; 16 px floor |
| 27 | 2026-11-14 | **Q13: validate before building** — 6–8 Integrator interviews + a 2-week CLI prototype measuring the first-run finding rate (kill criterion < ~20% → fall back to positioning A); the 10-second comprehension test runs in the same sessions | Product owner | A01 open questions | Build directly | **Approved** — A01 preparing the validation plan; prototype build needs its own go-ahead (scope, language) |
| 28 | 2026-11-14 | Build the throwaway CLI validation prototype in **Go** (also as Q3 evidence; ~2–3 days later than TypeScript) on `spike/proving-cli`, worktree `.worktrees/spike-proving-cli`, never merged | Product owner | `validation/prototype-spec.md` §6 | TypeScript on Node | **Approved** — local Go toolchain 1.27.4 via mise |
| 9  | 2026-11-11 | Pause the Live Props spike until Phase 4 | A00 | Brief forbids implementation before the prototype gate | Finish the spike now | **Done** — `spikes/live-props/` left installed, no app code |

## 29. Fix the prototype's security findings (2026-11-14)

- **PO:** "Je valide".
- **Fixes:** A06 fixes F1–F5 on `spike/proving-cli`, plus F6 (a trivial size
  cap). F7 and F8 are documented as known limits.
- **Re-check:** A09 then re-verifies on Opus.
- **Scope:** local only. No push, no PR, no merge.

## 30. Fix the re-check findings (2026-11-14)

**PO:** "Yes, fix them".

1. A06 fixes:
   - N1, the `judge` path traversal and its raw output;
   - N2, the raw strings in errors and the export, plus validation of
     `provider`.
2. A06 adds three entries to the README's known limits.
3. A09 then re-verifies on Opus.

Everything stays local: no push, no PR, no merge.

## 31. Start without field validation — option B (2026-11-14)

**PO:** "Yes, option B".

Q13 field validation (interviews and participant prototype runs) is waived
as a precondition for Gate 1. In its place, three safeguards:

1. **Proxy validation.** Run the Go prototype on real open-source webhook
   handlers (official Stripe, GitHub and Shopify samples, popular projects)
   and count real findings. Downloading and running third-party code needs
   PO approval of the repo list first.
2. **Build order.** Build first what serves both concept A and concept B:
   capture, inspect, signature verification. If B does not hold, the work
   falls back to A without waste.
3. **Comprehension test.** The 10-second comprehension test (5 developers)
   moves into Phase 2.

**Gate 1 still needs:**
- the A11 testability review;
- PO decisions on B-04, B-05 and B-06 and the concept-impact follow-ups
  (agent in the MVP, new ADR, amendments to ADR-003, 004 and 005);
- the PO viewing Timetable v2 (B-08);
- the Twice resemblance check before any public use.

**Risk accepted by the PO:** concept B is built without evidence from
users that its findings are seen as real.

## 32. Gate 1 architecture package approved (2026-11-14)

**PO:** "Approve all".

1. **Local agent in the MVP.**
   - A new ADR, "Local agent and client-side scenario engine", is to be
     drafted by A05 and A09 in Phase 4.
   - Go is the likely language: the Q13 prototype is evidence for Q3, and Q3
     is formally decided in that ADR.
2. **Amendments to draft as Proposed (A05):**
   - ADR-003: guest agent pairing with a one-time code bound to the session.
   - ADR-004: direct Echo events for run timelines, with small and
     non-sensitive payloads; lists keep Live Props.
   - ADR-005: capture the raw request target and the public scheme and host
     as the provider saw them.
3. **ADR-006 / B-05 localhost replay.**
   - The agent is the primary path to localhost.
   - Server-side private replay (B′) is for self-hosted instances only: off
     by default and never for guests.
   - The hosted service replays to public destinations only.
4. **ADR-007/008 / B-05, B-06 Redis.**
   - Redis is required at the MVP, with no Redis-free profile.
   - An architecture test forbids direct Redis use outside an infrastructure
     adapter.
   - Revisit in 2.1 with load data.
5. **ADR-009 / B-04 legacy data and URLs.**
   - No 1.x data migration.
   - 1.x stays online on its own domain until an announced sunset date, to
     be set later.
   - 2.0 starts fresh, and no production query is needed.
6. **Hosting.** Deferred to Phase 4 (A10). It does not block Phase 2.

**Effect.** B-04, B-05 and B-06 are resolved. Gate 1 passes once A11's
testability review comes back without NOT READY. B-08 (PO viewing) and the
Twice check (R-14) continue in parallel; neither blocks Phase 2.

## 33. Fix the last two prototype findings (2026-11-14)

- **PO approval:** "Yes, fix them".
- **Fixes, by A06:**
  - L1: strip Unicode tag characters.
  - L2: when loading run and fixture files, accept only regular files and
    cap them at 10 MB.
- **Verification:** A09 checks the fixes on Opus.
- **Scope:** local only.

## 34. Gate 1 passed (2026-11-14)

The condition set in decision 32 is met: A11's testability review returned
**READY WITH CLARIFICATIONS**, not NOT READY. See
`project/qa/testability-review.md`.

Phase 2 (UX and design system) starts.

**Owners of the 27 clarifications:**
- **A01, in Phase 2:** the scenario and judge rules, before the screens that
  depend on them (A-1 to A-6, A-8 to A-11, A-16, A-17, A-19 to A-22, A-24,
  A-25).
- **A03:** A-23, A-26 and A-27.
- **PO, on A01 advice:** A-7 (MVP scenarios and levels), A-13 (guest
  quotas), A-18 (MVP providers).
- **A05, in Phase 4:** A-12, A-14 and A-15, plus §4 determinism constraints
  for the new agent ADR.

**Carried over:**
- B-08 (the PO views Timetable v2);
- R-14 (Twice resemblance check);
- A11 recommends the PO pre-register the dogfood repo-selection rule and the
  40 %/20 % thresholds before the first proxy run.

## 35. Phase 2 recommendations approved (2026-11-14)

The PO approved all 22 recommendations in `phase2-decisions-pending.md`
("Approve all"):
- **Product scope:** A-7, A-13, A-18, C-1, C-2, C-3 (subject to an A09
  review before any build), C-4.
- **Information architecture:** P1 to P11 as recommended.
- **Proxy validation protocol (A01 §15):** pre-registered as written.
  - Snapshot date: **2026-11-14**.
  - Salt: **`a0e7bf395ede5cdf52a3a8ddbd7a0dbf`** (generated by A00 with `openssl rand -hex 16`,
    recorded here before any query runs).
  - The repository list produced from these values goes to the PO for
    approval before anything is downloaded.

**Next:** A03 applies A01's §16 change list and writes the user flows and
screen specs; A01 generates the candidate repository list (search only, no
clone); A09 reviews C-3 after the prototype check.

## 36. A05 starts Phase 4 in parallel with Phase 2 (2026-11-14)

The PO asked A05 to start architecture work now, alongside A03's Phase 2.
- **Accepted risk:** the schema and contracts may need revision after Gate 2
  if screens change.
- **Scope:** documents only (ADRs as Proposed, domain model, schema draft,
  event catalogue, API/prop contracts draft, agent protocol draft). No
  Laravel bootstrap, no `legacy/` move, no code.
- **Workspace:** worktree `.worktrees/p4-architecture`, branch
  `phase4/a05-architecture`, local only.

## 37. A09 threat model and A10 hosting options on A05's package (2026-11-14)

The PO approved launching both on A05's Phase 4 drafts.
- **A09:** threat model covering S-1 to S-14 in `open-questions.md` §2.
  Design-level output in the architecture worktree; anything sensitive in
  the private report folder.
- **A10:** hosting options H-1 to H-9 for a PO decision. Documents only:
  no provisioning, no accounts, no production changes.

## 38. PV-1 to PV-14 adopted (2026-11-14)

The PO adopted A09's probe-value rules PV-1 to PV-14 (C-3 review, private
report `C3-probe-values-review.md`). They replace A01's PRB-3. This also
settles A05's PO-5.
- **Owners of follow-ups:**
  - A01 updates `scenarios-and-judge.md` §8/§13 and rewrites the
    "never leaves the machine" claim (CE §2.7);
  - A03 uses the PV disclosure wording in the config, share and tooltip copy;
  - A05's schema and protocol already follow PV.

## 39. Phase 4 decisions (2026-11-14)

**HD-1 (changed by the PO):** the hosted 2.0 service runs on the **existing
Contabo VPS that hosts 1.x**, accessed over SSH, instead of a new Hetzner VPS.

The PO approved the other 23 recommendations in `phase4-decisions-pending.md`
as written: PO-1 to PO-4, PO-6, PO-7 and PO-9 (with A09's changes); PO-A to
PO-G; HD-2 to HD-10.

**How A00 reconciles HD-1 with HD-5.** The runner still runs on its own
small VM (HD-5, approved), not on the shared VPS. A09 requires the egress
firewall, and running the runner beside 1.x production would defeat it.

**Accepted risks of sharing the 1.x VPS:**
- disk, RAM and IP are shared with production 1.x;
- 2.0's reverse proxy and ports must coexist with 1.x's.

**Constraints:**
- nobody connects to or changes the VPS without a separate explicit PO
  authorisation;
- A10 plans coexistence on paper first.

**Next:**
- A05 revises the package with A09's changes and these decisions; A09
  re-checks.
- A10 rewrites hosting for Contabo (shared VPS + runner VM) and drafts the
  Compose profile.
- A08 runs the ADR-004 spike.
- A00 writes the ADR-001 amendment and marks the Phase 0 amendments Rejected.

## 40. Retire 1.x completely, now (2026-11-14)

The PO decided there is no coexistence with 1.x: it is retired and removed
everywhere. This replaces the "sunset on its own domain" part of decision 32
and the shared-VPS assumption in decision 39. The 2.0 service gets the whole
Contabo VPS; the runner stays on its own VM.

**Scope (all chosen by the PO):**
- production on the Contabo VPS (PM2 processes, reverse proxy, database,
  Redis);
- Vercel project, Cloudflare R2 bucket, 1.x DNS records;
- the 1.x code in the repo (`apps/api`, `apps/web` on `rewrite/v2`), and the
  hotfix branch, which becomes moot;
- users' data (inboxes, events, accounts).

**Timing:** offline now, before 2.0 ships. The domain stays down until
2.0 launches.

**Users' data:** users are notified before deletion, with no backup kept.

**How it runs:**
- A10 writes the decommission runbook on paper first.
- Every destructive or outward-facing step is confirmed by the PO when it
  is executed: the user notice, each shutdown and each deletion.
- Nothing is deleted before the notice has gone out.
- The repo code removal is a local commit on `rewrite/v2`, made only on the
  PO's go.
- P-16 and the hotfix track are closed, as there will be no 1.x to patch.

## 41. Keep the 1.x accounts for v2; local export (2026-11-14)

**Production inventory**, read-only, taken on 2026-11-14:
- 3 users (one is the PO), none verified, no OAuth links;
- 27 inboxes (22 anonymous);
- 15 events, the last on 2026-10-28;
- no replays, share tokens or media.

**PO decision:**
- The 1.x accounts are kept and recreated in v2.
- All data was exported as JSON to the PO's machine, outside any repository:
  `webhooktrap-private/migration/1x-export-2026-11-14/` (checksums and
  README included).
- When v2 ships, the users are told it is available and asked to sign in
  again.
- Tokens, OTPs and 2FA secrets were not exported; users re-enrol 2FA.

**This amends:**
- **decision 32**, which ruled out any 1.x data migration: accounts are now
  migrated, and events stay archived only;
- **decision 40**, which deleted user data with no backup: the local export
  now exists.

**Still open:**
- The user notice moves to v2 launch. The PO approves its text and its
  sending.
- A05 adds a one-off import of the 3 accounts. Old scrypt hashes are either
  verified once and rehashed, or replaced by a reset link; A09 to advise.

**Shared VPS:** the Contabo VPS also hosts unrelated apps (details in the private ops notes). The "OS
reinstall" step in A10's plan is withdrawn. 2.0 must coexist with those
apps, but not with 1.x.

## 42. Proxy repository list approved as produced (2026-11-14)

The PO approved A01's list as A01 produced it, in
`project/validation/proxy-repo-candidates.md`:
- 15 main repositories;
- a pooled reserve of 10;
- templates and boilerplates kept in the main stratum.

A00's two proposed adjustments (a per-provider reserve, excluding templates)
were declined.

Downloads of these repositories are now authorised, for runs in disposable
containers only, as §15 requires.

## 43. Deployment based on laravel-inertia-vps-template (2026-11-14)

The PO chose `github.com/jiordiviera/laravel-inertia-vps-template` as the base
of the 2.0 deployment. It provides:
- a FrankenPHP image;
- local and production Compose files;
- GHCR images;
- manual SSH deploy of an immutable commit tag, behind the host reverse proxy
  on `127.0.0.1`.

**Reasons:** it fits the shared Contabo VPS, which hosts other apps, and its
`/opt/apps` layout.

**This replaces:** A10's plan for a Compose-owned Caddy on ports 80/443.

**Additions needed for 2.0:**
- a Reverb service;
- Valkey (HD-8);
- the Postgres choice and backups (HD-4);
- the `in.` capture host (25 MiB, raw request target through host proxy +
  FrankenPHP);
- the runner VM;
- agent release (Go binary + npm);
- a licence;
- GitHub Actions pinned to SHAs;
- a self-host Compose profile derived from the production file.

## 44. Agent in Go; authentication methods (2026-11-14)

**Agent language:** the PO confirmed **Go** for the agent and engine
(ADR-010's language choice, Q3).

**Authentication methods confirmed:**
- email and password accounts;
- GitHub and Google sign-in;
- 2FA;
- guest sessions;
- agent tokens.

**Added by the PO:** security keys. A00 reads this as **WebAuthn**, which
covers:
- **passkeys**: sign-in without a password (Touch ID, Windows Hello,
  Android, iCloud or Google password managers);
- **hardware security keys** (YubiKey and similar) as a second factor
  alongside TOTP.

The two use the same standard and one implementation.

**Follow-ups:**
- A05 adds WebAuthn credentials to the Identity module, schema and contracts.
- A09 adds WebAuthn to the threat model (recovery when a key is lost,
  attestation policy, how WebAuthn and TOTP interact).
- A03 adds the security-key screens (register, sign in, manage, recover).

## 45. 2.0 stays open source; licence chosen later (2026-11-14)

The PO confirmed that 2.0 is open source.

**Licence:** chosen once development is done, before the first public release.

**A00 recommendation on file** (not adopted):
- Apache-2.0 for the Go agent and engine, the npm wrapper and the shared packs;
- AGPL-3.0 for the Laravel server.

**Until then:**
- no public release, no package publication (npm, GitHub Releases) and no
  public repository push of 2.0 code;
- contributions from outside are not accepted, so that the licence choice
  stays free.

## 46. PO-H, PO-I, PO-J decided; Phase 4 ADRs accepted (2026-11-14)

The PO approved A09's recommendations:
- **PO-H (option B):** the three 1.x accounts are imported without password
  hashes. Each user resets the password or signs in with GitHub, Google or a
  passkey. The import never reads the hash fields.
- **PO-I:** no email-link (magic link) sign-in.
- **PO-J:** delayed email recovery on hosted when every factor is lost, after
  72 hours, with A09's conditions:
  - any second-factor sign-in cancels it;
  - a password reset by email extends the wait to 7 days;
  - no email change while it is open;
  - completion resets agents to local reconfirmation.

  The residual risk **R-7 is accepted**.

**Accepted** (A09 verdict §12.10: ready pending only these decisions):
- ADR-010 (local agent and scenario engine, Go);
- amendment 1 to ADR-003, ADR-005 and ADR-006;
- ADR-004 amendment 1, which stays conditional on A08's spike.

**Status:**
- A05's revision 6 (account deletion and contract details) is still to be
  verified by A09; it does not change these ADRs' decisions.
- The go-live gates G-1 to G-6 remain open.

**Note to decision 46 (A09, K-23, 2026-11-14):** passkey sign-in is a way
back in for an imported 1.x account only **once a passkey has been
activated**. The launch notice to those users must not offer passkeys.

## 47. Account decisions approved; account scope frozen (2026-11-14)

The PO approved A09's advice:
- **PO-L:** account deletion is immediate, with a fresh confirmation made for
  `delete_account` (5 min, single use, DL-1).
- **PO-M:** hosted support never restores a deleted account. Support confirms
  a deletion only by writing to the account address. Operator deletion
  happens only on verified legal orders.
- **PO-N (DL-4):** R-12 is accepted. On accounts without 2FA, deletion is
  confirmed by a link sent to the account address.
- **PO-O (EC-1):** no "This wasn't me" revert link (R-AE rejected). On
  accounts without 2FA, the old address must also confirm an email change.

**Account scope is frozen.** Only these decisions are applied to the specs
and boards. Any new account case goes to a Phase 5 backlog
(`open-questions.md`) instead of a new spec/board/review cycle.

**Still open with the PO:**
- the alert channel;
- custody of the `age` identity for the deletion-ledger copy.

## 48. Gate 2 passed (2026-11-14)

The PO approved navigation and principal UX on the Timetable boards:
- canvas v47 and design system v16;
- A11 final QA READY WITH FIXES, all Majors fixed;
- Minors m4 and m6–m9 deferred to the build.

**Next:**
- A08 runs the Reverb / Echo spike that conditions ADR-004 amendment 1.
- Phase 3 (navigable prototype and the 10-second comprehension test with five
  developers) is proposed to the PO.

## 49. Phase 3 started (2026-11-14)

The PO started Phase 3:
- A04 builds a navigable prototype of the key flows from the Gate 2 boards.
- A01 updates the comprehension test kit to the final boards.
- The PO recruits five developers for the 10-second comprehension test
  (about 15 minutes each).
- Timetable is confirmed if the test passes. Notation stays the fallback.

## 50. ADR-004 amendment 1 confirmed by A08's spike (2026-11-14)

A08's spike (`spikes/live-props/SPIKE-REPORT.md`) returned **CONFIRMED WITH
CHANGES**. 22 tests pass, run twice. Under decision 46, ADR-004 amendment 1
is therefore **Accepted**; its spike condition is met.

**Changes required:**
- Both auth endpoints sign the principal into the subscription data.
- Server-side close of the principal's sockets after each terminating event.
- Client events set explicitly to `none`, with a config test.
- The per-principal connection cap and the max lifetime are our own code
  inside the Reverb process, because Reverb has neither.
- The client also detects a finished run on a timeline reload.
- Owner-only props are absent for share viewers, never lazy.
- The Reverb image needs `ext-uv` or `ext-event`: Reverb died at about
  1,014 sockets without it. Connection limits apply at Reverb and at the
  proxy.
- Long-polls are capped (503 + Retry-After).

**Owners:**
- A05 updates the event catalogue and contracts.
- A10 updates the image and the limits.

**Not covered by the spike:**
- a real browser client;
- numbers measured on the VPS;
- agent heartbeat cost;
- the capped long-poll on the production server stack.

These are tested in Phase 5.

## 51. Comprehension test skipped; Phase 5 (build) starts (2026-11-15)

The PO chose to skip the 10-second comprehension test and go straight to the
build.
- **Direction:** Timetable is kept as the direction without user validation.
- **Accepted risk:** the delivery graph's legibility is unproven with real
  developers (R-16). Mitigation: the run screen keeps the findings list and
  the delivery table alongside the graph. The first real users after launch
  are watched for confusion.
- **Leftover:** the Notion sign-up form and the prototype stay in place,
  unused. The PO may close or delete them.

## 52. Phase 5 repository and commit rules (2026-11-15)

**Repository layout:**
- The 2.0 code starts on a new branch, `phase5/foundation`, from
  `rewrite/v2`.
- The 1.x app code (`apps/`, `packages/`, the turbo and pnpm files, the PM2
  config, the 1.x compose file and 1.x-specific docs) is deleted there.
  1.x stays in git history and on `main`.
- Laravel is bootstrapped at the root, with the Go engine in `agent/`.
- `docs/` (the live Mintlify site) is not touched.

**Commits:**
- One local commit per finished slice, on phase 5 branches.
- Nothing is pushed, and no PR is opened.
- No AI attribution in commits.

**Planning docs:**
- `project/` docs are committed on `phase5/foundation`.
- `spikes/` stays untracked.
- Raw search dumps (`project/validation/raw/`) are not committed.
- The earlier accidental staging of about 1,087 files in the main checkout
  was undone (nothing had been committed).

## 53. Phase 5 runs in the main checkout (2026-11-15)

The PO asked for the work to happen directly in the project folder, not in
a worktree.
- The main checkout is now on `phase5/foundation`. The `p5-foundation`
  worktree was removed; the branch is kept.
- Leftover 1.x files were moved out of the repository, not deleted, to
  `~/workspace/personal/hookscope-1x-leftovers/`:
  - build output and `node_modules`;
  - the 1.x local `.env`;
  - the raw proxy-validation search dumps.
- Earlier worktrees (`p1b-*`, `p4-architecture`, `spike-proving-cli`,
  `hotfix-security`) are left as they are.

## 54. Self-hosted fonts (2026-11-15)

The PO approved installing `@fontsource-variable/archivo` and
`@fontsource-variable/martian-mono`. The app makes no request to Google
Fonts.

## 55. WebAuthn library approved, pending A09 (2026-11-15)

The PO approved installing `web-auth/webauthn-lib` 5.x for the WebAuthn
ceremonies (passkeys and security keys), **after** A09's slice 1 review.

If A09 recommends another library, the choice goes back to the PO.

## 56. Common-password list (2026-11-15)

The PO approved downloading the common-password list. The bundled list is:
- 100,000 entries of 12 characters or more, taken from Burnett's list as
  carried in SecLists (MIT);
- plus 53 hand-picked seed entries.

Provenance and licence files sit next to the list in `resources/security/`.

The PO confirms the licence before the first public release.

## 57. Visual direction: terminal (2026-11-15)

The PO found the Gate 2 "Timetable" look too plain ("basique, pas waouh"). A04 explored
three directions: product board, terminal, and bold red. The PO chose **Direction 2,
terminal**.

What changes:
- Martian Mono everywhere.
- Dark by default. The app keeps a light theme for preference and accessibility; the
  landing is dark only.
- A magenta live/status accent.
- PASS/FAIL badges.
- Findings shown as failing tests.

Reference mockup: `direction-2-terminal.html`, kept in
`project/design/directions/`.

Limit: the landing shows the fix as an illustrative diff. A real "before/after" diff in
the app would need the agent to read the user's code. That is a product change and is
not decided here; until then, the app shows the fix text only.

This supersedes the visual layer of the Gate 2 design system. Screen specs, flows,
accessibility rules and copy stay in force.

## 58. Account quotas and retention options (2026-11-15)

The PO keeps A06's proposed values (`config/workspace.php`):
- Account quota: 5,000 captures per day and 512 MiB stored per project.
- Retention choices: captures 1, 7, 14 or 30 days (default 30); runs 7, 30 or 90 days (default 90).
- No self-service restore of a deleted project. It is soft-deleted for 24 h, recoverable only by an operator, then purged.

## 59. User-chosen app layout; motion design on the landing (2026-11-15)

The PO wants each user to choose how the app is laid out, instead of the single top bar from Gate 2 navigation.

**Layout choices:**
- **Top bar.** The Gate 2 navigation: project, the tabs Capture · Runs · Fixtures · Agent, URL chip, agent pill.
- **Sidebar.** The same items in a left sidebar, expanded or collapsed to icons.

The choice is a preference, changed from the account menu and from a Preferences page.

**Storage:**
- **For now:** a cookie, per device, like the appearance setting. This works for guests too.
- **Later:** syncing across devices in the user's account is a later step.

The screens' content, routes and accessibility rules stay the same in every layout. Mobile keeps its own shell: a top bar plus bottom nav.

The landing page also gets real motion design. It must respect `prefers-reduced-motion` and add no new package without a proposal.

## 60. Guest storage cap (2026-11-15)

The PO approves a 50 MiB stored-bytes cap per guest session, as proposed by A06 for A09's S3-8.
The cap is set in `config/identity.php`. It comes on top of the 100-capture guest limit and the 5 GiB global guest budget.

## 61. HAProxy in front of the stack (2026-11-15)

For option O5 (A10, slice 3 runtime), the PO approves adding the official HAProxy image in front of the stack, in both Compose profiles. It provides:
- the per-address connection cap A09 asked for (S3-4);
- the backstop rate limit used while the app limiter fails open.

HAProxy is a new third-party image. A09 reviews its configuration, and A10 pins it by digest.

## 62. Live-update client packages (2026-11-15)

The PO approves adding these packages so the browser can receive Reverb live updates:
- `laravel-echo` 2.6.3 (Laravel first-party);
- `@laravel/echo-react` 2.6.3 (Laravel first-party);
- `pusher-js` 8.6.0 (the Pusher-protocol client that Reverb uses).

They are pinned to exact versions. A07 wires the Echo transport for Inertia Live Props. The channel authorizer sends `X-XSRF-TOKEN`.

## 63. Visible product first: run board next (2026-11-15)

The PO says the product isn't moving visibly. From now on we put visible screens first:
- Remaining server hardening is tracked and done before go-live, not now.
- Next is the run board: S-11 served as S-14 `/r/example`, from realistic demo data shaped like the run contract. The engine and agent will later feed the same components.
- Every step ends with something the PO can see: screenshots, a recording or a live page.

## 64. yaml.v3 in the engine; agent ↔ server connection next (2026-11-15)

- The PO approves adding `gopkg.in/yaml.v3` to the Go engine to read `webhooktrap.yml`. It replaces the small subset parser.
- The pure-Go SQL drivers are not approved for now. `sql:` probes keep pointing to `cmd: psql …`.
- Next comes the agent ↔ server connection:
  - pairing (S-06, S-08);
  - the `/agent/v1` protocol;
  - run jobs from the web app;
  - CLI and CI upload;
  - runs stored server-side and shown on the run board.

## 65. GitHub as remote; branches v1 and v2; 1.x removed from the VPS (2026-11-15)

- **GitHub** (`jiordiviera/webhooktrap`, public) is the active remote; GitLab stays as a mirror.
  - GitHub's `main` already equalled GitLab's `main` (1.x).
  - Pushed: `v1` (= 1.x `main`) and the GitLab-only `redesign-warm-workbench`.
  - **Not pushed yet:** `v2` (the 2.0 work). The repo is public, and decision 45 (no 2.0 publication until the licence is chosen), SecLists confirmation and R-14 still stand. The PO decides.
- **1.x removed from the VPS** (PO go):
  - PM2 `hookscope-api` stopped, deleted and saved; neighbours under the same PM2 untouched.
  - Port 3333 closed, so gate M holds.
  - The `api.webhooktrap.dev` vhost answers `410` (dated Caddy copy kept, validated, graceful reload).
  - The 1.x Redis keys (`bull:emails:*`, 19) are unlinked and the AOF rewritten. Redis itself stays up, because other apps reference it.
  - `/var/www/hookscope`, including its `.env`, is deleted, along with a stale PM2 pid file.
  - Every neighbour answers as before.
- **1.x still to retire, outside the VPS** (PO go and access needed):
  - Vercel project `webhooktrap` (it serves the apex and www);
  - the Neon database, R2 bucket and token, OAuth apps and Resend key;
  - the Cloudflare DNS records (`api`, `docs`, Vercel records).
- **VPS audit facts that change the plan:** 8 vCPU and **23 GiB** RAM (not 8 GB); Caddy 2.11.7; Docker 29.9.2 / Compose 5.6.1. Ports 18480 and 18481 are free. The admin API is on 127.0.0.1:2019 only. 5433 and 5000 are dropped in DOCKER-USER, and ufw is active. Only `<neighbour>` is in the docker group, and only `<neighbour>` in sudo.

## 66. Repo private until launch; 1.x web retired (2026-11-15)

- **GitHub repo:** `jiordiviera/webhooktrap` is now **private** until the 2.0 launch (PO choice A). `v2` is pushed there (4cae521).
- **Caddy:** `webhooktrap.dev` and `www` serve a static "1.x retired, 2.0 coming" page from the VPS. A dated copy of the Caddyfile was taken, the config validated and Caddy reloaded gracefully; Let's Encrypt certificates were issued.
- **Cloudflare DNS for webhooktrap.dev:**
  - apex and www now point to the VPS (A record, proxied);
  - removed: the Vercel A records (apex, www, wildcard `*`) and `_domainconnect`;
  - kept: `api` (410), `docs` (Mintlify), email routing, the Resend/SES records and CAA.
- **Vercel:** the project `webhooktrap` is deleted (PO go).
- **Still to retire, with the PO's access:** the Neon database, the R2 bucket and token, the 1.x OAuth apps, the 1.x Resend key, and the `hookscope.jiordiviera.me` record in the jiordiviera.me zone if it still points to Vercel.

## 67. v2 is the main branch (2026-11-15)

On GitHub, `main` is deleted. `v2` is the default branch, and `v1` keeps 1.x at the same commit `main` had (27e9d98).
Locally, `main` is renamed to `v1`. Workflows and docs refer to `v2`.

## 68. AGPL-3.0, public v2, Buy Me a Coffee (2026-11-15)

- **Licence:** the PO chooses **AGPL-3.0** for Webhook Trap 2.0. This closes decision 45.
  - `LICENSE` holds the AGPL-3.0 text.
  - `composer.json` says `AGPL-3.0-only`.
  - The README and the landing say "Open source (AGPL-3.0)".
  - The bundled SecLists list (MIT) is compatible.
- **Going public (PO option B):**
  - Before publishing, the details of the shared VPS (neighbour apps, users, ports, paths) move to the private ops notes (`webhooktrap-private/operations/`): `vps-facts.md`, `contabo-deployment-plan.md` and `1x-decommission-runbook.md`. Remaining mentions in tracked docs are redacted.
  - The public `v2` starts from one squashed commit, so earlier revisions of those details are not published. The full local history stays on the private local branch `phase5/foundation`.
- **R-14** (the Twice logo resemblance check) is still open. The PO publishes with it open.
- **Support link:** "Buy me a coffee" (`https://buymeacoffee.com/jiordiviera`) goes on the landing and on a few pages, with an svgl TSX icon.

**2026-11-15 follow-up:** the repo is **public**. `v2` (726d3dc, then f97ef5b and c33d55f) is the default branch, CI and the signed container are green on 726d3dc, and the earlier v2 workflow runs are deleted. Residual: the commits from before the squash are unreferenced on GitHub, but their objects can stay reachable by SHA until GitHub garbage-collects them.

## 69. No accepted residuals on the shared host; deploy now (2026-11-15)

The PO accepts no risks in writing. G-2: the monitoring agent's host user is removed from the `docker` group. Its system metrics keep working; container metrics from it stop until a read-only socket proxy is proposed. The PO asks for the 2.0 deploy now. The database is created by the deploy itself: the Postgres container plus migrations.

## 70. First-deploy readiness; passkeys on the apex (2026-11-16)

- **Deploy blockers closed** (A06 8855d9e, 48c37ac; A08 8ab6e92; A10 cac3086, 0cc98c6, 3f80c2c):
  - app health commands, `/health` fields, ledger re-apply and runner tokens;
  - engine `serve-sandbox`, `sandbox-ping` and `serve-run`;
  - the C3 finish script, the hosted env template, the deploy env-file fix and the engine socket directory.
- **GHCR:** the three images (`webhooktrap`, `-runner`, `-postgres`) are public (PO). The box needs no registry token.
- **DNS:** `in` and `ws` A records, DNS only, like `app`.
- **WebAuthn RP ID:** the PO chooses the apex `webhooktrap.dev`, so passkeys survive a change of app subdomain. The allowed origin stays `APP_URL` only.
- **A00 sign-off:** `backup` is left out of deploy.sh's final health gate. The pre-migrate backup already gates on it, and `/health` watches it.
- **Open after the deploy:**
  - the PHP EngineProcess adapter;
  - runner contracts (executor `server`, a decline/abort route, the secrets response);
  - the self-host runner origin under TLS_MODE=auto;
  - R2 and the ledger age recipient (they block the second deploy), mail and OAuth.

## 71. 2.0 live on app., in. and ws. (2026-11-16)

- **First deploy** of 40f2348 succeeded (PO approval in GitHub). All services are healthy. `backup` restarts until R2 is set.
- **Host Caddy (C7, PO GO):** the provisional blocks are replaced by the Webhook Trap site file (private ops note). Certificates are issued, and the post-reload checks pass. The other apps answer.
- **OAuth:** the GitHub OAuth app and the Google client "Webhook Trap" exist (the Google client in an existing GCP project, because the project quota is reached). Client ids are in `.env.production`, and the PO wrote the secrets on the box. The Google consent screen is in production, with no logo, so no verification is needed. The home, privacy and terms links point to app.
- **Next deploy is blocked by R2:** the pre-migrate backup needs it. The OAuth settings and the new /privacy and /terms pages wait for that deploy.

## 72. Transactional email through PulseSend (2026-11-16)

- **Provider:** the PO chooses **PulseSend**, the PO's own service, through the PO's package `pulsesend/pulsesend-laravel` (`^0.1`, MIT; it pulls `pulsesend/pulsesend-php`). The PO approves this new package.
- **Guzzle:** the released `pulsesend-php` v0.1.0 requires Guzzle `^7.8`, so the lock moves from Guzzle 8.5 to 7.16 (no app code uses Guzzle directly). The SDK's Guzzle 8 support is in the PulseSend repo but not released; once `pulsesend-php` v0.1.1 is tagged, `composer update` restores Guzzle 8.
- **Mailer:** `config/mail.php` has a `pulsesend` mailer. The default stays `log`. Production sets `MAIL_MAILER=pulsesend`; `MAIL_FROM_ADDRESS` stays `no-reply@webhooktrap.dev`.
- **Key as a file:** `PULSESEND_API_KEY` comes from the Compose secret `pulsesend_api_key`, optional like `mail_password`. While it is empty, the default mailer falls back to `log`, so nothing is sent and nothing fails.
  - Mounted on `horizon`, which sends the queued mail, and on `app`, which builds the mailer when it queues a mailable (the queued job keeps that mailer's name). Not on `scheduler`: it only dispatches jobs.
  - In the entrypoint's `_FILE` list, the policy (P-5, P-14, P-20), CI, `c3-finish.sh` and `.env.example` (names only).
- **Webhook route off:** the package's `POST /pulsesend/webhook` is not used. `config/pulsesend.php` sets `routes` to false by default; a test checks that the path answers 404.
- **Before the next deploy (host, PO go):** reinstall the on-box policy (`c3-finish.sh --install-bin`), write `secrets/pulsesend_api_key`, and set `MAIL_MAILER=pulsesend` in the box's `.env.production` (private ops note, C3 log).

## 73. The app stays on app.webhooktrap.dev; the apex redirects (2026-11-16)

- The PO keeps `app.webhooktrap.dev` as the app host. `in.` and `ws.` stay separate hosts (HD-7, TM-04).
- `webhooktrap.dev` and `www` answer with a 301 to `https://app.webhooktrap.dev{uri}` on the host Caddy. There is no HSTS on the apex, because `includeSubDomains` would break plain-http captures on `in.`.
- Moving the app to the apex later stays possible: the WebAuthn RP ID is already the apex (decision 70).
- Also on 2026-11-16: BIMI is ready (DMARC `p=quarantine`, `default._bimi`, an SVG Tiny PS logo; no VMC, so Gmail and Apple Mail show no logo). The 1.x GitHub OAuth app, R2 bucket `hookscope` (with `cdn.jiordiviera.me`) and its admin token are deleted. Neon and Resend wait for the PO's sign-in.

## 74. Operator admin panel on admin.webhooktrap.dev, Filament v5, read-only (2026-11-16)

- **PO decision:** an operator panel built with **Filament v5** (`filament/filament` ^5, which brings Livewire v4) on **admin.webhooktrap.dev**. The PO approves both packages. v1 is strictly read-only: no create, edit or delete action anywhere.
- **Scope v1:** accounts (search; masked addresses except the one typed in full; created, verified, TOTP, passkeys/keys, OAuth providers, last sign-in, claimed guest sessions), each account's projects with quotas and usage, an operations dashboard (`/health` checks, captures per day, active runs, connected agents and polling runners, backup ages and WAL archive lag, failed sign-ins over 5 min and the alert level, store failures, live guest sessions), paired agents, server runner tokens (name, last 4, networks, last poll) and the agent and runner versions in use. Never a secret, hash, recovery code, token, key or capture body.
- **Code:** a new module `app/Modules/Admin` (Filament custom pages and widgets, array-backed tables through `Table::records()`), fed by read models in each module's public `Application` layer. No Filament resource, so no Eloquent model crosses a module; the module is in `tests/Architecture/Modules.php` like the others. Identity's `User` implements Filament's `FilamentUser` and `HasName` contracts (code-layout.md).
- **Access (PO choice: flag + passkey):** `users.is_operator`, set only by `php artisan operator:grant {email}` / `operator:revoke {email}` (audited; guarded against mass assignment; no HTTP route). The panel admits an account only with the flag, a verified email, a live account and a second factor (TOTP or an unflagged passkey or security key), on every request including Livewire updates.
- **Sign-in through the app, by handoff:** the session cookie is `__Host-` and host-only, and WebAuthn's allowed origin is `APP_URL` only, so the admin host has no sign-in of its own. Its login route sends the browser to `app./operator/handoff` (sign-in if needed, then a fresh confirmation with a second factor: confirm-auth action `operator` refuses a password). That route mints a 256-bit token (SHA-256 stored in the shared store, 60 s, single use, bound to the user and to the minting `user_sessions` row) and redirects to `admin./handoff/{token}` with `Referrer-Policy: no-referrer`. The admin host spends it, re-checks eligibility and that the app session is still live, and starts its own session with a `user_sessions` row (`first_factor` `operator_handoff`, visible and revocable in Account › Security).
- **Admin session:** idle limit 15 min (`ADMIN_IDLE_SECONDS`), no widget polling, revocation honoured on every request; sign-out revokes the row and lands on the app. Every page view is written to `audit_log` as `operator_page_viewed` (actor, path without query, time); grant, revoke and handoff issue are audited too.
- **Isolation:** panel routes are bound to `ADMIN_HOST`; without it no panel route exists. On the admin host every app route answers 404, and Livewire's update endpoint (plus Livewire and Filament support routes) answers 404 everywhere else. Admin responses carry `X-Robots-Tag: noindex`, a robots meta tag, `no-store`, `X-Frame-Options: DENY`; its `/robots.txt` disallows everything. The app CSP is `frame-ancestors 'none'` only, so Filament's inline scripts and styles need no relaxation. No third-party request: local Inter font, inline initials avatar instead of ui-avatars.com.
- **Livewire state:** Livewire remembers in a singleton the route whose persistent middleware (Filament's `Authenticate`) it last applied and skips it for the same route, and never resets that itself. The panel provider calls `Livewire::flushState()` after every request, so a reused application (worker mode, the test client) cannot let a later update skip the access check (test: a revoked operator's update answers 403).
- **Image:** the Dockerfile build stage must run `php artisan filament:assets` after `composer dump-autoload` (it publishes `public/js|css|fonts/filament`, git-ignored). The image Caddyfile already sends every non-capture, non-ws host to PHP behind the same trust snippet, so `X-WT-Edge` verifies on admin. as on app.
- **Host and DNS (A00, PO go):** an `admin` A record like `app` (DNS only); a host-Caddy site block for `admin.webhooktrap.dev` identical to the `app.` block except the address (same upstream, same `X-WT-Edge` header from the edge secret, same forwarded headers, Host preserved); `ADMIN_HOST=admin.webhooktrap.dev` in `.env.production`.

## 75. docs.webhooktrap.dev serves the 2.0 documentation (2026-11-16)

- **PO decision:** the 1.x Mintlify site in `docs/` is replaced by the 2.0 documentation. This lifts the CLAUDE.md rule "docs/ is the live Mintlify site: do not touch"; `docs/` is now the 2.0 site, edited with care (CLAUDE.md updated).
- **Rebuilt from zero:** every 1.x page, asset, `mint.json`, `style.css` and the Mintlify starter `LICENSE` (MIT, not the project's) are removed. New `docs.json` (name "Webhook Trap", magenta `#b12083` / `#e977bb`, ground `#141411`, Archivo, logo and favicons from `public/brand`, GitHub and "Open app" in the navbar).
- **Content only from real sources:** the agent code (`agent/cmd/webhooktrap`, `internal/config`, `plan`, `judge`, packs 1.0.0), `config/*.php`, `routes/`, contracts.md, the scenarios-and-judge spec, compose.yaml and compose-profile.md, agent-release.md and `agent/install.sh`. Gaps are reported, not filled.
- **Screenshots:** taken with Playwright (`docs/scripts/screenshots.mjs`, dev dependencies `playwright-core` and `sharp` local to `docs/scripts/`): public pages read-only from production, signed-in screens from the local lerd site with a demo account and a real agent run against a deliberately buggy demo handler. Nothing is created on production.
- **Deploy:** Mintlify deploys on every push to `v2` (folder `docs`). A00 reviews and pushes.

## 76. EngineProcess adapter, slice 1: server-side plan preview (2026-11-17)

- **PO go** on slice 1 (plan only), run by A06 on Opus 5.5 (C4: untrusted engine output, S-11, TM-18, TM-74). `verify` is slice 2.
- **Adapter** `app/Infrastructure/EngineProcess`: `Engine` port bound in `AppServiceProvider` (like `Clock`), `SocketEngine` speaks the sandbox frames (4-byte length + JSON, one NDJSON reply ≤ 1 MiB, `proto` 1, one 8 s deadline through `Clock`), `ReplyDecoder` checks the reply like untrusted input (exact keys and types, bounded numbers, codes only, refs among the request's). Socket from `config/engine.php` (`ENGINE_SOCKET`, default `/run/engine/engine.sock`).
- **Runs/Draft:** `preview` and `previewError` are lazy props. The draft plans all 9 scenarios once (measured: 5 captures → 23 deliveries, under CAP-1's 50); the page adds up the ticked ones plus `once`, which the engine always plans (PLAN-2). TM-18: 20 engine calls per principal per minute (cache hits free), cache 10 min keyed by capture refs, orders, body and header hashes, scenarios, pack; transport failures are not cached. Engine down: `preview` null with a code, the page works as before.
- **Launch:** `delivery_count` is stored from the plan when the engine answers; else jobs send null as before. The agent's observations still overwrite it.
- **Local dev:** a dynamically linked engine binary fails under lerd's PHP container (musl loader re-exec); build with `CGO_ENABLED=0`, as the Dockerfile does. The real-binary test skips when `agent/bin/webhooktrap` is absent.

## 77. R-14: the Twice resemblance check, visual part done (2026-11-17)

- **Visual check:** Twice was set side by side with the NS mark (Wikimedia Commons), Svix (svix.com), ngrok (svgl) and Hookdeck (hookdeck.com). None is close:
  - NS is a closed double arrow in one blue shape;
  - Svix is an S in a hexagon, ngrok a wordmark, and Hookdeck two linked rings.
- **British Rail double arrow** stays the closest family (two arrows across two parallel lines). Twice keeps its distance: no arrowheads, parallel paths and not a zig-zag, both rails starting at the same edge, and the red square. The misuse rule that forbids arrowheads stays.
- **Name:** web search found no "Webhook Trap" filing. The nearest records are WEBHOOK (USPTO 86015060, abandoned in 2014) and WEBTRAP (USPTO 75507992, cancelled in 2007). These come from third-party trademark sites, not the official registers.
- **Still open:** a search in the official registers (INPI, EUIPO, USPTO and the WIPO Global Brand Database, including image search) before any filing. It does not block a LinkedIn post.
- **PO's call:** whether this closes R-14 for public use.
