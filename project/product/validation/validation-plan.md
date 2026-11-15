# Validation Plan: Proving Ground (Q13)

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-14
**Decisions this serves:** 24 (concept B confirmed), 27 (validate before building). Kill criterion from [vision.md](../vision.md#kill-or-pivot-criteria). Risk from [open-questions.md](../open-questions.md) Q5 and Q13.
**Companions:** [interview-kit.md](interview-kit.md) · [prototype-spec.md](prototype-spec.md) · [README.md](README.md)

## 1. Goal

Find out, in two weeks and before any 2.0 code, whether replaying real webhook events the way providers deliver them finds problems that developers agree are **real**, often enough to carry the product. If it does not, fall back to positioning A (the explaining debugger) before the build starts.

**What this plan does not test:** pricing, the hosted service, Horizon 2 (monitoring), the brand, or the agent's final language. It collects signals for some of these (§3, "side signals"), but none of them decides go or no-go.

## 2. Hypotheses

| # | Hypothesis | Why it matters | Main evidence |
| - | ---------- | -------------- | ------------- |
| **H1** | Integrators' handlers break under realistic delivery (twice, out of order, late, tampered, slow). | It is the problem behind concept B. If handlers hold up, there is no product. | Prototype first runs + interview stories |
| **H2** | Transport-only checks (Level 1: status, latency, response consistency, accepting bad input) find issues users call real, with zero setup. | Q5. The aha has to come without configuration. Level 1 is the free first run. | Prototype first runs, Level 1 findings only |
| **H3** | Integrators accept a local agent (a CLI on their machine that holds the signing secret and calls localhost). | The ADR-006 challenge and the MVP scope depend on it. | Install behaviour + interview answers |
| **H4** | Integrators would run it in CI from committed fixtures. | The habit loop (M9). Without it, the tool is used once per integration. | Commitment questions + 7-day follow-up |
| **H5** | The Timetable run screen and landing hero are understood in 10 seconds. | The lead design direction. Protocol owned by A03, defined in `ux-timetable-v2.md` §5. | 10-second test in the same sessions |

H1 and H2 decide the direction. H3 and H4 shape the MVP scope. H5 decides the design direction only.

## 3. Metrics and thresholds

Thresholds are **frozen before the first session**. Changing them afterwards requires a PO note in the decisions log, with the reason, before results are discussed.

### 3.1 Definitions

- **First run:** the first complete prototype run against a participant's **own** handler (not a sample app), with the default scenarios. Repeat runs are logged but don't count for the rate.
- **Finding:** anything the prototype flags at Medium or above.
- **User judgement per finding**, asked right after the run (interview-kit §3.5):
  - **Real:** "This would matter in production, I'd want it fixed." Includes things they already suspected but never fixed.
  - **Known and accepted:** a deliberate choice, e.g. "signature check is off in dev on purpose, it's on in prod". **Not real** for the rate.
  - **False positive:** the tool is wrong (e.g., the latency was the laptop, not the handler).
  - **Unsure.** Counted as not real.
- **Behavioural check on "real":** "Would you fix this before your next release?" A "real" without a yes is recorded as **real (soft)**. The rate is reported both ways; the decision uses the strict rate (real + yes).
- **Finding rate** = first runs with ≥ 1 strict-real finding ÷ first runs.

### 3.2 Thresholds per hypothesis

| # | Metric | Go | Grey zone | Fail |
| - | ------ | -- | --------- | ---- |
| H1 | Finding rate, any level (Level 1 + probe where used) | ≥ 40% (vision target) | 20–39% | < 20% (kill criterion) |
| H1 | Interview: participants who recall a delivery-semantics incident or near miss (duplicate, order, timeout, forged input) | ≥ 4 of 8 | 2–3 | ≤ 1 |
| H2 | Finding rate, Level 1 findings only | ≥ 30% | 20–29% | < 20% |
| H2 | False-positive share of all findings | ≤ 20% | 21–35% | > 35% |
| H2 | Time from run start to the first finding printed | median ≤ 60 s | 61–180 s | > 180 s |
| H3 | Participants who install and run it on their own machine during or before the session | ≥ 6 of 8 | 4–5 | ≤ 3 |
| H3 | Participants who would keep a local agent holding their signing secret (no hard objection) | ≥ 6 of 8 | 4–5 | ≤ 3 |
| H4 | Concrete CI intent: names the pipeline and the step, and agrees to a follow-up | ≥ 3 of 8 | 2 | ≤ 1 |
| H4 | Behaviour: ran it again (or in CI) by the 7-day follow-up | ≥ 2 | 1 | 0 (weak signal only, never decides alone) |
| H5 | Pass marks as defined in `ux-timetable-v2.md` §5 | S1 and S3 pass | S2 below 3 of 5 | S1 or S3 fails → round 2 or fallback, per §5 |

**Small numbers.** With 6–8 first runs, one person moves the rate by 12–17 points. So:
- **Minimum sample for a decision: 8 first runs.** If the sessions give fewer (some participants can't run it on their own code), top up with **async runs** from screened volunteers who send back only the anonymised run summary and the per-finding judgement (prototype-spec §5). Target 10–12 first runs in total.
- Report counts, not just percentages ("3 of 10").
- In the grey zone, add 5 more first runs before deciding, if the timeline allows a third week.

### 3.3 Side signals (recorded, not decisive)

- Which scenario produced each strict-real finding (Tampered, Late and Slow budget are expected to dominate at Level 1; Twice and Shuffled need the probe).
- Share of participants who agree to add a state probe after a Level 1 run, and how long it takes (Q5).
- Install friction: Node present? Time to first run? (Q3.)
- Fixture scrubbing worries (interview question 5).
- Who would pay, for what (interview question 6). Watch for the Owner signal in vision.md's second pivot rule.

## 4. Decision rules

Applied by the PO at the end of week 2, on the synthesis table in interview-kit §6.

| Outcome | Condition | What happens |
| ------- | --------- | ------------ |
| **GO: build B** | H1 finding rate ≥ 40% **and** H2 not failed **and** H3 not failed | Proceed to Gate 1 with B. The agent is in the MVP. A05 and A09 start the "Local agent" ADR. |
| **GO, probe-first** | H1 ≥ 40% only because of probe findings, H2 in grey or failed, users accepted the probe | Build B, but onboarding leads with the probe (Q5 becomes "probe in the first run"). Rework M5–M7 copy. |
| **Grey: extend** | H1 20–39% | Add 5 first runs (week 3). If still below 40%, decide between GO probe-first and pivot on the false-positive share and H3. |
| **PIVOT to A** | H1 < 20% after ≥ 8 first runs | Fall back to positioning A (the explaining debugger). Proving scenarios become a feature inside A, not the headline. Redo positioning.md and the landing story. |
| **Invert to C** | Integrators fail H1 **and** ≥ 3 participants with an Owner role describe paying for production silence or drift alerts | Second pivot rule in vision.md: start with the Flight Recorder sidecar. |
| **STOP** | H1 interview signal fails (≤ 1 of 8 recall any webhook incident) **and** nobody reports signature or debugging pain worth a tool | Pause 2.0 product work. Keep 1.x maintained. Return to research before any rewrite. |

**H3 failing alone** does not kill B. It moves the agent to a hosted-only first release (public URLs only) and pushes localhost to 2.1, which is the original ADR-006 timeline.
**H4 failing alone** does not kill B. It removes CI from the MVP pitch and keeps it as a later feature.
**H5** never changes the product decision. It only triggers the design fallbacks in `ux-timetable-v2.md` §5.

### Early stop during the study

- After the **first 4 first runs**, if none has a strict-real finding: stop recruiting for prototype runs. Use the remaining sessions for A-positioning discovery (signature debugging, "why did this fail") and the 10-second test.
- If a prototype run causes harm on a participant's machine (unwanted side effects, data change outside test mode): pause all runs until the safety issue is fixed and reviewed.

## 5. Timeline (2 weeks)

Dates assume decisions on Monday 16 November 2026.

| When | PO | Agents |
| ---- | -- | ------ |
| **Mon 16** | Decide channels, incentive, prototype go-ahead and language (README). Send the first recruiting messages. | A00 assigns the prototype builder. Builder starts (prototype-spec §9). |
| **Tue 17 – Wed 18** | Screen respondents, book sessions for Thu 19 → Thu 26. | Builder: record and replay, Once/Twice/Tampered, Stripe signing. A01: prepare notes files and the synthesis sheet. |
| **Thu 19** | Pilot session 1: discovery + 10-second test only (no prototype yet). Fix the script. | Builder: Shuffled/Late/Slow budget, GitHub and Standard Webhooks signing, run summary file. |
| **Fri 20** | Pilot session 2 (same scope). Dogfood the prototype on a sample handler with known bugs. | A09: 30-minute safety review of the prototype (localhost-only default, no network calls). Builder fixes. A01: synthesise pilots. |
| **Mon 23 – Thu 26** | Sessions 3–8 with prototype runs. Send async-run invites to screened volunteers. | A01: synthesis after each session (same day). A03 and A01: score the 10-second test independently. Builder: fixes only, no features. |
| **Fri 27** | Last sessions or catch-up. 7-day follow-ups go out for early participants. | A01: draft results and recommendation, with the go/no-go table. |
| **Mon 30** | Go / probe-first / extend / pivot / stop decision, logged as a decision. | A00 updates blockers B-07 and the Gate 1 plan. |

Pilot participants count toward H1 interview signals and H5, not toward the finding rate (no prototype run).

## 6. Roles

**The PO must do personally** (agents cannot hold calls, send messages for the PO, or give consent):
1. Choose recruiting channels and the incentive, and send every outreach message.
2. Run all sessions (moderate, screen-share the Timetable frames, watch the prototype run).
3. Collect consent at the start of each session and own any recording.
4. Make the comprehension canvas available: open https://claude.ai/artifact/3ovs7azTxTzGFREDuBe6En and screen-share it; share it with A03 if A03 scores from recordings. Do not send the link to participants.
5. Approve the prototype (scope, language, branch) and, if participants install from GitHub, push the spike branch.
6. Send the 7-day follow-ups.
7. Take the final decision, against the thresholds frozen here.

**Agents:**
- **A01:** this kit, per-session synthesis from the PO's anonymised notes, results memo.
- **Prototype builder (assigned by A00):** builds the throwaway CLI per prototype-spec.md. No other code.
- **A09:** safety review of the prototype before the first external run.
- **A03 + A01:** independent scoring of the 10-second test, from the PO's verbatim notes or recordings.
- **A00:** schedule, blockers, decision log entries.

## 7. Risks and biases

| Risk or bias | Effect | Mitigation |
| ------------ | ------ | ---------- |
| **Founder as interviewer** (confirmation bias, sunk cost) | Hears the yes, discounts the no | Thresholds frozen in advance. Strict "real" needs a fix-before-release yes. A01 synthesises from notes, not from the PO's impressions. Disconfirming quotes get their own section in the memo. |
| **Friendly sample** (PO's network) | Politeness inflates "real" and CI intent | At most half of participants from close contacts. Ask about past behaviour, not future intent. |
| **Pitching before discovery** | Participants adopt the PO's framing of the problem | Script runs discovery first, no product talk until §3.3. No mention of duplicates or ordering before the participant raises them. |
| **Exposure contaminates the 10-second test** | §5 requires participants who have never seen Webhook Trap | Run the 10-second test **before** the prototype run and before any product description (interview-kit §3). |
| **Easy findings** (signature check off in dev) | Tampered or Late "findings" users dismiss, or count as real without caring | "Known and accepted" category. Report findings by scenario. |
| **Environment noise** | Slow budget flags laptop slowness | Slow budget reports a finding only above the provider budget **and** ≥ 3× the run's median latency. Users judge it. |
| **Skeleton handlers** | Test-mode handlers with no logic produce no findings | Screener requires a handler that writes to a database or calls another service. |
| **Unsafe runs** | Twice or Burst triggers real emails or charges | Test mode only. Side-effect warning before Twice. Localhost only by default (prototype-spec §6). |
| **Small n** | Noise looks like signal | Minimum 8 first runs, counts reported, grey zone extends the study. |
| **Say/do gap on CI** | Stated intent overestimates use | 7-day follow-up as the behaviour check. H4 never decides alone. |
| **Recruiting slips** | Fewer than 8 sessions in 2 weeks | Async runs as a top-up. Extend by one week before deciding on thin data. |
