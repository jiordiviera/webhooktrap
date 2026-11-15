# Prototype Spec: Proving CLI (throwaway)

**Status:** PROPOSED for PO review · needs PO go-ahead (decision 27) · **Author:** A01 · **Date:** 2026-11-14
Plan and thresholds: [validation-plan.md](validation-plan.md). Scenarios and verdict levels: [core-experience.md](../core-experience.md) §2.3–2.4. Output format: `p1b-design/project/design/ux-timetable-v2.md` §1.4. Budgets: `p1b-design/project/design/provider-budgets.md`.

## 1. Purpose

A small command-line tool that a developer runs on their own machine against their own webhook handler, to measure one thing: **how often does a first run find something the developer calls real?** (H1, H2.) It also gives signals on install friction (H3, Q3) and probe acceptance (Q5).

It is an instrument for the study, not the first version of the product.

## 2. What it must NOT be

- **Not 2.0 code.** No architecture, naming or module is carried into 2.0. It is not reviewed as product code and it is **never merged** into `main` or `rewrite/v2`.
- **No server.** No hosted component, no accounts, no pairing, no web UI, no database.
- **No telemetry.** It makes no network call except to the target handler. No update check, no analytics.
- **Not the full catalogue.** No Unsigned, Retry after error, Burst or sequence packs (core-experience §2.3). No MCP, no `--json` contract, no CI integration beyond an exit code.
- **Not a fixture product.** No scrubbing, no `trap save`. Captured events stay in a local folder, uncommitted.

## 3. Scope (2 weeks)

### 3.1 Getting events in (three ways, all local)

| Mode | How | Notes |
| ---- | --- | ----- |
| **Record** (default) | `proto record --port 4400 --forward http://localhost:3000/webhooks/stripe` starts a local recorder. The developer points their existing tool at it: `stripe listen --forward-to localhost:4400`, or their own tunnel (ngrok, cloudflared, smee) for GitHub/Shopify. The recorder saves the exact raw body, headers and request target, then forwards unchanged to the handler. | "Public URL" comes from the developer's own tunnel or provider CLI. We host nothing. |
| **Import** | `proto import <file>`: a delivery copied from the provider (GitHub "Recent deliveries" headers + payload, a Stripe event JSON from the CLI or dashboard), or raw `.http`. | Re-signed at replay time, so the original signature doesn't matter. |
| **Fixtures** | Replays files already in `.proving/fixtures/`. | For repeat runs and async participants. |

Optional and PO-gated: a participant may capture in a Webhook Trap 1.x inbox and copy the raw request into `proto import`. **Test mode only.** Off by default, because it puts payloads on our server.

### 3.2 Providers and signing

Stripe (`Stripe-Signature`, timestamped HMAC-SHA256), GitHub (`X-Hub-Signature-256`), Shopify (`X-Shopify-Hmac-Sha256`, base64), Standard Webhooks (`webhook-id`/`webhook-timestamp`/`webhook-signature`). Anything else: replay without re-signing, and mark scenarios that need re-signing as "needs secret / unsupported".

Secret from an environment variable only: `PROTO_SECRET`, or per provider (`STRIPE_WEBHOOK_SECRET`, `GITHUB_WEBHOOK_SECRET`, `SHOPIFY_WEBHOOK_SECRET`, `WEBHOOK_SECRET` for Standard Webhooks). Never a flag (shell history), never written to disk, never printed.

### 3.3 Scenarios

Default run: all six, in this order, on the selected events (1–3 related events; Shuffled needs ≥ 2).

