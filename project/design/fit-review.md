# Fit review: do the three directions answer the Proving Ground vision?

**Status:** PROPOSED for A00 and PO review · **Author:** A01 (Product Strategist) · **Date:** 2026-11-11
**Reviewed against:** `p1b-product/project/product/` (`vision.md`, `positioning.md`, `core-experience.md` Part 2, `personas.md`).
**Reviewed material:** the local sources of each Design System and Design canvas under `claude-design/<territory>/` (markup, copy and state logic of every artboard), plus `brand-territories.md` and `ux-foundations.md` for intent. Nothing was rendered in a browser, so layout, contrast and motion are judged from the markup only.

Scale: 5 = answers the vision with no change · 4 = answers it, minor fixes · 3 = partly · 2 = works against it in places · 1 = misses it.

---

## Summary

| Criterion | I · Timetable | II · Crash Lab | III · Notation |
| --------- | :-----------: | :------------: | :------------: |
| 1. Landing tells the story and converts | 4 | 4 | 4 |
| 2. Capture: Prove is primary, debugging is the first minute | 4 | 4 | 3 |
| 3. Delivery detail serves signature diagnosis | 5 | 5 | 5 |
| 4. Run: verdict, severity, 1 → 2 and compare in seconds | 4 | 4 | 4 |
| 5. Tone (precise, calm, dry; judges code) | 5 | 3 | 4 |
| 6. Metaphor helps a developer in a hurry | 4 | 2 | 2 |
| 7. Scales to Horizon 2 and to CLI output | 4 | 2 | 3 |
| **Total / 35** | **30** | **24** | **25** |

**Ranking: 1. Timetable · 2. Notation · 3. Crash Lab.** The argument is at the end.

### What all three got right (no action)

- **The data is the product's data.** All three use the same run (Run #3: 14 deliveries, 8 scenarios, F1 Critical tampered, F2 High duplicate order, F3 Medium budget, F4 Info probe gap). Severity encoding is honest: F2 is High, Critical is only for D10.
- **The hero line and CTA are A01's.** All three use "Your webhook handler works. Once.", **Get a URL, no sign-up**, the `npx @webhooktrap/agent` chip, the "Open source · self-hostable · secrets stay on your machine" reassurance line and the footer "Prove your first handler in 5 minutes." All seven sections of core-experience §2.8 are present.
- **Signature diagnosis is excellent in all three.** #09 shows the reason ("Signed with a different secret"), expected and received digests, body integrity (7 of 7 checks), which secret was tried (names only), the likely cause (hook 512839104 edited at 13:31) and a fix with **Re-check**. #08 shows "no secret" with the `trap secrets set parcelbird` command, and says secrets stay local. This is problem #3 solved as A01 framed it.
- **The run headline frame works.** Verdict banner with counts per severity and the verdict level (Level 1 transport + Level 2 probe). F2 has evidence, measured values, the provider citation, the "why only the probe could see this" line and a framework-aware fix with **Copy fix** and **Copy finding as JSON**. D3 is compared with D2 on event id, signature timestamp, status, latency, body and `orders.count`. Inconclusive suggests the probe in A01's words. Aborted keeps D1–D2 and says nothing after was sent.

### Issues common to all three (must-fix whichever direction wins)

