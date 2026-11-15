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
