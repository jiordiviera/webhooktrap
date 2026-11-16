# @webhooktrap/agent

The Webhook Trap agent: one binary that pairs your machine with
[Webhook Trap](https://webhooktrap.dev), receives captured webhooks, proves your
handler against provider scenarios (Stripe, GitHub, Shopify, Standard Webhooks)
and replays them to a target on your machine.

## Usage

Pair from the code the web app shows you (codes expire after 10 minutes):

```bash
npx @webhooktrap/agent@1 pair XXXXX-XXXXX
```

Or install it once and run `webhooktrap`:

```bash
npm i -g @webhooktrap/agent
webhooktrap pair XXXXX-XXXXX
webhooktrap agent          # stay connected and take runs from the web app
webhooktrap test           # run scenarios locally from webhooktrap.yml
webhooktrap version --json
```

A self-hosted server: `webhooktrap pair CODE --server https://trap.example.com`
(or set `WEBHOOKTRAP_SERVER`).

## How it is packaged

- This package holds a small launcher (`bin/webhooktrap.js`, no dependencies).
  The binary comes from one of `@webhooktrap/agent-{linux,darwin,win32}-{x64,arm64}`,
  which npm installs as an optional dependency for your OS and CPU only, at the
  exact version of this package.
- **No `postinstall` script and no download at install or run time.** npm's
  lockfile integrity covers the binary, and `--ignore-scripts` changes nothing.
- Every version is published from GitHub Actions with npm trusted publishing and
  provenance: `npm audit signatures` checks it, and the npm page links the exact
  workflow run and commit.
- If you install with optional dependencies turned off (`--no-optional`,
  `--omit=optional`), the launcher cannot find the binary and says so. Reinstall
  with `--include=optional`, or use a release archive.

## Other install channels

Signed release archives (`SHA256SUMS` signed keyless with Sigstore, SLSA build
provenance) are on [GitHub Releases](https://github.com/jiordiviera/webhooktrap/releases)
under tags `agent-v*`. `agent/install.sh` in the repository downloads one, checks
it and installs `webhooktrap` to `~/.local/bin`.

There is no self-update. When the server says the agent is too old, update it the
way you installed it: `npm i -g @webhooktrap/agent@latest`, or `npx @webhooktrap/agent@1`
fetches the newest 1.x on its own.

## License

[AGPL-3.0-only](https://github.com/jiordiviera/webhooktrap/blob/v2/LICENSE).
