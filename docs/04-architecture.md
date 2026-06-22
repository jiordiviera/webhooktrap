# Architecture technique

## Monorepo

```
hookscope/
├── apps/
│   ├── api/              # AdonisJS 6 — ingest + replay + auth
│   ├── web/              # Next.js 15 — landing, docs, dashboard
│   └── cli/              # Node CLI — hs inbox, hs replay
├── packages/
│   ├── types/            # Contrats TS partagés
│   ├── validators/       # Schémas validation
│   └── sdk/              # Client npm (V1)
├── branding/             # SVG logo
└── docs/                 # Cette documentation
```

## Stack

Voir le document détaillé : [09-stack.md](./09-stack.md).

| Couche | Technologie |
|--------|-------------|
| Monorepo | pnpm + Turborepo |
| API | AdonisJS 6 (TypeScript) |
| Web | Next.js 15 + Tailwind 4 + shadcn/ui |
| ORM | Lucid |
| Validation | VineJS (API) + Zod (web, `packages/validators`) |
| Realtime | Polling (MVP) — pas de Redis |
| Queue | BullMQ — **post-MVP** (+ Upstash si besoin) |
| DB | PostgreSQL 16 — **native ou Neon, pas de Docker** |
| IDs | ULID |
| Tests | Japa (API) + Vitest (web) |
| Deploy | Web → Vercel, API → Railway, DB → Neon |

## Principes

1. **Monolithe modulaire** — une API, services métier clairs
2. **Append-only events** — pas d'event sourcing complet au MVP
3. **API contract-first** — `packages/types` avant implémentation
4. **Ingest < 100ms** — toujours 200 OK au provider
5. **Fail explicit** — erreurs typées (`CONNECTION_REFUSED`, etc.)

## Modèle de données MVP

```
users
teams / team_members (optionnel MVP — peut être user-only)
inboxes              # endpoint unique
events               # payload reçu (method, headers, body, ip…)
replays              # tentative + response status/body/latency
share_tokens         # liens read-only
```

## Flux ingest

```
Provider → POST /i/:inboxId → Store event → 200 OK (< 100ms)
```

## Flux replay

```
User/CLI → POST /api/events/:id/replay { url }
         → Server HTTP POST vers target_url (même payload)
         → Store replay result
         → UI affiche request ↔ response
```

### Limitation MVP — localhost

Le replay part **du serveur Hookscope**. `localhost` ne fonctionne que si :
- L'app est sur une URL **publique** (staging, ngrok, etc.)
- Ou tunnel natif (V2)

À documenter clairement dans l'UI.

## URLs

```
hookscope.dev              → landing
hookscope.dev/i/:id        → inbox
hookscope.dev/share/:token → partage read-only
hookscope.dev/docs         → documentation
api.hookscope.dev          → API (optionnel)
```

## Recyclage PulseSend

| Composant PulseSend | Hookscope |
|---------------------|-----------|
| Multi-tenant / teams | ✓ |
| API keys | ✓ |
| Dashboard Next.js | ✓ |
| Events append-only | ✓ |
| Queues | ✓ (replays async) |
| SMTP / email | ✗ |