# Interview Kit: Integrator Sessions (Q13)

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-14
Plan and thresholds: [validation-plan.md](validation-plan.md). Prototype: [prototype-spec.md](prototype-spec.md). 10-second test protocol: `p1b-design/project/design/ux-timetable-v2.md` §5 (owned by A03).

## 1. Recruiting

### 1.1 Who qualifies (screener)

Ask these in the first message or a short form. **Qualify** only if every "must" holds.

| # | Question | Must |
| - | -------- | ---- |
| 1 | In the last 12 months, did you write or maintain code that **receives** webhooks from a provider (Stripe, Shopify, GitHub, Twilio, Slack, an AI platform, other)? | Yes, and they wrote the handler themselves |
| 2 | Which providers? | Any. For the 10-second test, ≥ 3 of the 5 scored participants must have used Stripe |
| 3 | Does that handler change something: write to a database, send an email, call another service? | Yes (skeleton handlers give no signal) |
| 4 | Can you run that app on your own machine, with the provider in test or sandbox mode? | Yes for a prototype run. "No" still qualifies for discovery + 10-second test only |
| 5 | Have you ever seen or used Webhook Trap (webhooktrap / hookscope)? | **No** (required by the 10-second test) |
| 6 | Do you own or maintain CI or the test suite on your team? | Track it: ≥ 1 participant must say yes |
| 7 | Role: solo dev / small team / larger company · Are you on call for this integration? | Track it: mix of solo and team; on-call answers feed the Owner signal |
| 8 | Do you work on a webhook tool (Hookdeck, Svix, ngrok, webhook.site, Convoy…)? | **No** |

**Mix target for 8 sessions:** ≥ 5 can run the prototype on their own handler · ≥ 3 Stripe · ≥ 1 CI owner · ≤ 4 from the PO's close network · at least 2 who use a coding agent for handler code.

### 1.2 Where to find them (PO decides)

Options, in order of expected yield. **One message per channel, no cold mass DMs, no repeated posts.**

| Channel | How | Note |
| ------- | --- | ---- |
| PO's own network (ex-colleagues, clients, friends who build SaaS) | Personal message | Fast, but cap at half the sample (bias) |
| Existing Webhook Trap 1.x users | One email to users who opted in to product updates, if such consent exists | Fails screener Q5 for the 10-second test. Usable for prototype runs and async runs only |
| Local or online dev communities where the PO is already a member (meetup Slack/Discord, framework communities) | One post in the channel meant for research or jobs, following its rules | Ask moderators first if rules are unclear |
| People who publicly wrote about a webhook bug (blog posts, GitHub issues on Stripe/Shopify SDKs) | A short, personal note referencing their post | High quality, low volume. No scraping, no bulk |
| Paid panel (User Interviews, Respondent) | Screener above | Costly, but fastest and least biased |

Short invitation template (PO edits it):

> I'm researching how developers test webhook handlers (Stripe, GitHub, Shopify…). 45 minutes on a call, no sales pitch. If you agree, we'd also try a small open-source tool on your own handler, locally, in test mode. [Incentive]. Interested? Two quick questions first: [screener link].

### 1.3 Incentive (PO decides)