1. **Placeholders and unverified facts are visible in product copy.** "10 000 ms, VERIFY against pack data" and "Stripe pack, VERIFY" appear in scenario tables (Timetable in 3 files, Crash Lab and Notation in 1). "[TO CONFIRM]" / "[LICENCE]" appear on every landing. More important than the leak: the **Stripe 10 000 ms budget is stated as a fact everywhere** (F3, the Slow budget rows, the budget bars). Principle 3 (provider-faithful, "if we can't be faithful, we say so") means this number must be verified, or labelled as the pack's value, before any of these screens is used externally.
2. **"Your handler survived duplicates, reordering, late, tampered and slow deliveries"** (Passed, all three). This overclaims. It passed 8 Stripe scenarios at Level 1 + Level 2 for `orders.count` only, and F4 already showed the probe can't see subscription state. Say what was checked: "8 of 8 scenarios passed at Level 2 (`orders.count`). Not checked: state outside the probe."
3. **"Stripe … always retries after a timeout"** in F2's "Why it matters" (from realistic-data). Keep the claim to what the cited doc says ("retries failed or timed-out deliveries for up to 3 days").
4. **The anonymous first minute is not drawn.** Every *receiving* frame has the agent `maya-mbp` already paired. M2/M3 ask for an instant signature verdict on arrival, but a guest with no agent sees "Not checked" on every row (stated in the waiting states). Nobody has drawn what the first real event looks like for a guest without an agent, and that is the exact moment the funnel converts from "inspector" to "Prove". Add one frame per direction: guest, 4 Stripe events received, no agent, with the Prove path explaining pairing.
5. **CI and CLI are only promised, never shown.** No direction draws `trap test` terminal output, a PR annotation, or the fixtures/suite screen (core-experience §2.5 screen 4). The QA/CI persona's moment (M9) is therefore unassessed. See criterion 7.
6. **"Each duplicate ships Jonah another bag of coffee."** It is concrete and harmless, but it names a customer inside a verdict. Keep it in the landing story if wanted. In the product, prefer the consequence without the person ("Each duplicate creates another order").

---

## I · Timetable

