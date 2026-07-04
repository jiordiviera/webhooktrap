# Webhook Trap

> **Capture. Inspect. Replay.** Open-source webhook debugger for developers.

Webhook Trap is a self-hosted webhook debugging tool. Receive webhooks from Stripe, GitHub, Shopify, or any provider. Inspect payloads in real-time. Replay to localhost, staging, or production. See the response instantly. No tunnels, no SaaS lock-in.

**Perfect for:**
- Debugging webhook integrations locally
- Testing Stripe, GitHub, Shopify, or custom webhooks
- Sharing webhook events with teammates
- Understanding request/response pairs without logs

---

## Quick Start (Docker)

```bash
git clone https://github.com/jiordiviera/webhooktrap.git
cd webhooktrap

docker-compose up
```

Visit **http://localhost:7777** and create your first inbox.

See [DEPLOYMENT.md](./DEPLOYMENT.md) for production setup (Railway, Fly.io, VPS).

---

## Local Development (pnpm)

### Prerequisites
- Node.js 18+
- pnpm 9+
- PostgreSQL 16 (local or Docker)

### Setup

```bash
pnpm install
pnpm dev              # API on :3333 + Web on :7777
```

### Commands

```bash
pnpm build            # Build all packages
pnpm test             # Run API tests (Japa)
pnpm lint             # Lint all packages
pnpm check-types      # TypeScript check
pnpm format           # Prettier format
```

---

## Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | Next.js 16, Tailwind CSS 4, shadcn/ui, TanStack Query |
| **Backend** | AdonisJS 6 (TypeScript), Lucid ORM |
| **Database** | PostgreSQL 16 |
| **Auth** | OAuth (GitHub, Google), TOTP 2FA, Access tokens |
| **Media** | Cloudflare R2 (or local storage) |
| **Monorepo** | pnpm + Turborepo |

---

## Project Structure

```
webhooktrap/
├── apps/
│   ├── api/          AdonisJS 6 — webhook ingest, replay, auth
│   ├── web/          Next.js — landing, dashboard, inbox UI
│   └── videos/       Remotion — demo videos (optional)
├── packages/
│   ├── ui/           shadcn/ui components + Tailwind
│   ├── types/        Shared TypeScript types
│   └── eslint-config/
├── docs/             Product specs, architecture, ADRs
├── docker-compose.yml
└── DEPLOYMENT.md     Hosting guide
```

---

## Features

✅ **Receive webhooks** — Unique URL per inbox, always returns 200 OK  
✅ **Inspect payloads** — Raw headers, body, method, IP, timestamp  
✅ **Replay to any URL** — Localhost, staging, production  
✅ **See responses** — Status, latency, body, headers  
✅ **Share read-only links** — Show events to teammates without signup  
✅ **Anonymous mode** — No account required for first capture  
✅ **OAuth + 2FA** — GitHub/Google login, TOTP  
✅ **Self-hosted** — Full control, no vendor lock-in  

---

## Contributing

See [CONTRIBUTING.md](./CONTRIBUTING.md) for setup, testing, and PR guidelines.

**Want to help?** Issues tagged `good first issue` are perfect for new contributors.

---

## Deployment

- **Local:** `docker-compose up` (5 min)
- **Railway:** Click "Deploy" in Railway dashboard (~$5/mo)
- **Fly.io:** `flyctl launch` + env setup (~$7/mo)
- **VPS:** See [DEPLOYMENT.md](./DEPLOYMENT.md) for step-by-step Caddy + systemd

---

## License

MIT — See [LICENSE](./LICENSE) for details.

---

## Community

- **Issues & Bugs:** [GitHub Issues](https://github.com/jiordiviera/webhooktrap/issues)
- **Discussions:** [GitHub Discussions](https://github.com/jiordiviera/webhooktrap/discussions)
- **Security:** Email `security@jiordiviera.me` (do not open public issues)

---

**Built by** [@jiordiviera](https://github.com/jiordiviera)