| Scenario | What it sends | Level 1 verdict (transport) | With probe (Level 2) |
| -------- | ------------- | --------------------------- | -------------------- |
| **Once** | Each event once, freshly signed | 5xx, connection error or timeout → **High** · 401/403/400 → **Setup** (not a finding: "baseline rejected, check the secret"), and the run stops | Baseline digest recorded |
| **Twice** | Same event again, 500 ms later (same event ID; new delivery ID and timestamp where the provider does that) | Second response is 5xx, or differs in class from the first (2xx → 4xx/5xx) → **Medium** "duplicate not handled cleanly" · both 2xx and identical → pass + **Info** "Inconclusive: add a probe to see side effects" | Digest changes on the second delivery → **High** "Duplicate delivery changed state" |
| **Shuffled** | Related events in reverse order (reset hook first if configured) | Any 5xx → **High** | Final digest differs from the in-order final digest → **High** |
| **Late** | Valid signature, timestamp 360 s old (Stripe tolerance is 300 s; Standard Webhooks 5 min) | 2xx → **High** "Accepts old signed request (no replay protection)" · 4xx → pass (rejected) | Digest changes → raise to **High** with evidence |
| **Tampered** | One body field changed (an amount, an ID or a status), original signature kept | 2xx → **Critical** "Accepts tampered payload" · 4xx → pass | Digest changes → evidence added |
| **Slow budget** | No extra delivery: evaluates every delivery's latency | Latency > provider budget **and** ≥ 3× the run's median → **Medium**, labelled exactly as in provider-budgets.md §3 (e.g. `10 s budget · Webhook Trap default` for Stripe) | — |

Late does not apply to GitHub and Shopify (no signed timestamp): print `n/a (provider signs no timestamp)`, never a finding.

**Optional state probe** (Q5, opt-in): in `proving.yml`, a shell command whose stdout is the digest (e.g. `psql -Atc "select count(*) from orders"`), run after each delivery. Optional `reset:` command between scenarios. The digest is shown as `0 → 1`; its value never leaves the machine.

### 3.4 Output

Text only, following `ux-timetable-v2.md` §1.4: header (target, events, checks, budget with source) → deliveries grouped by scenario with `+ms`, http, latency, state, result → scenario strip → findings in severity order (What happened / Why it matters / Fix, one line of provider citation) → footer with counts and the next step. Same glyphs, ASCII fallback, `NO_COLOR`, 100-column limit, exit codes (0 / 1 at High+ / 2 incomplete / 3 config). Live row-by-row output in a TTY; the redrawn status line is optional.

Fix text is generic per scenario (no framework-aware snippets, no `show F1`).

## 4. What it must measure

All measurement is local, written to `.proving/runs/<run-id>.json`.

### 4.1 Recorded automatically per run

- Run start, each delivery's `+ms`, status, latency, digest change (yes/no, not the value), finding severity and scenario.
- **Time to first finding:** ms from run start to the first Medium-or-above finding printed.
- Levels used (L1, L1 + probe), provider, number of events, whether it is the first run in this folder.
- Environment: OS, Node or binary version, install method, minutes from first `proto` invocation to first completed run.

### 4.2 Recorded by the user after the run

`proto judge` asks, for each finding: Real / Known and accepted / False positive / Unsure · Fix before next release? (y/n) · Knew before? (y/suspected/n). Answers go into the same run file. (In live sessions the PO asks out loud and notes it; `proto judge` is for async runs.)

### 4.3 Exported summary (what the participant may share)

`proto export` writes `proving-summary.json` and prints it in full first, so the participant sees everything before sending it. It contains only: counts, scenarios, severities, levels, timings, judgements, environment. **No** payloads, headers, URLs, hostnames, event IDs, digests, file paths or secrets. Sending it is the participant's choice.

### 4.4 Derived by A01

Finding rate (strict and soft, Level 1 only and any level), false-positive share, median time to first finding, findings by scenario: the metrics in validation-plan §3.

## 5. Safety

| Rule | Detail |
| ---- | ------ |
| **Localhost only by default** | Targets must resolve to `localhost`, `127.0.0.1`, `::1` or `*.localhost`. Any other host needs `--allow-host <name>` matching exactly, and prints a warning. Never a public provider endpoint. |
| **No redirects** | Never follow redirects. A 3xx is reported as a response. |
| **Timeouts and caps** | 30 s per request. At most 25 deliveries per run. Ctrl-C stops at once and marks the run aborted. |
| **Secrets** | Env vars only. Not stored, not logged, not in run files or the summary. |
| **Side effects** | Before Twice and Shuffled, print a warning and ask once: "Your handler may send emails or charges twice. Test mode only. Continue?" Every delivery carries `X-Proving-Dry-Run: 1` so handlers can skip external calls if the developer wants. |
| **Local data** | Captures and run files live in `.proving/` in the current folder. The tool writes `.proving/.gitignore` containing `*` so nothing is committed by accident. |
| **No outbound network** | The only outbound connections are to the configured target (and, in record mode, the forward target). |
| **Review** | A09 does a 30-minute review of these rules before the first external participant runs it. |

