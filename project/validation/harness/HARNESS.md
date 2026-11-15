# Proxy-validation run harness

Runs one approved repository from `proxy-repo-candidates.md` against the proving prototype (`spikes/proving-cli`, built static with go 1.27.4) under `scenarios-and-judge.md` §15, inside disposable containers. Authorised by PO decision 42.

## Isolation design

| Phase | Container | Network | Notes |
| --- | --- | --- | --- |
| prepare | none (image builds) | host build | Toolchain image (`images/php` or `images/node`) built from official bases plus the prototype binary. It contains **no repository code**. The egress proxy image (`proxy/`) is alpine + tinyproxy. |
| setup | `wtp-<repo>-setup`, deleted at exit | **internal** Docker network only. The only way out is the tinyproxy container, which allows only the hosts in `proxy/allowlist` (GitHub, Packagist, npm, PyPI, Go proxy). | `git clone` + package install + migrations. Install scripts do run (Composer/npm), with network limited to the allowlist. |
| cutnet | none | proxy and both networks removed | Logs the egress hosts reached or refused into `setup-log.md`. |
| run | `wtp-<repo>-app` | `--network none` (loopback only; checked: only `lo`, empty route table) | The app listens on 127.0.0.1. |
| run | `wtp-<repo>-prober` | `--network container:<app>` (same loopback, still no route) | Runs `proto import` and `proto run`, and the probe and reset commands. |
| teardown | none | none | Removes containers, the volume, networks and images. The generated secret is shredded. It checks by label that nothing is left (`wt.repo=<repo>`). |

Every container that runs repository code gets `--cap-drop ALL`, `--security-opt no-new-privileges`, `--user 10001` (non-root), `--read-only` root fs with tmpfs `/tmp` and `/wt`, CPU/memory/pids limits (setup 4 CPU/4 GB/1024, app 2/2 GB/512, prober 1/1 GB/256). There is no Docker socket, no `--privileged` and **no bind mount at all**: repository files live only in the named volume `wtp-<repo>-work`. Scripts and fixtures go in through stdin or an env var. Results come out by exact, validated file name through `docker exec cat`, never by extracting a tar from the container.

Secrets: `wt.sh` generates a random `whsec_test_…` (Stripe) or `wt_test_…` secret per repository and stores it in the scratchpad (`proxy-runs/<repo>/secret.env`, mode 600, shredded at teardown). It reaches the app through `app.env` (`__WT_SECRET__` placeholder) and reaches `proto` as `STRIPE_WEBHOOK_SECRET` / `GITHUB_WEBHOOK_SECRET`. No `.env` from this machine is read. Every output file is grepped for the secret after each run.

Work dir (scratchpad, never in the repo): `/tmp/claude-1000/…/scratchpad/proxy-runs/<repo>/` holds the full install logs, app log tails, the proxy log and the secret.

## Usage

```bash
cd project/validation/harness
./wt.sh <owner__repo> prepare      # images, networks, proxy
./wt.sh <owner__repo> setup        # clone + install (repeatable while inside the 60 min timebox)
./wt.sh <owner__repo> setupexec '<cmd>'   # inspect the volume offline (--network none)
./wt.sh <owner__repo> cutnet       # network gone for good
./wt.sh <owner__repo> start        # app + prober, waits for the port
./wt.sh <owner__repo> run l1-run1 L1          # Level 1
./wt.sh <owner__repo> run l1l2-run1 probe     # Level 1 + probe/reset (proving.yml written by the harness)
./wt.sh <owner__repo> run l1-run2-rerun L1    # rerun when a run had a finding (15.3 step 3)
./wt.sh <owner__repo> logs | exec '<cmd>'     # debugging
./wt.sh <owner__repo> teardown
```

A recipe is `recipes/<owner__repo>/`: `recipe.env` (REPO, IMAGE_KIND, PORT, TARGET_PATH, PROVIDER, optional BUILD_ARGS), `setup.sh` (runs in the setup container), `start.sh` (runs in the app container), `app.env` (test values only), `fixtures/NN-<type>.json` (synthetic test-mode event bodies; file order is capture order, at most 3), and optionally `probe.sh` and `reset.sh` (run in the prober; the probe appends each reading to `<label>.probe-readings.log`).

Outputs per repository go to `project/validation/runs/<owner__repo>/`: `setup-log.md` (timestamps, egress hosts, netns check), `<label>.console.txt` (full prototype output and exit code), `<label>.run.json` (prototype run file), `<label>.probe-readings.log` and `notes.md` (setup minutes, what was needed, scenarios run and not run, how the run reads under the spec).

## Self-test

`recipes/selftest__proving-sample` runs the prototype's own buggy `sample/handler.js` (trusted code, not a study repository) through the full pipeline with `WT_OUT=harness/selftest`. On 2026-11-14 it reproduced the expected results: a duplicate changed state (High), a 500 on reverse order (High), slow responses (Medium), and Late and Tampered rejected. Outputs are in `selftest/`.

## Known limits (read before the other 13 runs)

- **Prototype vs spec.** The prototype runs Once, Twice, Shuffled, Late, Tampered and Slow budget only. It cannot run Retry after error, Burst or Unsigned. It still uses the 3× median slow rule, its own tamper rule (not TAMP-1), at most 3 events and 25 deliveries, and for GitHub duplicates it generates a **new** `X-GitHub-Delivery` (DUP-1 says to keep it). It does not run the PRB-10 stability check, and its run file stores `digest_changed` rather than probe values. That is why the harness logs probe readings separately. Re-judge every run against the spec. Do not reuse the prototype's verdict line.
- **Response bodies and request headers are not stored** by the prototype. App log tails are kept in the scratchpad only.
- **Egress allowlist** blocks hosts outside it (for example GitLab-hosted packages and binary downloads from CDNs). Add a host to `proxy/allowlist` and record the change in that repository's `setup-log.md`.
- **Install scripts run with network** (allowlisted). The proxy can still reach any IP an allowlisted name resolves to, but not the host's LAN services by address.
- **The prober runs repository code** (probe and reset commands such as `php artisan`). The prototype binary and run files inside the prober are therefore not tamper-proof against the repository. This is accepted: it is an integrity risk to the results, not a confinement risk.
- **Docker build cache** for the toolchain images is not pruned (pruning would touch other projects' caches). It holds only official base layers.
- Base images (`php:8.5-cli-bookworm`, `node:22-bookworm`, `composer:2`) were removed after the pilot. The next `prepare` pulls them again.
