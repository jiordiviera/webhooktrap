# Hébergement — Vercel + VPS + Neon

| Service | Rôle |
|---------|------|
| **Vercel** | Frontend Next.js (landing, inbox, dashboard) |
| **VPS** | API AdonisJS derrière Caddy |
| **Neon** | PostgreSQL (production uniquement) |

> **Dev local** : PostgreSQL natif (`brew install postgresql` / `apt install postgresql`)
> **Prod** : Neon

## Architecture

```mermaid
flowchart TB
    subgraph internet [Internet]
        DNS["hookscope.dev → Vercel\napi.hookscope.dev → VPS"]
        BROWSER["Navigateur"]
        STRIPE["Stripe / GitHub / …"]
    end

    subgraph vercel [Vercel]
        WEB["apps/web — Next.js 16"]
    end

    subgraph vps [VPS]
        CADDY["Caddy — reverse proxy + SSL"]
        API["apps/api — AdonisJS 6\nport 3333"]
    end

    subgraph neon [Neon]
        PG[(PostgreSQL 16)]
    end

    DNS --> WEB
    DNS --> CADDY
    BROWSER -->|"hookscope.dev"| WEB
    BROWSER -->|"api.hookscope.dev/*"| CADDY
    STRIPE -->|"POST /i/:id"| CADDY
    WEB -->|"fetch /api + rewrite /hooks"| CADDY
    CADDY --> API
    API --> PG
```

## 0. Dev local — PostgreSQL natif

```bash
# Install
sudo apt install postgresql   # linux
brew install postgresql       # mac

# Create user and database
sudo -u postgres createuser local --pwprompt
sudo -u postgres createdb hookscope --owner=local
```

```env
# .env (racine du monorepo)
DATABASE_URL=postgresql://local:password@localhost:5432/hookscope
```

Migrations :

```bash
cd apps/api
node ace migration:run
```

---

## 1. Production — Neon (PostgreSQL)

1. [neon.tech](https://neon.tech) → compte GitHub
2. Projet **hookscope**
3. Branche `main` (prod)
4. Copier la connection string

```env
DATABASE_URL=postgresql://user:pass@ep-xxx.eu-central-1.aws.neon.tech/hookscope?sslmode=require
```

Migrations à faire depuis le VPS :

```bash
cd apps/api
node ace migration:run
```

## 2. VPS — API AdonisJS

### Prérequis

```bash
# Node.js 22
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.4/install.sh | bash
nvm install 22 && nvm alias default 22

# pnpm
corepack enable && corepack install pnpm@latest

# Caddy
sudo apt install caddy
```

### Setup

```bash
git clone https://github.com/jiordiviera/hookscope
cd hookscope
pnpm install
pnpm build --filter=api
```

Fichier `.env` (racine du monorepo) :

```env
NODE_ENV=production
PORT=3333
HOST=127.0.0.1
APP_KEY=<node ace generate:key>
APP_URL=https://api.hookscope.dev
WEB_URL=https://hookscope.dev
DB_CONNECTION=pg
DATABASE_URL=postgresql://user:pass@ep-xxx.neon.tech/hookscope?sslmode=require
SESSION_DRIVER=cookie
LOG_LEVEL=info

# OAuth
GITHUB_CLIENT_ID=xxx
GITHUB_CLIENT_SECRET=xxx
GOOGLE_CLIENT_ID=xxx
GOOGLE_CLIENT_SECRET=xxx

# Media — Cloudflare R2
DRIVE_DISK=r2
R2_KEY=xxx
R2_SECRET=xxx
R2_BUCKET=xxx
R2_ENDPOINT=xxx
```

### Caddyfile

```caddy
# /etc/caddy/Caddyfile
api.hookscope.dev {
    reverse_proxy 127.0.0.1:3333
}
```

```bash
sudo systemctl reload caddy
```

### systemd — service api

```ini
# /etc/systemd/system/hookscope-api.service
[Unit]
Description=Hookscope API — AdonisJS 6
After=network.target

[Service]
Type=simple
User=deploy
WorkingDirectory=/home/deploy/hookscope/apps/api
ExecStart=/home/deploy/hookscope/apps/api/bin/server.js
Restart=always
RestartSec=5
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now hookscope-api
```

### Script deploy

```bash
#!/usr/bin/env bash
set -e

cd /home/deploy/hookscope

git pull origin main
pnpm install
pnpm build --filter=api
cd apps/api && node ace migration:run --force && cd ../..
sudo systemctl restart hookscope-api
```

## 3. Vercel — Web Next.js

1. [vercel.com](https://vercel.com) → importer le repo
2. Root directory : `apps/web`
3. Framework : Next.js (auto)

### Variables Vercel

```env
NEXT_PUBLIC_API_URL=https://api.hookscope.dev
```

### Rewrite ingest

Le proxy Next.js (`apps/web/proxy.ts`) rewrite `/hooks/:inboxId` vers l'API. En production, la variable `APP_URL` pointe vers le VPS :

```env
# Vercel (server-side, pas publique)
APP_URL=https://api.hookscope.dev
```

### Domaine

Vercel → Domains → `hookscope.dev` + `www.hookscope.dev`

DNS :

| Type | Nom | Valeur |
|------|-----|--------|
| CNAME | `@` | `cname.vercel-dns.com` |
| CNAME | `www` | `cname.vercel-dns.com` |
| A | `api` | IP du VPS |

## CORS (API)

```ts
// apps/api/config/cors.ts
{
  origin: [
    'http://localhost:7777',
    'https://hookscope.dev',
    'https://www.hookscope.dev',
  ],
  credentials: true,
}
```

## Dev local

```bash
pnpm dev   # api :3333 + web :7777
```

## Coûts

| Service | Free tier |
|---------|-----------|
| **Neon** | 0.5 GB, branches limitées |
| **Vercel** | Hobby gratuit |
| **VPS** | ~4–6€/mois (Hetzner CX22) |

**Total MVP solo : ~4–6€/mois**
