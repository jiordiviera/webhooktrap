# Phase 1b — Recommendation for the product owner

**Author:** A00 · **Date:** 2026-11-11 · **Status:** REVIEW_REQUIRED.
Nothing in this document is approved before the PO's review.

## 1. What was produced

| Deliverable | Where | Author |
| ----------- | ----- | ------ |
| Problem research (45 sources), problems, personas | `.worktrees/p1b-product/project/product/` | A01 |
| 4 product concepts, vision, positioning, core experience, open questions | same | A01 |
| 3 brand territories | `.worktrees/p1b-design/project/design/brand-territories.md` | A02 |
| UX foundations, UX per territory, shared realistic dataset | `.worktrees/p1b-design/project/design/` | A03 |
| 3 directions in **Claude Design**: 3 Design Systems + 3 canvases, 91 artboards | links below | A04 ×3 |
| Product fit review | `fit-review.md` | A01 |
| Static QA of all canvases | `qa-static-review.md` | A11 |
| ADR impact of the concepts | `project/architecture/concept-impact.md` | A00 |

| Direction | Design System | Screens canvas |
| --------- | ------------- | -------------- |
| I · Timetable | https://claude.ai/artifact/7n63VUDFZC31A1bRTJfwNY | https://claude.ai/artifact/3ovs7azTxTzGFREDuBe6En (31) |
| II · Crash Lab | https://claude.ai/artifact/NZYTD3LgZ8eBeDmQMFQTJo | https://claude.ai/artifact/EBrZR6RqBwwg18w45P7c2J (31) |
| III · Notation | https://claude.ai/artifact/HNKgCdYHshzD5N7xieZhnf | https://claude.ai/artifact/XXvbGdE7vo62VpDGXWUfqn (29) |

## 2. Evidence and its limits

**What A11's static QA confirms.**
- All 91 published artboards are identical to their local sources.
- They follow the format, every `{{hole}}` resolves, and every import exists.
- Every required screen and state is present.
- Data fidelity is 71–75 of 78 checked values per direction, with nothing
  invented.
- Contrast is at least 4.5:1 for all text in both themes.
- There are no blockers.

**What has not been checked.** No agent has seen the screens rendered,
because the built-in browser has no claude.ai login. The PO's own viewing is
the first visual check.

**What A01's fit review relies on.** It is a judgement made from the source
markup. Comprehension, especially of Timetable's graph and Notation's glyphs,
needs real users.

## 3. Recommendation

### Product

**Confirm concept B, "Proving Ground": the test suite for webhook
handlers.** Concept A, the explaining debugger, is its first minute. Concept
C, production monitoring, is Horizon 2.

- **Why B:** it attacks the problem ranked highest (handlers that break under
  duplicate, out-of-order, late or retried deliveries). It is the least
  served, and it fits open source, self-hosting and CI.
- **The condition:** run the cheap validation (Q13) before any build. That
  means 6–8 interviews plus a two-week CLI prototype. The kill criterion is
  that fewer than about 20% of first runs find something users call real; if
  so, fall back to positioning A.

### Visual direction

**Develop I · Timetable as the lead direction, with two borrowings.** A01
ranks it first (30/35), A02 recommended it, and I agree.

- **Why Timetable:**
  - It uses the product's own words everywhere.
  - Its hero image is the demo.
  - It is the only direction that extends to Horizon 2 monitoring without a
    rebrand.
  - Its main risk, whether people can read the graph, is cheap to test.
- **Borrow from Crash Lab:**
  - the per-scenario result cells, which show the whole run at a glance;
  - the "Expected 1 · got 2" gauge.
  Both read faster than the graph alone.
- **Borrow from Notation:**
  - its copy voice, where the commentary carries the fix;
  - its compact text format for the CLI, CI and pull-request output, which
    no direction has drawn yet.
- **Keep Notation as the fallback** if Timetable fails the 10-second
  comprehension test.
- **Drop Crash Lab as a whole direction.** It renames product terms, its tone
  works against "calm and precise", and it does not extend to Horizon 2.

**What would change this:**
- The PO finds Timetable too austere on viewing.
- The comprehension test fails.
- The PO prefers Crash Lab's louder launch image.

### Work before Gate 1 can pass (after the PO picks a direction)

Applies to the chosen direction:

1. Remove the visible placeholders ("VERIFY", "[TO CONFIRM]", "[LICENCE]").
2. Verify provider response budgets before stating them, starting with
   Stripe's 10 s.
3. Rewrite the Passed line so it does not overclaim.
4. Use one word for the provider budget.
5. Draw the missing screens:
   - the guest's first minute with no agent paired;
   - the CLI output;
   - the CI and pull-request output.
6. Fix the minor QA findings: incomplete ARIA patterns, the stale
   design-system record, and the Timetable glyphs that depend on `data:`
   masks.
7. Run a 10-second comprehension test with five developers on the Run screen.

## 4. Decisions required from the PO

1. **Q1: confirm concept B** as the defining job of 2.0. A, C or a discussion
   are the alternatives.
2. **Choose the lead direction:** Timetable (recommended), Notation or Crash
   Lab, with or without the proposed borrowings.
3. **Q13: validation first.** Interviews plus a CLI prototype, yes or no.
4. **If B is confirmed:**
   - Agent in the MVP (Q2).
   - Draft the new ADR "Local agent and client-side scenario engine".
   - Draft the scoped amendments to ADR-003, 004 and 005
     (`concept-impact.md`).
5. **Can wait until Gate 1 closes:**
   - Q3 agent language.
   - Q4 secrets.
   - Q6 open-source boundary.
   - Q7 launch providers.
   - Q9 guest quotas.
   - Q12 name.
   - B-05 and B-06.

## 5. Not started, by design

- Phase 2.
- Any Laravel, React or Inertia implementation.
- Any merge of the Phase 1b worktrees.
- The hotfix track, which stays independent (11 local commits, nothing
  pushed or deployed).
