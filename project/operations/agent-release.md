# Agent release runbook

Owner: A10 (ops), with A08 (engine). Implements ADR-010 "Distribution and supply chain"
items 1–3, 6, 7 and 9, and replaces the plan in `ci-cd.md` §5. Homebrew (item 4) and macOS /
Windows signing (item 5, `ci-cd.md` §6) are not built yet.

| Piece | Path |
| ----- | ---- |
| Release workflow | `.github/workflows/agent-release.yml` (tag `agent-v*`; `workflow_dispatch` = dry run) |
| Dry run on every push and PR | job `agent-dist` in `.github/workflows/ci.yml` |
| One target → archive | `agent/release/build.sh VERSION COMMIT GOOS GOARCH OUT_DIR` |
| Archives → npm tarballs + smoke test | `agent/release/npm-pack.sh VERSION ARCHIVE_DIR OUT_DIR` |
| npm package assembly | `agent/npm/assemble.mjs` (platform packages generated from its target table) |
| Launcher package source | `agent/npm/agent/` (`package.json`, `bin/webhooktrap.js`, `README.md`) |
| Install script | `agent/install.sh` (also attached to every release) |

## 1. What a release produces

Tag `agent-v1.2.3` → version `1.2.3`. The version is always **bare semver**: the server
compares it with `config('agent.min_agent_version')` through PHP `version_compare`, and
`v1.2.3` sorts below `0.9.0`, so a "v" build would be refused as too old everywhere. The
workflow and `build.sh` reject anything else.

Jobs (permissions are per job; the workflow grants nothing):

| Job | Runs on | Permissions | Does |
| --- | ------- | ----------- | ---- |
| `meta` | tag, dispatch | none | version from the tag (dispatch: `0.0.0-dryrun.<run>`), publish only on a tag push |
| `test` | tag, dispatch | `contents: read` | `go vet`, `go test ./...` |
| `build` ×6 | tag, dispatch | `contents: read` | `CGO_ENABLED=0 go build -trimpath -buildvcs=false -ldflags "-s -w -buildid= -X …Version= -X …Commit="` on the go.mod toolchain (checked), archive with sorted entries, fixed owner and mtime (the commit time): byte-for-byte reproducible |
| `package` | tag, dispatch | `contents: read` | `SHA256SUMS`; the linux/amd64 binary must report the version, commit and protocol v1; `npm-pack.sh` (checksums again, extract, assemble, `npm pack --dry-run`, `npm pack`, smoke tests) |
| `release` | tag | `contents: write`, `id-token: write`, `attestations: write` | `cosign sign-blob` (keyless, cosign v3.1.4 as in `container.yml`) → `SHA256SUMS.sigstore.json`, plus `SHA256SUMS.sig` and `SHA256SUMS.pem` taken from the bundle; `cosign verify-blob` against the workflow identity and commit; SLSA provenance on every archive (`attest-build-provenance`); **draft** GitHub release |
| `npm-publish` | tag | `contents: read`, `id-token: write`; environment `npm-publish` | npm 11.21.1, `npm publish <tgz> --provenance --access public` (OIDC trusted publishing, no token), six platform packages then the launcher; prereleases get dist-tag `next`; checks the provenance is on the registry |
| `finalize` | tag | `contents: write` | publishes the draft release, then runs `install.sh` against it with cosign and `WEBHOOKTRAP_REQUIRE_SIGNATURE=1` |

Release assets: `webhooktrap_<v>_{linux,darwin}_{amd64,arm64}.tar.gz`,
`webhooktrap_<v>_windows_{amd64,arm64}.zip` (binary + LICENSE), `SHA256SUMS`,
`SHA256SUMS.sigstore.json`, `SHA256SUMS.sig`, `SHA256SUMS.pem`, `install.sh`.

npm packages (all `AGPL-3.0-only`, repository `jiordiviera/webhooktrap`, homepage
`https://webhooktrap.dev`, no `scripts` at all; `npm-pack.sh` fails if one appears):