Options: a gift card (suggested €40–60 for 45 min of a developer's time), a donation to a charity they choose, or early access + credit in the open-source project. Pick one and use the same for everyone.

## 2. Consent, recording and privacy

Read at the start of every session. Get a spoken yes for each line.

> - This is research, not a sale. There are no wrong answers. Criticism helps the most.
> - May I record the call? The recording is only used to check my notes and to score one short visual test. I delete it within 30 days, after notes are written. You can say no and we continue without recording.
> - Notes are anonymised: I keep your role and company size, not your name or company.
> - Please don't show customer data, real payment data or production secrets. If we run the tool, we use test-mode data on your machine only. Nothing is sent to us. You choose what, if anything, to share afterwards.
> - You can stop at any time, or ask me to delete everything.

**Rules for the PO**
- Store recordings outside the repo, in a private location only the PO can access. Never commit recordings, transcripts or raw notes with names.
- Notes files use participant codes `P01…P08` (async: `A01…`). Keep the code-to-name mapping in one private file, outside the repo, deleted at the end of the study.
- No payloads, URLs, hostnames, company names, code or secrets in notes. Describe instead ("Express handler, writes an order row").
- If the participant shares their screen with something sensitive, look away from it in the notes: don't write it down.
- Agents only receive anonymised notes.

## 3. Session script (45 minutes)

Order matters: **discovery before any product talk**, and the **10-second test before the prototype run**, because the test requires people who have never seen Webhook Trap.

| Part | Minutes | Content |
| ---- | ------- | ------- |
| 3.1 | 0–3 | Welcome and consent (§2) |
| 3.2 | 3–17 | Problem discovery |
| 3.3 | 17–25 | 10-second comprehension test |
| 3.4 | 25–40 | Prototype run on their handler (if they agreed and qualify) |
| 3.5 | 40–45 | Judgements, commitments, wrap-up |

If there is no prototype run, use 25–40 for deeper discovery (signature debugging, CI, who pays).

### 3.2 Problem discovery (no pitching)

Ask about the past, in their words. Don't mention duplicates, ordering, timeouts or signatures before they do. Follow the story, then use the probes.

1. "Tell me about the last webhook integration you built. What was it for? What did it do when an event came in?"
2. "How did you test it before it went live?" *(Probe: provider CLI? fixtures? curl? nothing? How long did it take?)*
3. "When did a webhook handler last cause a problem in production, or nearly? What happened? How did you find out? How long did it take to fix?"
4. "Has an event ever arrived more than once, out of order, late, or been retried? What did your code do?" *(Ask only now, after 3. Record whether they raised it on their own in 3.)*
5. "What about signatures: did verification ever fail, or have you ever turned it off? Why?"
6. "If someone gave you a tool that sent your real events to your handler the way the provider might, what would you hope it finds? What would make you distrust it?"
7. **Q5 probe:** "Would you add one line of config, a SQL query or a command that tells the tool how many rows your handler created, to get checks on your database state?"
8. **Fixtures:** "Would you let a tool write captured events into your repo as test fixtures? What must be scrubbed first?"
9. **Money:** "Who on your team would pay for webhook tooling, and for what: history, team access, CI, production alerts?" *(Owner signal: note if they describe production monitoring as the thing they'd pay for.)*

### 3.3 10-second comprehension test

Follow `ux-timetable-v2.md` §5 exactly. Summary for the moderator:
- **Before the session:** open the canvas https://claude.ai/artifact/3ovs7azTxTzGFREDuBe6En and prepare stimuli S1 (`LandingDesktop`), S2 (`Main` cropped: strip + graph, findings and legend hidden), S3 (`Main`). Light theme, full screen, 1440 × 900.
- **The PO shares their own screen.** Do **not** send the link to participants (they would browse, and exposure must be exactly 10 s).
- For each stimulus, in the order S1 → S2 → S3: blank screen → stimulus for **exactly 10 s** (use a timer) → blank screen → ask the §5 questions → write answers **verbatim** → second untimed look: "What made this hard to read, if anything?"
- Don't help, don't confirm, don't react. "Thanks, next one."
- A03 and A01 score afterwards from the verbatim answers, independently.

### 3.4 Prototype run (only with consent, test mode, own machine)

Setup was sent the day before (prototype-spec §8), so this part is not about installing. Ask them to share their screen.

1. Safety check, said out loud: "Test mode only? Is the handler pointing at a local or test database? Does it send real emails or charges on an event? If yes, let's set the dry-run header or skip Twice."
2. They capture or pick 1–3 events (prototype-spec §3.1).
3. They run the default scenarios. **Say nothing during the run.** Note: time from start to first finding, their first reaction (verbatim), any confusion.
4. If the run is Level 1 only and they agreed to a probe in 3.2 Q7, offer a second run with a probe. Note how long it takes to set up.

If anything unexpected happens on their machine, stop the run.

### 3.5 Judgements and commitments

For **each finding**, ask and record:
- "Is this real? Would it matter in production?" → Real / Known and accepted / False positive / Unsure
- "Would you fix it before your next release?" → Yes / No
- "Did you know about it before today?" → Yes / Suspected / No

Then:
- **H3:** "Would you keep this tool on your machine, holding your signing secret? What would stop you?"
- **H4:** "Would you run this in CI? Where exactly, in which pipeline step?" If yes: "Can I check in with you in a week to see whether you tried?"
- "Is there anyone else I should talk to?"
- Thank them, confirm the incentive.

## 4. Note-taking template (one file per participant)

File: `notes/P0X.md`, anonymised. Write within 2 hours of the session.

```markdown
# P0X · <date> · <duration> · recorded: yes/no
Role: <solo / team of N / company size band> · On call: yes/no · CI owner: yes/no
Providers: <list> · Stack: <framework, DB> · Uses coding agent: yes/no
Prototype run: own handler / sample / none (reason)

## Discovery
- Last integration (what it did):
- How tested:
- Last incident (cause / how found / time to fix):
- Raised delivery semantics unprompted (Q3)? yes/no — which:
- Signatures (verification failures, disabled?):
- Hopes / distrust triggers (Q6):
- Probe acceptable (Q7)? yes / maybe / no — why:
- Fixtures in repo (Q8)? yes / no — must scrub:
- Who pays, for what (Q9):  · Owner signal: yes/no
- Best verbatim quotes (max 3, no names):

## 10-second test (verbatim)
S1 does / went wrong / click first:
S2 same event again / too long / changed state:
S3 changed state / what changed / most serious / passed:
Hard to read (second look):

## Prototype run
Install OK before session: yes/no · Node present: yes/no · minutes to first run:
Levels: L1 / L1+probe · Scenarios run:
Start → first finding (s):
First reaction (verbatim):

| Finding | Scenario | Level | Severity | Judgement | Fix before release | Knew before |
| ------- | -------- | ----- | -------- | --------- | ------------------ | ----------- |
| | | | | Real / Known / FP / Unsure | Y/N | Y/S/N |

## Commitments
Keep local agent with secret (H3): yes / no — objection:
CI intent (H4): concrete (pipeline + step) / vague / no · follow-up agreed: yes/no
7-day follow-up result: ran again / in CI / no reply / no

## Surprises and disconfirming evidence
```

## 5. Async runs (top-up for the finding rate)

For screened volunteers who can't join a call. They receive the setup instructions and a short form, and send back **only**:
- the anonymised run summary file (prototype-spec §4.3: counts, scenarios, timings, severities, no payloads);
- the judgement table above, filled in by them;
- answers to 3.5 H3 and H4.
Async runs count for H1/H2 rates and are reported separately ("A" codes).

## 6. Synthesis template

File: `results.md`, written by A01, updated after each session.

### 6.1 Per-participant matrix

| Code | Source | Run | L1 strict-real | Any-level strict-real | Findings (n) | FP (n) | Start→first finding (s) | Incident recalled | H3 keep agent | H4 concrete CI | H4 follow-up |
| ---- | ------ | --- | -------------- | --------------------- | ------------ | ------ | ----------------------- | ----------------- | ------------- | -------------- | ------------ |
| P01 | network | none | – | – | – | – | – | Y | ? | – | – |

### 6.2 Findings by scenario

| Scenario | Findings | Real (strict) | Known/accepted | FP | Unsure |
| -------- | -------- | ------------- | -------------- | -- | ------ |
| Once / Twice / Shuffled / Late / Tampered / Slow budget | | | | | |

### 6.3 Go / no-go table

| # | Metric | Threshold (go / fail) | Result (count) | Verdict |
| - | ------ | --------------------- | -------------- | ------- |
| H1 | First runs with ≥ 1 strict-real finding, any level | ≥ 40% / < 20% | x of n | Go / Grey / Fail |
| H1 | Delivery-semantics incident or near miss recalled | ≥ 4 of 8 / ≤ 1 | | |
| H2 | First runs with ≥ 1 strict-real Level 1 finding | ≥ 30% / < 20% | | |
| H2 | False-positive share | ≤ 20% / > 35% | | |
| H2 | Median start → first finding | ≤ 60 s / > 180 s | | |
| H3 | Installed and ran on own machine | ≥ 6 of 8 / ≤ 3 | | |
| H3 | Would keep a local agent with the secret | ≥ 6 of 8 / ≤ 3 | | |
| H4 | Concrete CI intent | ≥ 3 of 8 / ≤ 1 | | |
| H4 | Ran again or in CI by day 7 | ≥ 2 / 0 | | |
| H5 | `ux-timetable-v2.md` §5 pass marks | S1 + S3 pass | | |

**Outcome per validation-plan §4:** GO · GO probe-first · Extend · Pivot to A · Invert to C · Stop
**Strongest evidence for:** (3 quotes)  **Strongest evidence against:** (3 quotes)
**Side signals:** probe acceptance, install friction (Q3), scrubbing worries, who pays.
