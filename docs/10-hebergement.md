# Hébergement — Vercel + Railway + Neon

**Décision validée** : pas de Fly, pas de Docker. Stack managée from scratch.

## Architecture deploy

```mermaid
flowchart TB
    subgraph users [Trafic]
        BROWSER["Navigateur"]
        STRIPE["Stripe / GitHub / …"]
    end

    subgraph vercel [Vercel]
        WEB["apps/web — Next.js 15"]
    end

    subgraph railway [Railway]
        API["apps/api — AdonisJS 6"]
    end

    subgraph neon [Neon]
        PG[(PostgreSQL)]
    end

    BROWSER --> WEB
    BROWSER -->|"fetch /api"| API
    STRIPE -->|"POST /i/:id"| API
    WEB -->|"rewrite /i/*"| API
    API --> PG
```

## Qui fait quoi

| Service | Projet | Rôle | URL cible |
|---------|--------|------|-----------|
| **Vercel** | `hookscope-web` | Landing, inbox UI, dashboard, auth pages | `https://hookscope.dev` |
| **Railway** | `hookscope-api` | Ingest webhooks, replay, auth API, dashboard API | `https://api.hookscope.dev` |
| **Neon** | `hookscope` | Users, inboxes, events, replays | — (connexion interne) |

## Pourquoi ce trio

| Besoin | Solution |
|--------|----------|
| Next.js, previews PR, CDN | **Vercel** |
| Serveur Node **long-running** (ingest < 100ms, replay 30s) | **Railway** |
| PostgreSQL sans install locale | **Neon** |
| Pas de Redis au MVP | — |
| Pas de Docker | Tout est PaaS / DBaaS |

---

## 1. Neon — PostgreSQL

### Setup

1. [neon.tech](https://neon.tech) → compte GitHub
2. Projet **`hookscope`**
3. Branches :
   - `main` → **production**
   - `dev` → développement (optionnel, ou une DB locale)
4. Copier les connection strings

### Variables (exemple)

```env
# Production (Railway)
DATABASE_URL=postgresql://user:pass@ep-xxx.eu-central-1.aws.neon.tech/hookscope?sslmode=require

# Dev local (hookscope/.env à la racine du monorepo)
DATABASE_URL=postgresql://user:pass@ep-yyy.eu-central-1.aws.neon.tech/hookscope_dev?sslmode=require
```

### AdonisJS (`apps/api`)

```env
DB_CONNECTION=pg
# Ou laisser Adonis lire DATABASE_URL directement selon config
```

Migrations : `node ace migration:run` (Railway deploy hook ou manuel).

---

## 2. Railway — API AdonisJS

### Setup

1. [railway.app](https://railway.app) → compte GitHub
2. **New Project** → **Deploy from GitHub repo**
3. Root directory : `apps/api` (quand monorepo prêt)
4. Build : détecté auto (Node) ou :

```json
{
  "build": { "builder": "NIXPACKS" },
  "deploy": {
    "startCommand": "node bin/server.js",
    "healthcheckPath": "/health"
  }
}
```

### Variables Railway (production)

```env
NODE_ENV=production
PORT=3333
HOST=0.0.0.0
APP_KEY=<généré ace generate:key>
DATABASE_URL=<neon production URL>
WEB_ORIGIN=https://hookscope.dev
```

### Domaine custom

Railway → Settings → Networking → **Custom Domain** :
- `api.hookscope.dev` → CNAME fourni par Railway

### Ce qui tourne sur Railway

- `POST /i/:inboxId` — ingest (Stripe tape ici ou via rewrite Vercel)
- `POST /api/events/:id/replay`
- `POST /auth/login`, etc.
- `GET /health`

---

## 3. Vercel — Web Next.js

### Setup

1. [vercel.com](https://vercel.com) → compte GitHub
2. Import repo → root directory : `apps/web`
3. Framework : Next.js (auto)

### Variables Vercel

```env
NEXT_PUBLIC_API_URL=https://api.hookscope.dev
```

### Rewrite ingest (UX propre)

L'utilisateur copie `https://hookscope.dev/i/abc123`, pas l'URL Railway.

```js
// apps/web/next.config.ts
const nextConfig = {
  async rewrites() {
    return [
      {
        source: '/i/:path*',
        destination: `${process.env.APP_URL || 'https://api.hookscope.dev'}/i/:path*`,
      },
    ]
  },
}
```

Variable serveur (non publique) :

```env
APP_URL=https://api.hookscope.dev
```

### Domaine custom

Vercel → Domains → `hookscope.dev` + `www.hookscope.dev`

DNS (Cloudflare ou registrar) :

| Type | Nom | Valeur |
|------|-----|--------|
| A / CNAME | `@` | Vercel |
| CNAME | `www` | Vercel |
| CNAME | `api` | Railway |

---

## Flux dev local (sans Docker)

```env
# hookscope/.env (racine monorepo — partagé api + web)
DATABASE_URL=postgresql://...@neon.tech/hookscope_dev?sslmode=require
APP_KEY=dev-key-min-32-chars-long-xxxx
PORT=3333
NEXT_PUBLIC_API_URL=http://localhost:3333
```

```bash
pnpm dev   # turbo : api :3333 + web :3000
```

Même stack qu'en prod — seule la `DATABASE_URL` change (branche Neon `dev`).

---

## CORS (API Railway)

```ts
// apps/api — config/cors.ts
{
  origin: [
    'http://localhost:3000',
    'https://hookscope.dev',
    'https://www.hookscope.dev',
  ],
  credentials: true,
}
```

---

## Coûts estimés (début)

| Service | Free tier | Après |
|---------|-----------|-------|
| **Neon** | 0.5 GB, branches limitées | ~$19/mois scale |
| **Railway** | $5 crédit/mois (plan hobby) | ~$5–20/mois selon usage |
| **Vercel** | Hobby gratuit | $20/mois Pro si équipe |

**MVP solo** : souvent **$0–10/mois** total.

---

## Checklist premier deploy

```
Neon
[ ] Projet hookscope créé
[ ] Branche main (prod) + dev
[ ] DATABASE_URL copiée

Railway
[ ] Repo connecté, root apps/api
[ ] DATABASE_URL + APP_KEY + WEB_ORIGIN
[ ] Domain api.hookscope.dev
[ ] GET /health → 200
[ ] migration:run OK

Vercel
[ ] Repo connecté, root apps/web
[ ] NEXT_PUBLIC_API_URL + API_URL (rewrite)
[ ] Domain hookscope.dev
[ ] Rewrite /i/* → API testé avec curl

E2E
[ ] curl POST hookscope.dev/i/xxx → event en DB Neon
[ ] Inbox UI affiche l'event (polling)
```

---

## Ce qu'on n'utilise pas

- Fly.io
- Docker / docker-compose
- Redis (MVP)
- Supabase (sauf si tu changes d'avis sur PG)
- Hébergement API sur Vercel (serverless incompatible replay long)