## 6. Language (relates to Q3)

| Option | For | Against |
| ------ | --- | ------- |
| **TypeScript on Node ≥ 20, no runtime dependencies** (★ recommended) | Fastest to build in 2 weeks; `node:crypto`, `node:http` and `fetch` cover everything; most web Integrators already have Node; run with `npx` from a tarball or the spike branch; the team already writes TypeScript (1.x is AdonisJS/Next) | Needs Node on the participant's machine; says little about a Go binary's install experience |
| Go single binary | One file, no runtime; exercises the Q3 leaning; good timing precision | Slower to build; cross-compiling and unsigned binaries trigger OS warnings (macOS Gatekeeper), which adds friction that isn't the product's |
| PHP (phar) | Matches the 2.0 server (ADR-002) | Poor fit for a distributable agent; fewer Integrators have PHP locally |

**Recommendation: TypeScript on Node.** The prototype is thrown away, so speed of building wins. It **does not decide Q3**. It contributes one data point to Q3: how many participants had Node ready and how long install took (§4.1). If the PO wants the prototype to double as Q3 evidence, choose Go and accept about 2–3 extra days, which pushes the first prototype session to Tue 24 November.

## 7. Repository location

- Folder: `spikes/proving-cli/`, a standalone package with its own `package.json`. It is outside the pnpm workspace globs (`apps/*`, `packages/*`), so it doesn't touch the monorepo build.
- Branch: `spike/proving-cli`, created from `rewrite/v2`, in its own worktree `.worktrees/spike-proving-cli`. (Same pattern as `spikes/live-props/`.)
- Never merged. After the decision: archive the branch (tag `spike/proving-cli-final`) and delete the worktree. Results live in `project/product/validation/`, not in the spike.
- Distribution to participants: a tarball the PO sends, or `npx github:<repo>#spike/proving-cli` if the PO pushes the branch. Pushing is a PO decision.

## 8. Participant setup (sent the day before)

1. Node 20 or later (`node -v`).
2. Get the tool (tarball or `npx` command).
3. Your app running locally against a test or sandbox database, with the provider in test mode.
4. Set the signing secret in your shell: `export STRIPE_WEBHOOK_SECRET=…` (from `stripe listen` or your test endpoint).
5. Optional: run `proto record` once and trigger one test event, so we start with an event already captured.
Takes about 10 minutes. If anything fails, stop: we'll do it together.

## 9. Acceptance criteria (before the first external run, by Fri 20 Nov)

1. On a **sample Express handler with known bugs** (no signature check on one route, no idempotency, a 12 s sleep on one path, a crash on out-of-order events), a default run with a probe reports: Tampered Critical, Late High, Twice High (probe), Shuffled High, Slow budget Medium. On the **fixed version** of the same handler, it reports no finding.
2. Same handler, Level 1 only: Tampered, Late, Shuffled (crash) and Slow budget are found; Twice shows Info "Inconclusive: add a probe".
3. Signatures produced by the tool are accepted by the official verification in each provider's SDK (Stripe, `@octokit/webhooks`, Shopify's HMAC, `standardwebhooks`) for Once.
4. Record mode forwards `stripe listen` traffic unchanged (handler still verifies) and saves the exact raw bytes.
5. Output matches the §1.4 structure and rules (glyphs, ASCII fallback, `NO_COLOR`, width, exit codes) on the sample run.
6. A non-local target is refused without `--allow-host`. No network call other than the target is made (checked with a network monitor during one run).
7. No secret appears in any file under `.proving/` or in `proving-summary.json` (grep check), and the summary contains none of the excluded fields in §4.3.
8. A first run from a clean folder (setup done) to the summary takes under 2 minutes on the sample handler.
9. A09's safety review is done and its must-fixes are closed.
