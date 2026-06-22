# Product Spec MVP

**Version** : 0.1  
**Horizon** : 3–4 semaines

## Epics & user stories

### Epic A — Inbox & capture

| ID | Story | Priorité |
|----|-------|----------|
| A1 | Visiteur crée une inbox sans compte | P0 |
| A2 | Events arrivent en quasi temps réel (polling 2–3 s) | P0 |
| A3 | Détail event : method, headers, body, query | P0 |
| A4 | Copier URL inbox en un clic | P0 |
| A5 | Inbox anonyme expire après 48h | P1 |

### Epic B — Replay

| ID | Story | Priorité |
|----|-------|----------|
| B1 | Configurer URL de destination replay | P0 |
| B2 | Rejouer un event en un clic | P0 |
| B3 | Voir response status, headers, body | P0 |
| B4 | Voir latence replay (ms) | P1 |
| B5 | Historique replays par event | P0 |
| B6 | Erreur claire si connection refused / timeout | P0 |

### Epic C — Compte & persistance

| ID | Story | Priorité |
|----|-------|----------|
| C1 | Créer un compte pour sauvegarder inboxes | P0 |
| C2 | Inboxes ne expirent plus avec compte | P0 |
| C3 | Lister toutes les inboxes | P0 |
| C4 | Renommer une inbox | P1 |
| C5 | Supprimer une inbox | P1 |

### Epic D — Partage

| ID | Story | Priorité |
|----|-------|----------|
| D1 | Générer lien read-only vers un event | P0 |
| D2 | Collègue voit event + replays sans compte | P0 |

### Epic E — CLI (light)

| ID | Story | Priorité |
|----|-------|----------|
| E1 | Créer inbox via CLI | P1 |
| E2 | Afficher URL pour provider | P1 |
| E3 | Replay par event ID via CLI | P2 |

## Wireframes — Inbox `/i/:inboxId`

```
┌─────────────────────────────────────────────────────────────────┐
│ [←] Stripe Integration    ⚙ Settings    [Share]    [Login]      │
├──────────────────────────┬──────────────────────────────────────┤
│ EVENTS (12)              │  EVENT DETAIL                        │
│ ● POST checkout.session  │  POST /i/xK9m2p                       │
│   2 min ago              │  Headers / Body JSON                 │
│ ○ POST payment_intent    │  [Copy JSON]  [Copy as cURL]         │
├──────────────────────────┴──────────────────────────────────────┤
│ REPLAY                                                          │
│ Destination: [ http://localhost:3000/webhooks/stripe ]          │
│ [ ▶ Replay ]                                                    │
│ ✓ 200 OK  142ms — Response body affiché                         │
│ Replay history: 200 OK, Connection refused                      │
└─────────────────────────────────────────────────────────────────┘
```

## API MVP

### Ingest (public)

```http
POST /i/:inboxId    # + GET, PUT, PATCH, DELETE acceptés
```

- Toujours `200 OK` au provider
- Max body : 1 MB
- Réponse : `{ "received": true }`

### API authentifiée

```http
POST   /api/inboxes
GET    /api/inboxes
PATCH  /api/inboxes/:id
DELETE /api/inboxes/:id
GET    /api/inboxes/:id/events
GET    /api/events/:eventId
POST   /api/events/:eventId/replay
GET    /api/events/:eventId/replays
POST   /api/events/:eventId/share
GET    /api/share/:token
```

### Enveloppe réponse

```json
{
  "success": true,
  "data": {},
  "meta": { "request_id": "req_..." }
}
```

```json
{
  "success": false,
  "error": {
    "code": "CONNECTION_REFUSED",
    "message": "Could not reach http://localhost:3000/webhooks"
  }
}
```

## CLI

```bash
npx @hookscope/cli inbox create
npx @hookscope/cli events list --inbox xK9m2p
npx @hookscope/cli replay evt_01HXXX --to http://localhost:3000/webhook
```

## Limites Free

| Ressource | Anonyme | Compte free |
|-----------|---------|-------------|
| Inboxes | 1 | 3 |
| Events/mois | 50 | 100 |
| Retention | 48h | 7 jours |
| Share links | 1 | 5 |

## Hors scope MVP

- Billing / Stripe
- Multi-users équipe
- Tunnel `hs listen` natif
- Playbooks provider (page docs statique OK)
- Transformations payload
- Forward prod → inbox
- CI fixtures
- Custom domains
- Signature verification helper (Stripe-Signature) → V1.2

## Ordre d'implémentation

```
Semaine 1: Ingest + inbox UI + realtime
Semaine 2: Replay + response view
Semaine 3: Auth + dashboard + share
Semaine 4: CLI light + docs Stripe + polish
```

## Definition of Done

```
Given visiteur sur landing
When curl POST vers inbox URL
Then event visible en < 2s

When replay vers https://httpbin.org/post
Then response 200 + body visible

When création compte
Then inbox conservée dans /dashboard
```