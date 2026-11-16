# Docs screenshots

`screenshots.mjs` regenerates every image in `docs/images/` (WebP, 1440×900 at 2×, dark theme).

- Public pages (landing, example run, sign-in) come from production, read-only.
- Everything signed in comes from the **local** lerd site (`https://webhooktrap.test`) with a demo
  account, three demo Stripe captures signed with a demo-only secret, and a real run of the agent
  against `demo-handler.mjs`, a deliberately buggy handler on `127.0.0.1:4242`.
- Local host names and private addresses are shown as `app.webhooktrap.dev`, `in.webhooktrap.dev`
  and `203.0.113.x` on the page before each capture. Magenta frames are an overlay injected into
  the page, so they stay sharp.

Nothing is created on production. The script refuses a `WT_LOCAL` that isn't `*.test` or `localhost`.

## Regenerate

```bash
cd docs/scripts
npm install                                   # playwright-core and sharp, local to this folder

# 1. the demo account (local site only), once
php artisan tinker docs/scripts/seed-demo.php # from the repository root

# 2. an agent build stamped with a release version (the server refuses 0.0.0-dev)
(cd ../../agent && mise exec go@1.27.4 -- go build \
  -ldflags "-X github.com/jiordiviera/webhooktrap/agent/internal/version.Version=1.0.0" \
  -o ../docs/scripts/.out/webhooktrap ./cmd/webhooktrap)

# 3. the shots (Chrome from /usr/bin/google-chrome; PW_CHANNEL=chromium for Playwright's Chromium)
WT_DEMO_PASSWORD='<UserFactory::PASSWORD>' WEBHOOKTRAP_BIN=$PWD/.out/webhooktrap node screenshots.mjs
```

`WT_DEMO_PASSWORD` is the factory fixture value in
`app/Modules/Identity/Database/Factories/UserFactory.php`. `ONLY=prod`, `ONLY=guest` or
`ONLY=account` (comma-separated) runs part of the set. The local site needs its workers running
(Horizon, Reverb, Vite) and `curl` on the PATH (the demo reset command).

Each run creates a guest session and a `brindle-app` project for the demo account on the local
database, and unpairs its agent at the end. Take a lerd database snapshot first if you want to
roll that back.
