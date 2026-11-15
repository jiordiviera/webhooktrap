# Validation (Q13): start here

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-14

**Question:** do realistic deliveries (twice, out of order, late, tampered, slow) find problems in real handlers that developers call real? If fewer than about 20% of first runs do, fall back to positioning A (decision 27).

**How:** 6–8 Integrator interviews (45 min) with a 10-second Timetable test and a run of a throwaway CLI on the participant's own handler. Two weeks, decision on Mon 30 November.

| File | What it is |
| ---- | ---------- |
| [validation-plan.md](validation-plan.md) | Hypotheses H1–H5, thresholds, go / pivot / stop rules, timeline, roles, biases |
| [interview-kit.md](interview-kit.md) | Screener, channels, consent, 45-min script, notes and synthesis templates |
| [prototype-spec.md](prototype-spec.md) | The throwaway CLI: scope, measurements, safety, language, acceptance criteria |

## Decisions the PO must take (Monday 16)

1. **Recruiting channels** (interview-kit §1.2). Recommended: PO network (max half), communities the PO already belongs to, people who wrote publicly about a webhook bug. Paid panel if speed matters more than cost.
2. **Incentive** (interview-kit §1.3). Recommended: one gift card of €40–60, the same for everyone.
3. **Prototype go-ahead** (decision 27 asked for it separately): scope as in prototype-spec §3, built on branch `spike/proving-cli`, never merged.
4. **Prototype language** (prototype-spec §6). Recommended: **TypeScript on Node**. It doesn't decide Q3. Choose Go only if you want Q3 evidence and accept ~3 days' delay.
5. **Optional:** allow capture through a 1.x inbox (test mode only)? Recommended: no.
6. **Freeze the thresholds** in validation-plan §3 as they are, or change them now, before the first session.

## What the PO must do personally this week

- Mon: take the decisions above. Send the first invitations (agents can't send messages for you).
- Tue–Wed: screen the replies, book 2 pilot sessions (Thu 19, Fri 20) and 6 sessions for Mon 23 – Thu 26.
- Thu–Fri: run the 2 pilots (discovery + 10-second test, no prototype). Before each, open the canvas https://claude.ai/artifact/3ovs7azTxTzGFREDuBe6En and prepare S1–S3. **Screen-share it; don't send the link.**
- After each session: write the anonymised notes file (interview-kit §4) within 2 hours. Keep recordings and names outside the repo.

## What agents do this week

- A00: assign the prototype builder; log the decisions.
- Builder: prototype per spec, acceptance criteria met by Fri 20.
- A09: 30-minute safety review of the prototype, Fri 20.
- A01: per-session synthesis, results table, recommendation memo for Mon 30.
- A03 + A01: score the 10-second test independently.

## One thing to know

The kit runs the **10-second test before the prototype run**, not after. `ux-timetable-v2.md` §5 requires participants who have never seen Webhook Trap, and the prototype prints the same run format. Running it first keeps the test valid.