Artboards read: `Main` (Run, five states via `state`), `DeliveryGraph`, `RunDraft`, `RunMobile`, `LandingDesktop`, `LandingMobile`, `CaptureDesktop` (+ quota mode), `CaptureWaiting`, `CaptureMobile`, `DetailPane`/`DetailPage` (#01, #08, #09, Raw), `MobileDetail`, `StatesSheet`.

| # | Score | Reasons |
| - | :---: | ------- |
| 1 | 4 | The demo is **in the hero**: a three-line Delivery Graph (Once, then Twice with `D3 = D2′` 500 ms apart), the state rail reading `1`, `1`, `2`, and the finding "High · F2 · Duplicate delivery created a second order" with the Stripe citation, plus Pause/Replay. This is the vision's single image, told on first view. The "Secrets stay on your machine" section is a table (what / where / on our servers: *Never*), the clearest reassurance of the three. Capture → Scrub → Commit → CI uses the real file names. −1: the graph must be *read* (axis labels, legend). A visitor who can't parse a time–distance chart in 3 seconds gets the story only from the caption. |
| 2 | 4 | Capture is "Arrivals", grouped by related object, with a **primary Prove only on the selected row** and in the detail pane, and a secondary Prove per group. That is the right hierarchy: one primary per screen. The waiting state is A-first (URL, Copy, Stripe/GitHub/Shopify/Standard Webhooks/curl steps, "Send a test request"). The guest quota banner is honest ("your provider sees 200 … not stored") and offers **Save as fixtures** before **Sign in**, as §2.9 asks. −1: see common issue 4 (first minute without agent not drawn). |
| 3 | 5 | All five signature states are in the pane, with plain labels and the station glyphs as a second channel. The no-secret pane gives the env var name and prefix (`whsec_`). |
| 4 | 4 | Verdict banner ("4 findings" + 1/1/1/1 by severity + level) → graph (D3 selected; parallels, crossings, the allowance band) → inspector D3 with Request/Response, the D2/D3 compare table and "State after D3: orders.count 1 → 2 · High · changed (expected 1)". Findings sit in a right column, by severity, F2 open. **Integrator:** everything is on one screen. **Team debugging (shared link, mobile):** the mobile report is findings-first with a "Graph" toggle, and the D3 sheet keeps the compare. Good. **CI:** run history reads in plain words (`#1 Inconclusive · #2 Aborted · #3 4 findings`), and Save as fixtures lists the files and scrub rules. −1: the 10-second graph comprehension test (A02's condition) has not been run, and the graph takes the prime band above the inspector. |
| 5 | 5 | Exact numbers everywhere (`11 840 ms (+1 840 over)`, `13:57:40.912`), no exclamation marks, no railway jargon on controls. Error copy states what is kept and what to do. |
| 6 | 4 | Labels stay plain (Run, Delivery, Finding, Prove). The metaphor sits in the picture rather than the words. Visible legend: "steep = fast · parallel = same event again · crossing = reordered". Vocabulary risk: **"allowance" and "budget" name the same thing** ("of the 10 000 ms allowance" next to "within the 10 000 ms budget", and the scenario is "Slow budget"). "Conflict" in the legend versus "finding" everywhere else is a smaller drift. |
| 7 | 4 | **Horizon 2: 5.** The same graph over production time *is* the Flight Recorder (gaps = silence, slope = latency against the budget band, parallels = duplicates in the wild). No rebrand needed. **CLI: 3.** The data vocabulary (`D3`, exact ms, `orders.count 1 → 2`) prints well, but the signature image doesn't survive a terminal; the CLI would be a plain table plus a link. Nothing is drawn. |

**Must-fix (Timetable)**
1. Run A02's 10-second comprehension test on the run graph *and* the landing hero graph with 5 developers. If "D3 created a second order" isn't read in 10 s, make the hero caption/finding the headline and the graph the illustration.
2. Pick one word for the response limit: **budget** (A01's and the scenario's term). Drop "allowance" from UI copy.
3. Remove the VERIFY leaks (3 files) and verify the 10 000 ms figure (common issue 1).
4. Draw the guest first minute without an agent, and one CLI/PR output (`trap test` summary + PR annotation) to show the territory survives outside the graph.

---

## II · Crash Lab

Artboards read: `Main` (Run #3 dark), `RunRunning`, `RunDraft`, `RunPassed`, `RunAborted`, `RunInconclusive`, `RunMobileReport`, `RunMobileD3`, `Landing`, `LandingMobile`, `CaptureReceiving`, `CaptureWaiting`, `CaptureQuota`, `CaptureMobile(Waiting)`, `Detail01Verdict`, `Detail01Raw`, `Detail09Invalid`, `Detail08NoSecret`, `DetailMobile09`, `DetailMobile01Raw`, `StatesLoading`, `StatesErrors`, `StatesEmpty`.

| # | Score | Reasons |
| - | :---: | ------- |
| 1 | 4 | The most memorable landing. "One event, delivered twice": two film frames with probe odometers, `Intact · 0 → 1` then `Expected 1 · Not idempotent · High · 1 → 2`, and the finding text with "Pause film". The subline ("Then Stripe delivers it again") is the sharpest opener of the three. Scenarios are numbered `01–08` as "calibrated impacts", each with its provider citation. −1: the demo labels the first delivery `D02` and the second `D03` (borrowed from Run #3), which looks like an error to a first-time visitor. Secrets, open source and self-host are squeezed into one band, so the secrets promise gets less weight than in Timetable. |
| 2 | 4 | Capture ("Intake") is grouped, with a yellow primary **Prove** on the specimen sheet and a secondary Prove per group. The waiting state is A-first. −1: groups are renamed **"Batch"** ("Batch sub_1QH…4drI", "First in batch"), and the signature glyphs need a legend ("four quadrants = valid · cracked = invalid · outlined = no secret · one missing = unsigned"). Valid rows show the glyph without a word. |
| 3 | 5 | Same complete diagnosis. Detail08 is the best of the three: it adds the run consequence ("Twice, Late and Tampered can't re-sign Parcelbird events and are marked 'Needs secret'"), which is §2.9 exactly. |
| 4 | 4 | The **test bay** (one rig per scenario with pass/finding, a short reason and the key value: "02 Twice · Finding · High · orders.count 1→2", "06 Tampered · Finding · Critical · accepted a changed body") answers "which scenario failed?" faster than either other direction. The **deformation gauge** (`001 → 002 · Expected 1`) makes 1 → 2 unmissable. The D2/D3 comparison is the richest (body, signature, status, response body, probe, with a "Result" column). "A partial run never counts as passed" is exactly the right rule. −1: five stacked zones (verdict, bay, filmstrip, readout, damage report) make the desktop dense. The run is called both **"Run #3" and "Test 03"** on the same line. |
| 5 | 3 | Words are mostly clinical and measured, as A02 asked. But the headings carry the drama: "Damage report", "Deformation recorded", "impacts", "The camera stopped at frame D03; nothing after it was exposed", "Blank film was never sent". It never insults the developer, but it is louder than "calm, a little dry", and "damage" frames a finding as harm rather than a judgment on code. |
| 6 | 2 | This direction breaks A02's rule 1 ("product nouns don't change") most often, and in ways that collide. **"Rig" means a scenario** ("Rig 02 Twice", "Each rig is a toggle") **and also the agent** ("Rig offline", "Waiting for rig maya-mbp", "Rig setup"). The run is a "Test", a group a "Batch", a delivery a "Frame" that is "exposed", and a capture a "Specimen". A developer in a hurry must translate five nouns, and two of them point at different things. Delivery numbering also flips between `D3` and `D03`. |
| 7 | 2 | **Horizon 2: 1–2.** Its own UX doc concedes crash imagery is wrong on a production-monitoring board. A filmstrip of 14 frames also doesn't scale to thousands of production deliveries. **CLI: 3.** Zero-padded test numbers and a damage report map well to a test-runner summary ("TEST 03 · 4 findings"), but nothing is drawn. |

**Must-fix (Crash Lab)**
1. Untangle "rig": keep it for scenarios *or* the agent, never both. Agent states use the plain label ("Agent offline"), per A02 rule 1.
2. One name per object: **Run #3**, not "Test 03". Choose "group" or "batch" across all screens, and `D3` or `D03` everywhere.
3. Landing demo: number the two deliveries as the visitor meets them (1 of 2, 2 of 2), not D02/D03.
4. Turn down the headings: "Findings" over "Damage report", "State changed" over "Deformation recorded". Keep the gauge and the bay; they are the best ideas here.
5. Put a word next to the valid signature glyph in the list, as invalid/no secret already have.

---

## III · Notation

Artboards read: `Main` (Run #3), `Run-running`, `Run-draft`, `Run-passed`, `Run-aborted`, `Run-inconclusive`, `Run-mobile`, `Run-mobile-inspector`, `Landing`, `Landing-mobile`, `Capture`, `Capture-waiting`, `Capture-quota`, `Capture-mobile(-waiting)`, `Delivery-01-verdict`, `Delivery-01-raw`, `Delivery-09-invalid`, `Delivery-08-nosecret`, `Delivery-mobile-09`, `Delivery-mobile-01-raw`, `States`.

| # | Score | Reasons |
| - | :---: | ------- |
| 1 | 4 | The hero's annotated pair is a strong, shareable image: `1. invoice.paid 200 · 212 ms ! position: orders 1` / `2. invoice.paid (again) 200 · 196 ms ? position: orders 2`, then "A natural move, and a losing one. Stripe may deliver the same event more than once. Better was (2. … = with an event.id guard)." The agent section carries the strongest argument of the three ("The notation is the format … the same record your coding agent reads"). −1: the landing teaches a **notation key before the demo** ("How we annotate your handler: ?? ? ?! !? !"), which adds a comprehension step before the payoff. "A new ? fails the check" is wrong: in CI any finding at or above the configured severity should fail, including `??`. |
| 2 | 3 | Prove is primary in the detail pane and secondary per group: correct. But capture is renamed **"Games"** ("13 deliveries · 6 games", "The game brindle/brindle-app"), and deliveries inside a group are numbered `1. 2. 3.`, the same notation the Run uses for *deliveries in a run*. The same symbol means "order within a capture group" on one screen and "D-number of a run" on the next (`#01` is move `1.` in capture, and also `1.`/`2.`/`6.`/`11.` in Run #3). A GitHub repository as a "game" stretches the metaphor. |
| 3 | 5 | Complete diagnosis. The fix is written as a variation, "(Better: copy the secret from GitHub › … then press Re-check.)", plus a direct "Open hook 512839104 on GitHub" link, which saves a step. |
| 4 | 4 | The move list is grouped by scenario with each move's result inline (`3. invoice.paid (again, as 2.) ? High orders 1 → 2 200 196`). "Position after 3: orders.count 1 → 2 · ? High" is pinned. The compare closes with the best sentence in all three directions: **"Same request, same response, a different position."** The mobile "pocket book" puts Critical first and collapses passed Burst ("Burst collapsed: all passed"). **CI/PR readers** benefit most: the record is text. −1: on desktop the open F2 "Commentary" comes first and **F1 Critical is relegated to "Other annotations"**, so severity order breaks when a finding is open. **Run history is glyph-only** (`∞ #1 · ½ #2 · ?? #3`). |
| 5 | 4 | Analytical, generous (it always shows the better move), and it never gloats. "A natural move, and a losing one" judges the move, not the person. −1: "Good move" on every passing delivery grades the developer more than "Pass" does, and "An empty seat. The game starts when your agent sits down" is whimsy in a setup step. |
| 6 | 2 | The glyphs work against a developer in a hurry. **`?!` (Medium) and `!?` (Info) are mirror images** and must be told apart at 12 px. **`½` marks Aborted**, though in chess ½ means a draw: a reader who knows chess will misread it, and one who doesn't can't read it. **`=` carries four meanings**: unchanged position, shape matches baseline, the better variation ("2. … ="), and "not measured" in the inconclusive legend. "Flag fell" appears in data rows, not just helper text. Plain severity words are paired with glyphs on first view, as A02 required, but not in history, moves or legends. |
| 7 | 3 | **CLI/PR: 4–5.** A numbered move list with annotations and a "better" line is natively textual; it prints in a terminal and reads well in a PR comment with no picture. This is the strongest CLI story. **Horizon 2: 2.** A production stream has no "game" or turns, and "flag fell" or `½` on an on-call alert would be noise. |

**Must-fix (Notation)**
1. Replace `?!` / `!?` as the *primary* distinction between Medium and Info with a second channel (shape or weight), or drop `!?` and write "Info".
2. Aborted gets a plain label and a glyph that doesn't mean "draw". The history list shows words with the glyphs (`#2 Aborted`).
3. Give `=` one meaning. Use a word for "shape matches" and "not measured".
4. Keep findings in severity order while one is open: F1 Critical stays above the open F2.
5. Don't reuse `1. 2.` in capture groups. Use capture numbers (`#01`) there, and keep move numbers for runs. Call groups "groups" (or the object's own name), not "games".
6. Fix "A new ? fails the check".

---

## Ranking and argument

### 1. Timetable

It is the only direction where **the metaphor costs nothing in words**. Every label stays A01's (run, delivery, finding, Prove, budget), and the idea lives in one picture that *is* the product's belief: webhooks fail in sequences. It tells the landing story in the hero itself, has the clearest secrets promise, the calmest voice and the plainest run history for CI. It is also the only one that carries into Horizon 2 without a rebrand, which matters because the vision's expansion path (fixtures → baselines → Flight Recorder) is where team revenue sits. Its risk is real but **testable this week**: graph literacy. If the 10-second test fails, the fallback is to make the caption and the finding the headline, which the frames already contain. The direction degrades gracefully.

### 2. Notation

It has the best *sentences* ("A natural move, and a losing one", "Same request, same response, a different position"), the best mobile and PR reading, and the most natural CLI and agent output. It loses to Timetable on comprehension: a hurried developer has to learn a glyph key in which two symbols are mirror images, one means "draw" and one means four things. It also renames groups to "games" and reuses move numbers in capture. Most of this is fixable copy and glyph work, but the H2 ceiling is not: a monitoring board is not a game.

### 3. Crash Lab

It has the loudest landing and two genuinely superior run components: the **test bay** (per-scenario verdict at a glance) and the **deformation gauge** ("Expected 1"). But it renames five product nouns and collides on one ("rig" = scenario *and* agent). Its headings drift from "calm, a little dry" toward drama, and by its own team's admission it does not fit Horizon 2. As A02 proposed, it is best used as campaign material.

### If Timetable wins: what to carry over after the decision (not before)

- From Crash Lab: a per-scenario result strip (the test bay idea, in Timetable's plain words) above or beside the graph, and "expected 1" next to every state change.
- From Notation: the sentence "Same request, same response, a different position" as the compare caption, and a numbered, text-only run record as the template for `trap test` output and PR annotations.

---

## What I could not assess

- **Rendering, layout at real widths, motion, contrast and dark-mode quality:** not rendered (no claude.ai login). Judged from markup only; each README records its own contrast figures, which I did not re-verify.
- **CI, CLI and PR experience (M9, M10):** no direction draws terminal output, a PR annotation or the Fixtures & suite screen, so the QA/CI persona is judged only from run history, fixtures panels and landing copy.
- **Guest first minute without an agent:** not drawn in any direction (common issue 4).
- **Horizon 2:** judged by extrapolation; no direction draws a monitoring view.
- **Comprehension claims** (graph literacy, glyph literacy, lab vocabulary) are my judgment. They need the 5-person, 10-second tests A02 and A03 proposed before the PO decides.