| Package | Content |
| ------- | ------- |
| `@webhooktrap/agent-linux-x64`, `-linux-arm64`, `-darwin-x64`, `-darwin-arm64`, `-win32-x64`, `-win32-arm64` | `bin/webhooktrap[.exe]`, `os`/`cpu` set, `preferUnplugged` |
| `@webhooktrap/agent` | `bin/webhooktrap.js` (no dependencies; resolves the platform package, `execFileSync` with inherited stdio, passes argv and the exit status through; clear errors for an unsupported platform or a skipped optional dependency), the six platform packages as `optionalDependencies` pinned to the exact same version |

## 2. One-time setup on npmjs.com (PO, before the first tag)

npm can only attach a trusted publisher to a package that **already exists** on the registry
(`npm trust` docs: "The package must already exist"; there is no "pending publisher" as on
PyPI). So the first version of each of the seven packages has to be published by hand once.
The real release then goes through OIDC.

1. **Org.** On npmjs.com create the organization `webhooktrap` (free plan, public packages).
   Turn on 2FA for every member (Org settings → "Require two-factor authentication") and on
   your own account in "Authorization and writes" mode. Keep the recovery codes offline.
2. **Typosquat reservations (ADR-010 item 1, HD-3).** npm already refuses new names that differ
   from an existing one only by punctuation (`webhook-trap`, `webhook_trap`, `webhook.trap`
   once `webhooktrap` exists), so reserve these few. All returned 404 on 2026-11-16:
   - unscoped `webhooktrap` (placeholder pointing at `@webhooktrap/agent`);
   - unscoped `webhooktrap-agent` and `webhooktrap-cli`;
   - the org `webhook-trap` (create it, publish nothing).
   Each placeholder is a `package.json` with `"version": "0.0.0"`, a README naming the real
   package and `AGPL-3.0-only`, published with `npm publish --access public`, then
   `npm deprecate <name>@0.0.0 "Use @webhooktrap/agent"`.
3. **Placeholders for the seven real packages.** From any clean directory:

   ```sh
   for p in agent agent-linux-x64 agent-linux-arm64 agent-darwin-x64 agent-darwin-arm64 agent-win32-x64 agent-win32-arm64; do
     mkdir -p "$p" && cd "$p"
     printf '{"name":"@webhooktrap/%s","version":"0.0.0","description":"Placeholder. Install @webhooktrap/agent.","license":"AGPL-3.0-only","repository":{"type":"git","url":"git+https://github.com/jiordiviera/webhooktrap.git"}}\n' "$p" > package.json
     npm publish --access public          # asks for the 2FA code
     cd ..
   done
   ```

   These hold no code and no bin. After `1.0.0` is out, deprecate them so nobody pins them:
   `npm deprecate '@webhooktrap/agent@0.0.0' 'Placeholder, use 1.x'` (same for the six others).
4. **GitHub side.** In the repository settings:
   - Environments → new environment `npm-publish`: required reviewer = PO; deployment
     branches and tags = "Selected", tag pattern `agent-v*`. No secrets in it.
   - Rules → Rulesets → new tag ruleset for `agent-v*`: restrict creation, update and deletion
     to the PO (bypass list = repository admin only).
5. **Trusted publisher, one per package (all seven).** Either on npmjs.com, package → Settings →
   Trusted publishing → GitHub Actions, or with npm ≥ 11.15.0 and 2FA:

   ```sh
   for p in agent agent-linux-x64 agent-linux-arm64 agent-darwin-x64 agent-darwin-arm64 agent-win32-x64 agent-win32-arm64; do
     npm trust github "@webhooktrap/$p" --repo jiordiviera/webhooktrap --file agent-release.yml --env npm-publish --allow-publish
   done
   ```

   | Field | Value |
   | ----- | ----- |
   | Organization or user | `jiordiviera` |
   | Repository | `webhooktrap` |
   | Workflow filename | `agent-release.yml` (file name only) |
   | Environment | `npm-publish` |

   **Timing:** npm expires a new trusted publisher configuration if it has not completed a
   successful publish within 2 days. Do step 5 the same day as the first tag (§4). If it expired,
   revoke it (`npm trust list`, `npm trust revoke --id <id>`) and create it again.
6. **After the first release succeeded:** for each of the seven packages, Settings → Publishing
   access → "Require two-factor authentication and disallow tokens". CI keeps working (OIDC is
   not a token); a stolen npm token can no longer publish.

## 3. Repository visibility

