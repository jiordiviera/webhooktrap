# License checks

Webhook Trap is AGPL-3.0-only (`LICENSE`, `composer.json`, `package.json`). The agent archives ship the
same `LICENSE` (agent-release.md). This page lists how dependency and asset licenses are checked.

## When

Before every release, and in the review of any pull request that adds a dependency, a font, an icon set
or an image. New packages beyond Laravel first-party and the starter kit are proposals first (CLAUDE.md).

## How

```bash
composer licenses --no-dev                     # PHP runtime dependencies
pnpm licenses list --prod                      # JS runtime dependencies
cd agent && go list -m all                     # Go modules (read each module's LICENSE)
```

Allowed without discussion: MIT, BSD-2/3-Clause, 0BSD, ISC, Apache-2.0, MPL-2.0, Unlicense, CC0-1.0,
BlueOak-1.0.0, and OFL-1.1 for fonts. These are compatible with distributing the whole under AGPL-3.0.
Anything else (GPL-2.0-only, SSPL, BUSL, "Commons Clause", proprietary or no license) needs a PO decision
before it is added.

## Last check (2026-11-17)

- Composer, runtime: MIT 142, BSD-3-Clause 10, Apache-2.0 1.
- pnpm, production: MIT 146, ISC 7, Apache-2.0 4, OFL-1.1 2, MPL-2.0 2, BSD-3-Clause 1, 0BSD 1,
  Unlicense 1, BlueOak-1.0.0 1, MIT or CC0-1.0 1.
- Go: one third-party module, `gopkg.in/yaml.v3` (MIT and Apache-2.0).
- Fonts: Archivo, Martian Mono and Inter are OFL-1.1.
- Provider logos (svgl.app) are trademarks of their owners. They are used to name the provider only,
  not as Webhook Trap branding.

Nothing incompatible was found.

## Copy rules

- Public copy says AGPL-3.0, never MIT. The 1.x MIT references are gone from the 2.0 tree; only
  third-party lockfile entries mention MIT.
- Pricing pages sell the hosted service (quotas, retention, hosted CI history, team features, support).
  They never imply that a paid plan unlocks an engine, scenario, pack or CLI feature (decision 78).
- The self-hosting docs explain the AGPL network clause without suggesting it can be avoided by hosting
  privately (docs/guides/self-hosting.mdx, "License").
