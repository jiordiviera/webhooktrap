# 10-Second Comprehension Test Kit (Gate 2 · Phase 3)

**Status:** PROPOSED for PO · **Author:** A01 · **Date:** 2026-11-14 · **Decisions:** 48, 49
**Replaces** the stimuli and questions of interview-kit §3.3 for H5. The rest of the validation kit is unchanged. Design protocol: `ux-timetable-v2.md` §5 (A03).

**One question answered:** can a developer who has never seen Webhook Trap read the Timetable Run screen in 10 seconds and find the real problem? Five developers, about 15 minutes each, one session each.

## 1. Who to recruit

**5 developers.** Round 2 (only if round 1 fails, §6) needs 5 *different* ones, so line up 8–10 names and book 5.

Qualify only if every "must" holds:

| # | Screener question | Must |
| - | ----------------- | ---- |
| 1 | In the last 12 months, did you write or maintain a handler that **receives** webhooks (Stripe, GitHub, Shopify, Twilio...)? | Yes, wrote it themselves, and it is in production |
| 2 | Which provider(s)? | At least 3 of the 5 have used Stripe |
| 3 | Does the handler change something (database write, email, call to another service)? | Yes |
| 4 | Have you ever seen or used Webhook Trap (webhooktrap, hookscope), or seen its landing page? | **No** |
| 5 | Do you work on or for a webhook tool (Hookdeck, Svix, ngrok, webhook.site, Convoy...)? | **No** |
| 6 | Do you own or maintain CI or QA for your team? | At least 1 of the 5 says yes |
| 7 | Can you screen-share a laptop or desktop and read English (or French)? | Yes (a screen 1440 px wide or more is ideal) |

Mix: at most 2 from the PO's close network. Anyone from the validation interview study who already saw the product does **not** qualify.

## 2. Consent, recording, privacy

Read aloud, get a spoken yes for each line.

> - This is a 15-minute research test of a screen, not of you. There are no wrong answers.
> - May I record audio and the shared screen? I use it only to check my notes and score the test, and I delete it right after scoring, within 7 days. You can say no.
> - I note your first name and your role (for example "backend dev, team of 5"). No surname, company, or contact details go in my notes.
> - Everything shown is invented sample data. Nothing of yours is needed or collected.
> - You can stop at any time and ask me to delete everything.

**Rules for the PO**
- Store recordings outside the repo, in a private place. Delete them after scoring (same week, at most 7 days). Never commit recordings, names or raw notes.
- Notes use codes `T01`...`T05`. First names live only in a private code-to-name file, deleted with the recordings.
- Agents receive only the anonymised results sheet (§7) with verbatim answers, never recordings.

## 3. Before each session (PO)

1. Get **the prototype's test-mode link (URL provided by A00)**. Opening it must show the Run #3 findings screen directly, with no tour, banner, tooltip, highlight or label added for the test. If anything like that is visible, stop and tell A00 before running a session.
2. Open it in a clean window (no bookmarks bar), light theme, full screen, about 1440 x 900. Screen-share **your own window**. Do not send the link to the participant: they would explore, and the exposure must be exactly 10 seconds.
3. Prepare a **blank** tab to flip to, a timer, and the results sheet (§7).
4. Know the answers (§5) but never show or hint at them. What the screen holds: Run #3 · 16 deliveries · 8 scenario cells (Once, Twice, Shuffled, Late, Retry after error, Tampered, Burst, Slow budget; the product has 9 scenarios, Unsigned is not in this run) · 3 findings plus 1 Info:
   - F1 **Critical**: tampered payload accepted (D12). Critical is used only for tampered or unsigned.
   - F2 **High**: duplicate created a second order (D4 · D5, retry D10 · D11), orders.count 1 → 2, expected 1.
   - F3 **Medium**: over the 10 s budget (D10 · D16).
   - F4 **Info**: probe does not see subscription state (D6 · D15).
   Probe values appear as counts or codes; Level 2 findings read "judged on maya-mbp".

## 4. Facilitator script (English, about 15 minutes)

Say the quoted lines as written. Do not help, confirm, correct, or react ("Thanks, next question"). Write answers **verbatim**.