The repository is public (checked 2026-11-16). npm provenance and GitHub artifact attestations
both require a public repository: if it ever goes private, `npm publish --provenance` fails and
the `attest-build-provenance` step is skipped (same guard as `container.yml`).

## 4. Cutting a release

1. `v2` is green in CI (including `agent-dist`). Optional: Actions → agent-release → Run
   workflow (dry run) on `v2`.
2. Check the minimum version (§6) still accepts the new version.
3. First release only: §2 steps 5 done today.
4. Tag and push from a clean checkout of the commit to release:

   ```sh
   git tag -s agent-v1.0.0 -m "agent 1.0.0"      # or -a if you do not sign tags
   git push origin agent-v1.0.0
   ```

5. Approve the `npm-publish` deployment when the run asks for it (after `release` has drafted
   the GitHub release).
6. Check:
   - `npx @webhooktrap/agent@1 version --json` prints `"version":"1.0.0"`;
   - `npm view @webhooktrap/agent@1.0.0 dist.attestations` shows a provenance attestation, and
     the npm page shows "Built and signed on GitHub Actions";
   - the GitHub release is published (not draft) with 11 assets; the `finalize` job installed it
     with `install.sh` and verified the signature.
7. Prereleases: tag `agent-v1.1.0-rc.1`. npm dist-tag `next`, GitHub "pre-release";
   `npx @webhooktrap/agent@1` and `install.sh` (default) ignore it.

If a job fails, "Re-run failed jobs" keeps the tag push event. `npm-publish` skips versions
already on the registry, so a partial publish completes on re-run. A failure before
`npm-publish` leaves only a draft release: delete the draft and re-run.

## 5. Rolling back or withdrawing a version

npm versions are immutable and unpublish is limited (72 h, and never when other packages
depend on it), so the tool is **deprecate + move the dist-tag**, then ship a fix:

```sh
# 1.0.1 is bad, 1.0.0 is good
npm dist-tag add @webhooktrap/agent@1.0.0 latest
npm deprecate '@webhooktrap/agent@1.0.1' 'Broken: use 1.0.0 or 1.0.2'
# the platform packages follow the launcher's exact pins; deprecate them too:
for p in linux-x64 linux-arm64 darwin-x64 darwin-arm64 win32-x64 win32-arm64; do
  npm deprecate "@webhooktrap/agent-$p@1.0.1" 'Broken: use 1.0.0 or 1.0.2'
done
```

npm picks the `latest` dist-tag whenever it satisfies the requested range, so
`npx @webhooktrap/agent@1` and `npm i -g @webhooktrap/agent` get `1.0.0` again; it also avoids
deprecated versions when another one matches. People with `1.0.1` already installed keep it
until they update: ship `1.0.2` quickly. On GitHub, edit the bad release to "pre-release" (it drops
out of `install.sh`'s default choice) and say why in its notes; do not delete it (people verify
old downloads against it). To refuse a bad version on the server, raise `min_agent_version`
(§6) above it. Unpublish only for a security emergency within 72 h, and only after the PO
decides: `npm unpublish '@webhooktrap/agent@1.0.1'` first (npm refuses to unpublish a
version another package depends on), then the six platform packages at the same version.

A compromised release (key or workflow): deprecate every affected version, raise
`min_agent_version`, revoke and recreate the trusted publishers, rotate GitHub access, then
follow `SECURITY.md`.

## 6. Server version gate

`app/Modules/Agent`:

- `config/agent.php` `min_agent_version` = `0.9.0`. `pair` and `hello` answer **426
  `agent_too_old`** (with `min_agent_version`) below it (`AgentVersions::refused`,
  `TerminalText::versionBelow`: numeric core via `version_compare`, a prerelease of the same
  core counts as older).
- `0.0.0-dev` (an unstamped build) is refused unless `AGENT_ALLOW_DEV_BUILDS=true` **and**
  `APP_ENV` is `local` or `testing`: hosted and production self-host always refuse it. Release
  builds are always stamped, so this never hits a released agent.
- Protocol: the agent sends `proto_min = proto_max = 1`; the server accepts 1, else 426
  `proto_unsupported`. `version --json` reports `"protocol":"v1"`.
- The agents list marks `tooOld` with the same comparison.