| Min | Step | Say / do |
| --- | ---- | -------- |
| 0–2 | Welcome, consent | §2. Then: "You will see one screen for exactly ten seconds, then it disappears and I ask four questions. Answer from memory, in your own words. If you don't know, say so; that is useful." |
| 2–4 | Warm-up (no product talk) | "What kind of webhooks do you handle, and in what language?" Note role and provider. Do not describe the product. |
| 4–5 | Exposure | "Ready? Eyes on the screen... now." Switch to the test-mode link and **start the timer**; at **10.0 s** switch to the blank tab. Say nothing in between. |
| 5–11 | Four questions, screen blank | One at a time, in this order, wait for the full answer. |
| | **Q1** | "In your own words, what is this screen telling you?" |
| | **Q2** | "Which delivery, or which deliveries, are the problem?" *(If several:* "Which one first?"*)* |
| | **Q3** | "How serious is it? What is the most serious thing on it?" |
| | **Q4** | "What would you do next?" |
| 11–14 | Second look, untimed | Show the screen again. "Take your time. What made it hard to read, if anything? Was anything confusing or missing?" Then: "Did anything look like a mistake in the screen?" |
| 14–15 | Close | Thank them, confirm the incentive (the PO's choice, the same for everyone), remind them the recording is deleted after scoring. Do not explain the answers until after the last session. |

If the exposure ran more than 2 s off (under 8 s or over 12 s), mark "timing off" and replace the participant. Under 2 s off, note it and keep the session.

## 5. Scoring

Score the same day from verbatim notes. A01 and A03 score independently; on disagreement take the stricter mark. An answer passes if it carries the target meaning, in any words.

| Q | Target meaning (pass) | Mark |
| - | --------------------- | ---- |
| Q1 | Says the run found problems in how a handler behaves when webhooks are replayed (not just "a list of requests", "a dashboard", "logs") | **Q1** |
| Q2 | Names **D5** (or the D4/D5 pair, or D11) **and** gives the cause: the same event was delivered twice and created a second order (count 1 → 2, expected 1). Both parts needed | **Q2 = Key** |
| Q3 | Says the run failed **and** names F1 / Critical / "accepts a changed body" as the worst, or reads the duplicate correctly as High. Calling Info or Medium the worst fails | **Q3a** (diagnostic) |
| Q4 | A fitting action: make the handler idempotent / record the event id, or reject tampered bodies (check the signature), or open the finding's fix. "Nothing", "reload", "ask support" fail | **Q4** (diagnostic) |

**Graph-hard flag:** in the second look, the participant names the graph, the strip or the symbols as what made it hard to read.

**Pass marks (frozen before the first session)**

| Measure | Pass | Note |
| ------- | ---- | ---- |
| **Key (Q2): High finding and duplicate cause both correct** | **≥ 4 of 5** | Aligns with validation-plan H5 and ux-timetable-v2 §5 S3 ("which delivery" and "what changed", ≥ 4 of 5) |
| Q1: what the screen says | ≥ 4 of 5 | A miss with Q2 passing is a copy issue, not a layout one |
| Q3a: run failed and worst severity | ≥ 3 of 5 | Legibility of severity word and glyph |
| Q4: sensible next action | ≥ 3 of 5 | Diagnostic |
| Graph-hard flags | ≤ 2 of 5 | 3 or more triggers the Notation rule (§6) in either round |

Five people give a signal, not a statistic: one confused participant never changes the outcome, three always do.

## 6. What the result means

| Round 1 result | Decision |
| -------------- | -------- |
| Key ≥ 4, Q1 ≥ 4, graph-hard ≤ 2 | **Timetable stays.** Gate 2 direction confirmed. |
| Key ≥ 4, but Q1 < 4, Q3a < 3 or Q4 < 3 | **Timetable stays**, with copy and legibility fixes by A03 (no layout change). |
| Key ≤ 3 of 5 | **Timetable's own fallback** (`RunFallbackTableFirst`): Deliveries table first, graph reduced to a 120 px strip. A04 updates the test-mode screen. **Round 2** with 5 new developers, same script. |
| Graph-hard ≥ 3 of 5, in any round | **Notation fallback**, whatever Key says. |

| Round 2 result | Decision |
| -------------- | -------- |
| Key ≥ 4 and graph-hard ≤ 2 | Timetable (table-first) is the run view. |
| Key ≤ 3 of 5, or graph-hard ≥ 3 | **Notation fallback triggered.** Notation's A01 must-fixes apply first; the strip, state reading, commentary and run record carry over, only typography changes. |

The PO logs the outcome as a decision. Thresholds change only by a PO note in the decisions log made **before** the first session.

## 7. Results sheet (one per participant, anonymised)

```markdown
# T0X · <date> · round 1 / 2
Role: <solo / team of N> · Stripe: y/n · CI owner: y/n · Recorded: y/n
Exposure 10 s: ok / timing off · Screen had no extra hints: y/n

Q1 verbatim:                -> pass / fail
Q2 verbatim:                -> D5 (or D4/D5, D11): y/n · cause (twice, 2nd order): y/n -> KEY pass / fail
Q3 verbatim:                -> run failed: y/n · worst = Critical/tampered: y/n -> Q3a pass / fail
Q4 verbatim:                -> pass / fail
Second look (hard to read): -> graph-hard: y/n
Looked like a mistake:
Best quote (no names):
Scored A01: __  A03: __  Final (stricter): __
```

| Participant | Q1 | Key (Q2) | Q3a | Q4 | Graph-hard |
| ----------- | -- | -------- | --- | -- | ---------- |
| T01 | | | | | |
| T02 | | | | | |
| T03 | | | | | |
| T04 | | | | | |
| T05 | | | | | |
| **Count** | /5 (≥ 4) | **/5 (≥ 4)** | /5 (≥ 3) | /5 (≥ 3) | /5 (≤ 2) |

## 8. Script facilitateur (français, environ 15 minutes)

Lire tel quel. N'aidez pas, ne confirmez pas, ne corrigez pas ("Merci, question suivante"). Notez les réponses **mot pour mot**.

**Consentement (un oui oral par ligne).**
> - C'est un test de 15 minutes sur un écran, pas sur vous. Il n'y a pas de mauvaise réponse.
> - Puis-je enregistrer le son et l'écran partagé ? Je m'en sers seulement pour vérifier mes notes et noter le test, puis je supprime l'enregistrement dans les 7 jours. Vous pouvez refuser.
> - Je note votre prénom et votre rôle (par exemple « dev backend, équipe de 5 »). Ni nom de famille, ni entreprise, ni coordonnées.
> - Toutes les données affichées sont inventées. Rien de vous n'est nécessaire ni collecté.
> - Vous pouvez arrêter à tout moment et me demander de tout supprimer.

**Introduction.** « Vous allez voir un écran pendant exactement dix secondes, puis il disparaît et je vous pose quatre questions. Répondez de mémoire, avec vos mots. Si vous ne savez pas, dites-le, c'est utile. »

**Échauffement (sans parler du produit).** « Quels webhooks gérez-vous, et dans quel langage ? »

**Exposition.** « Prêt ? Les yeux sur l'écran... maintenant. » Lien du mode test, chrono, à 10,0 s onglet vide. Ne rien dire entre-temps.

**Questions (écran vide, une à la fois).**
- **Q1** : « Avec vos mots, que vous dit cet écran ? »
- **Q2** : « Quelle livraison, ou quelles livraisons, posent problème ? » *(Si plusieurs :* « Laquelle d'abord ? »*)*
- **Q3** : « Est-ce grave ? Quelle est la chose la plus grave ? »
- **Q4** : « Que feriez-vous ensuite ? »

**Second regard (sans limite de temps).** Remontrer l'écran. « Prenez votre temps. Qu'est-ce qui a rendu la lecture difficile, s'il y en a ? Quelque chose de confus ou de manquant ? » Puis : « Y a-t-il quelque chose qui ressemblait à une erreur de l'écran ? »

**Clôture.** Remercier, confirmer la contrepartie (identique pour tous), rappeler que l'enregistrement est supprimé après la notation. Ne pas expliquer les réponses avant la dernière session.

Notation, seuils et feuille de résultats : §5 à §7, inchangés. Notez les réponses dans la langue du participant ; A01 et A03 les lisent telles quelles.