A released `1.x` (e.g. `1.0.0`) passes all three. A dry-run build (`0.0.0-dryrun.N`,
`0.0.0-ci`) is below `0.9.0` and is refused, which is intended. When a release changes the
protocol or drops support, raise `min_agent_version` in the same server deploy that needs it;
"agent too old" messages point to `npm i -g @webhooktrap/agent@latest` (no self-update, ADR-010
item 7).

Known gaps outside this pipeline (reported 2026-11-16, not changed here):

- The pairing panel (`resources/js/components/agent/pair-panel.tsx`, `pairCommand`) shows
  `webhooktrap pair CODE [--server ORIGIN]`, and `config/agent.php` `pair_command` defaults to
  `webhooktrap pair {code} --server {origin}`. ADR-010 item 6 says the UI shows
  `npx @webhooktrap/agent@1 pair CODE`, which also works for people who never installed the
  binary. One of the two should change.
- "Agent too old" copy (`resources/js/lib/run/format.ts`, `agent-detail.tsx`) says
  `npm i -g @webhooktrap/agent@latest` only; people who used `install.sh` update by running it
  again (ADR-010 item 7: "points to the user's install channel").

## 7. Install script and manual verification

```sh
curl --proto '=https' --tlsv1.2 -fsSL https://raw.githubusercontent.com/jiordiviera/webhooktrap/v2/agent/install.sh | sh
# or the copy attached to a release; options:
WEBHOOKTRAP_VERSION=1.0.0 PREFIX=/usr/local WEBHOOKTRAP_REQUIRE_SIGNATURE=1 sh install.sh
```

It downloads over HTTPS only (curl `--proto =https`, wget `--https-only`), picks the newest
`agent-v1.*` non-prerelease unless `WEBHOOKTRAP_VERSION` is set, verifies `SHA256SUMS` with
cosign when cosign is on the PATH (else says so and prints the commands below, or refuses with
`WEBHOOKTRAP_REQUIRE_SIGNATURE=1`), checks the archive's SHA-256, then installs
`$PREFIX/bin/webhooktrap` (default `~/.local/bin`) with an atomic rename. Serving it from
`https://webhooktrap.dev/install.sh` is a later step (owner of `public/` and routes).

By hand, for release `agent-v1.0.0`:

```sh
v=1.0.0; base=https://github.com/jiordiviera/webhooktrap/releases/download/agent-v$v
curl -fsSL -O "$base/webhooktrap_${v}_linux_amd64.tar.gz" -O "$base/SHA256SUMS" -O "$base/SHA256SUMS.sigstore.json"
cosign verify-blob --bundle SHA256SUMS.sigstore.json \
  --certificate-identity "https://github.com/jiordiviera/webhooktrap/.github/workflows/agent-release.yml@refs/tags/agent-v$v" \
  --certificate-oidc-issuer https://token.actions.githubusercontent.com SHA256SUMS
sha256sum --ignore-missing -c SHA256SUMS          # macOS: shasum -a 256 --ignore-missing -c SHA256SUMS
gh attestation verify "webhooktrap_${v}_linux_amd64.tar.gz" --repo jiordiviera/webhooktrap
```

Without cosign, `SHA256SUMS.pem` shows who signed (`openssl x509 -in SHA256SUMS.pem -noout -text`:
the SAN must be the workflow URL above) but checking `SHA256SUMS.sig` against it with openssl
skips the Fulcio chain and the Rekor proof, so it is not a substitute for cosign or
`gh attestation verify`.

npm installs: `npm audit signatures` in a project that depends on `@webhooktrap/agent` checks
the registry signatures and provenance attestations.

## 8. Local build

```sh
for t in linux/amd64 linux/arm64 darwin/amd64 darwin/arm64 windows/amd64 windows/arm64; do
  mise exec go@1.27.4 -- agent/release/build.sh 1.0.0 "$(git rev-parse HEAD)" "${t%/*}" "${t#*/}" agent/dist/release
done
(cd agent/dist/release && sha256sum webhooktrap_* > SHA256SUMS)
agent/release/npm-pack.sh 1.0.0 agent/dist/release agent/dist/npm
node agent/dist/npm/node_modules/@webhooktrap/agent/bin/webhooktrap.js version --json
```

`build.sh` needs GNU tar (Linux; on macOS use `gtar` or CI). `agent/dist/` is git-ignored.
